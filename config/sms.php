<?php
/**
 * SMS Notification System
 * Uses free SMS services like Textbelt, SMSGateway.me, or Twilio free tier
 */

/**
 * SMS Service Configuration
 * Using Textbelt (free for development) - https://textbelt.com/
 * Alternative: SMSGateway.me, Twilio free tier
 */
define('SMS_SERVICE', 'textbelt'); // textbelt, twilio, smsgateway
define('SMS_API_KEY', 'your-api-key-here'); // For Twilio or paid services
define('SMS_FROM_NUMBER', '+1234567890'); // Your SMS sender number

/**
 * Send SMS using various free/cheap services
 */
function sendSMS($phone, $message) {
    // Log SMS for development
    logSMSForDevelopment($phone, $message);
    
    switch (SMS_SERVICE) {
        case 'textbelt':
            return sendSMSTextbelt($phone, $message);
        case 'twilio':
            return sendSMSTwilio($phone, $message);
        case 'smsgateway':
            return sendSMSSMSGateway($phone, $message);
        default:
            return sendSMSFallback($phone, $message);
    }
}

/**
 * Send SMS using Textbelt (Free tier: 1 SMS per day per IP)
 */
function sendSMSTextbelt($phone, $message) {
    // Convert Indian number format if needed
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '+91' . $phone;
    }
    
    $data = [
        'phone' => $phone,
        'message' => $message,
        'key' => 'textbelt' // Use 'textbelt' for free tier (1 SMS/day)
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://textbelt.com/text',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            logActivity(null, 'sms_sent', "SMS sent to: $phone via Textbelt");
            return true;
        } else {
            logActivity(null, 'sms_failed', "SMS failed to: $phone via Textbelt. Error: " . ($result['error'] ?? 'Unknown'));
            return false;
        }
    }
    
    logActivity(null, 'sms_failed', "SMS failed to: $phone via Textbelt. HTTP Code: $httpCode");
    return false;
}

/**
 * Send SMS using Twilio (Free trial: $15 credit)
 */
function sendSMSTwilio($phone, $message) {
    // This requires Twilio PHP SDK
    // composer require twilio/sdk
    
    if (!SMS_API_KEY || SMS_API_KEY === 'your-api-key-here') {
        return sendSMSFallback($phone, $message);
    }
    
    // Convert Indian number format
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '+91' . $phone;
    }
    
    // Twilio implementation would go here
    // For now, fallback to development logging
    return sendSMSFallback($phone, $message);
}

/**
 * Send SMS using SMSGateway.me (Free tier available)
 */
function sendSMSSMSGateway($phone, $message) {
    // Convert Indian number format
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        $phone = '+91' . $phone;
    }
    
    // SMSGateway.me implementation would go here
    // For now, fallback to development logging
    return sendSMSFallback($phone, $message);
}

/**
 * Fallback SMS logging for development
 */
function sendSMSFallback($phone, $message) {
    return logSMSForDevelopment($phone, $message);
}

/**
 * Log SMS for development
 */
function logSMSForDevelopment($phone, $message) {
    $logsDir = '../logs';
    $logFile = $logsDir . '/sms.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logsDir)) {
        if (!mkdir($logsDir, 0755, true)) {
            error_log("Failed to create logs directory: $logsDir");
            return false;
        }
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "\n" . str_repeat("=", 60) . "\n";
    $logMessage .= "[$timestamp] SMS SIMULATION\n";
    $logMessage .= "To: $phone\n";
    $logMessage .= "Message: $message\n";
    $logMessage .= str_repeat("=", 60) . "\n";
    
    if (error_log($logMessage, 3, $logFile) === false) {
        error_log("Failed to write to SMS log file: $logFile");
        return false;
    }
    
    return true;
}

/**
 * Send OTP via SMS
 */
function sendOTPSMS($phone, $otp, $purpose = 'login') {
    $message = "GASC Blood Bridge: Your OTP is $otp. Valid for 10 minutes. Do not share with anyone. Purpose: $purpose";
    return sendSMS($phone, $message);
}

/**
 * Send blood request notification via SMS
 */
function sendBloodRequestSMS($phone, $donorName, $requestDetails) {
    $urgencyEmoji = match($requestDetails['urgency']) {
        'Critical' => '🚨',
        'Urgent' => '⚠️',
        'Normal' => 'ℹ️',
        default => '📢'
    };
    
    $message = "$urgencyEmoji GASC Blood Bridge: Urgent {$requestDetails['blood_group']} blood needed in {$requestDetails['city']}. ";
    $message .= "Contact: {$requestDetails['requester_phone']}. ";
    $message .= "Your help can save lives! -GASC Team";
    
    return sendSMS($phone, $message);
}

/**
 * Send donation reminder SMS
 */
function sendDonationReminderSMS($phone, $donorName) {
    $message = "GASC Blood Bridge: Hi $donorName! You're now eligible to donate blood again. ";
    $message .= "Your contribution can save lives. Visit our portal to update availability. -GASC Team";
    
    return sendSMS($phone, $message);
}

/**
 * Send verification SMS for account
 */
function sendAccountVerificationSMS($phone, $name) {
    $message = "GASC Blood Bridge: Hi $name! Your donor account has been verified. ";
    $message .= "You can now receive blood request notifications. Thank you for joining our mission! -GASC Team";
    
    return sendSMS($phone, $message);
}

/**
 * Validate Indian phone number
 */
function isValidIndianPhone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Check if it's a valid 10-digit Indian mobile number
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

/**
 * Format Indian phone number
 */
function formatIndianPhone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Add +91 prefix if it's a valid Indian number
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        return '+91' . $phone;
    }
    
    return $phone;
}

/**
 * Send batch SMS to multiple recipients
 */
function sendBatchSMS($phoneNumbers, $message) {
    $successCount = 0;
    $failureCount = 0;
    
    foreach ($phoneNumbers as $phone) {
        if (sendSMS($phone, $message)) {
            $successCount++;
        } else {
            $failureCount++;
        }
        
        // Add small delay to avoid rate limiting
        usleep(500000); // 0.5 second delay
    }
    
    logActivity(null, 'batch_sms_sent', "Batch SMS: $successCount sent, $failureCount failed");
    
    return [
        'success' => $successCount,
        'failed' => $failureCount,
        'total' => count($phoneNumbers)
    ];
}
?>
