<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'movie_night_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session Security Configuration (MUST be before session_start())
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Security Configuration
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('MAX_ATTENDEES_PER_BOOKING', 3);

// Rate Limiting Configuration
define('RATE_LIMIT_REQUESTS', 30);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Application Settings
define('APP_NAME', 'WD Movie Night');
define('APP_VERSION', '2.0.0');
define('ADMIN_EMAIL', 'admin@company.com');
define('ADMIN_KEY', 'wd_movie_night_admin_2025');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('Asia/Singapore');

/**
 * Database Connection with PDO
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * CSRF Token Generation and Validation
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Input Sanitization
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Rate Limiting
 */
function checkRateLimit($identifier, $maxRequests = RATE_LIMIT_REQUESTS, $timeWindow = RATE_LIMIT_WINDOW) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    
    $currentTime = time();
    $timeDiff = $currentTime - $_SESSION[$key]['start_time'];
    
    if ($timeDiff > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $currentTime];
        return true;
    }
    
    if ($_SESSION[$key]['count'] >= $maxRequests) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Authentication Functions
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin-login.php');
        exit;
    }
}

function adminLogin($username, $password) {
    try {
        $pdo = getDBConnection();
        
        // Check if admin_users table exists, fallback to hardcoded if not
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_users'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Use database-based authentication
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_login_time'] = time();
                
                // Log successful login
                logSecurityEvent('admin_login_success', $username, 'low');
                
                return true;
            }
        }
        // Log failed login attempt
        logSecurityEvent('admin_login_failed', $username, 'medium');
        return false;
        
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        logSecurityEvent('admin_login_error', $username, 'high');
        return false;
    }
}

function adminLogout() {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_login_time']);
    session_regenerate_id(true);
}

/**
 * Registration Functions
 */
function isRegistrationEnabled() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'registration_enabled'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result === '1' || $result === 'true';
    } catch (Exception $e) {
        error_log("Error checking registration status: " . $e->getMessage());
        return false;
    }
}

function isEmployeeRegistered($empNumber) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE emp_number = ? AND status = 'active'");
        $stmt->execute([$empNumber]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking employee registration: " . $e->getMessage());
        return false;
    }
}

function getEventSetting($key, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        error_log("Error getting event setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Logging Functions
 */
function logActivity($action, $details = '', $userId = null) {
    try {
        $pdo = getDBConnection();
        // Check if activity_logs table exists, if not, just log to error log
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'activity_logs'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } else {
            // Fallback to error log if table doesn't exist
            error_log("Activity Log: $action - $details - User: $userId");
        }
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Security Event Logging Function
 */
function logSecurityEvent($eventType, $userId = null, $riskLevel = 'low', $details = []) {
    try {
        $pdo = getDBConnection();
        
        // Check if security_audit_log table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'security_audit_log'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO security_audit_log (event_type, user_id, ip_address, user_agent, details, risk_level, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $eventType,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode($details),
                $riskLevel
            ]);
        } else {
            // Fallback to error log
            error_log("Security Event: $eventType - User: $userId - Risk: $riskLevel - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    } catch (Exception $e) {
        error_log("Error logging security event: " . $e->getMessage());
    }
}

/**
 * Admin Activity Logging Function
 */
function logAdminActivity($action, $targetType = null, $targetId = null, $details = []) {
    try {
        $pdo = getDBConnection();
        $adminUser = $_SESSION['admin_username'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $adminUser,
            $action,
            $targetType,
            $targetId,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error logging admin activity: " . $e->getMessage());
    }
}

/**
 * Utility Functions
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return '';
    
    try {
        $date = new DateTime($datetime);
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function isValidPhoneNumber($phone) {
    return preg_match('/^[\+]?[0-9\s\-$$$$]{8,15}$/', $phone);
}

/**
 * File Upload Functions
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file upload parameters.');
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file was uploaded.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File size exceeds the maximum allowed size.');
        default:
            throw new Exception('Unknown file upload error.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds the maximum allowed size.');
    }
    
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!array_key_exists($extension, $allowedMimes) || 
        !in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type. Only ' . implode(', ', $allowedTypes) . ' files are allowed.');
    }
    
    return true;
}

/**
 * Email Functions (placeholder for future implementation)
 */
function sendEmail($to, $subject, $message, $headers = '') {
    // Placeholder for email functionality
    // In production, integrate with proper email service
    error_log("Email would be sent to: $to, Subject: $subject");
    return true;
}

/**
 * Security Headers
 */
function setSecurityHeaders() {
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // HTTPS enforcement
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    // Remove server information
    header_remove('X-Powered-By');
    header_remove('Server');
}

// Set security headers for all requests
setSecurityHeaders();

/**
 * Error Handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $errorType = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'Unknown Error';
    $errorMessage = "[$errorType] $errstr in $errfile on line $errline";
    
    error_log($errorMessage);
    
    // Don't execute PHP internal error handler
    return true;
}

// Set custom error handler
set_error_handler('customErrorHandler');

/**
 * Exception Handler
 */
function customExceptionHandler($exception) {
    $errorMessage = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine();
    
    error_log($errorMessage);
    
    // In production, show generic error message
    if (ini_get('display_errors')) {
        echo "<h1>Application Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        echo "<h1>Application Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
    }
}

// Set custom exception handler
set_exception_handler('customExceptionHandler');

/**
 * Shutdown Handler
 */
function shutdownHandler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorMessage = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log($errorMessage);
    }
}

// Register shutdown handler
register_shutdown_function('shutdownHandler');

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

/**
 * Admin CSRF Token Generation and Validation
 */
function generateAdminCSRFToken() {
    if (!isset($_SESSION['admin_csrf_token']) || !isset($_SESSION['admin_csrf_token_time']) || 
        (time() - $_SESSION['admin_csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['admin_csrf_token_time'] = time();
    }
    return $_SESSION['admin_csrf_token'];
}

function validateAdminCSRFToken($token) {
    if (!isset($_SESSION['admin_csrf_token']) || !isset($_SESSION['admin_csrf_token_time'])) {
        return false;
    }
    if ((time() - $_SESSION['admin_csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION['admin_csrf_token'], $_SESSION['admin_csrf_token_time']);
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}

/**
 * Admin Session Hardening
 */
function secureAdminSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
    }
}

/**
 * Admin Rate Limiting
 */
function checkAdminRateLimit($action, $maxRequests = 10, $timeWindow = 60) {
    $key = 'admin_rate_' . md5($action . ($_SESSION['admin_username'] ?? $_SERVER['REMOTE_ADDR']));
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    $currentTime = time();
    $timeDiff = $currentTime - $_SESSION[$key]['start_time'];
    if ($timeDiff > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $currentTime];
        return true;
    }
    if ($_SESSION[$key]['count'] >= $maxRequests) {
        return false;
    }
    $_SESSION[$key]['count']++;
    return true;
}

?>
