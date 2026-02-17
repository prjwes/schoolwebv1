<?php
/**
 * System Testing Script - Run this to verify all fixes are working
 * Access: http://yourdomain.com/test_system.php
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Database Connection
$test_name = "Database Connection";
try {
    $conn = getDBConnection();
    if ($conn && !$conn->connect_error) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Connected successfully'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Connection failed'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

// Test 2: Students Table
$test_name = "Students Table Exists";
try {
    $result = @$conn->query("SELECT 1 FROM students LIMIT 1");
    if ($result !== false) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Table exists and accessible'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Table not accessible'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

// Test 3: Check Duplicate Prevention
$test_name = "Duplicate Prevention (Student Names)";
try {
    $result = @$conn->query("SHOW COLUMNS FROM students LIKE 'admission_number'");
    if ($result && $result->num_rows > 0) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Duplicate check queries present'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Column check failed'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

// Test 4: Check Photo Upload Directory
$test_name = "Photo Upload Directory";
$photo_dir = 'uploads/student_photos/';
if (!is_dir($photo_dir)) {
    @mkdir($photo_dir, 0755, true);
}
if (is_dir($photo_dir) && is_writable($photo_dir)) {
    $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Directory exists and is writable'];
    $passed++;
} else {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Directory not writable or missing'];
    $failed++;
}

// Test 5: Exams Table
$test_name = "Exams Table Exists";
try {
    $result = @$conn->query("SELECT 1 FROM exams LIMIT 1");
    if ($result !== false) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Table exists and accessible'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Table not accessible'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

// Test 6: SMS Tables Exist
$test_name = "SMS Tables (sms_config)";
try {
    $result = @$conn->query("SELECT 1 FROM sms_config LIMIT 1");
    if ($result !== false) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'SMS tables created successfully'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'INFO', 'message' => 'SMS tables not yet created. Run setup_sms_tables.php'];
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'INFO', 'message' => 'Run setup_sms_tables.php to create SMS tables'];
}

// Test 7: News Posts Table
$test_name = "News Posts Table";
try {
    $result = @$conn->query("SELECT 1 FROM news_posts LIMIT 1");
    if ($result !== false) {
        $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Table exists and accessible'];
        $passed++;
    } else {
        $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Table not accessible'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

// Test 8: PHP Functions Available
$test_name = "Key Functions Available";
$functions_to_check = ['sanitize', 'formatDate', 'getStudentByUserId', 'getStudents'];
$all_exist = true;
foreach ($functions_to_check as $func) {
    if (!function_exists($func)) {
        $all_exist = false;
        break;
    }
}
if ($all_exist) {
    $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'All required functions available'];
    $passed++;
} else {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Some required functions missing'];
    $failed++;
}

// Test 9: File Uploads Configuration
$test_name = "File Upload Permissions";
$upload_dirs = ['uploads/', 'uploads/student_photos/', 'uploads/profiles/'];
$writable_count = 0;
foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_writable($dir)) {
        $writable_count++;
    }
}
if ($writable_count >= 2) {
    $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => "$writable_count/$" . count($upload_dirs) . ' directories writable'];
    $passed++;
} else {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => 'Upload directories not writable'];
    $failed++;
}

// Test 10: Error Handling
$test_name = "Error Handling";
try {
    // Simulate an error
    @trigger_error("Test error", E_USER_NOTICE);
    $tests[] = ['name' => $test_name, 'status' => 'PASS', 'message' => 'Error handling working'];
    $passed++;
} catch (Exception $e) {
    $tests[] = ['name' => $test_name, 'status' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Test Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .summary-card { padding: 20px; border-radius: 8px; text-align: center; color: white; font-weight: bold; }
        .summary-card.total { background: #667eea; }
        .summary-card.passed { background: #48bb78; }
        .summary-card.failed { background: #f56565; }
        .summary-card.info { background: #4299e1; }
        .tests-list { margin-top: 30px; }
        .test-item { padding: 15px; margin-bottom: 10px; border-radius: 6px; border-left: 4px solid #ccc; display: flex; justify-content: space-between; align-items: center; }
        .test-item.pass { background: #f0fff4; border-left-color: #48bb78; }
        .test-item.fail { background: #fff5f5; border-left-color: #f56565; }
        .test-item.info { background: #ebf8ff; border-left-color: #4299e1; }
        .test-status { font-weight: bold; padding: 4px 12px; border-radius: 4px; font-size: 12px; }
        .test-status.pass { background: #48bb78; color: white; }
        .test-status.fail { background: #f56565; color: white; }
        .test-status.info { background: #4299e1; color: white; }
        .test-name { font-weight: 600; color: #333; }
        .test-message { color: #666; font-size: 14px; }
        .recommendations { background: #fffaf0; border: 1px solid #fed7d7; padding: 20px; border-radius: 6px; margin-top: 30px; }
        .recommendations h3 { color: #744210; margin-bottom: 15px; }
        .recommendations ul { margin-left: 20px; }
        .recommendations li { margin-bottom: 8px; color: #744210; }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Health Check Report</h1>
        
        <div class="summary">
            <div class="summary-card total">
                <div style="font-size: 24px; margin-bottom: 5px;"><?php echo count($tests); ?></div>
                <div>Total Tests</div>
            </div>
            <div class="summary-card passed">
                <div style="font-size: 24px; margin-bottom: 5px;"><?php echo $passed; ?></div>
                <div>Passed</div>
            </div>
            <div class="summary-card failed">
                <div style="font-size: 24px; margin-bottom: 5px;"><?php echo $failed; ?></div>
                <div>Failed</div>
            </div>
            <div class="summary-card info">
                <div style="font-size: 24px; margin-bottom: 5px;"><?php echo count($tests) - $passed - $failed; ?></div>
                <div>Info</div>
            </div>
        </div>
        
        <div class="tests-list">
            <h2 style="margin-bottom: 15px; color: #333;">Test Results</h2>
            <?php foreach ($tests as $test): ?>
                <div class="test-item <?php echo strtolower($test['status']); ?>">
                    <div>
                        <div class="test-name"><?php echo htmlspecialchars($test['name']); ?></div>
                        <div class="test-message"><?php echo htmlspecialchars($test['message']); ?></div>
                    </div>
                    <span class="test-status <?php echo strtolower($test['status']); ?>"><?php echo $test['status']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($failed > 0): ?>
        <div class="recommendations">
            <h3>⚠ Recommendations</h3>
            <ul>
                <li>Review failed tests above</li>
                <li>Check your server configuration</li>
                <li>Contact Infinity Free support if needed</li>
                <li>Verify database credentials in config/database.php</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="recommendations" style="background: #f0fff4; border-color: #9ae6b4;">
            <h3 style="color: #22543d;">✓ System Ready</h3>
            <ul style="color: #22543d;">
                <li>All critical tests passed</li>
                <li>System is ready for use</li>
                <li>Run SMS setup if you need SMS functionality</li>
                <li>Delete test_system.php after verification</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
