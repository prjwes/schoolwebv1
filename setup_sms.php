<?php
/**
 * SMS Tables Setup Script
 * Run this once to create SMS configuration and logging tables
 * Access: yoursite.infinityfree.com/setup_sms.php
 */

require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($_GET['setup_key'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head><title>SMS Setup</title></head>
    <body style="font-family: Arial; margin: 40px;">
        <h1>SMS Configuration Setup</h1>
        <p>This script will create necessary tables for SMS functionality.</p>
        <form method="POST">
            <p>
                <label>Admin PIN (from your config):</label><br>
                <input type="password" name="admin_pin" required style="padding: 8px; width: 200px;">
            </p>
            <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Setup SMS Tables</button>
        </form>
    </body>
    </html>
    ');
}

// Verify PIN
$admin_pin = $_POST['admin_pin'] ?? $_GET['setup_key'] ?? '';
$correct_pin = 'school2024'; // Change this to your actual admin PIN

if ($admin_pin !== $correct_pin) {
    die('Unauthorized access');
}

$conn = getDBConnection();

// Create SMS Configuration Table
$sql1 = "CREATE TABLE IF NOT EXISTS sms_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL DEFAULT 'none',
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    sender_name VARCHAR(50),
    is_active BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create SMS Logs Table
$sql2 = "CREATE TABLE IF NOT EXISTS sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100),
    message_text LONGTEXT NOT NULL,
    sent_by_id INT,
    sent_by_name VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    error_message LONGTEXT,
    provider VARCHAR(50),
    external_id VARCHAR(100),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
)";

try {
    // Execute both statements
    if ($conn->query($sql1) === TRUE) {
        echo "SMS Config table created/verified successfully.<br>";
    } else {
        throw new Exception("Error creating sms_config table: " . $conn->error);
    }

    if ($conn->query($sql2) === TRUE) {
        echo "SMS Logs table created/verified successfully.<br>";
    } else {
        throw new Exception("Error creating sms_logs table: " . $conn->error);
    }

    // Insert default config if not exists
    $check = $conn->query("SELECT COUNT(*) as count FROM sms_config");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        $insert = "INSERT INTO sms_config (provider, is_active) VALUES ('none', 0)";
        if ($conn->query($insert) === TRUE) {
            echo "Default SMS configuration created.<br>";
        }
    }

    echo "<br><strong style='color: green; font-size: 16px;'>Setup completed successfully!</strong><br>";
    echo "SMS tables are now ready for use.<br>";
    echo "Next step: Configure SMS provider in contact_parents.php page.";

} catch (Exception $e) {
    echo "<strong style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</strong>";
}
?>
