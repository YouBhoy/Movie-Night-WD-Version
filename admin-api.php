<?php
require_once 'config.php';

// Check admin access
if (!isset($_GET['key']) && !isset($_POST['key'])) {
    $key = $_SERVER['HTTP_REFERER'] ?? '';
    if (!str_contains($key, 'key=' . ADMIN_KEY)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // CSRF validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
    }

    switch ($action) {
        case 'get_registrations':
            handleGetRegistrations($pdo);
            break;
        case 'get_stats':
            handleGetStats($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGetRegistrations($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.emp_number,
                r.staff_name,
                r.attendee_count,
                r.selected_seats,
                r.created_at,
                h.hall_name,
                s.shift_name
            FROM registrations r
            JOIN cinema_halls h ON r.hall_id = h.id
            JOIN shifts s ON r.shift_id = s.id
            WHERE r.status = 'active'
            ORDER BY r.created_at DESC
        ");
        
        $stmt->execute();
        $registrations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'registrations' => $registrations
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to load registrations: " . $e->getMessage());
    }
}

function handleGetStats($pdo) {
    try {
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_registrations,
                SUM(attendee_count) as total_attendees,
                (SELECT COUNT(*) FROM seats WHERE status = 'available') as available_seats,
                (SELECT COUNT(*) FROM seats WHERE status = 'blocked') as blocked_seats,
                (SELECT COUNT(*) FROM seats WHERE status = 'occupied') as occupied_seats
            FROM registrations WHERE status = 'active'
        ");
        $stats = $statsStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to load statistics: " . $e->getMessage());
    }
}
?>
