<?php
require_once '../config/database.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

$db = new Database();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'clear_logs') {
            if ($_SESSION['user_type'] !== 'admin') {
                throw new Exception('Only administrators can clear logs.');
            }
            
            $olderThan = $_POST['older_than'] ?? '30';
            $olderThanDays = intval($olderThan);
            
            if ($olderThanDays < 1) {
                throw new Exception('Invalid number of days.');
            }
            
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->bind_param('i', $olderThanDays);
            
            if ($stmt->execute()) {
                $deletedRows = $stmt->affected_rows;
                $success = "Successfully cleared {$deletedRows} log entries older than {$olderThanDays} days.";
                logActivity($_SESSION['user_id'], 'logs_cleared', "Cleared {$deletedRows} log entries older than {$olderThanDays} days");
            } else {
                $error = "Failed to clear logs.";
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity($_SESSION['user_id'], 'logs_management_error', $error);
    }
}

// Get filters
$actionFilter = $_GET['action_filter'] ?? 'all';
$userFilter = $_GET['user_filter'] ?? 'all';
$dateFilter = $_GET['date_filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ['1=1'];
$params = [];
$types = '';

if ($actionFilter !== 'all') {
    $whereConditions[] = 'action LIKE ?';
    $params[] = "%{$actionFilter}%";
    $types .= 's';
}

if ($userFilter !== 'all') {
    if ($userFilter === 'system') {
        $whereConditions[] = 'user_id IS NULL';
    } else {
        $whereConditions[] = 'user_id IS NOT NULL';
    }
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $whereConditions[] = 'DATE(created_at) = CURDATE()';
            break;
        case 'yesterday':
            $whereConditions[] = 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
            break;
        case 'week':
            $whereConditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $whereConditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
    }
}

if (!empty($search)) {
    $whereConditions[] = '(action LIKE ? OR details LIKE ? OR ip_address LIKE ?)';
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
$countStmt = $db->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get logs with user information
$logsQuery = "SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email,
                u.user_type
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              {$whereClause}
              ORDER BY al.created_at DESC 
              LIMIT ? OFFSET ?";

$logsStmt = $db->prepare($logsQuery);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$logsStmt->bind_param($types, ...$params);
$logsStmt->execute();
$logs = $logsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'activity_logs_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'ID', 'User Name', 'User Email', 'User Type', 'Action', 
        'Description', 'IP Address', 'User Agent', 'Created At'
    ];
    fputcsv($output, $headers);
    
    // Get all logs for export (without pagination)
    $exportQuery = "SELECT 
                      al.*,
                      u.name as user_name,
                      u.email as user_email,
                      u.user_type
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    {$whereClause}
                    ORDER BY al.created_at DESC";
    
    $exportStmt = $db->prepare($exportQuery);
    if (!empty($params)) {
        // Remove the limit and offset parameters for export
        $exportParams = array_slice($params, 0, -2);
        $exportTypes = substr($types, 0, -2);
        $exportStmt->bind_param($exportTypes, ...$exportParams);
    }
    $exportStmt->execute();
    $exportLogs = $exportStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Write data rows
    foreach ($exportLogs as $log) {
        $row = [
            $log['id'],
            $log['user_name'] ?? 'System',
            $log['user_email'] ?? 'N/A',
            $log['user_type'] ?? 'System',
            $log['action'],
            $log['description'],
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['created_at']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export activity
    logActivity($_SESSION['user_id'], 'logs_exported', "Exported " . count($exportLogs) . " activity logs to CSV");
    
    exit;
}

// Get action types for filter
$actionTypes = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC);

