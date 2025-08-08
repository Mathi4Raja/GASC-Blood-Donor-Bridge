<?php
require_once '../config/database.php';
require_once '../config/email.php'; // Ensure email configuration is loaded

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Rate limiting
        if (!checkRateLimit('donor_registration', 3, 300)) {
            throw new Exception('Too many registration attempts. Please try again later.');
        }
        
        // Validate input
        $rollNo = sanitizeInput($_POST['roll_no'] ?? '');
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $dateOfBirth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $class = sanitizeInput($_POST['class'] ?? '');
        $bloodGroup = sanitizeInput($_POST['blood_group'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        
        // Validation
        if (empty($rollNo) || empty($name) || empty($email) || empty($phone) || 
            empty($password) || empty($gender) || empty($dateOfBirth) || empty($class) || 
            empty($bloodGroup) || empty($city)) {
            throw new Exception('All fields are required.');
        }
        
        if (!isValidEmail($email)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        if (!isValidPhone($phone)) {
            throw new Exception('Please enter a valid 10-digit phone number.');
        }
        
        // Validate date of birth
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age < 18 || $age > 65) {
            throw new Exception('You must be between 18 and 65 years old to register as a donor.');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match.');
        }
        
        // Check if user already exists
        $db = new Database();
        $checkEmail = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
        if ($checkEmail->num_rows > 0) {
            throw new Exception('An account with this email already exists.');
        }
        
        $checkRollNo = $db->query("SELECT id FROM users WHERE roll_no = ?", [$rollNo]);
        if ($checkRollNo->num_rows > 0) {
            throw new Exception('An account with this roll number already exists.');
        }
        
        // Generate verification token and hash password
        $verificationToken = generateSecureToken();
        $hashedPassword = hashPassword($password);
        
        // Insert new donor
        $sql = "INSERT INTO users (roll_no, name, email, phone, password_hash, user_type, gender, date_of_birth, class, blood_group, city, email_verification_token) 
                VALUES (?, ?, ?, ?, ?, 'donor', ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sssssssssss', 
            $rollNo, $name, $email, $phone, $hashedPassword, 
            $gender, $dateOfBirth, $class, $bloodGroup, $city, $verificationToken
        );
        
        if ($stmt->execute()) {
            $userId = $db->lastInsertId();
            
            // Send verification email
            $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/GASC-Blood-Donor-Bridge/donor/verify-email.php?token=" . $verificationToken;
            $emailSubject = "GASC Blood Bridge - Verify Your Email";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>GASC Blood Bridge</h1>
                </div>
                <div style='padding: 30px; background: #f8f9fa;'>
                    <h2>Welcome $name!</h2>
                    <p>Thank you for registering as a blood donor with GASC Blood Bridge.</p>
                    <p>To complete your registration, please click the button below to verify your email address:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verificationLink' style='background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verify Email Address</a>
                    </div>
                    <p>Or copy and paste this link in your browser:</p>
                    <p style='word-break: break-all; background: white; padding: 10px; border-radius: 5px;'>$verificationLink</p>
                    <p>This link will expire in 24 hours.</p>
                    <hr style='margin: 30px 0; border: 1px solid #dee2e6;'>
                    <p style='color: #6c757d; font-size: 14px;'>
                        If you didn't create this account, please ignore this email.<br>
                        Best regards,<br>
                        GASC Blood Bridge Team
                    </p>
                </div>
            </div>
            ";
            
            sendEmail($email, $emailSubject, $emailBody);
            
            // Log activity
            logActivity($userId, 'donor_registration', "New donor registered: $name ($email)");
            
            $success = "Registration successful! Please check your email to verify your account.";
            
        } else {
            throw new Exception('Registration failed. Please try again.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'donor_registration_failed', $error . " - Email: " . ($email ?? 'unknown'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Donor - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
        .registration-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            display: flex;
            align-items: center;
            padding: 0;
        }
        
        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 800px;
            margin: 10px auto;
        }
        
        .registration-header {
            background: linear-gradient(135deg, #fee2e2, #white);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section {
            padding: 2rem;
        }
        
        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .blood-group-option {
            position: relative;
        }
        
        .blood-group-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .blood-group-option label {
            display: block;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .blood-group-option input[type="radio"]:checked + label {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        @media (max-width: 768px) {
            .blood-group-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .registration-card {
                margin: 10px;
            }
            
            .form-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="container">
            <div class="registration-card position-relative">
                <a href="../index.php" class="back-home-btn-card position-absolute top-0 start-0 mt-3 ms-3 text-decoration-none" style="z-index:1050;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="registration-header">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <h2 class="text-danger fw-bold mb-0">GASC Blood Bridge</h2>
                    </div>
                    <h3 class="text-dark mb-2">Become a Life Saver</h3>
                    <p class="text-muted mb-0">Join our community of heroes and help save lives</p>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success m-3 d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                    <div class="text-center p-3">
                        <a href="../index.php" class="btn btn-danger">Return to Home</a>
                        <a href="login.php" class="btn btn-outline-danger ms-2">Login</a>
                    </div>
                <?php else: ?>
                    <div class="form-section">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="registrationForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="roll_no" class="form-label">
                                        <i class="fas fa-id-card text-danger me-1"></i>Roll Number *
                                    </label>
                                    <input type="text" class="form-control" id="roll_no" name="roll_no" 
                                           value="<?php echo $rollNo ?? ''; ?>" required
                                           placeholder="e.g., CS2021001">
                                    <div class="invalid-feedback">Please provide a valid roll number.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-user text-danger me-1"></i>Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo $name ?? ''; ?>" required
                                           placeholder="Enter your full name">
                                    <div class="invalid-feedback">Please provide your name.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope text-danger me-1"></i>Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $email ?? ''; ?>" required
                                           placeholder="your.email@gasc.edu">
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone text-danger me-1"></i>Phone Number *
                                    </label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo $phone ?? ''; ?>" required
                                           placeholder="10-digit mobile number">
                                    <div class="invalid-feedback">Please provide a valid 10-digit phone number.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars text-danger me-1"></i>Gender *
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (($gender ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (($gender ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (($gender ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your gender.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">
                                        <i class="fas fa-calendar-alt text-danger me-1"></i>Date of Birth *
                                    </label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo $dateOfBirth ?? ''; ?>" required
                                           max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                           min="<?php echo date('Y-m-d', strtotime('-65 years')); ?>">
                                    <div class="invalid-feedback">Please provide your date of birth. Must be 18-65 years old.</div>
                                    <small class="form-text text-muted">Must be between 18-65 years old for blood donation</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="class" class="form-label">
                                        <i class="fas fa-graduation-cap text-danger me-1"></i>Class/Course *
                                    </label>
                                    <input type="text" class="form-control" id="class" name="class" 
                                           value="<?php echo $class ?? ''; ?>" required
                                           placeholder="e.g., B.Tech CSE, M.Tech, MBA">
                                    <div class="invalid-feedback">Please provide your class/course.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tint text-danger me-1"></i>Blood Group *
                                    </label>
                                    <input type="text" class="form-control" id="blood_group" name="blood_group" 
                                           value="<?php echo $bloodGroup ?? ''; ?>" required
                                           placeholder="e.g., A+, O-, B+" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();">
                                    <div class="invalid-feedback">Please provide your blood group (e.g., A+, O-, B+).</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>City *
                                    </label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo $city ?? ''; ?>" required
                                           placeholder="Your current city">
                                    <div class="invalid-feedback">Please provide your city.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock text-danger me-1"></i>Password *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required
                                               placeholder="At least 8 characters">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye" id="passwordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock text-danger me-1"></i>Confirm Password *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                               placeholder="Repeat your password">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" class="text-danger">Terms and Conditions</a> and 
                                        <a href="#" class="text-danger">Privacy Policy</a> *
                                    </label>
                                    <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger btn-lg">
                                    <i class="fas fa-heart me-2"></i>Register as Donor
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted">
                                Already have an account? 
                                <a href="login.php" class="text-danger text-decoration-none">Login here</a>
                            </p>
                            <!-- Back to Home button moved to top-left inside card -->
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordIcon = document.getElementById('passwordIcon');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordIcon = document.getElementById('confirmPasswordIcon');
            
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle the eye icon
                    if (type === 'password') {
                        passwordIcon.classList.remove('fa-eye-slash');
                        passwordIcon.classList.add('fa-eye');
                    } else {
                        passwordIcon.classList.remove('fa-eye');
                        passwordIcon.classList.add('fa-eye-slash');
                    }
                });
            }
            
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    
                    // Toggle the eye icon
                    if (type === 'password') {
                        confirmPasswordIcon.classList.remove('fa-eye-slash');
                        confirmPasswordIcon.classList.add('fa-eye');
                    } else {
                        confirmPasswordIcon.classList.remove('fa-eye');
                        confirmPasswordIcon.classList.add('fa-eye-slash');
                    }
                });
            }
            
            // Password strength indicator
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
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Phone number validation
            document.getElementById('phone').addEventListener('input', function() {
                const phone = this.value.replace(/\D/g, '');
                if (phone.length === 10 && phone.match(/^[6-9]/)) {
                    this.setCustomValidity('');
                } else {
                    this.setCustomValidity('Please enter a valid 10-digit phone number starting with 6-9');
                }
            });
            
            // Date of birth validation with dynamic constraints
            const dobField = document.getElementById('date_of_birth');
            if (dobField) {
                // Calculate and set dynamic min/max dates
                const today = new Date();
                const minDate = new Date(today.getFullYear() - 65, today.getMonth(), today.getDate());
                const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
                
                dobField.setAttribute('min', minDate.toISOString().split('T')[0]);
                dobField.setAttribute('max', maxDate.toISOString().split('T')[0]);
                
                dobField.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const age = Math.floor((today - selectedDate) / (365.25 * 24 * 60 * 60 * 1000));
                    
                    if (age < 18) {
                        this.setCustomValidity('You must be at least 18 years old to register as a donor');
                        this.classList.add('is-invalid');
                    } else if (age > 65) {
                        this.setCustomValidity('Maximum age for blood donation is 65 years');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
                
                // Real-time validation as user changes date
                dobField.addEventListener('input', function() {
                    if (this.value) {
                        const selectedDate = new Date(this.value);
                        const age = Math.floor((today - selectedDate) / (365.25 * 24 * 60 * 60 * 1000));
                        
                        if (age < 18 || age > 65) {
                            this.classList.add('is-invalid');
                            this.classList.remove('is-valid');
                        } else {
                            this.classList.remove('is-invalid');
                            this.classList.add('is-valid');
                        }
                    }
                });
            }
            
            // Form submission
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>
