<?php
require_once '../config/database.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

$db = new Database();
$success = '';
$error = '';

// Get blood group statistics for inventory using the database function
$inventory = getBloodInventoryStats();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clear any output that might have been sent and stop output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $filename = 'blood_inventory_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Blood Group', 'Total Donors', 'Available Donors', 'Can Donate Now', 
        'Active Requests', 'Fulfilled This Month', 'Stock Status'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($inventory as $item) {
        // Clean all values to ensure no HTML or extra whitespace
        $row = [
            trim($item['blood_group']),
            intval($item['total_donors']),
            intval($item['available_donors']),
            intval($item['can_donate_now']),
            intval($item['active_requests']),
            intval($item['fulfilled_this_month']),
            trim(preg_replace('/\s+/', ' ', strip_tags($item['stock_status']))) // Clean and normalize whitespace
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export activity
    logActivity($_SESSION['user_id'], 'inventory_exported', "Exported blood inventory data to CSV");
    
    exit;
}

// Get overall statistics
$totalDonors = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_verified = TRUE AND is_active = TRUE")->fetch_assoc()['count'];
$totalAvailable = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE")->fetch_assoc()['count'];
$totalCanDonate = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE AND (
    last_donation_date IS NULL 
    OR (gender = 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 120)
    OR (gender != 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 90)
)")->fetch_assoc()['count'];
$totalActiveRequests = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Active'")->fetch_assoc()['count'];

// Get recent donation activity (last 30 days)
$recentActivity = $db->query("
    SELECT 
        u.name,
        u.blood_group,
        u.city,
        u.last_donation_date,
        DATEDIFF(CURDATE(), u.last_donation_date) as days_ago
    FROM users u 
    WHERE u.user_type = 'donor' 
    AND u.last_donation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY u.last_donation_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get critical requests (urgent need)
$criticalRequests = $db->query("
    SELECT 
        br.*,
        (SELECT COUNT(*) FROM users u WHERE u.blood_group = br.blood_group AND u.city = br.city AND u.is_available = TRUE AND u.is_verified = TRUE AND u.is_active = TRUE AND u.user_type = 'donor') as available_donors_count
    FROM blood_requests br 
    WHERE br.status = 'Active' 
    AND (br.urgency = 'Critical' OR br.expires_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ORDER BY br.urgency DESC, br.expires_at ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// City-wise distribution
$cityDistribution = $db->query("
    SELECT 
        city,
        COUNT(*) as total_donors,
        SUM(CASE WHEN is_available = TRUE THEN 1 ELSE 0 END) as available_donors
    FROM users 
    WHERE user_type = 'donor' AND is_verified = TRUE AND is_active = TRUE AND city IS NOT NULL
    GROUP BY city
    ORDER BY total_donors DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Inventory - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .inventory-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .inventory-card:hover {
            transform: translateY(-2px);
        }
        
        .blood-group-header {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .stock-good {
            border-left: 5px solid #10b981;
        }
        
        .stock-low {
            border-left: 5px solid #f59e0b;
        }
        
        .stock-critical {
            border-left: 5px solid #dc2626;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 15px;
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .activity-item {
            border-left: 3px solid #dc2626;
            padding: 10px 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
            .inventory-card {
                padding: 15px;
            }
            
            .blood-group-header {
                font-size: 1.2rem;
                padding: 10px;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .rounded-pill {
                width: 100%;
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
                        <a class="nav-link active" href="inventory.php">
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
                        <i class="fas fa-warehouse text-danger me-2"></i>Blood Inventory
                    </h1>
                    <div class="d-flex gap-2 mb-2 mb-md-0">
                        <button type="button" class="btn btn-success rounded-pill px-4" onclick="window.location.href='inventory.php?export=csv'">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-primary"><?php echo $totalDonors; ?></div>
                            <div class="text-muted">Total Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-success"><?php echo $totalAvailable; ?></div>
                            <div class="text-muted">Available Donors</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-warning"><?php echo $totalCanDonate; ?></div>
                            <div class="text-muted">Can Donate Now</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="h3 text-danger"><?php echo $totalActiveRequests; ?></div>
                            <div class="text-muted">Active Requests</div>
                        </div>
                    </div>
                </div>
                
                <!-- Blood Group Inventory -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">
                            <i class="fas fa-tint me-2"></i>Blood Group Inventory
                        </h4>
                    </div>
                    <?php foreach ($inventory as $item): ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="inventory-card stock-<?php echo strtolower($item['stock_status']); ?>">
                                <div class="blood-group-header">
                                    <?php echo htmlspecialchars($item['blood_group']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Total Donors:</span>
                                        <strong><?php echo $item['total_donors']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Available:</span>
                                        <strong class="text-success"><?php echo $item['available_donors']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Can Donate:</span>
                                        <strong class="text-primary"><?php echo $item['can_donate_now']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Active Requests:</span>
                                        <strong class="text-warning"><?php echo $item['active_requests']; ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Fulfilled (Month):</span>
                                        <strong class="text-info"><?php echo $item['fulfilled_this_month']; ?></strong>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <span class="badge <?php 
                                        echo $item['stock_status'] === 'Good' ? 'bg-success' : 
                                            ($item['stock_status'] === 'Low' ? 'bg-warning' : 'bg-danger'); 
                                    ?> px-3 py-2">
                                        <?php echo $item['stock_status']; ?> Stock
                                    </span>
                                </div>
                                
                                <?php if ($item['stock_status'] === 'Critical'): ?>
                                    <div class="mt-2 text-center">
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Urgent need for donors!
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row">
                    <!-- Critical Requests -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Critical Requests
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($criticalRequests)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <p>No critical requests at the moment</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($criticalRequests as $request): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['requester_name']); ?></strong>
                                                    <span class="badge bg-danger ms-2"><?php echo $request['blood_group']; ?></span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $request['city']; ?> • 
                                                        <?php echo $request['units_needed']; ?> unit(s) • 
                                                        <?php echo $request['urgency']; ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-danger">
                                                        Expires: <?php echo date('M j, g:i A', strtotime($request['expires_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?php echo $request['available_donors_count']; ?> donors
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="requests.php?status=Active&urgency=Critical" class="btn btn-danger btn-sm">
                                            View All Critical Requests
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Donation Activity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Donations (30 days)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                                        <p>No recent donation activity</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['name']); ?></strong>
                                                    <span class="badge bg-danger ms-2"><?php echo $activity['blood_group']; ?></span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $activity['city']; ?> • 
                                                        <?php echo $activity['days_ago']; ?> days ago
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?php echo date('M j', strtotime($activity['last_donation_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="donors.php" class="btn btn-success btn-sm">
                                            View All Donors
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- City-wise Distribution -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i>City-wise Donor Distribution
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($cityDistribution as $city): ?>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <div class="border rounded p-3 text-center">
                                                <h6><?php echo htmlspecialchars($city['city']); ?></h6>
                                                <div class="text-muted small">
                                                    Total: <strong><?php echo $city['total_donors']; ?></strong><br>
                                                    Available: <strong class="text-success"><?php echo $city['available_donors']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Stock Status Legend
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="bg-success rounded" style="width: 20px; height: 20px; margin-right: 10px;"></div>
                                            <span><strong>Good Stock:</strong> Available donors ≥ 2x active requests</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="bg-warning rounded" style="width: 20px; height: 20px; margin-right: 10px;"></div>
                                            <span><strong>Low Stock:</strong> Available donors ≥ active requests</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="bg-danger rounded" style="width: 20px; height: 20px; margin-right: 10px;"></div>
                                            <span><strong>Critical Stock:</strong> Available donors < active requests</span>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <strong>Note:</strong> "Can Donate Now" includes donors who are eligible based on gender-specific donation intervals 
                                            (Female: 120+ days, Male/Other: 90+ days since last donation).
                                        </small>
                                    </div>
                                </div>
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
        // Auto-refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
        
        // Add tooltip for stock status
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
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
