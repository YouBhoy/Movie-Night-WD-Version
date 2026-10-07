<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Check if registration data exists in session
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header('Location: index.php');
    exit;
}

// Get registration data from session
$registrationData = $_SESSION['registration_data'] ?? null;
if (!$registrationData) {
    header('Location: index.php');
    exit;
}

// Get event settings for display
$pdo = getDBConnection();
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Clear the session data to prevent refresh issues
unset($_SESSION['registration_success']);
unset($_SESSION['registration_data']);

require dirname(__DIR__) . '/views/confirmation.php';
