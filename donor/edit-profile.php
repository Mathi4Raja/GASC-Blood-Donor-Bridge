<?php
require_once '../config/database.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$success = null;
$donor = null;

try {
    $db = new Database();
    
    // Get current donor information
    $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'donor'";
    $result = $db->query($sql, [$_SESSION['user_id']]);
    $donor = $result->fetch_assoc();
    
    if (!$donor) {
        throw new Exception('Donor not found');
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            // Validate and sanitize input
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $class = trim($_POST['class'] ?? '');
            $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
            
            // Validation
            if (empty($name) || empty($phone) || empty($city) || empty($dateOfBirth)) {
                throw new Exception('Please fill all required fields.');
            }
            
            if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
                throw new Exception('Please enter a valid 10-digit Indian phone number.');
            }
            
            // Validate date of birth and calculate age
            $birthDate = new DateTime($dateOfBirth);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18 || $age > 65) {
                throw new Exception('Age must be between 18 and 65 years for blood donation.');
            }
            
            // Update profile
            $updateSQL = "UPDATE users SET name = ?, phone = ?, city = ?, class = ?, date_of_birth = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $db->query($updateSQL, [$name, $phone, $city, $class, $dateOfBirth, $_SESSION['user_id']]);
            
            logActivity($_SESSION['user_id'], 'profile_updated', "Profile information updated");
            
            $success = "Profile updated successfully!";
            
            // Refresh donor data
            $result = $db->query($sql, [$_SESSION['user_id']]);
            $donor = $result->fetch_assoc();
            
        } else {
            $error = "Invalid security token.";
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'profile_update_error', $error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 2rem 0;
        }
        
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25);
        }
        
        .btn-primary {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-primary:hover {
            background-color: #991b1b;
            border-color: #991b1b;
        }
        
        .readonly-field {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        /* Mobile Navigation Styles */
        .mobile-nav-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .mobile-nav-toggle:hover {
            background: var(--dark-red);
            color: white;
            transform: scale(1.05);
        }
        
        @media (max-width: 767.98px) {
            .profile-header {
                padding-top: 60px;
            }
            
            .container.mt-4 {
                margin-top: 1rem !important;
                padding-top: 20px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle d-lg-none" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>
    
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-user-edit me-2"></i>Edit Profile</h2>
                    <p class="mb-0">Update your personal information</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body p-4">
                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($donor['name'] ?? ''); ?>" required>
                                    <div class="form-text">Your full name as per official documents</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control readonly-field" 
                                           value="<?php echo htmlspecialchars($donor['email'] ?? ''); ?>" readonly>
                                    <div class="form-text">Email cannot be changed for security reasons</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($donor['phone'] ?? ''); ?>" 
                                           pattern="[6-9]\d{9}" maxlength="10" required>
                                    <div class="form-text">10-digit Indian mobile number</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="blood_group" class="form-label">Blood Group</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?php echo htmlspecialchars($donor['blood_group'] ?? ''); ?>" readonly>
                                    <div class="form-text">Contact admin to change blood group</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?php echo htmlspecialchars($donor['gender'] ?? ''); ?>" readonly>
                                    <div class="form-text">Gender cannot be modified</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($donor['date_of_birth'] ?? ''); ?>" 
                                           max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                           min="<?php echo date('Y-m-d', strtotime('-65 years')); ?>" required>
                                    <div class="form-text">Age must be between 18-65 years</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Age</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?php 
                                           if ($donor['date_of_birth']) {
                                               echo calculateAge($donor['date_of_birth']) . ' years old';
                                           } else {
                                               echo 'Not calculated';
                                           }
                                           ?>" readonly>
                                    <div class="form-text">Automatically calculated from date of birth</div>
                                </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($donor['city'] ?? ''); ?>" required>
                                    <div class="form-text">Your current city of residence</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="class" class="form-label">Class/Department</label>
                                    <input type="text" class="form-control" id="class" name="class" 
                                           value="<?php echo htmlspecialchars($donor['class'] ?? ''); ?>">
                                    <div class="form-text">Your class, department, or designation</div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Account Information</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Last Donation:</strong><br>
                                                    <span class="text-muted">
                                                        <?php echo $donor['last_donation_date'] ? date('M d, Y', strtotime($donor['last_donation_date'])) : 'Never'; ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Member Since:</strong><br>
                                                    <span class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($donor['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Phone number validation
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });
            
            // Form validation
            const form = document.getElementById('profileForm');
            form.addEventListener('submit', function(e) {
                const phone = phoneInput.value;
                if (!/^[6-9]\d{9}$/.test(phone)) {
                    e.preventDefault();
                    alert('Please enter a valid 10-digit Indian phone number starting with 6-9');
                    phoneInput.focus();
                    return;
                }
                
                const age = document.getElementById('age').value;
                if (age < 18 || age > 65) {
                    e.preventDefault();
                    alert('Age must be between 18 and 65 years');
                    document.getElementById('age').focus();
                    return;
                }
            });
        });
    </script>
</body>
</html>
