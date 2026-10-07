<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Log admin activity if logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        logAdminActivity('logout', null, null, ['logout_time' => date('Y-m-d H:i:s')]);
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear both session data and the browser cookie.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'],
        'domain' => $params['domain'], 'secure' => $params['secure'],
        'httponly' => $params['httponly'], 'samesite' => $params['samesite']]);
}
session_destroy();

// Redirect to login page
header('Location: admin-login.php?logged_out=1');
exit;
?>
