<?php
require_once 'config/database.php';

// Start session if not already started
startSecureSession();

// Log the logout
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'user_logout', $_SESSION['user_type'] . ' logged out');
}

// Destroy session
destroySession();

// Redirect to home page
header('Location: index.php');
exit;
?>
