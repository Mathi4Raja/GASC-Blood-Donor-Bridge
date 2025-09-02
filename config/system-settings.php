<?php
/**
 * System Settings Helper
 * Provides functions to get and use system settings throughout the application
 */

class SystemSettings {
    private static $cache = [];
    private static $db = null;
    
    /**
     * Initialize the system settings class
     */
    public static function init() {
        if (self::$db === null) {
            self::$db = new Database();
        }
    }
    
    /**
     * Get a system setting value
     * @param string $key The setting key
     * @param mixed $default Default value if setting not found
     * @return mixed The setting value
     */
    public static function get($key, $default = null) {
        self::init();
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
            $result = self::$db->query($sql, [$key]);
            
            if ($row = $result->fetch_assoc()) {
                $value = $row['setting_value'];
                
                // Convert string values to appropriate types
                if (is_numeric($value)) {
                    $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
                } elseif (in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                    $value = in_array(strtolower($value), ['true', '1']);
                }
                
                // Cache the value
                self::$cache[$key] = $value;
                return $value;
            }
        } catch (Exception $e) {
            error_log("SystemSettings::get() error for key '$key': " . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Set a system setting value
     * @param string $key The setting key
     * @param mixed $value The setting value
     * @param string $description Optional description
     * @return bool Success status
     */
    public static function set($key, $value, $description = null) {
        self::init();
        
        try {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            
            $params = [$key, (string)$value, $description];
            self::$db->query($sql, $params);
            
            // Update cache
            self::$cache[$key] = $value;
            
            return true;
        } catch (Exception $e) {
            error_log("SystemSettings::set() error for key '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get site name
     */
    public static function getSiteName() {
        return self::get('site_name', 'GASC Blood Donor Bridge');
    }
    
    /**
     * Get admin email
     */
    public static function getAdminEmail() {
        return self::get('admin_email', 'admin@gasc.edu');
    }
    
    /**
     * Get request expiry days
     */
    public static function getRequestExpiryDays() {
        return self::get('request_expiry_days', 30);
    }
    
    /**
     * Get max requests per user
     */
    public static function getMaxRequestsPerUser() {
        return self::get('max_requests_per_user', 5);
    }
    
    /**
     * Get donation cooldown days
     */
    public static function getDonationCooldownDays() {
        return self::get('donation_cooldown_days', 56);
    }
    
    /**
     * Get male donation gap in months
     */
    public static function getMaleDonationGapMonths() {
        return self::get('male_donation_gap_months', 3);
    }
    
    /**
     * Get female donation gap in months
     */
    public static function getFemaleDonationGapMonths() {
        return self::get('female_donation_gap_months', 4);
    }
    
    /**
     * Get OTP expiry minutes
     */
    public static function getOtpExpiryMinutes() {
        return self::get('otp_expiry_minutes', 10);
    }
    
    /**
     * Get max login attempts
     */
    public static function getMaxLoginAttempts() {
        return self::get('max_login_attempts', 5);
    }
    
    /**
     * Get session timeout minutes
     */
    public static function getSessionTimeoutMinutes() {
        return self::get('session_timeout_minutes', 30);
    }
    
    /**
     * Check if email notifications are enabled
     */
    public static function isEmailNotificationsEnabled() {
        return self::get('email_notifications', true);
    }
    
    /**
     * Check if auto-expire requests is enabled
     */
    public static function isAutoExpireRequestsEnabled() {
        return self::get('auto_expire_requests', true);
    }
    
    /**
     * Check if email verification is required
     */
    public static function isEmailVerificationRequired() {
        return self::get('require_email_verification', true);
    }
    
    /**
     * Check if registrations are allowed
     */
    public static function areRegistrationsAllowed() {
        return self::get('allow_registrations', true);
    }
    
    /**
     * Get donation gap based on gender
     * @param string $gender 'Male', 'Female', or 'Other'
     * @return int Gap in months
     */
    public static function getDonationGapByGender($gender) {
        switch (strtolower($gender)) {
            case 'male':
                return self::getMaleDonationGapMonths();
            case 'female':
                return self::getFemaleDonationGapMonths();
            default:
                return self::getFemaleDonationGapMonths(); // Use safer female gap as default
        }
    }
    
    /**
     * Check if a donor can donate based on last donation date and gender
     * @param string $lastDonationDate Last donation date (YYYY-MM-DD)
     * @param string $gender Donor gender
     * @return array ['can_donate' => bool, 'next_eligible_date' => string, 'days_remaining' => int]
     */
    public static function checkDonationEligibility($lastDonationDate, $gender) {
        if (empty($lastDonationDate)) {
            return [
                'can_donate' => true,
                'next_eligible_date' => null,
                'days_remaining' => 0
            ];
        }
        
        $gapMonths = self::getDonationGapByGender($gender);
        $lastDonation = new DateTime($lastDonationDate);
        $nextEligibleDate = clone $lastDonation;
        $nextEligibleDate->add(new DateInterval("P{$gapMonths}M"));
        
        $now = new DateTime();
        $canDonate = $now >= $nextEligibleDate;
        
        $daysRemaining = 0;
        if (!$canDonate) {
            $diff = $now->diff($nextEligibleDate);
            $daysRemaining = $diff->days;
        }
        
        return [
            'can_donate' => $canDonate,
            'next_eligible_date' => $nextEligibleDate->format('Y-m-d'),
            'days_remaining' => $daysRemaining
        ];
    }
    
    /**
     * Clear the settings cache
     */
    public static function clearCache() {
        self::$cache = [];
    }
    
    /**
     * Get all settings as an array
     */
    public static function getAll() {
        self::init();
        
        try {
            $sql = "SELECT setting_key, setting_value FROM system_settings";
            $result = self::$db->query($sql);
            
            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $value = $row['setting_value'];
                
                // Convert string values to appropriate types
                if (is_numeric($value)) {
                    $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
                } elseif (in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                    $value = in_array(strtolower($value), ['true', '1']);
                }
                
                $settings[$row['setting_key']] = $value;
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("SystemSettings::getAll() error: " . $e->getMessage());
            return [];
        }
    }
}
?>
