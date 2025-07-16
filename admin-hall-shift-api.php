<?php
require_once 'config.php';

// Simple admin authentication check
session_start();
// Temporarily comment out authentication for testing
// if (!isAdminLoggedIn()) {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

// Set JSON header
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get event settings for default values
    $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
    $settingsStmt->execute();
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $defaultSeatCount = $settings['default_seat_count'] ?? 72;
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Debug logging
    error_log("API Action: " . $action);
    error_log("GET params: " . print_r($_GET, true));
    error_log("POST params: " . print_r($_POST, true));
    
    switch ($action) {
        // ===== CINEMA HALL FUNCTIONS =====
        
        case 'get_active_halls':
            $stmt = $pdo->prepare("
                SELECT id, hall_name, max_attendees_per_booking, total_seats, is_active, created_at, updated_at 
                FROM cinema_halls 
                WHERE is_active = 1 
                ORDER BY id
            ");
            $stmt->execute();
            $halls = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'halls' => $halls]);
            break;
            
        case 'add_hall':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            
            $hallName = sanitizeInput($_POST['hall_name'] ?? '');
            $maxAttendees = filter_var($_POST['max_attendees_per_booking'] ?? 3, FILTER_VALIDATE_INT);
            $totalSeats = filter_var($_POST['total_seats'] ?? $defaultSeatCount, FILTER_VALIDATE_INT);
            
            if (empty($hallName)) {
                echo json_encode(['success' => false, 'message' => 'Hall name is required']);
                exit;
            }
            
            if ($maxAttendees < 1 || $totalSeats < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid attendee or seat count']);
                exit;
            }
            
            // Check if hall name already exists
            $checkStmt = $pdo->prepare("SELECT id FROM cinema_halls WHERE hall_name = ? AND is_active = 1");
            $checkStmt->execute([$hallName]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Hall name already exists']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO cinema_halls (hall_name, max_attendees_per_booking, total_seats, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$hallName, $maxAttendees, $totalSeats]);
            $hallId = $pdo->lastInsertId();
            
            logAdminActivity('add_hall', 'cinema_halls', $hallId, [
                'hall_name' => $hallName,
                'max_attendees' => $maxAttendees,
                'total_seats' => $totalSeats
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Hall added successfully', 'hall_id' => $hallId]);
            break;
            
        case 'update_hall':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            
            $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
            $hallName = sanitizeInput($_POST['hall_name'] ?? '');
            $maxAttendees = filter_var($_POST['max_attendees_per_booking'] ?? 3, FILTER_VALIDATE_INT);
            $totalSeats = filter_var($_POST['total_seats'] ?? $defaultSeatCount, FILTER_VALIDATE_INT);
            
            if (!$hallId || empty($hallName)) {
                echo json_encode(['success' => false, 'message' => 'Invalid hall ID or name']);
                exit;
            }
            
            if ($maxAttendees < 1 || $totalSeats < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid attendee or seat count']);
                exit;
            }
            
            // Check if hall name already exists for different hall
            $checkStmt = $pdo->prepare("SELECT id FROM cinema_halls WHERE hall_name = ? AND id != ? AND is_active = 1");
            $checkStmt->execute([$hallName, $hallId]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Hall name already exists']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE cinema_halls 
                SET hall_name = ?, max_attendees_per_booking = ?, total_seats = ?, updated_at = NOW() 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$hallName, $maxAttendees, $totalSeats, $hallId]);
            
            if ($stmt->rowCount() > 0) {
                logAdminActivity('update_hall', 'cinema_halls', $hallId, [
                    'hall_name' => $hallName,
                    'max_attendees' => $maxAttendees,
                    'total_seats' => $totalSeats
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Hall updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Hall not found or no changes made']);
            }
            break;
            
        case 'deactivate_hall':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$hallId) {
                echo json_encode(['success' => false, 'message' => 'Invalid hall ID']);
                exit;
            }
            // Reassign all shifts to the 'Unassigned' hall (id=0)
            $pdo->prepare("UPDATE shifts SET hall_id = 0 WHERE hall_id = ?")->execute([$hallId]);
            // Now allow deactivation regardless of registrations
            $stmt = $pdo->prepare("UPDATE cinema_halls SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hallId]);
            if ($stmt->rowCount() > 0) {
                logAdminActivity('deactivate_hall', 'cinema_halls', $hallId);
                echo json_encode(['success' => true, 'message' => 'Hall deactivated successfully (shifts reassigned to Unassigned)']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Hall not found']);
            }
            break;
            
        case 'restore_hall':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$hallId) {
                echo json_encode(['success' => false, 'message' => 'Invalid hall ID']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE cinema_halls SET is_active = 1, updated_at = NOW() WHERE id = ? AND is_active = 0");
            $stmt->execute([$hallId]);
            if ($stmt->rowCount() > 0) {
                logAdminActivity('restore_hall', 'cinema_halls', $hallId);
                echo json_encode(['success' => true, 'message' => 'Hall restored successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Hall not found or already active']);
            }
            break;
            
        // ===== SHIFT FUNCTIONS =====
        
        case 'get_shifts_by_hall':
            $hallId = filter_var($_GET['hall_id'] ?? $_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
            
            if (!$hallId) {
                echo json_encode(['success' => false, 'message' => 'Invalid hall ID']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, hall_id, shift_name, shift_code, seat_prefix, seat_count, start_time, end_time, is_active, created_at 
                FROM shifts 
                WHERE hall_id = ? AND is_active = 1 
                ORDER BY start_time
            ");
            $stmt->execute([$hallId]);
            $shifts = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'shifts' => $shifts]);
            break;
            
        case 'add_shift':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $hallId = isset($_POST['hall_id']) ? $_POST['hall_id'] : '';
            $hallIdInt = filter_var($hallId, FILTER_VALIDATE_INT);
            $shiftName = sanitizeInput($_POST['shift_name'] ?? '');
            $shiftCode = sanitizeInput($_POST['shift_code'] ?? '');
            $seatPrefix = sanitizeInput($_POST['seat_prefix'] ?? '');
            $seatCount = filter_var($_POST['seat_count'] ?? $defaultSeatCount, FILTER_VALIDATE_INT);
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';
            // Check if hall is active
            if ($hallIdInt && $hallIdInt > 0) {
                $hallCheck = $pdo->prepare("SELECT is_active FROM cinema_halls WHERE id = ?");
                $hallCheck->execute([$hallIdInt]);
                $hallRow = $hallCheck->fetch();
                if (!$hallRow || $hallRow['is_active'] == 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot assign shift to an inactive or non-existent hall.']);
                    exit;
                }
            }
            if ($hallId === '0' || $hallIdInt === 0 || !$hallIdInt || empty($shiftName) || empty($shiftCode) || empty($startTime) || empty($endTime)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required and shift must be assigned to a valid hall.']);
                exit;
            }
            
            if ($seatCount < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid seat count']);
                exit;
            }
            
            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
                echo json_encode(['success' => false, 'message' => 'Invalid time format']);
                exit;
            }
            
            // Check if shift name already exists for this hall
            $checkStmt = $pdo->prepare("SELECT id FROM shifts WHERE shift_name = ? AND hall_id = ? AND is_active = 1");
            $checkStmt->execute([$shiftName, $hallIdInt]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Shift name already exists for this hall']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO shifts (hall_id, shift_name, shift_code, seat_prefix, seat_count, start_time, end_time, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$hallIdInt, $shiftName, $shiftCode, $seatPrefix, $seatCount, $startTime, $endTime]);
            $shiftId = $pdo->lastInsertId();
            
            logAdminActivity('add_shift', 'shifts', $shiftId, [
                'hall_id' => $hallIdInt,
                'shift_name' => $shiftName,
                'shift_code' => $shiftCode,
                'seat_count' => $seatCount
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Shift added successfully', 'shift_id' => $shiftId]);
            break;
            
        case 'update_shift':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
            $hallId = isset($_POST['hall_id']) ? $_POST['hall_id'] : '';
            $hallIdInt = filter_var($hallId, FILTER_VALIDATE_INT);
            $shiftName = sanitizeInput($_POST['shift_name'] ?? '');
            $shiftCode = sanitizeInput($_POST['shift_code'] ?? '');
            $seatPrefix = sanitizeInput($_POST['seat_prefix'] ?? '');
            $seatCount = filter_var($_POST['seat_count'] ?? $defaultSeatCount, FILTER_VALIDATE_INT);
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';
            // Check if hall is active
            if ($hallIdInt && $hallIdInt > 0) {
                $hallCheck = $pdo->prepare("SELECT is_active FROM cinema_halls WHERE id = ?");
                $hallCheck->execute([$hallIdInt]);
                $hallRow = $hallCheck->fetch();
                if (!$hallRow || $hallRow['is_active'] == 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot assign shift to an inactive or non-existent hall.']);
                    exit;
                }
            }
            if ($hallId === '0' || $hallIdInt === 0 || !$shiftId || !$hallIdInt || empty($shiftName) || empty($shiftCode) || empty($startTime) || empty($endTime)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required and shift must be assigned to a valid hall.']);
                exit;
            }
            
            if ($seatCount < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid seat count']);
                exit;
            }
            
            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
                echo json_encode(['success' => false, 'message' => 'Invalid time format']);
                exit;
            }
            
            // Get current shift info for validation
            $currentStmt = $pdo->prepare("SELECT hall_id FROM shifts WHERE id = ? AND is_active = 1");
            $currentStmt->execute([$shiftId]);
            $currentShift = $currentStmt->fetch();
            if (!$currentShift) {
                echo json_encode(['success' => false, 'message' => 'Shift not found']);
                exit;
            }
            
            // Check if shift name already exists for this hall (excluding current shift)
            $checkStmt = $pdo->prepare("SELECT id FROM shifts WHERE shift_name = ? AND hall_id = ? AND id != ? AND is_active = 1");
            $checkStmt->execute([$shiftName, $hallIdInt, $shiftId]);
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Shift name already exists for this hall']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE shifts 
                SET shift_name = ?, shift_code = ?, seat_prefix = ?, seat_count = ?, start_time = ?, end_time = ?, hall_id = ?
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$shiftName, $shiftCode, $seatPrefix, $seatCount, $startTime, $endTime, $hallIdInt, $shiftId]);
            
            if ($stmt->rowCount() > 0) {
                logAdminActivity('update_shift', 'shifts', $shiftId, [
                    'shift_name' => $shiftName,
                    'shift_code' => $shiftCode,
                    'seat_count' => $seatCount,
                    'hall_id' => $hallIdInt
                ]);
                echo json_encode(['success' => true, 'message' => 'Shift updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Shift not found or no changes made']);
            }
            break;
            
        case 'deactivate_shift':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$shiftId) {
                echo json_encode(['success' => false, 'message' => 'Invalid shift ID']);
                exit;
            }
            // Reassign all registrations to the 'Unassigned' shift (id=0)
            $pdo->prepare("UPDATE registrations SET shift_id = 0 WHERE shift_id = ? AND status = 'active'")->execute([$shiftId]);
            // Now allow deactivation regardless of registrations
            $stmt = $pdo->prepare("UPDATE shifts SET is_active = 0 WHERE id = ?");
            $stmt->execute([$shiftId]);
            if ($stmt->rowCount() > 0) {
                logAdminActivity('deactivate_shift', 'shifts', $shiftId);
                echo json_encode(['success' => true, 'message' => 'Shift deactivated successfully (registrations reassigned to Unassigned)']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Shift not found']);
            }
            break;
            
        case 'restore_shift':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Security validation failed']);
                exit;
            }
            $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$shiftId) {
                echo json_encode(['success' => false, 'message' => 'Invalid shift ID']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE shifts SET is_active = 1 WHERE id = ? AND is_active = 0");
            $stmt->execute([$shiftId]);
            if ($stmt->rowCount() > 0) {
                logAdminActivity('restore_shift', 'shifts', $shiftId);
                echo json_encode(['success' => true, 'message' => 'Shift restored successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Shift not found or already active']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 