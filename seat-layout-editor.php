<?php
session_start();
require_once 'config.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit;
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');
        
        switch ($action) {
            case 'save_layout':
                handleSaveLayout($pdo);
                break;
            case 'delete_seat':
                handleDeleteSeat($pdo);
                break;
            case 'add_seat':
                handleAddSeat($pdo);
                break;
        }
    }
}

function handleSaveLayout($pdo) {
    global $message, $messageType;
    
    try {
        $hallId = filter_var($_POST['hall_id'] ?? '', FILTER_VALIDATE_INT);
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        $seatsData = $_POST['seats'] ?? '';
        
        if (!$hallId || !$shiftId) {
            throw new Exception('Invalid hall or shift ID');
        }
        
        $seats = json_decode($seatsData, true);
        if (!is_array($seats)) {
            throw new Exception('Invalid seat data format');
        }
        
        $pdo->beginTransaction();
        
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
        
        $message = "Seat layout saved successfully! ✅";
        $messageType = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error saving layout: " . $e->getMessage();
        $messageType = "error";
        error_log("Save layout error: " . $e->getMessage());
    }
}

function handleDeleteSeat($pdo) {
    global $message, $messageType;
    
    try {
        $seatId = filter_var($_POST['seat_id'] ?? '', FILTER_VALIDATE_INT);
        
        if (!$seatId) {
            throw new Exception('Invalid seat ID');
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM seats WHERE id = ?");
        $deleteStmt->execute([$seatId]);
        
        if ($deleteStmt->rowCount() > 0) {
            logAdminActivity('delete_seat', 'seats', $seatId);
            $message = "Seat deleted successfully! ✅";
            $messageType = "success";
        } else {
            $message = "Seat not found";
            $messageType = "error";
        }
        
    } catch (Exception $e) {
        $message = "Error deleting seat: " . $e->getMessage();
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
            throw new Exception('All fields are required');
        }
        
        if (!preg_match('/^[A-Z]$/', $rowLetter)) {
            throw new Exception('Row letter must be A-Z');
        }
        
        if ($seatPosition < 1) {
            throw new Exception('Seat position must be positive');
        }
        
        $seatNumber = $rowLetter . $seatPosition;
        
        // Check if seat already exists
        $checkStmt = $pdo->prepare("SELECT id FROM seats WHERE hall_id = ? AND shift_id = ? AND seat_number = ?");
        $checkStmt->execute([$hallId, $shiftId, $seatNumber]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Seat ' . $seatNumber . ' already exists');
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
        
        $message = "Seat " . $seatNumber . " added successfully! ✅";
        $messageType = "success";
        
    } catch (Exception $e) {
        $message = "Error adding seat: " . $e->getMessage();
        $messageType = "error";
        error_log("Add seat error: " . $e->getMessage());
    }
}

// Get event settings
$settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
$settingsStmt->execute();
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get halls and shifts for the form
$hallsStmt = $pdo->prepare("SELECT id, hall_name FROM cinema_halls WHERE is_active = 1 ORDER BY id");
$hallsStmt->execute();
$halls = $hallsStmt->fetchAll();

$shiftsStmt = $pdo->prepare("SELECT id, shift_name, hall_id FROM shifts WHERE is_active = 1 ORDER BY hall_id, id");
$shiftsStmt->execute();
$shifts = $shiftsStmt->fetchAll();

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Layout Editor - Admin Panel</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .controls-section {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ffd700;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #ffffff;
            font-size: 0.9rem;
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        /* Modal Styles */
        .modal-content {
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-dark {
            background-color: rgba(0, 0, 0, 0.3);
            color: #ffffff;
        }

        .table-dark th {
            background-color: rgba(255, 215, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.1);
            color: #ffd700;
        }

        .table-dark td {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .table-dark.table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .badge {
            font-size: 0.75rem;
        }

        .text-muted {
            color: #6c757d !important;
        }

        /* Toast Styles */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            min-width: 250px;
        }

        .seat-grid-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .seat-grid-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .seat-grid-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffd700;
        }

        .seat-grid-actions {
            display: flex;
            gap: 1rem;
        }

        .seat-grid {
            display: grid;
            gap: 0.5rem;
            max-width: 100%;
            overflow-x: auto;
        }

        .seat-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .row-label {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .seat {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            border: 2px solid transparent;
        }

        .seat:hover {
            transform: scale(1.05);
        }

        .seat.available {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
        }

        .seat.occupied {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
        }

        .seat.blocked {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #ffffff;
        }

        .seat.reserved {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #ffffff;
        }

        .seat.empty {
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.5);
        }

        .seat.empty:hover {
            background: rgba(255, 215, 0, 0.1);
            border-color: #ffd700;
            color: #ffd700;
        }

        .seat .delete-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 10px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .seat:hover .delete-btn {
            display: flex;
        }

        .seat-status-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 0.5rem;
            z-index: 1000;
            display: none;
            min-width: 120px;
        }

        .seat-status-option {
            padding: 0.5rem;
            cursor: pointer;
            border-radius: 4px;
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
        }

        .seat-status-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .seat-status-option.available { color: #10b981; }
        .seat-status-option.occupied { color: #ef4444; }
        .seat-status-option.blocked { color: #6b7280; }
        .seat-status-option.reserved { color: #f59e0b; }

        .legend {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffd700;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .screen-indicator {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            color: #ffd700;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .seat-grid-actions {
                flex-direction: column;
            }
            
            .legend {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chair"></i> Seat Layout Editor</h1>
            <div class="header-actions">
                <a href="admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo sanitizeInput($message); ?>
            </div>
        <?php endif; ?>

        <div class="controls-section">
            <h2 style="color: #ffd700; margin-bottom: 1.5rem;">
                <i class="fas fa-cog"></i> Layout Controls
            </h2>
            
            <div class="form-row">
                <div class="form-group" style="position: relative; display: flex; align-items: center;">
                    <label class="form-label">Cinema Hall</label>
                    <select id="hallSelector" class="form-select">
                        <option value="">Select a hall</option>
                        <?php foreach (
                            $halls as $hall): ?>
                            <option value="<?php echo $hall['id']; ?>">
                                <?php echo sanitizeInput($hall['hall_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="hallSettingsBtn" class="btn btn-link" style="margin-left: 0.5rem; color: #ffd700; font-size: 1.2rem;" title="Manage Halls" onclick="showHallManagement()">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
                
                <div class="form-group" style="position: relative; display: flex; align-items: center;">
                    <label class="form-label">Shift</label>
                    <select id="shiftSelector" class="form-select">
                        <option value="">Select a shift</option>
                    </select>
                    <button type="button" id="shiftSettingsBtn" class="btn btn-link" style="margin-left: 0.5rem; color: #ffd700; font-size: 1.2rem;" title="Manage Shifts" onclick="showShiftManagement()">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Add New Seat</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="newRowLetter" class="form-input" placeholder="Row (A-Z)" maxlength="1" style="width: 80px;">
                        <input type="number" id="newSeatPosition" class="form-input" placeholder="Position" min="1" style="width: 100px;">
                        <select id="newSeatStatus" class="form-select" style="width: 120px;">
                            <option value="available">Available</option>
                            <option value="blocked">Blocked</option>
                            <option value="reserved">Reserved</option>
                        </select>
                        <button type="button" id="addSeatBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="seat-grid-container">
            <div class="seat-grid-header">
                <div class="seat-grid-title">
                    <i class="fas fa-th"></i> 
                    <span id="gridTitle">Select a hall and shift to view seats</span>
                </div>
                <div class="seat-grid-actions">
                    <button type="button" id="saveLayoutBtn" class="btn btn-primary" disabled>
                        <i class="fas fa-save"></i> Save Layout
                    </button>
                    <button type="button" id="resetBtn" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <div class="screen-indicator">
                <i class="fas fa-tv"></i> SCREEN
            </div>

            <div id="seatGrid" class="seat-grid">
                <div style="text-align: center; color: #94a3b8; padding: 3rem;">
                    <i class="fas fa-chair" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Select a hall and shift to start editing the seat layout</p>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Occupied</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #6b7280;"></div>
                    <span>Blocked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f59e0b;"></div>
                    <span>Reserved</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: rgba(255,255,255,0.1); border: 2px dashed rgba(255,255,255,0.3);"></div>
                    <span>Empty (Click to add)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hall Management Section -->
    <div class="controls-section" id="hallManagementSection" style="display: none;">
      <h3 class="text-warning mb-3"><i class="fas fa-building"></i> Cinema Hall Management</h3>
      
      <!-- Hall List Section -->
      <div id="hallListSection">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-warning mb-0">Active Cinema Halls</h6>
          <div>
            <button type="button" class="btn btn-primary btn-sm" onclick="showAddHallForm()">
              <i class="fas fa-plus"></i> Add New Hall
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideHallManagement()">
              <i class="fas fa-times"></i> Close
            </button>
          </div>
        </div>
        <div id="hallList" class="mb-3">
          <!-- Halls will be loaded here -->
        </div>
      </div>

      <!-- Add/Edit Hall Form -->
      <div id="hallFormSection" style="display: none;">
        <h6 class="text-warning mb-3" id="hallFormTitle">Add New Cinema Hall</h6>
        <form id="hallForm">
          <input type="hidden" id="hallId" name="hall_id">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="hallName" class="form-label">Hall Name</label>
                <input type="text" class="form-control" id="hallName" name="hall_name" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group mb-3">
                <label for="maxAttendees" class="form-label">Max Attendees per Booking</label>
                <input type="number" class="form-control" id="maxAttendees" name="max_attendees_per_booking" min="1" value="3" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group mb-3">
                <label for="totalSeats" class="form-label">Total Seats</label>
                                                <input type="number" class="form-control" id="totalSeats" name="total_seats" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>" required>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Hall
            </button>
            <button type="button" class="btn btn-secondary" onclick="showHallList()">
              <i class="fas fa-arrow-left"></i> Back to List
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Shift Management Section -->
    <div class="controls-section" id="shiftManagementSection" style="display: none;">
      <h3 class="text-warning mb-3"><i class="fas fa-clock"></i> Shift Management</h3>
      
      <!-- Hall Selection for Shifts -->
      <div class="form-group mb-3">
        <label for="shiftHallSelector" class="form-label">Select Cinema Hall</label>
        <select class="form-select" id="shiftHallSelector">
          <option value="">Choose a hall...</option>
        </select>
      </div>

      <!-- Shift List Section -->
      <div id="shiftListSection">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="text-warning mb-0">Active Shifts</h6>
          <div>
            <button type="button" class="btn btn-primary btn-sm" id="addShiftBtn" onclick="showAddShiftForm()" disabled>
              <i class="fas fa-plus"></i> Add New Shift
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideShiftManagement()">
              <i class="fas fa-times"></i> Close
            </button>
          </div>
        </div>
        <div id="shiftList" class="mb-3">
          <!-- Shifts will be loaded here -->
        </div>
      </div>

      <!-- Add/Edit Shift Form -->
      <div id="shiftFormSection" style="display: none;">
        <h6 class="text-warning mb-3" id="shiftFormTitle">Add New Shift</h6>
        <form id="shiftForm">
          <input type="hidden" id="shiftId" name="shift_id">
          <input type="hidden" id="shiftHallId" name="hall_id">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="shiftName" class="form-label">Shift Name</label>
                <input type="text" class="form-control" id="shiftName" name="shift_name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group mb-3">
                <label for="shiftCode" class="form-label">Shift Code</label>
                <input type="text" class="form-control" id="shiftCode" name="shift_code" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="seatPrefix" class="form-label">Seat Prefix</label>
                <input type="text" class="form-control" id="seatPrefix" name="seat_prefix" maxlength="5">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="seatCount" class="form-label">Seat Count</label>
                                                <input type="number" class="form-control" id="seatCount" name="seat_count" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="startTime" class="form-label">Start Time</label>
                <input type="time" class="form-control" id="startTime" name="start_time" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group mb-3">
                <label for="endTime" class="form-label">End Time</label>
                <input type="time" class="form-control" id="endTime" name="end_time" required>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Shift
            </button>
            <button type="button" class="btn btn-secondary" onclick="showShiftList()">
              <i class="fas fa-arrow-left"></i> Back to List
            </button>
          </div>
        </form>
      </div>
    </div>

    <script>
        // Configuration
        const csrfToken = '<?php echo $csrfToken; ?>';
        const halls = <?php echo json_encode($halls, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const shifts = <?php echo json_encode($shifts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // State
        let currentHallId = null;
        let currentShiftId = null;
        let currentSeats = [];
        let gridSize = { rows: 10, cols: 10 }; // This variable is no longer used for grid size, but kept for potential future use or if other parts of the code rely on it.
        let hasChanges = false;

        // DOM Elements
        const hallSelector = document.getElementById('hallSelector');
        const shiftSelector = document.getElementById('shiftSelector');
        const seatGrid = document.getElementById('seatGrid');
        const gridTitle = document.getElementById('gridTitle');
        const saveLayoutBtn = document.getElementById('saveLayoutBtn');
        const resetBtn = document.getElementById('resetBtn');
        const addSeatBtn = document.getElementById('addSeatBtn');
        const newRowLetter = document.getElementById('newRowLetter');
        const newSeatPosition = document.getElementById('newSeatPosition');
        const newSeatStatus = document.getElementById('newSeatStatus');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            updateShiftSelector();
            
            // New event listeners for hall and shift management
            document.getElementById('hallForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitHallForm();
            });
            
            document.getElementById('shiftForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitShiftForm();
            });
            

            
            document.getElementById('shiftHallSelector').addEventListener('change', function() {
                const hallId = this.value;
                document.getElementById('addShiftBtn').disabled = !hallId;
                if (hallId) {
                    loadShiftsByHall(hallId);
                } else {
                    document.getElementById('shiftList').innerHTML = '<div class="text-muted">Please select a hall first.</div>';
                }
            });
        });

        function setupEventListeners() {
            hallSelector.addEventListener('change', function() {
                currentHallId = parseInt(this.value);
                updateShiftSelector();
                if (currentHallId && currentShiftId) {
                    loadSeatLayout();
                }
            });

            shiftSelector.addEventListener('change', function() {
                currentShiftId = parseInt(this.value);
                if (currentHallId && currentShiftId) {
                    loadSeatLayout();
                }
            });

            saveLayoutBtn.addEventListener('click', saveLayout);
            resetBtn.addEventListener('click', resetLayout);
            addSeatBtn.addEventListener('click', addNewSeat);

            // Auto-uppercase row letter input
            newRowLetter.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }

        function updateShiftSelector() {
            shiftSelector.innerHTML = '<option value="">Select a shift</option>';
            currentShiftId = null;
            
            if (!currentHallId) return;

            const hallShifts = shifts.filter(shift => shift.hall_id == currentHallId);
            hallShifts.forEach(shift => {
                const option = document.createElement('option');
                option.value = shift.id;
                option.textContent = shift.shift_name;
                shiftSelector.appendChild(option);
            });
        }

        function loadSeatLayout() {
            if (!currentHallId || !currentShiftId) return;

            const hall = halls.find(h => h.id == currentHallId);
            const shift = shifts.find(s => s.id == currentShiftId);
            
            gridTitle.textContent = `${hall.hall_name} - ${shift.shift_name}`;
            seatGrid.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading"></div> Loading seats...</div>';

            fetch('admin-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_seat_layout&hall_id=${currentHallId}&shift_id=${currentShiftId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentSeats = data.seats || [];
                    renderSeatGrid();
                } else {
                    seatGrid.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 2rem;">Error loading seats</div>';
                }
            })
            .catch(error => {
                console.error('Error loading seats:', error);
                seatGrid.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 2rem;">Error loading seats</div>';
            });
        }

        function renderSeatGrid() {
            if (!currentHallId || !currentShiftId) return;

            // Determine grid size based on hall
            let rows = 10, cols = 10;
            const hall = halls.find(h => h.id == currentHallId);
            if (hall) {
                if (hall.hall_name.toLowerCase().includes('hall 1')) {
                    rows = 12; // A-L
                    cols = 11;
                } else if (hall.hall_name.toLowerCase().includes('hall 2')) {
                    rows = 13; // A-M
                    cols = 12;
                }
            }
            // Expand rows/cols if seat data or extraRows/extraCols require it
            const maxRowFromSeats = getMaxRowFromSeats();
            if (maxRowFromSeats + 1 > rows) rows = maxRowFromSeats + 1;
            rows += extraRows;
            const maxColFromSeats = getMaxColFromSeats();
            if (maxColFromSeats > cols) cols = maxColFromSeats;
            cols += extraCols;
            let html = '';

            // Create seat map for quick lookup
            const seatMap = {};
            currentSeats.forEach(seat => {
                if (!seatMap[seat.row_letter]) seatMap[seat.row_letter] = {};
                seatMap[seat.row_letter][seat.seat_position] = seat;
            });

            // Generate grid
            for (let row = 0; row < rows; row++) {
                const rowLetter = String.fromCharCode(65 + row); // A, B, C, etc.
                html += '<div class="seat-row">';
                html += `<div class="row-label">${rowLetter}</div>`;
                
                for (let col = 1; col <= cols; col++) {
                    const seat = seatMap[rowLetter]?.[col];
                    
                    if (seat) {
                        // Existing seat
                        html += `
                            <div class="seat ${seat.status}" 
                                 data-seat-id="${seat.id}" 
                                 data-row="${rowLetter}" 
                                 data-col="${col}"
                                 onclick="toggleSeatStatus(${seat.id})">
                                ${seat.seat_number}
                                <button class="delete-btn" onclick="deleteSeat(${seat.id}, event)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                    } else {
                        // Empty seat position
                        html += `
                            <div class="seat empty" 
                                 data-row="${rowLetter}" 
                                 data-col="${col}"
                                 onclick="addSeatAtPosition('${rowLetter}', ${col})">
                                ${rowLetter}${col}
                            </div>
                        `;
                    }
                }
                html += '</div>';
            }

            seatGrid.innerHTML = html;
            hasChanges = false;
            updateSaveButton();
        }

        function toggleSeatStatus(seatId) {
            const seat = currentSeats.find(s => s.id == seatId);
            if (!seat) return;

            const statuses = ['available', 'occupied', 'blocked', 'reserved'];
            const currentIndex = statuses.indexOf(seat.status);
            const nextIndex = (currentIndex + 1) % statuses.length;
            
            seat.status = statuses[nextIndex];
            
            // Update visual
            const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
            if (seatElement) {
                seatElement.className = `seat ${seat.status}`;
            }
            
            hasChanges = true;
            updateSaveButton();
        }

        function deleteSeat(seatId, event) {
            event.stopPropagation();
            
            if (!confirm('Are you sure you want to delete this seat?')) return;

            fetch('seat-layout-editor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_seat&seat_id=${seatId}&csrf_token=${csrfToken}`
            })
            .then(response => response.text())
            .then(() => {
                // Remove from current seats
                currentSeats = currentSeats.filter(s => s.id != seatId);
                renderSeatGrid();
            })
            .catch(error => {
                console.error('Error deleting seat:', error);
                alert('Error deleting seat');
            });
        }

        function addSeatAtPosition(rowLetter, col) {
            const seatNumber = rowLetter + col;
            
            // Check if seat already exists
            if (currentSeats.find(s => s.seat_number === seatNumber)) {
                alert('Seat already exists');
                return;
            }

            const newSeat = {
                id: 'temp_' + Date.now(),
                row_letter: rowLetter,
                seat_position: col,
                seat_number: seatNumber,
                status: 'available'
            };

            currentSeats.push(newSeat);
            // If the new row/col is beyond the current grid, trigger grid expansion
            const code = rowLetter.charCodeAt(0) - 65;
            let rows = 10, cols = 10;
            const hall = halls.find(h => h.id == currentHallId);
            if (hall) {
                if (hall.hall_name.toLowerCase().includes('hall 1')) { rows = 12; cols = 11; }
                else if (hall.hall_name.toLowerCase().includes('hall 2')) { rows = 13; cols = 12; }
            }
            if (code + 1 > rows + extraRows) {
                extraRows = code + 1 - rows;
            }
            if (col > cols + extraCols) {
                extraCols = col - cols;
            }
            renderSeatGrid();
            hasChanges = true;
            updateSaveButton();
        }

        function addNewSeat() {
            const rowLetter = newRowLetter.value.trim().toUpperCase();
            const seatPosition = parseInt(newSeatPosition.value);
            const status = newSeatStatus.value;

            if (!rowLetter || !seatPosition) {
                alert('Please enter both row letter and seat position');
                return;
            }

            if (!/^[A-Z]$/.test(rowLetter)) {
                alert('Row letter must be A-Z');
                return;
            }

            if (seatPosition < 1) {
                alert('Seat position must be positive');
                return;
            }

            if (!currentHallId || !currentShiftId) {
                alert('Please select a hall and shift first');
                return;
            }

            const seatNumber = rowLetter + seatPosition;
            
            // Check if seat already exists
            if (currentSeats.find(s => s.seat_number === seatNumber)) {
                alert('Seat already exists');
                return;
            }

            fetch('seat-layout-editor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_seat&hall_id=${currentHallId}&shift_id=${currentShiftId}&row_letter=${rowLetter}&seat_position=${seatPosition}&status=${status}&csrf_token=${csrfToken}`
            })
            .then(response => response.text())
            .then(() => {
                // Clear form
                newRowLetter.value = '';
                newSeatPosition.value = '';
                
                // Reload layout
                loadSeatLayout();
            })
            .catch(error => {
                console.error('Error adding seat:', error);
                alert('Error adding seat');
            });
        }

        function saveLayout() {
            if (!currentHallId || !currentShiftId) return;
            const formData = new FormData();
            formData.append('action', 'save_layout');
            formData.append('hall_id', currentHallId);
            formData.append('shift_id', currentShiftId);
            formData.append('seats', JSON.stringify(currentSeats));
            formData.append('csrf_token', csrfToken);

            saveLayoutBtn.disabled = true;
            saveLayoutBtn.textContent = 'Saving...';

            fetch('seat-layout-editor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Show a success message (optional: use a modal or alert)
                alert('Seat layout saved successfully!');
                // Reload the seat layout to reflect any changes
                loadSeatLayout();
            })
            .catch(error => {
                console.error('Error saving layout:', error);
                alert('Error saving layout');
            })
            .finally(() => {
                saveLayoutBtn.disabled = false;
                saveLayoutBtn.textContent = 'Save Layout';
            });
        }

        function resetLayout() {
            if (confirm('Are you sure you want to reset the layout? This will reload the current saved layout.')) {
                loadSeatLayout();
            }
        }

        function updateSaveButton() {
            saveLayoutBtn.disabled = !hasChanges;
        }

        // Add after the seat-grid-actions div
        // Add button to add a new row
        const seatGridActions = document.querySelector('.seat-grid-actions');
        if (seatGridActions && !document.getElementById('addRowBtn')) {
            const addRowBtn = document.createElement('button');
            addRowBtn.type = 'button';
            addRowBtn.id = 'addRowBtn';
            addRowBtn.className = 'btn btn-secondary';
            addRowBtn.innerHTML = '<i class="fas fa-plus"></i> Add Row';
            seatGridActions.appendChild(addRowBtn);
            addRowBtn.addEventListener('click', function() {
                addNewRow();
            });
        }

        // Track extra rows added by the admin
        let extraRows = 0;

        function getMaxRowFromSeats() {
            let maxRow = 0;
            currentSeats.forEach(seat => {
                const code = seat.row_letter.charCodeAt(0) - 65;
                if (code > maxRow) maxRow = code;
            });
            return maxRow;
        }

        function addNewRow() {
            extraRows++;
            renderSeatGrid();
        }

        // Track extra columns added by the admin
        let extraCols = 0;

        function getMaxColFromSeats() {
            let maxCol = 0;
            currentSeats.forEach(seat => {
                if (seat.seat_position > maxCol) maxCol = seat.seat_position;
            });
            return maxCol;
        }

        // Add after the Add Row button
        if (seatGridActions && !document.getElementById('addColBtn')) {
            const addColBtn = document.createElement('button');
            addColBtn.type = 'button';
            addColBtn.id = 'addColBtn';
            addColBtn.className = 'btn btn-secondary';
            addColBtn.innerHTML = '<i class="fas fa-plus"></i> Add Column';
            seatGridActions.appendChild(addColBtn);
            addColBtn.addEventListener('click', function() {
                addNewCol();
            });
        }

        function addNewCol() {
            extraCols++;
            renderSeatGrid();
        }

        // ===== CINEMA HALL MANAGEMENT FUNCTIONS =====
        
        function showHallManagement() {
            document.getElementById('hallManagementSection').style.display = 'block';
            document.getElementById('shiftManagementSection').style.display = 'none';
            loadCinemaHalls();
        }
        
        function hideHallManagement() {
            document.getElementById('hallManagementSection').style.display = 'none';
        }
        
        function loadCinemaHalls() {
            fetch('admin-hall-shift-api.php?action=get_active_halls')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayHalls(data.halls);
                    } else {
                        showToast('Error loading halls: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading halls:', error);
                    showToast('Error loading halls', 'error');
                });
        }

        function displayHalls(halls) {
            const hallList = document.getElementById('hallList');
            if (halls.length === 0) {
                hallList.innerHTML = '<div class="text-muted">No active halls found.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-dark table-hover">';
            html += '<thead><tr><th>Hall Name</th><th>Max Attendees</th><th>Total Seats</th><th>Actions</th></tr></thead><tbody>';
            
            halls.forEach(hall => {
                html += `
                    <tr>
                        <td>${hall.hall_name}</td>
                        <td>${hall.max_attendees_per_booking}</td>
                        <td>${hall.total_seats}</td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" onclick="editHall(${hall.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deactivateHall(${hall.id})" title="Deactivate">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            hallList.innerHTML = html;
        }

        function showAddHallForm() {
            document.getElementById('hallListSection').style.display = 'none';
            document.getElementById('hallFormSection').style.display = 'block';
            document.getElementById('hallFormTitle').textContent = 'Add New Cinema Hall';
            document.getElementById('hallForm').reset();
            document.getElementById('hallId').value = '';
        }

        function showHallList() {
            document.getElementById('hallFormSection').style.display = 'none';
            document.getElementById('hallListSection').style.display = 'block';
        }

        function editHall(hallId) {
            // Find hall data from the current halls array
            const hall = halls.find(h => h.id == hallId);
            if (!hall) {
                showToast('Hall not found', 'error');
                return;
            }

            document.getElementById('hallId').value = hall.id;
            document.getElementById('hallName').value = hall.hall_name;
            document.getElementById('maxAttendees').value = hall.max_attendees_per_booking || 3;
            document.getElementById('totalSeats').value = hall.total_seats || <?php echo $settings['default_seat_count'] ?? 72; ?>;
            
            document.getElementById('hallFormTitle').textContent = 'Edit Cinema Hall';
            showAddHallForm();
        }

        function deactivateHall(hallId) {
            if (!confirm('Are you sure you want to deactivate this hall? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'deactivate_hall');
            formData.append('hall_id', hallId);
            formData.append('csrf_token', csrfToken);

            fetch('admin-hall-shift-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Hall deactivated successfully', 'success');
                    loadCinemaHalls();
                    refreshDropdowns();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error deactivating hall:', error);
                showToast('Error deactivating hall', 'error');
            });
        }

        function submitHallForm() {
            const form = document.getElementById('hallForm');
            const formData = new FormData(form);
            
            const hallId = document.getElementById('hallId').value;
            const action = hallId ? 'update_hall' : 'add_hall';
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);

            fetch('admin-hall-shift-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    showHallList();
                    loadCinemaHalls();
                    refreshDropdowns();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving hall:', error);
                showToast('Error saving hall', 'error');
            });
        }

        // ===== SHIFT MANAGEMENT FUNCTIONS =====
        
        function showShiftManagement() {
            document.getElementById('shiftManagementSection').style.display = 'block';
            document.getElementById('hallManagementSection').style.display = 'none';
            refreshDropdowns();
        }
        
        function hideShiftManagement() {
            document.getElementById('shiftManagementSection').style.display = 'none';
        }
        
        function loadShiftsByHall(hallId) {
            if (!hallId) {
                document.getElementById('shiftList').innerHTML = '<div class="text-muted">Please select a hall first.</div>';
                return;
            }

            fetch(`admin-hall-shift-api.php?action=get_shifts_by_hall&hall_id=${hallId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayShifts(data.shifts);
                    } else {
                        showToast('Error loading shifts: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading shifts:', error);
                    showToast('Error loading shifts', 'error');
                });
        }

        function displayShifts(shifts) {
            const shiftList = document.getElementById('shiftList');
            if (shifts.length === 0) {
                shiftList.innerHTML = '<div class="text-muted">No active shifts found for this hall.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-dark table-hover">';
            html += '<thead><tr><th>Shift Name</th><th>Code</th><th>Seat Count</th><th>Time</th><th>Actions</th></tr></thead><tbody>';
            
            shifts.forEach(shift => {
                const startTime = shift.start_time.substring(0, 5);
                const endTime = shift.end_time.substring(0, 5);
                html += `
                    <tr>
                        <td>${shift.shift_name}</td>
                        <td><span class="badge bg-secondary">${shift.shift_code}</span></td>
                        <td>${shift.seat_count}</td>
                        <td>${startTime} - ${endTime}</td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" onclick="editShift(${shift.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deactivateShift(${shift.id})" title="Deactivate">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            shiftList.innerHTML = html;
        }

        function showAddShiftForm() {
            const hallId = document.getElementById('shiftHallSelector').value;
            if (!hallId) {
                showToast('Please select a hall first', 'error');
                return;
            }

            document.getElementById('shiftListSection').style.display = 'none';
            document.getElementById('shiftFormSection').style.display = 'block';
            document.getElementById('shiftFormTitle').textContent = 'Add New Shift';
            document.getElementById('shiftForm').reset();
            document.getElementById('shiftId').value = '';
            document.getElementById('shiftHallId').value = hallId;
        }

        function showShiftList() {
            document.getElementById('shiftFormSection').style.display = 'none';
            document.getElementById('shiftListSection').style.display = 'block';
        }

        function editShift(shiftId) {
            const hallId = document.getElementById('shiftHallSelector').value;
            if (!hallId) {
                showToast('Please select a hall first', 'error');
                return;
            }

            // Find shift data from the current shifts array
            const shift = shifts.find(s => s.id == shiftId && s.hall_id == hallId);
            if (!shift) {
                showToast('Shift not found', 'error');
                return;
            }

            document.getElementById('shiftId').value = shift.id;
            document.getElementById('shiftHallId').value = shift.hall_id;
            document.getElementById('shiftName').value = shift.shift_name;
            document.getElementById('shiftCode').value = shift.shift_code;
            document.getElementById('seatPrefix').value = shift.seat_prefix || '';
            document.getElementById('seatCount').value = shift.seat_count || <?php echo $settings['default_seat_count'] ?? 72; ?>;
            document.getElementById('startTime').value = shift.start_time;
            document.getElementById('endTime').value = shift.end_time;
            
            document.getElementById('shiftFormTitle').textContent = 'Edit Shift';
            showAddShiftForm();
        }

        function deactivateShift(shiftId) {
            if (!confirm('Are you sure you want to deactivate this shift? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'deactivate_shift');
            formData.append('shift_id', shiftId);
            formData.append('csrf_token', csrfToken);

            fetch('admin-hall-shift-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Shift deactivated successfully', 'success');
                    loadShiftsByHall(document.getElementById('shiftHallSelector').value);
                    refreshDropdowns();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error deactivating shift:', error);
                showToast('Error deactivating shift', 'error');
            });
        }

        function submitShiftForm() {
            const form = document.getElementById('shiftForm');
            const formData = new FormData(form);
            
            const shiftId = document.getElementById('shiftId').value;
            const action = shiftId ? 'update_shift' : 'add_shift';
            formData.append('action', action);
            formData.append('csrf_token', csrfToken);

            fetch('admin-hall-shift-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    showShiftList();
                    loadShiftsByHall(document.getElementById('shiftHallSelector').value);
                    refreshDropdowns();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving shift:', error);
                showToast('Error saving shift', 'error');
            });
        }

        // ===== UTILITY FUNCTIONS =====
        
        function refreshDropdowns() {
            // Refresh halls dropdown
            fetch('admin-hall-shift-api.php?action=get_active_halls')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update main hall selector
                        const hallSelector = document.getElementById('hallSelector');
                        const currentValue = hallSelector.value;
                        hallSelector.innerHTML = '<option value="">Select a cinema hall</option>';
                        
                        data.halls.forEach(hall => {
                            const option = document.createElement('option');
                            option.value = hall.id;
                            option.textContent = hall.hall_name;
                            hallSelector.appendChild(option);
                        });
                        
                        if (currentValue) {
                            hallSelector.value = currentValue;
                        }

                        // Update shift hall selector
                        const shiftHallSelector = document.getElementById('shiftHallSelector');
                        const currentShiftHallValue = shiftHallSelector.value;
                        shiftHallSelector.innerHTML = '<option value="">Choose a hall...</option>';
                        
                        data.halls.forEach(hall => {
                            const option = document.createElement('option');
                            option.value = hall.id;
                            option.textContent = hall.hall_name;
                            shiftHallSelector.appendChild(option);
                        });
                        
                        if (currentShiftHallValue) {
                            shiftHallSelector.value = currentShiftHallValue;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing dropdowns:', error);
                });
        }

        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            // Add to page
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            toastContainer.appendChild(toast);
            
            // Show toast
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove after hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }


    </script>
</body>
</html> 