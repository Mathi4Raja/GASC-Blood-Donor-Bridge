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
                'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
                'session_timeout_minutes' => (int)($_POST['session_timeout_minutes'] ?? 30),
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                'auto_expire_requests' => isset($_POST['auto_expire_requests']) ? 1 : 0,
                'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0,
                'allow_registrations' => isset($_POST['allow_registrations']) ? 1 : 0,
                'auto_backup_enabled' => isset($_POST['auto_backup_enabled']) ? 1 : 0
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
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            
            // Validate date range if provided
            $dateRange = null;
            if ($startDate && $endDate) {
                $start = DateTime::createFromFormat('Y-m-d', $startDate);
                $end = DateTime::createFromFormat('Y-m-d', $endDate);
                
                if (!$start || !$end) {
                    throw new Exception('Invalid date format provided');
                }
                
                if ($start > $end) {
                    throw new Exception('Start date cannot be after end date');
                }
                
                $dateRange = [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'start_formatted' => $start->format('M j, Y'),
                    'end_formatted' => $end->format('M j, Y'),
                    'duration_years' => $start->diff($end)->format('%y'),
                    'duration_months' => $start->diff($end)->format('%m'),
                    'duration_days' => $start->diff($end)->days
                ];
            }
            
            $result = createDatabaseBackup('manual', $dateRange);
            
            if ($result['success']) {
                $backupInfo = $result['filename'] . ' (' . $result['size_mb'] . ' MB)';
                if ($dateRange) {
                    $backupInfo .= ' [Period: ' . $dateRange['start_formatted'] . ' to ' . $dateRange['end_formatted'] . ']';
                }
                logActivity($_SESSION['user_id'], 'backup_database', 'Manual database backup created: ' . $backupInfo);
                $message = 'Manual database backup created successfully: ' . $backupInfo;
                $messageType = 'success';
            } else {
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            error_log("Manual backup exception: " . $e->getMessage());
            $message = 'Error creating manual backup: ' . $e->getMessage();
            $messageType = 'danger';
        }
        
    } elseif ($action === 'auto_backup_now') {
        try {
            $result = performAutomaticDatabaseBackup();
            
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            error_log("Force automatic backup exception: " . $e->getMessage());
            $message = 'Error performing automatic backup: ' . $e->getMessage();
            $messageType = 'danger';
        }
        
    } elseif ($action === 'test_email') {
        $test_email = $_POST['test_email'] ?? '';
        
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            try {
                $subject = 'GASC Blood Bridge - Email Test';
                $body = 'This is a test email from GASC Blood Bridge system. If you receive this, email configuration is working correctly.';
                
                $emailResult = sendEmail($test_email, $subject, $body);
                
                // Handle both old boolean return and new array return
                $emailSent = false;
                $emailLogged = false;
                
                if (is_array($emailResult)) {
                    $emailSent = $emailResult['email_sent'] ?? false;
                    $emailLogged = $emailResult['logged'] ?? false;
                } else {
                    $emailSent = $emailResult;
                }
                
                if ($emailSent) {
                    $message = 'Test email sent successfully to ' . $test_email;
                    $messageType = 'success';
                } elseif ($emailLogged) {
                    $message = 'Email notifications are disabled. Test email details have been logged instead of sent.';
                    $messageType = 'warning';
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
    'max_login_attempts' => 5,
    'session_timeout_minutes' => 30,
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'auto_expire_requests' => 1,
    'require_email_verification' => 1,
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

// Get recent backups (4 manual + 1 automatic = 5 total)
$backup_files = [];
$manual_backups = [];
$auto_backups = [];

if (is_dir('../database/')) {
    $files = glob('../database/backup_*.sql');
    foreach ($files as $file) {
        $backup_info = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file),
            'type' => (strpos(basename($file), '_automatic_') !== false) ? 'auto' : 'manual'
        ];
        
        if ($backup_info['type'] === 'auto') {
            $auto_backups[] = $backup_info;
        } else {
            $manual_backups[] = $backup_info;
        }
    }
    
    // Sort by date (newest first)
    usort($auto_backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    usort($manual_backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    
    // Take 1 most recent automatic backup and 4 most recent manual backups
    $selected_auto = array_slice($auto_backups, 0, 1);
    $selected_manual = array_slice($manual_backups, 0, 4);
    
    // Combine and sort by date again
    $backup_files = array_merge($selected_auto, $selected_manual);
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
            margin-right: 0.75rem; /* spacing between toggle and icon/label */
        }
        
        .system-features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            background: rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        
        .feature-item:hover {
            background: rgba(0,0,0,0.05);
            transform: translateY(-1px);
        }
        
        .feature-item .form-check {
            margin-bottom: 0;
            width: 100%;
        }
        
        .feature-item .form-check-label {
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .settings-card {
                margin-bottom: 15px;
            }
            
            .settings-section {
                padding: 15px 0;
            }
            
            .system-features-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
        
        /* Custom Popover Styles */
        .backup-date-popover {
            max-width: 300px;
        }
        
        .backup-date-popover .popover-body {
            padding: 0;
        }
        
        .backup-date-range-form {
            border-radius: 8px;
        }
        
        .backup-date-range-form .form-control-sm {
            border-radius: 6px;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .backup-date-range-form .form-control-sm:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .backup-date-range-form .form-label {
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        
        .backup-date-range-form .btn-sm {
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }
        
        .backup-date-range-form .btn-primary {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
        }
        
        .backup-date-range-form .btn-primary:hover {
            background: linear-gradient(135deg, #0b5ed7, #0a58ca);
            transform: translateY(-1px);
        }
        
        .backup-date-range-form .btn-outline-secondary:hover {
            transform: translateY(-1px);
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
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="max_requests_per_user" class="form-label">Max Requests per User per Day</label>
                                                    <input type="number" class="form-control" id="max_requests_per_user" name="max_requests_per_user" 
                                                           value="<?php echo $currentSettings['max_requests_per_user']; ?>" min="1" max="20">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6 class="text-primary">Security & Session Settings</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                                    <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                           value="<?php echo $currentSettings['max_login_attempts']; ?>" min="3" max="10">
                                                    <div class="form-text">Failed attempts before lockout</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="session_timeout_minutes" class="form-label">Session Timeout (Minutes)</label>
                                                    <input type="number" class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" 
                                                           value="<?php echo $currentSettings['session_timeout_minutes']; ?>" min="5" max="480">
                                                    <div class="form-text">Automatic logout after inactivity</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6 class="text-primary mb-4">
                                            <i class="fas fa-cogs me-2"></i>System Features
                                        </h6>
                                        <div class="system-features-grid">
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications"
                                                           <?php echo $currentSettings['email_notifications'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_notifications">
                                                        <i class="fas fa-envelope me-2 text-info"></i>Email Notifications
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications"
                                                           <?php echo $currentSettings['sms_notifications'] ? 'checked' : ''; ?> disabled>
                                                    <label class="form-check-label" for="sms_notifications">
                                                        <i class="fas fa-sms me-2 text-secondary"></i>SMS Notifications
                                                        <small class="text-muted d-block">(In Development)</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification"
                                                           <?php echo $currentSettings['require_email_verification'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="require_email_verification">
                                                        <i class="fas fa-shield-alt me-2 text-success"></i>Require Email Verification
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="auto_expire_requests" name="auto_expire_requests"
                                                           <?php echo $currentSettings['auto_expire_requests'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_expire_requests">
                                                        <i class="fas fa-clock me-2 text-warning"></i>Auto-expire Requests
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="allow_registrations" name="allow_registrations"
                                                           <?php echo $currentSettings['allow_registrations'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="allow_registrations">
                                                        <i class="fas fa-user-plus me-2 text-primary"></i>Allow New Registrations
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled"
                                                           <?php echo SystemSettings::get('auto_backup_enabled', 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_backup_enabled">
                                                        <i class="fas fa-database me-2 text-info"></i>Automatic Backup
                                                        <small class="text-muted d-block">Every <?php echo SystemSettings::get('auto_backup_interval_years', 3); ?> years</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>                                    <div class="d-grid">
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
                                <!-- Automatic Backup Status -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="small text-muted mb-2">Automatic Backup Status</h6>
                                        <div class="mb-2">
                                            <span class="badge <?php echo SystemSettings::get('auto_backup_enabled') ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo SystemSettings::get('auto_backup_enabled') ? 'Enabled' : 'Disabled'; ?>
                                            </span>
                                            <small class="text-muted ms-2">
                                                (Every <?php echo SystemSettings::get('auto_backup_interval_years', 3); ?> years)
                                            </small>
                                        </div>
                                        <div class="small text-muted">
                                            <strong>Last Auto Backup:</strong><br>
                                            <?php 
                                            $lastBackup = SystemSettings::get('last_automatic_backup');
                                            echo $lastBackup ? date('M j, Y H:i', strtotime($lastBackup)) : 'Never';
                                            ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <strong>Next Auto Backup:</strong><br>
                                            <?php echo getNextAutomaticBackupDate(); ?>
                                        </div>
                                        <?php if (isAutomaticBackupDue()): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Automatic Backup Due!
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="small text-muted mb-2">Backup Actions</h6>
                                        <!-- Manual Backup with Date Range -->
                                        <div class="mb-2">
                                            <button type="button" class="btn btn-primary btn-sm w-100" 
                                                    data-bs-toggle="popover" 
                                                    data-bs-placement="bottom" 
                                                    data-bs-html="true" 
                                                    data-bs-content="" 
                                                    id="manualBackupBtn">
                                                <i class="fas fa-download me-1"></i>Create Manual Backup
                                            </button>
                                        </div>
                                        <?php if (isAutomaticBackupDue()): ?>
                                        <form method="POST" action="" class="mb-2">
                                            <input type="hidden" name="action" value="auto_backup_now">
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-clock me-1"></i>Run Automatic Backup Now
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <?php if (!empty($backup_files)): ?>
                                    <h6 class="small text-muted mb-2">Recent Backups (4 Manual + 1 Auto)</h6>
                                    <div class="backup-list" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($backup_files as $backup): ?>
                                            <div class="backup-item d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <div>
                                                    <div class="small fw-bold">
                                                        <?php 
                                                        $name = htmlspecialchars($backup['name']);
                                                        // Add type indicator based on stored type
                                                        if ($backup['type'] === 'auto') {
                                                            echo '<i class="fas fa-clock text-success me-1" title="Automatic Backup"></i>';
                                                        } else {
                                                            echo '<i class="fas fa-user text-primary me-1" title="Manual Backup"></i>';
                                                        }
                                                        echo $name;
                                                        ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <?php echo date('M j, Y H:i', $backup['date']); ?> 
                                                        (<?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB)
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($backup['type'] === 'auto'): ?>
                                                        <span class="badge bg-success">Auto</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Manual</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-database fa-2x mb-2"></i>
                                        <p class="mb-0">No backups found</p>
                                        <small>Create your first backup above<br>
                                        <em>Recent backups will show 4 manual + 1 automatic</em></small>
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
    <script src="../assets/js/loading-manager.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
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

        // Manual Backup Date Range Popover
        let dateRangePopover; // Declare popover variable in global scope
        
        document.addEventListener('DOMContentLoaded', function() {
            const manualBackupBtn = document.getElementById('manualBackupBtn');
            const today = new Date().toISOString().split('T')[0];
            const oneYearAgo = new Date(Date.now() - 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            // Popover content
            const popoverContent = `
                <div class="backup-date-range-form" style="width: 280px; padding: 4px;">
                    <form method="POST" action="" id="dateRangeBackupForm">
                        <input type="hidden" name="action" value="backup_database">
                        
                        <div class="text-center mb-3">
                            <h6 class="mb-0 fw-bold text-primary">
                                <i class="fas fa-calendar-alt me-2"></i>Backup Date Range
                            </h6>
                            <small class="text-muted">Select the period to backup</small>
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="start_date" class="form-label small fw-semibold mb-1">From</label>
                                <input type="date" class="form-control form-control-sm" 
                                       id="start_date" name="start_date" 
                                       value="${oneYearAgo}" 
                                       max="${today}" required>
                            </div>
                            <div class="col-6">
                                <label for="end_date" class="form-label small fw-semibold mb-1">To</label>
                                <input type="date" class="form-control form-control-sm" 
                                       id="end_date" name="end_date" 
                                       value="${today}" 
                                       max="${today}" required>
                            </div>
                        </div>
                        
                        <div class="mb-3 text-center" style="min-height: 20px;">
                            <div id="dateRangeInfo" class="small"></div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-download me-2"></i>Create Backup
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelBackupBtn">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            // Initialize popover
            dateRangePopover = new bootstrap.Popover(manualBackupBtn, {
                content: popoverContent,
                html: true,
                placement: 'left',
                trigger: 'click',
                sanitize: false,
                customClass: 'backup-date-popover'
            });
            
            // Handle popover shown event
            manualBackupBtn.addEventListener('shown.bs.popover', function() {
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                const dateRangeInfo = document.getElementById('dateRangeInfo');
                const cancelBtn = document.getElementById('cancelBackupBtn');
                
                // Add cancel button event listener
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        dateRangePopover.hide();
                    });
                }
                
                function updateDateRangeInfo() {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    
                    if (startDate && endDate && startDate <= endDate) {
                        const diffTime = Math.abs(endDate - startDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        
                        let durationText = '';
                        if (diffDays === 0) {
                            durationText = 'Same day';
                        } else if (diffDays === 1) {
                            durationText = '1 day';
                        } else if (diffDays < 30) {
                            durationText = `${diffDays} days`;
                        } else if (diffDays < 365) {
                            const months = Math.floor(diffDays / 30);
                            const remainingDays = diffDays % 30;
                            durationText = months === 1 ? '1 month' : `${months} months`;
                            if (remainingDays > 0) durationText += ` ${remainingDays}d`;
                        } else {
                            const years = Math.floor(diffDays / 365);
                            const remainingDays = diffDays % 365;
                            const months = Math.floor(remainingDays / 30);
                            durationText = years === 1 ? '1 year' : `${years} years`;
                            if (months > 0) durationText += ` ${months}m`;
                        }
                        
                        dateRangeInfo.innerHTML = `
                            <span class="badge bg-light text-dark border">
                                <i class="fas fa-clock me-1"></i>${durationText}
                            </span>
                        `;
                    } else if (startDate > endDate) {
                        dateRangeInfo.innerHTML = `
                            <span class="badge bg-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i>Invalid range
                            </span>
                        `;
                    } else {
                        dateRangeInfo.innerHTML = '';
                    }
                }
                
                startDateInput.addEventListener('change', updateDateRangeInfo);
                endDateInput.addEventListener('change', updateDateRangeInfo);
                updateDateRangeInfo(); // Initial calculation
                
                // Set max date for start date when end date changes
                endDateInput.addEventListener('change', function() {
                    startDateInput.max = endDateInput.value;
                });
                
                // Set min date for end date when start date changes
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = startDateInput.value;
                });
            });
            
            // Global function to hide popover
            window.hidePopover = function() {
                if (dateRangePopover) {
                    dateRangePopover.hide();
                }
            };
        });
    </script>
</body>
</html>
