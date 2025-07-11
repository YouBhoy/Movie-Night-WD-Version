<?php
require_once 'config.php';

// Log admin activity if logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        logAdminActivity('logout', null, null, ['logout_time' => date('Y-m-d H:i:s')]);
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: admin-login.php?logged_out=1');
exit;
?>
