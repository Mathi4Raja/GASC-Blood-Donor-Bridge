# 📧 SMTP Configuration Guide - GASC Blood Bridge

## 🎯 Quick Setup (Gmail SMTP - Recommended)

### Step 1: Configure Gmail Account
1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Select "Mail" and generate a 16-character password
   - **Save this password** - you'll need it below

### Step 2: Update SMTP Credentials
Open `config/email.php` and update these lines:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'youremail@gmail.com');           // Your Gmail address
define('SMTP_PASSWORD', 'abcd efgh ijkl mnop');           // 16-char App Password (no spaces)
define('SMTP_FROM_EMAIL', 'youremail@gmail.com');         // Your Gmail address
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

### Step 3: Test Email System
1. Visit: `http://localhost/GASC Blood Donor Bridge/test-notifications.php`
2. Click "Test Email Delivery" to verify setup

---

## 🏢 Alternative SMTP Providers

### **Option 2: Microsoft Outlook/Hotmail**
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'youremail@outlook.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'youremail@outlook.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

### **Option 3: Yahoo Mail**
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'youremail@yahoo.com');
define('SMTP_PASSWORD', 'your-app-password');  // Generate in Yahoo Account Security
define('SMTP_FROM_EMAIL', 'youremail@yahoo.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

### **Option 4: Custom SMTP Server**
```php
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587);  // or 465 for SSL
define('SMTP_USERNAME', 'noreply@yourdomain.com');
define('SMTP_PASSWORD', 'your-email-password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'GASC Blood Bridge');
```

---

## 🔒 Security Best Practices

### ✅ **Do's:**
- Use App Passwords instead of regular passwords
- Enable 2-Factor Authentication on your email account
- Use dedicated email addresses for system notifications
- Keep credentials secure and don't share them

### ❌ **Don'ts:**
- Don't use your personal email password directly
- Don't commit credentials to version control
- Don't use the same password for multiple services
- Don't disable security features for convenience

---

## 🧪 Testing Your Setup

### **Test 1: Basic Email Test**
```php
// Visit: test-notifications.php
// Click "Test Email Delivery"
```

### **Test 2: Manual Test**
Create a simple PHP test file:
```php
<?php
require_once 'config/email.php';
$result = sendEmailSMTP('test@example.com', 'Test Subject', 'Test Body');
echo $result ? 'Email sent!' : 'Email failed!';
?>
```

### **Test 3: System Integration Test**
1. Register a new donor account
2. Check if verification email is received
3. Try forgot password functionality
4. Submit a blood request and check donor notifications

---

## 🚨 Troubleshooting

### **Problem: Authentication Failed**
**Solutions:**
- Verify App Password is correct (16 characters, no spaces)
- Ensure 2FA is enabled on Gmail
- Check username is the full email address
- Try regenerating the App Password

### **Problem: Connection Timeout**
**Solutions:**
- Check internet connection
- Verify SMTP host and port
- Check firewall settings (allow port 587)
- Try port 465 with SSL instead

### **Problem: Emails Going to Spam**
**Solutions:**
- Use your domain email instead of Gmail
- Set up SPF/DKIM records for your domain
- Avoid spam trigger words in subject/content
- Send test emails to multiple providers

### **Problem: Daily Sending Limits**
**Gmail Free:** 500 emails/day
**Solutions:**
- Use multiple Gmail accounts in rotation
- Upgrade to Google Workspace
- Use dedicated email service (SendGrid, Mailgun)

---

## 📊 Email System Features

### **Automatic Fallback:**
If SMTP fails, the system will:
1. Log the error
2. Save email content to `logs/emails.log`
3. Continue system operation
4. Retry on next scheduled task run

### **Email Types Supported:**
- ✅ Password reset OTP verification emails
- ✅ Blood request notifications  
- ✅ Password reset emails
- ✅ Account verification emails
- ✅ Donation reminders
- ✅ System status updates

### **HTML Email Templates:**
- Professional styling with GASC branding
- Responsive design for mobile devices
- Proper encoding for special characters
- Fallback plain text versions

---

## 🔧 Advanced Configuration

### **For Production Use:**
1. **Use Dedicated Email Service:**
   - SendGrid (free tier: 100 emails/day)
   - Mailgun (free tier: 5,000 emails/month)
   - Amazon SES (pay-per-use)

2. **Set up Email Queue:**
   - Implement background job processing
   - Handle high-volume email sending
   - Retry failed email deliveries

3. **Monitor Email Delivery:**
   - Track email open rates
   - Monitor bounce rates
   - Set up delivery webhooks

### **Development vs Production:**
```php
// Development: Use Gmail SMTP
define('SMTP_HOST', 'smtp.gmail.com');

// Production: Use dedicated service
define('SMTP_HOST', 'smtp.sendgrid.net');
```

---

## 🎯 Quick Start Checklist

- [ ] Enable 2FA on Gmail account
- [ ] Generate Gmail App Password
- [ ] Update `SMTP_USERNAME` in config/email.php
- [ ] Update `SMTP_PASSWORD` in config/email.php  
- [ ] Update `SMTP_FROM_EMAIL` in config/email.php
- [ ] Test email delivery via test-notifications.php
- [ ] Verify donor registration emails work
- [ ] Test blood request notifications
- [ ] Check logs/emails.log for any errors

**🎉 Once configured, your system will send professional emails for all donor and blood request notifications!**
