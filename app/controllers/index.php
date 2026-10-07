<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Get event settings and cinema data
$pdo = getDBConnection();

// Get cinema halls and shifts
$hallsStmt = $pdo->prepare("SELECT * FROM cinema_halls WHERE is_active = 1 ORDER BY id");
$hallsStmt->execute();
$halls = $hallsStmt->fetchAll();

$shiftsStmt = $pdo->prepare("SELECT * FROM shifts WHERE is_active = 1 ORDER BY id");
$shiftsStmt->execute();
$shifts = $shiftsStmt->fetchAll();

// Get event settings
$settings = getEventSettings();

// Get max attendees setting from database, fallback to constant if not set
$maxAttendees = max(1, (int)($settings['max_attendees'] ?? MAX_ATTENDEES_PER_BOOKING));

// Check if registration is enabled
$registrationEnabled = in_array($settings['registration_enabled'] ?? '0', ['1', 'true'], true);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Default values
$movieName = $settings['movie_name'] ?? 'Thunderbolts*';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'WD Campus Cinema Complex';
$eventDescription = $settings['event_description'] ?? 'Join us for an exclusive movie screening event!';
$primaryColor = $settings['primary_color'] ?? '#FFD700';
$secondaryColor = $settings['secondary_color'] ?? '#2E8BFF';
$companyName = $settings['company_name'] ?? 'Western Digital';
$footerText = $settings['footer_text'] ?? "© 2025 {$companyName} – Internal Movie Night Event";

require dirname(__DIR__) . '/views/index.php';
