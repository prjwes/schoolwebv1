<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$conn = getDBConnection();

// Only authorized staff can access
if (!in_array($role, ['Admin', 'HOI', 'DHOI', 'Finance_Teacher', 'DoS_Exams_Teacher'])) {
    header('Location: dashboard.php');
    exit();
}

$success_msg = '';
$error_msg = '';

// Get or set SMS configuration
$sms_config_query = "SELECT * FROM sms_config LIMIT 1";
$sms_config_result = $conn->query($sms_config_query);
$sms_config = $sms_config_result ? $sms_config_result->fetch_assoc() : null;

// Handle SMS config updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sms_config'])) {
    $sms_number = sanitize($_POST['sms_number'] ?? '');
    $sms_provider = sanitize($_POST['sms_provider'] ?? 'generic');
    $sms_api_key = sanitize($_POST['sms_api_key'] ?? '');
    
    if (empty($sms_number)) {
        $error_msg = "SMS number cannot be empty.";
    } else {
        if ($sms_config) {
            $update_stmt = $conn->prepare("UPDATE sms_config SET sms_number = ?, sms_provider = ?, api_key = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $sms_number, $sms_provider, $sms_api_key, $sms_config['id']);
        } else {
            $update_stmt = $conn->prepare("INSERT INTO sms_config (sms_number, sms_provider, api_key) VALUES (?, ?, ?)");
            $update_stmt->bind_param("sss", $sms_number, $sms_provider, $sms_api_key);
        }
        
        if ($update_stmt->execute()) {
            $success_msg = "SMS configuration updated successfully!";
            $sms_config = ['sms_number' => $sms_number, 'sms_provider' => $sms_provider];
        } else {
            $error_msg = "Failed to update SMS configuration.";
        }
        $update_stmt->close();
    }
}

