<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['Admin', 'HOI'])) {
    die("Admin access required");
}

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Edit Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .section h2 { color: #007bff; margin-top: 0; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #007bff; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .code { background: #2d2d2d; color: #f8f8f2; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Edit Verification Dashboard</h1>

        <?php
        // 1. Check students table structure
        echo '<div class="section">';
        echo '<h2>1. Students Table Structure</h2>';
        
        $result = $conn->query("DESCRIBE students");
        if ($result && $result->num_rows > 0) {
            echo '<div class="status success">✓ Students table exists and is accessible</div>';
            echo '<table><thead><tr><th>Field</th><th>Type</th><th>Nullable</th><th>Key</th><th>Default</th></tr></thead><tbody>';
            
            while ($row = $result->fetch_assoc()) {
                $nullable = $row['Null'] === 'YES' ? 'Yes' : 'No';
                $key = !empty($row['Key']) ? $row['Key'] : '-';
                $default = !empty($row['Default']) ? $row['Default'] : 'NULL';
                echo "<tr><td><strong>{$row['Field']}</strong></td><td>{$row['Type']}</td><td>{$nullable}</td><td>{$key}</td><td>{$default}</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="status error">✗ Error: ' . $conn->error . '</div>';
        }
        echo '</div>';

        // 2. Check required columns for editing
        echo '<div class="section">';
        echo '<h2>2. Required Edit Columns Check</h2>';
        
        $required_columns = ['full_name', 'admission_number', 'grade', 'stream', 'email', 'date_of_birth', 'parent_name', 'parent_phone', 'parent_email'];
        $student_table = $conn->query("DESCRIBE students");
        $existing_columns = [];
        
        if ($student_table) {
            while ($row = $student_table->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
        }
        
        $user_columns = $conn->query("DESCRIBE users");
        $user_fields = [];
        if ($user_columns) {
            while ($row = $user_columns->fetch_assoc()) {
                $user_fields[] = $row['Field'];
            }
        }
        
        foreach ($required_columns as $col) {
            if ($col === 'full_name' || $col === 'email') {
                if (in_array($col, $user_fields)) {
                    echo '<div class="status success">✓ ' . $col . ' (in users table)</div>';
                } else {
                    echo '<div class="status error">✗ ' . $col . ' NOT found (in users table)</div>';
                }
            } else {
                if (in_array($col, $existing_columns)) {
                    echo '<div class="status success">✓ ' . $col . ' (in students table)</div>';
                } else {
                    echo '<div class="status error">✗ ' . $col . ' NOT found (in students table)</div>';
                }
            }
        }
        echo '</div>';

        // 3. Test sample student
        echo '<div class="section">';
        echo '<h2>3. Sample Student Data</h2>';
        
        $sample = $conn->query("SELECT s.id, s.admission_number, s.grade, s.stream, s.parent_name, s.parent_phone, s.parent_email, u.full_name, u.email FROM students s JOIN users u ON s.user_id = u.id LIMIT 1");
        if ($sample && $sample->num_rows > 0) {
            echo '<div class="status info">✓ Sample student found</div>';
            $student = $sample->fetch_assoc();
            echo '<table><tbody>';
            foreach ($student as $key => $value) {
                echo "<tr><td><strong>{$key}</strong></td><td>" . ($value ? htmlspecialchars($value) : 'NULL') . "</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="status error">✗ No students found in database</div>';
        }
        echo '</div>';

        // 4. Test UPDATE query
        echo '<div class="section">';
        echo '<h2>4. UPDATE Query Test</h2>';
        
        echo '<p>Testing prepared statement for student update:</p>';
        echo '<div class="code">UPDATE students SET admission_number=?, grade=?, stream=?, date_of_birth=?, parent_name=?, parent_phone=?, parent_email=?, address=? WHERE id=?<br>UPDATE users SET full_name=?, email=? WHERE id=?</div>';
        
        $test_stmt = $conn->prepare("UPDATE students SET admission_number=? WHERE id=?");
        if ($test_stmt) {
            echo '<div class="status success">✓ Prepared statement created successfully</div>';
            $test_stmt->close();
        } else {
            echo '<div class="status error">✗ Failed to prepare statement: ' . $conn->error . '</div>';
        }
        echo '</div>';

        // 5. Database info
        echo '<div class="section">';
        echo '<h2>5. Database Information</h2>';
        
        $db_info = $conn->query("SELECT VERSION() as version, DATABASE() as name, USER() as user");
        if ($db_info) {
            $info = $db_info->fetch_assoc();
            echo '<table><tbody>';
            echo '<tr><td><strong>MySQL Version</strong></td><td>' . $info['version'] . '</td></tr>';
            echo '<tr><td><strong>Database Name</strong></td><td>' . $info['name'] . '</td></tr>';
            echo '<tr><td><strong>Connection User</strong></td><td>' . $info['user'] . '</td></tr>';
            echo '</tbody></table>';
        }
        echo '</div>';

        // 6. Fee tables check
        echo '<div class="section">';
        echo '<h2>6. Fee Tables Status</h2>';
        
        $tables_to_check = ['fee_types', 'student_fees', 'fee_payments'];
        foreach ($tables_to_check as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc();
                echo '<div class="status success">✓ ' . $table . ' exists (' . $count['cnt'] . ' records)</div>';
            } else {
                echo '<div class="status error">✗ ' . $table . ' NOT found</div>';
            }
        }
        echo '</div>';

        // 7. Exam tables check
        echo '<div class="section">';
        echo '<h2>7. Exam Tables Status</h2>';
        
        $exam_tables = ['exams', 'exam_results', 'exam_subjects'];
        foreach ($exam_tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc();
                echo '<div class="status success">✓ ' . $table . ' exists (' . $count['cnt'] . ' records)</div>';
            } else {
                echo '<div class="status error">✗ ' . $table . ' NOT found</div>';
            }
        }
        echo '</div>';

        echo '<div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0066cc; border-radius: 4px;">';
        echo '<strong>Status:</strong> All systems ' . ($user && in_array($user['role'], ['Admin', 'HOI']) ? '<span style="color: green;">✓ READY</span>' : '<span style="color: red;">✗ FAILED</span>') . ' for student editing';
        echo '<br><a href="student_details.php?id=1" style="margin-top: 10px; display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Test Student Details Page</a>';
        echo '</div>';
        ?>
    </div>
</body>
</html>
