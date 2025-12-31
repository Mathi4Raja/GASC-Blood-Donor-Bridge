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
    
    // ========== BASIC SETTINGS ==========
    
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
    
    // ========== REQUEST LIMITS ==========
    
    /**
     * Get maximum requests per user per day
     */
    public static function getMaxRequestsPerUser() {
        return self::get('max_requests_per_user', 5);
    }
    
    // ========== SECURITY & SESSIONS ==========
    
    /**
     * Get max login attempts
     */
    public static function getMaxLoginAttempts() {
        return self::get('max_login_attempts', 5);
    }
    
    // ========== NOTIFICATIONS ==========
    
    /**
     * Check if email notifications are enabled
     */
    public static function isEmailNotificationsEnabled() {
        return (bool) self::get('email_notifications', 1);
    }
    
    // ========== SYSTEM CONTROLS ==========
    
    /**
     * Check if auto-expire requests is enabled
     */
    public static function isAutoExpireRequestsEnabled() {
        return (bool) self::get('auto_expire_requests', 1);
    }
    
    /**
     * Check if email verification is required
     */
    public static function isEmailVerificationRequired() {
        return (bool) self::get('require_email_verification', 1);
    }
    
    /**
     * Check if new registrations are allowed
     */
    public static function areRegistrationsAllowed() {
        return (bool) self::get('allow_registrations', 1);
    }
    
    // ========== BLOOD MATCHING SETTINGS ==========
    
    /**
     * Get blood matching mode
     * @return string 'perfect' for exact matches only, 'acceptable' for compatible matches
     */
    public static function getBloodMatchingMode() {
        return self::get('blood_matching_mode', 'acceptable');
    }
    
    /**
     * Check if strict blood matching is enabled (perfect match only)
     */
    public static function isStrictBloodMatchingEnabled() {
        return (bool) self::get('strict_blood_matching', 0);
    }
    
    /**
     * Check if blood subtype awareness is enabled
     */
    public static function isBloodSubtypeAwarenessEnabled() {
        return (bool) self::get('blood_subtype_awareness', 1);
    }
    
    /**
     * Get blood matching help text
     */
    public static function getBloodMatchingHelpText() {
        return self::get('blood_matching_help_text', 'Perfect Match: Only donors with exact blood group. Acceptable Match: Donors with compatible blood groups.');
    }
    
    /**
     * Set blood matching mode
     * @param string $mode 'perfect' or 'acceptable'
     * @return bool Success status
     */
    public static function setBloodMatchingMode($mode) {
        if (!in_array($mode, ['perfect', 'acceptable'])) {
            return false;
        }
        
        $success = self::set('blood_matching_mode', $mode);
        
        // Also update the strict matching flag for consistency
        if ($success) {
            self::set('strict_blood_matching', $mode === 'perfect' ? 1 : 0);
        }
        
        return $success;
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
