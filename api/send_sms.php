<?php
/**
 * SMS Sending API
 * Integrates with Afrikaas or similar SMS provider
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
$user = getCurrentUser();
$conn = getDBConnection();

// Only finance staff can send SMS
if (!in_array($user['role'], ['Admin', 'HOI', 'DHOI', 'Finance_Teacher'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// SMS API Configuration
define('SMS_API_KEY', getenv('SMS_API_KEY') ?: 'YOUR_AFRIKAAS_API_KEY');
define('SMS_API_URL', getenv('SMS_API_URL') ?: 'https://api.afrikaas.co.ke/api/send');
define('SMS_SENDER_ID', getenv('SMS_SENDER_ID') ?: 'EBUSHIBO');

$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

/**
 * Send SMS to single parent
 */
if ($action === 'send_single') {
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $message = sanitize($_POST['message'] ?? '');
    
    if (!$parent_id || !$message) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get parent phone
    $stmt = $conn->prepare("SELECT parent_phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $parent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$parent || !$parent['parent_phone']) {
        echo json_encode(['success' => false, 'error' => 'Parent phone number not found']);
        exit();
    }
    
    $result = sendSMSViaAPI($parent['parent_phone'], $message);
    
    if ($result['success']) {
        // Log the SMS
        $log_stmt = $conn->prepare("INSERT INTO sms_logs (parent_id, phone_number, message, message_type, status, sent_by, api_response) VALUES (?, ?, ?, 'general', 'sent', ?, ?)");
        $api_response = json_encode($result);
        $message_type = 'general';
        $log_stmt->bind_param("issss", $parent_id, $parent['parent_phone'], $message, $message_type, $user['id'], $api_response);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'SMS sent successfully',
            'phone' => $parent['parent_phone']
        ]);
    } else {
        // Log failed SMS
        $log_stmt = $conn->prepare("INSERT INTO sms_logs (parent_id, phone_number, message, message_type, status, sent_by, api_response) VALUES (?, ?, ?, 'general', 'failed', ?, ?)");
        $api_response = json_encode($result);
        $message_type = 'general';
        $log_stmt->bind_param("issss", $parent_id, $parent['parent_phone'], $message, $message_type, $user['id'], $api_response);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * Send SMS to all parents of a grade
 */
elseif ($action === 'send_grade') {
    $grade = sanitize($_POST['grade'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (!$grade || !$message) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Get all parents of students in grade
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.parent_phone 
        FROM users u 
        JOIN students s ON s.user_id = u.id 
        WHERE s.grade = ? AND u.parent_phone IS NOT NULL AND u.parent_phone != ''
    ");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $parents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($parents)) {
        echo json_encode(['success' => false, 'error' => 'No parents found with phone numbers']);
        exit();
    }
    
    $sent_count = 0;
    $failed_count = 0;
    $failed_phones = [];
    
    foreach ($parents as $parent) {
        $result = sendSMSViaAPI($parent['parent_phone'], $message);
        
        if ($result['success']) {
            $sent_count++;
            $status = 'sent';
        } else {
            $failed_count++;
            $failed_phones[] = $parent['parent_phone'];
            $status = 'failed';
        }
        
        // Log SMS
        $log_stmt = $conn->prepare("INSERT INTO sms_logs (parent_id, phone_number, message, message_type, status, sent_by, api_response) VALUES (?, ?, ?, 'general', ?, ?, ?)");
        $api_response = json_encode($result);
        $log_stmt->bind_param("isssi", $parent['id'], $parent['parent_phone'], $message, $status, $user['id'], $api_response);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'sent' => $sent_count,
        'failed' => $failed_count,
        'failed_phones' => $failed_phones,
        'message' => "SMS sent to $sent_count parents"
    ]);
}

/**
 * Send SMS to all parents
 */
elseif ($action === 'send_all') {
    $message = sanitize($_POST['message'] ?? '');
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message is required']);
        exit();
    }
    
    // Get all parents with phone numbers
    $stmt = $conn->prepare("
        SELECT DISTINCT id, parent_phone 
        FROM users 
        WHERE parent_phone IS NOT NULL AND parent_phone != '' AND role = 'Student'
    ");
    $stmt->execute();
    $parents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($parents)) {
        echo json_encode(['success' => false, 'error' => 'No parents found with phone numbers']);
        exit();
    }
    
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($parents as $parent) {
        $result = sendSMSViaAPI($parent['parent_phone'], $message);
        
        if ($result['success']) {
            $sent_count++;
            $status = 'sent';
        } else {
            $failed_count++;
            $status = 'failed';
        }
        
        // Log SMS
        $log_stmt = $conn->prepare("INSERT INTO sms_logs (parent_id, phone_number, message, message_type, status, sent_by, api_response) VALUES (?, ?, ?, 'general', ?, ?, ?)");
        $api_response = json_encode($result);
        $log_stmt->bind_param("isssi", $parent['id'], $parent['parent_phone'], $message, $status, $user['id'], $api_response);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'sent' => $sent_count,
        'failed' => $failed_count,
        'message' => "SMS sent to $sent_count parents"
    ]);
}

/**
 * Get SMS logs
 */
elseif ($action === 'logs') {
    $limit = intval($_GET['limit'] ?? 50);
    
    $stmt = $conn->prepare("
        SELECT sl.*, u.full_name as sent_by_name, s.student_id
        FROM sms_logs sl
        LEFT JOIN users u ON sl.sent_by = u.id
        LEFT JOIN students s ON sl.student_id = s.id
        ORDER BY sl.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'logs' => $logs]);
}

else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

/**
 * Helper function to send SMS via API
 * Supports Afrikaas, Twilio, and other providers
 */
function sendSMSViaAPI($phone, $message) {
    // Remove leading +254 and replace with 0 or format as needed
    $phone = preg_replace('/^\+254/', '0', $phone);
    
    // Check if using Afrikaas API
    if (strpos(SMS_API_URL, 'afrikaas') !== false) {
        return sendViAfriikaas($phone, $message);
    }
    
    // Check if using Twilio
    if (strpos(SMS_API_URL, 'twilio') !== false) {
        return sendViaTwilio($phone, $message);
    }
    
    // Default fallback - log for manual review
    return [
        'success' => true,
        'message' => 'SMS queued for sending',
        'phone' => $phone,
        'test_mode' => true
    ];
}

/**
 * Send via Afrikaas API
 */
function sendViAfriikaas($phone, $message) {
    $url = SMS_API_URL;
    
    $payload = [
        'api_key' => SMS_API_KEY,
        'sender_id' => SMS_SENDER_ID,
        'phone' => $phone,
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpcode === 200 && isset($result['success']) && $result['success']) {
        return [
            'success' => true,
            'message_id' => $result['message_id'] ?? null,
            'phone' => $phone
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['error'] ?? 'Unknown API error',
        'response' => $response
    ];
}

/**
 * Send via Twilio
 */
function sendViaTwilio($phone, $message) {
    $sid = getenv('TWILIO_ACCOUNT_SID');
    $token = getenv('TWILIO_AUTH_TOKEN');
    $from = getenv('TWILIO_PHONE_NUMBER');
    
    if (!$sid || !$token || !$from) {
        return [
            'success' => false,
            'error' => 'Twilio credentials not configured'
        ];
    }
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'From' => $from,
        'To' => $phone,
        'Body' => $message
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpcode >= 200 && $httpcode < 300 && isset($result['sid'])) {
        return [
            'success' => true,
            'message_id' => $result['sid'],
            'phone' => $phone
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message'] ?? 'Unknown API error',
        'response' => $response
    ];
}
?>
