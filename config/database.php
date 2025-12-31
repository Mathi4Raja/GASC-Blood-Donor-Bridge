<?php
// Database configuration for GASC Blood Donor Bridge
// Compatible with PHP 7.2 and MySQL 5.7+

// Set timezone to IST for consistent timestamps across the entire system
require_once __DIR__ . '/timezone.php';

// Load environment configuration
require_once __DIR__ . '/env.php';

// Load Composer autoloader for third-party libraries
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    
    public function __construct() {
        // Load database configuration from environment variables
        $this->host = EnvLoader::get('DB_HOST', 'localhost');
        $this->username = EnvLoader::get('DB_USERNAME', 'root');
        $this->password = EnvLoader::get('DB_PASSWORD', '');
        $this->database = EnvLoader::get('DB_NAME', 'gasc_blood_bridge');
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new mysqli(
                $this->host, 
                $this->username, 
                $this->password, 
                $this->database
            );
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            // Set charset to utf8mb4 for full Unicode support
            $this->connection->set_charset("utf8mb4");
            
            // Ensure timezone synchronization between PHP and MySQL
            // Set both to system timezone
            $phpOffset = date('P'); // Get PHP timezone offset like +05:30
            $this->connection->query("SET time_zone = '$phpOffset'");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            if (empty($params)) {
                $result = $this->connection->query($sql);
                if ($result === false) {
                    throw new Exception("Query failed: " . $this->connection->error);
                }
                return $result;
            } else {
                $stmt = $this->connection->prepare($sql);
                if ($stmt === false) {
                    throw new Exception("Prepare failed: " . $this->connection->error);
                }
                
                if (!empty($params)) {
                    $types = str_repeat('s', count($params)); // Default to string type
                    $stmt->bind_param($types, ...$params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                
                return $result;
            }
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function prepare($sql) {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        return $stmt;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}

// Security and utility functions
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

function isValidBloodGroup($bloodGroup) {
    // Standard blood groups
    $standardGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    
    // Extended ABO subtype blood groups
    $extendedGroups = [
        'A1+', 'A1-',    // A1 subtypes
        'A2+', 'A2-',    // A2 subtypes
        'A1B+', 'A1B-',  // A1B subtypes
        'A2B+', 'A2B-'   // A2B subtypes
    ];
    
    // Combine all valid blood groups
    $validGroups = array_merge($standardGroups, $extendedGroups);
    
    return in_array($bloodGroup, $validGroups);
}

function calculateAvailability($lastDonationDate, $gender) {
    if (empty($lastDonationDate)) {
        return true; // First-time donor
    }
    
    $lastDonation = new DateTime($lastDonationDate);
    $today = new DateTime();
    $interval = $today->diff($lastDonation);
    $monthsDiff = $interval->y * 12 + $interval->m;
    
    // Males: 3 months, Females: 4 months
    $requiredMonths = ($gender === 'Female') ? 4 : 3;
    
    return $monthsDiff >= $requiredMonths;
}

function calculateAge($dateOfBirth) {
    if (empty($dateOfBirth)) {
        return null;
    }
    
    $birthDate = new DateTime($dateOfBirth);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    
    return $age->y;
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session management
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Fixed session timeout: 10 minutes
        $sessionTimeoutSeconds = 10 * 60;
        
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        
        // Auto-detect HTTPS for cookie_secure setting
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                   || $_SERVER['SERVER_PORT'] == 443
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        ini_set('session.cookie_secure', $isHttps ? 1 : 0);
        
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $sessionTimeoutSeconds);
        
        session_start();
        
        // Check for session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $sessionTimeoutSeconds) {
                // Session expired
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['session_expired'] = true;
            }
        }
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else {
            if (time() - $_SESSION['created'] > $sessionTimeoutSeconds) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
}

if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout() {
        // Check if session has been marked as expired
        if (isset($_SESSION['session_expired'])) {
            unset($_SESSION['session_expired']);
            return 'Your session has expired. Please log in again.';
        }
        
        // Check timeout based on last activity
        if (isset($_SESSION['last_activity'])) {
            // Fixed session timeout: 10 minutes
            $sessionTimeoutSeconds = 10 * 60;
            $timeSinceActivity = time() - $_SESSION['last_activity'];
            
            if ($timeSinceActivity > $sessionTimeoutSeconds) {
                // Session has timed out
                $_SESSION['session_expired'] = true;
                destroySession();
                return 'Your session has expired due to inactivity. Please log in again.';
            }
            
            // Update last activity on each check
            $_SESSION['last_activity'] = time();
        }
        
        return false;
    }
}

