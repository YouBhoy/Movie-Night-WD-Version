<?php
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Rate limiting
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIP, 50, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests']);
    exit;
}

try {
    $pdo = getDBConnection();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // CSRF token validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
    }

    switch ($action) {
        case 'update_setting':
            handleUpdateSetting($pdo);
            break;
        case 'get_statistics':
            handleGetStatistics($pdo);
            break;
        case 'delete_registration':
            handleDeleteRegistration($pdo);
            break;
        case 'toggle_registration':
            handleToggleRegistration($pdo);
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

function handleUpdateSetting($pdo) {
    try {
        $settingKey = trim($_POST['setting_key'] ?? '');
        $settingValue = $_POST['setting_value'] ?? '';
        
        if (empty($settingKey)) {
            throw new Exception('Setting key is required');
        }
        
        // Validate setting key exists
        $checkStmt = $pdo->prepare("SELECT id FROM event_settings WHERE setting_key = ?");
        $checkStmt->execute([$settingKey]);
        if (!$checkStmt->fetch()) {
            throw new Exception('Invalid setting key');
        }
        
        // Update setting
        $updateStmt = $pdo->prepare("
            UPDATE event_settings 
            SET setting_value = ?, updated_at = NOW() 
            WHERE setting_key = ?
        ");
        $updateStmt->execute([$settingValue, $settingKey]);
        
        // Log admin activity
        logAdminActivity('update_setting', 'event_settings', null, [
            'setting_key' => $settingKey, 
            'new_value' => $settingValue
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to update setting: " . $e->getMessage());
    }
}

function handleGetStatistics($pdo) {
    try {
        $stats = [];
        
        // Total registrations
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM registrations WHERE status = 'active'");
        $stmt->execute();
        $stats['total_registrations'] = $stmt->fetchColumn();
        
        // Total attendees
        $stmt = $pdo->prepare("SELECT SUM(attendee_count) as total FROM registrations WHERE status = 'active'");
        $stmt->execute();
        $stats['total_attendees'] = $stmt->fetchColumn() ?: 0;
        
        // Registrations by hall
        $stmt = $pdo->prepare("
            SELECT h.hall_name, COUNT(r.id) as registrations, SUM(r.attendee_count) as attendees
            FROM cinema_halls h
            LEFT JOIN registrations r ON h.id = r.hall_id AND r.status = 'active'
            WHERE h.is_active = 1
            GROUP BY h.id, h.hall_name
            ORDER BY h.id
        ");
        $stmt->execute();
        $stats['by_hall'] = $stmt->fetchAll();
        
        // Registrations by shift
        $stmt = $pdo->prepare("
            SELECT s.shift_name, COUNT(r.id) as registrations, SUM(r.attendee_count) as attendees
            FROM shifts s
            LEFT JOIN registrations r ON s.id = r.shift_id AND r.status = 'active'
            WHERE s.is_active = 1
            GROUP BY s.id, s.shift_name
            ORDER BY s.id
        ");
        $stmt->execute();
        $stats['by_shift'] = $stmt->fetchAll();
        
        // Recent activity
        $stmt = $pdo->prepare("
            SELECT registration_date, COUNT(*) as count
            FROM registrations 
            WHERE status = 'active' AND registration_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(registration_date)
            ORDER BY registration_date DESC
        ");
        $stmt->execute();
        $stats['recent_activity'] = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'statistics' => $stats
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to get statistics: " . $e->getMessage());
    }
}

function handleDeleteRegistration($pdo) {
    try {
        $registrationId = filter_var($_POST['registration_id'] ?? '', FILTER_VALIDATE_INT);
        
        if (!$registrationId) {
            throw new Exception('Invalid registration ID');
        }
        
        // Get registration details before deletion
        $stmt = $pdo->prepare("
            SELECT r.*, h.hall_name, s.shift_name 
            FROM registrations r
            JOIN cinema_halls h ON r.hall_id = h.id
            JOIN shifts s ON r.shift_id = s.id
            WHERE r.id = ? AND r.status = 'active'
        ");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            throw new Exception('Registration not found or already cancelled');
        }
        
        // Use stored procedure to free seats
        $result = releaseSeats($registrationId);
        
        if ($result === false) {
            throw new Exception('Failed to release seats');
        }
        
        // Log admin activity
        logAdminActivity('delete_registration', 'registrations', $registrationId, [
            'emp_number' => $result['emp_number'],
            'staff_name' => $registration['staff_name'],
            'hall_name' => $registration['hall_name'],
            'shift_name' => $registration['shift_name'],
            'seats_released' => $result['released_seats']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration cancelled successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to delete registration: " . $e->getMessage());
    }
}

function handleToggleRegistration($pdo) {
    try {
        // Get current registration status
        $stmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'registration_enabled'");
        $stmt->execute();
        $currentStatus = $stmt->fetchColumn();
        
        // Toggle status
        $newStatus = ($currentStatus === 'true') ? 'false' : 'true';
        
        // Update setting
        $updateStmt = $pdo->prepare("
            UPDATE event_settings 
            SET setting_value = ?, updated_at = NOW() 
            WHERE setting_key = 'registration_enabled'
        ");
        $updateStmt->execute([$newStatus]);
        
        // Log admin activity
        logAdminActivity('toggle_registration', 'event_settings', null, [
            'registration_enabled' => $newStatus
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration status updated successfully',
            'new_status' => $newStatus
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to toggle registration: " . $e->getMessage());
    }
}
?>
