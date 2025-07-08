<?php
// Movie Night Registration System Configuration
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'movie_night_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security Configuration
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'WD123');
define('ADMIN_KEY', 'WD2025MovieNight');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Application Configuration
define('MAX_ATTENDEES_PER_BOOKING', 4);
define('SITE_NAME', 'WD Movie Night');
define('TIMEZONE', 'Asia/Kuala_Lumpur');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Get database connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting
 */
function checkRateLimit($identifier, $maxRequests = 10, $timeWindow = 60) {
    $pdo = getDBConnection();
    
    // Clean old entries
    $cleanStmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $cleanStmt->execute([$timeWindow]);
    
    // Count current requests
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $countStmt->execute([$identifier, $timeWindow]);
    $currentRequests = $countStmt->fetchColumn();
    
    if ($currentRequests >= $maxRequests) {
        return false;
    }
    
    // Log this request
    $logStmt = $pdo->prepare("INSERT INTO rate_limits (identifier, request_count, created_at) VALUES (?, 1, NOW())");
    $logStmt->execute([$identifier]);
    
    return true;
}

/**
 * Log admin activity
 */
function logAdminActivity($action, $targetType = null, $targetId = null, $details = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['admin_username'] ?? 'admin',
            $action,
            $targetType,
            $targetId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Check if registration is enabled
 */
function isRegistrationEnabled() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'registration_enabled'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result === 'true';
    } catch (Exception $e) {
        error_log("Failed to check registration status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get event setting
 */
function getEventSetting($key, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        error_log("Failed to get event setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Validate employee number format
 */
function validateEmployeeNumber($empNumber) {
    return preg_match('/^[A-Z0-9]{3,20}$/', $empNumber);
}

/**
 * Check if employee already registered
 */
function isEmployeeRegistered($empNumber) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE emp_number = ? AND status = 'active'");
        $stmt->execute([$empNumber]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Failed to check employee registration: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available seats for hall and shift
 */
function getAvailableSeats($hallId, $shiftId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT id, seat_number, row_letter, seat_position, status 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? 
            ORDER BY row_letter, seat_position
        ");
        $stmt->execute([$hallId, $shiftId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to get available seats: " . $e->getMessage());
        return [];
    }
}

/**
 * Validate seat selection (adjacent seats)
 */
function validateSeatSelection($seats, $hallId, $shiftId) {
    if (empty($seats) || !is_array($seats)) {
        return false;
    }
    
    // Check if all seats are available
    $pdo = getDBConnection();
    $placeholders = str_repeat('?,', count($seats) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM seats 
        WHERE hall_id = ? AND shift_id = ? AND seat_number IN ($placeholders) AND status = 'available'
    ");
    $params = array_merge([$hallId, $shiftId], $seats);
    $stmt->execute($params);
    
    if ($stmt->fetchColumn() != count($seats)) {
        return false;
    }
    
    // Check adjacency (simplified - seats should be consecutive)
    if (count($seats) > 1) {
        $stmt = $pdo->prepare("
            SELECT seat_number, row_letter, seat_position 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? AND seat_number IN ($placeholders)
            ORDER BY row_letter, seat_position
        ");
        $stmt->execute($params);
        $seatData = $stmt->fetchAll();
        
        // Group by row
        $seatsByRow = [];
        foreach ($seatData as $seat) {
            $seatsByRow[$seat['row_letter']][] = (int)$seat['seat_position'];
        }
        
        // Check each row has consecutive seats
        foreach ($seatsByRow as $row => $positions) {
            sort($positions);
            for ($i = 1; $i < count($positions); $i++) {
                if ($positions[$i] - $positions[$i-1] !== 1) {
                    return false;
                }
            }
        }
    }
    
    return true;
}

/**
 * Reserve seats
 */
function reserveSeats($seats, $hallId, $shiftId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($seats) - 1) . '?';
        $stmt = $pdo->prepare("
            UPDATE seats 
            SET status = 'occupied', updated_at = NOW() 
            WHERE hall_id = ? AND shift_id = ? AND seat_number IN ($placeholders) AND status = 'available'
        ");
        $params = array_merge([$hallId, $shiftId], $seats);
        $stmt->execute($params);
        
        if ($stmt->rowCount() != count($seats)) {
            $pdo->rollBack();
            return false;
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Failed to reserve seats: " . $e->getMessage());
        return false;
    }
}

/**
 * Release seats
 */
function releaseSeats($seats, $hallId, $shiftId) {
    try {
        $pdo = getDBConnection();
        $placeholders = str_repeat('?,', count($seats) - 1) . '?';
        $stmt = $pdo->prepare("
            UPDATE seats 
            SET status = 'available', updated_at = NOW() 
            WHERE hall_id = ? AND shift_id = ? AND seat_number IN ($placeholders)
        ");
        $params = array_merge([$hallId, $shiftId], $seats);
        $stmt->execute($params);
        return true;
    } catch (Exception $e) {
        error_log("Failed to release seats: " . $e->getMessage());
        return false;
    }
}

// Initialize database connection on include
getDBConnection();
?>