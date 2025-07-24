<?php
require_once '../config/database.php';

$token = $_GET['token'] ?? '';
$status = '';
$message = '';

if (empty($token)) {
    $status = 'error';
    $message = 'Invalid verification link.';
} else {
    try {
        $db = new Database();
        
        // Find user with this verification token
        $sql = "SELECT id, name, email FROM users WHERE email_verification_token = ? AND email_verified = FALSE";
        $result = $db->query($sql, [$token]);
        
        if ($result->num_rows === 0) {
            $status = 'error';
            $message = 'Invalid or expired verification link.';
        } else {
            $user = $result->fetch_assoc();
            
            // Update user as verified
            $updateSql = "UPDATE users SET email_verified = TRUE, email_verification_token = NULL WHERE id = ?";
            $db->query($updateSql, [$user['id']]);
            
            // Log the verification
            logActivity($user['id'], 'email_verified', 'Email address verified');
            
            $status = 'success';
            $message = 'Email verification successful! You can now log in to your account.';
        }
        
    } catch (Exception $e) {
        $status = 'error';
        $message = 'Verification failed. Please try again or contact support.';
        logActivity(null, 'email_verification_failed', $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .verification-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .verification-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        
        .verification-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 3rem 2rem 2rem;
        }
        
        .verification-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }
        
        .success-icon {
            background: #10b981;
            color: white;
        }
        
        .error-icon {
            background: #dc2626;
            color: white;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="container">
            <div class="verification-card">
                <div class="verification-header">
                    <div class="verification-icon <?php echo $status === 'success' ? 'success-icon' : 'error-icon'; ?>">
                        <i class="fas fa-<?php echo $status === 'success' ? 'check' : 'times'; ?>"></i>
                    </div>
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <!-- GASC Logo removed as per privacy-focused design -->
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Email Verification</h3>
                </div>
                
                <div class="p-4">
                    <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>">
                        <i class="fas fa-<?php echo $status === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <?php if ($status === 'success'): ?>
                            <a href="login.php" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-danger">
                                <i class="fas fa-user-plus me-2"></i>Register Again
                            </a>
                        <?php endif; ?>
                        <a href="../index.php" class="btn btn-outline-danger">
                            <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
