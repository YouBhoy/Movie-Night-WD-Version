<?php
require_once 'config.php';
require_once __DIR__ . '/services/MysqlBookingRepository.php';

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIP, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if (in_array($action, ['get_registrations', 'search_registrations'], true)) requireAdminApi();
    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['success' => false, 'message' => 'POST required']);
        exit;
    }
    $pdo = getDBConnection();
    
    // CSRF token validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            exit;
        }
    }

    switch ($action) {
        case 'get_seats':
            handleGetSeats($pdo);
            break;
        case 'register':
            handleRegistration($pdo);
            break;
        case 'check_employee':
            handleCheckEmployee($pdo);
            break;
        case 'get_registrations':
            handleGetRegistrations($pdo);
            break;
        case 'search_registrations':
            handleSearchRegistrations($pdo);
            break;
        case 'get_smart_suggestions':
            handleSmartSeatSuggestions($pdo);
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e instanceof BookingValidationException ? $e->getMessage() : 'An error occurred. Please try again later.'
    ]);
}

function handleGetSeats($pdo) {
    try {
        $hallId = filter_var($_POST['hall_id'] ?? $_GET['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? $_GET['shift_id'] ?? '', FILTER_VALIDATE_INT);
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Invalid hall or shift ID provided');
        }
        
        // Get hall and shift information
        $hallStmt = $pdo->prepare("SELECT hall_name FROM cinema_halls WHERE id = ? AND is_active = 1");
        $hallStmt->execute([$hallId]);
        $hall = $hallStmt->fetch();
        
        $shiftStmt = $pdo->prepare("SELECT shift_name FROM shifts WHERE id = ? AND hall_id = ? AND is_active = 1");
        $shiftStmt->execute([$shiftId, $hallId]);
        $shift = $shiftStmt->fetch();
        
        if (!$hall || !$shift) {
            throw new Exception('Invalid hall or shift combination');
        }
        
        // Check if seats exist for this hall and shift combination
        $seatCountStmt = $pdo->prepare("
            SELECT COUNT(*) as seat_count 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ?
        ");
        $seatCountStmt->execute([$hallId, $shiftId]);
        $seatCount = $seatCountStmt->fetchColumn();
        
        // Layouts are configured by an administrator, never destructively initialized by public reads.
        if ($seatCount == 0) {
            throw new BookingValidationException('Seats have not been configured. Please contact the event organizer.');
        }

        // Get all seats for this hall and shift combination
        $stmt = $pdo->prepare("
            SELECT id, seat_number, row_letter, seat_position, status 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? 
            ORDER BY row_letter, seat_position
        ");
        $stmt->execute([$hallId, $shiftId]);
        $seats = $stmt->fetchAll();
        
        if (empty($seats)) {
            throw new Exception('No seats available for this hall and shift combination');
        }
        
        echo json_encode([
            'success' => true, 
            'seats' => $seats,
            'hall_name' => $hall['hall_name'],
            'shift_name' => $shift['shift_name']
        ]);
        
    } catch (Exception $e) {
        error_log("Seat loading error: " . $e->getMessage());
        throw $e;
    }
}

function handleRegistration($pdo) {
    $input = $_POST;
    $input['selected_seats'] = json_decode($_POST['selected_seats'] ?? '', true);
    $input['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $input['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $booking = bookingService($pdo)->register($input);
    $_SESSION['registration_success'] = true;
    $_SESSION['registration_data'] = $booking;
    echo json_encode(['success' => true, 'message' => 'Registration completed successfully!', 'redirect' => 'confirmation.php']);
}

function handleCheckEmployee($pdo) {
    try {
        $empNumber = strtoupper(trim($_POST['emp_number'] ?? ''));
        
        if (empty($empNumber)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter an employee number'
            ]);
            return;
        }
        // Check if employee exists in employees table, fetch department
        $stmt = $pdo->prepare("SELECT full_name, shift_id FROM employees WHERE emp_number = ? AND is_active = 1");
        $stmt->execute([$empNumber]);
        $employee = $stmt->fetch();
        
        if (!$employee) {
            echo json_encode([
                'success' => false,
                'message' => 'Employee not found'
            ]);
            return;
        }
        // Get shift name
        $shiftStmt = $pdo->prepare("SELECT shift_name FROM shifts WHERE id = ? AND is_active = 1");
        $shiftStmt->execute([$employee['shift_id']]);
        $shift = $shiftStmt->fetch();
        $shiftName = $shift ? $shift['shift_name'] : '';
        // Return employee data for auto-fill
        echo json_encode([
            'success' => true,
            'employee' => [
                'name' => $employee['full_name'],
                'shift' => $shiftName
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error checking employee: ' . $e->getMessage()
        ]);
    }
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
                r.registration_date,
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
        
        echo json_encode([
            'success' => true,
            'registrations' => $registrations
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to get registrations: " . $e->getMessage());
    }
}

function handleSearchRegistrations($pdo) {
    try {
        $search = trim($_GET['search'] ?? '');
        
        if (empty($search)) {
            handleGetRegistrations($pdo);
            return;
        }
        
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
            WHERE r.status = 'active' 
            AND (r.emp_number LIKE ? OR r.staff_name LIKE ?)
            ORDER BY r.registration_date DESC
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $registrations = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'registrations' => $registrations
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to search registrations: " . $e->getMessage());
    }
}

function handleSmartSeatSuggestions($pdo) {
    try {
        $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        $preferredRow = trim($_POST['preferred_row'] ?? '');
        $attendeeCount = filter_var($_POST['attendee_count'] ?? '', FILTER_VALIDATE_INT);
        
        if (!$hallId || !$shiftId || !$preferredRow || !$attendeeCount) {
            throw new Exception('Missing required parameters');
        }
        
        // Simple seat suggestion logic
        $stmt = $pdo->prepare("
            SELECT seat_number, row_letter, seat_position 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? AND row_letter = ? AND status = 'available'
            ORDER BY seat_position
            LIMIT ?
        ");
        $stmt->execute([$hallId, $shiftId, $preferredRow, $attendeeCount]);
        $suggestions = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'suggestions' => $suggestions
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to get seat suggestions: " . $e->getMessage());
    }
}
?>
