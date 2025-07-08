<?php
require_once 'config.php';

// Check admin access
if (!isset($_GET['key']) || $_GET['key'] !== ADMIN_KEY) {
    http_response_code(403);
    die('Access denied. Invalid admin key.');
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.emp_number,
            r.staff_name,
            r.attendee_count,
            r.selected_seats,
            r.ip_address,
            r.created_at,
            h.hall_name,
            s.shift_name,
            es.setting_value as movie_name
        FROM registrations r
        JOIN cinema_halls h ON r.hall_id = h.id
        JOIN shifts s ON r.shift_id = s.id
        LEFT JOIN event_settings es ON es.setting_key = 'movie_name'
        WHERE r.status = 'active'
        ORDER BY r.created_at DESC
    ");
    
    $stmt->execute();
    $registrations = $stmt->fetchAll();
    
    // Set headers for CSV download
    $filename = 'movie_night_registrations_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, [
        'Registration ID',
        'Employee Number',
        'Staff Name',
        'Attendee Count',
        'Cinema Hall',
        'Shift',
        'Selected Seats',
        'Movie Name',
        'Registration Date',
        'Registration Time',
        'IP Address'
    ]);
    
    // Write data rows
    foreach ($registrations as $reg) {
        $seats = json_decode($reg['selected_seats'], true);
        $seatsList = is_array($seats) ? implode(', ', $seats) : $reg['selected_seats'];
        
        $datetime = new DateTime($reg['created_at']);
        
        fputcsv($output, [
            $reg['id'],
            $reg['emp_number'],
            $reg['staff_name'],
            $reg['attendee_count'],
            $reg['hall_name'],
            $reg['shift_name'],
            $seatsList,
            $reg['movie_name'] ?? 'N/A',
            $datetime->format('Y-m-d'),
            $datetime->format('H:i:s'),
            $reg['ip_address']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
}
?>
