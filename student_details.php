<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id <= 0) {
    header('Location: students.php');
    exit();
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$success_msg = '';
$error_msg = '';

// Get student details with user info
$query = "SELECT s.id, s.student_id, s.admission_number, s.grade, s.stream, 
                 s.status, s.admission_date, s.admission_year,
                 s.parent_name, s.parent_phone, s.parent_email, s.address, s.date_of_birth,
                 u.id as user_id, u.full_name, u.email, u.profile_image
          FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE s.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: students.php');
    exit();
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === 0) {
        $file = $_FILES['student_photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $error_msg = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error_msg = "File size exceeds 5MB limit.";
        } else {
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/student_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $student_id . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update profile image in database
                $relative_path = $filename;
                $photo_update = "UPDATE users SET profile_image = ? WHERE id = ?";
                $photo_stmt = $conn->prepare($photo_update);
                
                if ($photo_stmt) {
                    $photo_stmt->bind_param("si", $relative_path, $student['user_id']);
                    if ($photo_stmt->execute()) {
                        // Refresh student data
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $student = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        $success_msg = "Student photo updated successfully!";
                    } else {
                        $error_msg = "Failed to update photo in database.";
                    }
                    $photo_stmt->close();
                } else {
                    $error_msg = "Database error: " . $conn->error;
                }
            } else {
                $error_msg = "Failed to upload photo. Please try again.";
            }
        }
    } else {
        $error_msg = "No file selected or upload error occurred.";
    }
}