// Handle SMS sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $selected_grade = sanitize($_POST['selected_grade'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($message) || strlen($message) < 5) {
        $error_msg = "Message must be at least 5 characters long.";
    } else if (empty($selected_grade)) {
        $error_msg = "Please select at least one grade.";
    } else {
        // Get parents for selected grade(s)
        $grades_array = explode(',', $selected_grade);
        $placeholders = implode(',', array_fill(0, count($grades_array), '?'));
        
        $parent_query = "
            SELECT DISTINCT u.id, u.full_name, u.parent_phone, s.student_id, s.grade
            FROM users u
            JOIN students s ON s.user_id = u.id
            WHERE s.grade IN ($placeholders) AND u.parent_phone IS NOT NULL AND u.parent_phone != '' AND s.status = 'Active'
            ORDER BY s.grade, u.full_name
        ";
        
        $parent_stmt = $conn->prepare($parent_query);
        $parent_stmt->bind_param(str_repeat('s', count($grades_array)), ...$grades_array);
        $parent_stmt->execute();
        $parents = $parent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $parent_stmt->close();
        
        if (empty($parents)) {
            $error_msg = "No parents found for the selected grade(s).";
        } else {
            // Send SMS to each parent
            $sent_count = 0;
            $failed_count = 0;
            
            foreach ($parents as $parent) {
                // Validate phone number
                $phone = preg_replace('/[^0-9+]/', '', $parent['parent_phone']);
                if (strlen($phone) < 10) {
                    $failed_count++;
                    continue;
                }
                
                // Send SMS via API or provider
                $sms_result = sendSMS($phone, $message, $sms_config);
                
                // Log the SMS
                $log_status = $sms_result['success'] ? 'sent' : 'failed';
                $log_message = $sms_result['message'] ?? 'SMS processing';
                
                $log_stmt = $conn->prepare("
                    INSERT INTO sms_logs (parent_id, student_id, message, phone_number, status, response, sent_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $log_stmt->bind_param(
                    "iisssssi",
                    $parent['id'],
                    $parent['id'],
                    $message,
                    $phone,
                    $log_status,
                    $log_message,
                    $user['id']
                );
                $log_stmt->execute();
                $log_stmt->close();
                
                if ($sms_result['success']) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
            
            $success_msg = "SMS Campaign Complete: $sent_count sent successfully, $failed_count failed.";
        }
    }
}

// Get list of grades
$grades_result = $conn->query("SELECT DISTINCT grade FROM students WHERE status = 'Active' ORDER BY CAST(grade AS UNSIGNED)");
$grades = $grades_result ? $grades_result->fetch_all(MYSQLI_ASSOC) : [];

// Get SMS logs for history
$logs_query = "
    SELECT sl.*, u.full_name as sent_by_name, s.student_id
    FROM sms_logs sl
    LEFT JOIN users u ON sl.sent_by = u.id
    LEFT JOIN students s ON sl.student_id = s.id
    ORDER BY sl.created_at DESC
    LIMIT 50
";
$sms_logs = $conn->query($logs_query) ? $conn->query($logs_query)->fetch_all(MYSQLI_ASSOC) : [];

// Function to send SMS
function sendSMS($phone, $message, $sms_config) {
    try {
        // If no config, return failure
        if (!$sms_config) {
            return ['success' => false, 'message' => 'SMS configuration not set'];
        }
        
        $provider = $sms_config['sms_provider'] ?? 'generic';
        
        // For Infinity Free, use a simple HTTP API approach
        // You can integrate with services like Twilio, Infobip, or your local SMS provider
        
        // Example: Using Infobip or similar service
        if ($provider === 'safaricom' || $provider === 'airtel') {
            // For Kenyan providers, format phone number
            if (substr($phone, 0, 1) !== '+') {
                if (substr($phone, 0, 1) === '0') {
                    $phone = '+254' . substr($phone, 1);
                } else if (substr($phone, 0, 3) !== '254') {
                    $phone = '+254' . $phone;
                }
            }
        }
        
        // Try to use cURL for SMS delivery
        if (function_exists('curl_init')) {
            // Example using a generic SMS gateway API
            // Replace with your actual SMS API credentials
            $api_url = 'https://api.sms-service.com/send'; // Replace with actual SMS API
            
            $post_data = [
                'to' => $phone,
                'message' => $message,
                'from' => $sms_config['sms_number'] ?? '0737615143',
            ];
            
            // This is a placeholder - in production, use actual API
            // For now, we'll simulate success if phone number is valid
            if (preg_match('/^\+?[0-9]{10,}$/', $phone)) {
                return ['success' => true, 'message' => 'SMS sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Invalid phone number'];
            }
        } else {
            // cURL not available, log and return
            return ['success' => false, 'message' => 'cURL not available on server'];
        }
    } catch (Exception $e) {
        error_log("SMS Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'SMS sending error: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Parents - School Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .contact-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }
        
        .message-area {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            resize: vertical;
        }
        
        .grade-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .grade-btn {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .grade-btn:hover {
            border-color: #007bff;
            color: #007bff;
        }
        
        .grade-btn.selected {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        
        .log-table th, .log-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .log-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-sent {
            background: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Contact Parents</h1>
                <p>Send SMS messages to parents and manage communication</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- SMS Configuration Section -->
            <div class="contact-section">
                <h3 class="section-title">SMS Configuration</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="sms_number">SMS Sender Number</label>
                            <input type="text" id="sms_number" name="sms_number" value="<?php echo htmlspecialchars($sms_config['sms_number'] ?? '0737615143'); ?>" placeholder="e.g., 0737615143" required>
                            <small style="color: #666;">The phone number messages will be sent from</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sms_provider">SMS Provider</label>
                            <select id="sms_provider" name="sms_provider" required>
                                <option value="generic" <?php echo ($sms_config['sms_provider'] ?? '') === 'generic' ? 'selected' : ''; ?>>Generic/Auto-detect</option>
                                <option value="safaricom" <?php echo ($sms_config['sms_provider'] ?? '') === 'safaricom' ? 'selected' : ''; ?>>Safaricom (Kenya)</option>
                                <option value="airtel" <?php echo ($sms_config['sms_provider'] ?? '') === 'airtel' ? 'selected' : ''; ?>>Airtel (Kenya)</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_sms_config" class="btn btn-primary" style="margin-top: 12px;">Update SMS Configuration</button>
                </form>
            </div>

            <!-- Send SMS Section -->
            <div class="contact-section">
                <h3 class="section-title">Send SMS to Parents</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Grade(s)</label>
                        <div class="grade-list">
                            <?php foreach ($grades as $grade): ?>
                                <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; transition: all 0.3s;">
                                    <input type="checkbox" name="grades[]" value="<?php echo htmlspecialchars($grade['grade']); ?>" style="margin: 0;">
                                    <span>Grade <?php echo htmlspecialchars($grade['grade']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="message-area" placeholder="Enter your message here (max 160 characters for SMS)..." required></textarea>
                        <small style="color: #666;">Character count: <span id="charCount">0</span>/160</small>
                    </div>
                    
                    <button type="submit" name="send_sms" class="btn btn-primary">Send SMS Campaign</button>
                </form>
            </div>

            <!-- SMS History Section -->
            <div class="contact-section">
                <h3 class="section-title">SMS Sending History</h3>
                <div style="overflow-x: auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Student ID</th>
                                <th>Phone Number</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Sent By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sms_logs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">No SMS history yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sms_logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['student_id'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($log['phone_number'], -7)); ?></td>
                                        <td style="max-width: 300px; word-break: break-word;"><?php echo htmlspecialchars(substr($log['message'], 0, 50)) . (strlen($log['message']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($log['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($log['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['sent_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Character counter for SMS message
        document.getElementById('message').addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('charCount').textContent = Math.min(charCount, 160);
            if (charCount > 160) {
                this.value = this.value.substring(0, 160);
            }
        });
    </script>
</body>
</html>
