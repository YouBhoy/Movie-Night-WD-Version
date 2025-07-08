<?php
require_once 'config.php';

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDBConnection();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
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
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

function handleGetSeats($pdo) {
    try {
        $hallId = filter_var($_POST['hall_id'] ?? $_GET['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? $_GET['shift_id'] ?? '', FILTER_VALIDATE_INT);
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Invalid hall or shift ID provided');
        }
        
        // Verify hall and shift exist and are active
        $verifyStmt = $pdo->prepare("
            SELECT h.hall_name, s.shift_name 
            FROM cinema_halls h 
            JOIN shifts s ON h.id = s.hall_id 
            WHERE h.id = ? AND s.id = ? AND h.is_active = 1 AND s.is_active = 1
        ");
        $verifyStmt->execute([$hallId, $shiftId]);
        $hallShift = $verifyStmt->fetch();
        
        if (!$hallShift) {
            throw new Exception('Invalid hall and shift combination');
        }
        
        $stmt = $pdo->prepare("
            SELECT id, seat_number, row_letter, seat_position, status 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? 
            ORDER BY row_letter, seat_position
        ");
        $stmt->execute([$hallId, $shiftId]);
        $seats = $stmt->fetchAll();
        
        if (empty($seats)) {
            throw new Exception('No seats found for this hall and shift combination');
        }
        
        echo json_encode([
            'success' => true, 
            'seats' => $seats,
            'hall_name' => $hallShift['hall_name'],
            'shift_name' => $hallShift['shift_name']
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to load seats: " . $e->getMessage());
    }
}

function handleRegistration($pdo) {
    try {
        // Validate input data
        $empNumber = strtoupper(trim($_POST['emp_number'] ?? ''));
        $staffName = trim($_POST['staff_name'] ?? '');
        $attendeeCount = filter_var($_POST['attendee_count'] ?? '', FILTER_VALIDATE_INT);
        $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        $selectedSeatsJson = $_POST['selected_seats'] ?? '';
        
        // Validation
        if (!validateEmployeeNumber($empNumber)) {
            throw new Exception('Invalid employee number format');
        }
        
        if (!validateName($staffName)) {
            throw new Exception('Invalid name format');
        }
        
        if (!$attendeeCount || $attendeeCount < 1 || $attendeeCount > MAX_ATTENDEES_PER_BOOKING) {
            throw new Exception('Invalid attendee count. Maximum ' . MAX_ATTENDEES_PER_BOOKING . ' attendees allowed.');
        }
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Missing required fields');
        }
        
        $selectedSeats = json_decode($selectedSeatsJson, true);
        if (!is_array($selectedSeats) || count($selectedSeats) !== $attendeeCount) {
            throw new Exception('Invalid seat selection');
        }
        
        // Check if employee exists and is active
        $empStmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE emp_number = ? AND is_active = 1");
        $empStmt->execute([$empNumber]);
        $employee = $empStmt->fetch();
        
        if (!$employee) {
            throw new Exception('Employee number not found or inactive');
        }
        
        // Check if employee already registered
        $checkStmt = $pdo->prepare("SELECT id FROM registrations WHERE emp_number = ? AND status = 'active'");
        $checkStmt->execute([$empNumber]);
        if ($checkStmt->fetch()) {
            throw new Exception('Employee already registered for this event');
        }
        
        // Verify hall capacity limits
        $hallStmt = $pdo->prepare("SELECT hall_name, max_attendees_per_booking FROM cinema_halls WHERE id = ? AND is_active = 1");
        $hallStmt->execute([$hallId]);
        $hall = $hallStmt->fetch();

        if (!$hall) {
            throw new Exception('Invalid cinema hall');
        }

        if ($attendeeCount > $hall['max_attendees_per_booking']) {
            throw new Exception("Maximum {$hall['max_attendees_per_booking']} attendees allowed for this cinema hall");
        }
        
        // Get shift information
        $shiftStmt = $pdo->prepare("SELECT shift_name FROM shifts WHERE id = ? AND hall_id = ? AND is_active = 1");
        $shiftStmt->execute([$shiftId, $hallId]);
        $shift = $shiftStmt->fetch();
        
        if (!$shift) {
            throw new Exception('Invalid shift for selected hall');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Verify seats are available and reserve them
            $seatIds = [];
            foreach ($selectedSeats as $seatNumber) {
                $seatStmt = $pdo->prepare("
                    SELECT id FROM seats 
                    WHERE hall_id = ? AND shift_id = ? AND seat_number = ? AND status = 'available'
                    FOR UPDATE
                ");
                $seatStmt->execute([$hallId, $shiftId, $seatNumber]);
                $seat = $seatStmt->fetch();
                
                if (!$seat) {
                    throw new Exception("Seat {$seatNumber} is no longer available");
                }
                
                $seatIds[] = $seat['id'];
            }
            
            // Mark seats as occupied
            foreach ($seatIds as $seatId) {
                $updateSeatStmt = $pdo->prepare("UPDATE seats SET status = 'occupied', updated_at = NOW() WHERE id = ?");
                $updateSeatStmt->execute([$seatId]);
            }
            
            // Create registration
            $registrationStmt = $pdo->prepare("
                INSERT INTO registrations (
                    emp_number, staff_name, attendee_count, hall_id, shift_id, 
                    selected_seats, ip_address, user_agent, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $registrationStmt->execute([
                $empNumber,
                $staffName,
                $attendeeCount,
                $hallId,
                $shiftId,
                json_encode($selectedSeats),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $registrationId = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Prepare response data
            $responseData = [
                'success' => true,
                'message' => 'Registration completed successfully',
                'registration' => [
                    'id' => $registrationId,
                    'emp_number' => $empNumber,
                    'staff_name' => $staffName,
                    'attendee_count' => $attendeeCount,
                    'hall_name' => $hall['hall_name'],
                    'shift_name' => $shift['shift_name'],
                    'selected_seats' => $selectedSeats,
                    'registration_date' => date('Y-m-d H:i:s')
                ]
            ];
            
            echo json_encode($responseData);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        throw new Exception("Registration failed: " . $e->getMessage());
    }
}

function handleCheckEmployee($pdo) {
    try {
        $empNumber = strtoupper(trim($_POST['emp_number'] ?? ''));
        
        if (!validateEmployeeNumber($empNumber)) {
            throw new Exception('Invalid employee number format');
        }
        
        $stmt = $pdo->prepare("SELECT full_name, email, department FROM employees WHERE emp_number = ? AND is_active = 1");
        $stmt->execute([$empNumber]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            echo json_encode([
                'success' => true,
                'employee' => $employee
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Employee not found or inactive'
            ]);
        }
        
    } catch (Exception $e) {
        throw new Exception("Employee check failed: " . $e->getMessage());
    }
}
?>
