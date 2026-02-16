<?php
/**
 * M-Pesa STK Push API
 * Handles M-Pesa payment initiation for fee payments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// M-Pesa Configuration
define('MPESA_BUSINESS_SHORTCODE', getenv('MPESA_BUSINESS_SHORTCODE') ?: '174379');
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: 'YOUR_KEY');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: 'YOUR_SECRET');
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: 'YOUR_PASSKEY');
define('MPESA_ENVIRONMENT', getenv('MPESA_ENVIRONMENT') ?: 'production');
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: 'https://schoolweb.ct.ws/api/mpesa_callback.php');

$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

/**
 * Initiate STK Push
 */
if ($action === 'initiate') {
    $student_fee_id = intval($_POST['student_fee_id'] ?? 0);
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    
    if (!$student_fee_id || !$phone_number) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    $conn = getDBConnection();
    
    // Get fee details
    $stmt = $conn->prepare("
        SELECT sf.*, ft.fee_name, s.student_id
        FROM student_fees sf
        JOIN fee_types ft ON sf.fee_type_id = ft.id
        JOIN students s ON sf.student_id = s.id
        WHERE sf.id = ?
    ");
    $stmt->bind_param("i", $student_fee_id);
    $stmt->execute();
    $fee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$fee) {
        echo json_encode(['success' => false, 'error' => 'Fee not found']);
        exit();
    }
    
    // Format phone number
    $phone = formatPhoneForMPesa($phone_number);
    $amount = intval($fee['amount_due'] - $fee['amount_paid']);
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'No outstanding balance']);
        exit();
    }
    
    // Initiate STK push
    $result = initiateSTKPush($phone, $amount, $student_fee_id);
    
    if ($result['success']) {
        // Log transaction
        $log_stmt = $conn->prepare("
            INSERT INTO mpesa_transactions (student_id, phone_number, amount, mpesa_request_id, status)
            VALUES (?, ?, ?, ?, 'initiated')
        ");
        $student_id = $fee['student_id'];
        $mpesa_request_id = $result['RequestId'] ?? null;
        $log_stmt->bind_param("isds", $student_id, $phone_number, $amount, $mpesa_request_id);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'STK prompt sent',
            'request_id' => $result['RequestId'] ?? null,
            'amount' => $amount,
            'phone' => $phone
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * Query STK transaction status
 */
elseif ($action === 'query_status') {
    $checkout_request_id = sanitize($_GET['checkout_request_id'] ?? '');
    
    if (!$checkout_request_id) {
        echo json_encode(['success' => false, 'error' => 'Checkout request ID required']);
        exit();
    }
    
    $result = querySTKStatus($checkout_request_id);
    echo json_encode($result);
}

/**
 * Process M-Pesa callback
 */
elseif ($action === 'callback') {
    $callback_data = file_get_contents('php://input');
    $data = json_decode($callback_data, true);
    
    $conn = getDBConnection();
    
    // Extract M-Pesa response
    if (isset($data['Body']['stkCallback'])) {
        $stk_callback = $data['Body']['stkCallback'];
        $result_code = $stk_callback['ResultCode'];
        $result_desc = $stk_callback['ResultDesc'];
        $checkout_request_id = $stk_callback['CheckoutRequestID'];
        
        if ($result_code == 0) {
            // Payment successful
            $callback_metadata = $stk_callback['CallbackMetadata']['Item'];
            $amount = null;
            $mpesa_reference = null;
            $phone = null;
            
            foreach ($callback_metadata as $item) {
                if ($item['Name'] === 'Amount') $amount = $item['Value'];
                if ($item['Name'] === 'MpesaReceiptNumber') $mpesa_reference = $item['Value'];
                if ($item['Name'] === 'PhoneNumber') $phone = $item['Value'];
            }
            
            // Update transaction
            $stmt = $conn->prepare("
                UPDATE mpesa_transactions
                SET status = 'success', mpesa_response_code = '0', mpesa_response_message = ?, completed_at = NOW()
                WHERE mpesa_request_id = ?
            ");
            $stmt->bind_param("ss", $result_desc, $checkout_request_id);
            $stmt->execute();
            $stmt->close();
            
            // Create fee payment record
            if ($amount && $mpesa_reference) {
                // Get student fee from transaction
                $get_txn = $conn->prepare("SELECT student_id FROM mpesa_transactions WHERE mpesa_request_id = ?");
                $get_txn->bind_param("s", $checkout_request_id);
                $get_txn->execute();
                $txn = $get_txn->get_result()->fetch_assoc();
                $get_txn->close();
                
                if ($txn) {
                    // Update student fee amount paid
                    $update_fee = $conn->prepare("
                        UPDATE student_fees
                        SET amount_paid = amount_paid + ?, status = IF(amount_paid + ? >= amount_due, 'paid', 'partial'), last_payment_date = NOW()
                        WHERE student_id = ?
                    ");
                    $update_fee->bind_param("ddi", $amount, $amount, $txn['student_id']);
                    $update_fee->execute();
                    $update_fee->close();
                }
            }
            
            // Log success
            error_log("M-Pesa Payment Success: $mpesa_reference for " . $amount);
        } else {
            // Payment failed
            $update = $conn->prepare("
                UPDATE mpesa_transactions
                SET status = 'failed', mpesa_response_code = ?, mpesa_response_message = ?
                WHERE mpesa_request_id = ?
            ");
            $update->bind_param("sss", $result_code, $result_desc, $checkout_request_id);
            $update->execute();
            $update->close();
            
            error_log("M-Pesa Payment Failed: $result_code - $result_desc");
        }
    }
    
    // Acknowledge receipt
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed']);
}

/**
 * Get payment history
 */
elseif ($action === 'history') {
    $student_id = intval($_GET['student_id'] ?? 0);
    $conn = getDBConnection();
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'error' => 'Student ID required']);
        exit();
    }
    
    $stmt = $conn->prepare("
        SELECT mt.*, sf.fee_name
        FROM mpesa_transactions mt
        LEFT JOIN student_fees sf ON mt.student_id = sf.student_id
        WHERE mt.student_id = ?
        ORDER BY mt.initiated_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'history' => $history]);
}

else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

/**
 * Helper Functions
 */

function formatPhoneForMPesa($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 254, it's international format - convert to 254...
    if (strpos($phone, '254') === 0) {
        return $phone;
    }
    
    // If starts with 0, replace with 254
    if (strpos($phone, '0') === 0) {
        return '254' . substr($phone, 1);
    }
    
    // If 10 digits, assume Kenya and prepend 254
    if (strlen($phone) === 10) {
        return '254' . substr($phone, 1);
    }
    
    return $phone;
}

function initiateSTKPush($phone, $amount, $student_fee_id) {
    // Get access token
    $token = getMPesaAccessToken();
    
    if (!$token) {
        return ['success' => false, 'error' => 'Failed to get M-Pesa token'];
    }
    
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_BUSINESS_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $url = MPESA_ENVIRONMENT === 'sandbox' 
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $payload = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => intval($amount),
        'PartyA' => $phone,
        'PartyB' => MPESA_BUSINESS_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => 'FEE' . $student_fee_id,
        'TransactionDesc' => 'School Fees Payment'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
        return [
            'success' => true,
            'RequestId' => $result['CheckoutRequestID'] ?? null,
            'message' => $result['ResponseDescription'] ?? 'STK prompt sent'
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error'
    ];
}

function getMPesaAccessToken() {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return isset($result['access_token']) ? $result['access_token'] : null;
}

function querySTKStatus($checkout_request_id) {
    $token = getMPesaAccessToken();
    
    if (!$token) {
        return ['success' => false, 'error' => 'Failed to get M-Pesa token'];
    }
    
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_BUSINESS_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $url = MPESA_ENVIRONMENT === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    
    $payload = [
        'BusinessShortCode' => MPESA_BUSINESS_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkout_request_id
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
        return [
            'success' => true,
            'status' => $result['ResultDesc'] ?? 'Unknown'
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['errorMessage'] ?? 'Unknown error'
    ];
}
?>
