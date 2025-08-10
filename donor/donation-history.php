<?php
require_once '../config/database.php';

// Check if user is logged in as donor
requireRole(['donor']);

$error = null;
$donationHistory = [];
$donor = null;
$totalDonations = 0;
$verifiedDonations = 0;
$pendingDonations = 0;

// Get filter and pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$statusFilter = $_GET['status'] ?? '';
$yearFilter = $_GET['year'] ?? '';

try {
    $db = new Database();
    
    // Get donor information
    $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'donor'";
    $result = $db->query($sql, [$_SESSION['user_id']]);
    $donor = $result->fetch_assoc();
    
    if (!$donor) {
        throw new Exception('Donor not found');
    }
    
    // Build WHERE clause for filters
    $whereConditions = ["donor_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if (!empty($statusFilter)) {
        if ($statusFilter === 'verified') {
            $whereConditions[] = "is_verified = 1";
        } elseif ($statusFilter === 'pending') {
            $whereConditions[] = "is_verified = 0";
        }
    }
    
    if (!empty($yearFilter)) {
        $whereConditions[] = "YEAR(donation_date) = ?";
        $params[] = $yearFilter;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count for pagination
    $countSQL = "SELECT COUNT(*) as total FROM donor_availability_history WHERE $whereClause";
    $countResult = $db->query($countSQL, $params);
    $totalDonations = $countResult->fetch_assoc()['total'];
    
    // Get filtered donation history with pagination
    $historySQL = "SELECT * FROM donor_availability_history 
                   WHERE $whereClause 
                   ORDER BY donation_date DESC 
                   LIMIT ? OFFSET ?";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $historyResult = $db->query($historySQL, $allParams);
    $donationHistory = $historyResult->fetch_all(MYSQLI_ASSOC);
    
    // Get statistics
    $statsSQL = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending,
                    SUM(units_donated) as total_units
                 FROM donor_availability_history 
                 WHERE donor_id = ?";
    $statsResult = $db->query($statsSQL, [$_SESSION['user_id']]);
    $stats = $statsResult->fetch_assoc();
    
    $verifiedDonations = $stats['verified'] ?? 0;
    $pendingDonations = $stats['pending'] ?? 0;
    $totalUnits = $stats['total_units'] ?? 0;
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'donation_history_error', $error);
}

// Calculate pagination
$totalPages = ceil($totalDonations / $limit);

// Get available years for filter
$availableYears = [];
try {
    $yearsSQL = "SELECT DISTINCT YEAR(donation_date) as year 
                 FROM donor_availability_history 
                 WHERE donor_id = ? 
                 ORDER BY year DESC";
    $yearsResult = $db->query($yearsSQL, [$_SESSION['user_id']]);
    while ($row = $yearsResult->fetch_assoc()) {
        $availableYears[] = $row['year'];
    }
} catch (Exception $e) {
    // Ignore errors for years filter
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .donation-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #dc2626;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .donation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .donation-verified {
            border-left-color: #28a745;
        }
        
        .donation-pending {
            border-left-color: #ffc107;
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pagination .page-link {
            color: #dc2626;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
            <h5 class="mb-0">Donation History</h5>
            <div></div>
        </div>
    </div>
    
    <div class="donor-main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-history me-2"></i>Donation History</h2>
                        <p class="text-muted mb-0">Track all your blood donation records and contributions</p>
                    </div>
                    <a href="add-donation.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Donation
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon text-danger">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4 class="text-danger mb-1"><?php echo $stats['total'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Total Donations</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="text-success mb-1"><?php echo $verifiedDonations; ?></h4>
                        <p class="text-muted mb-0">Verified</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 class="text-warning mb-1"><?php echo $pendingDonations; ?></h4>
                        <p class="text-muted mb-0">Pending Verification</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon text-info">
                            <i class="fas fa-tint"></i>
                        </div>
                        <h4 class="text-info mb-1"><?php echo $totalUnits ?? 0; ?></h4>
                        <p class="text-muted mb-0">Total Units Donated</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $yearFilter == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                        <div class="col-md-3 mb-3 text-md-end">
                            <a href="donation-history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Donation History List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($donationHistory)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No Donation Records Found</h4>
                            <?php if (!empty($statusFilter) || !empty($yearFilter)): ?>
                                <p>No donations match your current filters. Try adjusting your search criteria.</p>
                                <a href="donation-history.php" class="btn btn-outline-danger">Clear Filters</a>
                            <?php else: ?>
                                <p>Start your blood donation journey today!</p>
                                <a href="add-donation.php" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Add Your First Donation
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($donationHistory as $donation): ?>
                            <div class="donation-card <?php echo $donation['is_verified'] ? 'donation-verified' : 'donation-pending'; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <h5 class="mb-0 me-3">
                                                <?php echo date('F d, Y', strtotime($donation['donation_date'])); ?>
                                            </h5>
                                            <?php if ($donation['is_verified']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i>Pending Verification
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <p class="mb-1">
                                                    <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($donation['location'] ?? 'Not specified'); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="fas fa-building text-muted me-2"></i>
                                                    <strong>Blood Bank:</strong> <?php echo htmlspecialchars($donation['blood_bank_name'] ?? 'Not specified'); ?>
                                                </p>
                                            </div>
                                            <div class="col-sm-6">
                                                <p class="mb-1">
                                                    <i class="fas fa-tint text-muted me-2"></i>
                                                    <strong>Units Donated:</strong> 
                                                    <span class="badge bg-danger"><?php echo $donation['units_donated'] ?? 1; ?></span>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="fas fa-calendar text-muted me-2"></i>
                                                    <strong>Recorded:</strong> <?php echo date('M d, Y', strtotime($donation['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($donation['notes'])): ?>
                                            <div class="mt-2">
                                                <p class="mb-0">
                                                    <i class="fas fa-sticky-note text-muted me-2"></i>
                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($donation['notes']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex flex-column align-items-md-end gap-2">
                                            <a href="donation-details.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            <?php if ($donation['is_verified'] && $donation['verified_by']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    Verified by Admin
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <nav aria-label="Donation history pagination">
                                    <ul class="pagination">
                                        <!-- Previous Button -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo urlencode($statusFilter); ?>&year=<?php echo urlencode($yearFilter); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&year=<?php echo urlencode($yearFilter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Button -->
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo urlencode($statusFilter); ?>&year=<?php echo urlencode($yearFilter); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            
                            <!-- Pagination Info -->
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $totalDonations); ?> of <?php echo $totalDonations; ?> donations
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
    <script>
        // Auto-refresh every 10 minutes for new verifications
        setTimeout(function() {
            window.location.reload();
        }, 600000);
        
        // Smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to filter form
            const filterForm = document.querySelector('form');
            if (filterForm) {
                filterForm.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Filtering...';
                        submitBtn.disabled = true;
                    }
                });
            }
            
            // Enhanced hover effects
            const donationCards = document.querySelectorAll('.donation-card');
            donationCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderLeftWidth = '6px';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderLeftWidth = '4px';
                });
            });
        });
    </script>
</body>
</html>
