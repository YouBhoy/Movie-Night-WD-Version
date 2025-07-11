<?php
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Validate CSRF token
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    die('Invalid security token');
}

$pdo = getDBConnection();
$exportType = $_GET['type'] ?? 'csv';

try {
    // Get all registrations with related data
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.emp_number,
            r.staff_name,
            r.email,
            r.attendee_count,
            r.selected_seats,
            r.registration_date,
            r.ip_address,
            h.hall_name,
            s.shift_name
        FROM registrations r
        JOIN cinema_halls h ON r.hall_id = h.id
        JOIN shifts s ON r.shift_id = s.id
        WHERE r.status = 'active'
        ORDER BY r.registration_date DESC
    ");
    $stmt->execute();
    $registrations = $stmt->fetchAll();
    
    if ($exportType === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="movie_night_registrations_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Registration ID',
            'Employee Number',
            'Staff Name',
            'Email',
            'Number of Attendees',
            'Cinema Hall',
            'Shift',
            'Selected Seats',
            'Registration Date',
            'IP Address'
        ]);
        
        // Add data rows
        foreach ($registrations as $reg) {
            $selectedSeats = json_decode($reg['selected_seats'], true);
            $seatsString = is_array($selectedSeats) ? implode(', ', $selectedSeats) : '';
            
            fputcsv($output, [
                $reg['id'],
                $reg['emp_number'],
                $reg['staff_name'],
                $reg['email'] ?? '',
                $reg['attendee_count'],
                $reg['hall_name'],
                $reg['shift_name'],
                $seatsString,
                $reg['registration_date'],
                $reg['ip_address'] ?? ''
            ]);
        }
        
        fclose($output);
        
        // Log admin activity
        logAdminActivity('export_registrations', 'registrations', null, [
            'export_type' => 'csv', 
            'record_count' => count($registrations)
        ]);
        
    } else {
        throw new Exception('Unsupported export type');
    }
    
} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    die('Export failed: ' . $e->getMessage());
}
?>
