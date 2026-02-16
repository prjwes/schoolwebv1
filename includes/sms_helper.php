<?php
/**
 * SMS Helper Functions
 * Supports multiple SMS providers for Infinity Free hosting
 * Providers: Twilio, Nexmo, AWS SNS, Direct HTTP (for local providers)
 */

/**
 * Get SMS Configuration
 */
function getSMSConfig() {
    try {
        $conn = getDBConnection();
        if (!$conn) return null;
        
        $result = $conn->query("SELECT * FROM sms_config WHERE id = 1 LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    } catch (Exception $e) {
        error_log("getSMSConfig error: " . $e->getMessage());
        return null;
    }
}

/**
 * Send SMS via configured provider
 * 
 * @param string $phone Phone number (format: +256701234567)
 * @param string $message Message text
 * @param int $sent_by_id User ID of sender
 * @param string $recipient_name Name of recipient
 * @return array ['success' => bool, 'message' => string, 'external_id' => string]
 */
function sendSMS($phone, $message, $sent_by_id = null, $recipient_name = '') {
    try {
        $config = getSMSConfig();
        
        if (!$config || !$config['is_active']) {
            return [
                'success' => false,
                'message' => 'SMS service is not configured or disabled'
            ];
        }

        // Validate phone number
        $phone = sanitizePhoneNumber($phone);
        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Invalid phone number format'
            ];
        }

        // Truncate message if too long
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }

        $result = [];
        $provider = $config['provider'];

        switch ($provider) {
            case 'twilio':
                $result = sendSMSViaTwilio($phone, $message, $config);
                break;
            case 'nexmo':
                $result = sendSMSViaNexmo($phone, $message, $config);
                break;
            case 'aws_sns':
                $result = sendSMSViaAWS($phone, $message, $config);
                break;
            case 'custom_http':
                $result = sendSMSViaHTTP($phone, $message, $config);
                break;
            default:
                $result = [
                    'success' => false,
                    'message' => 'Unknown SMS provider: ' . $provider
                ];
        }

        // Log SMS attempt
        logSMSAttempt($phone, $message, $sent_by_id, $recipient_name, $result, $provider);

        return $result;

    } catch (Exception $e) {
        error_log("sendSMS error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error sending SMS: ' . $e->getMessage()
        ];
    }
}

/**
 * Send SMS via Twilio
 */
