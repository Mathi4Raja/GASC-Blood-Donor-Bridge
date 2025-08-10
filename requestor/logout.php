<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['requestor_email'])) {
    logActivity(null, 'requestor_logout', "Requestor logged out: " . $_SESSION['requestor_email']);
    
    // Destroy requestor session
    unset($_SESSION['requestor_email']);
    unset($_SESSION['requestor_login_time']);
}

header('Location: login.php?success=' . urlencode('You have been logged out successfully.'));
exit;
?>
