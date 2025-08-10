    <style>
        .back-home-btn-card {
            background: #fff;
            color: #dc2626 !important;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            box-shadow: 0 2px 10px rgba(220,38,38,0.10);
            border: 1px solid #f3f4f6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .back-home-btn-card:hover {
            background: #fee2e2;
            color: #991b1b !important;
            box-shadow: 0 4px 16px rgba(220,38,38,0.18);
            text-decoration: none;
        }
        @media (max-width: 576px) {
            .back-home-btn-card {
                width: 36px;
                height: 36px;
                font-size: 1rem;
                margin-top: 10px !important;
                margin-left: 10px !important;
            }
        }
    </style>
<?php
require_once '../config/database.php';

// Redirect if already logged in
if (isLoggedIn() && in_array($_SESSION['user_type'], ['admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Rate limiting
        if (!checkRateLimit('admin_login', 5, 300)) {
            throw new Exception('Too many login attempts. Please try again later.');
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $userType = sanitizeInput($_POST['user_type'] ?? '');
        
        // Validation
        if (empty($email) || empty($password) || empty($userType)) {
            throw new Exception('All fields are required.');
        }
        
        if (!isValidEmail($email)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        if (!in_array($userType, ['admin', 'moderator'])) {
            throw new Exception('Invalid user type selected.');
        }
        
        // Check user credentials
        $db = new Database();
        $sql = "SELECT id, name, email, password_hash, user_type, is_active, is_verified 
                FROM users WHERE email = ? AND user_type = ?";
        $result = $db->query($sql, [$email, $userType]);
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid email or user type.');
        }
        
        $user = $result->fetch_assoc();
        
        if (!$user['is_active']) {
            throw new Exception('Your account has been deactivated. Please contact support.');
        }
        
        if (!$user['is_verified']) {
            throw new Exception('Your account is pending verification.');
        }
        
        // Verify password
        if (!verifyPassword($password, $user['password_hash'])) {
            throw new Exception('Invalid password.');
        }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        
        // Log successful login
        logActivity($user['id'], 'admin_login', "Successful {$user['user_type']} login");
        
        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'admin_login_failed', $error . " - Email: " . ($email ?? 'unknown'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin/Moderator Login - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .admin-login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 10px 0;
            position: relative;
        }
        
        .admin-login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }
        
        .admin-login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .admin-login-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 1.5rem 2rem 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .admin-login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #dc2626, #991b1b);
            border-radius: 2px;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            box-shadow: 0 10px 30px rgba(220, 38, 38, 0.3);
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.8rem 0.75rem;
            height: calc(3rem + 2px);
            transition: all 0.3s ease;
        }
        
        /* Add padding for password field with toggle button */
        .form-floating.position-relative .form-control {
            padding-right: 50px;
        }
        
        /* Adjust when Bootstrap validation is active */
        .was-validated .form-floating.position-relative .form-control:valid {
            padding-right: 80px;
        }
        
        .form-floating .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .form-floating .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            height: calc(3rem + 2px);
        }
        
        .form-floating .form-select:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border: none;
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, #991b1b, #dc2626);
        }
        
        .security-note {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
        }
        
        .user-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 1rem;
        }
        
        .user-type-option {
            position: relative;
        }
        
        .user-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .user-type-option label {
            display: block;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .user-type-option input[type="radio"]:checked + label {
            background: #fee2e2;
            border-color: #dc2626;
            color: #dc2626;
        }
        
        .user-type-option label i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
        }
        
        @media (max-width: 576px) {
            .admin-login-card {
                margin: 2vw 2vw;
            }
            .admin-login-header {
                padding: 0.7rem 0.7rem 0.7rem;
            }
            .user-type-selector {
                grid-template-columns: 1fr;
            }
            .admin-login-container {
                padding: 1vw 0;
            }
            .p-3 {
                padding: 0.8rem !important;
            }
        }
    </style>
