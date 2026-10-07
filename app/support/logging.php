<?php

function logSecurityEvent($eventType, $userId = null, $riskLevel = 'low', $details = []) {
    try {
        $pdo = getDBConnection();

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
    } catch (Exception $e) {
        error_log("Security event $eventType ($riskLevel): " . json_encode($details) . "; logging failure: " . $e->getMessage());
    }
}

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

function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
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

function customExceptionHandler($exception) {
    http_response_code(500);
    $errorMessage = "Uncaught Exception: " . $exception->getMessage() .
                   " in " . $exception->getFile() .
                   " on line " . $exception->getLine();

    error_log($errorMessage);

    // In production, show generic error message
    if (ini_get('display_errors')) {
        echo "<h1>Application Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        echo "<h1>Application Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
    }
}

function shutdownHandler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorMessage = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log($errorMessage);
    }
}
