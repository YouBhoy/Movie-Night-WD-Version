<?php
// Database configuration - Update these for your environment
define('DB_HOST', 'localhost');
define('DB_NAME', 'movie_night_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security settings
define('ADMIN_KEY', 'WD123');
define('MAX_ATTENDEES_PER_BOOKING', 3);
define('SESSION_TIMEOUT', 3600); // 1 hour

// Database connection with enhanced error handling
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

// Security functions
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmployeeNumber($empNumber) {
    return preg_match('/^[A-Z0-9]{3,20}$/', $empNumber);
}

function validateName($name) {
    return strlen($name) >= 2 && strlen($name) <= 255 && 
           preg_match('/^[a-zA-Z\s\-\.\']+$/u', $name);
}

// CSRF Protection
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 1800) {
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_token_time']) &&
           (time() - $_SESSION['csrf_token_time']) <= 1800 &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Initialize secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
?>
