<?php
require_once 'config.php';

// Simple admin authentication check
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_registrations':
            $search = $_GET['search'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            // Check if registrations table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
            if ($checkStmt->rowCount() === 0) {
                echo json_encode(['success' => true, 'registrations' => [], 'total' => 0]);
                exit;
            }
            
            // Build query with search
            $whereClause = "WHERE status = 'active'";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (staff_name LIKE ? OR emp_number LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get registrations
            $stmt = $pdo->prepare("
                SELECT id, emp_number, staff_name, attendee_count, selected_seats, created_at
                FROM registrations 
                $whereClause
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $registrations = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'registrations' => $registrations, 
                'total' => $total,
                'page' => $page,
                'totalPages' => ceil($total / $limit)
            ]);
            break;
            
        case 'get_seat_layout':
            // Check if seats table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'seats'");
            if ($checkStmt->rowCount() === 0) {
                // Return default seat layout
                $seats = [];
                for ($i = 1; $i <= 100; $i++) {
                    $seats[] = [
                        'id' => $i,
                        'status' => 'available',
                        'seat_number' => $i
                    ];
                }
                echo json_encode(['success' => true, 'seats' => $seats]);
                exit;
            }
            
            $stmt = $pdo->query("SELECT id, seat_number, status FROM seats ORDER BY seat_number");
            $seats = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'seats' => $seats]);
            break;
            
        case 'get_event_settings':
            // Check if event_settings table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'event_settings'");
            if ($checkStmt->rowCount() === 0) {
                echo json_encode(['success' => true, 'settings' => []]);
                exit;
            }
            
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'get_employees':
            // Check if employees table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'employees'");
            if ($checkStmt->rowCount() === 0) {
                echo json_encode(['success' => true, 'employees' => []]);
                exit;
            }
            
            $stmt = $pdo->query("SELECT id, emp_number, full_name, department, is_active FROM employees ORDER BY full_name");
            $employees = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'employees' => $employees]);
            break;
            
        case 'get_statistics':
            $stats = [];
            
            // Check if registrations table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
            if ($checkStmt->rowCount() > 0) {
                // Total registrations
                $stmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'active'");
                $stats['total_registrations'] = $stmt->fetchColumn();
                
                // Today's registrations
                $stmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE() AND status = 'active'");
                $stats['today_registrations'] = $stmt->fetchColumn();
                
                // Hall counts (using default values since hall_assigned doesn't exist)
                $stats['hall1_count'] = 0;
                $stats['hall2_count'] = 0;
            } else {
                $stats = [
                    'total_registrations' => 0,
                    'today_registrations' => 0,
                    'hall1_count' => 0,
                    'hall2_count' => 0
                ];
            }
            
            echo json_encode(['success' => true, 'statistics' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
