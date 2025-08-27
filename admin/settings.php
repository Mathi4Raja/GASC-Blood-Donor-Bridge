<?php
require_once '../config/database.php';
require_once '../config/email.php';
require_once '../config/env.php';

// Check if user is logged in as admin
requireRole(['admin']);

$db = new Database();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        try {
            $settings = [
                'site_name' => $_POST['site_name'] ?? 'GASC Blood Bridge',
                'admin_email' => $_POST['admin_email'] ?? '',
                'max_requests_per_user' => (int)($_POST['max_requests_per_user'] ?? 5),
                'request_expiry_days' => (int)($_POST['request_expiry_days'] ?? 30),
                'donation_cooldown_days' => (int)($_POST['donation_cooldown_days'] ?? 56),
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'auto_expire_requests' => isset($_POST['auto_expire_requests']) ? 1 : 0,
                'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0,
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                'allow_registrations' => isset($_POST['allow_registrations']) ? 1 : 0
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->bind_param('ss', $key, $value);
                $stmt->execute();
            }
            
            logActivity($_SESSION['user_id'], 'update_system_settings', 'Updated system settings');
            $message = 'System settings updated successfully!';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $messageType = 'danger';
        }
        
    } elseif ($action === 'backup_database') {
        try {
            // Ensure backup directory exists
            if (!is_dir('../database')) {
                mkdir('../database', 0755, true);
            }
            
            // Database credentials from environment variables
            $host = EnvLoader::get('DB_HOST', 'localhost');
            $username = EnvLoader::get('DB_USERNAME', 'root');
            $password = EnvLoader::get('DB_PASSWORD', '');
            $database = EnvLoader::get('DB_NAME', 'gasc_blood_bridge');
            
            // Full path to mysqldump - remove extra quotes from env variable
            $mysqldump_path = trim(EnvLoader::get('MYSQLDUMP_PATH', 'C:\\Program Files\\XAMPP\\mysql\\bin\\mysqldump.exe'), '"');
            
            // Verify mysqldump exists
            if (!file_exists($mysqldump_path)) {
                throw new Exception("mysqldump not found at: $mysqldump_path");
            }
            
            // Build the backup file path
            $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_file_full = realpath('../database') . DIRECTORY_SEPARATOR . $backup_filename;
            
            // Log the attempt
            error_log("Starting database backup - File: $backup_filename");
            
            // Use popen for direct process execution (most reliable on Windows)
            if (empty($password)) {
                $command = "\"$mysqldump_path\" --user=\"$username\" --host=\"$host\" --single-transaction --routines --triggers \"$database\"";
            } else {
                $command = "\"$mysqldump_path\" --user=\"$username\" --password=\"$password\" --host=\"$host\" --single-transaction --routines --triggers \"$database\"";
            }
            
            // Execute command and capture output
            $handle = popen($command, 'r');
            if (!$handle) {
                throw new Exception('Failed to execute mysqldump command');
            }
            
            $backup_content = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) break;
                $backup_content .= $chunk;
            }
            $return_code = pclose($handle);
            
            error_log("Backup command executed - Return code: $return_code, Content length: " . strlen($backup_content));
            
            // Check if we got valid backup content
            if ($return_code === 0 && !empty($backup_content) && strlen($backup_content) > 1000 && strpos($backup_content, 'CREATE TABLE') !== false) {
                // Write backup to file
                if (file_put_contents($backup_file_full, $backup_content) !== false) {
                    $file_size_mb = round(strlen($backup_content) / 1024 / 1024, 2);
                    logActivity($_SESSION['user_id'], 'backup_database', 'Created database backup: ' . $backup_filename . ' (' . $file_size_mb . ' MB)');
                    error_log("Backup successful - File: $backup_filename, Size: $file_size_mb MB");
                    $message = 'Database backup created successfully: ' . $backup_filename . ' (' . $file_size_mb . ' MB)';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to write backup file to: ' . $backup_file_full);
                }
            } else {
                // Log error details for debugging
                $error_sample = substr($backup_content, 0, 500);
                error_log("Backup failed - Return code: $return_code, Content sample: " . $error_sample);
                
                if ($return_code !== 0) {
                    throw new Exception("mysqldump returned error code: $return_code");
                } elseif (empty($backup_content)) {
                    throw new Exception('mysqldump produced no output');
                } elseif (strlen($backup_content) <= 1000) {
                    throw new Exception('mysqldump produced insufficient content (' . strlen($backup_content) . ' bytes)');
                } else {
                    throw new Exception('mysqldump output does not contain expected SQL structure');
                }
            }
            
        } catch (Exception $e) {
            error_log("Backup exception: " . $e->getMessage());
            $message = 'Error creating backup: ' . $e->getMessage();
            $messageType = 'danger';
        }
        
    } elseif ($action === 'test_email') {
        $test_email = $_POST['test_email'] ?? '';
        
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            try {
                $subject = 'GASC Blood Bridge - Email Test';
                $body = 'This is a test email from GASC Blood Bridge system. If you receive this, email configuration is working correctly.';
                
                if (sendEmail($test_email, $subject, $body)) {
                    $message = 'Test email sent successfully to ' . $test_email;
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send test email. Please check email configuration.';
                    $messageType = 'danger';
                }
                
            } catch (Exception $e) {
                $message = 'Error sending test email: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Please enter a valid email address.';
            $messageType = 'warning';
        }
        
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        try {
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New password and confirmation do not match.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters long.');
            }
            
            // Get current user's password hash
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('si', $new_password_hash, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'password_changed', 'Admin password changed successfully');
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } else {
                throw new Exception('Failed to update password.');
            }
            
        } catch (Exception $e) {
            $message = 'Error changing password: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get current settings
$currentSettings = [];
$settingsResult = $db->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $settingsResult->fetch_assoc()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'site_name' => 'GASC Blood Bridge',
    'admin_email' => '',
    'max_requests_per_user' => 5,
    'request_expiry_days' => 30,
    'donation_cooldown_days' => 56,
    'email_notifications' => 1,
    'auto_expire_requests' => 1,
    'require_email_verification' => 1,
    'maintenance_mode' => 0,
    'allow_registrations' => 1
];

foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}

// Get system statistics
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_donors'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor'")->fetch_assoc()['count'];
$stats['total_requests'] = $db->query("SELECT COUNT(*) as count FROM blood_requests")->fetch_assoc()['count'];
$stats['total_logs'] = $db->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$stats['database_size'] = $db->query("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
")->fetch_assoc()['size_mb'] ?? 0;

// Get recent backups
$backup_files = [];
if (is_dir('../database/')) {
    $files = glob('../database/backup_*.sql');
    foreach ($files as $file) {
        $backup_files[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .settings-section {
            border-bottom: 1px solid #e9ecef;
            padding: 20px 0;
        }
        
        .settings-section:last-child {
            border-bottom: none;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
        }
        
        .backup-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 10px 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.25em;
        }
        
        @media (max-width: 768px) {
            .settings-card {
                margin-bottom: 15px;
            }
            
            .settings-section {
                padding: 15px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle" type="button" id="mobileNavToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar bg-light vh-100 sticky-top" id="adminSidebar">
                <!-- Close button for mobile -->
                <button class="sidebar-close d-md-none" type="button" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="p-3">
                    <div class="d-flex align-items-center mb-4">
                        <i class="fas fa-tint text-danger me-2 fs-4"></i>
                        <h5 class="mb-0 text-danger fw-bold">GASC Blood Bridge</h5>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="donors.php">
                            <i class="fas fa-users me-2"></i>Manage Donors
                        </a>
                        <a class="nav-link" href="requests.php">
                            <i class="fas fa-hand-holding-heart me-2"></i>Blood Requests
                        </a>
                        <a class="nav-link" href="inventory.php">
                            <i class="fas fa-warehouse me-2"></i>Blood Inventory
                        </a>
                        <a class="nav-link" href="moderators.php">
                            <i class="fas fa-user-cog me-2"></i>Manage Moderators
                        </a>
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="logs.php">
                            <i class="fas fa-clipboard-list me-2"></i>Activity Logs
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 admin-main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cog text-danger me-2"></i>System Settings
                    </h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-primary"><?php echo $stats['total_users']; ?></div>
                            <div class="text-muted small">Total Users</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-success"><?php echo $stats['total_donors']; ?></div>
                            <div class="text-muted small">Total Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-warning"><?php echo $stats['total_requests']; ?></div>
                            <div class="text-muted small">Blood Requests</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-info"><?php echo $stats['total_logs']; ?></div>
                            <div class="text-muted small">Activity Logs</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-danger"><?php echo $stats['database_size']; ?> MB</div>
                            <div class="text-muted small">Database Size</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="stat-card">
                            <div class="h4 text-secondary"><?php echo count($backup_files); ?></div>
                            <div class="text-muted small">Backups</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- General Settings -->
                    <div class="col-lg-8">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="settings-section">
                                        <h6 class="text-primary">Site Configuration</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="site_name" class="form-label">Site Name</label>
                                                    <input type="text" class="form-control" id="site_name" name="site_name" 
                                                           value="<?php echo htmlspecialchars($currentSettings['site_name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="admin_email" class="form-label">Admin Email</label>
                                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                           value="<?php echo htmlspecialchars($currentSettings['admin_email']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6 class="text-primary">Request Limits</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="max_requests_per_user" class="form-label">Max Requests per User</label>
                                                    <input type="number" class="form-control" id="max_requests_per_user" name="max_requests_per_user" 
                                                           value="<?php echo $currentSettings['max_requests_per_user']; ?>" min="1" max="20">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="request_expiry_days" class="form-label">Request Expiry (Days)</label>
                                                    <input type="number" class="form-control" id="request_expiry_days" name="request_expiry_days" 
                                                           value="<?php echo $currentSettings['request_expiry_days']; ?>" min="1" max="365">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="donation_cooldown_days" class="form-label">Donation Cooldown (Days)</label>
                                                    <input type="number" class="form-control" id="donation_cooldown_days" name="donation_cooldown_days" 
                                                           value="<?php echo $currentSettings['donation_cooldown_days']; ?>" min="1" max="365">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6 class="text-primary">System Features</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                           <?php echo $currentSettings['email_notifications'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_notifications">
                                                        Email Notifications
                                                    </label>
                                                </div>
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="auto_expire_requests" name="auto_expire_requests" 
                                                           <?php echo $currentSettings['auto_expire_requests'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_expire_requests">
                                                        Auto-expire Requests
                                                    </label>
                                                </div>
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification" 
                                                           <?php echo $currentSettings['require_email_verification'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="require_email_verification">
                                                        Require Email Verification
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="allow_registrations" name="allow_registrations" 
                                                           <?php echo $currentSettings['allow_registrations'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="allow_registrations">
                                                        Allow New Registrations
                                                    </label>
                                                </div>
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                           <?php echo $currentSettings['maintenance_mode'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="maintenance_mode">
                                                        <span class="text-warning">Maintenance Mode</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Tools -->
                    <div class="col-lg-4">
                        <!-- Password Change -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye" id="current_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('new_password')">
                                                <i class="fas fa-eye" id="new_password_icon"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye" id="confirm_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="fas fa-key me-1"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Email Test -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-envelope me-2"></i>Email Test
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="test_email">
                                    <div class="mb-3">
                                        <label for="test_email" class="form-label">Test Email Address</label>
                                        <input type="email" class="form-control" id="test_email" name="test_email" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-paper-plane me-1"></i>Send Test Email
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Database Management -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-database me-2"></i>Database Management
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" class="mb-3">
                                    <input type="hidden" name="action" value="backup_database">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-download me-1"></i>Create Backup
                                        </button>
                                    </div>
                                </form>
                                
                                <?php if (!empty($backup_files)): ?>
                                    <h6 class="small text-muted mb-2">Recent Backups</h6>
                                    <div class="backup-list" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach (array_slice($backup_files, 0, 5) as $backup): ?>
                                            <div class="backup-item">
                                                <div>
                                                    <div class="small fw-bold"><?php echo htmlspecialchars($backup['name']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <?php echo date('M j, Y H:i', $backup['date']); ?> 
                                                        (<?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB)
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Confirm maintenance mode toggle
        document.getElementById('maintenance_mode').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('Are you sure you want to enable maintenance mode? This will prevent users from accessing the site.')) {
                    this.checked = false;
                }
            }
        });
        
        // Password visibility toggle function
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                return false;
            }
            
            if (!confirm('Are you sure you want to change your password?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
        
        // Mobile Navigation Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebar = document.getElementById('adminSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function showSidebar() {
                sidebar.classList.add('show');
                sidebarOverlay.classList.add('show');
                mobileNavToggle.classList.add('hidden');
                document.body.style.overflow = 'hidden';
            }
            
            function hideSidebar() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                mobileNavToggle.classList.remove('hidden');
                document.body.style.overflow = '';
            }
            
            mobileNavToggle.addEventListener('click', showSidebar);
            sidebarClose.addEventListener('click', hideSidebar);
            sidebarOverlay.addEventListener('click', hideSidebar);
            
            // Close sidebar when clicking on navigation links on mobile
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 768) {
                        hideSidebar();
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    hideSidebar();
                }
            });
        });
    </script>
</body>
</html>
