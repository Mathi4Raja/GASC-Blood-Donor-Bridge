<?php
/**
 * Notification System for Blood Requests
 * Automatically notifies eligible donors when new blood requests are posted
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/system-settings.php';

/**
 * Notify eligible donors about a new blood request
 */
function notifyDonorsForBloodRequest($requestId) {
    try {
        $db = new Database();
        
        // Get blood request details
        $requestSQL = "SELECT * FROM blood_requests WHERE id = ?";
        $requestResult = $db->query($requestSQL, [$requestId]);
        
        if ($requestResult->num_rows === 0) {
            logActivity(null, 'notification_error', "Blood request not found: $requestId");
            return false;
        }
        
        $request = $requestResult->fetch_assoc();
        
        // Find eligible donors
        $donorSQL = "SELECT id, name, email, phone, city 
                     FROM users 
                     WHERE user_type = 'donor' 
                     AND blood_group = ? 
                     AND is_available = TRUE 
                     AND is_verified = TRUE 
                     AND is_active = TRUE 
                     AND city = ?
                     ORDER BY 
                        CASE WHEN city = ? THEN 1 ELSE 2 END,
                        ISNULL(last_donation_date), last_donation_date ASC
                     LIMIT 50"; // Limit to 50 donors to avoid spam
        
        $donorResult = $db->query($donorSQL, [$request['blood_group'], $request['city'], $request['city']]);
        
        if ($donorResult->num_rows === 0) {
            logActivity(null, 'notification_info', "No eligible donors found for request: $requestId");
            return ['success' => true, 'donors_notified' => 0, 'emails_sent' => 0];
        }
        
        $donors = $donorResult->fetch_all(MYSQLI_ASSOC);
        $donorsCount = count($donors);
        
        // Check if email notifications are enabled
        if (!SystemSettings::isEmailNotificationsEnabled()) {
            // Log the notification details instead of sending emails
            $logDetails = "Email notifications disabled - Would have notified $donorsCount donors for request: $requestId | " .
                         "Blood Group: {$request['blood_group']} | City: {$request['city']} | Urgency: {$request['urgency']}";
            logActivity(null, 'notification_logged_fallback', $logDetails);
            
            // Log to email log file as fallback
            $emailLogEntry = date('Y-m-d H:i:s') . " [NOTIFICATION DISABLED] Request ID: $requestId | " .
                           "Blood Group: {$request['blood_group']} | City: {$request['city']} | " .
                           "Eligible Donors: $donorsCount | Urgency: {$request['urgency']}" . PHP_EOL;
            safeLogToFile(__DIR__ . '/../logs/emails.log', $emailLogEntry);
            
            return ['success' => true, 'donors_notified' => $donorsCount, 'emails_sent' => 0, 'logged_instead' => true];
        }
        
        $emailsSent = 0;
        
        // Send notifications to eligible donors
        foreach ($donors as $donor) {
            // Send email notification
            if (sendBloodRequestNotification($donor['email'], $donor['name'], $request)) {
                $emailsSent++;
            }
            
            // Small delay to avoid overwhelming services
            usleep(100000); // 0.1 second delay
        }
        
        // Log notification results
        logActivity(null, 'blood_request_notifications_sent', 
            "Request #$requestId: Notified $donorsCount donors - $emailsSent emails");
        
        // Update request with notification info
        $updateSQL = "UPDATE blood_requests 
                      SET updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
        $db->query($updateSQL, [$requestId]);
        
        return [
            'success' => true,
            'donors_notified' => $donorsCount,
            'emails_sent' => $emailsSent
        ];
        
    } catch (Exception $e) {
        logActivity(null, 'notification_error', "Error notifying donors for request $requestId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to requestor about request status update
 */
function notifyRequestorStatusUpdate($requestId, $newStatus) {
    // Check if email notifications are enabled
    if (!SystemSettings::isEmailNotificationsEnabled()) {
        try {
            $db = new Database();
            
            // Get request details for logging
            $sql = "SELECT * FROM blood_requests WHERE id = ?";
            $result = $db->query($sql, [$requestId]);
            
            if ($result->num_rows === 0) {
                logActivity(null, 'notification_error', "Request not found for status update notification: $requestId");
                return false;
            }
            
            $request = $result->fetch_assoc();
            
            // Log notification details when email is disabled
            $logMessage = "NOTIFICATION DISABLED - Status Update Request:\n";
            $logMessage .= "Request ID: $requestId\n";
            $logMessage .= "Requestor: {$request['requester_name']} ({$request['requester_email']})\n";
            $logMessage .= "Blood Group: {$request['blood_group']}\n";
            $logMessage .= "Units Needed: {$request['units_needed']}\n";
            $logMessage .= "New Status: $newStatus\n";
            $logMessage .= "Location: {$request['city']}\n";
            $logMessage .= "Original Request Date: {$request['created_at']}\n";
            $logMessage .= "Status Update Time: " . date('Y-m-d H:i:s') . "\n";
            $logMessage .= "----------------------------------------\n";
            
            // Write to email log file
            safeLogToFile(__DIR__ . '/../logs/emails.log', $logMessage);
            
            logActivity(null, 'notification_logged', "Email notifications disabled - logged status update for request: $requestId");
            return ['status' => 'logged', 'emails_sent' => 0, 'logged' => true];
            
        } catch (Exception $e) {
            logActivity(null, 'notification_error', "Error logging status update notification for request $requestId: " . $e->getMessage());
            return false;
        }
    }
    try {
        $db = new Database();
        
        // Get request details
        $sql = "SELECT * FROM blood_requests WHERE id = ?";
        $result = $db->query($sql, [$requestId]);
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $request = $result->fetch_assoc();
        
        $subject = "Blood Request Status Update - GASC Blood Bridge";
        $statusMessage = '';
        $statusColor = '#6b7280';
        
        switch ($newStatus) {
            case 'Fulfilled':
                $statusMessage = "Great news! Your blood request has been fulfilled. Thank you for using our service.";
                $statusColor = '#10b981';
                break;
            case 'Cancelled':
                $statusMessage = "Your blood request has been cancelled. If you have any questions, please contact us.";
                $statusColor = '#f59e0b';
                break;
            case 'Expired':
                $statusMessage = "Your blood request has expired. You can submit a new request if still needed.";
                $statusColor = '#6b7280';
                break;
            default:
                $statusMessage = "Your blood request status has been updated to: $newStatus";
        }
        
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #dc2626, #991b1b); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>GASC Blood Bridge</h1>
                <p style='color: #fee2e2; margin: 10px 0 0 0;'>Request Status Update</p>
            </div>
            
            <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <h2 style='color: #dc2626; margin-bottom: 20px;'>Hello {$request['requester_name']},</h2>
                
                <div style='background: #f3f4f6; border-left: 4px solid $statusColor; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0;'>
                    <h3 style='color: $statusColor; margin: 0 0 15px 0;'>Request #{$request['id']} - $newStatus</h3>
                    <p style='margin: 0; color: #374151; font-size: 16px; line-height: 1.6;'>$statusMessage</p>
                </div>
                
                <div style='background: #fef2f2; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='color: #dc2626; margin: 0 0 15px 0;'>Request Details:</h4>
                    <p style='margin: 0; color: #374151; line-height: 1.6;'>
                        <strong>Blood Group:</strong> {$request['blood_group']}<br>
                        <strong>Units Needed:</strong> {$request['units_needed']}<br>
                        <strong>Urgency:</strong> {$request['urgency']}<br>
                        <strong>Location:</strong> {$request['city']}<br>
                        <strong>Submitted:</strong> " . date('M d, Y H:i', strtotime($request['created_at'])) . "
                    </p>
                </div>
                
                <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                    Thank you for using GASC Blood Bridge.<br>
                    <strong>GASC Blood Bridge Team</strong><br>
                    Connecting donors with those in need
                </p>
            </div>
        </div>
        ";
        
        $emailSent = sendEmailSMTP($request['requester_email'], $subject, $body, true);
        
        // For critical updates, email notifications are sent immediately
        // For critical requests, you might want to add additional urgent notifications here
        // For now, we'll rely on email notifications only
        
        logActivity(null, 'requestor_notified', "Requestor notified for request #$requestId - Status: $newStatus");
        
        return $emailSent;
        
    } catch (Exception $e) {
        logActivity(null, 'requestor_notification_error', "Error notifying requestor for request $requestId: " . $e->getMessage());
        return false;
    }
}

/**
 * Send reminder to donors who can donate again
 */
function sendDonationEligibilityReminders() {
    // Check if email notifications are enabled
    if (!SystemSettings::isEmailNotificationsEnabled()) {
        try {
            $db = new Database();
            
            // Count eligible donors for logging
            $sql = "SELECT COUNT(*) as count 
                    FROM users 
                    WHERE user_type = 'donor' 
                    AND is_active = TRUE 
                    AND is_verified = TRUE 
                    AND last_donation_date IS NOT NULL
                    AND (
                        (gender = 'Male' AND last_donation_date = DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) OR
                        (gender = 'Female' AND last_donation_date = DATE_SUB(CURDATE(), INTERVAL 4 MONTH))
                    )";
            $result = $db->query($sql);
            $eligibleCount = $result->fetch_assoc()['count'];
            
            // Log notification details when email is disabled
            $logMessage = "NOTIFICATION DISABLED - Donation Eligibility Reminders:\n";
            $logMessage .= "Eligible Donors Count: $eligibleCount\n";
            $logMessage .= "Reminder Type: Donation Eligibility (3/4 months after last donation)\n";
            $logMessage .= "Reminder Time: " . date('Y-m-d H:i:s') . "\n";
            $logMessage .= "Note: These donors are now eligible to donate again\n";
            $logMessage .= "----------------------------------------\n";
            
            // Write to email log file
            safeLogToFile(__DIR__ . '/../logs/emails.log', $logMessage);
            
            logActivity(null, 'eligibility_reminders_logged', "Email notifications disabled - logged $eligibleCount eligibility reminders");
            return ['status' => 'logged', 'reminders_sent' => 0, 'logged' => $eligibleCount];
            
        } catch (Exception $e) {
            logActivity(null, 'eligibility_reminder_error', "Error logging eligibility reminders: " . $e->getMessage());
            return 0;
        }
    }
    try {
        $db = new Database();
        
        // Find donors who became eligible to donate in the last week
        $sql = "SELECT id, name, email, phone, gender, last_donation_date 
                FROM users 
                WHERE user_type = 'donor' 
                AND is_active = TRUE 
                AND is_verified = TRUE 
                AND last_donation_date IS NOT NULL
                AND (
                    (gender = 'Male' AND last_donation_date = DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) OR
                    (gender = 'Female' AND last_donation_date = DATE_SUB(CURDATE(), INTERVAL 4 MONTH))
                )";
                
        $result = $db->query($sql);
        $remindersSetn = 0;
        
        while ($donor = $result->fetch_assoc()) {
            // Send email reminder
            $subject = "You're Eligible to Donate Again! - GASC Blood Bridge";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #10b981, #059669); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='color: white; margin: 0; font-size: 28px;'>GASC Blood Bridge</h1>
                    <p style='color: #d1fae5; margin: 10px 0 0 0;'>Donation Eligibility Reminder</p>
                </div>
                
                <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <h2 style='color: #10b981; margin-bottom: 20px;'>Great News, {$donor['name']}!</h2>
                    
                    <p style='color: #374151; font-size: 16px; line-height: 1.6;'>
                        You're now eligible to donate blood again! Your last donation was on " . date('M d, Y', strtotime($donor['last_donation_date'])) . ".
                    </p>
                    
                    <div style='background: #ecfdf5; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0;'>
                        <h3 style='color: #065f46; margin: 0 0 15px 0;'>Ready to Save Lives Again?</h3>
                        <p style='margin: 0; color: #047857; font-size: 16px; line-height: 1.6;'>
                            Your contribution can make a real difference. Consider marking yourself as available for donation requests.
                        </p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://{$_SERVER['HTTP_HOST']}/GASC Blood Donor Bridge/donor/login.php' 
                           style='background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                            Update My Availability
                        </a>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                        Thank you for being a lifesaver!<br>
                        <strong>GASC Blood Bridge Team</strong>
                    </p>
                </div>
            </div>
            ";
            
            if (sendEmailSMTP($donor['email'], $subject, $body, true)) {
                $remindersSetn++;
            }
            
            // Delay to avoid overwhelming email services
            usleep(200000); // 0.2 second delay
        }
        
        logActivity(null, 'donation_reminders_sent', "Sent $remindersSetn donation eligibility reminders");
        return $remindersSetn;
        
    } catch (Exception $e) {
        logActivity(null, 'donation_reminder_error', "Error sending donation reminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Auto-expire old blood requests
 */
function autoExpireBloodRequests() {
    // Check if auto-expire is enabled
    if (!SystemSettings::isAutoExpireRequestsEnabled()) {
        logActivity(null, 'auto_expire_skipped', "Auto-expire requests disabled - skipping");
        return 0;
    }
    try {
        $db = new Database();
        
        // Find expired requests that are still active
        $sql = "SELECT id, requester_email, requester_name, blood_group, city 
                FROM blood_requests 
                WHERE status = 'Active' AND expires_at < NOW()";
        $result = $db->query($sql);
        
        $expiredCount = 0;
        
        while ($request = $result->fetch_assoc()) {
            // Update status to expired
            $updateSQL = "UPDATE blood_requests SET status = 'Expired', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $db->query($updateSQL, [$request['id']]);
            
            // Notify requestor
            notifyRequestorStatusUpdate($request['id'], 'Expired');
            
            $expiredCount++;
        }
        
        if ($expiredCount > 0) {
            logActivity(null, 'auto_expire_requests', "Auto-expired $expiredCount blood requests");
        }
        
        return $expiredCount;
        
    } catch (Exception $e) {
        logActivity(null, 'auto_expire_error', "Error auto-expiring requests: " . $e->getMessage());
        return 0;
    }
}

/**
 * Clean up old logs and notifications (should be run daily)
 */
function cleanupOldData() {
    try {
        $db = new Database();
        
        // Clean old activity logs (keep 90 days)
        $logSQL = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $db->query($logSQL);
        $logsCleaned = $db->lastAffectedRows();
        
        logActivity(null, 'cleanup_completed', "Cleaned $logsCleaned old activity logs");
        
        return [
            'logs_cleaned' => $logsCleaned
        ];
        
    } catch (Exception $e) {
        logActivity(null, 'cleanup_error', "Error during cleanup: " . $e->getMessage());
        return false;
    }
}
?>
