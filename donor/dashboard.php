<?php
// Set timezone for consistent dashboard timestamps
date_default_timezone_set('Asia/Kolkata');
?>
<?php
require_once '../config/database.php';

// Check if user is logged in as donor
requireRole(['donor']);

// Initialize variables to prevent undefined variable warnings
$donor = null;
$donationHistory = [];
$bloodRequests = [];
$canDonate = false;
$error = null;
$success = null;

try {
    $db = new Database();
    
    // Get donor information
    $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'donor'";
    $result = $db->query($sql, [$_SESSION['user_id']]);
    $donor = $result->fetch_assoc();
    
    if (!$donor) {
        throw new Exception('Donor not found');
    }
    
    // Check availability status
    $canDonate = calculateAvailability($donor['last_donation_date'], $donor['gender']);
    
    // Get donation history
    $historySQL = "SELECT * FROM donor_availability_history WHERE donor_id = ? ORDER BY donation_date DESC LIMIT 5";
    $historyResult = $db->query($historySQL, [$_SESSION['user_id']]);
    $donationHistory = $historyResult->fetch_all(MYSQLI_ASSOC);
    
    // Get recent blood requests that exactly match donor's blood group in donor's city
    $requestsSQL = "SELECT * FROM blood_requests 
                    WHERE status = 'Active' 
                    AND blood_group = ? 
                    AND city = ? 
                    ORDER BY urgency = 'Critical' DESC, urgency = 'Urgent' DESC, created_at DESC 
                    LIMIT 5";
    $requestsResult = $db->query($requestsSQL, [$donor['blood_group'], $donor['city']]);
    $bloodRequests = $requestsResult->fetch_all(MYSQLI_ASSOC);
    
    // Handle mark as available
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_available'])) {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $updateSQL = "UPDATE users SET is_available = TRUE WHERE id = ?";
            $db->query($updateSQL, [$_SESSION['user_id']]);
            
            logActivity($_SESSION['user_id'], 'marked_available', "Donor marked themselves as available");
            
            $success = "You have been marked as available for blood donation.";
            $donor['is_available'] = true;
        }
    }
    
    // Handle unmark available
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unmark_available'])) {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $updateSQL = "UPDATE users SET is_available = FALSE WHERE id = ?";
            $db->query($updateSQL, [$_SESSION['user_id']]);
            
            logActivity($_SESSION['user_id'], 'unmarked_available', "Donor marked themselves as unavailable");
            
            $success = "You have been marked as unavailable for blood donation.";
            $donor['is_available'] = false;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    logActivity($_SESSION['user_id'] ?? null, 'dashboard_error', $error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - GASC Blood Bridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-sidebar {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            min-height: 100vh;
            color: white;
        }
        
        .profile-card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        
        .availability-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid white;
        }
        
        .available { background: #28a745; }
        .unavailable { background: #dc3545; }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #dc2626;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .urgency-critical { border-left-color: #dc2626; }
        .urgency-urgent { border-left-color: #f59e0b; }
        .urgency-normal { border-left-color: #10b981; }
        
        .blood-group-display {
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .dashboard-sidebar {
                min-height: auto;
            }
        }
        
        /* Section Management */
        .content-section {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Enhanced Cards */
        .card {
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        /* Profile Section Styling */
        .form-control[readonly] {
            background-color: #f8f9fa;
            border-color: #e9ecef;
        }
        
        /* Table Enhancements */
        .table-hover tbody tr:hover {
            background-color: rgba(220, 38, 38, 0.05);
        }
        
        /* Filter Section */
        .form-select, .form-control {
            border-radius: 8px;
        }
        
        /* Request Cards Enhancement */
        .request-card {
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Action Buttons */
        .btn {
            border-radius: 8px;
        }
        
        /* Confirmation styling */
        .unmark-btn:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }
        
        /* Mobile Navigation Styles for Donor Dashboard */
        .mobile-nav-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1050;
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .mobile-nav-toggle:hover {
            background: var(--dark-red);
            color: white;
            transform: scale(1.05);
        }
        
        .mobile-nav-toggle.hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.7) rotate(90deg);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        .sidebar-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            color: rgba(255,255,255,0.8);
            z-index: 1046;
            opacity: 0;
            transform: rotate(0deg) scale(0.8);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .dashboard-sidebar.show .sidebar-close {
            opacity: 1;
            transform: rotate(180deg) scale(1);
        }
        
        .sidebar-close:hover {
            color: white;
            transform: rotate(180deg) scale(1.1);
        }
        
        @media (max-width: 767.98px) {
            .dashboard-sidebar {
                position: fixed !important;
                top: 0;
                left: -100%;
                width: 300px;
                height: 100vh;
                z-index: 1045;
                transition: left 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
                box-shadow: 2px 0 25px rgba(0,0,0,0.3);
            }
            
            .dashboard-sidebar.show {
                left: 0;
            }
            
            .dashboard-sidebar .p-4 {
                padding-top: 60px !important;
            }
            
            .col-lg-9, .col-md-8 {
                margin-left: 0 !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .donor-main-content {
                padding-top: 60px !important;
            }
            
            /* Animate nav links */
            .dashboard-sidebar .nav-link {
                opacity: 0.8;
                transform: translateX(-20px);
                transition: all 0.3s ease;
            }
            
            .dashboard-sidebar.show .nav-link {
                opacity: 1;
                transform: translateX(0);
                animation: slideInLeft 0.5s ease forwards;
            }
            
            .dashboard-sidebar.show .nav-link:nth-child(1) { animation-delay: 0.1s; }
            .dashboard-sidebar.show .nav-link:nth-child(2) { animation-delay: 0.15s; }
            .dashboard-sidebar.show .nav-link:nth-child(3) { animation-delay: 0.2s; }
            .dashboard-sidebar.show .nav-link:nth-child(4) { animation-delay: 0.25s; }
            .dashboard-sidebar.show .nav-link:nth-child(5) { animation-delay: 0.3s; }
            .dashboard-sidebar.show .nav-link:nth-child(6) { animation-delay: 0.35s; }
            
            @keyframes slideInLeft {
                from {
                    opacity: 0.8;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle d-md-none" type="button" id="mobileNavToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php if ($error && !$donor): ?>
        <!-- Error State -->
        <div class="container-fluid bg-danger text-white min-vh-100 d-flex align-items-center">
            <div class="container text-center">
                <i class="fas fa-exclamation-triangle" style="font-size: 64px; margin-bottom: 20px;"></i>
                <h1>Dashboard Error</h1>
                <p class="lead"><?php echo htmlspecialchars($error); ?></p>
                <div class="mt-4">
                    <a href="login.php" class="btn btn-light btn-lg me-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Login Again
                    </a>
                    <a href="../index.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-home me-2"></i>Go Home
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
    
    <div class="container-fluid"><?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 col-md-4 dashboard-sidebar p-0" id="donorSidebar">
                <!-- Close button for mobile -->
                <button class="sidebar-close d-md-none" type="button" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="p-4">
                    <!-- Profile Card -->
                    <div class="profile-card position-relative">
                        <div class="availability-badge <?php echo ($donor['is_available'] ?? false) ? 'available' : 'unavailable'; ?>"></div>
                        <div class="d-flex align-items-center">
                            <div class="blood-group-display me-3">
                                <?php echo $donor['blood_group'] ?? 'N/A'; ?>
                            </div>
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($donor['name'] ?? 'Unknown'); ?></h6>
                                <small class="opacity-75"><?php echo htmlspecialchars($donor['class'] ?? 'N/A'); ?></small>
                                <br>
                                <small class="opacity-75"><?php echo htmlspecialchars($donor['city'] ?? 'N/A'); ?></small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="opacity-75">
                                Status: 
                                <span class="<?php echo $canDonate ? 'text-success' : 'text-warning'; ?>">
                                    <?php echo $canDonate ? 'Eligible to Donate' : 'Not Eligible Yet'; ?>
                                </span>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="edit-profile.php">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                        <a class="nav-link" href="#" onclick="showHistorySection(); return false;">
                            <i class="fas fa-history me-2"></i>Donation History
                        </a>
                        <a class="nav-link" href="blood-requests.php">
                            <i class="fas fa-hand-holding-heart me-2"></i>Blood Requests
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9 col-md-8 donor-main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-dark">Welcome back, <?php echo htmlspecialchars($donor['name'] ?? 'Donor'); ?>!</h2>
                            <p class="text-muted mb-0">Your contribution can save lives today</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Last login: <?php echo date('M d, Y \a\t g:i A'); ?> IST</small>
                        </div>
                    </div>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-danger">
                                    <i class="fas fa-tint"></i>
                                </div>
                                <h4 class="text-danger mb-1"><?php echo $donor['blood_group'] ?? 'N/A'; ?></h4>
                                <p class="text-muted mb-0">Your Blood Group</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <h4 class="text-success mb-1"><?php echo count($donationHistory); ?></h4>
                                <p class="text-muted mb-0">Total Donations</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h4 class="text-warning mb-1">
                                    <?php 
                                    if ($donor['last_donation_date']) {
                                        echo date('M d, Y', strtotime($donor['last_donation_date']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </h4>
                                <p class="text-muted mb-0">Last Donation</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4 class="text-info mb-1"><?php echo count($bloodRequests); ?></h4>
                                <p class="text-muted mb-0">Active Requests</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Availability Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="card-title mb-2">
                                                <i class="fas fa-heartbeat text-danger me-2"></i>Donation Availability
                                            </h5>
                                            <?php if ($canDonate): ?>
                                                <p class="text-success mb-2">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    You are eligible to donate blood!
                                                </p>
                                                <small class="text-muted">
                                                    You can safely donate blood. Remember to maintain good health and stay hydrated.
                                                </small>
                                            <?php else: ?>
                                                <p class="text-warning mb-2">
                                                    <i class="fas fa-clock me-1"></i>
                                                    You are not eligible to donate yet.
                                                </p>
                                                <small class="text-muted">
                                                    <?php 
                                                    $nextEligibleDate = date('M d, Y', strtotime($donor['last_donation_date'] . ' + ' . (($donor['gender'] === 'Female') ? '4' : '3') . ' months'));
                                                    echo "You can donate again after $nextEligibleDate";
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <?php if ($canDonate): ?>
                                                <?php if (!$donor['is_available']): ?>
                                                    <!-- Currently Unavailable - Show Mark as Available button -->
                                                    <div class="d-flex flex-column align-items-md-end">
                                                        <span class="badge bg-secondary fs-6 px-3 py-2 mb-2">
                                                            <i class="fas fa-times-circle me-1"></i>Unavailable
                                                        </span>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <button type="submit" name="mark_available" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check me-2"></i>Mark as Available
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Currently Available - Show Mark as Unavailable button -->
                                                    <div class="d-flex flex-column align-items-md-end">
                                                        <span class="badge bg-success fs-6 px-3 py-2 mb-2">
                                                            <i class="fas fa-check-circle me-1"></i>Available
                                                        </span>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <button type="submit" name="unmark_available" class="btn btn-outline-secondary btn-sm">
                                                                <i class="fas fa-times me-1"></i>Mark as Unavailable
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Not eligible to donate -->
                                                <div class="d-flex flex-column align-items-md-end">
                                                    <span class="badge bg-warning fs-6 px-3 py-2">
                                                        <i class="fas fa-clock me-1"></i>Not Eligible
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Sections -->
                    <div id="dashboard-section" class="content-section">
                    <div class="row">
                        <!-- Recent Blood Requests -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-hand-holding-heart text-danger me-2"></i>
                                            Blood Requests for <?php echo htmlspecialchars($donor['blood_group']); ?>
                                        </h5>
                                        <span class="badge bg-danger">
                                            <?php echo htmlspecialchars($donor['city']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">Showing only requests matching your blood group in your area</small>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($bloodRequests)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-heart text-muted" style="font-size: 48px;"></i>
                                            <h6 class="text-muted mt-3">No <?php echo htmlspecialchars($donor['blood_group']); ?> requests in <?php echo htmlspecialchars($donor['city']); ?></h6>
                                            <p class="text-muted">No active blood requests matching your blood group in your area.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($bloodRequests as $request): ?>
                                            <div class="request-card urgency-<?php echo strtolower($request['urgency']); ?>">
                                                <div class="row align-items-center">
                                                    <div class="col-md-8">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <span class="badge bg-danger me-2"><?php echo $request['blood_group']; ?></span>
                                                            <span class="badge bg-<?php echo strtolower($request['urgency']) === 'critical' ? 'danger' : (strtolower($request['urgency']) === 'urgent' ? 'warning' : 'success'); ?>">
                                                                <?php echo $request['urgency']; ?>
                                                            </span>
                                                            <small class="text-muted ms-2">
                                                                <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['requester_name']); ?></h6>
                                                        <p class="text-muted mb-1 small">
                                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($request['city']); ?>
                                                        </p>
                                                        <p class="mb-0 small"><?php echo htmlspecialchars(substr($request['details'], 0, 100)) . '...'; ?></p>
                                                    </div>
                                                    <div class="col-md-4 text-md-end">
                                                        <div class="mb-2">
                                                            <strong><?php echo $request['units_needed']; ?> unit<?php echo $request['units_needed'] > 1 ? 's' : ''; ?></strong>
                                                        </div>
                                                        <a href="tel:<?php echo $request['requester_phone']; ?>" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-phone me-1"></i>Call
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- View All Requests Button -->
                                    <div class="text-center mt-4">
                                        <a href="blood-requests.php" class="btn btn-outline-danger">
                                            <i class="fas fa-external-link-alt me-2"></i>View All Blood Requests
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Donation History -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history text-danger me-2"></i>
                                        Recent Donations
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($donationHistory)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-calendar-times text-muted" style="font-size: 32px;"></i>
                                            <p class="text-muted mt-2 mb-0">No donation history yet</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($donationHistory as $donation): ?>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <div>
                                                    <strong><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></strong>
                                                    <?php if ($donation['location']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($donation['location']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <i class="fas fa-heart text-danger"></i>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center mt-3">
                                            <a href="#" onclick="showHistorySection(); return false;" class="btn btn-sm btn-outline-danger">View All</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <!-- Full Donation History Section (Initially Hidden) -->
                    <div id="history-section" class="content-section" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-history text-danger me-2"></i>
                                            Complete Donation History
                                        </h5>
                                        <div>
                                            <button class="btn btn-outline-secondary btn-sm me-2" onclick="showDashboardSection()">
                                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" onclick="addDonationRecord()">
                                                <i class="fas fa-plus me-1"></i>Add Donation
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($donationHistory)): ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-calendar-times text-muted" style="font-size: 64px;"></i>
                                                <h4 class="text-muted mt-3">No Donation History</h4>
                                                <p class="text-muted">Start your blood donation journey today!</p>
                                                <button class="btn btn-success" onclick="addDonationRecord()">
                                                    <i class="fas fa-plus me-2"></i>Add Your First Donation
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Location</th>
                                                            <th>Units</th>
                                                            <th>Blood Bank</th>
                                                            
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($donationHistory as $donation): ?>
                                                            <tr>
                                                                <td><strong><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($donation['location'] ?? 'N/A'); ?></td>
                                                                <td><span class="badge bg-danger"><?php echo $donation['units_donated'] ?? 1; ?></span></td>
                                                                <td><?php echo htmlspecialchars($donation['blood_bank_name'] ?? 'N/A'); ?></td>
                                                                
                                                                <td>
                                                                    <?php if ($donation['is_verified']): ?>
                                                                        <span class="badge bg-success">Verified</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning">Pending</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewDonationDetails(<?php echo $donation['id']; ?>)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
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

                    <!-- All Blood Requests Section -->
                    <div id="requests-section" class="content-section" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="fas fa-hand-holding-heart text-danger me-2"></i>
                                                All <?php echo htmlspecialchars($donor['blood_group']); ?> Blood Requests
                                            </h5>
                                            <a href="blood-requests.php" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i>View Full List
                                            </a>
                                        </div>
                                        <small class="text-muted">Complete list of blood requests matching your blood group</small>
                                    </div>
                                    <div class="card-body">
                                        <!-- Filter Options -->
                                        <div class="row mb-4">
                                            <div class="col-md-3">
                                                <select class="form-select" id="bloodGroupFilter">
                                                    <option value="">All Blood Groups</option>
                                                    <option value="A+">A+</option>
                                                    <option value="A-">A-</option>
                                                    <option value="B+">B+</option>
                                                    <option value="B-">B-</option>
                                                    <option value="AB+">AB+</option>
                                                    <option value="AB-">AB-</option>
                                                    <option value="O+">O+</option>
                                                    <option value="O-">O-</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select" id="urgencyFilter">
                                                    <option value="">All Urgency Levels</option>
                                                    <option value="Critical">Critical</option>
                                                    <option value="Urgent">Urgent</option>
                                                    <option value="Normal">Normal</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" class="form-control" id="cityFilter" placeholder="Filter by city">
                                            </div>
                                            <div class="col-md-3">
                                                <button class="btn btn-danger" onclick="applyFilters()">
                                                    <i class="fas fa-filter me-1"></i>Filter
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Requests List -->
                                        <div id="requestsList">
                                            <?php if (empty($bloodRequests)): ?>
                                                <div class="text-center py-5">
                                                    <i class="fas fa-heart text-muted" style="font-size: 64px;"></i>
                                                    <h4 class="text-muted mt-3">No Active Requests</h4>
                                                    <p class="text-muted">Check back later for new blood requests.</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($bloodRequests as $request): ?>
                                                    <div class="request-card urgency-<?php echo strtolower($request['urgency']); ?> mb-3">
                                                        <div class="row">
                                                            <div class="col-md-8">
                                                                <div class="d-flex align-items-center mb-3">
                                                                    <span class="badge bg-danger me-2 fs-6"><?php echo $request['blood_group']; ?></span>
                                                                    <span class="badge bg-<?php echo strtolower($request['urgency']) === 'critical' ? 'danger' : (strtolower($request['urgency']) === 'urgent' ? 'warning' : 'success'); ?> me-2">
                                                                        <?php echo $request['urgency']; ?>
                                                                    </span>
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                                <h5 class="mb-2"><?php echo htmlspecialchars($request['requester_name']); ?></h5>
                                                                <p class="text-muted mb-2">
                                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($request['city']); ?>
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
                                                                    <button class="btn btn-outline-secondary btn-sm" onclick="shareRequest(<?php echo $request['id']; ?>)">
                                                                        <i class="fas fa-share me-1"></i>Share
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
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
    <script>
        // Mobile navigation functionality
        function toggleDonorSidebar() {
            const sidebar = document.getElementById('donorSidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const toggle = document.querySelector('.mobile-nav-toggle');
            
            if (sidebar && overlay && toggle) {
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    toggle.classList.remove('hidden');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.add('show');
                    overlay.classList.add('show');
                    toggle.classList.add('hidden');
                    document.body.style.overflow = 'hidden';
                }
            }
        }
        
        function hideDonorSidebar() {
            const sidebar = document.getElementById('donorSidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const toggle = document.querySelector('.mobile-nav-toggle');
            
            if (sidebar && overlay && toggle) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                toggle.classList.remove('hidden');
                document.body.style.overflow = '';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile navigation initialization
            const mobileToggle = document.querySelector('.mobile-nav-toggle');
            const sidebarClose = document.querySelector('.sidebar-close');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', toggleDonorSidebar);
            }
            
            if (sidebarClose) {
                sidebarClose.addEventListener('click', hideDonorSidebar);
            }
            
            if (overlay) {
                overlay.addEventListener('click', hideDonorSidebar);
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 767.98) {
                    hideDonorSidebar();
                }
            });
            
            // Section navigation
            const navLinks = document.querySelectorAll('[data-section]');
            const sections = document.querySelectorAll('.content-section');
            
            // Initialize - show dashboard section
            showSection('dashboard');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sectionName = this.getAttribute('data-section');
                    showSection(sectionName);
                    
                    // Update active nav link
                    navLinks.forEach(nl => nl.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            function showSection(sectionName) {
                sections.forEach(section => {
                    section.style.display = 'none';
                });
                
                const targetSection = document.getElementById(sectionName + '-section');
                if (targetSection) {
                    targetSection.style.display = 'block';
                }
            }
            
            // Auto-refresh every 5 minutes
            setTimeout(function() {
                window.location.reload();
            }, 300000);
            
            // Update time display
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleString();
                // Update any time displays if needed
            }
            
            // Call updateTime every minute
            setInterval(updateTime, 60000);
            
            // Add click tracking for phone calls
            document.querySelectorAll('a[href^="tel:"]').forEach(link => {
                link.addEventListener('click', function() {
                    // Track phone call clicks for analytics
                    console.log('Phone call initiated:', this.href);
                });
            });
        });
        
        // Dashboard Functions
        function addDonationRecord() {
            window.location.href = 'add-donation.php';
        }
        
        function viewDonationDetails(donationId) {
            window.location.href = 'donation-details.php?id=' + donationId;
        }
        
        // Section Navigation Functions
        function showHistorySection() {
            document.getElementById('dashboard-section').style.display = 'none';
            document.getElementById('history-section').style.display = 'block';
        }
        
        function showDashboardSection() {
            document.getElementById('history-section').style.display = 'none';
            document.getElementById('dashboard-section').style.display = 'block';
        }
        
        function applyFilters() {
            const bloodGroup = document.getElementById('bloodGroupFilter').value;
            const urgency = document.getElementById('urgencyFilter').value;
            const city = document.getElementById('cityFilter').value;
            
            // Build URL with filters
            const params = new URLSearchParams();
            if (bloodGroup) params.append('blood_group', bloodGroup);
            if (urgency) params.append('urgency', urgency);
            if (city) params.append('city', city);
            
            window.location.href = 'blood-requests.php?' + params.toString();
        }
        
        function shareRequest(requestId) {
            const shareData = {
                title: 'Blood Donation Request',
                text: 'Urgent blood donation needed! Please help save a life.',
                url: window.location.origin + '/request-details/' + requestId
            };
            
            if (navigator.share) {
                navigator.share(shareData);
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(shareData.url).then(() => {
                    alert('Request link copied to clipboard!');
                });
            }
        }
        
        // Unmark available confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const unmarkForm = document.querySelector('form[action=""] button[name="unmark_available"]');
            if (unmarkForm) {
                unmarkForm.closest('form').addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to mark yourself as unavailable for blood donation?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
    
    <?php endif; ?>
</body>
</html>
