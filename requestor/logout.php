<?php
// Initialize secure session BEFORE database connection
require_once '../config/session.php';

if (isset($_SESSION['requestor_email'])) {
    // Store email for logging before destroying session
    $requestor_email = $_SESSION['requestor_email'];
    
    // Destroy requestor session
    unset($_SESSION['requestor_email']);
    unset($_SESSION['requestor_login_time']);
    
    // Now connect to database for logging
    require_once '../config/database.php';
    logActivity(null, 'requestor_logout', "Requestor logged out: " . $requestor_email);
}

header('Location: login.php?success=' . urlencode('You have been logged out successfully.'));
exit;
?>
