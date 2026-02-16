<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireRole(['Admin', 'HOI', 'DHOI', 'DoS_Exams_Teacher', 'Social_Affairs_Teacher', 'Finance_Teacher', 'Teacher']);

$user = getCurrentUser();
$conn = getDBConnection();

$subjects = ['English', 'Kiswahili', 'Mathematics', 'Integrated Science', 'CRE', 'CA&S', 'Pre-technical Studies', 'Social Studies', 'Agriculture'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $exam_name = sanitize($_POST['exam_name']);
    $exam_type = sanitize($_POST['exam_type']);
    $grade = sanitize($_POST['grade']);
    $exam_date = sanitize($_POST['exam_date']);
    $total_marks = intval($_POST['total_marks']);
    
    $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, grade, total_marks, exam_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisi", $exam_name, $exam_type, $grade, $total_marks, $exam_date, $user['id']);
    $stmt->execute();
    $exam_id = $stmt->insert_id;
    $stmt->close();
    
    // Create exam entries for all 9 subjects
    foreach ($subjects as $subject) {
        $stmt = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject) VALUES (?, ?)");
        $stmt->bind_param("is", $exam_id, $subject);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: exams.php?success=1');
    exit();
}

if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    
    // Delete exam results first (foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM exam_results WHERE exam_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete exam subjects
    $stmt = $conn->prepare("DELETE FROM exam_subjects WHERE exam_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete exam
    $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: exams.php?success=1');
    exit();
}

$exams = $conn->query("SELECT e.*, u.full_name as created_by_name, COUNT(es.id) as subject_count FROM exams e JOIN users u ON e.created_by = u.id LEFT JOIN exam_subjects es ON e.id = es.exam_id GROUP BY e.id ORDER BY e.exam_date DESC")->fetch_all(MYSQLI_ASSOC);

$exam_subjects_map = [];
$result = $conn->query("SELECT exam_id, subject FROM exam_subjects ORDER BY exam_id, id");
while ($row = $result->fetch_assoc()) {
    if (!isset($exam_subjects_map[$row['exam_id']])) {
        $exam_subjects_map[$row['exam_id']] = [];
    }
    $exam_subjects_map[$row['exam_id']][] = $row['subject'];
}

