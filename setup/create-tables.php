<?php
/**
 * Database Table Setup Script
 * Run this once to create the teacher_grades table if it doesn't exist
 */

require_once __DIR__ . '/../config/database.php';

function createTeacherGradesTable() {
    $conn = getDBConnection();
    
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    // Check if table exists
    if (tableExists('teacher_grades')) {
        return ['success' => true, 'message' => 'teacher_grades table already exists'];
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS teacher_grades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id INT NOT NULL,
        grade VARCHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_teacher_grade (teacher_id, grade)
    )";
    
    if ($conn->query($sql) === TRUE) {
        // Create indexes
        $conn->query("CREATE INDEX IF NOT EXISTS idx_teacher_id ON teacher_grades(teacher_id)");
        $conn->query("CREATE INDEX IF NOT EXISTS idx_grade ON teacher_grades(grade)");
        
        return ['success' => true, 'message' => 'teacher_grades table created successfully'];
    } else {
        error_log("Error creating teacher_grades table: " . $conn->error);
        return ['success' => false, 'message' => 'Error creating table: ' . $conn->error];
    }
}

// Check if script is being accessed via browser
if (php_sapi_name() !== 'cli' && !isset($_GET['skip_auth'])) {
    require_once __DIR__ . '/../includes/auth.php';
    requireLogin();
    requireRole(['Admin', 'HOI', 'DHOI']);
}

// Create the table
$result = createTeacherGradesTable();

// Return JSON if requested
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// HTML response
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="padding: 20px; font-family: Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto;">
        <h1>Database Setup</h1>
        
        <?php if ($result['success']): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong>Success!</strong><br>
                <?php echo htmlspecialchars($result['message']); ?>
            </div>
        <?php else: ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong>Error!</strong><br>
                <?php echo htmlspecialchars($result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="../dashboard.php" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
