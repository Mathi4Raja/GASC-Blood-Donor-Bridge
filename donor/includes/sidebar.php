<?php
// Get donor information for sidebar
if (!isset($donor) || !$donor) {
    try {
        $db = new Database();
        $sql = "SELECT * FROM users WHERE id = ? AND user_type = 'donor'";
        $result = $db->query($sql, [$_SESSION['user_id']]);
        $donor = $result->fetch_assoc();
        
        if ($donor) {
            // Check availability status
            $canDonate = calculateAvailability($donor['last_donation_date'], $donor['gender']);
        }
    } catch (Exception $e) {
        // Handle error silently for sidebar
        $donor = ['name' => 'Donor', 'blood_group' => 'N/A', 'class' => 'N/A', 'city' => 'N/A', 'is_available' => false];
        $canDonate = false;
    }
}

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

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
                    <span class="<?php echo ($canDonate ?? false) ? 'text-success' : 'text-warning'; ?>">
                        <?php echo ($canDonate ?? false) ? 'Eligible to Donate' : 'Not Eligible Yet'; ?>
                    </span>
                </small>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="nav flex-column">
            <a class="nav-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link <?php echo ($currentPage === 'edit-profile.php') ? 'active' : ''; ?>" href="edit-profile.php">
                <i class="fas fa-user me-2"></i>My Profile
            </a>
            <a class="nav-link <?php echo ($currentPage === 'donation-details.php') ? 'active' : ''; ?>" href="#" onclick="showHistorySection(); return false;">
                <i class="fas fa-history me-2"></i>Donation History
            </a>
            <a class="nav-link <?php echo ($currentPage === 'blood-requests.php') ? 'active' : ''; ?>" href="blood-requests.php">
                <i class="fas fa-hand-holding-heart me-2"></i>Blood Requests
            </a>
            <a class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i>Settings
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>
</div>
