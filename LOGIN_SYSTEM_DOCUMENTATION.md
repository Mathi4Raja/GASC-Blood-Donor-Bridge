# GASC Blood Donor Bridge - Login System Documentation

## 🔐 Dual Login System

The GASC Blood Donor Bridge now supports two login methods for donor convenience and security:

### 1. **Primary Login Method: Password-Based** (`login.php`)
- **Default**: This is the main login method
- **How it works**: Email + Password authentication
- **Features**:
  - Fast and familiar login experience
  - Remember me functionality
  - Password show/hide toggle
  - Account verification checks
  - Rate limiting protection
  - CSRF protection

### 2. **Alternative Login Method: OTP-Based** (`login_new.php`)
- **Alternative**: For users who prefer OTP verification
- **How it works**: Email verification followed by OTP (sent via email and SMS)
- **Features**:
  - Two-step verification process
  - OTP sent to both email and SMS
  - 10-minute OTP expiry with countdown timer
  - Resend OTP functionality
  - Rate limiting protection
  - Auto-submit when 6 digits entered

## 🔄 Navigation Between Login Methods

### From Regular Login to OTP Login:
- Click "Login with OTP (Alternative)" button on the regular login page
- Users are redirected to the OTP-based login system

### From OTP Login to Regular Login:
- Click "Use Regular Login (Password)" link on the OTP login page
- Users are redirected back to the regular password-based login

## 🛡️ Security Features (Both Methods)

1. **CSRF Protection**: All forms protected against CSRF attacks
2. **Rate Limiting**: 
   - Login attempts: Max 5 attempts per 5 minutes
   - OTP requests: Max 3 requests per 5 minutes
3. **Account Verification**: 
   - Email verification required
   - Admin verification required
   - Account must be active
4. **Session Management**: Secure session handling
5. **Input Sanitization**: All inputs sanitized and validated

## 🎯 When to Use Each Method

### Use Regular Login (Password) When:
- Quick daily access needed
- User has a strong, memorable password
- Faster login process preferred
- SMS/Email delivery might be unreliable

### Use OTP Login When:
- Extra security needed
- User forgot password temporarily
- User prefers not to store passwords in browser
- Two-factor authentication preference
- Mobile verification desired

## ⚙️ Configuration

### Email & SMS Settings
- Configure SMTP settings in `config/email.php`
- Configure SMS services in `config/sms.php`
- OTP settings managed in `config/otp.php`

### System Settings
- OTP expiry: 10 minutes (configurable)
- Rate limiting: 5 login attempts per 5 minutes
- Session timeout: Standard PHP session handling

## 🔧 Maintenance

### Log Files
- Regular login activities logged to `activity_logs` table
- OTP activities logged to `otp_verifications` table
- Email/SMS delivery logged to respective log files

### Database Tables
- `users`: Main user authentication data
- `otp_verifications`: OTP codes and verification tracking
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

4. **SMS delivery issues**:
   - Verify phone number is correct
   - Check network connectivity
   - Try OTP via email instead
   - Use regular login as backup

---

**Note**: The system automatically falls back to logging notifications if email/SMS services are unavailable, ensuring system reliability.
