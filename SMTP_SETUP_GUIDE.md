# 📧 SMTP Configuration Guide - GASC Blood Donor Bridge

## 🎯 Overview

This guide will help you configure email functionality for the GASC Blood Donor Bridge system. The application supports sending emails for account verification, password resets, and blood request notifications.

### 📧 Email Features:
- ✅ **Account Verification** emails with secure tokens
- ✅ **Password Reset** emails for donors and admins
- ✅ **Blood Request Notifications** to eligible donors
- ✅ **Status Update Notifications** to requestors
- ✅ **Request Confirmation** emails with donor counts

---

## 🚀 Step-by-Step SMTP Setup

### **Step 1: Install Dependencies**

You can install PHPMailer using **either method**:

#### **Method A: Using Composer (Recommended)**
```bash
# Navigate to your project root directory
cd "C:\Program Files\XAMPP\htdocs\GASC-Blood-Donor-Bridge"

# Install PHPMailer via Composer
composer install
```

If you don't have Composer installed:
1. Download from https://getcomposer.org/
2. Install Composer on your system
3. Run the command above

#### **Method B: Manual Installation (No Composer Required)**
1. **Download PHPMailer:**
   - Go to https://github.com/PHPMailer/PHPMailer/releases
   - Download the latest release (e.g., `PHPMailer-6.8.1.zip`)

2. **Extract and organize files:**
   - Extract the downloaded zip file
   - Create this directory structure in your project:
   ```
   GASC-Blood-Donor-Bridge/
   └── vendor/
       └── phpmailer/
           └── phpmailer/
               └── src/
                   ├── PHPMailer.php
                   ├── SMTP.php
                   └── Exception.php
   ```

3. **Copy the required files:**
   - From the extracted PHPMailer folder, copy these 3 files to the `src/` directory:
     - `src/PHPMailer.php`
     - `src/SMTP.php` 
     - `src/Exception.php`

4. **The system will automatically detect the manual installation!**

### **Step 2: Choose Your Email Provider**

#### **Option A: Gmail (Recommended for Development)**
1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account → Security → 2-Step Verification
   - Click "App passwords"
   - Select "Mail" and generate a 16-character password
   - **Save this password** - you'll need it in Step 3

#### **Option B: Outlook/Hotmail**
1. Use your regular Outlook password
2. Ensure "Less secure app access" is enabled (if required)

#### **Option C: SendGrid (Recommended for Production)**
1. Sign up at https://sendgrid.com/
2. Get your API key from the dashboard
3. Use 'apikey' as username and your API key as password

### **Step 3: Configure Email Settings**
Open `config/email.php` and update the configuration constants (around lines 19-24):

#### **For Gmail:**
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-16-char-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

#### **For Outlook/Hotmail:**
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@outlook.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'your-email@outlook.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

#### **For SendGrid:**
```php
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'apikey');
define('SMTP_PASSWORD', 'your-sendgrid-api-key');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

### **Step 4: Test Your Configuration**

#### **Method 1: Test Donor Registration**
1. Go to `http://localhost/GASC-Blood-Donor-Bridge/donor/register.php`
2. Fill out the registration form with a valid email
3. Check if you receive the verification email
4. If no email arrives, check `logs/emails.log` for errors

#### **Method 2: Test Password Reset**
1. Go to `http://localhost/GASC-Blood-Donor-Bridge/donor/forgot-password.php`
2. Enter an existing donor email address
3. Check if the password reset email is received

#### **Method 3: Check Email Logs**
Monitor the email log file for any issues:
```bash
# View recent email activity
type logs\emails.log
```

---

## � System Requirements

### **Required:**
- PHP 7.2 or higher
- Composer (for PHPMailer installation)
- Working internet connection
- Valid email account with SMTP access

### **File Structure:**
```
config/email.php              # Email configuration file
logs/emails.log               # Email activity logs
vendor/phpmailer/            # PHPMailer library (via Composer or manual)
composer.json                # Dependencies (if using Composer)
```

---

## 🧪 Verifying Your Setup

### **Check PHPMailer Installation:**
Create a test file in your project root to verify either installation method:

```php
<?php
// test-email.php
// This will work with both Composer and manual installation
require_once 'config/email.php';

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✅ PHPMailer is installed and ready!<br>";
    echo "📧 Email system is functional!";
} else {
    echo "❌ PHPMailer not found.<br>";
    echo "📝 Check your installation method:<br>";
    echo "• Composer: Run 'composer install'<br>";
    echo "• Manual: Ensure files are in vendor/phpmailer/phpmailer/src/";
}
?>
```
    echo "✅ PHPMailer is installed and ready!";
} else {
    echo "❌ PHPMailer not found. Check installation method.";
}
?>
```

### **Verify Installation Method:**
You can also check which method is being used:

```php
<?php
// check-installation.php
$manual_path = 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
$composer_path = 'vendor/autoload.php';

echo "<h3>📦 PHPMailer Installation Status:</h3>";

if (file_exists($manual_path)) {
    echo "✅ <strong>Manual Installation:</strong> Files found<br>";
}

