<?php
/**
 * Email Configuration and Functions
 * Uses PHPMailer with Gmail SMTP (free tier)
 */

// Load environment configuration
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/site.php';

// Include PHPMailer classes manually if available
$phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
$phpmailer_available = false;

if (file_exists($phpmailer_path . 'PHPMailer.php')) {
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
    require_once $phpmailer_path . 'Exception.php';
    $phpmailer_available = true;
}

// Check for Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $phpmailer_available = true;
}

/**
 * Email configuration settings - loaded from environment variables
 */
define('SMTP_HOST', EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)EnvLoader::get('SMTP_PORT', 587));
define('SMTP_USERNAME', EnvLoader::get('SMTP_USERNAME', 'your-email@gmail.com'));
define('SMTP_PASSWORD', EnvLoader::get('SMTP_PASSWORD', 'your-app-password'));
define('SMTP_FROM_EMAIL', EnvLoader::get('SMTP_FROM_EMAIL', EnvLoader::get('SMTP_USERNAME', 'your-email@gmail.com')));
define('SMTP_FROM_NAME', EnvLoader::get('SMTP_FROM_NAME', 'GASC Blood Bridge'));

/**
 * Send email using PHPMailer or fallback to logging
 */
function sendEmailSMTP($to, $subject, $body, $isHTML = true) {
    // Check if PHPMailer class is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailWithPHPMailer($to, $subject, $body, $isHTML);
    } else {
        // Fallback to logging for development
        return logEmailForDevelopment($to, $subject, $body);
    }
}

/**
 * Send email using PHPMailer
 */
function sendEmailWithPHPMailer($to, $subject, $body, $isHTML = true) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if (!$isHTML) {
            $mail->AltBody = $body;
        }
        
        $result = $mail->send();
        if ($result) {
            logActivity(null, 'email_sent', "Email sent to: $to, Subject: $subject");
            return true;
        } else {
            logActivity(null, 'email_failed', "Email failed to send to: $to");
            return false;
        }
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        logActivity(null, 'email_failed', "Email failed to: $to, Error: {$e->getMessage()}");
        return false;
    }
}

/**
 * Fallback email logging for development
 */
function logEmailForDevelopment($to, $subject, $body) {
    $logsDir = '../logs';
    $logFile = $logsDir . '/emails.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logsDir)) {
        if (!mkdir($logsDir, 0755, true)) {
            error_log("Failed to create logs directory: $logsDir");
            return false;
        }
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "\n" . str_repeat("=", 80) . "\n";
    $logMessage .= "[$timestamp] EMAIL SIMULATION\n";
    $logMessage .= "To: $to\n";
    $logMessage .= "Subject: $subject\n";
    $logMessage .= "Body:\n$body\n";
    $logMessage .= str_repeat("=", 80) . "\n";
    
    if (error_log($logMessage, 3, $logFile) === false) {
        error_log("Failed to write to email log file: $logFile");
        return false;
    }
    
    return true;
}



/**
 * Send notification email to donors about blood requests
 */
function sendBloodRequestNotification($donorEmail, $donorName, $requestDetails) {
    $subject = "Urgent Blood Request - Your Help Needed!";
    
    $urgencyColor = match($requestDetails['urgency']) {
        'Critical' => '#dc2626',
        'Urgent' => '#f59e0b',
        'Normal' => '#059669',
        default => '#6b7280'
    };
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px;'>
        <div style='background: linear-gradient(135deg, $urgencyColor, #7f1d1d); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0; font-size: 28px;'>Blood Request Alert</h1>
            <p style='color: #fee2e2; margin: 10px 0 0 0;'>GASC Blood Bridge</p>
        </div>
        
        <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <h2 style='color: #dc2626; margin-bottom: 20px;'>Hello $donorName,</h2>
            
            <div style='background: #fef2f2; border-left: 4px solid $urgencyColor; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0;'>
                <h3 style='color: $urgencyColor; margin: 0 0 15px 0;'>{$requestDetails['urgency']} Request</h3>
                <p style='margin: 0; color: #374151; font-size: 16px; line-height: 1.6;'>
                    <strong>Blood Group Needed:</strong> {$requestDetails['blood_group']}<br>
                    <strong>Location:</strong> {$requestDetails['city']}<br>
                    <strong>Units Required:</strong> {$requestDetails['units_needed']}<br>
                    <strong>Contact:</strong> {$requestDetails['requester_phone']}
                </p>
            </div>
            
            <div style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h4 style='color: #374151; margin: 0 0 10px 0;'>Request Details:</h4>
                <p style='color: #6b7280; margin: 0; line-height: 1.6;'>{$requestDetails['details']}</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='tel:{$requestDetails['requester_phone']}' style='background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px;'>
                    Call Now: {$requestDetails['requester_phone']}
                </a>
            </div>
            
            <div style='background: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #065f46; font-size: 14px;'>
                    <strong>Remember:</strong><br>
                    • Ensure you're eligible to donate (check your last donation date)<br>
                    • Carry a valid ID and stay hydrated<br>
                    • Your contribution can save lives!
                </p>
            </div>
            
            <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                Thank you for being a life-saver!<br>
                <strong>GASC Blood Bridge Team</strong>
            </p>
        </div>
        
        <div style='text-align: center; padding: 20px; color: #9ca3af; font-size: 12px;'>
            You received this because you're registered as an available {$requestDetails['blood_group']} donor in {$requestDetails['city']}.
        </div>
    </div>
    ";
    
    return sendEmailSMTP($donorEmail, $subject, $body, true);
}

/**
 * Send password reset email with role-specific links
 */
function sendPasswordResetEmail($email, $resetToken, $userName, $userType = 'donor') {
    // Generate reset link - handle different server configurations
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Generate role-specific reset link using site configuration
    if ($userType === 'admin' || $userType === 'moderator') {
        $resetPath = sitePath("admin/forgot-password.php?step=2&token=" . urlencode($resetToken));
    } else {
        $resetPath = sitePath("donor/forgot-password.php?step=2&token=" . urlencode($resetToken));
    }
    
    $resetLink = "$protocol://$host$resetPath";
    
    $subject = "GASC Blood Bridge - Password Reset Request";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0; font-size: 28px;'>GASC Blood Bridge</h1>
            <p style='color: #fee2e2; margin: 10px 0 0 0;'>Password Reset Request</p>
        </div>
        
        <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <h2 style='color: #dc2626; margin-bottom: 20px;'>Hello $userName,</h2>
            
            <p style='color: #374151; font-size: 16px; line-height: 1.6;'>
                We received a request to reset your password. Click the button below to set a new password:
            </p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    Reset Password
                </a>
            </div>
            
            <p style='color: #6b7280; font-size: 14px;'>
                Or copy and paste this link in your browser:<br>
                <span style='word-break: break-all; background: #f3f4f6; padding: 10px; border-radius: 4px; display: block; margin: 10px 0;'>$resetLink</span>
            </p>
            
            <div style='background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #92400e; font-size: 14px;'>
                    <strong>Security Notice:</strong><br>
                    • This link expires in 1 hour<br>
                    • If you didn't request this, please ignore this email<br>
                    • Your password won't change until you click the link above
                </p>
            </div>
            
            <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                Best regards,<br>
                <strong>GASC Blood Bridge Team</strong>
            </p>
        </div>
    </div>
    ";
    
    return sendEmailSMTP($email, $subject, $body, true);
}

/**
 * Simple wrapper for sending emails
 */
function sendEmail($to, $subject, $body, $isHTML = false) {
    return sendEmailSMTP($to, $subject, $body, $isHTML);
}
?>
