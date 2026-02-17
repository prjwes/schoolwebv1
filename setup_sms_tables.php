<?php
/**
 * SMS Setup Script - Run this once to create required SMS tables
 * Access: http://yourdomain.com/setup_sms_tables.php
 * Then delete this file after setup is complete
 */

require_once 'config/database.php';

$conn = getDBConnection();
$setup_messages = [];
$setup_errors = [];

// Table 1: SMS Configuration
$sql_sms_config = "CREATE TABLE IF NOT EXISTS sms_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL DEFAULT 'twilio',
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    sender_id VARCHAR(50),
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql_sms_config)) {
    $setup_messages[] = "✓ SMS Configuration table created successfully";
} else {
    $setup_errors[] = "Error creating SMS Configuration table: " . $conn->error;
}

// Table 2: SMS Logs
$sql_sms_logs = "CREATE TABLE IF NOT EXISTS sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_phone VARCHAR(20) NOT NULL,
    parent_name VARCHAR(100),
    student_id INT,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    error_message TEXT,
    sent_by INT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (parent_phone),
    INDEX (status),
    INDEX (student_id),
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql_sms_logs)) {
    $setup_messages[] = "✓ SMS Logs table created successfully";
} else {
    $setup_errors[] = "Error creating SMS Logs table: " . $conn->error;
}

// Table 3: SMS Templates
$sql_sms_templates = "CREATE TABLE IF NOT EXISTS sms_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    message TEXT NOT NULL,
    variables TEXT COMMENT 'JSON array of available variables',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql_sms_templates)) {
    $setup_messages[] = "✓ SMS Templates table created successfully";
    
    // Insert default templates
    $default_templates = [
        [
            'name' => 'fees_payment_reminder',
            'message' => 'Dear {parent_name}, This is a reminder that fees of {amount} for {student_name} (Grade {grade}) are due on {due_date}. Please make payment. Thank you.',
            'variables' => '["parent_name", "amount", "student_name", "grade", "due_date"]'
        ],
        [
            'name' => 'exam_result_notification',
            'message' => 'Dear {parent_name}, {student_name} has scored {marks}/{total_marks} in {exam_name}. For more details, please login to the school portal.',
            'variables' => '["parent_name", "student_name", "marks", "total_marks", "exam_name"]'
        ],
        [
            'name' => 'attendance_alert',
            'message' => 'Dear {parent_name}, {student_name} has been absent from school on {date}. Please contact school if you need any information.',
            'variables' => '["parent_name", "student_name", "date"]'
        ],
        [
            'name' => 'general_notification',
            'message' => '{message}',
            'variables' => '["message"]'
        ]
    ];
    
    foreach ($default_templates as $template) {
        $check = $conn->prepare("SELECT id FROM sms_templates WHERE name = ? LIMIT 1");
        $check->bind_param("s", $template['name']);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO sms_templates (name, message, variables) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $template['name'], $template['message'], $template['variables']);
            $insert->execute();
            $insert->close();
        }
        $check->close();
    }
    
    $setup_messages[] = "✓ Default SMS templates inserted";
} else {
    $setup_errors[] = "Error creating SMS Templates table: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Setup - School Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; width: 100%; }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        .message { padding: 15px; margin-bottom: 10px; border-radius: 6px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e7f3ff; color: #004085; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #004085; }
        .action-required { background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #ffc107; }
        .next-steps { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px; }
        .next-steps h3 { margin-bottom: 15px; color: #333; }
        .next-steps ol { margin-left: 20px; color: #666; }
        .next-steps li { margin-bottom: 10px; }
        .delete-warning { color: #dc3545; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SMS Setup Wizard</h1>
        
        <?php if (!empty($setup_messages)): ?>
            <div class="setup-result">
                <?php foreach ($setup_messages as $msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($setup_errors)): ?>
            <div class="setup-result">
                <?php foreach ($setup_errors as $err): ?>
                    <div class="message error"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($setup_errors)): ?>
            <div class="info-box">
                <strong>Setup Complete!</strong> All SMS tables have been created successfully.
            </div>
            
            <div class="action-required">
                <strong>Next Steps:</strong>
                <div class="next-steps">
                    <ol>
                        <li>Login to your admin panel</li>
                        <li>Go to Settings → SMS Configuration</li>
                        <li>Enter your SMS provider details (Twilio, AfricasTalking, or HTTP Gateway)</li>
                        <li>Test the SMS connection</li>
                        <li>Enable SMS notifications</li>
                    </ol>
                </div>
            </div>
            
            <div class="delete-warning">
                ⚠ IMPORTANT: Delete this file (setup_sms_tables.php) after setup is complete for security!
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
