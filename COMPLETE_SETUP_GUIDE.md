# GASC Blood Donor Bridge - Complete Setup Guide

## 🩸 Project Overview

The GASC Blood Donor Bridge is a comprehensive web application that connects blood donors with those in need. This enhanced version includes advanced features like OTP-based authentication, SMS notifications, email systems, and automated workflows.

## ✨ Enhanced Features (Now Implemented)

### 🔐 Authentication & Security
- **OTP-based Login**: Donors can login using email + OTP verification
- **Password-based Login**: Traditional email + password login
- **Forgot Password**: Complete password reset workflow via email
- **Email Verification**: New donors must verify their email
- **CSRF Protection**: All forms protected against CSRF attacks
- **Rate Limiting**: Protection against brute force attempts

### 📧 Email System
- **SMTP Integration**: Uses Gmail SMTP or custom SMTP servers
- **HTML Email Templates**: Beautiful, responsive email designs
- **Multiple Email Types**:
  - OTP verification emails
  - Blood request notifications
  - Password reset emails
  - Account verification emails
  - Request status updates

### 📱 SMS Notification System
- **Multiple SMS Services**: Textbelt, Twilio, SMSGateway.me support
- **Smart SMS Delivery**: Critical requests get SMS notifications
- **SMS Types**:
  - OTP verification SMS
  - Urgent blood request alerts
  - Donation eligibility reminders
  - Account verification confirmations

### 🔔 Advanced Notification System
- **Automatic Donor Notifications**: Eligible donors notified instantly
- **Geo-based Filtering**: Only donors in the same city are notified
- **Priority-based SMS**: Critical/Urgent requests trigger SMS alerts
- **Batch Notifications**: Efficient bulk notification system
- **Status Update Notifications**: Requestors informed of status changes

### ⚡ Automated Workflows
- **Auto-expire Requests**: Old requests automatically marked as expired
- **Donation Eligibility**: Automatic availability updates based on last donation
- **Daily Statistics**: Automated daily reporting
- **Data Cleanup**: Automatic cleanup of expired tokens and old logs

## 🚀 Installation Guide

### Prerequisites
- XAMPP/WAMP/LAMP stack
- PHP 7.4 or higher
- MySQL 5.7 or higher
- cURL extension enabled
- OpenSSL extension enabled

### Step 1: Download and Setup
1. Download the project files to your web server directory
2. Extract to: `C:\xampp\htdocs\GASC Blood Donor Bridge\GASC-Blood-Donor-Bridge\`

### Step 2: Database Setup
1. Start MySQL in XAMPP Control Panel
2. Open phpMyAdmin: `http://localhost/phpmyadmin`
3. Create database: `gasc_blood_bridge`
4. Import schema: `database/schema-phpmyadmin.sql`

### Step 3: Email Configuration (Optional but Recommended)
1. Open `config/email.php`
2. Update SMTP settings:
```php
define('SMTP_USERNAME', 'your-gmail@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // Gmail App Password
```

### Step 4: SMS Configuration (Optional)
1. Open `config/sms.php`
2. Choose SMS service and configure API keys
3. For Textbelt (free): Use as-is (1 SMS per day per IP)
4. For Twilio: Add your API credentials

### Step 5: Test the Installation
1. Visit: `http://localhost/GASC Blood Donor Bridge/`
2. Test notifications: `http://localhost/GASC Blood Donor Bridge/test-notifications.php`

## 🔧 Configuration Options

### Email Settings (`config/email.php`)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### SMS Settings (`config/sms.php`)
```php
define('SMS_SERVICE', 'textbelt'); // textbelt, twilio, smsgateway
define('SMS_API_KEY', 'your-api-key-here');
```

### System Settings (Database)
- Request expiry days
- OTP expiry time
- Donation gap periods
- Rate limiting settings

## 📋 Default Login Credentials

### Admin Panel
- **URL**: `http://localhost/GASC Blood Donor Bridge/admin/login.php`
- **Email**: `admin@gasc.edu`
- **Password**: `secret`

