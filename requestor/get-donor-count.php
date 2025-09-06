<?php
// Initialize secure session BEFORE database connection
require_once '../config/session.php';

header('Content-Type: application/json');

// Check for session timeout first
$timeoutMessage = checkSessionTimeout();
if ($timeoutMessage) {
    // Session has expired
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'timeout' => true]);
    exit;
}

// Check if requestor is authenticated
if (!isset($_SESSION['requestor_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Now safely connect to database
require_once '../config/database.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['blood_group']) || empty($input['blood_group'])) {
        echo json_encode(['success' => false, 'message' => 'Blood group is required']);
        exit;
    }
    
    $bloodGroup = $input['blood_group'];
    
    // Get current donor count for the blood group
    $db = new Database();
    $query = "SELECT COUNT(*) as donor_count FROM users 
              WHERE blood_group = ? 
              AND is_available = TRUE 
              AND is_verified = TRUE 
              AND is_active = TRUE 
              AND user_type = 'donor'";
    
    $result = $db->query($query, [$bloodGroup]);
    $donorData = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'donor_count' => (int)$donorData['donor_count'],
        'blood_group' => $bloodGroup
    ]);
    
} catch (Exception $e) {
    error_log("Error getting donor count: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to fetch donor count']);
}
?>
