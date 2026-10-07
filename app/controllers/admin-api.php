<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/services/EmployeeService.php';

$requestedAction = $_POST['action'] ?? $_GET['action'] ?? '';
$readActions = ['get_registrations', 'get_seat_layout', 'get_event_settings', 'get_employees', 'get_statistics'];
requireAdminApi(!in_array($requestedAction, $readActions, true) || $_SERVER['REQUEST_METHOD'] === 'POST');

try {
    $pdo = getDBConnection();
    
    $action = $requestedAction;
    
    switch ($action) {
        case 'get_registrations':
            $search = $_GET['search'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
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
            
            foreach ($params as $index => $value) $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
            $stmt->execute();
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
            
            // Get seats for specific hall and shift
            $stmt = $pdo->prepare("
                SELECT id, seat_number, row_letter, seat_position, status 
                FROM seats 
                WHERE hall_id = ? AND shift_id = ? 
                ORDER BY row_letter, seat_position
            ");
            $stmt->execute([$hallId, $shiftId]);
            $seats = $stmt->fetchAll();

            // Get all seat numbers and their hall assignments
            $allStmt = $pdo->query("SELECT DISTINCT seat_number, hall_id FROM seats");
            $allSeatHalls = $allStmt->fetchAll();

            echo json_encode(['success' => true, 'seats' => $seats, 'all_seat_halls' => $allSeatHalls]);
            break;
            
        case 'get_event_settings':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'get_employees':
            // Get employees with registration status
            $stmt = $pdo->query("
                SELECT 
                    e.id, 
                    e.emp_number, 
                    e.full_name, 
                    e.shift_id,
                    s.shift_name, 
                    e.is_active,
                    EXISTS(SELECT 1 FROM registrations r WHERE r.emp_number = e.emp_number AND r.status = 'active') AS has_active_registration
                FROM employees e 
                LEFT JOIN shifts s ON e.shift_id = s.id 
                ORDER BY e.full_name
            ");
            $employees = $stmt->fetchAll();
            echo json_encode(['success' => true, 'employees' => $employees]);
            break;
            
        case 'add_employee':
            $employeeId = (new EmployeeService($pdo))->add(
                (string)($_POST['emp_number'] ?? ''), (string)($_POST['full_name'] ?? ''), (int)($_POST['shift_id'] ?? 0)
            );
            logAdminActivity('add_employee', 'employees', $employeeId);
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
            exit;

        case 'get_statistics':
            $stats = $pdo->query("SELECT COUNT(*) AS total_registrations,
                COALESCE(SUM(registration_date >= CURDATE()), 0) AS today_registrations
                FROM registrations WHERE status = 'active'")->fetch();
            $hallCounts = $pdo->query("SELECT hall_id, COUNT(*) AS registrations
                FROM registrations WHERE status = 'active' GROUP BY hall_id")->fetchAll(PDO::FETCH_KEY_PAIR);
            $stats['hall1_count'] = (int)($hallCounts[1] ?? 0);
            $stats['hall2_count'] = (int)($hallCounts[2] ?? 0);
            $stats['hall_counts'] = $hallCounts;

            echo json_encode(['success' => true, 'statistics' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Admin API: ' . $e->getMessage());
    http_response_code($e instanceof DomainException ? 400 : 500);
    echo json_encode(['success' => false, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Unable to complete the request. Please try again.']);
}
?>
