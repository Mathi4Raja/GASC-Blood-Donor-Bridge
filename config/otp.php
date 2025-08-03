<?php
/**
 * OTP Generation and Verification System
 */

/**
 * Generate a secure OTP
 */
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Store OTP in database
 */
function storeOTP($email, $phone, $otp, $purpose = 'login') {
    try {
        $db = new Database();
        
        // Delete any existing OTPs for this email/phone and purpose
        $deleteSQL = "DELETE FROM otp_verifications WHERE (email = ? OR email = ?) AND purpose = ?";
        $db->query($deleteSQL, [$email, $phone, $purpose]);
        
        // Store new OTP with 10-minute expiry
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        $insertSQL = "INSERT INTO otp_verifications (email, otp, purpose, expires_at) VALUES (?, ?, ?, ?)";
        $db->query($insertSQL, [$email, $otp, $purpose, $expiresAt]);
        
        logActivity(null, 'otp_generated', "OTP generated for: $email, Purpose: $purpose");
        return true;
        
    } catch (Exception $e) {
        logActivity(null, 'otp_generation_failed', "OTP generation failed for: $email, Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify OTP
 */
function verifyOTP($email, $otp, $purpose = 'login') {
    try {
        $db = new Database();
        
        // Find valid OTP
        $sql = "SELECT id FROM otp_verifications 
                WHERE email = ? AND otp = ? AND purpose = ? 
                AND is_used = FALSE AND expires_at > NOW()";
        $result = $db->query($sql, [$email, $otp, $purpose]);
        
        if ($result->num_rows === 0) {
            logActivity(null, 'otp_verification_failed', "Invalid or expired OTP for: $email");
            return false;
        }
        
        $otpRecord = $result->fetch_assoc();
        
        // Mark OTP as used
        $updateSQL = "UPDATE otp_verifications SET is_used = TRUE WHERE id = ?";
        $db->query($updateSQL, [$otpRecord['id']]);
        
        logActivity(null, 'otp_verified', "OTP verified for: $email, Purpose: $purpose");
        return true;
        
    } catch (Exception $e) {
        logActivity(null, 'otp_verification_error', "OTP verification error for: $email, Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean expired OTPs (should be run periodically)
 */
function cleanExpiredOTPs() {
    try {
        $db = new Database();
        $sql = "DELETE FROM otp_verifications WHERE expires_at < NOW()";
        $result = $db->query($sql);
        
        $deletedCount = $db->lastAffectedRows();
        logActivity(null, 'otp_cleanup', "Cleaned $deletedCount expired OTPs");
        
        return $deletedCount;
        
    } catch (Exception $e) {
        logActivity(null, 'otp_cleanup_error', "Error cleaning expired OTPs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if email/phone has too many OTP requests (rate limiting)
 */
function checkOTPRateLimit($email, $maxAttempts = 3, $timeWindow = 300) {
    try {
        $db = new Database();
        
        $windowStart = date('Y-m-d H:i:s', time() - $timeWindow);
        $sql = "SELECT COUNT(*) as attempt_count FROM otp_verifications 
                WHERE email = ? AND created_at > ?";
        $result = $db->query($sql, [$email, $windowStart]);
        
        $count = $result->fetch_assoc()['attempt_count'];
        
        if ($count >= $maxAttempts) {
            logActivity(null, 'otp_rate_limit_exceeded', "Rate limit exceeded for: $email");
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        logActivity(null, 'otp_rate_limit_error', "Error checking rate limit for: $email");
        return false; // Err on the side of caution
    }
}

/**
 * Send OTP to user (both email and SMS)
 */
function sendOTPToUser($email, $phone, $purpose = 'login', $userName = '') {
    // Check rate limiting
    if (!checkOTPRateLimit($email)) {
        return [
            'success' => false,
            'message' => 'Too many OTP requests. Please try again in 5 minutes.'
        ];
    }
    
    // Generate OTP
    $otp = generateOTP(6);
    
    // Store in database
    if (!storeOTP($email, $phone, $otp, $purpose)) {
        return [
            'success' => false,
            'message' => 'Failed to generate OTP. Please try again.'
        ];
    }
    
    // Send via email
    require_once '../config/email.php';
    $emailSent = sendOTPEmail($email, $otp, $purpose);
    
    // Send via SMS
    require_once '../config/sms.php';
    $smsSent = sendOTPSMS($phone, $otp, $purpose);
    
    // Log results
    if ($emailSent && $smsSent) {
        $message = 'OTP sent to both email and SMS';
    } elseif ($emailSent) {
        $message = 'OTP sent to email (SMS failed)';
    } elseif ($smsSent) {
        $message = 'OTP sent to SMS (Email failed)';
    } else {
        return [
            'success' => false,
            'message' => 'Failed to send OTP. Please try again.'
        ];
    }
    
    return [
        'success' => true,
        'message' => $message,
        'email_sent' => $emailSent,
        'sms_sent' => $smsSent
    ];
}
?>