if (file_exists($composer_path)) {
    echo "✅ <strong>Composer Installation:</strong> Autoloader found<br>";
}

require_once 'config/email.php';
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "🎉 <strong>Result:</strong> PHPMailer is working!";
} else {
    echo "❌ <strong>Result:</strong> PHPMailer not available";
}
?>
```

### **Test Email Sending:**
```php
<?php
// test-send.php
require_once 'config/email.php';

$result = sendEmail('test@example.com', 'Test Subject', 'Test message body');
echo $result ? '✅ Email sent successfully!' : '❌ Email failed to send';
?>
```

---

## 🚨 Troubleshooting Common Issues

### **Problem: "No Emails Being Sent"**
**Check these in order:**
1. ✅ PHPMailer installed: Run `composer install`
2. ✅ SMTP credentials correct in `config/email.php`
3. ✅ Internet connection working
4. ✅ Email provider allows SMTP access

### **Problem: "SMTP Authentication Failed"**
**For Gmail:**
- Ensure 2-Factor Authentication is enabled
- Use App Password, not your regular password
- App Password should be 16 characters without spaces

**For Other Providers:**
- Verify username and password are correct
- Check if "less secure apps" setting needs to be enabled

### **Problem: "Connection Timeout"**
**Solutions:**
- Check firewall settings (allow port 587)
- Try port 465 instead (update `SMTP_PORT` to 465)
- Verify your hosting provider allows outbound SMTP

### **Problem: "Class PHPMailer not found"**
**For Composer Installation:**
1. Install Composer from https://getcomposer.org/
2. Run `composer install` in project directory
3. Verify `vendor/autoload.php` exists

**For Manual Installation:**
1. Download PHPMailer from https://github.com/PHPMailer/PHPMailer/releases
2. Extract and copy these files to `vendor/phpmailer/phpmailer/src/`:
   - `PHPMailer.php`
   - `SMTP.php`
   - `Exception.php`
3. Verify the directory structure matches exactly

**Check Installation Status:**
Create and run the verification scripts provided in the "Verifying Your Setup" section.

### **Problem: "Emails Going to Spam"**
**Solutions:**
- Check spam/junk folder first
- Use your own domain email instead of free providers
- Add sender to address book
- Set up SPF/DKIM records for your domain

---

## 📊 Understanding the Email System

### **How It Works:**
1. **Application triggers** email function (registration, password reset, etc.)
2. **System checks** if PHPMailer is available
3. **If available:** Sends email via configured SMTP
4. **If not available:** Logs email content to `logs/emails.log`
5. **Result logged** for debugging purposes

### **Email Functions in the System:**
- `sendEmail($to, $subject, $body, $isHTML)` - Main email function
- `sendPasswordResetEmail($email, $token, $name, $userType)` - Password resets
- `sendBloodRequestNotification($donorEmail, $donorName, $details)` - Blood alerts

### **Fallback Behavior:**
If SMTP fails, the system will:
- Log the error for debugging
- Save email content to `logs/emails.log`
- Continue normal operation
- Allow you to resend emails manually

---

## 🎯 Quick Setup Checklist

### **For Development (Local Testing):**

#### **Option A: Using Composer**
- [ ] Install Composer from https://getcomposer.org/
- [ ] Run `composer install` in project directory
- [ ] Configure email provider settings
- [ ] Test with donor registration
- [ ] Check `logs/emails.log` for any issues

#### **Option B: Manual Installation**
- [ ] Download PHPMailer from GitHub releases
- [ ] Create `vendor/phpmailer/phpmailer/src/` directory
- [ ] Copy PHPMailer.php, SMTP.php, Exception.php files
- [ ] Configure email provider settings
- [ ] Test with donor registration
- [ ] Check `logs/emails.log` for any issues

### **For Production Deployment:**
- [ ] Choose installation method (Composer recommended for production)
- [ ] Set up dedicated email service (SendGrid recommended)
- [ ] Configure domain SPF/DKIM records
- [ ] Test all email types thoroughly
- [ ] Set up email monitoring
- [ ] Configure backup email service
- [ ] Monitor `logs/emails.log` regularly

---

## 🔒 Security Best Practices

### **Email Account Security:**
- ✅ Use dedicated email account for system notifications
- ✅ Enable 2-Factor Authentication
- ✅ Use App Passwords instead of regular passwords
- ✅ Regularly rotate passwords and API keys
- ✅ Monitor for suspicious activity

### **Configuration Security:**
- ✅ Never commit credentials to version control
- ✅ Consider using environment variables for production
- ✅ Restrict SMTP access to necessary IPs only
- ✅ Use strong, unique passwords
- ✅ Enable email rate limiting if available

---

## 🎉 Congratulations!

Once configured, your GASC Blood Donor Bridge system will automatically send professional, branded emails for:

- **New Donor Registration** verification
- **Password Reset** requests with secure tokens
- **Blood Request Notifications** to eligible donors
- **Status Updates** for blood requests
- **Confirmation Emails** for requestors

**Need Help?** Check the `logs/emails.log` file for detailed information about email sending attempts and any errors that occur.
