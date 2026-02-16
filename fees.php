<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

// Handle fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $student_id = intval($_POST['student_id']);
    $fee_type_id = intval($_POST['fee_type_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = sanitize($_POST['payment_date']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    $receipt_number = 'RCP' . time() . rand(100, 999);
    
    $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, fee_type_id, amount_paid, payment_date, payment_method, term, receipt_number, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsssssi", $student_id, $fee_type_id, $amount_paid, $payment_date, $payment_method, $term, $receipt_number, $remarks, $user['id']);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle payment edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $payment_id = intval($_POST['payment_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    
    $stmt = $conn->prepare("UPDATE fee_payments SET amount_paid = ?, payment_method = ?, term = ?, remarks = ? WHERE id = ?");
    $stmt->bind_param("dsssi", $amount_paid, $payment_method, $term, $remarks, $payment_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle payment delete
if (isset($_GET['delete_payment']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $payment_id = intval($_GET['delete_payment']);
    
    $stmt = $conn->prepare("DELETE FROM fee_payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle grade-based fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment_grade']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $grade = sanitize($_POST['grade']);
    $fee_type_id = intval($_POST['fee_type_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = sanitize($_POST['payment_date']);
    $payment_method = sanitize($_POST['payment_method']);
    $term = sanitize($_POST['term']);
    $remarks = sanitize($_POST['remarks']);
    
    // Get all students in the grade
    $stmt = $conn->prepare("SELECT id FROM students WHERE grade = ? AND status = 'Active'");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $grade_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Add payment for each student
    foreach ($grade_students as $student) {
        $receipt_number = 'RCP' . time() . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, fee_type_id, amount_paid, payment_date, payment_method, term, receipt_number, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsssssi", $student['id'], $fee_type_id, $amount_paid, $payment_date, $payment_method, $term, $receipt_number, $remarks, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle fee type addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee_type']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $fee_name = sanitize($_POST['fee_name']);
    $fee_amount = floatval($_POST['fee_amount']);
    $fee_grade = sanitize($_POST['fee_grade']);
    
    $stmt = $conn->prepare("INSERT INTO fee_types (fee_name, amount, grade) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $fee_name, $fee_amount, $fee_grade);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle fee type edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fee_type']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $fee_type_id = intval($_POST['fee_type_id']);
    $fee_name = sanitize($_POST['fee_name']);
    $fee_amount = floatval($_POST['fee_amount']);
    $fee_grade = sanitize($_POST['fee_grade']);
    
    $stmt = $conn->prepare("UPDATE fee_types SET fee_name = ?, amount = ?, grade = ? WHERE id = ?");
    $stmt->bind_param("sdsi", $fee_name, $fee_amount, $fee_grade, $fee_type_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle fee type delete
if (isset($_GET['delete_fee_type']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $fee_type_id = intval($_GET['delete_fee_type']);
    
    $stmt = $conn->prepare("DELETE FROM fee_types WHERE id = ?");
    $stmt->bind_param("i", $fee_type_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: fees.php?success=1');
    exit();
}

// Handle fee type assignment to grade or all students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_fee']) && in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    $fee_type_id = intval($_POST['fee_type_id']);
    $assign_to = sanitize($_POST['assign_to']); // 'all' or grade number
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));
    
    // Get the fee amount
    $stmt = $conn->prepare("SELECT amount FROM fee_types WHERE id = ?");
    $stmt->bind_param("i", $fee_type_id);
    $stmt->execute();
    $fee_type = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$fee_type) {
        header('Location: fees.php?error=1');
        exit();
    }
    
    if ($assign_to === 'all') {
        // Assign to all active students
        $stmt = $conn->prepare("SELECT id FROM students WHERE status = 'Active'");
        $stmt->execute();
        $students_to_assign = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        // Assign to specific grade
        $stmt = $conn->prepare("SELECT id FROM students WHERE grade = ? AND status = 'Active'");
        $stmt->bind_param("s", $assign_to);
        $stmt->execute();
        $students_to_assign = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // Assign fee to each student with 0 paid initially
    $assigned = 0;
    foreach ($students_to_assign as $student) {
        // Check if already assigned
        $check = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_type_id = ?");
        $check->bind_param("ii", $student['id'], $fee_type_id);
        $check->execute();
        $existing = $check->get_result();
        $check->close();
        
        if ($existing->num_rows > 0) {
            continue; // Skip if already assigned
        }
        
        // Create student fee record with amount_due = fee_amount, amount_paid = 0
        $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_type_id, amount_due, amount_paid, status, due_date) VALUES (?, ?, ?, 0.00, 'pending', ?)");
        $stmt->bind_param("iids", $student['id'], $fee_type_id, $fee_type['amount'], $due_date);
        
        if ($stmt->execute()) {
            $assigned++;
        }
        $stmt->close();
    }
    
    header('Location: fees.php?success=2&assigned=' . $assigned);
    exit();
}

// Handle print functionality and percentage filter
if (isset($_GET['print_students']) && isset($_GET['percentage_min']) && isset($_GET['percentage_max'])) {
    $percentage_min = floatval($_GET['percentage_min']);
    $percentage_max = floatval($_GET['percentage_max']);
    $grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
    
    $students = getStudentsByFeePercentageAndGrade($percentage_min, $percentage_max, $grade_filter);
    
    // Generate print-friendly PDF/HTML
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Student List - Fee Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #000; padding: 10px; text-align: left; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .footer { margin-top: 40px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <h1>Student Fee Payment Report</h1>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>Filter:</strong> Fee Percentage <?php echo $percentage_min; ?>% - <?php echo $percentage_max; ?>%
        <?php if ($grade_filter): ?>
            | Grade <?php echo htmlspecialchars($grade_filter); ?>
        <?php endif; ?>
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Grade</th>
                    <th>Fee Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['grade']); ?></td>
                        <td><?php echo number_format($student['fee_percentage'], 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total Students: <?php echo count($students); ?></p>
            <p><button onclick="window.print()">Print This Page</button></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get data based on role
if ($role === 'Student') {
    $student = getStudentByUserId($user['id']);
    if (!$student) {
        header('Location: login.php');
        exit();
    }
    $fee_percentage = calculateFeePercentage($student['id']);
    
    // Get student's payments
    $stmt = $conn->prepare("SELECT fp.*, ft.fee_name, ft.amount as fee_amount FROM fee_payments fp JOIN fee_types ft ON fp.fee_type_id = ft.id WHERE fp.student_id = ? ORDER BY fp.payment_date DESC");
    $stmt->bind_param("i", $student['id']);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Get all students for payment form
    $students = getStudents();
    
    // Get all payments with filters
    $grade_filter = isset($_GET['grade']) ? sanitize($_GET['grade']) : null;
    $term_filter = isset($_GET['term']) ? sanitize($_GET['term']) : null;
    
    $sql = "SELECT fp.*, ft.fee_name, s.student_id, s.grade, u.full_name as student_name FROM fee_payments fp JOIN fee_types ft ON fp.fee_type_id = ft.id JOIN students s ON fp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE 1=1";
    
    if ($grade_filter) {
        $sql .= " AND s.grade = '" . $conn->real_escape_string($grade_filter) . "'";
    }
    
    if ($term_filter) {
        $sql .= " AND fp.term = '" . $conn->real_escape_string($term_filter) . "'";
    }
    
    $sql .= " ORDER BY fp.payment_date DESC LIMIT 100";
    
    $payments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Get fee types
$fee_types = $conn->query("SELECT * FROM fee_types WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1><?php echo $role === 'Student' ? 'My Fees' : 'Fee Management'; ?></h1>
                <?php if (in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])): ?>
                    <button class="btn btn-primary" onclick="togglePaymentForm()">Add Payment</button>
                    <button class="btn btn-primary" onclick="toggleFeeTypeForm()">Add Fee Type</button>
                    <button class="btn btn-primary" onclick="toggleAssignFeeForm()">Assign Fee</button>
                    <button class="btn btn-primary" onclick="toggleFilterForm()">Filter & Print</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php if ($_GET['success'] === '2' && isset($_GET['assigned'])): ?>
                        Fee assigned to <?php echo intval($_GET['assigned']); ?> students successfully!
                    <?php else: ?>
                        Payment recorded successfully!
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">An error occurred. Please try again.</div>
            <?php endif; ?>

            <?php if ($role === 'Student'): ?>
                <!-- Student Fee Summary -->
                <div class="stats-grid" style="margin-bottom: 24px;">
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3><?php echo $fee_percentage; ?>%</h3>
                            <p>Fees Paid</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?php echo count($payments); ?></h3>
                            <p>Total Payments</p>
                        </div>
                    </div>
                </div>

                <!-- M-Pesa Payment Section -->
                <div class="table-container" style="margin-bottom: 24px; background: #f0f9ff; border-left: 4px solid #00bf63;">
                    <div class="table-header" style="background: #00bf63; color: white;">
                        <h3>Pay via M-Pesa</h3>
                    </div>
                    <div style="padding: 24px;">
                        <p style="margin-bottom: 16px;">
                            <strong>School Paybill Number:</strong> <span style="font-size: 18px; color: #00bf63; font-weight: bold;">0758955122</span>
                        </p>
                        <p style="color: #666; margin-bottom: 16px;">
                            You can initiate an M-Pesa payment below. A prompt will appear on your phone asking you to enter your M-Pesa PIN to authorize the payment.
                        </p>
                        
                        <div class="form-group" style="max-width: 400px;">
                            <label for="mpesa_phone">Your Phone Number (254...)</label>
                            <input type="tel" id="mpesa_phone" placeholder="e.g., 2547XXXXXXXX" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; margin-bottom: 8px;">
                        </div>
                        
                        <button type="button" class="btn" style="background: #00bf63; color: white; padding: 12px 24px;" onclick="initiateMPesaPayment()">
                            Send STK Prompt
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])): ?>
                
                <!-- Filter & Print Form -->
                <div id="filterForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Filter Students by Fee Percentage</h3>
                    </div>
                    <form method="GET" style="padding: 24px;">
                        <div class="filter-section">
                            <div class="form-group" style="flex: 0.5;">
                                <label for="percentage_min">Min Percentage (%)</label>
                                <input type="number" id="percentage_min" name="percentage_min" min="0" max="100" step="5" value="0" placeholder="0">
                            </div>
                            
                            <div class="form-group" style="flex: 0.5;">
                                <label for="percentage_max">Max Percentage (%)</label>
                                <input type="number" id="percentage_max" name="percentage_max" min="0" max="100" step="5" value="100" placeholder="100">
                            </div>
                            
                            <div class="form-group" style="flex: 0.8;">
                                <label for="filter_grade">Grade</label>
                                <select id="filter_grade" name="grade">
                                    <option value="">All Grades</option>
                                    <optgroup label="Lower Class Primary (Grade 1-3)">
                                        <option value="1">Grade 1</option>
                                        <option value="2">Grade 2</option>
                                        <option value="3">Grade 3</option>
                                    </optgroup>
                                    <optgroup label="Upper Class Primary (Grade 4-6)">
                                        <option value="4">Grade 4</option>
                                        <option value="5">Grade 5</option>
                                        <option value="6">Grade 6</option>
                                    </optgroup>
                                    <optgroup label="Junior School (Grade 7-9)">
                                        <option value="7">Grade 7</option>
                                        <option value="8">Grade 8</option>
                                        <option value="9">Grade 9</option>
                                    </optgroup>
                                </select>
                            </div>
                            
                            <button type="submit" name="print_students" value="1" class="btn btn-primary">Print List</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleFilterForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Add Payment Form -->
                <div id="paymentForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Record Payment</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <div class="form-group">
                            <label for="student_id">Student *</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name']) . ' - ' . htmlspecialchars($s['student_id']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_type_id">Fee Type *</label>
                            <select id="fee_type_id" name="fee_type_id" required>
                                <option value="">Select Fee Type</option>
                                <?php foreach ($fee_types as $ft): ?>
                                    <option value="<?php echo $ft['id']; ?>"><?php echo htmlspecialchars($ft['fee_name']) . ' - /= ' . number_format($ft['amount'], 2); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount_paid">Amount Paid *</label>
                            <input type="number" id="amount_paid" name="amount_paid" step="0.01" required placeholder="Enter amount">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_date">Payment Date *</label>
                            <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="term">Term *</label>
                            <select id="term" name="term" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                                <option value="Full Year">Full Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_payment" class="btn btn-primary">Record Payment</button>
                            <button type="button" class="btn btn-secondary" onclick="togglePaymentForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Add Fee Type Form -->
                <div id="feeTypeForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Add Fee Type</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <div class="form-group">
                            <label for="fee_name">Fee Name *</label>
                            <input type="text" id="fee_name" name="fee_name" required placeholder="Enter fee name">
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_amount">Fee Amount *</label>
                            <input type="number" id="fee_amount" name="fee_amount" step="0.01" required placeholder="Enter fee amount">
                        </div>
                        
                        <div class="form-group">
                            <label for="fee_grade">Grade *</label>
                            <select id="fee_grade" name="fee_grade" required>
                                <option value="">Select Grade</option>
                                <!-- Added grades 1-9 with school classifications -->
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                </optgroup>
                                <option value="All">All Grades</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_fee_type" class="btn btn-primary">Add Fee Type</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleFeeTypeForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Assign Fee Form -->
                <div id="assignFeeForm" class="table-container" style="display: none; margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Assign Fee to Students</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <p style="color: #666; margin-bottom: 16px; font-size: 14px;">
                            <strong>Note:</strong> When you assign a fee to students, each student gets 0 paid amount initially. 
                            You can later update the amount paid as students make payments.
                        </p>
                        
                        <div class="form-group">
                            <label for="assign_fee_type_id">Fee Type *</label>
                            <select id="assign_fee_type_id" name="fee_type_id" required>
                                <option value="">Select Fee Type</option>
                                <?php foreach ($fee_types as $ft): ?>
                                    <option value="<?php echo $ft['id']; ?>">
                                        <?php echo htmlspecialchars($ft['fee_name']) . ' - /= ' . number_format($ft['amount'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Assign To *</label>
                            <div style="margin-bottom: 12px;">
                                <label style="display: flex; align-items: center; margin-bottom: 8px; cursor: pointer;">
                                    <input type="radio" name="assign_to" value="all" checked style="margin-right: 8px;">
                                    <span>All Active Students</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="radio" name="assign_to" id="assign_grade_option" style="margin-right: 8px;">
                                    <span>Specific Grade</span>
                                </label>
                            </div>
                            <select id="assign_fee_grade" name="assign_to" style="display: none; margin-top: 8px;">
                                <option value="">Select Grade</option>
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assign_due_date">Due Date</label>
                            <input type="date" id="assign_due_date" name="due_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="assign_fee" class="btn btn-primary">Assign Fee</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleAssignFeeForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Fee Types Management Table -->
                <div class="table-container" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Fee Types Management</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fee Name</th>
                                    <th>Amount</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fee_types as $ft): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ft['fee_name']); ?></td>
                                        <td>/= <?php echo number_format($ft['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($ft['grade']); ?></td>
                                        <td><?php echo $ft['is_active'] ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>'; ?></td>
                                        <td>
                                            <button class="btn btn-sm" style="background: #339af0; color: white;" onclick="editFeeType(<?php echo $ft['id']; ?>, '<?php echo addslashes(htmlspecialchars($ft['fee_name'])); ?>', <?php echo $ft['amount']; ?>, '<?php echo htmlspecialchars($ft['grade']); ?>')">Edit</button>
                                            <a href="?delete_fee_type=<?php echo $ft['id']; ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this fee type?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($fee_types)): ?>
                                    <tr><td colspan="5" style="text-align: center;">No fee types added yet</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Fee Type Modal Form -->
                <div id="editFeeTypeForm" class="table-container" style="display: none; margin-bottom: 24px; background: #e7f5ff; border: 2px solid #339af0;">
                    <div class="table-header" style="background: #339af0; color: white;">
                        <h3>Edit Fee Type</h3>
                    </div>
                    <form method="POST" style="padding: 24px;">
                        <input type="hidden" id="edit_fee_type_id" name="fee_type_id">
                        <div class="form-group">
                            <label for="edit_fee_name">Fee Name *</label>
                            <input type="text" id="edit_fee_name" name="fee_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_fee_amount">Fee Amount *</label>
                            <input type="number" id="edit_fee_amount" name="fee_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_fee_grade">Grade *</label>
                            <select id="edit_fee_grade" name="fee_grade" required>
                                <option value="">Select Grade</option>
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                </optgroup>
                                <option value="All">All Grades</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="edit_fee_type" class="btn btn-primary">Update Fee Type</button>
                            <button type="button" class="btn btn-secondary" onclick="closeFeeTypeEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($role !== 'Student'): ?>
                <!-- Filters -->
                <div class="table-container" style="margin-bottom: 24px;">
                    <form method="GET" style="padding: 16px; display: flex; gap: 16px; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin: 0;">
                            <label for="grade">Filter by Grade</label>
                            <select id="grade" name="grade" onchange="this.form.submit()">
                                <option value="">All Grades</option>
                                <!-- Added grades 1-9 for filtering -->
                                <optgroup label="Lower Class Primary (Grade 1-3)">
                                    <option value="1" <?php echo isset($_GET['grade']) && $_GET['grade'] === '1' ? 'selected' : ''; ?>>Grade 1</option>
                                    <option value="2" <?php echo isset($_GET['grade']) && $_GET['grade'] === '2' ? 'selected' : ''; ?>>Grade 2</option>
                                    <option value="3" <?php echo isset($_GET['grade']) && $_GET['grade'] === '3' ? 'selected' : ''; ?>>Grade 3</option>
                                </optgroup>
                                <optgroup label="Upper Class Primary (Grade 4-6)">
                                    <option value="4" <?php echo isset($_GET['grade']) && $_GET['grade'] === '4' ? 'selected' : ''; ?>>Grade 4</option>
                                    <option value="5" <?php echo isset($_GET['grade']) && $_GET['grade'] === '5' ? 'selected' : ''; ?>>Grade 5</option>
                                    <option value="6" <?php echo isset($_GET['grade']) && $_GET['grade'] === '6' ? 'selected' : ''; ?>>Grade 6</option>
                                </optgroup>
                                <optgroup label="Junior School (Grade 7-9)">
                                    <option value="7" <?php echo isset($_GET['grade']) && $_GET['grade'] === '7' ? 'selected' : ''; ?>>Grade 7</option>
                                    <option value="8" <?php echo isset($_GET['grade']) && $_GET['grade'] === '8' ? 'selected' : ''; ?>>Grade 8</option>
                                    <option value="9" <?php echo isset($_GET['grade']) && $_GET['grade'] === '9' ? 'selected' : ''; ?>>Grade 9</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label for="term">Filter by Term</label>
                            <select id="term" name="term" onchange="this.form.submit()">
                                <option value="">All Terms</option>
                                <option value="1" <?php echo isset($_GET['term']) && $_GET['term'] === '1' ? 'selected' : ''; ?>>Term 1</option>
                                <option value="2" <?php echo isset($_GET['term']) && $_GET['term'] === '2' ? 'selected' : ''; ?>>Term 2</option>
                                <option value="3" <?php echo isset($_GET['term']) && $_GET['term'] === '3' ? 'selected' : ''; ?>>Term 3</option>
                                <option value="Full Year" <?php echo isset($_GET['term']) && $_GET['term'] === 'Full Year' ? 'selected' : ''; ?>>Full Year</option>
                            </select>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Payments Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Payment History (<?php echo count($payments); ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($role !== 'Student'): ?>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Grade</th>
                                <?php endif; ?>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Method</th>
                                <th>Term</th>
                                <th>Receipt No.</th>
                                <?php if ($role !== 'Student'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <?php if ($role !== 'Student'): ?>
                                        <td><?php echo htmlspecialchars($payment['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                        <td>Grade <?php echo htmlspecialchars($payment['grade']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                    <td>/= <?php echo number_format($payment['amount_paid'], 2); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['term']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                    <?php if ($role !== 'Student'): ?>
                                        <td>
                                            <button class="btn btn-sm" onclick="editPayment(<?php echo $payment['id']; ?>)">Edit</button>
                                            <a href="?delete_payment=<?php echo $payment['id']; ?>" class="btn btn-sm" style="background-color: #dc3545;" onclick="return confirm('Delete this payment?')">Delete</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="<?php echo $role === 'Student' ? '5' : '10'; ?>" style="text-align: center;">No payments found</td>
                                </tr>
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
        function toggleFilterForm() {
            const form = document.getElementById('filterForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function togglePaymentForm() {
            const form = document.getElementById('paymentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function toggleFeeTypeForm() {
            const form = document.getElementById('feeTypeForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function toggleAssignFeeForm() {
            const form = document.getElementById('assignFeeForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Handle assign grade option toggle
        document.querySelectorAll('input[name="assign_to"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const gradeSelect = document.getElementById('assign_fee_grade');
                const gradeRadio = document.getElementById('assign_grade_option');
                
                if (gradeRadio && gradeRadio.checked) {
                    gradeSelect.style.display = 'block';
                    gradeSelect.name = 'assign_to'; // Change name to submit
                } else {
                    gradeSelect.style.display = 'none';
                    gradeSelect.name = 'assign_to_grade'; // Prevent submission
                }
            });
        });

        // M-Pesa Payment Function
        function initiateMPesaPayment() {
            const phone = document.getElementById('mpesa_phone').value;
            
            if (!phone) {
                alert('Please enter your phone number');
                return;
            }
            
            if (!phone.startsWith('254')) {
                alert('Phone number must start with 254 (e.g., 2547XXXXXXXX)');
                return;
            }
            
            // Disable button and show processing
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            // Call M-Pesa API
            fetch('api/mpesa_stk.php?action=initiate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'phone_number=' + encodeURIComponent(phone) + '&student_fee_id=0'
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Send STK Prompt';
                
                if (data.success) {
                    alert('STK prompt sent to ' + phone + '. Check your phone to complete the payment.');
                    document.getElementById('mpesa_phone').value = '';
                } else {
                    alert('Error: ' + (data.error || 'Failed to send STK prompt'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.textContent = 'Send STK Prompt';
                alert('An error occurred. Please try again.');
            });
        }

        function editFeeType(id, name, amount, grade) {
            document.getElementById('edit_fee_type_id').value = id;
            document.getElementById('edit_fee_name').value = name;
            document.getElementById('edit_fee_amount').value = amount;
            document.getElementById('edit_fee_grade').value = grade;
            document.getElementById('editFeeTypeForm').style.display = 'block';
        }

        function closeFeeTypeEdit() {
            document.getElementById('editFeeTypeForm').style.display = 'none';
        }

        function editPayment(paymentId) {
            alert('Edit functionality coming soon');
        }
    </script>
</body>
</html>
