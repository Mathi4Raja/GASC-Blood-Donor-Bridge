<?php
// Include sidebar utilities
require_once __DIR__ . '/sidebar-utils.php';

// Check for force refresh parameter
if (isset($_GET['refresh_sidebar'])) {
    clearSidebarCache();
}

// Get donor information for sidebar - always fetch fresh data for consistency
// Use session cache to avoid repeated database calls within the same session
$sidebarDonor = null;
$sidebarCanDonate = false;

// Cache key for this session
$cacheKey = 'sidebar_donor_' . ($_SESSION['user_id'] ?? 'unknown');

// Check if we have cached data and it's still valid (cache for 30 seconds)
if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_time'])) {
    $cacheAge = time() - $_SESSION[$cacheKey . '_time'];
    if ($cacheAge < 30) { // 30 seconds cache for better responsiveness
        $sidebarDonor = $_SESSION[$cacheKey];
        $sidebarCanDonate = $_SESSION[$cacheKey . '_can_donate'] ?? false;
    }
}

// If no valid cache, fetch fresh data
if (!$sidebarDonor) {
    try {
        // Ensure we have a user session
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("No user session found");
        }
        
        $db = new Database();
        $sql = "SELECT id, roll_no, name, email, phone, class, blood_group, city, gender, last_donation_date, is_available, is_verified 
                FROM users WHERE id = ? AND user_type = 'donor'";
        $result = $db->query($sql, [$_SESSION['user_id']]);
        $sidebarDonor = $result->fetch_assoc();
        
        if ($sidebarDonor) {
            // Check availability status
            $sidebarCanDonate = calculateAvailability($sidebarDonor['last_donation_date'], $sidebarDonor['gender']);
            
            // Cache the results
            $_SESSION[$cacheKey] = $sidebarDonor;
            $_SESSION[$cacheKey . '_time'] = time();
            $_SESSION[$cacheKey . '_can_donate'] = $sidebarCanDonate;
        } else {
            // If no donor found, set defaults
            $sidebarDonor = [
                'name' => 'Unknown Donor', 
                'blood_group' => 'N/A', 
                'class' => 'Class not set', 
                'city' => 'City not set', 
                'is_available' => false
            ];
            $sidebarCanDonate = false;
            error_log("Sidebar: No donor found for user_id: " . $_SESSION['user_id']);
        }
    } catch (Exception $e) {
        // Handle error and set defaults
        $sidebarDonor = [
            'name' => 'Error Loading', 
            'blood_group' => 'N/A', 
            'class' => 'Error', 
            'city' => 'Error', 
            'is_available' => false
        ];
        $sidebarCanDonate = false;
        error_log("Sidebar donor fetch error: " . $e->getMessage());
    }
}

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Navigation Toggle -->
<button class="mobile-nav-toggle d-lg-none" type="button" id="mobileNavToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="dashboard-sidebar" id="donorSidebar">
    <!-- Close button for mobile -->
    <button class="sidebar-close d-lg-none" type="button" id="sidebarClose">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="sidebar-content">
        <!-- Profile Card -->
        <div class="profile-card position-relative">
            <div class="availability-badge <?php echo ($sidebarDonor['is_available'] ?? false) ? 'available' : 'unavailable'; ?>"></div>
            <div class="d-flex align-items-center">
                <div class="blood-group-display me-3">
                    <?php echo htmlspecialchars($sidebarDonor['blood_group'] ?? 'N/A'); ?>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 text-white"><?php echo htmlspecialchars($sidebarDonor['name'] ?? 'Unknown Donor'); ?></h6>
                    <small class="text-white-50"><?php echo htmlspecialchars($sidebarDonor['class'] ?? 'Class not set'); ?></small>
                    <br>
                    <small class="text-white-50"><?php echo htmlspecialchars($sidebarDonor['city'] ?? 'City not set'); ?></small>
                </div>
            </div>
            <div class="mt-3">
                <!-- Availability Status (User Controlled) -->
                <small class="text-white-50 d-block mb-1">
                    Availability: 
                    <span class="fw-bold" style="color: <?php echo ($sidebarDonor['is_available'] ?? false) ? '#00ff88' : '#ffffff'; ?> !important;">
                        <?php echo ($sidebarDonor['is_available'] ?? false) ? 'Available' : 'Not Available'; ?>
                    </span>
                </small>
                
                <!-- Donation Eligibility (Date Based) -->
                <small class="text-white-50 d-block">
                    Eligibility: 
                    <span class="fw-bold" style="color: <?php echo ($sidebarCanDonate ?? false) ? '#00d4ff' : '#ffd93d'; ?> !important;">
                        <?php echo ($sidebarCanDonate ?? false) ? 'Eligible to Donate' : 'Not Eligible Yet'; ?>
                    </span>
                </small>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="sidebar-nav">
            <a class="nav-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo ($currentPage === 'edit-profile.php') ? 'active' : ''; ?>" href="edit-profile.php">
                <i class="fas fa-user me-2"></i>My Profile
            </a>
            <a class="nav-link <?php echo ($currentPage === 'add-donation.php') ? 'active' : ''; ?>" href="add-donation.php">
                <i class="fas fa-plus-circle me-2"></i>Add Donation
            </a>
            <a class="nav-link <?php echo ($currentPage === 'donation-history.php') ? 'active' : ''; ?>" href="donation-history.php">
                <i class="fas fa-history me-2"></i>Donation History
            </a>
            <a class="nav-link <?php echo ($currentPage === 'blood-requests.php') ? 'active' : ''; ?>" href="blood-requests.php">
                <i class="fas fa-hand-holding-heart me-2"></i>Blood Requests
            </a>
            <a class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i>Settings
            </a>
            
            <hr class="sidebar-divider">
            
            <a class="nav-link logout-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>
</div>
