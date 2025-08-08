<?php
require_once '../config/database.php';
require_once '../config/email.php';

$step = $_GET['step'] ?? '1';
$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Ensure step is always valid
if (!in_array($step, ['1', '2', '3'])) {
    $step = '1';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        if ($step === '1') {
            // Step 1: Email submission for password reset
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if user exists
            $db = new Database();
            $sql = "SELECT id, name, email, user_type FROM users WHERE email = ? AND user_type IN ('donor', 'admin', 'moderator')";
            $result = $db->query($sql, [$email]);
            
            if ($result->num_rows === 0) {
                // User doesn't exist - show error message for better UX
                throw new Exception('No account found with this email address. Please check your email and try again.');
            } else {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $resetToken = generateSecureToken(32); // 32 bytes = 64 hex chars (fits varchar(64) column)
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                
                // Store reset token
                $updateSQL = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
                $db->query($updateSQL, [$resetToken, $expiresAt, $user['id']]);
                
                // Send reset email
                $emailSent = sendPasswordResetEmail($email, $resetToken, $user['name']);
                
                if ($emailSent) {
                    logActivity($user['id'], 'password_reset_requested', "Password reset requested for: $email");
                    $success = "A password reset link has been sent to your email. Please check your inbox and spam folder.";
                } else {
                    throw new Exception('Failed to send reset email. Please try again or contact support if the problem persists.');
                }
            }
            
        } elseif ($step === '2') {
            // Step 2: New password submission
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            
            if (empty($token)) {
                throw new Exception('Invalid reset token.');
            }
            
            // Verify reset token
            $db = new Database();
            $sql = "SELECT id, name, email, user_type FROM users 
                    WHERE reset_token = ? AND reset_token_expires > NOW()";
            $result = $db->query($sql, [$token]);
            
            if ($result->num_rows === 0) {
                throw new Exception('Invalid or expired reset token. Please request a new password reset.');
            }
            
            $user = $result->fetch_assoc();
            
            // Update password and clear reset token
            $hashedPassword = hashPassword($newPassword);
            $updateSQL = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $db->query($updateSQL, [$hashedPassword, $user['id']]);
            
            logActivity($user['id'], 'password_reset_completed', "Password reset completed for: {$user['email']}");
            
            $success = "Password reset successful! You can now log in with your new password.";
            $step = '3'; // Show success page
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'password_reset_failed', $error . " - Email: " . ($_POST['email'] ?? 'unknown'));
    }
}

// Validate token for step 2
if ($step === '2') {
    if (empty($token)) {
        // No token provided, redirect to step 1
        $error = "No reset token provided. Please start the password reset process.";
        $step = '1';
    } else {
        // Token provided, validate it
        try {
            $db = new Database();
            $sql = "SELECT id, name, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()";
            $result = $db->query($sql, [$token]);
            
            if ($result->num_rows === 0) {
                // Check if token exists but is expired
                $expiredCheck = $db->query("SELECT id, email, reset_token_expires, NOW() as current_time FROM users WHERE reset_token = ?", [$token]);
                if ($expiredCheck->num_rows > 0) {
                    $expiredData = $expiredCheck->fetch_assoc();
                    $error = "Your reset token has expired on " . $expiredData['reset_token_expires'] . ". Current time is " . $expiredData['current_time'] . ". Please request a new password reset.";
                } else {
                    // Token not found at all
                    $error = "Invalid reset token. Please request a new password reset.";
                }
                $step = '1';
                $token = '';
            }
        } catch (Exception $e) {
            $error = "Error validating reset token. Please try again.";
            $step = '1';
            $token = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .reset-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            margin: 0 auto;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .step.active {
            background: #dc2626;
            color: white;
        }
        
        .step.completed {
            background: #10b981;
            color: white;
        }
        
        .step.inactive {
            background: #e5e7eb;
            color: #6b7280;
        }
        
        .step-line {
            width: 50px;
            height: 2px;
            background: #e5e7eb;
            margin: 14px 0;
        }
        
        .step-line.completed {
            background: #10b981;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc2626; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="container">
            <div class="reset-card">
                <div class="reset-header">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">
                        <i class="fas fa-key text-danger me-2"></i>
                        <?php if ($step === '3'): ?>
                            Success!
                        <?php else: ?>
                            Reset Password
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($step !== '3'): ?>
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step === '1' ? 'active' : ($step === '2' ? 'completed' : 'inactive'); ?>">1</div>
                        <div class="step-line <?php echo $step === '2' ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo $step === '2' ? 'active' : 'inactive'; ?>">2</div>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-0">
                        <?php if ($step === '1'): ?>
                            Enter your email to receive reset link
                        <?php elseif ($step === '2'): ?>
                            Create your new password
                        <?php else: ?>
                            Your password has been reset successfully
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="p-4">
                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success) && !empty($success)): ?>
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === '1'): ?>
                        <!-- Step 1: Email Input -->
                        <form method="POST" action="?step=1" id="emailForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope text-danger me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       placeholder="your.email@example.com">
                                <div class="form-text">
                                    Enter the email address associated with your account
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                            </div>
                        </form>
                        
                    <?php elseif ($step === '2'): ?>
                        <!-- Step 2: New Password Input -->
                        <form method="POST" action="?step=2&token=<?php echo htmlspecialchars($token); ?>" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock text-danger me-1"></i>New Password
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       required minlength="8" placeholder="Enter new password">
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="form-text">
                                    Must be at least 8 characters long
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock text-danger me-1"></i>Confirm Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       required placeholder="Confirm new password">
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-check me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <!-- Step 3: Success -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="../admin/login.php" class="btn btn-danger">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step !== '3'): ?>
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted mb-2">
                            Remember your password? 
                            <a href="../admin/login.php" class="text-danger text-decoration-none fw-semibold">Login here</a>
                        </p>
                        <!-- Removed Login with OTP link for clarity -->
                        <a href="../index.php" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($step === '2'): ?>
            // Password strength indicator
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                strengthIndicator.className = 'password-strength';
                if (strength <= 2) {
                    strengthIndicator.classList.add('strength-weak');
                } else if (strength <= 3) {
                    strengthIndicator.classList.add('strength-medium');
                } else {
                    strengthIndicator.classList.add('strength-strong');
                }
            });
            
            // Real-time password confirmation
            confirmInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Form validation
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            });
            
            passwordInput.focus();
            <?php else: ?>
            // Auto-focus email input
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
