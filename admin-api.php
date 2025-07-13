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
                $whereClause
                ORDER BY r.registration_date DESC 
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
            $hallId = filter_var($_GET['hall_id'] ?? $_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
            $shiftId = filter_var($_GET['shift_id'] ?? $_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
            
            if (!$hallId || !$shiftId) {
                echo json_encode(['success' => false, 'message' => 'Invalid hall or shift ID']);
                exit;
            }
            
            // Check if seats table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'seats'");
            if ($checkStmt->rowCount() === 0) {
                echo json_encode(['success' => true, 'seats' => []]);
                exit;
            }
            
            // Get seats for specific hall and shift
            $stmt = $pdo->prepare("
                SELECT id, seat_number, row_letter, seat_position, status 
                FROM seats 
                WHERE hall_id = ? AND shift_id = ? 
                ORDER BY row_letter, seat_position
            ");
            $stmt->execute([$hallId, $shiftId]);
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
            
        case 'add_employee':
            $emp_number = trim($_POST['emp_number'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            if (empty($emp_number) || empty($full_name) || !$shift_id) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            // Check if employee already exists
            $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE emp_number = ?");
            $checkStmt->execute([$emp_number]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Employee number already exists']);
                exit;
            }
            // Insert new employee (department left blank for legacy)
            $stmt = $pdo->prepare("INSERT INTO employees (emp_number, full_name, department, shift_id, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$emp_number, $full_name, '', $shift_id]);
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
            exit;
            
        case 'get_statistics':
            $stats = [];
            
            // Check if registrations table exists
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
            if ($checkStmt->rowCount() > 0) {
                // Total registrations
                $stmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'active'");
                $stats['total_registrations'] = $stmt->fetchColumn();
                
                // Today's registrations
                $stmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(registration_date) = CURDATE() AND status = 'active'");
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
