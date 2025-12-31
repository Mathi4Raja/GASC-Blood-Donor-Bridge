<?php
/**
 * Secure Session Management
 * Handles session initialization independently of database connections
 */

// Set timezone to IST for consistent timestamps
require_once __DIR__ . '/timezone.php';

if (!function_exists('initSecureSession')) {
    function initSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Fixed session timeout: 10 minutes
            $sessionTimeoutSeconds = 10 * 60;
            
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS in production
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', $sessionTimeoutSeconds);
            
            // Start session
            session_start();
            
            // Check for session timeout
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > $sessionTimeoutSeconds) {
                    // Session expired
                    session_unset();
                    session_destroy();
                    session_start();
                    $_SESSION['session_expired'] = true;
                }
            }
            $_SESSION['last_activity'] = time();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
                session_regenerate_id(true);
            } elseif (time() - $_SESSION['created'] > $sessionTimeoutSeconds) {
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

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout() {
        // Check if session has been marked as expired
        if (isset($_SESSION['session_expired'])) {
            unset($_SESSION['session_expired']);
            return 'Your session has expired. Please log in again.';
        }
        
        // Check timeout based on last activity
        if (isset($_SESSION['last_activity'])) {
            // Fixed session timeout: 10 minutes
            $sessionTimeoutSeconds = 10 * 60;
            $timeSinceActivity = time() - $_SESSION['last_activity'];
            
            if ($timeSinceActivity > $sessionTimeoutSeconds) {
                // Session has timed out
                $_SESSION['session_expired'] = true;
                destroySession();
                return 'Your session has expired due to inactivity. Please log in again.';
            }
            
            // Update last activity on each check
            $_SESSION['last_activity'] = time();
        }
        
        return false;
    }
}

// Auto-initialize secure session when this file is included
initSecureSession();
?>
