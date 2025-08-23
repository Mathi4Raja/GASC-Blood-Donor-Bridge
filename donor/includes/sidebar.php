<?php
// Get donor information for sidebar
if (!isset($donor) || !$donor) {
    try {
        // Ensure we have a user session
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("No user session found");
        }
        
        $db = new Database();
        $sql = "SELECT id, roll_no, name, email, phone, class, blood_group, city, gender, last_donation_date, is_available, is_verified 
                FROM users WHERE id = ? AND user_type = 'donor'";
        $result = $db->query($sql, [$_SESSION['user_id']]);
        $donor = $result->fetch_assoc();
        
        if ($donor) {
            // Check availability status
            $canDonate = calculateAvailability($donor['last_donation_date'], $donor['gender']);
        } else {
            // If no donor found, set defaults
            $donor = [
                'name' => 'Unknown Donor', 
                'blood_group' => 'N/A', 
                'class' => 'Class not set', 
                'city' => 'City not set', 
                'is_available' => false
            ];
            $canDonate = false;
            error_log("No donor found for user_id: " . $_SESSION['user_id']);
        }
    } catch (Exception $e) {
        // Handle error and set defaults
        $donor = [
            'name' => 'Error Loading', 
            'blood_group' => 'N/A', 
            'class' => 'Error', 
            'city' => 'Error', 
            'is_available' => false
        ];
        $canDonate = false;
        // Log the error for debugging
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
            <div class="availability-badge <?php echo ($donor['is_available'] ?? false) ? 'available' : 'unavailable'; ?>"></div>
            <div class="d-flex align-items-center">
                <div class="blood-group-display me-3">
                    <?php echo htmlspecialchars($donor['blood_group'] ?? 'N/A'); ?>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 text-white"><?php echo htmlspecialchars($donor['name'] ?? 'Unknown Donor'); ?></h6>
                    <small class="text-white-50"><?php echo htmlspecialchars($donor['class'] ?? 'Class not set'); ?></small>
                    <br>
                    <small class="text-white-50"><?php echo htmlspecialchars($donor['city'] ?? 'City not set'); ?></small>
                </div>
            </div>
            <div class="mt-3">
                <small class="text-white-50">
                    Status: 
                    <span class="<?php echo ($canDonate ?? false) ? 'text-success' : 'text-warning'; ?>">
                        <?php echo ($canDonate ?? false) ? 'Eligible to Donate' : 'Not Eligible Yet'; ?>
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
