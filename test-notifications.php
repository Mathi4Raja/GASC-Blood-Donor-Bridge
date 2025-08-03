<?php
/**
 * Test Notification System
 * This file allows manual testing of the notification system
 * Access via: http://localhost/GASC Blood Donor Bridge/test-notifications.php?test=email
 */

require_once 'config/database.php';
require_once 'config/email.php';
require_once 'config/sms.php';
require_once 'config/otp.php';
require_once 'config/notifications.php';

// Security check - only allow in development
if ($_SERVER['HTTP_HOST'] !== 'localhost' && !isset($_GET['allow'])) {
    die('This test file is only available in development environment.');
}

$test = $_GET['test'] ?? '';
$email = $_GET['email'] ?? 'test@example.com';
$phone = $_GET['phone'] ?? '9876543210';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notifications - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #dee2e6; border-radius: 8px; }
        .test-result { margin-top: 1rem; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h2 class="mb-0">🧪 GASC Blood Bridge - Notification System Test</h2>
                    </div>
                    <div class="card-body">
                        
                        <!-- Email Tests -->
                        <div class="test-section">
                            <h4>📧 Email System Tests</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Test Email:</label>
                                        <input type="email" class="form-control" id="testEmail" value="<?php echo htmlspecialchars($email); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Test Phone:</label>
                                        <input type="tel" class="form-control" id="testPhone" value="<?php echo htmlspecialchars($phone); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="btn-group mb-3" role="group">
                                <button type="button" class="btn btn-primary" onclick="testEmail('otp')">Test OTP Email</button>
                                <button type="button" class="btn btn-primary" onclick="testEmail('blood_request')">Test Blood Request Email</button>
                                <button type="button" class="btn btn-primary" onclick="testEmail('password_reset')">Test Password Reset</button>
                            </div>
                            
                            <?php if ($test === 'email'): ?>
                            <div class="test-result alert alert-info">
                                <strong>Testing Email System...</strong><br>
                                <?php
                                try {
                                    $testType = $_GET['type'] ?? 'otp';
                                    $result = false;
                                    
                                    switch ($testType) {
                                        case 'otp':
                                            $result = sendOTPEmail($email, '123456', 'test');
                                            echo "OTP Email Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                        case 'blood_request':
                                            $requestDetails = [
                                                'blood_group' => 'O+',
                                                'city' => 'Delhi',
                                                'urgency' => 'Critical',
                                                'units_needed' => 2,
                                                'requester_phone' => '9876543210',
                                                'details' => 'Test blood request notification'
                                            ];
                                            $result = sendBloodRequestNotification($email, 'Test Donor', $requestDetails);
                                            echo "Blood Request Email Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                        case 'password_reset':
                                            $result = sendPasswordResetEmail($email, 'test-token-123', 'Test User');
                                            echo "Password Reset Email Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                    }
                                    
                                    if ($result) {
                                        echo "<br><small>✅ Email sent successfully! Check logs/emails.log for details.</small>";
                                    } else {
                                        echo "<br><small>❌ Email failed. Check error logs.</small>";
                                    }
                                } catch (Exception $e) {
                                    echo "❌ Error: " . htmlspecialchars($e->getMessage());
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- SMS Tests -->
                        <div class="test-section">
                            <h4>📱 SMS System Tests</h4>
                            <div class="btn-group mb-3" role="group">
                                <button type="button" class="btn btn-success" onclick="testSMS('otp')">Test OTP SMS</button>
                                <button type="button" class="btn btn-success" onclick="testSMS('blood_request')">Test Blood Request SMS</button>
                                <button type="button" class="btn btn-success" onclick="testSMS('reminder')">Test Donation Reminder</button>
                            </div>
                            
                            <?php if ($test === 'sms'): ?>
                            <div class="test-result alert alert-info">
                                <strong>Testing SMS System...</strong><br>
                                <?php
                                try {
                                    $testType = $_GET['type'] ?? 'otp';
                                    $result = false;
                                    
                                    switch ($testType) {
                                        case 'otp':
                                            $result = sendOTPSMS($phone, '123456', 'test');
                                            echo "OTP SMS Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                        case 'blood_request':
                                            $requestDetails = [
                                                'blood_group' => 'O+',
                                                'city' => 'Delhi',
                                                'urgency' => 'Critical',
                                                'requester_phone' => '9876543210'
                                            ];
                                            $result = sendBloodRequestSMS($phone, 'Test Donor', $requestDetails);
                                            echo "Blood Request SMS Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                        case 'reminder':
                                            $result = sendDonationReminderSMS($phone, 'Test Donor');
                                            echo "Donation Reminder SMS Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                    }
                                    
                                    if ($result) {
                                        echo "<br><small>✅ SMS sent successfully! Check logs/sms.log for details.</small>";
                                    } else {
                                        echo "<br><small>❌ SMS failed. Check error logs or SMS service configuration.</small>";
                                    }
                                } catch (Exception $e) {
                                    echo "❌ Error: " . htmlspecialchars($e->getMessage());
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- OTP System Tests -->
                        <div class="test-section">
                            <h4>🔐 OTP System Tests</h4>
                            <div class="btn-group mb-3" role="group">
                                <button type="button" class="btn btn-warning" onclick="testOTP('generate')">Generate OTP</button>
                                <button type="button" class="btn btn-warning" onclick="testOTP('verify')">Verify OTP</button>
                                <button type="button" class="btn btn-warning" onclick="testOTP('cleanup')">Cleanup Expired</button>
                            </div>
                            
                            <?php if ($test === 'otp'): ?>
                            <div class="test-result alert alert-info">
                                <strong>Testing OTP System...</strong><br>
                                <?php
                                try {
                                    $testType = $_GET['type'] ?? 'generate';
                                    
                                    switch ($testType) {
                                        case 'generate':
                                            $otp = generateOTP(6);
                                            $result = storeOTP($email, $phone, $otp, 'test');
                                            echo "Generate OTP Test: " . ($result ? "✅ Success" : "❌ Failed");
                                            if ($result) {
                                                echo "<br>Generated OTP: <strong>$otp</strong>";
                                            }
                                            break;
                                        case 'verify':
                                            $testOTP = $_GET['otp'] ?? '123456';
                                            $result = verifyOTP($email, $testOTP, 'test');
                                            echo "Verify OTP Test ($testOTP): " . ($result ? "✅ Success" : "❌ Failed");
                                            break;
                                        case 'cleanup':
                                            $cleanedCount = cleanExpiredOTPs();
                                            echo "Cleanup Expired OTPs: ✅ Cleaned $cleanedCount expired OTPs";
                                            break;
                                    }
                                } catch (Exception $e) {
                                    echo "❌ Error: " . htmlspecialchars($e->getMessage());
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Notification System Tests -->
                        <div class="test-section">
                            <h4>🔔 Notification System Tests</h4>
                            <div class="btn-group mb-3" role="group">
                                <button type="button" class="btn btn-info" onclick="testNotification('blood_request')">Test Blood Request Notifications</button>
                                <button type="button" class="btn btn-info" onclick="testNotification('auto_expire')">Test Auto Expire</button>
                                <button type="button" class="btn btn-info" onclick="testNotification('reminders')">Test Donation Reminders</button>
                            </div>
                            
                            <?php if ($test === 'notification'): ?>
                            <div class="test-result alert alert-info">
                                <strong>Testing Notification System...</strong><br>
                                <?php
                                try {
                                    $testType = $_GET['type'] ?? 'blood_request';
                                    
                                    switch ($testType) {
                                        case 'blood_request':
                                            // Find the latest blood request
                                            $db = new Database();
                                            $sql = "SELECT id FROM blood_requests ORDER BY created_at DESC LIMIT 1";
                                            $result = $db->query($sql);
                                            if ($result->num_rows > 0) {
                                                $requestId = $result->fetch_assoc()['id'];
                                                $notificationResult = notifyDonorsForBloodRequest($requestId);
                                                if ($notificationResult && is_array($notificationResult)) {
                                                    echo "Blood Request Notifications: ✅ Success<br>";
                                                    echo "Donors notified: {$notificationResult['donors_notified']}<br>";
                                                    echo "Emails sent: {$notificationResult['emails_sent']}<br>";
                                                    echo "SMS sent: {$notificationResult['sms_sent']}";
                                                } else {
                                                    echo "Blood Request Notifications: ❌ Failed";
                                                }
                                            } else {
                                                echo "❌ No blood requests found to test with";
                                            }
                                            break;
                                        case 'auto_expire':
                                            $expiredCount = autoExpireBloodRequests();
                                            echo "Auto Expire Test: ✅ Expired $expiredCount requests";
                                            break;
                                        case 'reminders':
                                            $reminderCount = sendDonationEligibilityReminders();
                                            echo "Donation Reminders Test: ✅ Sent $reminderCount reminders";
                                            break;
                                    }
                                } catch (Exception $e) {
                                    echo "❌ Error: " . htmlspecialchars($e->getMessage());
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- System Information -->
                        <div class="test-section">
                            <h4>ℹ️ System Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                                        <li><strong>Email Service:</strong> <?php echo class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'PHPMailer Available' : 'Development Mode (Logging)'; ?></li>
                                        <li><strong>SMS Service:</strong> <?php echo defined('SMS_SERVICE') ? SMS_SERVICE : 'textbelt'; ?></li>
                                        <li><strong>Log Directory:</strong> <?php echo is_dir('logs') ? '✅ Available' : '❌ Missing'; ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><strong>cURL:</strong> <?php echo extension_loaded('curl') ? '✅ Available' : '❌ Missing'; ?></li>
                                        <li><strong>MySQLi:</strong> <?php echo extension_loaded('mysqli') ? '✅ Available' : '❌ Missing'; ?></li>
                                        <li><strong>OpenSSL:</strong> <?php echo extension_loaded('openssl') ? '✅ Available' : '❌ Missing'; ?></li>
                                        <li><strong>JSON:</strong> <?php echo extension_loaded('json') ? '✅ Available' : '❌ Missing'; ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>⚠️ Important:</strong> This is a test interface. Remove or secure this file in production!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function testEmail(type) {
            const email = document.getElementById('testEmail').value;
            window.location.href = `?test=email&type=${type}&email=${encodeURIComponent(email)}`;
        }
        
        function testSMS(type) {
            const phone = document.getElementById('testPhone').value;
            window.location.href = `?test=sms&type=${type}&phone=${encodeURIComponent(phone)}`;
        }
        
        function testOTP(type) {
            const email = document.getElementById('testEmail').value;
            const phone = document.getElementById('testPhone').value;
            let url = `?test=otp&type=${type}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}`;
            
            if (type === 'verify') {
                const otp = prompt('Enter OTP to verify:');
                if (otp) {
                    url += `&otp=${otp}`;
                } else {
                    return;
                }
            }
            
            window.location.href = url;
        }
        
        function testNotification(type) {
            window.location.href = `?test=notification&type=${type}`;
        }
    </script>
</body>
</html>