// Handle form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $parent_name = sanitize($_POST['parent_name'] ?? '');
    $parent_phone = sanitize($_POST['parent_phone'] ?? '');
    $parent_email = sanitize($_POST['parent_email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $date_of_birth = isset($_POST['date_of_birth']) && !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : NULL;
    
    // Update student information
    $update_query = "UPDATE students SET parent_name = ?, parent_phone = ?, parent_email = ?, address = ?, date_of_birth = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        $error_msg = "Database error: " . $conn->error;
    } else {
        $update_stmt->bind_param("sssssi", $parent_name, $parent_phone, $parent_email, $address, $date_of_birth, $student_id);
        
        if ($update_stmt->execute()) {
            // Refresh student data
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $success_msg = "Student information updated successfully!";
        } else {
            $error_msg = "Failed to update: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Get exam results
$exam_results = [];
$exam_query = "SELECT er.id, er.exam_id, er.marks_obtained, 
                      e.exam_name, e.exam_type, e.total_marks, e.exam_date
               FROM exam_results er 
               JOIN exams e ON er.exam_id = e.id 
               WHERE er.student_id = ? 
               ORDER BY e.exam_date DESC";

$exam_stmt = $conn->prepare($exam_query);
if ($exam_stmt) {
    $exam_stmt->bind_param("i", $student_id);
    $exam_stmt->execute();
    $exam_results = $exam_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $exam_stmt->close();
}

// Get fee information - safely handle results
$fee_percentage = 0;
$total_fees = 100000;

// Check if fees table exists first
$fee_check = $conn->query("SHOW TABLES LIKE 'fees'");
if ($fee_check && $fee_check->num_rows > 0) {
    $fee_query = "SELECT SUM(amount_paid) as total_paid FROM fees WHERE student_id = ?";
    $fee_stmt = $conn->prepare($fee_query);
    if ($fee_stmt) {
        $fee_stmt->bind_param("i", $student_id);
        $fee_stmt->execute();
        $fee_result = $fee_stmt->get_result()->fetch_assoc();
        if ($fee_result && $fee_result['total_paid'] > 0) {
            $fee_percentage = min(100, round(($fee_result['total_paid'] / $total_fees) * 100));
        }
        $fee_stmt->close();
    }
}

// Get clubs
$clubs = [];
$club_check = $conn->query("SHOW TABLES LIKE 'clubs'");
if ($club_check && $club_check->num_rows > 0) {
    $club_query = "SELECT c.club_name, cm.role 
                  FROM clubs c 
                  JOIN club_members cm ON c.id = cm.club_id 
                  WHERE cm.student_id = ?";
    $club_stmt = $conn->prepare($club_query);
    if ($club_stmt) {
        $club_stmt->bind_param("i", $student_id);
        $club_stmt->execute();
        $clubs = $club_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $club_stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - School Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Student Details</h1>
                <a href="students.php" class="btn btn-secondary">Back to Students</a>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Student Information Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Personal Information</h3>
                    <button class="btn btn-primary" onclick="toggleEditForm()">Edit Information</button>
                </div>
                
                <!-- Edit Form -->
                <div id="editForm" style="display: none; padding: 24px; border-bottom: 1px solid #ddd; background: var(--bg-secondary);">
                    <!-- Photo Upload Section -->
                    <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0;">Update Student Photo</h4>
                        <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: flex-end;">
                            <div class="form-group" style="flex: 1;">
                                <label for="student_photo">Select Photo (JPG, PNG, GIF - Max 5MB)</label>
                                <input type="file" id="student_photo" name="student_photo" accept="image/*" required style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; width: 100%;">
                            </div>
                            <button type="submit" name="upload_photo" class="btn btn-primary" style="padding: 10px 16px; white-space: nowrap;">Upload Photo</button>
                        </form>
                    </div>

                    <!-- Edit Information Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo isset($student['date_of_birth']) && $student['date_of_birth'] ? $student['date_of_birth'] : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="parent_name">Parent/Guardian Name *</label>
                                <input type="text" id="parent_name" name="parent_name" value="<?php echo isset($student['parent_name']) ? htmlspecialchars($student['parent_name']) : ''; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="parent_phone">Parent Phone *</label>
                                <input type="tel" id="parent_phone" name="parent_phone" value="<?php echo isset($student['parent_phone']) ? htmlspecialchars($student['parent_phone']) : ''; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="parent_email">Parent Email *</label>
                                <input type="email" id="parent_email" name="parent_email" value="<?php echo isset($student['parent_email']) ? htmlspecialchars($student['parent_email']) : ''; ?>" required>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3"><?php echo isset($student['address']) ? htmlspecialchars($student['address']) : ''; ?></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="edit_student" class="btn btn-primary">Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEditForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Display Information -->
                <div style="padding: 24px;">
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 30px; align-items: start;">
                        <div style="text-align: center;">
                            <?php if (!empty($student['profile_image']) && $student['profile_image'] !== 'default-avatar.png'): ?>
                                <img src="uploads/student_photos/<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Student Photo" style="width: 150px; height: 150px; border-radius: 8px; object-fit: cover; border: 2px solid #ddd;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div style="display: none; width: 150px; height: 150px; border-radius: 8px; background: #f0f0f0; align-items: center; justify-content: center; border: 2px solid #ddd;">
                                    <span style="color: #999;">No Photo</span>
                                </div>
                            <?php else: ?>
                                <div style="width: 150px; height: 150px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px solid #ddd;">
                                    <span style="color: #999;">No Photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                <div>
                                    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                                    <p><strong>Admission Number:</strong> <?php echo isset($student['admission_number']) ? htmlspecialchars($student['admission_number']) : '-'; ?></p>
                                    <p><strong>Grade:</strong> Grade <?php echo htmlspecialchars($student['grade']); ?></p>
                                    <p><strong>Stream:</strong> <?php echo isset($student['stream']) ? htmlspecialchars($student['stream']) : '-'; ?></p>
                                </div>
                                <div>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo isset($student['date_of_birth']) && $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : '-'; ?></p>
                                    <p><strong>Status:</strong> <span style="background: <?php echo $student['status'] === 'Active' ? '#d4edda' : '#fff3cd'; ?>; padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($student['status']); ?></span></p>
                                    <p><strong>Admission Date:</strong> <?php echo date('M d, Y', strtotime($student['admission_date'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent/Guardian Information -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Parent/Guardian Information</h3>
                </div>
                <div style="padding: 24px;">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['parent_phone'] ?? '-'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['parent_email'] ?? '-'); ?></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo count($exam_results); ?></h3>
                        <p>Exams Taken</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo $fee_percentage; ?>%</h3>
                        <p>Fees Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎭</div>
                    <div class="stat-info">
                        <h3><?php echo count($clubs); ?></h3>
                        <p>Clubs Joined</p>
                    </div>
                </div>
            </div>

            <!-- Exam Results -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Exam Results</h3>
                    <?php if (!empty($exam_results)): ?>
                        <button class="btn btn-primary" onclick="printExamResults()">Print Results</button>
                    <?php endif; ?>
                </div>
                <div id="examResultsContent">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Exam Type</th>
                                    <th>Date</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($exam_results)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px;">No exam results yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($exam_results as $result): 
                                        $percentage = ($result['marks_obtained'] / $result['total_marks']) * 100;
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($result['exam_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($result['exam_type']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></td>
                                            <td><?php echo number_format($result['marks_obtained'], 2); ?></td>
                                            <td><?php echo $result['total_marks']; ?></td>
                                            <td><strong><?php echo round($percentage, 2) . '%'; ?></strong></td>
                                            <td><button class="btn btn-sm" onclick="viewExamDetails(<?php echo $result['id']; ?>, '<?php echo htmlspecialchars($result['exam_name']); ?>')">View Details</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fees Paid Section -->
            <div class="table-container" style="margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Fees Paid</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fee Type</th>
                                <th>Expected Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $student_fees_query = "SELECT sf.*, ft.fee_name, ft.amount as expected_amount FROM student_fees sf JOIN fee_types ft ON sf.fee_type_id = ft.id WHERE sf.student_id = ? ORDER BY ft.fee_name";
                            $fees_stmt = $conn->prepare($student_fees_query);
                            $student_fees = [];
                            if ($fees_stmt) {
                                $fees_stmt->bind_param("i", $student_id);
                                $fees_stmt->execute();
                                $student_fees = $fees_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                $fees_stmt->close();
                            }
                            
                            if (empty($student_fees)): 
                            ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">No fees assigned yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_fees as $fee): 
                                    $balance = $fee['expected_amount'] - $fee['amount_paid'];
                                    $status_color = $balance <= 0 ? '#d4edda' : ($balance < ($fee['expected_amount'] * 0.25) ? '#fff3cd' : '#f8d7da');
                                    $status_text = $balance <= 0 ? 'Paid' : 'Pending';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                        <td>/= <?php echo number_format($fee['expected_amount'], 2); ?></td>
                                        <td>/= <?php echo number_format($fee['amount_paid'], 2); ?></td>
                                        <td>/= <?php echo number_format($balance, 2); ?></td>
                                        <td><span style="background: <?php echo $status_color; ?>; padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?php echo $status_text; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Clubs -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Clubs Joined</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Club Name</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clubs)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center;">Not a member of any clubs</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clubs as $club): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                        <td><?php echo htmlspecialchars($club['role']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleEditForm() {
            const form = document.getElementById('editForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function printExamResults() {
            const printWindow = window.open('', '_blank');
            const content = document.getElementById('examResultsContent').innerHTML;
            const studentName = '<?php echo htmlspecialchars($student['full_name']); ?>';
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Exam Results - ${studentName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { text-align: center; margin-bottom: 10px; }
                        .info { text-align: center; margin-bottom: 20px; color: #666; font-size: 14px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #000; padding: 10px; text-align: left; }
                        th { background-color: #f0f0f0; font-weight: bold; }
                        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #999; }
                    </style>
                </head>
                <body>
                    <h1>Exam Results Report</h1>
                    <div class="info">
                        <p><strong>Student:</strong> ${studentName}</p>
                        <p><strong>Date Printed:</strong> ${new Date().toLocaleString()}</p>
                    </div>
                    ${content}
                    <div class="footer">
                        <p>This is an official school document.</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        function viewExamDetails(examId, examName) {
            // Redirect to exam results page for detailed view
            window.location.href = 'exam_results.php?id=' + examId;
        }

        function toggleEditForm() {
            const form = document.getElementById('editForm');
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        }
    </script>
</body>
</html>
