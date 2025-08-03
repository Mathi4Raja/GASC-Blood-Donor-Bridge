<?php
/**
 * Standalone Email Test Script
 * Test email functionality independent of the main system
 */

// Test if PHP mail function is available (basic test)
echo "<h2>Email System Diagnostics</h2>\n";

// 1. Check if mail() function exists
if (function_exists('mail')) {
    echo "✅ PHP mail() function is available<br>\n";
} else {
    echo "❌ PHP mail() function is NOT available<br>\n";
}

// 2. Check PHPMailer files
$phpmailer_files = [
    'PHPMailer.php',
    'SMTP.php', 
    'Exception.php'
];

$phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
echo "<h3>PHPMailer Files Check:</h3>\n";

foreach ($phpmailer_files as $file) {
    if (file_exists($phpmailer_path . $file)) {
        echo "✅ $file exists<br>\n";
    } else {
        echo "❌ $file missing<br>\n";
    }
}

// 3. Test loading PHPMailer
echo "<h3>PHPMailer Loading Test:</h3>\n";
try {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        echo "✅ Autoloader loaded<br>\n";
    }
    
    if (file_exists($phpmailer_path . 'PHPMailer.php')) {
        require_once $phpmailer_path . 'PHPMailer.php';
        require_once $phpmailer_path . 'SMTP.php';
        require_once $phpmailer_path . 'Exception.php';
        echo "✅ PHPMailer files loaded<br>\n";
        
        // Test creating PHPMailer instance
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        echo "✅ PHPMailer instance created successfully<br>\n";
        
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'solunattic@gmail.com';
        $mail->Password = 'npio ogcb fdoc jphc';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        echo "✅ SMTP configuration set<br>\n";
        
        // Test connection (without sending)
        try {
            $mail->SMTPDebug = 0; // Disable debug output
            echo "✅ PHPMailer configured successfully<br>\n";
        } catch (Exception $e) {
            echo "❌ SMTP configuration error: " . $e->getMessage() . "<br>\n";
        }
        
    } else {
        echo "❌ PHPMailer files not found<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error loading PHPMailer: " . $e->getMessage() . "<br>\n";
}

// 4. Test sending to a test email (if form submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_email'])) {
    echo "<h3>Email Sending Test:</h3>\n";
    
    $testEmail = $_POST['test_email'];
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'solunattic@gmail.com';
        $mail->Password = 'npio ogcb fdoc jphc';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Email Content
        $mail->setFrom('solunattic@gmail.com', 'GASC Blood Bridge Test');
        $mail->addAddress($testEmail);
        $mail->Subject = 'GASC Blood Bridge - Test Email';
        $mail->isHTML(true);
        $mail->Body = '
        <h2>Test Email Successful!</h2>
        <p>This is a test email from GASC Blood Bridge system.</p>
        <p>If you received this email, the system is working correctly!</p>
        <p>Sent at: ' . date('Y-m-d H:i:s') . '</p>
        ';
        
        if ($mail->send()) {
            echo "✅ Test email sent successfully to: $testEmail<br>\n";
        } else {
            echo "❌ Failed to send test email<br>\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Email sending failed: " . $e->getMessage() . "<br>\n";
    }
}

echo "<br><hr><br>\n";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        form { margin-top: 20px; padding: 20px; background: #f5f5f5; border-radius: 5px; }
    </style>
</head>
<body>
    <form method="POST">
        <h3>Send Test Email</h3>
        <label>Enter your email to test:</label><br>
        <input type="email" name="test_email" required style="width: 300px; padding: 8px; margin: 10px 0;">
        <br>
        <button type="submit" style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 5px;">
            Send Test Email
        </button>
    </form>
    
    <p><a href="forgot-password.php">Test Forgot Password</a> | <a href="../admin/login.php">Admin Login</a></p>
</body>
</html>
