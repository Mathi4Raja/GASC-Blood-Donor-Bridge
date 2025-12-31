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
require_once '../config/system-settings.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['blood_group']) || empty($input['blood_group'])) {
        echo json_encode(['success' => false, 'message' => 'Blood group is required']);
        exit;
    }
    
    $bloodGroup = $input['blood_group'];
    $city = $input['city'] ?? '';
    
    // Get compatible donor count using the new compatibility system
    $db = new Database();
    
    if (!empty($city)) {
        // Use the new compatibility function that considers city
        $donorCount = getCompatibleDonorsCount($bloodGroup, $city);
    } else {
        // Get compatible donor blood groups
        $compatibleDonorGroups = getCompatibleDonors($bloodGroup);
        
        if (empty($compatibleDonorGroups)) {
            $donorCount = 0;
        } else {
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($compatibleDonorGroups) - 1) . '?';
            
            $query = "SELECT COUNT(*) as donor_count FROM users 
                      WHERE blood_group IN ($placeholders)
                      AND is_available = TRUE 
                      AND is_verified = TRUE 
                      AND is_active = TRUE 
                      AND email_verified = TRUE
                      AND user_type = 'donor'";
            
            $result = $db->query($query, $compatibleDonorGroups);
            $donorData = $result->fetch_assoc();
            $donorCount = (int)$donorData['donor_count'];
        }
    }
    
    // Get breakdown by compatible blood types for detailed info
    $compatibleGroups = getCompatibleDonors($bloodGroup);
    $breakdown = [];
    
    foreach ($compatibleGroups as $compatibleGroup) {
        $query = "SELECT COUNT(*) as count FROM users 
                  WHERE blood_group = ? 
                  AND is_available = TRUE 
                  AND is_verified = TRUE 
                  AND is_active = TRUE 
                  AND email_verified = TRUE
                  AND user_type = 'donor'";
        
        if (!empty($city)) {
            $query .= " AND city = ?";
            $result = $db->query($query, [$compatibleGroup, $city]);
        } else {
            $result = $db->query($query, [$compatibleGroup]);
        }
        
        $groupData = $result->fetch_assoc();
        if ((int)$groupData['count'] > 0) {
            $breakdown[$compatibleGroup] = (int)$groupData['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'donor_count' => $donorCount,
        'blood_group' => $bloodGroup,
        'city' => $city,
        'compatible_groups' => $compatibleGroups,
        'breakdown' => $breakdown,
        'display_name' => getBloodGroupDisplayName($bloodGroup),
        'matching_mode' => SystemSettings::getBloodMatchingMode(),
        'matching_info' => SystemSettings::getBloodMatchingHelpText()
    ]);
    
} catch (Exception $e) {
    error_log("Error getting donor count: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to fetch donor count']);
}
?>
