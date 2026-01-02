# 🩸 GASC Blood Donor Bridge

> A comprehensive blood donation management system designed for educational institutions to connect blood donors with those in need efficiently and securely.

**Live Link 🔗**: [https://bdb.free.nf](https://bdb.free.nf)

[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple)](https://getbootstrap.com)

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Technology Stack](#️-technology-stack)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [User Roles](#-user-roles)
- [Security](#-security)
- [Database Backup](#-database-backup)
- [Project Structure](#-project-structure)
- [Troubleshooting](#-troubleshooting)

---

## 🎯 Overview

GASC Blood Donor Bridge is a web-based blood donation management platform that facilitates seamless connections between blood donors and those in emergency need. The system features a multi-role architecture with dedicated portals for donors, administrators, moderators, and requestors.

### Key Objectives:
- 🎯 **Save Lives**: Connect blood donors with urgent requests efficiently
- 🏥 **Emergency Response**: Handle critical blood requests with urgency levels
- 🔐 **Secure Platform**: Ensure data privacy and secure transactions
- 📱 **Mobile-First**: Responsive design for all devices
- 🎓 **Educational Focus**: Tailored for college/university environments

---

## ✨ Features

### 🩸 For Blood Donors
- **Secure Registration** with email verification and roll number validation
- **Extended Blood Groups** - 16 combinations (A1+, A1-, A2+, A2-, A1B+, A1B-, A2B+, A2B-, B+, B-, AB+, AB-, O+, O-)
- **Smart Dashboard** with donation history and availability tracking
- **Automatic Eligibility Calculation** based on gender and last donation date (Males: 3 months, Females: 4 months)
- **Blood Request Alerts** with real-time notifications for matching requests
- **Unified Sidebar Navigation** with mobile-responsive design
- **Profile Management** with location and preference updates
- **Real-time Status Indicators** (Available, Not Available, Pending Verification)

### 🚨 For Blood Requestors
- **Quick Request System** without registration requirement
- **Three Urgency Levels**: Critical (1 day), Urgent (3 days), Normal (7 days)
- **Location-based Matching** with city-wise donor filtering
- **Real-time Donor Count** showing compatible available donors
- **Email Notifications** for request status changes
- **Auto-expiry System** based on urgency level

### 👨‍💼 For Administrators
- **Comprehensive Analytics Dashboard** with blood group distribution and statistics
- **Donor Management** - verify, edit, and manage donor profiles
- **Request Management** - monitor, fulfill, and track all blood requests
- **Database Backup System** - automated backups with MySQLDump-PHP library (portable across all hosting environments)
- **Activity Logging** - complete audit trail with timestamps and user tracking
- **System Settings** - configurable parameters with real-time updates
- **Email Management** - control notification settings and templates
- **Advanced Reports** - fulfillment statistics and analytics
- **Moderator Management** - create and manage moderator accounts

### 🛡️ For Moderators
- **Donor Verification** - approve and verify new donor registrations
- **Request Management** - view and fulfill blood requests
- **Data Updates** - edit donor information and status
- **Basic Reports** - generate fulfillment and activity reports

---

## 🛠️ Technology Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML5, CSS3, JavaScript, Bootstrap 5 |
| **Backend** | PHP 7.2+ |
| **Database** | MySQL 5.7+ / MariaDB |
| **Email** | PHPMailer 6.8+ |
| **Backup** | MySQLDump-PHP Library (portable, no shell access required) |
| **Security** | CSRF Protection, Bcrypt Hashing, Prepared Statements |
| **Architecture** | MVC-inspired with separation of concerns |

### Design Features:
- 📱 **Mobile-First**: Responsive design optimized for all devices
- 🎨 **Modern UI**: Red and white color scheme with smooth animations
- ⚡ **Fast Loading**: Optimized assets with professional loading states
- ♿ **Accessible**: Keyboard navigation and screen reader support
- 🔄 **Real-time Updates**: Live status indicators and dynamic content

---

## 🚀 Installation

### Prerequisites
- **Server**: XAMPP/WAMP/MAMP or any PHP-enabled web server
- **PHP**: Version 7.2 or higher
- **MySQL**: Version 5.7 or higher
- **Composer**: For dependency management
- **Web Browser**: Modern browser (Chrome, Firefox, Edge, Safari)

### Step-by-Step Setup

#### 1. Download and Extract
```bash
# Extract the project to your web server directory
# For XAMPP: C:\xampp\htdocs\GASC-Blood-Donor-Bridge
# For WAMP: C:\wamp64\www\GASC-Blood-Donor-Bridge
```

#### 2. Install Dependencies
```bash
cd GASC-Blood-Donor-Bridge
composer install
```

#### 3. Configure Environment
```bash
# Copy the example environment file
cp .env.example .env

# Edit .env file with your database credentials:
DB_HOST=localhost
DB_NAME=gasc_blood_bridge
DB_USERNAME=root
DB_PASSWORD=your_password
```

#### 4. Start Services
- Start Apache and MySQL from XAMPP/WAMP Control Panel
- Ensure both services are running (green indicators)

#### 5. Database Setup
- Open your browser and navigate to:
  ```
  http://localhost/GASC-Blood-Donor-Bridge/database/setup.php
  ```
- This will automatically create the database and tables using `schema-phpmyadmin.sql`
- Alternatively, import `database/schema-phpmyadmin.sql` via phpMyAdmin

#### 6. Access the Application
```
Homepage: http://localhost/GASC-Blood-Donor-Bridge/
Admin Panel: http://localhost/GASC-Blood-Donor-Bridge/admin/
Donor Portal: http://localhost/GASC-Blood-Donor-Bridge/donor/
```

### Default Login Credentials

#### Admin Login:
- **Email**: `admin@gasc.edu`
- **Password**: `secret`

#### Moderator Login:
- **Email**: `moderator@gasc.edu`
- **Password**: `secret`

#### Test Donor Accounts:
- `john.doe@student.gasc.edu` (Male, O+, Delhi)
- `jane.smith@student.gasc.edu` (Female, A+, Delhi)
- `mike.johnson@student.gasc.edu` (Male, B+, Mumbai)
- **Password for all**: `secret`

⚠️ **Important**: Change all default passwords after first login!

---

## ⚙️ Configuration

### Email Settings (config/email.php)

#### Quick SMTP Setup:
1. **Install PHPMailer**: Run `composer install` or download manually from [GitHub](https://github.com/PHPMailer/PHPMailer/releases)
2. **Configure SMTP** in `config/email.php` (lines 19-24):
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');        // Gmail/Outlook/SendGrid
   define('SMTP_PORT', 587);                      // Use 587 or 465
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password'); // Gmail: Use App Password (16-char)
   define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
   define('SMTP_FROM_NAME', 'GASC Blood Bridge');
   ```

#### Email Providers:
- **Gmail**: Enable 2FA → Generate App Password → Use 16-char password
- **Outlook**: Use `smtp.office365.com` with regular password
- **SendGrid**: Use `smtp.sendgrid.net` with API key (recommended for production)

#### Development Mode:
- Emails are logged to `logs/emails.log` instead of being sent
- No SMTP configuration required for local testing
- Switch to production mode by configuring SMTP settings

### Database Backup Settings
The system uses **MySQLDump-PHP library** for portable backups:
- ✅ Works on any hosting environment (no shell access required)
- ✅ Automatic fallback to traditional mysqldump if needed
- ✅ Scheduled automatic backups
- ✅ Manual backup option in admin panel

Backups are stored in: `database/backup_*.sql`

### System Settings (Admin Panel → Settings)
- **Email Notifications**: Enable/disable email system
- **Blood Matching Mode**: Choose between Acceptable or Perfect matching
- **Request Expiry**: Configure urgency-based durations
- **Donor Eligibility**: Set intervals (Males: 3 months, Females: 4 months)
- **Maintenance Mode**: Toggle system availability

### Blood Matching Configuration

The system supports two matching modes:

#### 1. Acceptable Match Mode (Default - Recommended)
- Shows all medically compatible donors
- Example: O- donors can donate to all blood groups
- Example: Request for A+ shows O-, O+, A-, A+, A1-, A1+, A2-, A2+ donors
- **Best for**: Emergency situations, maximizing donor availability

#### 2. Perfect Match Mode
- Shows only exact blood group matches
- Example: Request for A+ shows only A+ donors
- Example: Request for O- shows only O- donors
- **Best for**: Specific medical requirements, inventory management

#### How to Switch:
1. Login as Admin → **Settings** → **Blood Matching Settings**
2. Select desired mode
3. Changes take effect immediately across all requests

---

## 👥 User Roles

### 🩸 Donors
**Access Level**: Basic
- Register with college roll number and email verification
- Login with email + password authentication
- View personalized dashboard with donation history
- Track donation eligibility (automatically calculated)
- Receive blood request notifications
- Update profile and preferences

### 🛡️ Moderators
**Access Level**: Intermediate
- All donor permissions
- Verify donor profiles
- Manage blood requests
- Edit donor information
- Fulfill blood requests
- Generate basic reports
- View activity logs

### 👑 Admins
**Access Level**: Full
- All moderator permissions
- Manage moderator accounts
- System configuration and settings
- Database backup management
- Advanced reporting and analytics
- Complete activity oversight
- Email system management

---

## 🔐 Security

The system implements multiple layers of security:

### Authentication & Authorization
- ✅ **Bcrypt Password Hashing** (cost factor 12)
- ✅ **Secure Session Management** with regeneration
- ✅ **Role-Based Access Control** (RBAC)
- ✅ **Token-based Password Reset** (time-limited)
- ✅ **Email Verification** for new registrations

### Attack Prevention
- ✅ **CSRF Protection** on all forms
- ✅ **SQL Injection Prevention** (prepared statements)
- ✅ **XSS Protection** (input sanitization)
- ✅ **Rate Limiting** (file-based, prevents brute force)
- ✅ **User Enumeration Protection**

### Data Protection
- ✅ **Activity Logging** (complete audit trail)
- ✅ **Secure Token Generation** (cryptographically secure)
- ✅ **Session Timeout Management**
- ✅ **Input Validation** (server-side)
- ✅ **Error Handling** (no sensitive data exposure)

---

## 💾 Database Backup

### Automated Backup System

The system features a **hybrid backup approach** for maximum portability:

#### Primary Method: MySQLDump-PHP Library
- Pure PHP solution (no external dependencies)
- Works on shared hosting without shell access
- Portable across all environments
- No security restrictions (exec/popen not required)

#### Fallback Method: Traditional mysqldump
- Automatic fallback if library unavailable
- Uses system mysqldump executable
- Maintains backward compatibility

### Backup Features:
- ✅ **Manual Backups** - Create on-demand backups from admin panel
- ✅ **Automatic Backups** - Scheduled backups (configurable interval)
- ✅ **Date Range Backups** - Backup with metadata about time periods
- ✅ **Validation** - Automatic verification of backup integrity
- ✅ **Metadata** - Includes backup method, timestamp, and duration info

### Backup Location:
```
database/backup_manual_2025-12-31_14-30-45.sql
database/backup_auto_2025-12-31_14-30-45.sql
```

### Creating Manual Backup:
1. Login as Admin
2. Navigate to **Settings** → **Database Backup**
3. Click **"Create Manual Backup"**
4. Download or store the backup file

---

## 🗂️ Project Structure

```
GASC-Blood-Donor-Bridge/
├── index.php                      # Landing page
├── logout.php                     # Universal logout handler
├── composer.json                  # Dependencies and autoloader
├── .env                          # Environment configuration
├── .gitignore                    # Git ignore rules
│
├── config/                       # Configuration files
│   ├── database.php              # DB connection, backup, security functions
│   ├── email.php                 # Email sending with PHPMailer
│   ├── env.php                   # Environment variable loader
│   ├── notifications.php         # Email notification system
│   ├── session.php               # Session management
│   ├── site.php                  # Site-wide settings
│   ├── system-settings.php       # System settings manager
│   └── timezone.php              # Timezone configuration (IST)
│
├── database/                     # Database files
│   ├── schema-phpmyadmin.sql     # Complete database schema
│   ├── update_*.sql              # Schema update scripts
│   └── backup_*.sql              # Backup files (auto-generated)
│
├── assets/                       # Static assets
│   ├── css/
│   │   └── style.css            # Main stylesheet
│   ├── images/                  # Logos and graphics
│   └── js/
│       ├── loading-manager.js   # Loading state management
│       └── timezone-utils.js    # Timezone utilities
│
├── donor/                        # Donor Portal
│   ├── register.php             # Donor registration
│   ├── login.php               # Donor authentication
│   ├── forgot-password.php     # Password reset
│   ├── dashboard.php           # Main dashboard
│   ├── edit-profile.php        # Profile management
│   ├── settings.php            # Account settings
│   ├── blood-requests.php      # Available requests
│   ├── add-donation.php        # Log donations
│   ├── donation-history.php    # Past donations
│   ├── donation-details.php    # Donation details
│   ├── verify-email.php        # Email verification
│   └── includes/
│       ├── sidebar.php          # Unified navigation
│       ├── sidebar.css          # Sidebar styles
│       ├── sidebar.js           # Sidebar functionality
│       └── sidebar-utils.php    # Cache management
│
├── admin/                        # Admin/Moderator Portal
│   ├── login.php               # Admin authentication
│   ├── forgot-password.php     # Password reset
│   ├── dashboard.php           # Admin dashboard
│   ├── change-password.php     # Password change
│   ├── donors.php              # Donor management
│   ├── requests.php            # Request management
│   ├── inventory.php           # Blood inventory
│   ├── moderators.php          # Moderator management (admin only)
│   ├── reports.php             # Analytics and reports
│   ├── logs.php                # Activity logs viewer
│   └── settings.php            # System settings (admin only)
│
├── request/                      # Blood Request System
│   ├── blood-request.php       # Request submission form
│   └── request-success.php     # Confirmation page
│
├── requestor/                    # Requestor Portal
│   ├── login.php               # Requestor authentication
│   ├── dashboard.php           # Requestor dashboard
│   ├── authenticate.php        # Auth handler
│   ├── get-request-details.php # Request details API
│   ├── get-donor-count.php     # Donor count API
│   ├── cancel-request.php      # Request cancellation
│   ├── submit-request.php      # Request submission handler
│   └── logout.php              # Requestor logout
│
├── logs/                         # Application logs
│   └── emails.log              # Email sending logs
│
└── vendor/                       # Composer dependencies
    ├── autoload.php            # Composer autoloader
    ├── phpmailer/              # PHPMailer library
    └── ifsnop/                 # MySQLDump-PHP library
```

---

## 🐛 Troubleshooting

### Common Issues and Solutions

#### Database Connection Error
**Problem**: "Could not connect to database"
**Solution**:
- Verify MySQL service is running
- Check database credentials in `.env` file
- Ensure database exists (run setup.php)
- Check MySQL user permissions

#### Email Not Working
**Problem**: Emails not being sent
**Solution**:
- Check `logs/emails.log` for errors
- For development: Emails are logged, not sent
- For production: Configure SMTP in `config/email.php`
- Verify email settings in admin panel
- Check firewall/antivirus blocking SMTP

#### Session Issues
**Problem**: Getting logged out frequently
**Solution**:
- Clear browser cookies and cache
- Check session configuration in `config/session.php`
- Ensure `session.save_path` is writable
- Verify PHP session extension is enabled

#### Permission Errors
**Problem**: "Permission denied" errors
**Solution**:
- Ensure web server has read/write access to project directory
- Check `logs/` folder permissions (777 or 755)
- Check `database/` folder permissions (for backups)
- On Linux: `chmod -R 755 /path/to/project`

#### Backup System Fails
**Problem**: Database backup not working
**Solution**:
- Ensure MySQLDump-PHP library is installed (`composer install`)
- Check `database/` folder permissions (writable)
- Verify database credentials in `.env`
- Check PHP memory limit (increase if needed)
- Review error logs in admin panel

#### Composer Not Found
**Problem**: "composer: command not found"
**Solution**:
- Download Composer from: https://getcomposer.org/download/
- Install globally or use `php composer.phar` instead
- Add Composer to system PATH
- On XAMPP: Use `C:\xampp\php\php.exe composer.phar`

### Getting Help

1. **Check Error Logs**:
   - Browser console (F12 → Console)
   - PHP error logs (XAMPP: `xampp/logs/php_error_log`)
   - Application logs (`logs/emails.log`)

2. **Verify Requirements**:
   - PHP version: `php -v` (must be 7.2+)
   - MySQL version: Check in XAMPP Control Panel
   - Required PHP extensions: mysqli, mbstring, openssl

3. **Database Issues**:
   - Access phpMyAdmin: `http://localhost/phpmyadmin`
   - Verify database exists and has correct permissions
   - Check if tables are created properly

4. **Clear Cache**:
   - Browser cache (Ctrl+Shift+Del)
   - PHP opcache (restart Apache)
   - Application cache (donor sidebar cache)

---

## 📝 Additional Documentation

This README contains all essential information. For advanced topics:

- **[PROJECT_FEATURES_ANALYSIS.md](PROJECT_FEATURES_ANALYSIS.md)** - Complete technical analysis with code verification and API documentation

---

## 🤝 Contributing

This project is developed for educational purposes. For improvements:

1. Follow existing code style and conventions
2. Test all changes thoroughly on multiple devices
3. Update documentation as needed
4. Ensure mobile compatibility
5. Maintain security best practices
6. Add comments for complex logic

---

## 📄 License

This project is developed for educational purposes as part of college coursework at GASC (Gobi Arts & Science College).

---

## 💡 Key Takeaways

- 🩸 **Every feature prioritizes saving lives**
- 🔐 **Security and privacy are paramount**
- 📱 **Mobile-first design for accessibility**
- ⚡ **Performance optimization for emergency situations**
- 🛡️ **Comprehensive error handling and logging**

---

**Remember**: This application is designed to save lives. Every feature should prioritize user safety, data privacy, and system reliability.

For questions or issues, refer to the [Troubleshooting](#-troubleshooting) section or check the additional documentation files.

---

**Version**: 2.0.0  
**Last Updated**: December 31, 2025  
