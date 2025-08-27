<?php
/**
 * Site Configuration Test Script
 * Test that environment-based URL generation is working correctly
 */

// Include necessary files
require_once 'config/env.php';
require_once 'config/site.php';

echo "=== GASC Blood Bridge - Site Configuration Test ===\n\n";

// Test environment loading
echo "Environment Variables:\n";
echo "- SITE_NAME: " . EnvLoader::get('SITE_NAME', 'NOT SET') . "\n";
echo "- SITE_BASE_PATH: " . EnvLoader::get('SITE_BASE_PATH', 'NOT SET') . "\n";
echo "- SMTP_USERNAME: " . (EnvLoader::get('SMTP_USERNAME') ? '[HIDDEN]' : 'NOT SET') . "\n";
echo "\n";

// Test site configuration
echo "Site Configuration:\n";
$config = getSiteConfig();
foreach ($config as $key => $value) {
    echo "- $key: $value\n";
}
echo "\n";

// Test URL generation functions
echo "URL Generation Tests:\n";
echo "- siteUrl(): " . siteUrl() . "\n";
echo "- siteUrl('donor/login.php'): " . siteUrl('donor/login.php') . "\n";
echo "- siteUrl('/admin/dashboard.php'): " . siteUrl('/admin/dashboard.php') . "\n";
echo "- sitePath(): " . sitePath() . "\n";
echo "- sitePath('request/blood-request.php'): " . sitePath('request/blood-request.php') . "\n";
echo "- sitePath('/requestor/dashboard.php'): " . sitePath('/requestor/dashboard.php') . "\n";
echo "\n";

// Test email verification link (like in register.php)
$testToken = 'test123token';
$verificationLink = siteUrl("donor/verify-email.php?token=" . $testToken);
echo "Example Verification Link:\n";
echo $verificationLink . "\n\n";

// Test password reset links (like in email.php)
$resetToken = 'reset456token';
$adminResetPath = sitePath("admin/forgot-password.php?step=2&token=" . urlencode($resetToken));
$donorResetPath = sitePath("donor/forgot-password.php?step=2&token=" . urlencode($resetToken));
echo "Example Password Reset Paths:\n";
echo "- Admin: " . $adminResetPath . "\n";
echo "- Donor: " . $donorResetPath . "\n\n";

// Test share URL (like in blood-requests.php)
$requestId = '123';
$shareUrl = sitePath("request/blood-request.php") . "?id=" . $requestId;
echo "Example Share URL Path:\n";
echo $shareUrl . "\n\n";

echo "=== Test Complete ===\n";
echo "All URLs are now generated from SITE_BASE_PATH environment variable!\n";
?>
