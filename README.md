# GASC Blood Donor Bridge

A comprehensive blood donation portal developed for college students to connect blood donors with those in need. This web application follows a mobile-first design approach and prioritizes privacy and security.

## 🩸 Features

### For Donors
- **Secure Registration**: Email-based verification system
- **Password-Based Login**: Secure authentication with forgot password functionality
- **Smart Dashboard**: Track donation history and availability status
- **Automatic Eligibility**: System calculates donation eligibility based on gender and last donation date
- **Blood Request Alerts**: Get notified about urgent requests in your area
- **Profile Management**: Update personal information and donation preferences
- **Real-time Status**: Live availability status with color-coded indicators

### For Requestors
- **Quick Request System**: Submit blood requests with urgency levels
- **Real-time Donor Count**: See available donors in your area
- **Multiple Urgency Levels**: Critical (1 day), Urgent (3 days), Normal (7 days)
- **Automatic Expiry**: Requests auto-expire based on urgency
- **Status Tracking**: Monitor request status with email notifications

### For Admins & Moderators
- **Comprehensive Dashboard**: Statistics, blood group distribution, recent activities
- **Donor Management**: Verify and manage donor profiles
- **Request Management**: Monitor and fulfill blood requests
- **Activity Logging**: Complete audit trail of all activities
- **Role-based Access**: Different access levels for admins and moderators
- **Password Reset Management**: Secure password reset for all user types

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.2+
- **Database**: MySQL 5.7+
- **Security**: CSRF protection, bcrypt password hashing, secure session management
- **Email**: PHPMailer integration with comprehensive email system
- **Architecture**: MVC-inspired structure with separation of concerns

## 📱 Design Features

- **Mobile-First**: Responsive design optimized for mobile devices
- **Modern UI**: Red and white color scheme with smooth animations
- **Unified Sidebar**: Consistent navigation across all donor pages
- **Real-time Updates**: Live status indicators and dynamic content
- **Fast Loading**: Optimized assets and efficient database queries
- **SEO Optimized**: Proper meta tags and semantic HTML structure

## 🚀 Quick Setup

### Prerequisites
- XAMPP/WAMP/MAMP server
- PHP 7.2 or higher
- MySQL 5.7 or higher
- Web browser

### Installation Steps

1. **Download and Extract**
   ```
   Extract the project to: C:\xampp\htdocs\GASC Blood Donor Bridge
   ```

2. **Start Services**
   - Start Apache and MySQL from XAMPP Control Panel

3. **Database Setup**
   - Open your browser and navigate to:
   ```
   http://localhost/GASC Blood Donor Bridge/database/setup.php
   ```
   - This will automatically create the database and tables using `schema-phpmyadmin.sql`

4. **Access the Application**
   ```
   Homepage: http://localhost/GASC Blood Donor Bridge/
   ```

### Default Login Credentials

**Admin Login:**
- Email: `admin@gasc.edu`
- Password: `secret`

**Moderator Login:**
- Email: `moderator@gasc.edu`
- Password: `secret`

**Test Donor Accounts:**
- Email: `john.doe@student.gasc.edu` (Male, O+, Delhi)
- Email: `jane.smith@student.gasc.edu` (Female, A+, Delhi)
- Email: `mike.johnson@student.gasc.edu` (Male, B+, Mumbai)
- **Password for all donor accounts:** `secret`

⚠️ **Important**: Change these default passwords after first login!

## 📊 User Roles & Access Levels

### 🩸 Donors
- Register with college roll number and personal details
- Login with email + password authentication
- View personalized dashboard
- Track donation history and eligibility
- Receive blood request notifications

### 👨‍💼 Moderators
- Verify donor profiles
- Manage blood requests
- Edit and update donor information
- Fulfill blood requests
- Generate basic reports

### 👑 Admins
- All moderator permissions
- Manage moderator accounts
- System configuration
- Advanced reporting
- Complete activity oversight

## 🗂️ Project Structure

```
GASC-Blood-Donor-Bridge/
├── index.php                     # Landing page
├── scheduled-tasks.php            # Automated system maintenance
├── logout.php                     # Universal logout handler
├── composer.json                  # Dependencies and autoloader
├── .gitignore                     # Git ignore rules
├── config/
│   ├── database.php              # Database config & security functions
│   ├── email.php                 # Email sending functionality
│   ├── notifications.php         # Email notification system
│   ├── otp.php                   # OTP generation utilities
│   └── forgot-password.php       # Legacy redirect handler
├── database/
│   └── schema-phpmyadmin.sql     # Complete database schema
├── assets/
│   ├── css/
│   │   └── style.css            # Main stylesheet
│   └── images/                   # Logo and graphics
├── donor/
│   ├── register.php             # Donor registration
│   ├── login.php               # Donor authentication
│   ├── forgot-password.php     # Donor password reset
│   ├── dashboard.php           # Main donor dashboard
│   ├── edit-profile.php        # Profile management
│   ├── settings.php            # Account settings
│   ├── blood-requests.php      # Available requests
│   ├── add-donation.php        # Log new donations
│   ├── donation-history.php    # View past donations
│   ├── donation-details.php    # Individual donation details
│   ├── verify-email.php        # Email verification
│   └── includes/
│       ├── sidebar.php          # Unified navigation sidebar
│       ├── sidebar.css          # Sidebar-specific styles
│       ├── sidebar.js           # Sidebar functionality
│       └── sidebar-utils.php    # Sidebar cache management
├── admin/
│   ├── login.php               # Admin/Moderator login
│   ├── forgot-password.php     # Admin password reset
│   ├── dashboard.php           # Admin dashboard
│   ├── change-password.php     # Password change utility
│   ├── donors.php              # Donor management
│   ├── requests.php            # Request management
│   ├── inventory.php           # Blood inventory
│   ├── moderators.php          # Moderator management
│   ├── reports.php             # Analytics and reports
│   ├── logs.php                # Activity logs viewer
│   └── settings.php            # System settings
├── request/
│   ├── blood-request.php       # Blood request form
│   └── request-success.php     # Request confirmation
├── requestor/
│   ├── login.php               # Requestor authentication
│   ├── dashboard.php           # Requestor dashboard
│   ├── authenticate.php        # Authentication handler
│   ├── get-request-details.php # Request details API
│   ├── cancel-request.php      # Request cancellation
│   └── logout.php              # Requestor logout
├── logs/
│   └── emails.log              # Email sending logs
└── vendor/                     # Composer dependencies
    └── phpmailer/              # PHPMailer library
```