if (isset($_GET['export_csv']) && isset($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    $stmt = $conn->prepare("SELECT e.*, u.full_name as created_by_name FROM exams e JOIN users u ON e.created_by = u.id WHERE e.id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($exam) {
        $stmt = $conn->prepare("SELECT s.admission_number, u.full_name, er.subject, er.marks_obtained FROM exam_results er JOIN students s ON er.student_id = s.id JOIN users u ON s.user_id = u.id WHERE er.exam_id = ? ORDER BY s.admission_number, er.subject");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Group results by student
        $students_data = [];
        foreach ($results as $row) {
            $key = $row['admission_number'] . '|' . $row['full_name'];
            if (!isset($students_data[$key])) {
                $students_data[$key] = [
                    'admission_number' => $row['admission_number'],
                    'full_name' => $row['full_name'],
                    'subjects' => []
                ];
            }
            $students_data[$key]['subjects'][$row['subject']] = $row['marks_obtained'];
        }
        
        // Create header with all subjects and rubrics
        $header = ['Admission Number', 'Name'];
        foreach ($subjects as $subject) {
            $header[] = $subject;
            $header[] = $subject . ' Rubric';
        }
        $header[] = 'Average';
        $header[] = 'Average Rubric';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exam_results_' . $exam_id . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($output, $header);
        
        // Write student data
        foreach ($students_data as $data) {
            $row = [$data['admission_number'], $data['full_name']];
            $total_marks = 0;
            $total_rubric_value = 0;
            $count = 0;
            
            foreach ($subjects as $subject) {
                $marks = $data['subjects'][$subject] ?? 0;
                $rubric = getRubric($marks);
                $row[] = $marks;
                $row[] = $rubric;
                
                if ($marks > 0) {
                    $total_marks += $marks;
                    $total_rubric_value += convertRubricToValue($rubric);
                    $count++;
                }
            }
            
            $average = $count > 0 ? round($total_marks / $count, 2) : 0;
            $average_rubric = $count > 0 ? convertValueToRubric(round($total_rubric_value / $count, 2)) : '';
            
            $row[] = $average;
            $row[] = $average_rubric;
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        $exam_id = intval($_POST['exam_id']);
        
        // Get the exam details
        $stmt = $conn->prepare("SELECT grade FROM exams WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$exam) {
            header('Location: exams.php?error=' . urlencode('Exam not found'));
            exit();
        }
        
        $exam_grade = $exam['grade'];
        
        $stmt = $conn->prepare("SELECT s.id, s.admission_number, u.full_name 
                               FROM students s 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.grade = ? AND s.status = 'Active'
                               ORDER BY CAST(s.admission_number AS UNSIGNED) ASC");
        $stmt->bind_param("s", $exam_grade);
        $stmt->execute();
        $db_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Subject order
        $subjects = ['English', 'Kiswahili', 'Mathematics', 'Integrated Science', 'CRE', 'CA&S', 'Pre-technical Studies', 'Social Studies', 'Agriculture'];
        
        if (($handle = fopen($file, 'r')) !== FALSE) {
            $row_num = 0;
            $excel_students = [];
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $row_num++;
                
                // Start from row 4 (skip first 3 rows)
                if ($row_num < 4) continue;
                
                // Stop at empty row
                if (empty(trim($data[0] ?? ''))) break;
                
                // Extract student data (admission in col 0, name in col 1, marks from col 2 onwards)
                $admission = trim($data[0]);
                $name = trim($data[1]);
                $marks = [];
                
                // Get marks from columns starting at index 2 (column 3 in Excel)
                for ($i = 2; $i < 2 + count($subjects); $i++) {
                    $marks[] = isset($data[$i]) ? floatval($data[$i]) : 0;
                }
                
                $excel_students[] = [
                    'admission' => $admission,
                    'name' => $name,
                    'marks' => $marks
                ];
            }
            fclose($handle);
            
            $imported_count = 0;
            $skipped_count = 0;
            
            foreach ($excel_students as $idx => $excel_student) {
                // Check if we have a corresponding database student
                if ($idx >= count($db_students)) {
                    $skipped_count++;
                    continue;
                }
                
                $db_student = $db_students[$idx];
                $student_id = $db_student['id'];
                
                // Import marks for this student
                $import_successful = true;
                foreach ($subjects as $subject_idx => $subject) {
                    $marks = $excel_student['marks'][$subject_idx] ?? 0;
                    
                    // Validate marks
                    if ($marks < 0) $marks = 0;
                    
                    // Insert or update exam result
                    $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, student_id, subject, marks_obtained) 
                                           VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE marks_obtained = ?");
                    if ($stmt) {
                        $stmt->bind_param("iisdd", $exam_id, $student_id, $subject, $marks, $marks);
                        if (!$stmt->execute()) {
                            $import_successful = false;
                            $stmt->close();
                            break;
                        }
                        $stmt->close();
                    }
                }
                
                if ($import_successful) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
            }
            
            header('Location: exams.php?success=1&imported=' . $imported_count . '&skipped=' . $skipped_count);
            exit();
        } else {
            header('Location: exams.php?error=' . urlencode('Failed to read Excel file'));
            exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - EBUSHIBO J.S PORTAL</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Exam Management</h1>
                <button class="btn btn-primary" onclick="toggleExamForm()">Create Exam</button>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Operation completed successfully!
                    <?php if (isset($_GET['imported'])): ?>
                        <br>Imported results for <?php echo intval($_GET['imported']); ?> students.
                    <?php endif; ?>
                    <?php if (isset($_GET['skipped'])): ?>
                        <br>Skipped <?php echo intval($_GET['skipped']); ?> rows due to errors.
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <br><strong>Warning:</strong> <?php echo htmlspecialchars($_GET['error']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Create Exam Form -->
            <div id="examForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Create New Exam</h3>
                </div>
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label for="exam_name">Exam Name *</label>
                        <input type="text" id="exam_name" name="exam_name" placeholder="e.g., Mid-Term Exam" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_type">Exam Type *</label>
                        <input type="text" id="exam_type" name="exam_type" placeholder="e.g., Mid-Term, End-Term, Quiz" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade">Grade *</label>
                        <select id="grade" name="grade" required>
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
                        <label for="total_marks">Total Marks (per subject) *</label>
                        <input type="number" id="total_marks" name="total_marks" value="10" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_date">Exam Date *</label>
                        <input type="date" id="exam_date" name="exam_date" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_exam" class="btn btn-primary">Create Exam</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleExamForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Import Excel Form -->
            <div id="importForm" class="table-container" style="display: none; margin-bottom: 24px;">
                <div class="table-header">
                    <h3>Import Results from Excel</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                    <div class="form-group">
                        <label for="exam_id">Select Exam *</label>
                        <select id="exam_id" name="exam_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['exam_name']) . ' - Grade ' . $exam['grade']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="excel_file">Excel File (CSV format) *</label>
                        <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx" required>
                        <small>First student should be on row 4. Columns: Admission Number, Student Name, then 9 subjects</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="import_excel" class="btn btn-primary">Import</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleImportForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Exams Container View -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Exams List (<?php echo count($exams); ?>)</h3>
                    <button class="btn btn-sm" onclick="toggleImportForm()">Import from Excel</button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; padding: 24px;">
                    <?php foreach ($exams as $exam): ?>
                        <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s;" onclick="openExamDetails(<?php echo htmlspecialchars(json_encode($exam)); ?>, <?php echo htmlspecialchars(json_encode($exam_subjects_map[$exam['id']] ?? [])); ?>)">
                            <h4 style="margin: 0 0 8px 0; color: var(--primary-color);"><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
                            <p style="margin: 4px 0; font-size: 14px; color: var(--text-secondary);"><strong>Type:</strong> <?php echo htmlspecialchars($exam['exam_type']); ?></p>
                            <p style="margin: 4px 0; font-size: 14px; color: var(--text-secondary);"><strong>Grade:</strong> <?php echo htmlspecialchars($exam['grade']); ?></p>
                            <p style="margin: 4px 0; font-size: 14px; color: var(--text-secondary);"><strong>Date:</strong> <?php echo formatDate($exam['exam_date']); ?></p>
                            <p style="margin: 4px 0; font-size: 12px; color: var(--text-secondary);"><strong>Created by:</strong> <?php echo htmlspecialchars($exam['created_by_name']); ?></p>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 12px;">
                                <?php 
                                $subjects = $exam_subjects_map[$exam['id']] ?? [];
                                foreach (array_slice($subjects, 0, 3) as $subject) {
                                    echo '<span style="font-size: 11px; padding: 2px 6px; background: var(--primary-color); color: white; border-radius: 3px;">' . htmlspecialchars($subject) . '</span>';
                                }
                                if (count($subjects) > 3) {
                                    echo '<span style="font-size: 11px; padding: 2px 6px; background: var(--primary-color); color: white; border-radius: 3px;">+' . (count($subjects) - 3) . ' more</span>';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($exams)): ?>
                        <p style="grid-column: 1 / -1; text-align: center; padding: 40px;">No exams found. Create one to get started.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Exam Details Modal -->
    <div id="examDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 24px; border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                <h2 id="modalExamName" style="margin: 0;"></h2>
                <button onclick="closeExamDetails()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <p><strong>Exam Type:</strong> <span id="modalExamType"></span></p>
                    <p><strong>Grade:</strong> <span id="modalExamGrade"></span></p>
                </div>
                <div>
                    <p><strong>Date:</strong> <span id="modalExamDate"></span></p>
                    <p><strong>Total Marks:</strong> <span id="modalExamTotalMarks"></span></p>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <h4 style="margin-top: 0;">Subjects:</h4>
                <div id="modalExamSubjects" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <a id="modalViewLink" href="#" class="btn btn-primary" style="text-decoration: none; display: inline-block; padding: 10px 16px; border-radius: 4px;">View Results</a>
                <a id="modalExportLink" href="#" class="btn btn-secondary" style="text-decoration: none; display: inline-block; padding: 10px 16px; border-radius: 4px;">Export CSV</a>
                <a id="modalDeleteLink" href="#" class="btn" style="text-decoration: none; display: inline-block; padding: 10px 16px; border-radius: 4px; background-color: #dc3545; color: white;" onclick="return confirm('Delete this exam?')">Delete</a>
                <button onclick="closeExamDetails()" class="btn btn-secondary" style="padding: 10px 16px;">Close</button>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function toggleExamForm() {
            const form = document.getElementById('examForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function toggleImportForm() {
            const form = document.getElementById('importForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function openExamDetails(exam, subjects) {
            document.getElementById('modalExamName').textContent = exam.exam_name;
            document.getElementById('modalExamType').textContent = exam.exam_type;
            document.getElementById('modalExamGrade').textContent = 'Grade ' + exam.grade;
            document.getElementById('modalExamDate').textContent = exam.exam_date;
            document.getElementById('modalExamTotalMarks').textContent = exam.total_marks;
            
            const subjectsDiv = document.getElementById('modalExamSubjects');
            subjectsDiv.innerHTML = '';
            subjects.forEach(subject => {
                const span = document.createElement('span');
                span.textContent = subject;
                span.style.cssText = 'display: inline-block; padding: 6px 12px; background: var(--primary-color); color: white; border-radius: 4px; font-size: 14px;';
                subjectsDiv.appendChild(span);
            });
            
            document.getElementById('modalViewLink').href = 'exam_results.php?id=' + exam.id;
            document.getElementById('modalExportLink').href = '?export_csv=1&exam_id=' + exam.id;
            document.getElementById('modalDeleteLink').href = '?delete_exam=' + exam.id;
            
            document.getElementById('examDetailsModal').style.display = 'flex';
        }

        function closeExamDetails() {
            document.getElementById('examDetailsModal').style.display = 'none';
        }
    </script>
</body>
</html>
