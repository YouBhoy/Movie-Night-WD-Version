<?php

function isAdminLoggedIn(): bool {
    return ($_SESSION['admin_logged_in'] ?? false) === true;
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: admin-login.php');
        exit;
    }
}

function adminLogin($username, $password): bool {
    try {
        $pdo = getDBConnection();
        $statement = $pdo->prepare('SELECT id, username, password_hash, role FROM admin_users WHERE username = ? AND is_active = 1');
        $statement->execute([$username]);
        $admin = $statement->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            logSecurityEvent('admin_login_failed', $username, 'medium');
            return false;
        }
        $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_login_time'] = time();
        logSecurityEvent('admin_login_success', $username, 'low');
        return true;
    } catch (Exception $exception) {
        error_log('Admin login: ' . $exception->getMessage());
        return false;
    }
}
