<?php
// Set timezone for consistent dashboard timestamps
date_default_timezone_set('Asia/Kolkata');
?>
<?php
require_once '../config/database.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

try {
    $db = new Database();
    
    // Get dashboard statistics
    $stats = [];
    
    // Total donors
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_active = TRUE");
    $stats['total_donors'] = $result->fetch_assoc()['count'];
    
    // Active blood requests
    $result = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Active'");
    $stats['active_requests'] = $result->fetch_assoc()['count'];
    
    // Available donors
    $result = $db->query("SELECT COUNT(*) as count FROM users 
                         WHERE user_type = 'donor' 
                         AND is_active = TRUE 
                         AND is_verified = TRUE 
                         AND is_available = TRUE 
                         AND (
                             last_donation_date IS NULL 
                             OR (gender = 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 120)
                             OR (gender != 'Female' AND DATEDIFF(CURDATE(), last_donation_date) >= 90)
                         )");
    $stats['available_donors'] = $result->fetch_assoc()['count'];
    
    // Fulfilled requests this month
    $result = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Fulfilled' AND MONTH(updated_at) = MONTH(CURRENT_DATE()) AND YEAR(updated_at) = YEAR(CURRENT_DATE())");
    $stats['fulfilled_this_month'] = $result->fetch_assoc()['count'];
    
    // Blood group distribution
    $bloodGroupStats = $db->query("SELECT * FROM blood_group_stats ORDER BY blood_group")->fetch_all(MYSQLI_ASSOC);
    
    // Recent blood requests
    $recentRequests = $db->query("SELECT * FROM blood_requests ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Recent donor registrations
    $recentDonors = $db->query("SELECT id, name, email, blood_group, city, created_at FROM users WHERE user_type = 'donor' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    // Critical requests
    $criticalRequests = $db->query("SELECT * FROM blood_requests WHERE status = 'Active' AND urgency = 'Critical' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'], 'dashboard_error', $error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($_SESSION['user_type']); ?> Dashboard - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            background: linear-gradient(180deg, #1f2937, #111827);
            min-height: 100vh;
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .admin-brand {
            background: rgba(220, 38, 38, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.7);
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(220, 38, 38, 0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 4px solid #dc2626;
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        .stat-card.success {
            border-left-color: #10b981;
        }
        
        .stat-card.info {
            border-left-color: #3b82f6;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #dc2626;
        }
        
        .stat-card.warning .stat-number {
            color: #f59e0b;
        }
        
        .stat-card.success .stat-number {
            color: #10b981;
        }
        
        .stat-card.info .stat-number {
            color: #3b82f6;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #dc2626;
        }
        
        .request-card.critical {
            border-left-color: #dc2626;
            background: linear-gradient(90deg, #fef2f2, white);
        }
        
        .request-card.urgent {
            border-left-color: #f59e0b;
            background: linear-gradient(90deg, #fffbeb, white);
        }
        
        .request-card.normal {
            border-left-color: #10b981;
            background: linear-gradient(90deg, #f0fdf4, white);
        }
        
        .blood-group-chart {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .blood-group-item {
            text-align: center;
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .blood-group-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #dc2626;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            margin: 0 auto 10px;
        }
        
        .admin-header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .user-info {
            background: rgba(220, 38, 38, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            border-radius: 10px;
            padding: 10px 20px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                min-height: auto;
            }
            
            .blood-group-chart {
                grid-template-columns: repeat(2, 1fr);
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <i class="fas fa-tachometer-alt text-danger me-2"></i>Dashboard Overview
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <small class="text-muted me-3">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                        <button class="btn btn-sm btn-outline-danger" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['total_donors']); ?></div>
                                        <h6 class="text-muted mb-0">Total Donors</h6>
                                    </div>
                                    <div class="text-danger" style="font-size: 2.5rem;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['active_requests']); ?></div>
                                        <h6 class="text-muted mb-0">Active Requests</h6>
                                    </div>
                                    <div class="text-warning" style="font-size: 2.5rem;">
                                        <i class="fas fa-hand-holding-heart"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['available_donors']); ?></div>
                                        <h6 class="text-muted mb-0">Available Donors</h6>
                                    </div>
                                    <div class="text-success" style="font-size: 2.5rem;">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['fulfilled_this_month']); ?></div>
                                        <h6 class="text-muted mb-0">Fulfilled This Month</h6>
                                    </div>
                                    <div class="text-info" style="font-size: 2.5rem;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Critical Requests Alert -->
                    <?php if (!empty($criticalRequests)): ?>
                        <div class="alert alert-danger mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Critical Blood Requests Alert!</strong>
                                <span class="ms-auto">
                                    <span class="badge bg-white text-danger"><?php echo count($criticalRequests); ?></span>
                                </span>
                            </div>
                            <p class="mb-2 mt-2">There are <?php echo count($criticalRequests); ?> critical blood requests that need immediate attention.</p>
                            <a href="requests.php?filter=critical" class="btn btn-sm btn-light">
                                <i class="fas fa-eye me-1"></i>View Critical Requests
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt text-danger me-2"></i>Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <a href="donors.php?action=add" class="btn btn-outline-danger quick-action-btn w-100">
                                                <i class="fas fa-user-plus me-2"></i>Add New Donor
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <a href="requests.php" class="btn btn-outline-warning quick-action-btn w-100">
                                                <i class="fas fa-eye me-2"></i>View All Requests
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <a href="donors.php?filter=pending" class="btn btn-outline-info quick-action-btn w-100">
                                                <i class="fas fa-user-check me-2"></i>Verify Donors
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <a href="reports.php" class="btn btn-outline-success quick-action-btn w-100">
                                                <i class="fas fa-chart-line me-2"></i>Generate Report
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Blood Group Distribution -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-tint text-danger me-2"></i>Blood Group Distribution
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="blood-group-chart">
                                        <?php foreach ($bloodGroupStats as $bg): ?>
                                            <div class="blood-group-item">
                                                <div class="blood-group-circle">
                                                    <?php echo $bg['blood_group']; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo $bg['total_donors']; ?></strong>
                                                    <small class="d-block text-muted">Total</small>
                                                    <small class="text-success"><?php echo $bg['available_donors']; ?> Available</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Blood Requests -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-clock text-danger me-2"></i>Recent Blood Requests
                                    </h5>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($recentRequests)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-inbox text-muted" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-2">No recent requests</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentRequests as $request): ?>
                                            <div class="request-card <?php echo strtolower($request['urgency']); ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <span class="badge bg-danger me-2"><?php echo $request['blood_group']; ?></span>
                                                            <span class="badge bg-<?php echo strtolower($request['urgency']) === 'critical' ? 'danger' : (strtolower($request['urgency']) === 'urgent' ? 'warning' : 'success'); ?>">
                                                                <?php echo $request['urgency']; ?>
                                                            </span>
                                                            <span class="badge bg-secondary ms-2"><?php echo $request['status']; ?></span>
                                                        </div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['requester_name']); ?></h6>
                                                        <p class="text-muted small mb-1">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($request['city']); ?>
                                                            | <i class="fas fa-vial me-1"></i><?php echo $request['units_needed']; ?> unit<?php echo $request['units_needed'] > 1 ? 's' : ''; ?>
                                                        </p>
                                                        <p class="small mb-0"><?php echo htmlspecialchars(substr($request['details'], 0, 100)) . '...'; ?></p>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></small>
                                                        <br>
                                                        <a href="requests.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-danger mt-1">
                                                            View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Donor Registrations -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-plus text-danger me-2"></i>Recent Donor Registrations
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentDonors)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-users text-muted" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-2">No recent registrations</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Blood Group</th>
                                                        <th>City</th>
                                                        <th>Registered</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentDonors as $donor): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                                            <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                                            <td>
                                                                <span class="badge bg-danger"><?php echo $donor['blood_group']; ?></span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($donor['city']); ?></td>
                                                            <td><?php echo date('M d, Y', strtotime($donor['created_at'])); ?></td>
                                                            <td>
                                                                <a href="donors.php?id=<?php echo $donor['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh dashboard every 5 minutes
            setTimeout(function() {
                location.reload();
            }, 300000);
            
            // Real-time clock
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleString();
                // Update clock if element exists
                const clockElement = document.getElementById('currentTime');
                if (clockElement) {
                    clockElement.textContent = timeString;
                }
            }
            
            // Update clock every second
            setInterval(updateClock, 1000);
            
            // Smooth scrolling for sidebar links
            document.querySelectorAll('.nav-link[href^="#"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
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
