<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email) || !isValidEmail($email)) {
        header('Location: login.php?error=' . urlencode('Please enter a valid email address.'));
        exit;
    }
    
    try {
        $db = new Database();
        
        // Check if this email has made any blood requests
        $sql = "SELECT DISTINCT requester_email FROM blood_requests WHERE requester_email = ?";
        $result = $db->query($sql, [$email]);
        
        if ($result->num_rows === 0) {
            header('Location: login.php?error=' . urlencode('No blood requests found for this email address.'));
            exit;
        }
        
        // Create simple session for requestor
        $_SESSION['requestor_email'] = $email;
        $_SESSION['requestor_login_time'] = time();
        
        // Log the access
        logActivity(null, 'requestor_access', "Requestor accessed dashboard: $email");
        
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        header('Location: login.php?error=' . urlencode('Unable to access dashboard. Please try again.'));
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
?>