function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function requireLogin() {
    // Check for session timeout first
    $timeoutMessage = checkSessionTimeout();
    if ($timeoutMessage) {
        // Session has expired - redirect to login with message
        $loginUrl = '../index.php?timeout=1&message=' . urlencode($timeoutMessage);
        header('Location: ' . $loginUrl);
        exit;
    }
    
    // Check if user is still logged in
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['user_type'], $allowedRoles)) {
        header('Location: ../unauthorized.php');
        exit;
    }
}

// Email configuration using PHPMailer
// Logging functions
function logActivity($userId, $action, $details = '') {
    try {
        $db = new Database();
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('issss', 
            $userId, 
            $action, 
            $details, 
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['HTTP_USER_AGENT']
        );
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Rate limiting for security
function checkRateLimit($action, $limit = 5, $timeWindow = 300) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logsDir = __DIR__ . '/../logs';
    $file = $logsDir . '/rate_limit_' . md5($key) . '.tmp';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logsDir)) {
        if (!mkdir($logsDir, 0755, true)) {
            error_log("Failed to create logs directory: $logsDir");
            return true; // Allow request if we can't create logs directory
        }
    }
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && $data['timestamp'] > time() - $timeWindow) {
            if ($data['count'] >= $limit) {
                return false; // Rate limit exceeded
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'timestamp' => time()];
        }
    } else {
        $data = ['count' => 1, 'timestamp' => time()];
    }
    
    if (file_put_contents($file, json_encode($data)) === false) {
        error_log("Failed to write rate limit file: $file");
        // Continue anyway - don't block user if logging fails
    }
    
    return true;
}

/**
 * Get blood group statistics (replaces blood_group_stats view)
 * Alternative to CREATE VIEW for hosting environments without view privileges
 */
