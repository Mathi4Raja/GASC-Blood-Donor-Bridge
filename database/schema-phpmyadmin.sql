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
    blood_group VARCHAR(15) NULL COMMENT 'Supports standard (O+, A-, etc.) and extended (A1+, A2B-, etc.) blood groups',
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
    blood_group VARCHAR(15) NOT NULL COMMENT 'Supports standard and extended blood group formats',
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
    description TEXT NULL COMMENT 'Description of what this setting controls',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Blood group reference table for validation and compatibility
CREATE TABLE blood_group_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blood_group VARCHAR(15) UNIQUE NOT NULL,
    abo_type ENUM('O', 'A', 'B', 'AB', 'A1', 'A2', 'A1B', 'A2B') NOT NULL,
    rh_factor ENUM('+', '-') NOT NULL,
    is_standard BOOLEAN DEFAULT TRUE COMMENT 'TRUE for standard 8 types, FALSE for extended types',
    is_active BOOLEAN DEFAULT TRUE,
    description VARCHAR(100) NULL,
    population_percentage DECIMAL(4,2) NULL COMMENT 'Approximate population percentage',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_abo_type (abo_type),
    INDEX idx_rh_factor (rh_factor),
    INDEX idx_is_standard (is_standard)
) COMMENT = 'Reference table for all supported blood group types including ABO subtypes';

