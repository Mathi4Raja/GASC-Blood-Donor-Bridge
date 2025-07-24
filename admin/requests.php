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
        
        if ($action === 'update_status') {
            $requestId = intval($_POST['request_id']);
            $newStatus = $_POST['new_status'];
            
            if (!in_array($newStatus, ['Active', 'Fulfilled', 'Expired', 'Cancelled'])) {
                throw new Exception('Invalid status');
            }
            
            $stmt = $db->prepare("UPDATE blood_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('si', $newStatus, $requestId);
            
            if ($stmt->execute()) {
                $success = "Request status updated to {$newStatus}.";
                logActivity($_SESSION['user_id'], 'blood_request_status_updated', "Request ID {$requestId} status changed to {$newStatus}");
                
                // Send email notification to requester
                $requestQuery = $db->prepare("SELECT * FROM blood_requests WHERE id = ?");
                $requestQuery->bind_param('i', $requestId);
                $requestQuery->execute();
                $request = $requestQuery->get_result()->fetch_assoc();
                
                if ($request) {
                    $emailSubject = "Blood Request Status Update - GASC Blood Bridge";
                    $statusMessage = '';
                    
                    switch ($newStatus) {
                        case 'Fulfilled':
                            $statusMessage = "Great news! Your blood request has been fulfilled. Thank you for using our service.";
                            break;
                        case 'Cancelled':
                            $statusMessage = "Your blood request has been cancelled. If you have any questions, please contact us.";
                            break;
                        case 'Expired':
                            $statusMessage = "Your blood request has expired. You can submit a new request if still needed.";
                            break;
                    }
                    
                    if ($statusMessage) {
                        $emailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 20px; text-align: center;'>
                                <h1 style='color: white; margin: 0;'>GASC Blood Bridge</h1>
                            </div>
                            <div style='padding: 30px; background: #f8f9fa;'>
                                <h2>Request Status Update</h2>
                                <p>Dear {$request['requester_name']},</p>
                                <p>{$statusMessage}</p>
                                <div style='background: white; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0;'>
                                    <p><strong>Request ID:</strong> #{$request['id']}</p>
                                    <p><strong>Blood Group:</strong> {$request['blood_group']}</p>
                                    <p><strong>Status:</strong> {$newStatus}</p>
                                </div>
                                <p>Thank you for using GASC Blood Bridge.</p>
                            </div>
                        </div>
                        ";
                        sendEmail($request['requester_email'], $emailSubject, $emailBody);
                    }
                }
            } else {
                $error = "Failed to update request status.";
            }
        }
        
        if ($action === 'delete_request') {
            $requestId = intval($_POST['request_id']);
            
            $stmt = $db->prepare("DELETE FROM blood_requests WHERE id = ?");
            $stmt->bind_param('i', $requestId);
            
            if ($stmt->execute()) {
                $success = "Blood request deleted successfully.";
                logActivity($_SESSION['user_id'], 'blood_request_deleted', "Request ID {$requestId} deleted");
            } else {
                $error = "Failed to delete request.";
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity($_SESSION['user_id'], 'blood_request_management_error', $error);
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$urgencyFilter = $_GET['urgency'] ?? 'all';
$bloodGroupFilter = $_GET['blood_group'] ?? 'all';
$cityFilter = $_GET['city'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ['1=1'];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $whereConditions[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($urgencyFilter !== 'all') {
    $whereConditions[] = 'urgency = ?';
    $params[] = $urgencyFilter;
    $types .= 's';
}

if ($bloodGroupFilter !== 'all') {
    $whereConditions[] = 'blood_group = ?';
    $params[] = $bloodGroupFilter;
    $types .= 's';
}

if (!empty($cityFilter)) {
    $whereConditions[] = 'city LIKE ?';
    $params[] = "%{$cityFilter}%";
    $types .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(requester_name LIKE ? OR requester_email LIKE ? OR details LIKE ?)';
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM blood_requests {$whereClause}";
$countStmt = $db->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get requests
$requestsQuery = "SELECT *, 
                  (SELECT COUNT(*) FROM users u WHERE u.blood_group = blood_requests.blood_group AND u.city = blood_requests.city AND u.is_available = TRUE AND u.is_verified = TRUE AND u.is_active = TRUE AND u.user_type = 'donor') as available_donors_count
                  FROM blood_requests {$whereClause} 
                  ORDER BY 
                    CASE urgency 
                        WHEN 'Critical' THEN 1 
                        WHEN 'Urgent' THEN 2 
                        WHEN 'Normal' THEN 3 
                    END,
                    created_at DESC 
                  LIMIT ? OFFSET ?";

$requestsStmt = $db->prepare($requestsQuery);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$requestsStmt->bind_param($types, ...$params);
$requestsStmt->execute();
$requests = $requestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get blood groups for filter
$bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Get request statistics
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) as count FROM blood_requests")->fetch_assoc()['count'];
$stats['active'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Active'")->fetch_assoc()['count'];
$stats['fulfilled'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Fulfilled'")->fetch_assoc()['count'];
$stats['critical'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE urgency = 'Critical' AND status = 'Active'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .request-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .urgency-critical {
            border-left: 5px solid #dc2626;
        }
        
        .urgency-urgent {
            border-left: 5px solid #f59e0b;
        }
        
        .urgency-normal {
            border-left: 5px solid #10b981;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .blood-group-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border-radius: 15px;
            padding: 4px 12px;
            font-weight: bold;
            font-size: 0.9rem;
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
        
        .action-btn {
            margin: 2px;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .expired-request {
            opacity: 0.7;
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .request-card {
                padding: 15px;
            }
            
            .action-btn {
                width: 100%;
                margin-bottom: 5px;
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
                        <a class="nav-link active" href="requests.php">
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
                        <i class="fas fa-hand-holding-heart text-danger me-2"></i>Blood Requests
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
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
                            <div class="text-muted">Total Requests</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-warning"><?php echo $stats['active']; ?></div>
                            <div class="text-muted">Active Requests</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-success"><?php echo $stats['fulfilled']; ?></div>
                            <div class="text-muted">Fulfilled Requests</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-danger"><?php echo $stats['critical']; ?></div>
                            <div class="text-muted">Critical Active</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Fulfilled" <?php echo $statusFilter === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                    <option value="Expired" <?php echo $statusFilter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Urgency</label>
                                <select name="urgency" class="form-select form-select-sm">
                                    <option value="all" <?php echo $urgencyFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Critical" <?php echo $urgencyFilter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="Urgent" <?php echo $urgencyFilter === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    <option value="Normal" <?php echo $urgencyFilter === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select form-select-sm">
                                    <option value="all">All Groups</option>
                                    <?php foreach ($bloodGroups as $group): ?>
                                        <option value="<?php echo $group; ?>" <?php echo $bloodGroupFilter === $group ? 'selected' : ''; ?>>
                                            <?php echo $group; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control form-control-sm" 
                                       placeholder="City" value="<?php echo htmlspecialchars($cityFilter); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Name, email, details..." value="<?php echo htmlspecialchars($search); ?>">
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
                                <a href="requests.php" class="btn btn-outline-secondary btn-sm">
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
                        of <?php echo $totalRecords; ?> requests
                    </div>
                    <div>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Request pagination">
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
                
                <!-- Requests List -->
                <div class="row">
                    <?php if (empty($requests)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-hand-holding-heart fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No requests found</h5>
                                <p class="text-muted">Try adjusting your filters or search criteria.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <?php 
                            $isExpired = strtotime($request['expires_at']) < time();
                            $urgencyClass = 'urgency-' . strtolower($request['urgency']);
                            ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="request-card <?php echo $urgencyClass; ?> <?php echo $isExpired ? 'expired-request' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($request['requester_name']); ?></h6>
                                            <small class="text-muted">Request #<?php echo $request['id']; ?></small>
                                        </div>
                                        <span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($request['requester_email']); ?>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($request['requester_phone']); ?>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($request['city']); ?>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-vial me-1"></i><?php echo $request['units_needed']; ?> unit(s) needed
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge status-badge <?php 
                                                echo $request['status'] === 'Active' ? 'bg-success' : 
                                                    ($request['status'] === 'Fulfilled' ? 'bg-primary' : 
                                                    ($request['status'] === 'Expired' ? 'bg-secondary' : 'bg-danger')); 
                                            ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                            <span class="badge status-badge <?php 
                                                echo $request['urgency'] === 'Critical' ? 'bg-danger' : 
                                                    ($request['urgency'] === 'Urgent' ? 'bg-warning' : 'bg-info'); 
                                            ?>">
                                                <?php echo $request['urgency']; ?>
                                            </span>
                                            <span class="badge status-badge bg-secondary">
                                                <?php echo $request['available_donors_count']; ?> donors available
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <strong>Details:</strong><br>
                                            <?php echo nl2br(htmlspecialchars(substr($request['details'], 0, 100))); ?>
                                            <?php if (strlen($request['details']) > 100): ?>...<?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            Created: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?><br>
                                            Expires: <?php echo date('M j, Y g:i A', strtotime($request['expires_at'])); ?>
                                            <?php if ($isExpired): ?>
                                                <span class="text-danger">(Expired)</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if ($request['status'] === 'Active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="new_status" value="Fulfilled">
                                                <button type="submit" class="btn btn-success action-btn"
                                                        onclick="return confirm('Mark this request as fulfilled?')">
                                                    <i class="fas fa-check"></i> Mark Fulfilled
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="new_status" value="Cancelled">
                                                <button type="submit" class="btn btn-warning action-btn"
                                                        onclick="return confirm('Cancel this request?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-info action-btn" 
                                                onclick="showRequestDetails(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        
                                        <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_request">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <button type="submit" class="btn btn-danger action-btn"
                                                        onclick="return confirm('Are you sure you want to delete this request? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRequestDetails(request) {
            const modalContent = document.getElementById('requestDetailsContent');
            const statusBadgeClass = request.status === 'Active' ? 'bg-success' : 
                                   (request.status === 'Fulfilled' ? 'bg-primary' : 
                                   (request.status === 'Expired' ? 'bg-secondary' : 'bg-danger'));
            
            const urgencyBadgeClass = request.urgency === 'Critical' ? 'bg-danger' : 
                                    (request.urgency === 'Urgent' ? 'bg-warning' : 'bg-info');
            
            modalContent.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Requester Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${request.requester_name}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${request.requester_email}</td></tr>
                            <tr><td><strong>Phone:</strong></td><td>${request.requester_phone}</td></tr>
                            <tr><td><strong>City:</strong></td><td>${request.city}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Request Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Request ID:</strong></td><td>#${request.id}</td></tr>
                            <tr><td><strong>Blood Group:</strong></td><td><span class="badge bg-danger">${request.blood_group}</span></td></tr>
                            <tr><td><strong>Units Needed:</strong></td><td>${request.units_needed}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge ${statusBadgeClass}">${request.status}</span></td></tr>
                            <tr><td><strong>Urgency:</strong></td><td><span class="badge ${urgencyBadgeClass}">${request.urgency}</span></td></tr>
                            <tr><td><strong>Available Donors:</strong></td><td>${request.available_donors_count}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h6>Additional Details</h6>
                        <p class="border p-3 bg-light">${request.details.replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <strong>Created:</strong> ${new Date(request.created_at).toLocaleString()}
                        </small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            <strong>Expires:</strong> ${new Date(request.expires_at).toLocaleString()}
                        </small>
                    </div>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('requestDetailsModal')).show();
        }
        
        function exportData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'requests.php?' + params.toString();
        }
        
        // Auto-submit form on filter change
        document.querySelectorAll('select[name="status"], select[name="urgency"], select[name="blood_group"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
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