function sendSMSViaTwilio($phone, $message, $config) {
    try {
        $accountSID = $config['api_key'];
        $authToken = $config['api_secret'];
        $twilio_number = $config['sender_name'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSID/Messages.json";

        $post_data = [
            'From' => $twilio_number,
            'To' => $phone,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_USERPWD, "$accountSID:$authToken");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 201) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'external_id' => $data['sid'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Twilio Error: HTTP ' . $http_code
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Twilio Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send SMS via Nexmo/Vonage
 */
function sendSMSViaNexmo($phone, $message, $config) {
    try {
        $api_key = $config['api_key'];
        $api_secret = $config['api_secret'];
        $sender_name = $config['sender_name'];

        $url = "https://rest.nexmo.com/sms/json?" . http_build_query([
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'to' => $phone,
            'from' => $sender_name,
            'text' => $message
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['messages'][0]['status']) && $data['messages'][0]['status'] == '0') {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'external_id' => $data['messages'][0]['message-id'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Nexmo Error: ' . ($data['messages'][0]['error-text'] ?? 'Unknown error')
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Nexmo Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send SMS via AWS SNS
 */
function sendSMSViaAWS($phone, $message, $config) {
    try {
        // AWS requires SDK which may not be available on Infinity Free
        // This is a placeholder for when AWS SDK is available
        return [
            'success' => false,
            'message' => 'AWS SMS not available on this hosting'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'AWS Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send SMS via Custom HTTP endpoint
 * For local SMS providers or custom integrations
 */
function sendSMSViaHTTP($phone, $message, $config) {
    try {
        $api_key = $config['api_key'];
        $url = $config['api_secret']; // Store endpoint URL in api_secret
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Invalid HTTP endpoint configured'
            ];
        }

        $post_data = [
            'phone' => $phone,
            'message' => $message,
            'api_key' => $api_key
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'external_id' => $data['id'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $http_code
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'HTTP Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Sanitize phone number
 */
function sanitizePhoneNumber($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Ensure it starts with country code (at least 10 digits)
    if (strlen($phone) < 10) {
        return false;
    }
    
    // Format: +256XXXXXXXXX or 256XXXXXXXXX
    if (strlen($phone) == 10) {
        $phone = '256' . $phone; // Add Uganda country code
    }
    
    if (strlen($phone) == 12 && substr($phone, 0, 3) != '256') {
        return false; // Invalid
    }
    
    return '+' . $phone;
}

/**
 * Log SMS attempt
 */
function logSMSAttempt($phone, $message, $sent_by_id, $recipient_name, $result, $provider) {
    try {
        $conn = getDBConnection();
        if (!$conn) return false;

        $status = $result['success'] ? 'sent' : 'failed';
        $error_message = $result['success'] ? null : $result['message'];
        $external_id = $result['external_id'] ?? null;

        $stmt = $conn->prepare("
            INSERT INTO sms_logs 
            (recipient_phone, recipient_name, message_text, sent_by_id, sent_by_name, status, error_message, provider, external_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $sent_by_name = 'System';
        if ($sent_by_id) {
            $user_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
            $user_stmt->bind_param("i", $sent_by_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();
                $sent_by_name = $user['full_name'];
            }
            $user_stmt->close();
        }

        $stmt->bind_param(
            "sssisssss",
            $phone,
            $recipient_name,
            $message,
            $sent_by_id,
            $sent_by_name,
            $status,
            $error_message,
            $provider,
            $external_id
        );

        return $stmt->execute();

    } catch (Exception $e) {
        error_log("logSMSAttempt error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get SMS logs with pagination
 */
function getSMSLogs($limit = 50, $offset = 0) {
    try {
        $conn = getDBConnection();
        if (!$conn) return [];

        $limit = intval($limit);
        $offset = intval($offset);

        $result = $conn->query("
            SELECT * FROM sms_logs 
            ORDER BY sent_at DESC 
            LIMIT $limit OFFSET $offset
        ");

        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    } catch (Exception $e) {
        error_log("getSMSLogs error: " . $e->getMessage());
        return [];
    }
}

/**
 * Bulk send SMS to parents
 */
function sendBulkSMS($student_ids, $message, $sent_by_id) {
    $results = [
        'total' => 0,
        'sent' => 0,
        'failed' => 0,
        'details' => []
    ];

    try {
        $conn = getDBConnection();
        if (!$conn) return $results;

        foreach ($student_ids as $student_id) {
            $stmt = $conn->prepare("
                SELECT s.id, u.full_name, s.parent_phone, s.parent_name 
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$student || !$student['parent_phone']) {
                $results['details'][] = [
                    'student' => $student['full_name'] ?? 'Unknown',
                    'success' => false,
                    'message' => 'No parent phone number'
                ];
                $results['failed']++;
                continue;
            }

            $sms_result = sendSMS(
                $student['parent_phone'],
                $message,
                $sent_by_id,
                $student['parent_name']
            );

            $results['total']++;
            if ($sms_result['success']) {
                $results['sent']++;
                $results['details'][] = [
                    'student' => $student['full_name'],
                    'success' => true,
                    'message' => 'Sent successfully'
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'student' => $student['full_name'],
                    'success' => false,
                    'message' => $sms_result['message']
                ];
            }
        }

    } catch (Exception $e) {
        error_log("sendBulkSMS error: " . $e->getMessage());
    }

    return $results;
}
?>
