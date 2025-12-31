<?php
require_once '../config/database.php';
require_once 'includes/sidebar-utils.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$success = null;
$donor = null;

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

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
            $action = $_POST['action'] ?? '';
            
            switch($action) {
                case 'update_availability_status':
                    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
                    $updateSQL = "UPDATE users SET is_available = ? WHERE id = ?";
                    $db->query($updateSQL, [$isAvailable, $_SESSION['user_id']]);
                    
                    // Clear sidebar cache to reflect availability change
                    clearSidebarCache();
                    
                    logActivity($_SESSION['user_id'], 'availability_updated', "Availability status updated to " . ($isAvailable ? 'available' : 'unavailable'));
                    $success = "Availability status updated successfully!";
                    $donor['is_available'] = $isAvailable;
                    
                    // Redirect to refresh sidebar data
                    header("Location: settings.php?refresh_sidebar=1&success=" . urlencode($success));
                    exit;
                    break;
                    
                case 'change_password':
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    // Verify current password
                    if (!password_verify($currentPassword, $donor['password_hash'])) {
                        throw new Exception('Current password is incorrect.');
                    }
                    
                    // Validate new password
                    if (strlen($newPassword) < 8) {
                        throw new Exception('New password must be at least 8 characters long.');
                    }
                    
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('New passwords do not match.');
                    }
                    
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateSQL = "UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $db->query($updateSQL, [$hashedPassword, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'password_changed', "Password changed successfully");
                    $success = "Password changed successfully!";
                    break;
                    
                default:
                    throw new Exception('Invalid action.');
            }
            
        } else {
            $error = "Invalid security token.";
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'settings_error', $error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .settings-card .card-header {
            background: #f8f9fa;
            border-bottom: 2px solid #dc2626;
        }
        
        .form-switch .form-check-input:checked {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .availability-status {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .status-available {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .status-unavailable {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-cog me-2"></i>Settings & Preferences</h2>
                <p class="text-muted mb-0">Manage your account settings and donation preferences</p>
            </div>

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
        
        <div class="row">
            <!-- Availability Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat text-danger me-2"></i>
                            Donation Availability
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Current Status -->
                        <div class="availability-status <?php echo $donor['is_available'] ? 'status-available' : 'status-unavailable'; ?>">
                            <div class="d-flex align-items-center">
                                <i class="fas <?php echo $donor['is_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                                <strong>
                                    Current Status: <?php echo $donor['is_available'] ? 'Available' : 'Unavailable'; ?>
                                </strong>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_availability_status">
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="availabilitySwitch" 
                                       name="is_available" <?php echo $donor['is_available'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="availabilitySwitch">
                                    Mark me as available for blood donation
                                </label>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    When marked as available, you may receive calls for urgent blood donation requests.
                                </small>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-save me-2"></i>Update Availability
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="col-lg-6 mb-4">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user text-danger me-2"></i>
                            Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">Full Name</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($donor['name']); ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">Blood Group</small>
                                <div class="fw-bold text-danger"><?php echo htmlspecialchars($donor['blood_group']); ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">Email</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($donor['email']); ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">Phone</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($donor['phone']); ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">City</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($donor['city']); ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <small class="text-muted">Member Since</small>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($donor['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="edit-profile.php" class="btn btn-outline-danger">
                                <i class="fas fa-edit me-2"></i>Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Password Change Section -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock text-danger me-2"></i>
                            Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="current_password" class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-text">Enter your current password for verification</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" minlength="8" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" minlength="8" required>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>
                                    Password must be at least 8 characters long and contain a mix of letters and numbers for security.
                                </small>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password form validation
            const passwordForm = document.getElementById('passwordForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            passwordForm.addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    confirmPassword.focus();
                    return;
                }
                
                if (newPassword.value.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    newPassword.focus();
                    return;
                }
                
                // Confirm password change
                if (!confirm('Are you sure you want to change your password?')) {
                    e.preventDefault();
                }
            });
            
            // Real-time password confirmation
            confirmPassword.addEventListener('input', function() {
                if (this.value && this.value !== newPassword.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>
