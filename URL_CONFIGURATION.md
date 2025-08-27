# URL Configuration Guide

## SITE_BASE_PATH Environment Variable

The `SITE_BASE_PATH` in your `.env` file is now **actively used** throughout the codebase to generate all URLs dynamically.

### What it does:
- **Replaces hardcoded paths** like `/GASC-Blood-Donor-Bridge/` in URLs
- **Makes your app portable** across different deployment environments
- **Centralizes URL configuration** in one environment variable

### Current Usage:

✅ **Email verification links** (donor/register.php)
✅ **Password reset links** (config/email.php) 
✅ **JavaScript share URLs** (donor/blood-requests.php)
✅ **All future URL generation** via helper functions

### Configuration Examples:

```bash
# Current XAMPP setup (subfolder)
SITE_BASE_PATH=/GASC-Blood-Donor-Bridge

# Deployed to document root
SITE_BASE_PATH=

# Different subfolder
SITE_BASE_PATH=/blood-bridge

# Subdomain setup
SITE_BASE_PATH=
```

### Helper Functions Available:

- `siteUrl($path)` - Generate full absolute URL
- `sitePath($path)` - Generate relative path 
- `getSiteConfig()` - Get all site configuration

### Before vs After:

**Before** (hardcoded):
```php
$link = "http://" . $_SERVER['HTTP_HOST'] . "/GASC-Blood-Donor-Bridge/donor/verify-email.php?token=" . $token;
```

**After** (environment-based):
```php
$link = siteUrl("donor/verify-email.php?token=" . $token);
```

### Benefits:

✅ **One config change** deploys to any path
✅ **No code changes** needed for different environments  
✅ **Consistent URL generation** across the entire app
✅ **Easy testing** on different local setups

Your `SITE_BASE_PATH=/GASC-Blood-Donor-Bridge` is now powering all URL generation! 🚀
