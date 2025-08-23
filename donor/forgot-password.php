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
        
        // Rate limiting for donor password reset
        if (!checkRateLimit('donor_password_reset', 3, 600)) {
            throw new Exception('Too many password reset attempts. Please try again in 10 minutes.');
        }
        
        if ($step === '1') {
            // Step 1: Email submission for password reset
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if donor exists - ONLY allow donor accounts
            $db = new Database();
            $sql = "SELECT id, name, email, user_type FROM users WHERE email = ? AND user_type = 'donor' AND is_active = 1";
            $result = $db->query($sql, [$email]);
            
            // Always show success message to prevent user enumeration
            $success = "If a donor account exists with this email address, a password reset link has been sent. Please check your inbox and spam folder.";
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $resetToken = generateSecureToken(32);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour for donors
                
                // Store reset token
                $updateSQL = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
                $db->query($updateSQL, [$resetToken, $expiresAt, $user['id']]);
                
                // Send reset email using the existing email function
                $emailSent = sendPasswordResetEmail($user['email'], $resetToken, $user['name'], 'donor');
                
                if (!$emailSent) {
                    throw new Exception('Failed to send reset email. Please try again later.');
                }
                
                // Log security event
                logActivity($user['id'], 'password_reset_requested', 'Donor password reset email sent to: ' . $user['email']);
            }
            
        } elseif ($step === '3' && !empty($token)) {
            // Step 3: Process new password
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($newPassword) || empty($confirmPassword)) {
                throw new Exception('Please fill in all password fields.');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            
            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            // Validate password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
                throw new Exception('Password must contain at least one uppercase letter, one lowercase letter, and one number.');
            }
            
            // Verify token again
            $db = new Database();
            $sql = "SELECT id, name, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND user_type = 'donor'";
            $result = $db->query($sql, [$token]);
            
            if ($result->num_rows === 0) {
                throw new Exception('Invalid or expired reset token. Please request a new password reset.');
            }
            
            $user = $result->fetch_assoc();
            
            // Hash new password
            $passwordHash = hashPassword($newPassword);
            
            // Update password and clear reset token
            $updateSQL = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?";
            $db->query($updateSQL, [$passwordHash, $user['id']]);
            
            // Log security event
            logActivity($user['id'], 'password_reset_completed', 'Donor password successfully reset');
            
            $success = "Your password has been successfully reset! You can now log in with your new password.";
            
            // Clear step to show success message
            $step = 'success';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// For step 2, validate token and redirect to step 3
if ($step === '2' && !empty($token)) {
    try {
        $db = new Database();
        $sql = "SELECT id, name, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND user_type = 'donor'";
        $result = $db->query($sql, [$token]);
        
        if ($result->num_rows === 0) {
            $error = 'Invalid or expired reset token. Please request a new password reset.';
            $step = '1';
            $token = '';
        } else {
            // Token is valid, redirect to step 3
            header("Location: ?step=3&token=" . urlencode($token));
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error validating reset token. Please try again.';
        $step = '1';
        $token = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .reset-body {
            padding: 40px;
        }
        
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            transform: translateY(-1px);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: #dc2626;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: white;
        }
        
        .step.active {
            background: #dc2626;
        }
        
        .step.completed {
            background: #10b981;
        }
        
        .step.inactive {
            background: #9ca3af;
        }
        
        .step-line {
            height: 2px;
            width: 50px;
            background: #e5e7eb;
            margin-top: 19px;
        }
        
        .step-line.completed {
            background: #10b981;
        }
        
        .security-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .security-info .fa-shield-alt {
            color: #0284c7;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h1 class="mb-2">
                    <i class="fas fa-key me-2"></i>Reset Password
                </h1>
                <p class="mb-0 opacity-75">GASC Blood Bridge - Donor Portal</p>
            </div>
            
            <div class="reset-body">
                <?php if ($step !== 'success'): ?>
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo ($step === '1') ? 'active' : (in_array($step, ['2', '3']) ? 'completed' : 'inactive'); ?>">1</div>
                        <div class="step-line <?php echo (in_array($step, ['2', '3'])) ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo ($step === '2') ? 'active' : ($step === '3' ? 'completed' : 'inactive'); ?>">2</div>
                        <div class="step-line <?php echo ($step === '3') ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo ($step === '3') ? 'active' : 'inactive'; ?>">3</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === '1'): ?>
                    <!-- Step 1: Email Input -->
                    <h4 class="mb-3">Enter Your Email Address</h4>
                    <p class="text-muted mb-4">
                        Enter the email address associated with your donor account and we'll send you a link to reset your password.
                    </p>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope text-danger me-1"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   required placeholder="Enter your registered email address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </div>
                    </form>
                    
                    <div class="security-info">
                        <h6 class="mb-2">
                            <i class="fas fa-shield-alt me-2"></i>Security Information
                        </h6>
                        <ul class="mb-0 small">
                            <li>Reset links expire after 1 hour for security</li>
                            <li>Only active donor accounts can request password resets</li>
                            <li>You'll receive an email if your account exists</li>
                        </ul>
                    </div>
                    
                <?php elseif ($step === '2'): ?>
                    <!-- Step 2: Token Validation (auto-redirects to step 3) -->
                    <div class="text-center">
                        <div class="spinner-border text-danger mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h4>Validating Reset Link...</h4>
                        <p class="text-muted">Please wait while we verify your reset token.</p>
                        <p class="text-muted small">If this page doesn't redirect automatically, <a href="?step=3&token=<?php echo urlencode($token); ?>" class="text-danger">click here</a>.</p>
                    </div>
                    
                    <script>
                        // Fallback redirect in case server-side redirect fails
                        setTimeout(function() {
                            window.location.href = '?step=3&token=<?php echo urlencode($token); ?>';
                        }, 2000); // 2 second delay
                    </script>
                    
                <?php elseif ($step === '3'): ?>
                    <!-- Step 3: New Password Form -->
                    <h4 class="mb-3">Create New Password</h4>
                    <p class="text-muted mb-4">
                        Choose a strong password for your donor account.
                    </p>
                    
                    <form method="POST" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                <i class="fas fa-lock text-danger me-1"></i>New Password
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       required placeholder="Enter your new password" minlength="8">
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                Must be at least 8 characters with uppercase, lowercase, and numbers
                            </small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock text-danger me-1"></i>Confirm New Password
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       required placeholder="Confirm your new password" minlength="8">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-check me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step === 'success'): ?>
                    <!-- Success Message -->
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-success mb-3">Password Reset Successful!</h4>
                        <p class="text-muted mb-4">
                            Your password has been updated successfully. You can now log in to your donor account with your new password.
                        </p>
                        <div class="d-grid">
                            <a href="login.php" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4 pt-3 border-top">
                    <a href="login.php" class="text-danger text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                    <span class="mx-2 text-muted">|</span>
                    <a href="../index.php" class="text-muted text-decoration-none">
                        <i class="fas fa-home me-1"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Password strength validation
        if (document.getElementById('resetForm')) {
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please try again.');
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }
                
                if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
                    e.preventDefault();
                    alert('Password must contain at least one uppercase letter, one lowercase letter, and one number.');
                    return false;
                }
            });
            
            // Real-time password matching feedback
            document.getElementById('confirm_password').addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = this.value;
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
        
        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="email"], input[type="password"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>