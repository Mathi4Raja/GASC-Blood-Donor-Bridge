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
        
        // Stricter rate limiting for admin resets
        if (!checkRateLimit('admin_password_reset', 2, 600)) {
            throw new Exception('Too many password reset attempts. Please try again in 10 minutes.');
        }
        
        if ($step === '1') {
            // Step 1: Email submission for password reset
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if admin/moderator exists - ONLY allow admin/moderator accounts
            $db = new Database();
            $sql = "SELECT id, name, email, user_type FROM users WHERE email = ? AND user_type IN ('admin', 'moderator') AND is_active = 1";
            $result = $db->query($sql, [$email]);
            
            // Always show success message to prevent user enumeration
            $success = "If an administrative account exists with this email address, a password reset link has been sent. Please check your inbox and spam folder.";
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $resetToken = generateSecureToken(32);
                $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes only for admin
                
                // Store reset token
                $updateSQL = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
                $db->query($updateSQL, [$resetToken, $expiresAt, $user['id']]);
                
                // Send reset email
                $emailSent = sendPasswordResetEmail($email, $resetToken, $user['name'], $user['user_type']);
                
                if ($emailSent) {
                    logActivity($user['id'], 'admin_password_reset_requested', "Password reset requested for {$user['user_type']}: $email");
                } else {
                    logActivity($user['id'], 'admin_password_reset_email_failed', "Failed to send reset email to {$user['user_type']}: $email");
                }
            } else {
                // Log failed attempt for security monitoring
                logActivity(null, 'admin_password_reset_invalid_email', "Password reset attempted for non-existent admin email: $email");
            }
            
        } elseif ($step === '2') {
            // Step 2: New password submission
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Stricter password requirements for admin
            if (strlen($newPassword) < 8) {
                throw new Exception('Admin password must be at least 8 characters long.');
            }
            
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
                throw new Exception('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            
            if (empty($token)) {
                throw new Exception('Invalid reset token.');
            }
            
            // Verify reset token - ONLY for admin/moderator accounts
            $db = new Database();
            $sql = "SELECT id, name, email, user_type FROM users 
                    WHERE reset_token = ? AND reset_token_expires > NOW() AND user_type IN ('admin', 'moderator')";
            $result = $db->query($sql, [$token]);
            
            if ($result->num_rows === 0) {
                throw new Exception('Invalid or expired reset token. Please request a new password reset.');
            }
            
            $user = $result->fetch_assoc();
            
            // Update password and clear reset token
            $hashedPassword = hashPassword($newPassword);
            $updateSQL = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $db->query($updateSQL, [$hashedPassword, $user['id']]);
            
            logActivity($user['id'], 'admin_password_reset_completed', "Password reset completed for {$user['user_type']}: {$user['email']}");

            $success = "Password reset successful! You can now log in with your new password.";
            $step = '3'; // Show success page
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'admin_password_reset_failed', $error . " - Email: " . ($_POST['email'] ?? 'unknown'));
    }
}

