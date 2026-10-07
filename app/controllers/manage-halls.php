<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Check if user is logged in as admin
requireAdminLogin();

$pdo = getDBConnection();
$adminCsrfToken = generateAdminCSRFToken();

// Get event settings
$settings = getEventSettings(true);

// Fetch all halls (active and inactive), but exclude the Unassigned hall (id=0) from the editable list
$halls = $pdo->query("SELECT * FROM cinema_halls WHERE id != 0 ORDER BY is_active DESC, id")->fetchAll();
// Fetch all shifts (active and inactive)
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY is_active DESC, hall_id, id")->fetchAll();
// Fetch the Unassigned hall for display
$unassignedHall = [ 'id' => 0, 'hall_name' => 'Unassigned' ];

// Separate halls and shifts into active and deactivated
$activeHalls = array_filter($halls, function($h) { return $h['is_active']; });
$deactivatedHalls = array_filter($halls, function($h) { return !$h['is_active']; });
// Separate shifts into active and deactivated, excluding the Unassigned shift (id=0) from editable lists
$activeShifts = array_filter($shifts, function($s) { return $s['is_active'] && $s['id'] != 0; });
$deactivatedShifts = array_filter($shifts, function($s) { return !$s['is_active'] && $s['id'] != 0; });

require dirname(__DIR__) . '/views/manage-halls.php';
