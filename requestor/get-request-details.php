<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['requestor_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$requestId = intval($_GET['id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    $db = new Database();
    
    // Get request details - ensure it belongs to the current requestor
    $sql = "SELECT * FROM blood_requests WHERE id = ? AND requester_email = ?";
    $result = $db->query($sql, [$requestId, $_SESSION['requestor_email']]);
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    $request = $result->fetch_assoc();
    
    // Get available donors count
    $donorsQuery = "SELECT COUNT(*) as donor_count FROM users 
                   WHERE blood_group = ? AND city = ? AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE AND user_type = 'donor'";
    $donorsResult = $db->query($donorsQuery, [$request['blood_group'], $request['city']]);
    $donorCount = $donorsResult->fetch_assoc()['donor_count'];
    
    // Generate HTML content
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-danger mb-3">Request Information</h6>
            <table class="table table-borderless">
                <tr>
                    <td><strong>Request ID:</strong></td>
                    <td>#' . $request['id'] . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span class="badge bg-' . ($request['status'] === 'Active' ? 'success' : ($request['status'] === 'Fulfilled' ? 'info' : 'secondary')) . '">' . $request['status'] . '</span></td>
                </tr>
                <tr>
                    <td><strong>Blood Group:</strong></td>
                    <td><span class="badge bg-danger">' . $request['blood_group'] . '</span></td>
                </tr>
                <tr>
                    <td><strong>Units Needed:</strong></td>
                    <td>' . $request['units_needed'] . '</td>
                </tr>
                <tr>
                    <td><strong>Urgency:</strong></td>
                    <td><span class="text-' . ($request['urgency'] === 'Critical' ? 'danger' : ($request['urgency'] === 'Urgent' ? 'warning' : 'success')) . '">' . $request['urgency'] . '</span></td>
                </tr>
                <tr>
                    <td><strong>City:</strong></td>
                    <td>' . htmlspecialchars($request['city']) . '</td>
                </tr>
                <tr>
                    <td><strong>Available Donors:</strong></td>
                    <td><span class="badge bg-info">' . $donorCount . ' donors</span></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-danger mb-3">Timeline</h6>
            <table class="table table-borderless">
                <tr>
                    <td><strong>Created:</strong></td>
                    <td>' . date('M d, Y H:i', strtotime($request['created_at'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Last Updated:</strong></td>
                    <td>' . date('M d, Y H:i', strtotime($request['updated_at'])) . '</td>
                </tr>';
    
    if ($request['status'] === 'Active') {
        $html .= '
                <tr>
                    <td><strong>Expires At:</strong></td>
                    <td class="text-warning">' . date('M d, Y H:i', strtotime($request['expires_at'])) . '</td>
                </tr>';
    }
    
    $html .= '
            </table>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-danger mb-3">Request Details</h6>
            <div class="bg-light p-3 rounded">
                ' . nl2br(htmlspecialchars($request['details'])) . '
            </div>
        </div>
    </div>';
    
    if ($request['status'] === 'Active') {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Your request is currently active and our system is notifying available donors in your area.
                </div>
            </div>
        </div>';
    } elseif ($request['status'] === 'Fulfilled') {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Great news! Your blood request has been fulfilled.
                </div>
            </div>
        </div>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading request details: ' . $e->getMessage()
    ]);
}
?>
