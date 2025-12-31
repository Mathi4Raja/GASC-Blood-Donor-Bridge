<?php
/**
 * Timezone Configuration for GASC Blood Donor Bridge
 * Ensures IST (Indian Standard Time) is used consistently across the application
 * 
 * This file should be included at the top of all entry point files to ensure
 * consistent timezone handling throughout the application.
 */

// Set default timezone to IST (Indian Standard Time)
if (!date_default_timezone_set('Asia/Kolkata')) {
    // Fallback to UTC if IST is not available (unlikely scenario)
    date_default_timezone_set('UTC');
    error_log('Warning: Asia/Kolkata timezone not available, falling back to UTC');
}

// Set timezone for all datetime operations
ini_set('date.timezone', 'Asia/Kolkata');

// Function to get current IST timestamp
if (!function_exists('getISTDateTime')) {
    function getISTDateTime($format = 'Y-m-d h:i:s A') {
        return date($format);
    }
}

// Function to convert any datetime to IST
if (!function_exists('convertToIST')) {
    function convertToIST($datetime, $format = 'Y-m-d h:i:s A') {
        if (empty($datetime)) {
            return null;
        }
        
        try {
            // Create DateTime object from input
            $dt = new DateTime($datetime);
            // Set timezone to IST
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format($format);
        } catch (Exception $e) {
            error_log("Error converting datetime to IST: " . $e->getMessage());
            return $datetime; // Return original if conversion fails
        }
    }
}

// Function to format datetime for display in IST
if (!function_exists('formatISTDateTime')) {
    function formatISTDateTime($datetime, $format = 'd M Y, h:i A') {
        if (empty($datetime)) {
            return 'Not available';
        }
        
        try {
            $dt = new DateTime($datetime);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format($format);
        } catch (Exception $e) {
            return $datetime; // Return original if formatting fails
        }
    }
}

// Function to get IST offset for JavaScript
if (!function_exists('getISTOffsetMinutes')) {
    function getISTOffsetMinutes() {
        // IST is UTC+5:30 = 330 minutes
        return 330;
    }
}

// Function to get IST offset string
if (!function_exists('getISTOffsetString')) {
    function getISTOffsetString() {
        return '+05:30';
    }
}

// Add timezone information to PHP error logs
if (!function_exists('logWithTimezone')) {
    function logWithTimezone($message, $level = 'INFO') {
        $timestamp = date('Y-m-d h:i:s A T');
        error_log("[$timestamp] [$level] $message");
    }
}

// Verify timezone is correctly set
$currentTz = date_default_timezone_get();
if ($currentTz !== 'Asia/Kolkata') {
    error_log("Warning: Expected timezone 'Asia/Kolkata', but got '$currentTz'");
}

// Log timezone initialization (only once per request)
if (!defined('TIMEZONE_INITIALIZED')) {
    define('TIMEZONE_INITIALIZED', true);
    
    // Silent logging - only log if debug mode is enabled
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logWithTimezone("Timezone initialized to IST (Asia/Kolkata)", 'DEBUG');
    }
}
?>