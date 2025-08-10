# GASC Blood Donor Bridge - Login System Documentation

## 🔐 Login System

The GASC Blood Donor Bridge uses a secure password-based login system for donor authentication:

### **Password-Based Login** (`login.php`)
- **Primary Method**: Email + Password authentication
- **How it works**: Users login with their registered email and password
- **Features**:
  - Fast and familiar login experience
  - Remember me functionality
  - Password show/hide toggle
  - Account verification checks
  - Rate limiting protection
  - CSRF protection
  - Forgot password functionality

## 🛡️ Security Features

1. **CSRF Protection**: All forms protected against CSRF attacks
2. **Rate Limiting**: 
   - Login attempts: Max 5 attempts per 5 minutes
3. **Account Verification**: 
   - Email verification required
   - Admin verification required
   - Account must be active
4. **Session Management**: Secure session handling
5. **Input Sanitization**: All inputs sanitized and validated

## ⚙️ Configuration

### Email Settings
- Configure SMTP settings in `config/email.php`
- Password reset OTP settings managed in `config/otp.php`

### System Settings
- Password reset OTP expiry: 10 minutes (configurable)
- Rate limiting: 5 login attempts per 5 minutes
- Session timeout: Standard PHP session handling

## 🔧 Maintenance

### Log Files
- Regular login activities logged to `activity_logs` table
- Password reset OTP activities logged to `otp_verifications` table
- Email delivery logged to respective log files

### Database Tables
- `users`: Main user authentication data
- `otp_verifications`: Password reset OTP codes and verification tracking
- `activity_logs`: All login activities and security events

## 🚀 Future Enhancements

Potential improvements for the login system:
1. **Social Login**: Google/Facebook login integration
2. **Biometric Login**: Fingerprint/Face ID support (mobile app)
3. **Push Notifications**: App-based OTP delivery
4. **Advanced 2FA**: TOTP (Google Authenticator) support
5. **Single Sign-On**: Integration with institutional systems

## 📱 Mobile Compatibility

Both login methods are fully responsive and work seamlessly on:
- Desktop browsers
- Mobile browsers
- Tablet devices
- All modern browsers (Chrome, Firefox, Safari, Edge)

## 🆘 Troubleshooting

### Common Issues:

1. **OTP not received**:
   - Check spam/junk folder
   - Verify phone number format
   - Try resending OTP
   - Use regular login as backup

2. **Email verification pending**:
   - Check email for verification link
   - Contact admin if verification link expired
   - Use alternative contact methods

3. **Account locked**:
   - Wait 5 minutes after too many failed attempts
   - Use forgot password if password is incorrect
   - Contact support for account reactivation

3. **Account recovery issues**:
   - Verify phone number is correct
   - Check network connectivity
   - Try OTP via email instead
   - Use regular login as backup

---

**Note**: The system automatically falls back to logging notifications if email services are unavailable, ensuring system reliability.