// Get log statistics
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$stats['today'] = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$stats['week'] = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
$stats['month'] = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get most active users (this month)
$activeUsers = $db->query("
    SELECT 
        u.name,
        u.user_type,
        COUNT(*) as activity_count
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY al.user_id
    ORDER BY activity_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent actions summary
$recentActions = $db->query("
    SELECT 
        action,
        COUNT(*) as count
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY action
    ORDER BY count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .log-entry {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            transition: background-color 0.2s;
        }
        
        .log-entry:hover {
            background: #f8f9fa;
        }
        
        .log-system {
            border-left: 4px solid #6c757d;
        }
        
        .log-admin {
            border-left: 4px solid #dc2626;
        }
        
        .log-moderator {
            border-left: 4px solid #f59e0b;
        }
        
        .log-donor {
            border-left: 4px solid #10b981;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .action-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .ip-address {
            font-family: monospace;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .log-entry {
                padding: 10px;
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
                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                        <a class="nav-link" href="moderators.php">
                            <i class="fas fa-user-cog me-2"></i>Manage Moderators
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link active" href="logs.php">
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
                        <i class="fas fa-clipboard-list text-danger me-2"></i>Activity Logs
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-success" onclick="exportData()">
                                <i class="fas fa-download me-1"></i>Export CSV
                            </button>
                            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                                    <i class="fas fa-trash me-1"></i>Clear Old Logs
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-primary"><?php echo $stats['total']; ?></div>
                            <div class="text-muted">Total Logs</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-success"><?php echo $stats['today']; ?></div>
                            <div class="text-muted">Today's Activities</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-warning"><?php echo $stats['week']; ?></div>
                            <div class="text-muted">This Week</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-info"><?php echo $stats['month']; ?></div>
                            <div class="text-muted">This Month</div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Most Active Users (30 days)</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($activeUsers)): ?>
                                    <p class="text-muted text-center">No activity data available</p>
                                <?php else: ?>
                                    <?php foreach ($activeUsers as $user): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                <span class="badge bg-secondary ms-2"><?php echo ucfirst($user['user_type']); ?></span>
                                            </div>
                                            <span class="text-muted"><?php echo $user['activity_count']; ?> actions</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Recent Actions (24 hours)</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActions)): ?>
                                    <p class="text-muted text-center">No recent actions</p>
                                <?php else: ?>
                                    <?php foreach ($recentActions as $action): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?php echo ucwords(str_replace('_', ' ', $action['action'])); ?></span>
                                            <span class="badge bg-primary"><?php echo $action['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">Action Type</label>
                                <select name="action_filter" class="form-select form-select-sm">
                                    <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                    <?php foreach ($actionTypes as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                                <?php echo $actionFilter === $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo ucwords(str_replace('_', ' ', $action['action'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">User Type</label>
                                <select name="user_filter" class="form-select form-select-sm">
                                    <option value="all" <?php echo $userFilter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="system" <?php echo $userFilter === 'system' ? 'selected' : ''; ?>>System</option>
                                    <option value="users" <?php echo $userFilter === 'users' ? 'selected' : ''; ?>>Users</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Time Period</label>
                                <select name="date_filter" class="form-select form-select-sm">
                                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                    <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Action, details, IP address..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter me-1"></i>Apply Filters
                                </button>
                                <a href="logs.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Results -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> 
                        of <?php echo $totalRecords; ?> log entries
                    </div>
                    <div>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Log pagination">
                                <ul class="pagination pagination-sm">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Logs List -->
                <div class="logs-container">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No log entries found</h5>
                            <p class="text-muted">Try adjusting your filters or search criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php 
                            $userTypeClass = 'log-system';
                            if ($log['user_type']) {
                                $userTypeClass = 'log-' . $log['user_type'];
                            }
                            ?>
                            <div class="log-entry <?php echo $userTypeClass; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="action-badge badge bg-primary me-2">
                                                <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i:s A', strtotime($log['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <?php if ($log['user_name']): ?>
                                                <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                <span class="badge bg-secondary ms-1"><?php echo ucfirst($log['user_type']); ?></span>
                                                <small class="text-muted">(<?php echo htmlspecialchars($log['user_email']); ?>)</small>
                                            <?php else: ?>
                                                <strong>System</strong>
                                                <span class="badge bg-secondary ms-1">Automated</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($log['details']): ?>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($log['details']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-end ms-3">
                                        <?php if ($log['ip_address']): ?>
                                            <div class="ip-address mb-1">
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            ID: <?php echo $log['id']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clear Logs Modal -->
    <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <div class="modal fade" id="clearLogsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Clear Old Logs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="clear_logs">
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This action cannot be undone. Log entries will be permanently deleted.
                            </div>
                            
                            <div class="mb-3">
                                <label for="olderThan" class="form-label">Delete logs older than:</label>
                                <select class="form-select" name="older_than" id="olderThan" required>
                                    <option value="7">7 days</option>
                                    <option value="30" selected>30 days</option>
                                    <option value="60">60 days</option>
                                    <option value="90">90 days</option>
                                    <option value="180">180 days</option>
                                    <option value="365">1 year</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmClear" required>
                                <label class="form-check-label" for="confirmClear">
                                    I understand that this action cannot be undone
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Clear Logs</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'logs.php?' + params.toString();
        }
        
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="action_filter"], select[name="user_filter"], select[name="date_filter"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            if (document.querySelector('select[name="date_filter"]').value === 'today') {
                location.reload();
            }
        }, 30000);
        
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
