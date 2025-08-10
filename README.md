# GASC Blood Donor Bridge

A comprehensive blood donation portal developed for college students to connect blood donors with those in need. This web application follows a mobile-first design approach and prioritizes privacy and security.

## 🩸 Features

### For Donors
- **Secure Registration**: Email-based verification system
- **Password Login**: Secure password-based authentication
- **Smart Dashboard**: Track donation history and availability status
- **Automatic Eligibility**: System calculates donation eligibility based on gender and last donation date
- **Blood Request Alerts**: Get notified about urgent requests in your area

### For Requestors
- **Quick Request System**: Submit blood requests with urgency levels
- **Real-time Donor Count**: See available donors in your area
- **Multiple Urgency Levels**: Critical (1 day), Urgent (3 days), Normal (7 days)
- **Automatic Expiry**: Requests auto-expire based on urgency

### For Admins & Moderators
- **Comprehensive Dashboard**: Statistics, blood group distribution, recent activities
- **Donor Management**: Verify and manage donor profiles
- **Request Management**: Monitor and fulfill blood requests
- **Activity Logging**: Complete audit trail of all activities
- **Role-based Access**: Different access levels for admins and moderators

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.2+
- **Database**: MySQL 5.7+
- **Security**: CSRF protection, password hashing, session management
- **Email**: PHPMailer integration (configured for development logging)

## 📱 Design Features

- **Mobile-First**: Responsive design optimized for mobile devices
- **Modern UI**: Red and white color scheme with smooth animations
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
GASC Blood Donor Bridge/
├── index.php                 # Landing page
├── config/
│   └── database.php          # Database configuration & utilities
├── database/
│   ├── schema.sql           # Database schema
│   └── setup.php            # Automated setup script
├── assets/
│   ├── css/
│   │   └── style.css        # Main stylesheet
│   ├── js/
│   │   └── script.js        # Main JavaScript
│   └── images/              # Logo and graphics
├── donor/
│   ├── register.php         # Donor registration
│   ├── login.php           # Donor login with password
│   ├── dashboard.php       # Donor dashboard
│   └── verify-email.php    # Email verification
├── admin/
│   ├── login.php           # Admin/Moderator login
│   └── dashboard.php       # Admin dashboard
├── request/
│   ├── blood-request.php   # Blood request form
│   └── request-success.php # Request confirmation
└── logout.php              # Logout handler
```

## 🔐 Security Features

- **CSRF Protection**: All forms protected against CSRF attacks
- **Password Hashing**: Bcrypt with cost factor 12
- **Session Security**: Secure session configuration
- **Rate Limiting**: Prevents brute force attacks
- **Input Validation**: Comprehensive server-side validation
- **SQL Injection Prevention**: Prepared statements throughout
- **Activity Logging**: Complete audit trail

## 🎨 UI/UX Features

- **Responsive Design**: Works on all device sizes
- **Progressive Enhancement**: Works without JavaScript
- **Loading States**: Visual feedback for user actions
- **Error Handling**: User-friendly error messages
- **Accessibility**: Keyboard navigation and screen reader support
- **Modern Animations**: Smooth transitions and hover effects

## 📧 Email System

Currently configured for development with file logging. Email verification is used for:
- Account registration verification
- Password reset functionality (when implemented)

For production:

1. Configure SMTP settings in `config/database.php`
2. Install PHPMailer: `composer require phpmailer/phpmailer`
3. Update email functions to use SMTP

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
