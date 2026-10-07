<?php
// Shared bootstrap for every HTTP entry point and command-line fixture.
define('PROJECT_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', PROJECT_ROOT . '/public');
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'movie_night_db');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('CSRF_TOKEN_EXPIRY', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('MAX_ATTENDEES_PER_BOOKING', 3);
define('RATE_LIMIT_REQUESTS', 30);
define('RATE_LIMIT_WINDOW', 60);
define('APP_NAME', 'WD Movie Night');
define('APP_VERSION', '2.0.0');

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
$logPath = getenv('APP_LOG_PATH') ?: PROJECT_ROOT . '/storage/logs/php_errors.log';
if (!is_dir(dirname($logPath))) mkdir(dirname($logPath), 0700, true);
ini_set('error_log', $logPath);
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/support/database.php';
require_once __DIR__ . '/support/security.php';
require_once __DIR__ . '/support/auth.php';
require_once __DIR__ . '/support/logging.php';
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('shutdownHandler');

if (session_status() === PHP_SESSION_NONE) {
    // Explicit CLI/test overrides remain supported; normal requests use private writable storage.
    $sessionPath = getenv('APP_SESSION_PATH') ?: PROJECT_ROOT . '/storage/sessions';
    if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
    if (PHP_SAPI !== 'cli' || getenv('APP_SESSION_PATH')) session_save_path($sessionPath);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}
if (PHP_SAPI !== 'cli') setSecurityHeaders();
