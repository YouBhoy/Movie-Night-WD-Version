<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Check if user is logged in as admin
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminApi(true);
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Use the admin actions endpoint to make changes.']);
    exit;
}
header('Content-Type: text/html; charset=UTF-8');

$pdo = getDBConnection();

// Get event settings
$settings = getEventSettings(false);

// Get registration statistics
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_registrations,
        SUM(attendee_count) as total_attendees,
        COUNT(DISTINCT hall_id) as halls_used
    FROM registrations
    WHERE status = 'active'
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Get registrations by hall
$hallStatsStmt = $pdo->prepare("
    SELECT
        h.hall_name,
        COUNT(r.id) as registration_count,
        SUM(r.attendee_count) as attendee_count
    FROM cinema_halls h
    LEFT JOIN registrations r ON h.id = r.hall_id AND r.status = 'active'
    WHERE h.is_active = 1
    GROUP BY h.id, h.hall_name
    ORDER BY h.id
");
$hallStatsStmt->execute();
$hallStats = $hallStatsStmt->fetchAll();

// Get recent registrations
$recentStmt = $pdo->prepare("
    SELECT
        r.id,
        r.emp_number,
        r.staff_name,
        r.attendee_count,
        r.selected_seats,
        r.registration_date,
        h.hall_name,
        s.shift_name
    FROM registrations r
    JOIN cinema_halls h ON r.hall_id = h.id
    JOIN shifts s ON r.shift_id = s.id
    WHERE r.status = 'active'
    ORDER BY r.registration_date DESC
    LIMIT 50
");
$recentStmt->execute();
$recentRegistrations = $recentStmt->fetchAll();

$movieName = $settings['movie_name'] ?? 'WD Movie Night';
$registrationEnabled = ($settings['registration_enabled'] ?? '1') === '1';


require dirname(__DIR__) . '/views/admin-dashboard.php';
