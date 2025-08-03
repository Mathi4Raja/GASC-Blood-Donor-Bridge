<?php
/**
 * Scheduled Tasks Simulation
 * This file should be run periodically (via cron job or scheduled task)
 * to handle automatic operations like expiring requests, sending reminders, etc.
 * 
 * For Windows: Use Task Scheduler to run this script every hour
 * For Linux: Add to crontab: 0 * * * * php /path/to/scheduled-tasks.php
 */

require_once '../config/database.php';
require_once '../config/notifications.php';

// Prevent direct browser access for security
if (isset($_SERVER['HTTP_HOST']) && !isset($_GET['manual'])) {
    die('This script should be run via command line or scheduled task.');
}

echo "=== GASC Blood Bridge Scheduled Tasks ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    // 1. Auto-expire old blood requests
    echo "1. Auto-expiring old blood requests..." . PHP_EOL;
    $expiredCount = autoExpireBloodRequests();
    echo "   → Expired $expiredCount requests" . PHP_EOL . PHP_EOL;
    
    // 2. Send donation eligibility reminders (run once daily)
    $currentHour = date('H');
    if ($currentHour == '09') { // Run at 9 AM
        echo "2. Sending donation eligibility reminders..." . PHP_EOL;
        $remindersCount = sendDonationEligibilityReminders();
        echo "   → Sent $remindersCount reminders" . PHP_EOL . PHP_EOL;
    } else {
        echo "2. Skipping donation reminders (not 9 AM)" . PHP_EOL . PHP_EOL;
    }
    
    // 3. Cleanup old data (run once daily at midnight)
    if ($currentHour == '00') { // Run at midnight
        echo "3. Cleaning up old data..." . PHP_EOL;
        $cleanupResult = cleanupOldData();
        if ($cleanupResult) {
            echo "   → Cleaned {$cleanupResult['otps_cleaned']} expired OTPs" . PHP_EOL;
            echo "   → Cleaned {$cleanupResult['logs_cleaned']} old activity logs" . PHP_EOL;
        } else {
            echo "   → Cleanup failed" . PHP_EOL;
        }
        echo PHP_EOL;
    } else {
        echo "3. Skipping cleanup (not midnight)" . PHP_EOL . PHP_EOL;
    }
    
    // 4. Update donor availability based on last donation date
    echo "4. Updating donor availability..." . PHP_EOL;
    $updatedCount = updateDonorAvailability();
    echo "   → Updated $updatedCount donor availability statuses" . PHP_EOL . PHP_EOL;
    
    // 5. Generate daily statistics (run once daily at 11 PM)
    if ($currentHour == '23') { // Run at 11 PM
        echo "5. Generating daily statistics..." . PHP_EOL;
        generateDailyStatistics();
        echo "   → Daily statistics generated" . PHP_EOL . PHP_EOL;
    } else {
        echo "5. Skipping statistics generation (not 11 PM)" . PHP_EOL . PHP_EOL;
    }
    
    echo "=== All tasks completed successfully ===" . PHP_EOL;
    echo "Finished at: " . date('Y-m-d H:i:s') . PHP_EOL;
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    logActivity(null, 'scheduled_tasks_error', $e->getMessage());
    exit(1);
}

/**
 * Update donor availability based on last donation date
 */
function updateDonorAvailability() {
    try {
        $db = new Database();
        
        // Update donors who are now eligible to donate
        $sql = "UPDATE users SET is_available = TRUE 
                WHERE user_type = 'donor' 
                AND is_active = TRUE 
                AND is_verified = TRUE 
                AND is_available = FALSE
                AND last_donation_date IS NOT NULL
                AND (
                    (gender = 'Male' AND last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) OR
                    (gender = 'Female' AND last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 4 MONTH))
                )";
        
        $db->query($sql);
        $updatedCount = $db->lastAffectedRows();
        
        if ($updatedCount > 0) {
            logActivity(null, 'donor_availability_updated', "Updated $updatedCount donors as eligible");
        }
        
        return $updatedCount;
        
    } catch (Exception $e) {
        logActivity(null, 'donor_availability_update_error', $e->getMessage());
        return 0;
    }
}

/**
 * Generate daily statistics
 */
function generateDailyStatistics() {
    try {
        $db = new Database();
        
        // Get today's statistics
        $stats = [
            'date' => date('Y-m-d'),
            'new_requests' => 0,
            'fulfilled_requests' => 0,
            'new_donors' => 0,
            'active_donors' => 0,
            'total_donations' => 0
        ];
        
        // New blood requests today
        $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE DATE(created_at) = CURDATE()";
        $result = $db->query($sql);
        $stats['new_requests'] = $result->fetch_assoc()['count'];
        
        // Fulfilled requests today
        $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE status = 'Fulfilled' AND DATE(updated_at) = CURDATE()";
        $result = $db->query($sql);
        $stats['fulfilled_requests'] = $result->fetch_assoc()['count'];
        
        // New donors today
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND DATE(created_at) = CURDATE()";
        $result = $db->query($sql);
        $stats['new_donors'] = $result->fetch_assoc()['count'];
        
        // Active donors
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_type = 'donor' AND is_active = TRUE AND is_verified = TRUE";
        $result = $db->query($sql);
        $stats['active_donors'] = $result->fetch_assoc()['count'];
        
        // Total donations recorded today
        $sql = "SELECT COUNT(*) as count FROM donor_availability_history WHERE DATE(created_at) = CURDATE()";
        $result = $db->query($sql);
        $stats['total_donations'] = $result->fetch_assoc()['count'];
        
        // Log statistics
        $statsMessage = "Daily Stats: {$stats['new_requests']} new requests, {$stats['fulfilled_requests']} fulfilled, " .
                       "{$stats['new_donors']} new donors, {$stats['active_donors']} active donors, " .
                       "{$stats['total_donations']} donations recorded";
        
        logActivity(null, 'daily_statistics', $statsMessage);
        
        // Save to file for reporting
        $statsFile = '../logs/daily_stats_' . date('Y-m-d') . '.json';
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        
        return $stats;
        
    } catch (Exception $e) {
        logActivity(null, 'statistics_generation_error', $e->getMessage());
        return null;
    }
}
?>
