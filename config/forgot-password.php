<?php
/**
 * DEPRECATED: This file has been replaced with secure, role-specific password reset pages
 * 
 * For security reasons, this shared password reset functionality has been replaced with:
 * - /donor/forgot-password.php (for donors)
 * - /admin/forgot-password.php (for admins/moderators)
 * 
 * This redirect ensures old links still work while directing users to secure pages.
 */

// Determine redirect based on any existing token or default to donor
$token = $_GET['token'] ?? '';
$redirectTo = '../donor/forgot-password.php';

if (!empty($token)) {
    // Check token to determine user type
    require_once 'database.php';
    $db = new Database();
    $sql = "SELECT user_type FROM users WHERE reset_token = ? AND reset_token_expires > NOW()";
    $result = $db->query($sql, [$token]);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['user_type'] === 'admin' || $user['user_type'] === 'moderator') {
            $redirectTo = '../admin/forgot-password.php';
        }
    }
    
    // Preserve token and step in redirect
    $redirectTo .= '?step=' . ($_GET['step'] ?? '1') . '&token=' . urlencode($token);
} else {
    // No token - redirect to appropriate reset page based on referrer or default to donor
    $redirectTo = '../donor/forgot-password.php';
}

// Log the redirect for security monitoring
if (function_exists('logActivity')) {
    logActivity(null, 'deprecated_reset_redirect', "Redirected from deprecated reset page to: $redirectTo");
}

// Perform redirect
header("Location: $redirectTo");
exit;
?>