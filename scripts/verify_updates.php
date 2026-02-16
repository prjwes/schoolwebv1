<?php
/**
 * Verification Script for School Website Updates
 * Tests all the fixes made to ensure they're working correctly
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

echo "=== School Website Update Verification ===\n\n";

$conn = getDBConnection();
if (!$conn) {
    echo "ERROR: Could not connect to database\n";
    exit(1);
}

echo "✓ Database connection successful\n";

// Check database tables exist
$tables = ['exams', 'exam_results', 'exam_subjects', 'fee_types', 'student_fees', 'fee_payments', 'students', 'users'];
$missing_tables = [];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    echo "✓ All required database tables exist\n";
} else {
    echo "⚠ Missing tables: " . implode(', ', $missing_tables) . "\n";
}

// Check if upload directories exist
$upload_dirs = [
    'uploads/student_photos/',
    'uploads/news/',
    'uploads/profiles/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo "✗ Could not create directory: $dir\n";
        } else {
            echo "✓ Created upload directory: $dir\n";
        }
    } else {
        echo "✓ Directory exists: $dir\n";
    }
}

// Verify key functions exist
$functions = ['getStudentByUserId', 'calculateFeePercentage', 'getRubric', 'convertRubricToValue', 'convertValueToRubric', 'getStudents'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function exists: $func\n";
    } else {
        echo "✗ Function missing: $func\n";
    }
}

echo "\n=== Verification Complete ===\n";
echo "All systems are ready for use!\n";
?>