</head>
<body class="admin-login">
    <style>
        .back-home-btn {
            background: #fff;
            color: #dc2626 !important;
            border-radius: 50px;
            padding: 8px 20px;
            box-shadow: 0 2px 10px rgba(220,38,38,0.10);
            font-weight: 600;
            border: 1px solid #f3f4f6;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
        }
        .back-home-btn:hover {
            background: #fee2e2;
            color: #991b1b !important;
            box-shadow: 0 4px 16px rgba(220,38,38,0.18);
            text-decoration: none;
        }
        .back-home-btn i {
            font-size: 1rem;
        }
        .back-home-btn-mobile {
            background: #fff;
            color: #dc2626 !important;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            box-shadow: 0 2px 10px rgba(220,38,38,0.10);
            border: 1px solid #f3f4f6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .back-home-btn-mobile:hover {
            background: #fee2e2;
            color: #991b1b !important;
            box-shadow: 0 4px 16px rgba(220,38,38,0.18);
            text-decoration: none;
        }
        @media (max-width: 576px) {
            .back-home-btn { display: none !important; }
            .back-home-btn-mobile { display: inline-flex !important; }
        }
        @media (min-width: 577px) {
            .back-home-btn-mobile { display: none !important; }
        }
    </style>
    <style>
        .back-home-btn {
            background: #fff;
            color: #dc2626 !important;
            border-radius: 50px;
            padding: 8px 20px;
            box-shadow: 0 2px 10px rgba(220,38,38,0.10);
            font-weight: 600;
            border: 1px solid #f3f4f6;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
        }
        .back-home-btn:hover {
            background: #fee2e2;
            color: #991b1b !important;
            box-shadow: 0 4px 16px rgba(220,38,38,0.18);
            text-decoration: none;
        }
        .back-home-btn i {
            font-size: 1rem;
        }
    </style>
    <div class="admin-login-container">
        <div class="container">
            <div class="admin-login-card position-relative">
                <a href="../index.php" class="back-home-btn-card position-absolute top-0 start-0 mt-3 ms-3 text-decoration-none" style="z-index:1050;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="admin-login-header">
                    <div class="admin-badge">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="text-center mb-2">
                        <h3 class="text-danger fw-bold mb-0">GASC Blood Bridge</h3>
                    </div>
                    <h5 class="text-dark mb-1">Secure Access Portal</h5>
                    <p class="text-muted mb-0 small">Admin & Moderator Login</p>
                </div>
                
                <div class="p-3">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="adminLoginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- User Type Selection -->
                        <div class="user-type-selector">
                            <div class="user-type-option">
                                <input type="radio" id="admin_type" name="user_type" value="admin" 
                                       <?php echo (($userType ?? '') === 'admin') ? 'checked' : ''; ?> required>
                                <label for="admin_type">
                                    <i class="fas fa-user-shield text-danger"></i>
                                    <strong>Administrator</strong>
                                    <small class="d-block text-muted">Full system access</small>
                                </label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="moderator_type" name="user_type" value="moderator" 
                                       <?php echo (($userType ?? '') === 'moderator') ? 'checked' : ''; ?> required>
                                <label for="moderator_type">
                                    <i class="fas fa-user-cog text-warning"></i>
                                    <strong>Moderator</strong>
                                    <small class="d-block text-muted">Limited access</small>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Email Input -->
                        <div class="form-floating">
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $email ?? ''; ?>" placeholder="admin@gasc.edu" required>
                            <label for="email">
                                <i class="fas fa-envelope text-danger me-1"></i>Email Address
                            </label>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                        
                        <!-- Password Input -->
                        <div class="form-floating position-relative">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required>
                            <label for="password">
                                <i class="fas fa-lock text-danger me-1"></i>Password
                            </label>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2" 
                                    id="togglePassword" style="z-index: 10;">
                                <i class="fas fa-eye text-muted"></i>
                            </button>
                            <div class="invalid-feedback">Please provide your password.</div>
                        </div>
                        
                        <!-- Login Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-admin text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Secure Login
                            </button>
                        </div>
                        
                        <!-- Forgot Password -->
                        <div class="text-center">
                            <a href="forgot-password.php" class="text-danger text-decoration-none">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>
                        </div>
                    </form>
                    
                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('adminLoginForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const userType = form.querySelector('input[name="user_type"]:checked');
                
                if (!userType) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show error for user type selection
                    const userTypeContainer = document.querySelector('.user-type-selector');
                    if (!userTypeContainer.querySelector('.invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback d-block';
                        errorDiv.textContent = 'Please select your role (Admin or Moderator).';
                        userTypeContainer.appendChild(errorDiv);
                        
                        setTimeout(() => errorDiv.remove(), 5000);
                    }
                }
                
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
            
            // Clear validation on input
            form.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
            });
            
            // Auto-focus email field
            document.getElementById('email').focus();
            
            // Caps lock detection
            document.addEventListener('keyup', function(e) {
                if (e.getModifierState && e.getModifierState('CapsLock')) {
                    if (!document.getElementById('capsLockWarning')) {
                        const warning = document.createElement('div');
                        warning.id = 'capsLockWarning';
                        warning.className = 'alert alert-warning alert-dismissible fade show mt-2';
                        warning.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Caps Lock is ON
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        passwordInput.parentNode.appendChild(warning);
                    }
                } else {
                    const warning = document.getElementById('capsLockWarning');
                    if (warning) {
                        warning.remove();
                    }
                }
            });
        });
    </script>
</body>
</html>