## 🔐 Security Features

- **CSRF Protection**: All forms protected against CSRF attacks
- **Password Hashing**: Bcrypt with cost factor 12
- **Secure Sessions**: Session regeneration and timeout management
- **Rate Limiting**: File-based rate limiting prevents brute force attacks
- **Input Validation**: Comprehensive server-side validation
- **SQL Injection Prevention**: Prepared statements throughout
- **Activity Logging**: Complete audit trail with user tracking
- **Secure Token Generation**: Cryptographically secure password reset tokens
- **Token Expiration**: Time-limited tokens for password resets
- **User Enumeration Protection**: Consistent responses for security
- **Role-Based Access Control**: Granular permissions system

## 🎨 UI/UX Features

- **Responsive Design**: Mobile-first approach, works on all device sizes
- **Progressive Enhancement**: Works without JavaScript
- **Loading States**: Visual feedback for user actions
- **Error Handling**: User-friendly error messages with proper validation
- **Accessibility**: Keyboard navigation and screen reader support
- **Modern Animations**: Smooth transitions and hover effects
- **Unified Navigation**: Consistent sidebar across donor pages
- **Real-time Updates**: Live status indicators and cache management
- **Color-coded Status**: High-contrast availability indicators

## 📧 Email System

Comprehensive email system with PHPMailer integration:

### Current Email Features:
- **Account Verification**: Email verification for new registrations
- **Password Reset**: Secure password reset with time-limited tokens
- **Request Notifications**: Automated donor notifications for blood requests
- **Status Updates**: Requestor notifications for status changes
- **Confirmation Emails**: Request confirmations with donor availability counts

### Email Configuration:
- Development: File-based logging to `logs/emails.log`
- Production: SMTP integration ready (configure in `config/email.php`)
- Template-based emails with branding
- Comprehensive error handling and logging

## 🔧 Scheduled Tasks

Automated maintenance system (`scheduled-tasks.php`):

- **Auto-expire Blood Requests**: Removes old requests based on urgency
- **Donation Eligibility Updates**: Updates donor availability based on last donation
- **Data Cleanup**: Removes expired tokens and old logs
- **Daily Statistics**: Generates daily system reports
- **Reminder System**: Sends donation eligibility reminders

### Setup Instructions:
- **Windows**: Use Task Scheduler to run hourly
- **Linux**: Add to crontab: `0 * * * * php /path/to/scheduled-tasks.php`

## 🔧 Configuration

### Database Settings
Edit `config/database.php` to change:
- Database credentials
- Email verification settings
- Session security settings
- Rate limiting parameters

### Email Settings
Configure email settings in the `sendEmail()` function for production use.

## 📈 Key Features in Detail

### Blood Group Compatibility
- Automatic matching based on blood type compatibility
- Universal donors (O-) and recipients (AB+) highlighted
- Smart filtering for emergency situations

### Donation Eligibility Tracking
- Males: 3-month minimum interval
- Females: 4-month minimum interval
- Automatic calculation and status updates
- Visual indicators for availability

### Request Management
- Urgency-based expiry system
- City-wise donor filtering
- Real-time availability counts
- Email notifications for status updates

### Analytics Dashboard
- Blood group distribution charts
- Monthly fulfillment statistics
- Recent activity monitoring
- Critical request alerts

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL service is running
   - Verify database credentials in `config/database.php`

2. **Permission Errors**
   - Ensure web server has read/write access to project directory
   - Check logs folder permissions

3. **Email Not Working**
   - Check email configuration
   - Verify SMTP settings for production

4. **Session Issues**
   - Clear browser cookies
   - Check session configuration in PHP

### Getting Help

1. Check the browser console for JavaScript errors
2. Review PHP error logs
3. Verify all file permissions
4. Ensure all required PHP extensions are installed

## 📝 Future Enhancements

- Email notification system
- Blood bank inventory management
- Mobile app development
- Advanced reporting system
- Multi-language support
- Social media integration
- Donor recognition system
- Hospital partnership module

## 🤝 Contributing

This project is developed as a college assignment. For improvements:

1. Follow the existing code style
2. Test all changes thoroughly
3. Update documentation as needed
4. Ensure mobile compatibility

## 📄 License

This project is developed for educational purposes as part of college coursework.

## 🙏 Acknowledgments

- Bootstrap team for the responsive framework
- Font Awesome for icons
- GASC College for the opportunity
- All blood donors who save lives every day

---

**Remember**: This application is designed to save lives. Every feature should prioritize user safety, data privacy, and system reliability.

For any questions or issues, please refer to the troubleshooting section above or contact the development team.
