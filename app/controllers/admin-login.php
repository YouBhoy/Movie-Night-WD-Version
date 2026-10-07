<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$loginAttempts = 0;

// Check login attempts from this IP
$pdo = getDBConnection();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Clean old login attempts (older than 1 hour)
$cleanStmt = $pdo->prepare("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$cleanStmt->execute();

// Check recent failed attempts
$attemptStmt = $pdo->prepare("
    SELECT COUNT(*) as attempts
    FROM login_attempts
    WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$attemptStmt->execute([$ip]);
$loginAttempts = $attemptStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check with delay for failed attempts
    if ($loginAttempts >= MAX_LOGIN_ATTEMPTS) {
        sleep(3); // Add delay for repeated failed attempts
        $error = 'Too many failed login attempts. Please try again in 15 minutes.';
    } else {
        // Add small delay for any login attempt to prevent timing attacks
        usleep(500000); // 0.5 second delay

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid security token. Please refresh the page.';
        } else {
            // Check credentials using the adminLogin function
            if (adminLogin($username, $password)) {
                // Log successful login
                $logStmt = $pdo->prepare("
                    INSERT INTO login_attempts (ip_address, username, success, message, created_at)
                    VALUES (?, ?, 1, 'Successful login', NOW())
                ");
                $logStmt->execute([$ip, $username]);

                header('Location: admin-dashboard.php');
                exit;
            } else {
                // Failed login with rate limiting
                if (!checkRateLimit($ip . '_login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
                    $error = 'Too many failed attempts. Please try again later.';
                } else {
                    $error = 'Invalid username or password.';
                }

                // Log failed attempt
                $logStmt = $pdo->prepare("
                    INSERT INTO login_attempts (ip_address, username, success, message, created_at)
                    VALUES (?, ?, 0, 'Invalid credentials', NOW())
                ");
                $logStmt->execute([$ip, $username]);

                $loginAttempts++;
            }
        }
    }
}

$csrfToken = generateCSRFToken();

require dirname(__DIR__) . '/views/admin-login.php';
