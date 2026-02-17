<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Admin only
$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['Admin', 'HOI'])) {
    die("Admin access required");
}

$conn = getDBConnection();
echo "<h2>Student Edit Verification</h2>";
echo "<pre>";

// Check students table structure
echo "=== STUDENTS TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE students");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Test update query
echo "\n=== TEST UPDATE QUERY ===\n";
$test_id = 1;
$test_name = "Test Parent";
$test_phone = "0123456789";
$test_email = "test@example.com";
$test_address = "Test Address";
$test_dob = NULL;

$stmt = $conn->prepare("UPDATE students SET parent_name = ?, parent_phone = ?, parent_email = ?, address = ?, date_of_birth = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("sssssi", $test_name, $test_phone, $test_email, $test_address, $test_dob, $test_id);
    echo "Query prepared successfully\n";
    echo "Binding parameters...\n";
    echo "Will update student with ID: $test_id\n";
    echo "Parent name: $test_name\n";
    echo "Parent phone: $test_phone\n";
    echo "Parent email: $test_email\n";
    echo "Address: $test_address\n";
    echo "DOB: " . ($test_dob ? $test_dob : "NULL") . "\n";
} else {
    echo "Error preparing statement: " . $conn->error . "\n";
}

// Show database version
echo "\n=== DATABASE INFO ===\n";
$version = $conn->query("SELECT VERSION()")->fetch_row()[0];
echo "MySQL Version: $version\n";

// Check for any constraints
echo "\n=== TABLE CONSTRAINTS ===\n";
$result = $conn->query("SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='students'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['CONSTRAINT_NAME'] . " on " . $row['COLUMN_NAME'] . "\n";
    }
} else {
    echo "No constraints found or query error: " . $conn->error . "\n";
}

echo "</pre>";
?>
