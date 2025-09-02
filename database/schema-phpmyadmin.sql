-- GASC Blood Donor Bridge Database Schema
-- Cleaned for phpMyAdmin Import
-- Compatible with MySQL 5.7+ and PHP 7.2

-- Note: Create database 'gasc_blood_bridge' manually in phpMyAdmin first

-- Users table for all user types (donors, moderators, admins)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    roll_no VARCHAR(20) NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('donor', 'moderator', 'admin') NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    date_of_birth DATE NULL,
    class VARCHAR(50) NULL,
    blood_group VARCHAR(10) NULL,
    city VARCHAR(100) NULL,
    last_donation_date DATE NULL,
    is_available BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(64) NULL,
    reset_token VARCHAR(64) NULL,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    
    INDEX idx_email (email),
    INDEX idx_blood_group (blood_group),
    INDEX idx_city (city),
    INDEX idx_user_type (user_type),
    INDEX idx_is_available (is_available),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Blood requests table
CREATE TABLE blood_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requester_email VARCHAR(100) NOT NULL,
    requester_name VARCHAR(100) NOT NULL,
    requester_phone VARCHAR(15) NOT NULL,
    blood_group VARCHAR(10) NOT NULL,
    urgency ENUM('Critical', 'Urgent', 'Normal') NOT NULL,
    details TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    units_needed INT DEFAULT 1,
    status ENUM('Active', 'Fulfilled', 'Expired', 'Cancelled') DEFAULT 'Active',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_blood_group (blood_group),
    INDEX idx_city (city),
    INDEX idx_status (status),
    INDEX idx_urgency (urgency),
    INDEX idx_expires_at (expires_at)
);

-- OTP verifications table (used for email verification and password reset only)
CREATE TABLE otp_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    purpose ENUM('registration', 'password_reset') NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_otp (otp),
    INDEX idx_expires_at (expires_at)
);

-- Donor availability history table
CREATE TABLE donor_availability_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    donation_date DATE NOT NULL,
    location VARCHAR(255) NULL,
    units_donated INT DEFAULT 1,
    blood_bank_name VARCHAR(255) NULL,

    notes TEXT NULL,
    verified_by INT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_donor_id (donor_id),
    INDEX idx_donation_date (donation_date),
    INDEX idx_verified (is_verified),
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Blood group statistics view
-- REMOVED: CREATE VIEW not supported on many hosting providers
-- Use getBloodGroupStats() function in config/database.php instead

-- Recent requests view  
-- REMOVED: CREATE VIEW not supported on many hosting providers
-- Use getRecentRequestsStats() function in config/database.php instead

-- Composite indexes for better performance
CREATE INDEX idx_requests_composite ON blood_requests(status, blood_group, city, urgency);
CREATE INDEX idx_logs_composite ON activity_logs(user_id, action, created_at);

-- Insert default admin and moderator accounts
-- Default password for all accounts: secret
INSERT INTO users (name, email, phone, password_hash, user_type, is_verified, is_active, email_verified) VALUES
('System Administrator', 'admin@gasc.edu', '9999999999', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin', TRUE, TRUE, TRUE),
('System Moderator', 'moderator@gasc.edu', '9999999998', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'moderator', TRUE, TRUE, TRUE);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'GASC Blood Donor Bridge', 'Website name'),
('admin_email', 'admin@gasc.edu', 'Administrator email address'),
('request_expiry_days', '30', 'Default expiry days for blood requests'),
('max_requests_per_user', '5', 'Maximum requests per user'),
('donation_cooldown_days', '56', 'General donation cooldown in days'),
('male_donation_gap_months', '3', 'Minimum months between donations for males'),
('female_donation_gap_months', '4', 'Minimum months between donations for females'),
('otp_expiry_minutes', '10', 'OTP expiry time in minutes'),
('max_login_attempts', '5', 'Maximum login attempts before lockout'),
('session_timeout_minutes', '30', 'Session timeout in minutes'),
('email_notifications', '1', 'Enable email notifications'),
('auto_expire_requests', '1', 'Auto-expire old requests'),
('require_email_verification', '1', 'Require email verification for new accounts'),
('allow_registrations', '1', 'Allow new user registrations');

-- Insert sample donor data for testing (password: "secret")
-- Test donor login credentials: Use any email below with password "secret"
INSERT INTO users (roll_no, name, email, phone, password_hash, user_type, gender, date_of_birth, class, blood_group, city, is_verified, is_active, email_verified) VALUES
('CS21001', 'John Doe', 'john.doe@student.gasc.edu', '9876543210', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'donor', 'Male', '2002-03-15', 'B.Tech CSE 3rd Year', 'O+', 'Delhi', TRUE, TRUE, TRUE),
('CS21002', 'Jane Smith', 'jane.smith@student.gasc.edu', '9876543211', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'donor', 'Female', '2001-08-22', 'B.Tech CSE 3rd Year', 'A+', 'Delhi', TRUE, TRUE, TRUE),
('EE21001', 'Mike Johnson', 'mike.johnson@student.gasc.edu', '9876543212', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'donor', 'Male', '2003-11-05', 'B.Tech EE 2nd Year', 'B+', 'Mumbai', TRUE, TRUE, TRUE);

-- Insert sample blood requests for testing
INSERT INTO blood_requests (requester_email, requester_name, requester_phone, blood_group, urgency, details, city, units_needed, expires_at) VALUES
('emergency@hospital.com', 'City Hospital', '9876543213', 'O+', 'Critical', 'Urgent need for accident victim', 'Delhi', 2, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('blood.bank@medical.com', 'Blood Bank Center', '9876543214', 'A+', 'Normal', 'Regular blood bank restocking', 'Mumbai', 5, DATE_ADD(NOW(), INTERVAL 30 DAY));