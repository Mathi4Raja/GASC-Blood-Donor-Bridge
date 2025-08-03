<?php
session_start();

// Simple session-based authentication for requestors
if (!isset($_SESSION['requestor_email'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$db = new Database();
$requestorEmail = $_SESSION['requestor_email'];

// Get requestor's blood requests with filters
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

$whereClause = "WHERE requester_email = ?";
$params = [$requestorEmail];

if ($statusFilter !== 'all') {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql = "SELECT * FROM blood_requests $whereClause ORDER BY $sortBy $sortOrder";
$result = $db->query($sql, $params);
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$statsQuery = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_requests,
    SUM(CASE WHEN status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
    SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) as expired_requests
    FROM blood_requests WHERE requester_email = ?";
$stats = $db->query($statsQuery, [$requestorEmail])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Blood Requests - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .requestor-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 2rem 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #dc2626;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .status-active { border-left-color: #28a745; }
        .status-fulfilled { border-left-color: #17a2b8; }
        .status-expired { border-left-color: #6c757d; }
        .status-cancelled { border-left-color: #dc3545; }
        
        .urgency-critical { color: #dc3545; }
        .urgency-urgent { color: #fd7e14; }
        .urgency-normal { color: #28a745; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="requestor-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">My Blood Requests</h1>
                    <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($requestorEmail); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="../request/blood-request.php" class="btn btn-light me-2">
                        <i class="fas fa-plus me-1"></i>New Request
                    </a>
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                    <div class="text-muted">Total Requests</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['active_requests']; ?></div>
                    <div class="text-muted">Active</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['fulfilled_requests']; ?></div>
                    <div class="text-muted">Fulfilled</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo $stats['expired_requests']; ?></div>
                    <div class="text-muted">Expired</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Fulfilled" <?php echo $statusFilter === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                            <option value="Expired" <?php echo $statusFilter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort by</label>
                        <select name="sort" class="form-select">
                            <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                            <option value="urgency" <?php echo $sortBy === 'urgency' ? 'selected' : ''; ?>>Urgency</option>
                            <option value="blood_group" <?php echo $sortBy === 'blood_group' ? 'selected' : ''; ?>>Blood Group</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order</label>
                        <select name="order" class="form-select">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-danger d-block">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Requests List -->
        <div class="row">
            <?php if (empty($requests)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No blood requests found</h4>
                        <p class="text-muted">You haven't made any blood requests yet.</p>
                        <a href="../request/blood-request.php" class="btn btn-danger">
                            <i class="fas fa-plus me-1"></i>Create Your First Request
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="request-card status-<?php echo strtolower($request['status']); ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-danger">Request #<?php echo $request['id']; ?></span>
                                <span class="badge bg-<?php echo $request['status'] === 'Active' ? 'success' : ($request['status'] === 'Fulfilled' ? 'info' : 'secondary'); ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                            </div>
                            
                            <h6 class="mb-2">
                                <i class="fas fa-tint text-danger me-1"></i>
                                <?php echo $request['blood_group']; ?> Blood
                            </h6>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <span class="urgency-<?php echo strtolower($request['urgency']); ?>">
                                        <?php echo $request['urgency']; ?>
                                    </span>
                                </small>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo $request['city']; ?>
                                </small>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-vials me-1"></i><?php echo $request['units_needed']; ?> unit(s) needed
                                </small>
                            </div>
                            
                            <p class="text-muted small mb-2">
                                <?php echo htmlspecialchars(substr($request['details'], 0, 100)); ?>
                                <?php if (strlen($request['details']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </small>
                                <div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($request['status'] === 'Active'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($request['status'] === 'Active'): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>
                                        Expires: <?php echo date('M d, Y H:i', strtotime($request['expires_at'])); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestModalContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRequest(requestId) {
            // Load request details via AJAX
            fetch(`get-request-details.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('requestModalContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('requestModal')).show();
                    } else {
                        alert('Error loading request details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading request details');
                });
        }

        function cancelRequest(requestId) {
            if (confirm('Are you sure you want to cancel this blood request?')) {
                fetch('cancel-request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Request cancelled successfully');
                        window.location.reload();
                    } else {
                        alert('Error cancelling request: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error cancelling request');
                });
            }
        }
    </script>
</body>
</html>
