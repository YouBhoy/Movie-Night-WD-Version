<?php

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
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

/** Fetch setting pairs once when rendering a page. */
function getEventSettings(bool $publicOnly = false): array {
    $sql = 'SELECT setting_key, setting_value FROM event_settings';
    if ($publicOnly) $sql .= ' WHERE is_public = 1';
    return getDBConnection()->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
}
