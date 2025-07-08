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

// Rate limiting
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIP, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
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
        case 'get_registrations':
            handleGetRegistrations($pdo);
            break;
        case 'search_registrations':
            handleSearchRegistrations($pdo);
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
        
        // Get seats for this hall and shift combination
        $stmt = $pdo->prepare("
            SELECT id, seat_number, row_letter, seat_position, status 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? 
            ORDER BY row_letter, seat_position
        ");
        $stmt->execute([$hallId, $shiftId]);
        $seats = $stmt->fetchAll();
        
        // If no seats exist for this combination, create them dynamically
        if (empty($seats)) {
            createSeatsForHallAndShift($pdo, $hallId, $shiftId, $shift['shift_name']);
            
            // Try to get seats again after creation
            $stmt->execute([$hallId, $shiftId]);
            $seats = $stmt->fetchAll();
        }
        
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
        throw new Exception("Failed to load seats: " . $e->getMessage());
    }
}

function createSeatsForHallAndShift($pdo, $hallId, $shiftId, $shiftName) {
    try {
        $pdo->beginTransaction();
        
        if ($hallId == 1) {
            // CINEMA HALL 1 - Standard layout
            $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L'];
            
            foreach ($rows as $rowLetter) {
                if ($shiftId == 1) {
                    // Normal Shift gets seats 1-6
                    for ($position = 1; $position <= 6; $position++) {
                        $seatNumber = $rowLetter . $position;
                        insertSeat($pdo, $hallId, $shiftId, $seatNumber, $rowLetter, $position);
                    }
                } else if ($shiftId == 2) {
                    // Crew C (Day Shift) gets seats 7-11
                    for ($position = 7; $position <= 11; $position++) {
                        $seatNumber = $rowLetter . $position;
                        insertSeat($pdo, $hallId, $shiftId, $seatNumber, $rowLetter, $position);
                    }
                }
            }
            
        } else if ($hallId == 2) {
            // CINEMA HALL 2 - Special layout based on crew assignments
            // 13 rows A through M, skipping I (as shown in the image)
            $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M'];
            
            foreach ($rows as $rowLetter) {
                if (strpos($shiftName, 'CREW A') !== false) {
                    // CREW A (OFF/REST DAY) gets seats 1-6 in each row (left section)
                    for ($position = 1; $position <= 6; $position++) {
                        $seatNumber = $rowLetter . $position;
                        insertSeat($pdo, $hallId, $shiftId, $seatNumber, $rowLetter, $position);
                    }
                } else if (strpos($shiftName, 'CREW B') !== false) {
                    // CREW B (OFF/REST DAY) gets seats 7-12 in each row (right section)
                    for ($position = 7; $position <= 12; $position++) {
                        $seatNumber = $rowLetter . $position;
                        insertSeat($pdo, $hallId, $shiftId, $seatNumber, $rowLetter, $position);
                    }
                }
            }
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Failed to create seats: " . $e->getMessage());
    }
}

function insertSeat($pdo, $hallId, $shiftId, $seatNumber, $rowLetter, $position) {
    $stmt = $pdo->prepare("
        INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'available', NOW())
    ");
    $stmt->execute([$hallId, $shiftId, $seatNumber, $rowLetter, $position]);
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
        
        // Set different max attendees for different halls
        $maxAttendees = ($hallId == 2) ? 3 : MAX_ATTENDEES_PER_BOOKING;
        
        if (!$attendeeCount || $attendeeCount < 1 || $attendeeCount > $maxAttendees) {
            throw new Exception('Invalid attendee count. Maximum ' . $maxAttendees . ' attendees allowed for this hall.');
        }
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Missing required fields');
        }
        
        $selectedSeats = json_decode($selectedSeatsJson, true);
        if (!is_array($selectedSeats) || count($selectedSeats) !== $attendeeCount) {
            throw new Exception('Invalid seat selection');
        }
        
        // Check if registration is enabled
        $settingStmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'registration_enabled'");
        $settingStmt->execute();
        $regEnabled = $settingStmt->fetchColumn();
        
        if ($regEnabled !== 'true') {
            throw new Exception('Registration is currently disabled');
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
        
        // Verify hall and shift combination
        $hallShiftStmt = $pdo->prepare("
            SELECT h.hall_name, h.max_attendees_per_booking, s.shift_name 
            FROM cinema_halls h 
            JOIN shifts s ON h.id = s.hall_id 
            WHERE h.id = ? AND s.id = ? AND h.is_active = 1 AND s.is_active = 1
        ");
        $hallShiftStmt->execute([$hallId, $shiftId]);
        $hallShift = $hallShiftStmt->fetch();

        if (!$hallShift) {
            throw new Exception('Invalid hall and shift combination');
        }

        // Use the hall-specific max attendees
        $hallMaxAttendees = ($hallId == 2) ? 4 : $hallShift['max_attendees_per_booking'];
        if ($attendeeCount > $hallMaxAttendees) {
            throw new Exception("Maximum {$hallMaxAttendees} attendees allowed for this cinema hall");
        }
        
        // Validate seat adjacency (server-side validation)
        if (!validateSeatAdjacency($pdo, $selectedSeats, $hallId, $shiftId)) {
            throw new Exception('Selected seats must be adjacent to each other with no gaps');
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
                    'hall_name' => $hallShift['hall_name'],
                    'shift_name' => $hallShift['shift_name'],
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

function validateSeatAdjacency($pdo, $selectedSeats, $hallId, $shiftId) {
    if (count($selectedSeats) <= 1) {
        return true; // Single seat or no seats are always valid
    }
    
    // Check if seat separation is allowed
    $settingStmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'allow_seat_separation'");
    $settingStmt->execute();
    $allowSeparation = $settingStmt->fetchColumn();
    
    if ($allowSeparation === 'true') {
        return true; // Skip adjacency check if separation is allowed
    }
    
    // Get seat positions for validation
    $seatPositions = [];
    foreach ($selectedSeats as $seatNumber) {
        $stmt = $pdo->prepare("
            SELECT row_letter, seat_position 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? AND seat_number = ?
        ");
        $stmt->execute([$hallId, $shiftId, $seatNumber]);
        $seat = $stmt->fetch();
        
        if (!$seat) {
            return false; // Seat not found
        }
        
        $seatPositions[] = [
            'seat_number' => $seatNumber,
            'row_letter' => $seat['row_letter'],
            'seat_position' => (int)$seat['seat_position']
        ];
    }
    
    // Group by row
    $seatsByRow = [];
    foreach ($seatPositions as $seat) {
        $seatsByRow[$seat['row_letter']][] = $seat['seat_position'];
    }
    
    // Check each row has continuous seats
    foreach ($seatsByRow as $row => $positions) {
        sort($positions);
        
        // Check if positions are continuous
        for ($i = 1; $i < count($positions); $i++) {
            if ($positions[$i] - $positions[$i-1] !== 1) {
                return false; // Gap found
            }
        }
    }
    
    return true;
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
?>
