<?php
// Database configuration for GASC Blood Donor Bridge
// Compatible with PHP 7.2 and MySQL 5.7+

// Load environment configuration
require_once __DIR__ . '/env.php';

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
    $validGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
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
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
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
    $key = $action . '_' . $_SERVER['REMOTE_ADDR'];
    $logsDir = '../logs';
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
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
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
?>
