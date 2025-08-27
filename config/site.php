<?php
/**
 * Site Configuration
 * Provides centralized URL generation and site settings
 */

// Load environment configuration
require_once __DIR__ . '/env.php';

/**
 * Site configuration constants
 */
define('SITE_NAME', EnvLoader::get('SITE_NAME', 'GASC Blood Bridge'));
define('SITE_BASE_PATH', EnvLoader::get('SITE_BASE_PATH', '/GASC-Blood-Donor-Bridge'));

// Auto-detect protocol and host
define('SITE_PROTOCOL', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
define('SITE_HOST', $_SERVER['HTTP_HOST'] ?? 'localhost');
define('SITE_BASE_URL', SITE_PROTOCOL . '://' . SITE_HOST . SITE_BASE_PATH);

/**
 * Generate absolute URL for the site
 * @param string $path Relative path (without leading slash)
 * @return string Full URL
 */
function siteUrl($path = '') {
    $cleanPath = ltrim($path, '/');
    return SITE_BASE_URL . ($cleanPath ? '/' . $cleanPath : '');
}

/**
 * Generate relative URL for the site
 * @param string $path Relative path (without leading slash)
 * @return string Relative URL
 */
function sitePath($path = '') {
    $cleanPath = ltrim($path, '/');
    return SITE_BASE_PATH . ($cleanPath ? '/' . $cleanPath : '');
}

/**
 * Get current page URL
 * @return string Current page full URL
 */
function getCurrentUrl() {
    $protocol = SITE_PROTOCOL;
    $host = SITE_HOST;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return $protocol . '://' . $host . $uri;
}

/**
 * Check if current request is HTTPS
 * @return bool
 */
function isHttps() {
    return SITE_PROTOCOL === 'https';
}

/**
 * Get site configuration as array
 * @return array Site configuration
 */
function getSiteConfig() {
    return [
        'name' => SITE_NAME,
        'base_path' => SITE_BASE_PATH,
        'base_url' => SITE_BASE_URL,
        'protocol' => SITE_PROTOCOL,
        'host' => SITE_HOST,
        'is_https' => isHttps()
    ];
}
?>
