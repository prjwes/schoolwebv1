<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$conn = getDBConnection();
if (!$conn) {
    die('Database connection failed');
}

$results = [];
$errors = [];

// Create fee_types table
$sql1 = "CREATE TABLE IF NOT EXISTS fee_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fee_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    grade VARCHAR(10) DEFAULT 'all',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grade (grade),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql1)) {
    $results[] = 'fee_types table created/verified successfully';
} else {
    $errors[] = 'fee_types: ' . $conn->error;
}

// Create fee_payments table
$sql2 = "CREATE TABLE IF NOT EXISTS fee_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    term VARCHAR(20),
    receipt_number VARCHAR(50) UNIQUE,
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_fee_type (fee_type_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql2)) {
    $results[] = 'fee_payments table created/verified successfully';
} else {
    $errors[] = 'fee_payments: ' . $conn->error;
}

// Create student_fees table
$sql3 = "CREATE TABLE IF NOT EXISTS student_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_fee (student_id, fee_type_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_fee_type (fee_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql3)) {
    $results[] = 'student_fees table created/verified successfully';
} else {
    $errors[] = 'student_fees: ' . $conn->error;
}

// Create exam_subjects table if missing
$sql4 = "CREATE TABLE IF NOT EXISTS exam_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exam_subject (exam_id, subject),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    INDEX idx_exam (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql4)) {
    $results[] = 'exam_subjects table created/verified successfully';
} else {
    $errors[] = 'exam_subjects: ' . $conn->error;
}

// Add missing rubric column to exam_results if not exists
$check_rubric = $conn->query("SHOW COLUMNS FROM exam_results LIKE 'rubric'");
if ($check_rubric && $check_rubric->num_rows === 0) {
    if ($conn->query("ALTER TABLE exam_results ADD COLUMN rubric VARCHAR(10) DEFAULT NULL")) {
        $results[] = 'rubric column added to exam_results table';
    } else {
        $errors[] = 'rubric column: ' . $conn->error;
    }
} else {
    $results[] = 'rubric column already exists in exam_results table';
}

// Create clubs table if missing
$sql5 = "CREATE TABLE IF NOT EXISTS clubs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    club_name VARCHAR(100) NOT NULL,
    description TEXT,
    established_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql5)) {
    $results[] = 'clubs table created/verified successfully';
} else {
    $errors[] = 'clubs: ' . $conn->error;
}

// Create club_members table if missing
$sql6 = "CREATE TABLE IF NOT EXISTS club_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    club_id INT NOT NULL,
    student_id INT NOT NULL,
    role VARCHAR(50),
    joined_date DATE DEFAULT CURRENT_DATE(),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_club_member (club_id, student_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql6)) {
    $results[] = 'club_members table created/verified successfully';
} else {
    $errors[] = 'club_members: ' . $conn->error;
}

// Create notes table if missing
$sql7 = "CREATE TABLE IF NOT EXISTS notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    grade VARCHAR(10) NOT NULL,
    subject VARCHAR(100),
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    file_url VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_grade (grade),
    INDEX idx_subject (subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql7)) {
    $results[] = 'notes table created/verified successfully';
} else {
    $errors[] = 'notes: ' . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Complete</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; margin: 10px 0; background: #d4edda; border-left: 4px solid #28a745; }
        .error { color: #dc3545; padding: 10px; margin: 10px 0; background: #f8d7da; border-left: 4px solid #dc3545; }
        .info { color: #004085; padding: 10px; margin: 10px 0; background: #d1ecf1; border-left: 4px solid #0c5460; }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Tables Setup</h1>
        <p><strong>All database tables have been created/verified</strong></p>
        
        <?php if (!empty($results)): ?>
            <h3>Success Messages:</h3>
            <?php foreach ($results as $msg): ?>
                <div class="success">✓ <?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <h3>Errors:</h3>
            <?php foreach ($errors as $err): ?>
                <div class="error">✗ <?php echo $err; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="info">
            <strong>All fee-related tables are now ready!</strong><br>
            You can now:<br>
            - View student fees in student_details.php<br>
            - Manage fee types in fees.php<br>
            - Track payments<br>
        </div>
        
        <a href="dashboard.php" class="button">Go to Dashboard</a>
    </div>
</body>
</html>