-- Blood group compatibility matrix
CREATE TABLE blood_group_compatibility (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_blood_group VARCHAR(15) NOT NULLName of the site/application'),
('admin_email', 'admin@gasc.edu', 'Primary administrator email address'),
('max_requests_per_user', '5', 'Maximum blood requests per user per day'),
('max_login_attempts', '5', 'Maximum failed login attempts before lockout'),
('session_timeout_minutes', '30', 'Session timeout in minutes'),
('email_notifications', '1', 'Enable or disable email notifications'),
('auto_expire_requests', '1', 'Automatically expire blood requests based on urgency'),
('require_email_verification', '1', 'Require email verification for new user registrations'),
('allow_registrations', '1', 'Allow new user registrations'),
('auto_backup_enabled', '0', 'Enable automatic database backups'),
('blood_matching_mode', 'acceptable', 'Blood matching mode: perfect (exact match only) or acceptable (compatible matches)'),
('strict_blood_matching', '0', 'Enable strict blood group matching (1 = perfect match only, 0 = allow compatible matches)'),
('blood_subtype_awareness', '1', 'Consider blood group subtypes in matching (1 = enabled, 0 = use main groups only)'),
('blood_matching_help_text', 'Perfect Match: Only donors with exact blood group. Acceptable Match: Donors with compatible blood groups.', 'Help text explaining blood matching modes');

-- Insert blood group types (standard 8 + extended 8 with subtypes)
INSERT INTO blood_group_types (blood_group, abo_type, rh_factor, is_standard, description, population_percentage) VALUES
-- Standard blood groups
('O-', 'O', '-', TRUE, 'Universal Donor', 6.60),
('O+', 'O', '+', TRUE, 'Most common blood type', 37.40),
('A-', 'A', '-', TRUE, 'Standard A negative', 6.30),
('A+', 'A', '+', TRUE, 'Standard A positive', 35.70),
('B-', 'B', '-', TRUE, 'Standard B negative', 1.50),
('B+', 'B', '+', TRUE, 'Standard B positive', 8.50),
('AB-', 'AB', '-', TRUE, 'Rare AB negative', 0.60),
('AB+', 'AB', '+', TRUE, 'Universal Recipient', 3.40),
-- Extended ABO subtype blood groups
('A1-', 'A1', '-', FALSE, 'A1 subtype negative', 5.04),
('A1+', 'A1', '+', FALSE, 'A1 subtype positive', 28.56),
('A2-', 'A2', '-', FALSE, 'A2 subtype negative', 1.26),
('A2+', 'A2', '+', FALSE, 'A2 subtype positive', 7.14),
('A1B-', 'A1B', '-', FALSE, 'A1B subtype negative', 0.48),
('A1B+', 'A1B', '+', FALSE, 'A1B subtype positive', 2.72),
('A2B-', 'A2B', '-', FALSE, 'A2B subtype negative', 0.12),
('A2B+', 'A2B', '+', FALSE, 'A2B subtype positive', 0.68);

-- Insert blood group compatibility rules
-- O- (Universal Donor) compatibility
INSERT INTO blood_group_compatibility (donor_blood_group, recipient_blood_group, compatibility_level, notes) VALUES
('O-', 'O-', 'perfect', 'Perfect match'),
('O-', 'O+', 'perfect', 'Compatible Rh- to Rh+'),
('O-', 'A-', 'perfect', 'Universal donor compatibility'),
('O-', 'A+', 'perfect', 'Universal donor compatibility'),
('O-', 'A1-', 'perfect', 'Universal donor to A1 subtype'),
('O-', 'A1+', 'perfect', 'Universal donor to A1 subtype'),
('O-', 'A2-', 'perfect', 'Universal donor to A2 subtype'),
('O-', 'A2+', 'perfect', 'Universal donor to A2 subtype'),
('O-', 'B-', 'perfect', 'Universal donor compatibility'),
('O-', 'B+', 'perfect', 'Universal donor compatibility'),
('O-', 'AB-', 'perfect', 'Universal donor compatibility'),
('O-', 'AB+', 'perfect', 'Universal donor compatibility'),
('O-', 'A1B-', 'perfect', 'Universal donor to A1B subtype'),
('O-', 'A1B+', 'perfect', 'Universal donor to A1B subtype'),
('O-', 'A2B-', 'perfect', 'Universal donor to A2B subtype'),
('O-', 'A2B+', 'perfect', 'Universal donor to A2B subtype'),
-- O+ compatibility
('O+', 'O+', 'perfect', 'Perfect match'),
('O+', 'A+', 'perfect', 'O+ to A+ compatibility'),
('O+', 'A1+', 'perfect', 'O+ to A1+ compatibility'),
('O+', 'A2+', 'perfect', 'O+ to A2+ compatibility'),
('O+', 'B+', 'perfect', 'O+ to B+ compatibility'),
('O+', 'AB+', 'perfect', 'O+ to AB+ compatibility'),
('O+', 'A1B+', 'perfect', 'O+ to A1B+ compatibility'),
('O+', 'A2B+', 'perfect', 'O+ to A2B+ compatibility'),
-- A1 subtype compatibility
('A1-', 'A1-', 'perfect', 'Perfect A1 match'),
('A1-', 'A1+', 'perfect', 'A1- to A1+ compatibility'),
('A1-', 'A-', 'acceptable', 'A1 to general A compatibility'),
('A1-', 'A+', 'acceptable', 'A1 to general A compatibility'),
('A1-', 'AB-', 'perfect', 'A1 to AB compatibility'),
('A1-', 'AB+', 'perfect', 'A1 to AB compatibility'),
('A1-', 'A1B-', 'perfect', 'A1 to A1B compatibility'),
('A1-', 'A1B+', 'perfect', 'A1 to A1B compatibility'),
('A1+', 'A1+', 'perfect', 'Perfect A1+ match'),
('A1+', 'A+', 'acceptable', 'A1+ to general A+ compatibility'),
('A1+', 'AB+', 'perfect', 'A1+ to AB+ compatibility'),
('A1+', 'A1B+', 'perfect', 'A1+ to A1B+ compatibility'),
-- A2 subtype compatibility
('A2-', 'A2-', 'perfect', 'Perfect A2 match'),
('A2-', 'A2+', 'perfect', 'A2- to A2+ compatibility'),
('A2-', 'A-', 'acceptable', 'A2 to general A compatibility'),
('A2-', 'A+', 'acceptable', 'A2 to general A compatibility'),
('A2-', 'AB-', 'perfect', 'A2 to AB compatibility'),
('A2-', 'AB+', 'perfect', 'A2 to AB compatibility'),
('A2-', 'A2B-', 'perfect', 'A2 to A2B compatibility'),
('A2-', 'A2B+', 'perfect', 'A2 to A2B compatibility'),
('A2+', 'A2+', 'perfect', 'Perfect A2+ match'),
('A2+', 'A+', 'acceptable', 'A2+ to general A+ compatibility'),
('A2+', 'AB+', 'perfect', 'A2+ to AB+ compatibility'),
('A2+', 'A2B+', 'perfect', 'A2+ to A2B+ compatibility'),
-- Standard blood group compatibility
('A-', 'A-', 'perfect', 'Perfect A- match'),
('A-', 'A+', 'perfect', 'A- to A+ compatibility'),
('A-', 'AB-', 'perfect', 'A- to AB- compatibility'),
('A-', 'AB+', 'perfect', 'A- to AB+ compatibility'),
('A+', 'A+', 'perfect', 'Perfect A+ match'),
('A+', 'AB+', 'perfect', 'A+ to AB+ compatibility'),
('B-', 'B-', 'perfect', 'Perfect B- match'),
('B-', 'B+', 'perfect', 'B- to B+ compatibility'),
('B-', 'AB-', 'perfect', 'B- to AB- compatibility'),
('B-', 'AB+', 'perfect', 'B- to AB+ compatibility'),
('B+', 'B+', 'perfect', 'Perfect B+ match'),
('B+', 'AB+', 'perfect', 'B+ to AB+ compatibility'),
-- A1B subtype compatibility
('A1B-', 'A1B-', 'perfect', 'Perfect A1B- match'),
('A1B-', 'A1B+', 'perfect', 'A1B- to A1B+ compatibility'),
('A1B-', 'AB-', 'acceptable', 'A1B to general AB compatibility'),
('A1B-', 'AB+', 'acceptable', 'A1B to general AB compatibility'),
('A1B+', 'A1B+', 'perfect', 'Perfect A1B+ match'),
('A1B+', 'AB+', 'acceptable', 'A1B+ to general AB+ compatibility'),
-- A2B subtype compatibility
('A2B-', 'A2B-', 'perfect', 'Perfect A2B- match'),
('A2B-', 'A2B+', 'perfect', 'A2B- to A2B+ compatibility'),
('A2B-', 'AB-', 'acceptable', 'A2B to general AB compatibility'),
('A2B-', 'AB+', 'acceptable', 'A2B to general AB compatibility'),
('A2B+', 'A2B+', 'perfect', 'Perfect A2B+ match'),
('A2B+', 'AB+', 'acceptable', 'A2B+ to general AB+ compatibility'),
-- AB standard compatibility
('AB-', 'AB-', 'perfect', 'Perfect AB- match'),
('AB-', 'AB+', 'perfect', 'AB- to AB+ compatibility'),
('AB+', 'AB+', 'perfect', 'Perfect AB+ match
    INDEX idx_recipient_blood_group (recipient_blood_group),
    FOREIGN KEY (donor_blood_group) REFERENCES blood_group_types(blood_group) ON DELETE CASCADE,
    FOREIGN KEY (recipient_blood_group) REFERENCES blood_group_types(blood_group) ON DELETE CASCADE
) COMMENT = 'Compatibility matrix for blood group matching including subtype compatibility';

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
('max_requests_per_user', '5', 'Maximum requests per user per day'),
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