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
    
    /**
     * Get session timeout in minutes
     */
    public static function getSessionTimeoutMinutes() {
        return self::get('session_timeout_minutes', 30);
    }
    
    // ========== NOTIFICATIONS ==========
    
    /**
     * Check if email notifications are enabled
     */
    public static function isEmailNotificationsEnabled() {
        return (bool) self::get('email_notifications', 1);
    }
    
    /**
     * Check if SMS notifications are enabled
     */
    public static function isSmsNotificationsEnabled() {
        return (bool) self::get('sms_notifications', 0);
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
