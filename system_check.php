<?php
/**
 * School Management System - Health Check
 * Run this to verify all fixes are working correctly
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$checks = [];
$all_passed = true;

// Check 1: Database Connection
try {
    $conn = getDBConnection();
    if ($conn && !$conn->connect_error) {
        $checks['Database Connection'] = ['status' => 'PASS', 'message' => 'Connected successfully'];
    } else {
        $checks['Database Connection'] = ['status' => 'FAIL', 'message' => 'Connection failed'];
        $all_passed = false;
    }
} catch (Exception $e) {
    $checks['Database Connection'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
    $all_passed = false;
}

// Check 2: Required Tables
$required_tables = [
    'users', 'students', 'exams', 'exam_results', 'exam_subjects',
    'fee_types', 'student_fees', 'fee_payments', 'clubs', 'club_members',
    'notes', 'news_posts', 'news_comments'
];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $checks["Table: $table"] = ['status' => 'PASS', 'message' => 'Table exists'];
    } else {
        $checks["Table: $table"] = ['status' => 'FAIL', 'message' => 'Table missing'];
        $all_passed = false;
    }
}

// Check 3: SMS Tables
$sms_tables = ['sms_config', 'sms_logs'];
foreach ($sms_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $checks["SMS Table: $table"] = ['status' => 'PASS', 'message' => 'Table exists'];
    } else {
        $checks["SMS Table: $table"] = ['status' => 'WARN', 'message' => 'Run setup_sms.php to create'];
    }
}

// Check 4: Upload Directories
$upload_dirs = ['uploads/profiles', 'uploads/notes', 'uploads/student_photos', 'uploads/news'];
foreach ($upload_dirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        $checks["Directory: $dir"] = ['status' => 'PASS', 'message' => 'Directory exists and writable'];
    } elseif (is_dir($dir)) {
        $checks["Directory: $dir"] = ['status' => 'WARN', 'message' => 'Directory exists but not writable'];
    } else {
        $checks["Directory: $dir"] = ['status' => 'WARN', 'message' => 'Directory missing - will be created on first use'];
    }
}

// Check 5: Key PHP Functions
$required_functions = ['sanitize', 'formatDate', 'getStudentByUserId', 'calculateFeePercentage'];
foreach ($required_functions as $func) {
    if (function_exists($func)) {
        $checks["Function: $func"] = ['status' => 'PASS', 'message' => 'Function exists'];
    } else {
        $checks["Function: $func"] = ['status' => 'FAIL', 'message' => 'Function not found'];
        $all_passed = false;
    }
}

// Check 6: Important Files
$required_files = [
    'students.php', 'student_details.php', 'exams.php', 'contact_parents.php',
    'dashboard.php', 'fees.php', 'includes/functions.php', 'includes/sms_helper.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        $checks["File: $file"] = ['status' => 'PASS', 'message' => 'File exists'];
    } else {
        $checks["File: $file"] = ['status' => 'FAIL', 'message' => 'File missing'];
        $all_passed = false;
    }
}

// Check 7: Student Duplicate Prevention
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students GROUP BY admission_number HAVING count > 1");
$stmt->execute();
$duplicates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($duplicates)) {
    $checks['Student Duplicates'] = ['status' => 'PASS', 'message' => 'No duplicate admission numbers found'];
} else {
    $checks['Student Duplicates'] = ['status' => 'WARN', 'message' => count($duplicates) . ' duplicate(s) found - review needed'];
}

// Check 8: Exam Records
$result = $conn->query("SELECT COUNT(*) as count FROM exams");
$exam_count = $result->fetch_assoc()['count'];
$checks['Exam Records'] = ['status' => 'PASS', 'message' => $exam_count . ' exams in system'];

// Check 9: Student Records
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$student_count = $result->fetch_assoc()['count'];
$checks['Active Students'] = ['status' => 'PASS', 'message' => $student_count . ' active students'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>System Health Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .check-item {
            padding: 12px;
            margin: 10px 0;
            border-left: 4px solid #ddd;
            border-radius: 4px;
        }
        .PASS {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .FAIL {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .WARN {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .check-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .check-message {
            font-size: 14px;
            margin-top: 5px;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            margin: 5px;
        }
        .PASS-badge { background: #28a745; }
        .FAIL-badge { background: #dc3545; }
        .WARN-badge { background: #ffc107; }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .actions a {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .actions a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>School Management System - Health Check</h1>
        <p>Verification Date: <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php foreach ($checks as $check_name => $check_data): ?>
            <div class="check-item <?php echo $check_data['status']; ?>">
                <div class="check-label">
                    <span class="status-badge <?php echo $check_data['status']; ?>-badge"><?php echo $check_data['status']; ?></span>
                    <?php echo htmlspecialchars($check_name); ?>
                </div>
                <div class="check-message"><?php echo htmlspecialchars($check_data['message']); ?></div>
            </div>
        <?php endforeach; ?>

        <div class="summary">
            <h2>Overall Status: 
                <?php if ($all_passed): ?>
                    <span style="color: green;">✓ SYSTEM OPERATIONAL</span>
                <?php else: ?>
                    <span style="color: red;">✗ ISSUES DETECTED</span>
                <?php endif; ?>
            </h2>
            <p>Review any FAIL items above and address them before going live.</p>
        </div>

        <div class="actions">
            <a href="setup_sms.php">Setup SMS (if needed)</a>
            <a href="dashboard.php">Go to Dashboard</a>
            <a href="javascript:location.reload()">Refresh Check</a>
        </div>
    </div>
</body>
</html>
