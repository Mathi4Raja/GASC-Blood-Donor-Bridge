<?php
// Initialize secure session BEFORE database connection
require_once '../config/session.php';

// Simple session-based authentication for requestors
if (!isset($_SESSION['requestor_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Now safely connect to database
require_once '../config/database.php';
require_once '../config/email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Validate input
        $requesterName = sanitizeInput($_POST['requester_name'] ?? '');
        $requesterEmail = sanitizeInput($_POST['requester_email'] ?? '');
        $requesterPhone = sanitizeInput($_POST['requester_phone'] ?? '');
        $bloodGroup = sanitizeInput($_POST['blood_group'] ?? '');
        $urgency = sanitizeInput($_POST['urgency'] ?? '');
        $details = sanitizeInput($_POST['details'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $unitsNeeded = intval($_POST['units_needed'] ?? 1);
        
        // Validation
        if (empty($requesterName) || empty($requesterEmail) || empty($requesterPhone) || 
            empty($bloodGroup) || empty($urgency) || empty($details) || empty($city)) {
            throw new Exception('All required fields must be filled.');
        }
        
        if (!isValidEmail($requesterEmail)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        if (!isValidPhone($requesterPhone)) {
            throw new Exception('Please enter a valid 10-digit phone number.');
        }
        
        if (!isValidBloodGroup($bloodGroup)) {
            throw new Exception('Please select a valid blood group.');
        }
        
        if (!in_array($urgency, ['Critical', 'Urgent', 'Normal'])) {
            throw new Exception('Please select a valid urgency level.');
        }
        
        if ($unitsNeeded < 1 || $unitsNeeded > 10) {
            throw new Exception('Units needed must be between 1 and 10.');
        }
        
        // Calculate expiry date based on urgency
        $expiryDays = [
            'Critical' => 1,
            'Urgent' => 3,
            'Normal' => 7
        ];
        
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays[$urgency]} days"));
        
        // Insert blood request
        $db = new Database();
        $sql = "INSERT INTO blood_requests (requester_name, requester_email, requester_phone, blood_group, urgency, details, city, units_needed, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sssssssis', 
            $requesterName, $requesterEmail, $requesterPhone, $bloodGroup, 
            $urgency, $details, $city, $unitsNeeded, $expiresAt
        );
        
        if ($stmt->execute()) {
            $requestId = $db->lastInsertId();
            
            // Get available donors count
            $donorsQuery = "SELECT COUNT(*) as donor_count FROM users 
                           WHERE blood_group = ? 
                           AND is_available = TRUE AND is_verified = TRUE AND is_active = TRUE AND user_type = 'donor'";
            $donorsResult = $db->query($donorsQuery, [$bloodGroup]);
            $donorCount = $donorsResult->fetch_assoc()['donor_count'];
            
            // Send confirmation email to requester
            $emailSubject = "GASC Blood Bridge - Blood Request Received";
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>GASC Blood Bridge</h1>
                </div>
                <div style='padding: 30px; background: #f8f9fa;'>
                    <h2>Blood Request Received</h2>
                    <p>Dear $requesterName,</p>
                    <p>Your blood request has been successfully submitted. Here are the details:</p>
                    <div style='background: white; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0;'>
                        <p><strong>Request ID:</strong> #$requestId</p>
                        <p><strong>Blood Group:</strong> $bloodGroup</p>
                        <p><strong>Urgency:</strong> $urgency</p>
                        <p><strong>Units Needed:</strong> $unitsNeeded</p>
                        <p><strong>City:</strong> $city</p>
                        <p><strong>Available Donors:</strong> $donorCount</p>
                    </div>
                    <p>Our team will contact available donors and get back to you soon.</p>
                    <p>You can track your request status by visiting our website with your request ID.</p>
                    <hr style='margin: 30px 0; border: 1px solid #dee2e6;'>
                    <p style='color: #6c757d; font-size: 14px;'>
                        Best regards,<br>
                        GASC Blood Bridge Team<br>
                        Emergency Helpline: +91-9999999999
                    </p>
                </div>
            </div>
            ";
            
            sendEmail($requesterEmail, $emailSubject, $emailBody);
            
            // Send notifications to eligible donors
            require_once '../config/notifications.php';
            $notificationResult = notifyDonorsForBloodRequest($requestId);
            
            if ($notificationResult && is_array($notificationResult)) {
                logActivity(null, 'blood_request_notifications', 
                    "Request #$requestId notifications: {$notificationResult['donors_notified']} donors, " .
                    "{$notificationResult['emails_sent']} emails sent");
            }
            
            // Log activity
            logActivity(null, 'blood_request_created', "New blood request: $bloodGroup in $city (Request ID: $requestId)");
            
            echo json_encode([
                'success' => true,
                'message' => 'Blood request submitted successfully!',
                'request_id' => $requestId,
                'donor_count' => $donorCount
            ]);
            
        } else {
            throw new Exception('Failed to submit blood request. Please try again.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity(null, 'blood_request_failed', $error . " - Email: " . ($requesterEmail ?? 'unknown'));
        
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
