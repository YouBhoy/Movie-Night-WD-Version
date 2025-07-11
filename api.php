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
        
        // Check if seats exist for this hall and shift combination
        $seatCountStmt = $pdo->prepare("
            SELECT COUNT(*) as seat_count 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ?
        ");
        $seatCountStmt->execute([$hallId, $shiftId]);
        $seatCount = $seatCountStmt->fetchColumn();
        
        // If no seats exist, create them using the stored procedure
        if ($seatCount == 0) {
            try {
                $createStmt = $pdo->prepare("CALL createSeatsForHallShift(?, ?, ?)");
                $createStmt->execute([$hallId, $shiftId, $shift['shift_name']]);
                
                // Verify seats were created
                $seatCountStmt->execute([$hallId, $shiftId]);
                $newSeatCount = $seatCountStmt->fetchColumn();
                
                if ($newSeatCount == 0) {
                    throw new Exception('Failed to create seats for this hall and shift combination');
                }
            } catch (Exception $e) {
                error_log("Seat creation error: " . $e->getMessage());
                throw new Exception('Failed to initialize seats: ' . $e->getMessage());
            }
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
        
        // Basic validation
        if (empty($empNumber) || strlen($empNumber) < 2) {
            throw new Exception('Employee number must be at least 2 characters');
        }
        
        if (empty($staffName) || strlen($staffName) < 2) {
            throw new Exception('Full name must be at least 2 characters');
        }
        
        if (!$attendeeCount || $attendeeCount < 1 || $attendeeCount > 3) {
            throw new Exception('Invalid attendee count. Maximum 3 attendees allowed.');
        }
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Please select a shift');
        }
        
        $selectedSeats = json_decode($selectedSeatsJson, true);
        if (!is_array($selectedSeats) || count($selectedSeats) !== $attendeeCount) {
            throw new Exception('Please select exactly ' . $attendeeCount . ' seat(s)');
        }
        
        // Check if registration is enabled
        if (!isRegistrationEnabled()) {
            throw new Exception('Registration is currently disabled');
        }
        
        // Check if employee number already registered
        if (isEmployeeRegistered($empNumber)) {
            throw new Exception('This employee number is already registered for this event');
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
        
        // Validate that selected seats are available
        $placeholders = str_repeat('?,', count($selectedSeats) - 1) . '?';
        $seatCheckStmt = $pdo->prepare("
            SELECT COUNT(*) as available_count 
            FROM seats 
            WHERE hall_id = ? AND shift_id = ? AND seat_number IN ($placeholders) AND status = 'available'
        ");
        $seatCheckParams = array_merge([$hallId, $shiftId], $selectedSeats);
        $seatCheckStmt->execute($seatCheckParams);
        $availableCount = $seatCheckStmt->fetchColumn();
        
        if ($availableCount != count($selectedSeats)) {
            throw new Exception('One or more selected seats are no longer available. Please refresh and try again.');
        }
        
        // Begin transaction for seat reservation
        $pdo->beginTransaction();
        
        try {
            // Reserve the seats by updating their status
            $updateSeatStmt = $pdo->prepare("
                UPDATE seats 
                SET status = 'occupied', updated_at = NOW() 
                WHERE hall_id = ? AND shift_id = ? AND seat_number = ? AND status = 'available'
            ");
            
            foreach ($selectedSeats as $seatNumber) {
                $updateSeatStmt->execute([$hallId, $shiftId, $seatNumber]);
                if ($updateSeatStmt->rowCount() === 0) {
                    throw new Exception("Failed to reserve seat {$seatNumber}. It may have been taken by another user.");
                }
            }
            
            // Create registration record
            $insertStmt = $pdo->prepare("
                INSERT INTO registrations (
                    emp_number, staff_name, attendee_count, hall_id, shift_id,
                    selected_seats, movie_name, screening_time, ip_address, user_agent, 
                    status, registration_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $movieName = getEventSetting('movie_name', 'WD Movie Night');
            $screeningTime = getEventSetting('screening_time', 'TBA');
            
            $insertStmt->execute([
                $empNumber,
                $staffName,
                $attendeeCount,
                $hallId,
                $shiftId,
                json_encode($selectedSeats),
                $movieName,
                $screeningTime,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $registrationId = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Store registration data in session for confirmation page
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_data'] = [
                'id' => $registrationId,
                'emp_number' => $empNumber,
                'staff_name' => $staffName,
                'attendee_count' => $attendeeCount,
                'hall_name' => $hallShift['hall_name'],
                'shift_name' => $hallShift['shift_name'],
                'selected_seats' => $selectedSeats,
                'registration_date' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration completed successfully! 🎉',
                'redirect' => 'confirmation.php'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Registration Error: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
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
        
        // Check if already registered
        if (isEmployeeRegistered($empNumber)) {
            echo json_encode([
                'success' => false,
                'message' => 'This employee number is already registered'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'employee' => [
                    'full_name' => 'Employee',
                    'email' => null,
                    'department' => 'General'
                ]
            ]);
        }
        
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
