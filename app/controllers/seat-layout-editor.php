<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Reject every write before any database access, including AJAX layout saves.
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireAdminApi(true);
requireAdminLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_layout') {
    $pdo = getDBConnection();
    $message = '';
    $messageType = '';
    handleSaveLayout($pdo);
    echo json_encode(['success' => $messageType === 'success', 'message' => $message]);
    exit;
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateAdminCSRFToken($_POST['admin_csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');

        switch ($action) {
            case 'delete_seat':
                handleDeleteSeat($pdo);
                break;
            case 'add_seat':
                handleAddSeat($pdo);
                break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['success' => $messageType === 'success', 'message' => $message]);
    exit;
}

function handleSaveLayout($pdo) {
    global $message, $messageType;

    try {
        $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        $seatsData = $_POST['seats'] ?? '';

        if (!$hallId || !$shiftId) {
            throw new DomainException('Invalid hall or shift ID');
        }

        $seats = json_decode($seatsData, true);
        if (!is_array($seats)) {
            throw new DomainException('Invalid seat data format');
        }

        $pdo->beginTransaction();

        // Serialize layout changes with bookings and cancellation.
        $lock = $pdo->prepare('SELECT id FROM cinema_halls WHERE id = ? FOR UPDATE');
        $lock->execute([$hallId]);
        if (!$lock->fetch()) throw new DomainException('Hall not found');
        $lock = $pdo->prepare('SELECT id FROM shifts WHERE id = ? AND hall_id = ? FOR UPDATE');
        $lock->execute([$shiftId, $hallId]);
        if (!$lock->fetch()) throw new DomainException('Invalid hall and shift combination');
        $bookings = $pdo->prepare("SELECT id FROM registrations WHERE hall_id = ? AND shift_id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
        $bookings->execute([$hallId, $shiftId]);
        if ($bookings->fetch()) throw new DomainException('Cancel active bookings before replacing this layout.');
        $currentSeats = $pdo->prepare("SELECT id FROM seats WHERE hall_id = ? AND shift_id = ? AND status = 'occupied' LIMIT 1 FOR UPDATE");
        $currentSeats->execute([$hallId, $shiftId]);
        if ($currentSeats->fetch()) throw new DomainException('Occupied seats must be released before replacing this layout.');
        $numbers = [];
        foreach ($seats as $seat) {
            if (!is_array($seat) || !is_string($seat['row_letter'] ?? null) || !preg_match('/^[A-Z]$/', $seat['row_letter']) ||
                !filter_var($seat['seat_position'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ||
                !is_string($seat['seat_number'] ?? null) || $seat['seat_number'] === '' || strlen($seat['seat_number']) > 10 ||
                !in_array($seat['status'] ?? '', ['available', 'blocked', 'reserved'], true) ||
                isset($numbers[$seat['seat_number']])) {
                throw new DomainException('Invalid or duplicate seat data. Occupied status is managed by bookings.');
            }
            $numbers[$seat['seat_number']] = true;
        }

        // Delete all existing seats for this hall and shift
        $deleteStmt = $pdo->prepare("DELETE FROM seats WHERE hall_id = ? AND shift_id = ?");
        $deleteStmt->execute([$hallId, $shiftId]);

        // Insert new seat data
        $insertStmt = $pdo->prepare("
            INSERT INTO seats (hall_id, shift_id, row_letter, seat_position, seat_number, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($seats as $seat) {
            $insertStmt->execute([
                $hallId,
                $shiftId,
                $seat['row_letter'],
                $seat['seat_position'],
                $seat['seat_number'],
                $seat['status']
            ]);
        }

        $pdo->commit();

        logAdminActivity('save_seat_layout', 'seats', null, [
            'hall_id' => $hallId,
            'shift_id' => $shiftId,
            'seats_count' => count($seats)
        ]);

        $message = "Seat layout saved successfully.";
        $messageType = "success";

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e instanceof DomainException ? $e->getMessage() : 'Unable to save the layout. Please try again.';
        $messageType = "error";
        error_log("Save layout error: " . $e->getMessage());
    }
}

function handleDeleteSeat($pdo) {
    global $message, $messageType;

    try {
        $seatId = filter_var($_POST['seat_id'] ?? '', FILTER_VALIDATE_INT);

        if (!$seatId) {
            throw new DomainException('Invalid seat ID');
        }

        $deleteStmt = $pdo->prepare("DELETE FROM seats WHERE id = ? AND status != 'occupied'");
        $deleteStmt->execute([$seatId]);

        if ($deleteStmt->rowCount() > 0) {
            logAdminActivity('delete_seat', 'seats', $seatId);
            $message = "Seat deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Seat not found or occupied";
            $messageType = "error";
        }

    } catch (Exception $e) {
        $message = $e instanceof DomainException ? $e->getMessage() : 'Unable to delete the seat. Please try again.';
        $messageType = "error";
        error_log("Delete seat error: " . $e->getMessage());
    }
}

function handleAddSeat($pdo) {
    global $message, $messageType;

    try {
        $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        $rowLetter = strtoupper(trim($_POST['row_letter'] ?? ''));
        $seatPosition = filter_var($_POST['seat_position'] ?? '', FILTER_VALIDATE_INT);
        $status = sanitizeInput($_POST['status'] ?? 'available');

        if (!$hallId || !$shiftId || !$rowLetter || !$seatPosition) {
            throw new DomainException('All fields are required');
        }

        if (!preg_match('/^[A-Z]$/', $rowLetter)) {
            throw new DomainException('Row letter must be A-Z');
        }

        if ($seatPosition < 1) {
            throw new DomainException('Seat position must be positive');
        }

        if (!in_array($status, ['available', 'blocked', 'reserved'], true)) {
            throw new DomainException('Invalid seat status. Occupied status is managed by bookings.');
        }
        $seatNumber = $rowLetter . $seatPosition;

        // Check if seat already exists
        $checkStmt = $pdo->prepare("SELECT id FROM seats WHERE hall_id = ? AND shift_id = ? AND seat_number = ?");
        $checkStmt->execute([$hallId, $shiftId, $seatNumber]);

        if ($checkStmt->rowCount() > 0) {
            throw new DomainException('Seat ' . $seatNumber . ' already exists');
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO seats (hall_id, shift_id, row_letter, seat_position, seat_number, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $insertStmt->execute([$hallId, $shiftId, $rowLetter, $seatPosition, $seatNumber, $status]);

        logAdminActivity('add_seat', 'seats', $pdo->lastInsertId(), [
            'hall_id' => $hallId,
            'shift_id' => $shiftId,
            'seat_number' => $seatNumber
        ]);

        $message = "Seat " . $seatNumber . " added successfully.";
        $messageType = "success";

    } catch (Exception $e) {
        $message = $e instanceof DomainException ? $e->getMessage() : 'Unable to add the seat. Please try again.';
        $messageType = "error";
        error_log("Add seat error: " . $e->getMessage());
    }
}

// Get event settings
$settings = getEventSettings(true);

// Get halls and shifts for the form
// Only show active halls
$hallsStmt = $pdo->prepare("SELECT id, hall_name FROM cinema_halls WHERE is_active = 1 ORDER BY id");
$hallsStmt->execute();
$halls = $hallsStmt->fetchAll();

// Only show active shifts
$shiftsStmt = $pdo->prepare("SELECT id, shift_name, hall_id FROM shifts WHERE is_active = 1 ORDER BY hall_id, id");
$shiftsStmt->execute();
$shifts = $shiftsStmt->fetchAll();

// Use admin CSRF token for admin actions
$csrfToken = generateAdminCSRFToken();

require dirname(__DIR__) . '/views/seat-layout-editor.php';