### Test Donor Accounts
- **Email**: `john.doe@student.gasc.edu` | **Password**: `secret`
- **Email**: `jane.smith@student.gasc.edu` | **Password**: `secret`

## 🔄 Automated Tasks Setup

### Windows (Task Scheduler)
1. Open Task Scheduler
2. Create Basic Task
3. Action: Start a program
4. Program: `C:\xampp\php\php.exe`
5. Arguments: `"C:\xampp\htdocs\GASC Blood Donor Bridge\GASC-Blood-Donor-Bridge\scheduled-tasks.php"`
6. Schedule: Every hour

### Linux (Cron Job)
```bash
# Add to crontab (crontab -e)
0 * * * * php /path/to/scheduled-tasks.php
```

## 🧪 Testing the System

### Test Notifications
Visit: `http://localhost/GASC Blood Donor Bridge/test-notifications.php`

Test features:
- Email delivery
- SMS delivery  
- OTP generation/verification
- Blood request notifications
- Auto-expiry system

### Manual Testing
1. **Register a new donor** → Check email verification
2. **Submit blood request** → Verify donor notifications
3. **Try OTP login** → Test OTP delivery
4. **Test forgot password** → Verify reset workflow

## 📊 System Monitoring

### Log Files
- `logs/emails.log` - Email delivery logs
- `logs/sms.log` - SMS delivery logs
- `logs/daily_stats_YYYY-MM-DD.json` - Daily statistics

### Database Monitoring
- `activity_logs` table - All system activities
- `otp_verifications` table - OTP usage tracking
- `blood_requests` table - Request status and metrics

## 🔧 Troubleshooting

### Email Issues
1. **Gmail Authentication**: Use App Passwords, not regular passwords
2. **SMTP Errors**: Check firewall and port 587 access
3. **Fallback**: System logs emails to file if SMTP fails

### SMS Issues
1. **Textbelt**: Limited to 1 SMS per day per IP (free tier)
2. **API Keys**: Verify Twilio/SMSGateway credentials
3. **Fallback**: System logs SMS to file if service fails

### Database Issues
1. **Connection**: Verify MySQL service is running
2. **Permissions**: Ensure database user has full privileges
3. **Schema**: Re-import schema if tables are missing

### OTP Issues
1. **Expiry**: OTPs expire after 10 minutes
2. **Rate Limiting**: Max 3 OTP requests per 5 minutes
3. **Cleanup**: Run cleanup task to remove expired OTPs

## 🛡️ Security Considerations

### Production Deployment
1. **Remove test files**: Delete `test-notifications.php`
2. **Change default passwords**: Update all default credentials
3. **Enable HTTPS**: Use SSL certificates
4. **Update configuration**: Set production SMTP/SMS credentials
5. **File permissions**: Restrict access to config files
6. **Regular backups**: Backup database and files

### Security Features
- Password hashing with bcrypt
- CSRF token protection
- Rate limiting on authentication
- Input sanitization and validation
- SQL injection prevention
- XSS protection

## 📈 Performance Optimization

### Caching
- Implement Redis/Memcached for session storage
- Cache frequent database queries
- Use CDN for static assets

### Database
- Regular cleanup of old logs
- Index optimization
- Query optimization

### Email/SMS
- Queue system for bulk notifications
- Retry mechanism for failed deliveries
- Load balancing across multiple services

## 🤝 Support and Maintenance

### Regular Tasks
- Monitor log files
- Check email/SMS delivery rates
- Update donor eligibility status
- Generate usage reports
- Security updates

### Scaling
- Load balancer setup
- Database replication
- Microservices architecture
- API rate limiting

## 📚 API Documentation

The system includes internal APIs for:
- Donor notifications
- OTP verification
- Blood request matching
- Statistics generation

## 🎯 Future Enhancements

Potential improvements:
- Mobile application
- Real-time notifications (WebSocket)
- Advanced matching algorithms
- Integration with hospital systems
- Multi-language support
- Advanced analytics dashboard

---

**Note**: This system is designed for educational purposes and should be thoroughly tested before production use. Always follow local regulations for medical applications.

For technical support or questions, refer to the code comments and log files for detailed debugging information.
