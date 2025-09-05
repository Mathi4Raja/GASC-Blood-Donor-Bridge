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

// Token validation - check if token is provided and validate it (BEFORE form processing)
if (!empty($token)) {
    $db = new Database();
    $sql = "SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND user_type IN ('admin', 'moderator')";
    $result = $db->query($sql, [$token]);
    
    if ($result->num_rows > 0) {
        // Valid token - proceed to step 2 for password input
        $step = '2';
    } else {
        // Invalid or expired token - show error on step 1 and clear any success messages
        $error = 'Invalid or expired reset token. Please request a new password reset.';
        $success = ''; // Clear any existing success message
        $token = '';
        $step = '1';
    }
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
                    $success = "If an administrative account exists with this email address, a password reset link has been sent. Please check your inbox and spam folder.";
                } else {
                    logActivity($user['id'], 'admin_password_reset_email_failed', "Failed to send reset email to {$user['user_type']}: $email");
                    throw new Exception('Failed to send reset email. Please try again later.');
                }
            } else {
                // Log failed attempt for security monitoring
                logActivity(null, 'admin_password_reset_invalid_email', "Password reset attempted for non-existent admin email: $email");
                // Always show success message to prevent user enumeration
                $success = "If an administrative account exists with this email address, a password reset link has been sent. Please check your inbox and spam folder.";
            }
            
            // Clear the email field after successful submission
            $_POST['email'] = '';
            // Stay on step 1 to show the message
            
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
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 10px 0;
            position: relative;
        }
        
        .reset-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            position: relative;
            z-index: 2;
            max-height: 90vh;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 1rem 1.5rem 0.75rem;
            text-align: center;
            position: relative;
        }
        
        .reset-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #dc2626, #991b1b);
            border-radius: 2px;
        }
        
        .security-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 8px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }
        
        .reset-header h2 {
            font-size: 1.4rem;
            margin-bottom: 5px;
        }
        
        .reset-header h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .reset-header p {
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 10px 0 8px;
            align-items: center;
        }
        
        .step {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: #dc2626;
            color: white;
            box-shadow: 0 3px 10px rgba(220, 38, 38, 0.4);
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
            width: 30px;
            height: 2px;
            background: #e5e7eb;
            margin: 0 6px;
            transition: all 0.3s ease;
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
            padding: 4px;
            transition: color 0.2s ease;
        }
        
        .position-relative .form-control {
            padding-right: 40px;
        }
        
        .password-toggle:hover {
            color: #dc2626;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.6rem 0.75rem;
            font-size: 0.9rem;
            height: auto;
            transition: all 0.3s ease;
            background-image: none !important;
        }
        
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.15rem rgba(220, 38, 38, 0.25);
        }
        
        /* Password validation feedback */
        .form-control.valid {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 0.15rem rgba(16, 185, 129, 0.25) !important;
        }
        
        .form-control.invalid {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 0.15rem rgba(239, 68, 68, 0.25) !important;
        }
        
        .form-control.valid:focus {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.3) !important;
        }
        
        .form-control.invalid:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.3) !important;
        }
        
        .form-label {
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            color: #374151;
        }
        
        .form-text {
            font-size: 0.75rem;
            margin-top: 0.3rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, #991b1b, #dc2626);
        }
        
        .security-info {
            background: #fef2f2;
            border-left: 3px solid #dc2626;
            padding: 8px 12px;
            margin: 10px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .security-info h6 {
            margin-bottom: 4px;
            color: #dc2626;
            font-size: 0.8rem;
        }
        
        .security-info small {
            color: #374151;
            line-height: 1.3;
            font-size: 0.7rem;
        }
        
        .alert {
            padding: 0.6rem 0.8rem;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
        }
        
        .mb-3 {
            margin-bottom: 0.8rem !important;
        }
        
        .mb-2 {
            margin-bottom: 0.6rem !important;
        }
        
        @media (max-width: 576px) {
            .reset-container {
                padding: 5px;
            }
            
            .reset-card {
                margin: 0;
                max-height: 95vh;
                border-radius: 10px;
            }
            
            .reset-header {
                padding: 0.8rem 1rem 0.6rem;
            }
            
            .reset-header h2 {
                font-size: 1.2rem;
            }
            
            .reset-header h3 {
                font-size: 1rem;
            }
            
            .step-indicator {
                margin: 8px 0 6px;
            }
            
            .step {
                width: 20px;
                height: 20px;
                font-size: 10px;
            }
            
            .step-line {
                width: 25px;
                margin: 0 4px;
            }
            
            .form-control {
                padding: 0.5rem 0.6rem;
                font-size: 0.85rem;
            }
            
            .position-relative .form-control {
                padding-right: 35px;
            }
            
            .btn-danger {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
            
            .security-info {
                padding: 6px 10px;
                margin: 8px 0;
            }
            
            .security-info h6 {
                font-size: 0.75rem;
            }
            
            .security-info small {
                font-size: 0.65rem;
            }
        }
        
        @media (max-height: 600px) {
            .reset-header {
                padding: 0.6rem 1rem 0.4rem;
            }
            
            .reset-header h2 {
                font-size: 1.1rem;
            }
            
            .reset-header h3 {
                font-size: 0.95rem;
            }
            
            .security-info {
                padding: 6px 10px;
                margin: 6px 0;
            }
            
            .form-control {
                padding: 0.45rem 0.6rem;
            }
            
            .btn-danger {
                padding: 0.45rem 1rem;
            }
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
                
                <div class="p-2">
                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success) && !empty($success) && ($step === '1' || $step === '3')): ?>
                        <div class="alert alert-success d-flex align-items-center mb-2">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === '1'): ?>
                        <div class="security-info">
                            <h6><i class="fas fa-info-circle text-warning me-1"></i>Security Notice</h6>
                            <small>
                                • Reset links expire in 30 minutes<br>
                                • All admin password resets are logged
                            </small>
                        </div>
                        
                        <!-- Step 1: Email Input -->
                        <form method="POST" action="?step=1" id="emailForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-2">
                                <label for="email" class="form-label fw-semibold">
                                    Administrative Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       placeholder="admin@gasc.edu"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <div class="form-text small">
                                    Enter your admin email address.
                                </div>
                            </div>
                            
                            <div class="d-grid mb-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                            </div>
                        </form>
                        
                    <?php elseif ($step === '2'): ?>
                        <div class="security-info">
                            <h6><i class="fas fa-key text-warning me-1"></i>Password Requirements</h6>
                            <small>
                                • Minimum 8 characters<br>
                                • Mixed case, numbers, and symbols
                            </small>
                        </div>
                        
                        <!-- Step 2: New Password Input -->
                        <form method="POST" action="?step=2&token=<?php echo htmlspecialchars($token); ?>" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-2">
                                <label for="new_password" class="form-label fw-semibold">
                                    New Admin Password
                                </label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           required minlength="8" placeholder="Enter new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')">
                                        <i class="fas fa-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Confirm Password
                                </label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required minlength="8" placeholder="Confirm password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                        <i class="fas fa-eye" id="toggleIcon2"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key me-2"></i>Update Password
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
                    <div class="text-center mt-3 pt-2 border-top">
                        <p class="text-muted mb-1 small">
                            Remember your password? 
                            <a href="login.php" class="text-danger text-decoration-none fw-semibold">Admin Login</a>
                        </p>
                        <a href="../index.php" class="text-muted text-decoration-none small">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loading-manager.js"></script>
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
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Real-time password validation
            function validatePasswords() {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Password strength validation
                const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/;
                const isPasswordStrong = password.length >= 8 && strongPassword.test(password);
                
                // Update new password field styling
                if (password.length === 0) {
                    newPasswordInput.classList.remove('valid', 'invalid');
                } else if (isPasswordStrong) {
                    newPasswordInput.classList.remove('invalid');
                    newPasswordInput.classList.add('valid');
                } else {
                    newPasswordInput.classList.remove('valid');
                    newPasswordInput.classList.add('invalid');
                }
                
                // Update confirm password field styling
                if (confirmPassword.length === 0) {
                    confirmPasswordInput.classList.remove('valid', 'invalid');
                } else if (password === confirmPassword && isPasswordStrong) {
                    confirmPasswordInput.classList.remove('invalid');
                    confirmPasswordInput.classList.add('valid');
                } else {
                    confirmPasswordInput.classList.remove('valid');
                    confirmPasswordInput.classList.add('invalid');
                }
            }
            
            // Add event listeners for real-time validation
            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
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
