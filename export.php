<?php
require_once 'config.php';

// Simple admin authentication check
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// Get export type
$type = $_GET['type'] ?? 'registrations';

try {
    $pdo = getDBConnection();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($type) {
        case 'registrations':
            exportRegistrations($pdo, $output);
            break;
            
        case 'attendees':
            exportAttendees($pdo, $output);
            break;
            
        case 'employees':
            exportEmployees($pdo, $output);
            break;
            
        case 'seats':
            exportSeats($pdo, $output);
            break;
            
        default:
            fputcsv($output, ['Error', 'Invalid export type']);
            break;
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // If there's an error, redirect back to admin with error message
    header('Location: admin.php?tab=export&error=' . urlencode('Export failed: ' . $e->getMessage()));
    exit;
}

function exportRegistrations($pdo, $output) {
    // Check if registrations table exists
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
    if ($checkStmt->rowCount() === 0) {
        fputcsv($output, ['No registrations table found']);
        return;
    }
    
    // Headers
    fputcsv($output, [
        'Employee Number',
        'Staff Name',
        'Number of Attendees',
        'Selected Seats',
        'Registration Date & Time',
        'Status'
    ]);
    
    // Get all registrations
    $stmt = $pdo->query("
        SELECT emp_number, staff_name, attendee_count, selected_seats, created_at, status
        FROM registrations 
        ORDER BY created_at DESC
    ");
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['emp_number'],
            $row['staff_name'],
            $row['attendee_count'] ?? 1,
            $row['selected_seats'] ?? 'N/A',
            $row['created_at'],
            $row['status']
        ]);
    }
}

function exportAttendees($pdo, $output) {
    // Check if registrations table exists
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
    if ($checkStmt->rowCount() === 0) {
        fputcsv($output, ['No registrations table found']);
        return;
    }
    
    // Headers
    fputcsv($output, [
        'Employee Number',
        'Staff Name',
        'Registration Date'
    ]);
    
    // Get all active registrations
    $stmt = $pdo->query("
        SELECT emp_number, staff_name, created_at
        FROM registrations 
        WHERE status = 'active'
        ORDER BY created_at DESC
    ");
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['emp_number'],
            $row['staff_name'],
            date('Y-m-d', strtotime($row['created_at']))
        ]);
    }
}

function exportEmployees($pdo, $output) {
    // Check if employees table exists
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'employees'");
    if ($checkStmt->rowCount() === 0) {
        fputcsv($output, ['No employees table found']);
        return;
    }
    
    // Headers
    fputcsv($output, [
        'Employee Number',
        'Full Name',
        'Shift Name',
        'Status',
        'Hall'
    ]);
    
    // Get all employees with shift and hall information
    $stmt = $pdo->query("
        SELECT 
            e.emp_number,
            e.full_name,
            e.is_active,
            COALESCE(s.shift_name, 'Unassigned') as shift_name,
            COALESCE(h.hall_name, 'No Hall') as hall_name
        FROM employees e
        LEFT JOIN shifts s ON e.shift_id = s.id
        LEFT JOIN cinema_halls h ON s.hall_id = h.id
        ORDER BY e.full_name
    ");
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['emp_number'],
            $row['full_name'],
            $row['shift_name'],
            $row['is_active'] ? 'Active' : 'Inactive',
            $row['hall_name']
        ]);
    }
}

function exportSeats($pdo, $output) {
    // Check if seats table exists
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'seats'");
    if ($checkStmt->rowCount() === 0) {
        fputcsv($output, ['No seats table found']);
        return;
    }
    
    // Headers
    fputcsv($output, [
        'Seat Number',
        'Status',
        'Hall',
        'Last Updated'
    ]);
    
    // Get all seats
    $stmt = $pdo->query("
        SELECT seat_number, status, hall_id, updated_at
        FROM seats 
        ORDER BY seat_number
    ");
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['seat_number'],
            $row['status'],
            $row['hall_id'] ?? 'N/A',
            $row['updated_at'] ?? 'N/A'
        ]);
    }
}
?>
