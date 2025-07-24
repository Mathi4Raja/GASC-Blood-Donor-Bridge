# Manual Database Setup Instructions

## Option 1: Using phpMyAdmin (Recommended)

1. **Open phpMyAdmin**
   - Go to `http://localhost/phpmyadmin`
   - Login with your MySQL credentials (usually username: `root`, password: leave blank)

2. **Create Database**
   - Click "New" in the left sidebar
   - Database name: `gasc_blood_bridge`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Import Tables**
   - Select the `gasc_blood_bridge` database from the left sidebar
   - Click the "Import" tab
   - Choose file: Browse and select `database/schema-phpmyadmin.sql`
   - Click "Go"

4. **Verify Setup**
   - You should see 5 tables created: `users`, `blood_requests`, `otp_verifications`, `activity_logs`, `system_settings`
   - Check that sample data has been inserted

## Option 2: Using MySQL Command Line

1. **Open Command Prompt/Terminal**
   - Navigate to your MySQL bin directory (usually `C:\xampp\mysql\bin\`)

2. **Connect to MySQL**
   ```bash
   mysql -u root -p
   ```

3. **Create Database**
   ```sql
   CREATE DATABASE gasc_blood_bridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE gasc_blood_bridge;
   ```

4. **Import Schema**
   ```bash
   source path/to/your/database/schema-phpmyadmin.sql
   ```

## Default Login Credentials

After successful setup, you can use these default accounts:

### Admin Account
- **Email:** admin@gasc.edu
- **Password:** secret

### Moderator Account
- **Email:** moderator@gasc.edu  
- **Password:** secret

### Sample Donor Account
- **Email:** john.doe@student.gasc.edu
- **Password:** secret

**⚠️ IMPORTANT:** Change these default passwords immediately after first login using the password change feature in Admin Settings!

## Changing Default Passwords

After logging in with the default credentials:

1. **For Admin Account:**
   - Login with `admin@gasc.edu` / `secret`
   - Go to **Admin Dashboard** → **System Settings**
   - Use the **Change Password** section in the right sidebar
   - Enter current password: `secret`
   - Set your new secure password
   - Click "Change Password"

2. **For Moderator Account:**
   - Login with `moderator@gasc.edu` / `secret`  
   - Go to **Dashboard** → **System Settings**
   - Use the **Change Password** section to update password

## Testing the Setup

1. Go to `http://localhost/GASC%20Blood%20Donor%20Bridge/`
2. Try registering a new donor account
3. Try logging in with the default admin credentials
4. Check if all features work properly

## Troubleshooting

- **Connection Failed:** Make sure XAMPP's MySQL service is running
- **Database Not Found:** Ensure you created the database with the exact name `gasc_blood_bridge`
- **Permission Errors:** Check that the web server has read/write access to the project directory
- **Import Errors:** Use the `schema-phpmyadmin.sql` file instead of the original `schema.sql`
- **"Unknown column 'type' in 'field list'":** This means you imported the wrong schema file. The correct schema uses `purpose` instead of `type` for the OTP table. Re-import using `schema-phpmyadmin.sql`
- **OTP Login Issues:** If OTP verification fails, check that the `otp_verifications` table was created properly with the `purpose` column
- **Email Sending Issues:** The system uses a simple PHP mail function. For production, configure proper SMTP settings
