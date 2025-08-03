<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['requestor_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestId = intval($input['request_id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    $db = new Database();
    
    // Verify request belongs to current requestor and is active
    $checkSql = "SELECT id, status FROM blood_requests WHERE id = ? AND requester_email = ? AND status = 'Active'";
    $checkResult = $db->query($checkSql, [$requestId, $_SESSION['requestor_email']]);
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or cannot be cancelled']);
        exit;
    }
    
    // Update request status to cancelled
    $updateSql = "UPDATE blood_requests SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $db->query($updateSql, [$requestId]);
    
    // Log the cancellation
    logActivity(null, 'request_cancelled', "Request ID $requestId cancelled by requestor: " . $_SESSION['requestor_email']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Request cancelled successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling request: ' . $e->getMessage()
    ]);
}
?>
