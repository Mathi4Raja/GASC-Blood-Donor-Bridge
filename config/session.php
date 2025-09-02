<?php
/**
 * Secure Session Management
 * Handles session initialization independently of database connections
 */

if (!function_exists('initSecureSession')) {
    function initSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS in production
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', 1800); // 30 minutes
            
            // Start session
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
                session_regenerate_id(true);
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
            
            // Initialize CSRF token if not exists
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
        
        return true;
    }
}

// Auto-initialize secure session when this file is included
initSecureSession();
?>
