<?php

function generateSessionToken(string $namespace): string {
    $key = $namespace . '_token';
    $timeKey = $key . '_time';
    if (!isset($_SESSION[$key], $_SESSION[$timeKey]) || time() - $_SESSION[$timeKey] > CSRF_TOKEN_EXPIRY) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
        $_SESSION[$timeKey] = time();
    }
    return $_SESSION[$key];
}

function validateSessionToken(string $namespace, $token): bool {
    $key = $namespace . '_token';
    $timeKey = $key . '_time';
    if (!isset($_SESSION[$key], $_SESSION[$timeKey])) return false;
    if (time() - $_SESSION[$timeKey] > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION[$key], $_SESSION[$timeKey]);
        return false;
    }
    return is_string($token) && hash_equals($_SESSION[$key], $token);
}

function generateCSRFToken() {
    return generateSessionToken('csrf');
}

function validateCSRFToken($token) {
    return validateSessionToken('csrf', $token);
}

function generateAdminCSRFToken() {
    return generateSessionToken('admin_csrf');
}

function validateAdminCSRFToken($token) {
    return validateSessionToken('admin_csrf', $token);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

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

function checkAdminRateLimit($action, $maxRequests = 10, $timeWindow = 60) {
    $identifier = 'admin:' . $action . ':' . ($_SESSION['admin_username'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return checkRateLimit($identifier, $maxRequests, $timeWindow);
}

function setSecurityHeaders() {
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'");

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

function requireAdminApi(bool $write = false): void {
    header('Content-Type: application/json');
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    if ($write && !in_array($_SESSION['admin_role'] ?? '', ['admin', 'manager'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to make changes.']);
        exit;
    }
    if ($write && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['success' => false, 'message' => 'POST required']);
        exit;
    }
    if ($write && !validateAdminCSRFToken($_POST['admin_csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh.']);
        exit;
    }
}
