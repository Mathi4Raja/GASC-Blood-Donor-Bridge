<?php
require_once '../config/database.php';
require_once '../config/otp.php';

// Redirect if already logged in
if (isLoggedIn() && $_SESSION['user_type'] === 'donor') {
    header('Location: dashboard.php');
    exit;
}

$step = $_GET['step'] ?? '1';
$email = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        if ($step === '1') {
            // Step 1: Email submission and OTP generation
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if donor exists and is active
            $db = new Database();
            $sql = "SELECT id, name, phone, is_verified, is_active, email_verified FROM users 
                    WHERE email = ? AND user_type = 'donor'";
            $result = $db->query($sql, [$email]);
            
            if ($result->num_rows === 0) {
                throw new Exception('No donor account found with this email address.');
            }
            
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                throw new Exception('Your account has been deactivated. Please contact support.');
            }
            
            if (!$user['email_verified']) {
                throw new Exception('Please verify your email address first.');
            }
            
            if (!$user['is_verified']) {
                throw new Exception('Your account is pending verification by our team.');
            }
            
            // Send OTP
            $otpResult = sendOTPToUser($email, $user['phone'], 'login', $user['name']);
            
            if ($otpResult['success']) {
                $_SESSION['login_email'] = $email;
                $_SESSION['login_attempt_time'] = time();
                $success = $otpResult['message'];
                $step = '2';
            } else {
                throw new Exception($otpResult['message']);
            }
            
        } elseif ($step === '2') {
            // Step 2: OTP verification
            $email = $_SESSION['login_email'] ?? '';
            $otp = sanitizeInput($_POST['otp'] ?? '');
            
            if (empty($email)) {
                throw new Exception('Session expired. Please start again.');
            }
            
            if (empty($otp)) {
                throw new Exception('Please enter the OTP.');
            }
            
            // Check session timeout (10 minutes)
            if (!isset($_SESSION['login_attempt_time']) || (time() - $_SESSION['login_attempt_time']) > 600) {
                unset($_SESSION['login_email'], $_SESSION['login_attempt_time']);
                throw new Exception('OTP session expired. Please request a new OTP.');
            }
            
            // Verify OTP
            if (!verifyOTP($email, $otp, 'login')) {
                throw new Exception('Invalid or expired OTP. Please try again.');
            }
            
            // Get user details
            $db = new Database();
            $sql = "SELECT id, name, email FROM users WHERE email = ? AND user_type = 'donor'";
            $result = $db->query($sql, [$email]);
            $user = $result->fetch_assoc();
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'donor';
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            
            // Clean up login session variables
            unset($_SESSION['login_email'], $_SESSION['login_attempt_time']);
            
            logActivity($user['id'], 'donor_login_otp', "Successful OTP login");
            
            header('Location: dashboard.php');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'donor_login_otp_failed', $error . " - Email: " . ($email ?? 'unknown'));
    }
}

// For step 2, get email from session
if ($step === '2' && isset($_SESSION['login_email'])) {
    $email = $_SESSION['login_email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Login with OTP - GASC Blood Bridge</title>
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
        
        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: bold;
        }
        
        .countdown-timer {
            color: #dc2626;
            font-weight: bold;
        }
        
        .btn-resend {
            background: none;
            border: none;
            color: #dc2626;
            text-decoration: underline;
            cursor: pointer;
        }
        
        .btn-resend:hover {
            color: #991b1b;
        }
        
        .btn-resend:disabled {
            color: #6b7280;
            cursor: not-allowed;
            text-decoration: none;
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
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step === '1' ? 'active' : ($step === '2' ? 'completed' : 'inactive'); ?>">1</div>
                        <div class="step-line <?php echo $step === '2' ? 'completed' : ''; ?>"></div>
                        <div class="step <?php echo $step === '2' ? 'active' : 'inactive'; ?>">2</div>
                    </div>
                    
                    <p class="text-muted mb-0">
                        <?php if ($step === '1'): ?>
                            Enter your email to receive OTP
                        <?php else: ?>
                            Enter the OTP sent to your email
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
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" required
                                       placeholder="your.email@gasc.edu">
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-paper-plane me-2"></i>Send OTP
                                </button>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <!-- Step 2: OTP Input -->
                        <form method="POST" action="?step=2" id="otpForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="otp" class="form-label">
                                    <i class="fas fa-key text-danger me-1"></i>Enter OTP
                                </label>
                                <input type="text" class="form-control otp-input" id="otp" name="otp" 
                                       maxlength="6" pattern="[0-9]{6}" required
                                       placeholder="000000">
                                <div class="form-text">
                                    OTP sent to: <?php echo htmlspecialchars($email); ?>
                                </div>
                            </div>
                            
                            <div class="text-center mb-3">
                                <div class="countdown-timer" id="countdown">10:00</div>
                                <small class="text-muted">OTP expires in</small>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-sign-in-alt me-2"></i>Verify & Login
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="text-muted small">Didn't receive OTP?</p>
                                <button type="button" class="btn-resend" id="resendBtn" onclick="resendOTP()">
                                    <i class="fas fa-redo me-1"></i>Resend OTP
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted mb-2">
                            Don't have an account? 
                            <a href="register.php" class="text-danger text-decoration-none fw-semibold">Register here</a>
                        </p>
                        <p class="text-muted mb-2">
                            <a href="login.php" class="text-muted text-decoration-none">
                                <i class="fas fa-lock me-1"></i>Login with Password
                            </a>
                        </p>
                        <a href="../index.php" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($step === '2'): ?>
        // Countdown timer
        let timeLeft = 600; // 10 minutes in seconds
        const countdownElement = document.getElementById('countdown');
        const resendBtn = document.getElementById('resendBtn');
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                countdownElement.textContent = 'Expired';
                countdownElement.style.color = '#dc2626';
                resendBtn.disabled = false;
                resendBtn.textContent = 'OTP Expired - Click to Resend';
            } else {
                timeLeft--;
            }
        }
        
        // Update countdown every second
        updateCountdown();
        const countdownInterval = setInterval(updateCountdown, 1000);
        
        // Auto-format OTP input
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) {
                // Auto-submit when 6 digits entered
                document.getElementById('otpForm').submit();
            }
        });
        
        // Focus OTP input
        document.getElementById('otp').focus();
        
        // Resend OTP function
        function resendOTP() {
            window.location.href = '?step=1&email=<?php echo urlencode($email); ?>';
        }
        
        <?php else: ?>
        // Auto-focus email input
        document.getElementById('email').focus();
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
        });
    </script>
</body>
</html>