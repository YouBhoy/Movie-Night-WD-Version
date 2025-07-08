<?php
require_once 'config.php';

// Log admin activity if logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        $pdo = getDBConnection();
        $adminUser = $_SESSION['admin_username'] ?? 'admin';
        
        $logStmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_user, action, details, ip_address, user_agent, created_at) 
            VALUES (?, 'logout', 'Admin logged out', ?, ?, NOW())
        ");
        $logStmt->execute([
            $adminUser,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
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