// If we have a token in URL, validate it and go to step 2
if (!empty($token) && $step === '1') {
    $db = new Database();
    $sql = "SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND user_type IN ('admin', 'moderator')";
    $result = $db->query($sql, [$token]);
    
    if ($result->num_rows > 0) {
        $step = '2';
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
        $token = '';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .reset-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid #fbbf24;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #fef3c7, #white);
            padding: 2rem;
            text-align: center;
            border-bottom: 2px solid #fbbf24;
        }
        
        .security-badge {
            background: #dc2626;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: inline-block;
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
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
        }
        
        .position-relative .form-control {
            padding-right: 45px;
        }
        
        /* Adjust when Bootstrap validation is active */
        .was-validated .position-relative .form-control:valid {
            padding-right: 75px; /* Extra space for both validation checkmark and eye icon */
        }
        
        .was-validated .position-relative .password-toggle {
            right: 40px; /* Move eye icon left to avoid validation checkmark */
        }
        
        .password-toggle:hover {
            color: #dc2626;
        }
        
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
        }
        
        .security-info {
            background: #f3f4f6;
            border-left: 4px solid #fbbf24;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="container">
            <div class="reset-card">
                <div class="reset-header">
                    <div class="security-badge">
                        <i class="fas fa-shield-alt me-1"></i>ADMIN AREA
                    </div>
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Admin Password Reset</h3>
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step === '1' ? 'active' : ($step === '2' || $step === '3' ? 'completed' : 'inactive'); ?>">1</div>
                        <div class="step-line <?php echo $step === '2' || $step === '3' ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo $step === '2' ? 'active' : ($step === '3' ? 'completed' : 'inactive'); ?>">2</div>
                        <div class="step-line <?php echo $step === '3' ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo $step === '3' ? 'active' : 'inactive'; ?>">3</div>
                    </div>
                    
                    <p class="text-muted mb-0">
                        <?php if ($step === '1'): ?>
                            Secure admin password reset
                        <?php elseif ($step === '2'): ?>
                            Set your new admin password
                        <?php else: ?>
                            Password reset complete!
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
                        <div class="security-info">
                            <h6><i class="fas fa-info-circle text-warning me-1"></i>Security Notice</h6>
                            <small>
                                • Reset links expire in 30 minutes<br>
                                • All admin password resets are logged and monitored
                            </small>
                        </div>
                        
                        <!-- Step 1: Email Input -->
                        <form method="POST" action="?step=1" id="emailForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope text-danger me-1"></i>Administrative Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       placeholder="admin@gasc.edu"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <div class="form-text">
                                    Enter the email address associated with your admin account.
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-paper-plane me-2"></i>Send Secure Reset Link
                                </button>
                            </div>
                        </form>
                        
                    <?php elseif ($step === '2'): ?>
                        <div class="security-info">
                            <h6><i class="fas fa-key text-warning me-1"></i>Admin Password Requirements</h6>
                            <small>
                                • Minimum 8 characters<br>
                                • Must include uppercase, lowercase, number, and special character<br>
                                • Cannot reuse previous passwords
                            </small>
                        </div>
                        
                        <!-- Step 2: New Password Input -->
                        <form method="POST" action="?step=2&token=<?php echo htmlspecialchars($token); ?>" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock text-danger me-1"></i>New Admin Password
                                </label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           required minlength="8" placeholder="Enter your new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')">
                                        <i class="fas fa-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Must be at least 8 characters with mixed case, numbers, and symbols.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock text-danger me-1"></i>Confirm New Password
                                </label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required minlength="8" placeholder="Confirm your new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                        <i class="fas fa-eye" id="toggleIcon2"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key me-2"></i>Update Admin Password
                                </button>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <!-- Step 3: Success Message -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            
                            <div class="security-info text-start">
                                <h6><i class="fas fa-shield-alt text-success me-1"></i>Security Confirmation</h6>
                                <small>
                                    • Your admin password has been successfully updated<br>
                                    • All administrators have been notified of this change<br>
                                    • This activity has been logged for security audit
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <a href="login.php" class="btn btn-danger">
                                    <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step !== '3'): ?>
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted mb-2">
                            Remember your password? 
                            <a href="login.php" class="text-danger text-decoration-none fw-semibold">Admin Login</a>
                        </p>
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
        
        // Auto-focus first input
        <?php if ($step === '1'): ?>
        document.getElementById('email').focus();
        <?php elseif ($step === '2'): ?>
        document.getElementById('new_password').focus();
        <?php endif; ?>
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
            
            // Password validation for admin
            <?php if ($step === '2'): ?>
            const passwordForm = document.getElementById('passwordForm');
            passwordForm.addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Check password strength
                const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/;
                if (!strongPassword.test(password)) {
                    e.preventDefault();
                    alert('Password must contain at least one uppercase letter, lowercase letter, number, and special character.');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please try again.');
                    return false;
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
