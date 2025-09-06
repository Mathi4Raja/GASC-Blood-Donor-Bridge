<?php
require_once '../config/database.php';
require_once '../config/system-settings.php';

// Redirect if already logged in
if (isLoggedIn() && $_SESSION['user_type'] === 'donor') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Rate limiting with system settings
        $maxLoginAttempts = SystemSettings::getMaxLoginAttempts();
        if (!checkRateLimit('donor_login', $maxLoginAttempts, 300)) {
            throw new Exception("Too many login attempts. Please try again later.");
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!isValidEmail($email)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        if (empty($password)) {
            throw new Exception('Please enter your password.');
        }
        
        // Check if donor exists and is verified
        $db = new Database();
        $sql = "SELECT id, name, email, password_hash, is_verified, is_active, email_verified FROM users 
                WHERE email = ? AND user_type = 'donor'";
        $result = $db->query($sql, [$email]);
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid email or password.');
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (!verifyPassword($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password.');
        }
        
        if (!$user['is_active']) {
            throw new Exception('Your account has been deactivated. Please contact support.');
        }
        
        // Check email verification only if required by admin settings
        if (SystemSettings::isEmailVerificationRequired() && !$user['email_verified']) {
            throw new Exception('Please verify your email address before logging in.');
        }
        
        if (!$user['is_verified']) {
            throw new Exception('Your account is pending verification by our team.');
        }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = 'donor';
        $_SESSION['user_email'] = $user['email'];
        
        logActivity($user['id'], 'donor_login', "Successful login");
        
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'donor_login_failed', $error . " - Email: " . ($email ?? 'not provided'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Login - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            margin: 0 auto;
        }
        
        .login-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="login-card">
                <div class="login-header">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Donor Login</h3>
                    <p class="text-muted mb-0">Welcome back! Please sign in to your account</p>
                </div>
                
                <div class="p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope text-danger me-1"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>" required
                                   placeholder="your.email@gasc.edu">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock text-danger me-1"></i>Password
                            </label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="password" name="password" 
                                       required placeholder="Enter your password">
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <a href="#" class="text-danger text-decoration-none" onclick="showForgotPassword()">
                                Forgot your password?
                            </a>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <?php if (SystemSettings::areRegistrationsAllowed()): ?>
                        <p class="text-muted mb-2">
                            Don't have an account? 
                            <a href="register.php" class="text-danger text-decoration-none fw-semibold">Register here</a>
                        </p>
                        <?php else: ?>
                        <p class="text-muted mb-2">
                            <i class="fas fa-info-circle text-warning me-1"></i>
                            New registrations are currently disabled.
                        </p>
                        <?php endif; ?>
                        <a href="../index.php" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loading-manager.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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
        
        function showForgotPassword() {
            window.location.href = 'forgot-password.php';
        }
        
        // Auto-focus email input
        document.getElementById('email').focus();
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</body>
</html>
