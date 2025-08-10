<?php
require_once '../config/database.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$bloodRequests = [];
$totalRequests = 0;
$donor = null;

// Get filter parameters
$bloodGroupFilter = $_GET['blood_group'] ?? '';
$urgencyFilter = $_GET['urgency'] ?? '';
$cityFilter = $_GET['city'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $db = new Database();
    
    // Get donor information to filter by their blood group
    $donorSQL = "SELECT blood_group, city FROM users WHERE id = ? AND user_type = 'donor'";
    $donorResult = $db->query($donorSQL, [$_SESSION['user_id']]);
    $donor = $donorResult->fetch_assoc();
    
    if (!$donor) {
        throw new Exception('Donor information not found');
    }
    
    // Build the WHERE clause - always filter by donor's exact blood group
    $whereConditions = [
        "status = 'Active'",
        "blood_group = ?"  // Only show requests matching donor's exact blood group
    ];
    $params = [$donor['blood_group']];
    
    // Apply additional filters if provided
    if (!empty($urgencyFilter)) {
        $whereConditions[] = "urgency = ?";
        $params[] = $urgencyFilter;
    }
    
    if (!empty($cityFilter)) {
        $whereConditions[] = "city LIKE ?";
        $params[] = "%$cityFilter%";
    } else {
        // Default to donor's city if no city filter is specified
        $whereConditions[] = "city = ?";
        $params[] = $donor['city'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count for pagination
    $countSQL = "SELECT COUNT(*) as total FROM blood_requests WHERE $whereClause";
    $countResult = $db->query($countSQL, $params);
    $totalRequests = $countResult->fetch_assoc()['total'];
    
    // Get filtered requests with pagination
    $requestsSQL = "SELECT * FROM blood_requests 
                    WHERE $whereClause 
                    ORDER BY urgency = 'Critical' DESC, urgency = 'Urgent' DESC, created_at DESC 
                    LIMIT ? OFFSET ?";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $requestsResult = $db->query($requestsSQL, $allParams);
    $bloodRequests = $requestsResult->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'requests_filter_error', $error);
}

// Calculate pagination
$totalPages = ceil($totalRequests / $limit);
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
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        .urgency-critical { border-left-color: #dc2626; }
        .urgency-urgent { border-left-color: #f59e0b; }
        .urgency-normal { border-left-color: #10b981; }
        
        .stats-banner {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .pagination .page-link {
            color: #dc2626;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
        }
    </style>
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
            .requests-header {
                padding-top: 60px;
            }
            
            .container.mt-4 {
                margin-top: 1rem !important;
                padding-top: 20px;
            }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>
    
    <!-- Mobile header with sidebar toggle -->
    <div class="mobile-header d-lg-none">
        <div class="d-flex justify-content-between align-items-center">
            <button class="sidebar-toggle btn btn-primary">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0">Blood Requests</h5>
            <div></div>
        </div>
    </div>
    
    <div class="donor-main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-hand-holding-heart me-2"></i>Blood Requests</h2>
                <p class="text-muted mb-0">Find and respond to blood donation requests</p>
                
                <!-- Stats Banner -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="bg-danger text-white rounded p-3 text-center">
                            <h4 class="mb-0"><?php echo $totalRequests; ?></h4>
                            <small>Total Requests Found</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-warning text-white rounded p-3 text-center">
                            <h4 class="mb-0"><?php echo count(array_filter($bloodRequests, function($r) { return $r['urgency'] === 'Critical'; })); ?></h4>
                            <small>Critical Cases</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-info text-white rounded p-3 text-center">
                            <h4 class="mb-0"><?php echo $totalPages; ?></h4>
                            <small>Total Pages</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-card">
            <div class="card-body">
                <div class="row align-items-center mb-3">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filter Requests
                        </h5>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-danger">
                            <i class="fas fa-info-circle me-1"></i>
                            Showing only <?php echo htmlspecialchars($donor['blood_group']); ?> blood group requests
                        </span>
                    </div>
                </div>
                
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Urgency Level</label>
                        <select class="form-select" name="urgency">
                            <option value="">All Urgency Levels</option>
                            <option value="Critical" <?php echo $urgencyFilter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="Urgent" <?php echo $urgencyFilter === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="Normal" <?php echo $urgencyFilter === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" 
                               value="<?php echo htmlspecialchars($cityFilter); ?>" 
                               placeholder="Enter city name (default: <?php echo htmlspecialchars($donor['city']); ?>)">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if ($urgencyFilter || $cityFilter): ?>
                    <div class="mt-3">
                        <a href="blood-requests.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Clear All Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Results Section -->
        <div class="row">
            <div class="col-12">
                <?php if (empty($bloodRequests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-heart text-muted" style="font-size: 64px;"></i>
                        <h4 class="text-muted mt-3">No Requests Found</h4>
                        <p class="text-muted">Try adjusting your filters or check back later for new requests.</p>
                        <a href="blood-requests.php" class="btn btn-outline-danger">
                            <i class="fas fa-refresh me-2"></i>Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($bloodRequests as $request): ?>
                        <div class="request-card urgency-<?php echo strtolower($request['urgency']); ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="badge bg-danger me-2 fs-6"><?php echo $request['blood_group']; ?></span>
                                        <span class="badge bg-<?php echo strtolower($request['urgency']) === 'critical' ? 'danger' : (strtolower($request['urgency']) === 'urgent' ? 'warning' : 'success'); ?> me-2">
                                            <?php echo $request['urgency']; ?>
                                        </span>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <h5 class="mb-2"><?php echo htmlspecialchars($request['requester_name']); ?></h5>
                                    
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($request['city']); ?>
                                    </p>
                                    
                                    <p class="mb-3"><?php echo htmlspecialchars($request['details']); ?></p>
                                </div>
                                
                                <div class="col-md-4 text-md-end">
                                    <div class="mb-3">
                                        <h4 class="text-danger mb-1"><?php echo $request['units_needed']; ?></h4>
                                        <small class="text-muted">unit<?php echo $request['units_needed'] > 1 ? 's' : ''; ?> needed</small>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="tel:<?php echo $request['requester_phone']; ?>" class="btn btn-danger">
                                            <i class="fas fa-phone me-1"></i>Call Now
                                        </a>
                                        
                                        <?php if ($request['requester_email']): ?>
                                            <a href="mailto:<?php echo $request['requester_email']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-envelope me-1"></i>Email
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-secondary btn-sm" onclick="shareRequest(<?php echo $request['id']; ?>, '<?php echo addslashes($request['requester_name']); ?>', '<?php echo $request['blood_group']; ?>')">
                                            <i class="fas fa-share me-1"></i>Share
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Blood requests pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(['blood_group' => $bloodGroupFilter, 'urgency' => $urgencyFilter, 'city' => $cityFilter]); ?>">
                                            <i class="fas fa-chevron-left me-1"></i>Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(['blood_group' => $bloodGroupFilter, 'urgency' => $urgencyFilter, 'city' => $cityFilter]); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(['blood_group' => $bloodGroupFilter, 'urgency' => $urgencyFilter, 'city' => $cityFilter]); ?>">
                                            Next<i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareRequest(requestId, requesterName, bloodGroup) {
            const shareData = {
                title: 'Urgent Blood Donation Request',
                text: `${requesterName} urgently needs ${bloodGroup} blood. Please help save a life!`,
                url: window.location.origin + '/GASC Blood Donor Bridge/request/blood-request.php?id=' + requestId
            };
            
            if (navigator.share) {
                navigator.share(shareData).catch(err => console.log('Error sharing:', err));
            } else {
                // Fallback - copy to clipboard
                const textToShare = `${shareData.text}\n\nHelp here: ${shareData.url}`;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(textToShare).then(() => {
                        alert('Request details copied to clipboard! Share it to help save a life.');
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = textToShare;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('Request details copied to clipboard! Share it to help save a life.');
                }
            }
        }
        
        // Auto-refresh every 5 minutes for new requests
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
    <script src="includes/sidebar.js"></script>
</body>
</html>
