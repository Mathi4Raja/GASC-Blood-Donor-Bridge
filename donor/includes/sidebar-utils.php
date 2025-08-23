<?php
/**
 * Sidebar Utility Functions
 * Helper functions for managing sidebar cache and data
 */

/**
 * Clear sidebar cache when user data is updated
 * @param int|null $userId User ID (defaults to current session user)
 */
function clearSidebarCache($userId = null) {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if ($userId) {
        $cacheKey = 'sidebar_donor_' . $userId;
        
        // Clear all cache keys
        unset($_SESSION[$cacheKey]);
        unset($_SESSION[$cacheKey . '_can_donate']);
        unset($_SESSION[$cacheKey . '_time']);
        
        // Add debug logging
        error_log("Sidebar cache cleared for user_id: " . $userId);
        
        return true;
    }
    return false;
}

/**
 * Force refresh of sidebar data for current user
 */
function refreshSidebarData() {
    clearSidebarCache();
}