function getBloodGroupStats() {
    try {
        $db = new Database();
        
        $sql = "SELECT 
            blood_group,
            COUNT(*) as total_donors,
            SUM(CASE WHEN is_available = TRUE AND is_verified = TRUE AND is_active = TRUE THEN 1 ELSE 0 END) as available_donors,
            SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_donors,
            SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_donors,
            AVG(CASE WHEN last_donation_date IS NOT NULL THEN DATEDIFF(CURDATE(), last_donation_date) ELSE NULL END) as avg_days_since_last_donation
        FROM users 
        WHERE user_type = 'donor' AND blood_group IS NOT NULL 
        GROUP BY blood_group 
        ORDER BY blood_group";
        
        $result = $db->query($sql);
        $stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting blood group stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available donors count by blood group
 * Quick function for dashboard/inventory displays
 */
function getAvailableDonorsByBloodGroup() {
    try {
        $db = new Database();
        
        $sql = "SELECT 
            blood_group,
            COUNT(*) as available_count
        FROM users 
        WHERE user_type = 'donor' 
            AND blood_group IS NOT NULL 
            AND is_available = TRUE 
            AND is_verified = TRUE 
            AND is_active = TRUE
        GROUP BY blood_group 
        ORDER BY blood_group";
        
        $result = $db->query($sql);
        $stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $stats[$row['blood_group']] = $row['available_count'];
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting available donors by blood group: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent blood requests with available donors count
 * Replaces recent_requests view for hosting environments without view privileges
 */
function getRecentRequestsStats($days = 30) {
    try {
        $db = new Database();
        
        $sql = "SELECT 
            br.id,
            br.requester_name,
            br.blood_group,
            br.city,
            br.urgency,
            br.status,
            br.created_at,
            br.units_needed,
            br.details
        FROM blood_requests br 
        WHERE br.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY br.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            // Add available donors count for each request
            $availableCountSql = "SELECT COUNT(*) as count FROM users u 
                WHERE u.blood_group = ? AND u.city = ? 
                AND u.is_available = TRUE AND u.is_verified = TRUE 
                AND u.is_active = TRUE AND u.user_type = 'donor'";
            
            $countStmt = $db->prepare($availableCountSql);
            $countStmt->bind_param('ss', $row['blood_group'], $row['city']);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            
            $row['available_donors_count'] = $countRow['count'];
            $requests[] = $row;
        }
        
        return $requests;
        
    } catch (Exception $e) {
        error_log("Error getting recent requests: " . $e->getMessage());
        return [];
    }
}

function getBloodInventoryStats() {
    try {
        $db = new Database();
        
        // Extended blood groups including ABO subtypes
        $bloodGroups = [
            // Standard ABO/Rh groups
            'O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+',
            // Extended ABO subtype groups
            'A1-', 'A1+', 'A2-', 'A2+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'
        ];
        
        $inventory = [];
        
        foreach ($bloodGroups as $bloodGroup) {
            // Get basic blood group stats
            $statsSql = "SELECT 
                COUNT(*) as total_donors,
                SUM(CASE WHEN is_available = TRUE AND is_verified = TRUE AND is_active = TRUE THEN 1 ELSE 0 END) as available_donors
            FROM users 
            WHERE blood_group = ? AND user_type = 'donor'";
            
            $statsStmt = $db->prepare($statsSql);
            $statsStmt->bind_param('s', $bloodGroup);
            $statsStmt->execute();
            $statsResult = $statsStmt->get_result();
            $stats = $statsResult->fetch_assoc();
            
            // Get active requests count
            $activeRequestsSql = "SELECT COUNT(*) as active_requests 
                FROM blood_requests 
                WHERE blood_group = ? AND status = 'Active'";
            
            $activeStmt = $db->prepare($activeRequestsSql);
            $activeStmt->bind_param('s', $bloodGroup);
            $activeStmt->execute();
            $activeResult = $activeStmt->get_result();
            $activeData = $activeResult->fetch_assoc();
            
            // Get fulfilled requests this month
            $fulfilledSql = "SELECT COUNT(*) as fulfilled_this_month 
                FROM blood_requests 
                WHERE blood_group = ? AND status = 'Fulfilled' 
                AND MONTH(updated_at) = MONTH(CURRENT_DATE()) 
                AND YEAR(updated_at) = YEAR(CURRENT_DATE())";
            
            $fulfilledStmt = $db->prepare($fulfilledSql);
            $fulfilledStmt->bind_param('s', $bloodGroup);
            $fulfilledStmt->execute();
            $fulfilledResult = $fulfilledStmt->get_result();
            $fulfilledData = $fulfilledResult->fetch_assoc();
            
            // Get donors who can donate now (real-time availability)
            $canDonateSql = "SELECT COUNT(*) as can_donate_now FROM users u 
                WHERE u.blood_group = ? 
                AND u.user_type = 'donor' 
                AND u.is_available = TRUE 
                AND u.is_verified = TRUE 
                AND u.is_active = TRUE 
                AND (
                    u.last_donation_date IS NULL 
                    OR (u.gender = 'Female' AND DATEDIFF(CURDATE(), u.last_donation_date) >= 120)
                    OR (u.gender != 'Female' AND DATEDIFF(CURDATE(), u.last_donation_date) >= 90)
                )";
            
            $canDonateStmt = $db->prepare($canDonateSql);
            $canDonateStmt->bind_param('s', $bloodGroup);
            $canDonateStmt->execute();
            $canDonateResult = $canDonateStmt->get_result();
            $canDonateData = $canDonateResult->fetch_assoc();
            
            // Calculate stock status
            $availableDonors = (int)$stats['available_donors'];
            $activeRequests = (int)$activeData['active_requests'];
            
            if ($availableDonors >= $activeRequests * 2) {
                $stockStatus = 'Good';
            } elseif ($availableDonors >= $activeRequests) {
                $stockStatus = 'Low';
            } else {
                $stockStatus = 'Critical';
            }
            
            $inventory[] = [
                'blood_group' => $bloodGroup,
                'total_donors' => (int)$stats['total_donors'],
                'available_donors' => $availableDonors,
                'active_requests' => $activeRequests,
                'fulfilled_this_month' => (int)$fulfilledData['fulfilled_this_month'],
                'stock_status' => $stockStatus,
                'can_donate_now' => (int)$canDonateData['can_donate_now']
            ];
        }
        
        return $inventory;
        
    } catch (Exception $e) {
        error_log("Error getting blood inventory stats: " . $e->getMessage());
        return [];
    }
}

// Start session securely by default
startSecureSession();

// Include system settings helper
require_once __DIR__ . '/system-settings.php';

/**
 * Check if automatic database backup is due (every 3 years)
 */
function isAutomaticBackupDue() {
    try {
        if (!SystemSettings::get('auto_backup_enabled')) {
            return false;
        }
        
        $lastBackup = SystemSettings::get('last_automatic_backup');
        $intervalYears = (int)SystemSettings::get('auto_backup_interval_years', 3);
        
        if (empty($lastBackup)) {
            return true; // Never backed up before
        }
        
        $lastBackupTime = strtotime($lastBackup);
        $intervalSeconds = $intervalYears * 365.25 * 24 * 60 * 60; // Account for leap years
        
        return (time() - $lastBackupTime) >= $intervalSeconds;
    } catch (Exception $e) {
        error_log("Error checking automatic backup due: " . $e->getMessage());
        return false;
    }
}

/**
 * Perform automatic database backup
 */
function performAutomaticDatabaseBackup() {
    try {
        if (!isAutomaticBackupDue()) {
            return ['success' => false, 'message' => 'Automatic backup not due yet'];
        }
        
        $result = createDatabaseBackup('automatic');
        
        if ($result['success']) {
            // Update the last automatic backup timestamp
            SystemSettings::set('last_automatic_backup', date('Y-m-d H:i:s'));
            
            logActivity(null, 'automatic_backup_database', 'Automatic database backup created: ' . $result['filename']);
            
            return [
                'success' => true, 
                'message' => 'Automatic backup created successfully: ' . $result['filename'],
                'filename' => $result['filename']
            ];
        } else {
            logActivity(null, 'automatic_backup_failed', 'Automatic database backup failed: ' . $result['message']);
            return $result;
        }
    } catch (Exception $e) {
        $errorMsg = "Automatic backup error: " . $e->getMessage();
        error_log($errorMsg);
        logActivity(null, 'automatic_backup_error', $errorMsg);
        return ['success' => false, 'message' => $errorMsg];
    }
}

/**
 * Create database backup (used by both manual and automatic systems)
 */
function createDatabaseBackup($type = 'manual', $dateRange = null) {
    try {
        // Get database connection details from environment
        require_once __DIR__ . '/env.php';
        $host = EnvLoader::get('DB_HOST', 'localhost');
        $database = EnvLoader::get('DB_NAME', 'gasc_blood_bridge');
        $username = EnvLoader::get('DB_USERNAME', 'root');
        $password = EnvLoader::get('DB_PASSWORD', '');
        
        // Try PHP-based backup first (MySQLDump-PHP library - no external dependencies)
        if (class_exists('Ifsnop\Mysqldump\Mysqldump')) {
            return createDatabaseBackupPHP($type, $dateRange, $host, $database, $username, $password);
        }
        
        // Fallback to traditional mysqldump if library not available
        return createDatabaseBackupTraditional($type, $dateRange, $host, $database, $username, $password);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Create database backup using MySQLDump-PHP library (preferred method)
 * No external dependencies, works on all hosting environments
 */
function createDatabaseBackupPHP($type, $dateRange, $host, $database, $username, $password) {
    try {
        // Create backup directory if it doesn't exist
        $backupDir = __DIR__ . '/../database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Build the backup file path with date range info if provided
        $backup_filename = 'backup_' . $type . '_' . date('Y-m-d_H-i-s');
        if ($dateRange) {
            $backup_filename .= '_from-' . $dateRange['start'] . '_to-' . $dateRange['end'];
        }
        $backup_filename .= '.sql';
        $backup_file_full = $backupDir . DIRECTORY_SEPARATOR . $backup_filename;
        
        // Configure dump settings
        $dumpSettings = [
            'compress' => \Ifsnop\Mysqldump\Mysqldump::NONE,
            'no-data' => false,
            'add-drop-table' => true,
            'single-transaction' => true,
            'lock-tables' => false,
            'add-locks' => true,
            'extended-insert' => true,
            'disable-keys' => true,
            'skip-triggers' => false,
            'add-drop-trigger' => true,
            'routines' => true,
            'hex-blob' => true
        ];
        
        // Create DSN
        $dsn = "mysql:host=$host;dbname=$database";
        
        // Create dump object
        $dump = new \Ifsnop\Mysqldump\Mysqldump($dsn, $username, $password, $dumpSettings);
        
        // Perform backup
        $dump->start($backup_file_full);
        
        // Verify backup was created
        if (!file_exists($backup_file_full)) {
            throw new Exception('Backup file was not created');
        }
        
        $backup_content = file_get_contents($backup_file_full);
        
        // Validate backup content
        if (empty($backup_content) || strlen($backup_content) < 1000 || strpos($backup_content, 'CREATE TABLE') === false) {
            throw new Exception('Invalid backup content generated');
        }
        
        // Add date range metadata if provided
        if ($dateRange) {
            $metadata = "\n-- BACKUP METADATA\n";
            $metadata .= "-- Backup Type: Manual with Date Range\n";
            $metadata .= "-- Backup Method: PHP MySQLDump Library\n";
            $metadata .= "-- Date Range: {$dateRange['start']} to {$dateRange['end']}\n";
            $metadata .= "-- Period: {$dateRange['start_formatted']} to {$dateRange['end_formatted']}\n";
            $metadata .= "-- Duration: {$dateRange['duration_years']} years, {$dateRange['duration_months']} months, {$dateRange['duration_days']} days\n";
            $metadata .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $metadata .= "-- Note: This is a full database backup with specified date range for reference\n\n";
            
            // Insert metadata after the first comment block
            $backup_content = preg_replace('/^(-- .+?\n\n)/s', '$1' . $metadata, $backup_content, 1);
            file_put_contents($backup_file_full, $backup_content);
        }
        
        $file_size_mb = round(filesize($backup_file_full) / 1024 / 1024, 2);
        $result = [
            'success' => true,
            'filename' => $backup_filename,
            'size_mb' => $file_size_mb,
            'type' => $type,
            'method' => 'PHP Library'
        ];
        
        // Add date range info to result
        if ($dateRange) {
            $result['date_range'] = $dateRange;
        }
        
        return $result;
        
    } catch (Exception $e) {
        // If PHP method fails, try traditional method as fallback
        error_log("PHP backup method failed: " . $e->getMessage() . " - Attempting traditional method");
        return createDatabaseBackupTraditional($type, $dateRange, $host, $database, $username, $password);
    }
}

/**
 * Create database backup using traditional mysqldump command (fallback method)
 * Requires mysqldump to be available on the server
 */
function createDatabaseBackupTraditional($type, $dateRange, $host, $database, $username, $password) {
    try {
        // Find mysqldump
        $possible_paths = [
            'C:\\Program Files\\XAMPP\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.21\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump', // Linux/Mac
            '/usr/local/bin/mysqldump' // Alternative Linux/Mac
        ];
        
        $mysqldump_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $mysqldump_path = $path;
                break;
            }
        }
        
        if (!$mysqldump_path) {
            throw new Exception("mysqldump not found. Please install the MySQLDump-PHP library via Composer: composer require ifsnop/mysqldump-php");
        }
        
        // Create backup directory if it doesn't exist
        $backupDir = __DIR__ . '/../database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Build the backup file path with date range info if provided
        $backup_filename = 'backup_' . $type . '_' . date('Y-m-d_H-i-s');
        if ($dateRange) {
            $backup_filename .= '_from-' . $dateRange['start'] . '_to-' . $dateRange['end'];
        }
        $backup_filename .= '.sql';
        $backup_file_full = $backupDir . DIRECTORY_SEPARATOR . $backup_filename;
        
        // Build command (full backup - date range is for metadata only)
        if (empty($password)) {
            $command = "\"$mysqldump_path\" --user=\"$username\" --host=\"$host\" --single-transaction --routines --triggers \"$database\"";
        } else {
            $command = "\"$mysqldump_path\" --user=\"$username\" --password=\"$password\" --host=\"$host\" --single-transaction --routines --triggers \"$database\"";
        }
        
        // Execute command
        $handle = popen($command, 'r');
        if (!$handle) {
            throw new Exception('Failed to execute mysqldump command');
        }
        
        $backup_content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) break;
            $backup_content .= $chunk;
        }
        $return_code = pclose($handle);
        
        // Validate backup content
        if ($return_code === 0 && !empty($backup_content) && strlen($backup_content) > 1000 && strpos($backup_content, 'CREATE TABLE') !== false) {
            // Add date range metadata to backup content if provided
            if ($dateRange) {
                $metadata = "\n-- BACKUP METADATA\n";
                $metadata .= "-- Backup Type: Manual with Date Range\n";
                $metadata .= "-- Backup Method: Traditional mysqldump\n";
                $metadata .= "-- Date Range: {$dateRange['start']} to {$dateRange['end']}\n";
                $metadata .= "-- Period: {$dateRange['start_formatted']} to {$dateRange['end_formatted']}\n";
                $metadata .= "-- Duration: {$dateRange['duration_years']} years, {$dateRange['duration_months']} months, {$dateRange['duration_days']} days\n";
                $metadata .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
                $metadata .= "-- Note: This is a full database backup with specified date range for reference\n\n";
                
                // Insert metadata after the first comment block
                $backup_content = preg_replace('/^(-- .+?\n\n)/s', '$1' . $metadata, $backup_content, 1);
            }
            
            if (file_put_contents($backup_file_full, $backup_content) !== false) {
                $file_size_mb = round(strlen($backup_content) / 1024 / 1024, 2);
                $result = [
                    'success' => true,
                    'filename' => $backup_filename,
                    'size_mb' => $file_size_mb,
                    'type' => $type,
                    'method' => 'Traditional mysqldump'
                ];
                
                // Add date range info to result
                if ($dateRange) {
                    $result['date_range'] = $dateRange;
                }
                
                return $result;
            } else {
                throw new Exception('Failed to write backup file');
            }
        } else {
            throw new Exception("Invalid backup content or mysqldump error (return code: $return_code)");
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get next automatic backup due date
 */
function getNextAutomaticBackupDate() {
    try {
        $lastBackup = SystemSettings::get('last_automatic_backup');
        $intervalYears = (int)SystemSettings::get('auto_backup_interval_years', 3);
        
        if (empty($lastBackup)) {
            return 'Due now (never backed up)';
        }
        
        $lastBackupTime = strtotime($lastBackup);
        $nextBackupTime = $lastBackupTime + ($intervalYears * 365.25 * 24 * 60 * 60);
        
        return formatISTDateTime(date('Y-m-d H:i:s', $nextBackupTime), 'M j, Y h:i A');
    } catch (Exception $e) {
        return 'Error calculating date: ' . $e->getMessage();
    }
}

/**
 * Get all supported blood groups (standard + extended)
 * @return array Array of all supported blood group types
 */
function getAllSupportedBloodGroups() {
    return [
        // Standard ABO/Rh groups (ordered by compatibility importance)
        'O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+',
        // Extended ABO subtype groups
        'A1-', 'A1+', 'A2-', 'A2+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'
    ];
}

/**
 * Get compatible donor blood groups for a given recipient blood group
 * @param string $recipientBloodGroup The recipient's blood group
 * @param bool $perfectMatchOnly If true, return only exact matches
 * @return array Array of compatible donor blood groups
 */
function getCompatibleDonors($recipientBloodGroup, $perfectMatchOnly = null) {
    // Check system setting if not explicitly specified
    if ($perfectMatchOnly === null) {
        require_once __DIR__ . '/system-settings.php';
        $perfectMatchOnly = SystemSettings::getBloodMatchingMode() === 'perfect';
    }
    
    // Perfect match mode - return only exact blood group
    if ($perfectMatchOnly) {
        return [$recipientBloodGroup];
    }
    
    // Acceptable match mode - return compatible blood groups
    // Comprehensive compatibility matrix including ABO subtypes
    $compatibility = [
        // Standard blood groups
        'O-' => ['O-'],
        'O+' => ['O-', 'O+'],
        'A-' => ['O-', 'A-', 'A1-', 'A2-'],
        'A+' => ['O-', 'O+', 'A-', 'A+', 'A1-', 'A1+', 'A2-', 'A2+'],
        'B-' => ['O-', 'B-'],
        'B+' => ['O-', 'O+', 'B-', 'B+'],
        'AB-' => ['O-', 'A-', 'A1-', 'A2-', 'B-', 'AB-', 'A1B-', 'A2B-'],
        'AB+' => ['O-', 'O+', 'A-', 'A+', 'A1-', 'A1+', 'A2-', 'A2+', 'B-', 'B+', 'AB-', 'AB+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'],
        
        // A1 subtypes
        'A1-' => ['O-', 'A-', 'A1-'],
        'A1+' => ['O-', 'O+', 'A-', 'A+', 'A1-', 'A1+'],
        
        // A2 subtypes
        'A2-' => ['O-', 'A-', 'A2-'],
        'A2+' => ['O-', 'O+', 'A-', 'A+', 'A2-', 'A2+'],
        
        // A1B subtypes
        'A1B-' => ['O-', 'A-', 'A1-', 'B-', 'AB-', 'A1B-'],
        'A1B+' => ['O-', 'O+', 'A-', 'A+', 'A1-', 'A1+', 'B-', 'B+', 'AB-', 'AB+', 'A1B-', 'A1B+'],
        
        // A2B subtypes
        'A2B-' => ['O-', 'A-', 'A2-', 'B-', 'AB-', 'A2B-'],
        'A2B+' => ['O-', 'O+', 'A-', 'A+', 'A2-', 'A2+', 'B-', 'B+', 'AB-', 'AB+', 'A2B-', 'A2B+']
    ];
    
    return $compatibility[$recipientBloodGroup] ?? [$recipientBloodGroup];
}

/**
 * Get compatible recipient blood groups for a given donor blood group
 * @param string $donorBloodGroup The donor's blood group
 * @param bool $perfectMatchOnly If true, return only exact matches
 * @return array Array of compatible recipient blood groups
 */
function getCompatibleRecipients($donorBloodGroup, $perfectMatchOnly = null) {
    // Check system setting if not explicitly specified
    if ($perfectMatchOnly === null) {
        require_once __DIR__ . '/system-settings.php';
        $perfectMatchOnly = SystemSettings::getBloodMatchingMode() === 'perfect';
    }
    
    // Perfect match mode - return only exact blood group
    if ($perfectMatchOnly) {
        return [$donorBloodGroup];
    }
    
    // Acceptable match mode - return compatible blood groups
    // Reverse compatibility matrix - who can receive from this donor
    $compatibility = [
        // Standard blood groups
        'O-' => ['O-', 'O+', 'A-', 'A+', 'A1-', 'A1+', 'A2-', 'A2+', 'B-', 'B+', 'AB-', 'AB+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'], // Universal donor
        'O+' => ['O+', 'A+', 'A1+', 'A2+', 'B+', 'AB+', 'A1B+', 'A2B+'],
        'A-' => ['A-', 'A+', 'A1-', 'A1+', 'A2-', 'A2+', 'AB-', 'AB+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'],
        'A+' => ['A+', 'A1+', 'A2+', 'AB+', 'A1B+', 'A2B+'],
        'B-' => ['B-', 'B+', 'AB-', 'AB+', 'A1B-', 'A1B+', 'A2B-', 'A2B+'],
        'B+' => ['B+', 'AB+', 'A1B+', 'A2B+'],
        'AB-' => ['AB-', 'AB+'],
        'AB+' => ['AB+'],
        
        // A1 subtypes (A1 can donate to A and AB recipients, but specificity matters)
        'A1-' => ['A-', 'A+', 'A1-', 'A1+', 'AB-', 'AB+', 'A1B-', 'A1B+'],
        'A1+' => ['A+', 'A1+', 'AB+', 'A1B+'],
        
        // A2 subtypes (A2 can donate to A and AB recipients)
        'A2-' => ['A-', 'A+', 'A2-', 'A2+', 'AB-', 'AB+', 'A2B-', 'A2B+'],
        'A2+' => ['A+', 'A2+', 'AB+', 'A2B+'],
        
        // A1B subtypes
        'A1B-' => ['AB-', 'AB+', 'A1B-', 'A1B+'],
        'A1B+' => ['AB+', 'A1B+'],
        
        // A2B subtypes
        'A2B-' => ['AB-', 'AB+', 'A2B-', 'A2B+'],
        'A2B+' => ['AB+', 'A2B+']
    ];
    
    return $compatibility[$donorBloodGroup] ?? [$donorBloodGroup];
}

/**
 * Check if a donor blood group is compatible with a recipient blood group
 * @param string $donorBloodGroup The donor's blood group
 * @param string $recipientBloodGroup The recipient's blood group
 * @param bool $perfectMatchOnly If true, check only exact matches
 * @return bool True if compatible, false otherwise
 */
function isBloodGroupCompatible($donorBloodGroup, $recipientBloodGroup, $perfectMatchOnly = null) {
    // Check system setting if not explicitly specified
    if ($perfectMatchOnly === null) {
        require_once __DIR__ . '/system-settings.php';
        $perfectMatchOnly = SystemSettings::getBloodMatchingMode() === 'perfect';
    }
    
    // Perfect match mode - only exact matches are compatible
    if ($perfectMatchOnly) {
        return $donorBloodGroup === $recipientBloodGroup;
    }
    
    // Acceptable match mode - check compatibility matrix
    $compatibleRecipients = getCompatibleRecipients($donorBloodGroup, false);
    return in_array($recipientBloodGroup, $compatibleRecipients);
}

/**
 * Get main ABO group from extended blood group (for fallback compatibility)
 * @param string $bloodGroup Extended blood group (e.g., A1+, A2B-)
 * @return string Main ABO group (e.g., A+, AB-)
 */
function getMainBloodGroup($bloodGroup) {
    $mapping = [
        'A1-' => 'A-', 'A1+' => 'A+',
        'A2-' => 'A-', 'A2+' => 'A+',
        'A1B-' => 'AB-', 'A1B+' => 'AB+',
        'A2B-' => 'AB-', 'A2B+' => 'AB+'
    ];
    
    return $mapping[$bloodGroup] ?? $bloodGroup;
}

/**
 * Get blood group display name with description
 * @param string $bloodGroup Blood group code
 * @return string Formatted display name
 */
function getBloodGroupDisplayName($bloodGroup) {
    $descriptions = [
        // Standard groups
        'O-' => 'O- (Universal Donor)',
        'O+' => 'O+ (Most Common)',
        'A-' => 'A- (Standard)',
        'A+' => 'A+ (Common)',
        'B-' => 'B- (Standard)',
        'B+' => 'B+ (Standard)',
        'AB-' => 'AB- (Rare)',
        'AB+' => 'AB+ (Universal Recipient)',
        
        // Extended groups
        'A1-' => 'A1- (A1 Subtype)',
        'A1+' => 'A1+ (A1 Subtype)',
        'A2-' => 'A2- (A2 Subtype)',
        'A2+' => 'A2+ (A2 Subtype)',
        'A1B-' => 'A1B- (A1B Subtype)',
        'A1B+' => 'A1B+ (A1B Subtype)',
        'A2B-' => 'A2B- (A2B Subtype)',
        'A2B+' => 'A2B+ (A2B Subtype)'
    ];
    
    return $descriptions[$bloodGroup] ?? $bloodGroup;
}

/**
 * Get available donors count for a specific blood group and city with compatibility
 * @param string $neededBloodGroup The blood group needed
 * @param string $city The city to search in
 * @param bool $perfectMatchOnly If true, count only exact matches
 * @return int Number of available compatible donors
 */
function getCompatibleDonorsCount($neededBloodGroup, $city, $perfectMatchOnly = null) {
    try {
        $db = new Database();
        
        // Use the updated getCompatibleDonors function with perfect match setting
        $compatibleDonorGroups = getCompatibleDonors($neededBloodGroup, $perfectMatchOnly);
        
        if (empty($compatibleDonorGroups)) {
            return 0;
        }
        
        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($compatibleDonorGroups) - 1) . '?';
        
        $sql = "SELECT COUNT(*) as count 
                FROM users 
                WHERE user_type = 'donor' 
                    AND blood_group IN ($placeholders)
                    AND city = ?
                    AND is_available = TRUE 
                    AND is_verified = TRUE 
                    AND is_active = TRUE
                    AND email_verified = TRUE";
        
        // Prepare parameters (blood groups + city)
        $params = array_merge($compatibleDonorGroups, [$city]);
        
        $result = $db->query($sql, $params);
        $row = $result->fetch_assoc();
        
        return (int)$row['count'];
        
    } catch (Exception $e) {
        error_log("Error getting compatible donors count: " . $e->getMessage());
        return 0;
    }
}
?>
