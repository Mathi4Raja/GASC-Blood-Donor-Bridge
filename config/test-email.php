<?php
/**
 * Test Email Functionality
 * This page helps test if email sending is working properly
 */

require_once '../config/database.php';
require_once '../config/email.php';

$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Test sending a simple email
        $subject = "GASC Blood Bridge - Email Test";
        $body = "
        <h2>Email Test Successful!</h2>
        <p>This is a test email from GASC Blood Bridge system.</p>
        <p>If you're seeing this, email functionality is working correctly.</p>
        <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
        ";
        
        if (sendEmailSMTP($email, $subject, $body, true)) {
            $result = "✅ Email sent successfully to: $email";
        } else {
            $error = "❌ Failed to send email. Check logs for details.";
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-envelope-open-text me-2"></i>Email Test</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($result)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $result; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Test Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       placeholder="Enter email to test" required>
                                <div class="form-text">Enter your email address to test if emails are being sent</div>
                            </div>
                            
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-paper-plane me-2"></i>Send Test Email
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="mt-4">
                            <h6>Email Configuration Status:</h6>
                            <ul class="list-unstyled">
                                <?php
                                global $phpmailer_available;
                                if ($phpmailer_available && class_exists('PHPMailer\PHPMailer\PHPMailer')):
                                ?>
                                    <li class="text-success"><i class="fas fa-check me-2"></i>PHPMailer: Available</li>
                                <?php else: ?>
                                    <li class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>PHPMailer: Using fallback logging</li>
                                <?php endif; ?>
                                
                                <li class="text-info"><i class="fas fa-server me-2"></i>SMTP Host: <?php echo SMTP_HOST; ?></li>
                                <li class="text-info"><i class="fas fa-envelope me-2"></i>From Email: <?php echo SMTP_FROM_EMAIL; ?></li>
                            </ul>
                        </div>
                        
                        <div class="mt-3">
                            <a href="../admin/login.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                            <a href="forgot-password.php" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-key me-2"></i>Test Password Reset
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (file_exists('../logs/emails.log')): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Email Log (Last 20 lines)</h6>
                    </div>
                    <div class="card-body">
                        <pre class="small text-muted" style="max-height: 300px; overflow-y: auto;"><?php
                            $log_lines = file('../logs/emails.log');
                            if ($log_lines) {
                                echo htmlspecialchars(implode('', array_slice($log_lines, -20)));
                            } else {
                                echo "No email logs found.";
                            }
                        ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
