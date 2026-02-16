<?php
/**
 * Initialize Fee Management System
 * This script sets up the necessary database tables for:
 * 1. Student fee assignments
 * 2. SMS notifications
 * 3. M-Pesa payment tracking
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$conn = getDBConnection();

if (!$conn) {
    die('Database connection failed');
}

$migrations = [];
$errors = [];

// Migration 1: Create student_fees table
$migration1 = "
CREATE TABLE IF NOT EXISTS student_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_due DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    due_date DATE,
    last_payment_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_fee (student_id, fee_type_id)
)";

if ($conn->query($migration1)) {
    $migrations[] = 'student_fees table created successfully';
} else {
    $errors[] = 'student_fees: ' . $conn->error;
}

// Migration 2: Create sms_logs table
$migration2 = "
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,
    student_id INT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('fee_reminder', 'payment_confirmation', 'general') DEFAULT 'general',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME,
    sent_by INT,
    api_response TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($migration2)) {
    $migrations[] = 'sms_logs table created successfully';
} else {
    $errors[] = 'sms_logs: ' . $conn->error;
}

// Migration 3: Create mpesa_transactions table
$migration3 = "
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    phone_number VARCHAR(20) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    mpesa_request_id VARCHAR(100),
    mpesa_response_code VARCHAR(50),
    mpesa_response_message TEXT,
    status ENUM('initiated', 'success', 'failed', 'timeout') DEFAULT 'initiated',
    payment_id INT,
    initiated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES fee_payments(id) ON DELETE SET NULL
)";

if ($conn->query($migration3)) {
    $migrations[] = 'mpesa_transactions table created successfully';
} else {
    $errors[] = 'mpesa_transactions: ' . $conn->error;
}

// Migration 4: Add parent_phone to users if not exists
$check_parent_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'parent_phone'");
if ($check_parent_phone->num_rows === 0) {
    $migration4 = "ALTER TABLE users ADD COLUMN parent_phone VARCHAR(20)";
    if ($conn->query($migration4)) {
        $migrations[] = 'parent_phone column added to users table';
    } else {
        $errors[] = 'parent_phone: ' . $conn->error;
    }
}

// Migration 5: Add payment_method column to fee_payments if not exists
$check_payment_method = $conn->query("SHOW COLUMNS FROM fee_payments LIKE 'payment_method'");
if ($check_payment_method->num_rows === 0) {
    $migration5 = "ALTER TABLE fee_payments ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cash'";
    if ($conn->query($migration5)) {
        $migrations[] = 'payment_method column added to fee_payments table';
    } else {
        $errors[] = 'payment_method: ' . $conn->error;
    }
}

echo json_encode([
    'success' => empty($errors),
    'migrations' => $migrations,
    'errors' => $errors
]);
?>
