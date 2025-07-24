<?php
require_once '../config/database.php';

// Check if user is logged in as admin or moderator
requireRole(['admin', 'moderator']);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All password fields are required.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New password and confirmation do not match.');
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception('New password must be at least 6 characters long.');
        }
        
        $db = new Database();
        
        // Get current user's password hash
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            throw new Exception('Current password is incorrect.');
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('si', $new_password_hash, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'password_changed', ucfirst($_SESSION['user_type']) . ' password changed successfully');
            $response['success'] = true;
            $response['message'] = 'Password changed successfully!';
        } else {
            throw new Exception('Failed to update password.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        logActivity($_SESSION['user_id'], 'password_change_failed', $e->getMessage());
    }
}

// Return JSON response for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// For non-AJAX requests, redirect back with message
if ($response['success']) {
    $_SESSION['success_message'] = $response['message'];
} else {
    $_SESSION['error_message'] = $response['message'];
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit;
?>
