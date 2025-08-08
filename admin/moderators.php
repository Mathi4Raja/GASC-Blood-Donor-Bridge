<?php
require_once '../config/database.php';

// Check if user is logged in as admin
requireRole(['admin']);

$db = new Database();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_moderator') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            $message = 'All fields are required.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'danger';
        } else {
            // Check if email already exists
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                $message = 'A user with this email already exists.';
                $messageType = 'danger';
            } else {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $userType = 'moderator';
                    $isActive = 1;
                    $isVerified = 1;
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, password_hash, user_type, is_active, is_verified, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param('ssssii', $name, $email, $hashedPassword, $userType, $isActive, $isVerified);
                    $stmt->execute();
                    
                    logActivity($_SESSION['user_id'], 'add_moderator', "Added new moderator: $name ($email)");
                    $message = 'Moderator added successfully!';
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $message = 'Error adding moderator: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
        
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $currentStatus = (int)($_POST['current_status'] ?? 0);
        $newStatus = $currentStatus ? 0 : 1;
        
        try {
            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND user_type = 'moderator'");
            $stmt->bind_param('ii', $newStatus, $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $statusText = $newStatus ? 'activated' : 'deactivated';
                logActivity($_SESSION['user_id'], 'toggle_moderator_status', "Moderator $statusText (ID: $userId)");
                $message = "Moderator $statusText successfully!";
                $messageType = 'success';
            } else {
                $message = 'Failed to update moderator status.';
                $messageType = 'danger';
            }
            
        } catch (Exception $e) {
            $message = 'Error updating status: ' . $e->getMessage();
            $messageType = 'danger';
        }
        
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        
        if (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'danger';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND user_type = 'moderator'");
                $stmt->bind_param('si', $hashedPassword, $userId);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    logActivity($_SESSION['user_id'], 'reset_moderator_password', "Reset password for moderator (ID: $userId)");
                    $message = 'Password reset successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to reset password.';
                    $messageType = 'danger';
                }
                
            } catch (Exception $e) {
                $message = 'Error resetting password: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
    } elseif ($action === 'delete_moderator') {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        try {
            // Get moderator info before deletion
            $infoStmt = $db->prepare("SELECT name, email FROM users WHERE id = ? AND user_type = 'moderator'");
            $infoStmt->bind_param('i', $userId);
            $infoStmt->execute();
            $moderatorInfo = $infoStmt->get_result()->fetch_assoc();
            
            if ($moderatorInfo) {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND user_type = 'moderator'");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    logActivity($_SESSION['user_id'], 'delete_moderator', "Deleted moderator: {$moderatorInfo['name']} ({$moderatorInfo['email']})");
                    $message = 'Moderator deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete moderator.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Moderator not found.';
                $messageType = 'danger';
            }
            
        } catch (Exception $e) {
            $message = 'Error deleting moderator: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = trim($_GET['search'] ?? '');
$searchCondition = '';
$searchParam = '';

if ($search) {
    $searchCondition = "AND (name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users WHERE user_type = 'moderator' $searchCondition";
if ($search) {
    $countStmt = $db->prepare($countQuery);
    $countStmt->bind_param('ss', $searchParam, $searchParam);
    $countStmt->execute();
    $totalModerators = $countStmt->get_result()->fetch_assoc()['total'];
} else {
    $totalModerators = $db->query($countQuery)->fetch_assoc()['total'];
}

$totalPages = ceil($totalModerators / $perPage);

// Get moderators
$query = "
    SELECT id, name, email, is_active, created_at,
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = users.id) as activity_count,
           (SELECT MAX(created_at) FROM activity_logs WHERE user_id = users.id) as last_activity
    FROM users 
    WHERE user_type = 'moderator' $searchCondition
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
";

if ($search) {
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssii', $searchParam, $searchParam, $perPage, $offset);
} else {
    $stmt = $db->prepare($query);
    $stmt->bind_param('ii', $perPage, $offset);
}

$stmt->execute();
$moderators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get system statistics
$stats = [];
$stats['total_moderators'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'moderator'")->fetch_assoc()['count'];
$stats['active_moderators'] = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'moderator' AND is_active = 1")->fetch_assoc()['count'];
$stats['recent_activity'] = $db->query("
    SELECT COUNT(*) as count 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    WHERE u.user_type = 'moderator' AND DATE(al.created_at) = CURDATE()
")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Moderators - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .moderator-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .moderator-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .activity-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .activity-indicator.active {
            background-color: #28a745;
        }
        
        .activity-indicator.inactive {
            background-color: #6c757d;
        }
        
        .rounded-pill {
            border-radius: 50rem !important;
            transition: all 0.3s ease;
        }
        
        .rounded-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            .moderator-card {
                margin-bottom: 10px;
            }
            
            .btn-group-sm .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .d-flex.gap-2 .btn {
                flex: 1;
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
                        <a class="nav-link active" href="moderators.php">
                            <i class="fas fa-user-cog me-2"></i>Manage Moderators
                        </a>
                        <a class="nav-link" href="settings.php">
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
                        <i class="fas fa-user-cog text-danger me-2"></i>Manage Moderators
                    </h1>
                    <div class="d-flex gap-2 mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModeratorModal">
                            <i class="fas fa-plus me-2"></i>Add Moderator
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="h3 text-primary"><?php echo $stats['total_moderators']; ?></div>
                            <div class="text-muted">Total Moderators</div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="h3 text-success"><?php echo $stats['active_moderators']; ?></div>
                            <div class="text-muted">Active Moderators</div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="h3 text-info"><?php echo $stats['recent_activity']; ?></div>
                            <div class="text-muted">Activities Today</div>
                        </div>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" placeholder="Search by name or email..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Moderators List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Moderators (<?php echo $totalModerators; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($moderators)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-cog fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No moderators found</h5>
                                <p class="text-muted">
                                    <?php echo $search ? 'Try adjusting your search criteria.' : 'Start by adding your first moderator.'; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($moderators as $moderator): ?>
                                <div class="moderator-card p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($moderator['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($moderator['email']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="mb-1">
                                                <span class="activity-indicator <?php echo $moderator['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                                <span class="status-badge badge <?php echo $moderator['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $moderator['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Joined: <?php echo date('M j, Y', strtotime($moderator['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h6 mb-0"><?php echo $moderator['activity_count']; ?></div>
                                                <small class="text-muted">Activities</small>
                                            </div>
                                            <?php if ($moderator['last_activity']): ?>
                                                <small class="text-muted d-block text-center">
                                                    Last: <?php echo date('M j', strtotime($moderator['last_activity'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <div class="btn-group-vertical w-100" role="group">
                                                <!-- Toggle Status -->
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $moderator['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $moderator['is_active']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $moderator['is_active'] ? 'btn-warning' : 'btn-success'; ?> w-100 mb-1">
                                                        <i class="fas <?php echo $moderator['is_active'] ? 'fa-pause' : 'fa-play'; ?> me-1"></i>
                                                        <?php echo $moderator['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                
                                                <!-- Reset Password -->
                                                <button type="button" class="btn btn-sm btn-info w-100 mb-1" 
                                                        onclick="showResetPasswordModal(<?php echo $moderator['id']; ?>, '<?php echo htmlspecialchars($moderator['name']); ?>')">
                                                    <i class="fas fa-key me-1"></i>Reset Password
                                                </button>
                                                
                                                <!-- Delete -->
                                                <button type="button" class="btn btn-sm btn-danger w-100" 
                                                        onclick="showDeleteModal(<?php echo $moderator['id']; ?>, '<?php echo htmlspecialchars($moderator['name']); ?>')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Moderators pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Moderator Modal -->
    <div class="modal fade" id="addModeratorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Moderator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_moderator">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Moderator</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        
                        <p>Reset password for <strong id="reset_user_name"></strong>?</p>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_moderator">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        
                        <p>Are you sure you want to delete <strong id="delete_user_name"></strong>?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Moderator</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showResetPasswordModal(userId, userName) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').textContent = userName;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }
        
        function showDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Mobile Navigation Toggle
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
            }        mobileNavToggle.addEventListener('click', showSidebar);
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
    </script>
</body>
</html>
