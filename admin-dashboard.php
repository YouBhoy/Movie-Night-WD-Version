<?php
require_once 'config.php';

// Strict admin authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Check session timeout
if (!isset($_SESSION['admin_login_time']) || (time() - $_SESSION['admin_login_time']) > SESSION_TIMEOUT) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
    header('Location: admin-login.php?timeout=1');
    exit;
}

// Refresh session time on activity
$_SESSION['admin_login_time'] = time();

// Handle logout
if (isset($_GET['logout'])) {
    // Log logout activity
    $pdo = getDBConnection();
    $logStmt = $pdo->prepare("
        INSERT INTO admin_activity_log (admin_user, action, ip_address, user_agent, created_at) 
        VALUES (?, 'logout', ?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['admin_user'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
    unset($_SESSION['admin_user']);
    header('Location: admin-login.php');
    exit;
}

// Helper function to validate integers
function validateInteger($value, $min = null, $max = null) {
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) {
        return false;
    }
    
    if ($min !== null && $int < $min) {
        return false;
    }
    
    if ($max !== null && $int > $max) {
        return false;
    }
    
    return $int;
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');
        
        // Log admin activity
        $logActivity = function($action, $targetType = null, $targetId = null, $details = null) use ($pdo) {
            $logStmt = $pdo->prepare("
                INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $_SESSION['admin_user'] ?? 'unknown',
                $action,
                $targetType,
                $targetId,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        };
        
        switch ($action) {
            case 'delete_registration':
                $regId = validateInteger($_POST['reg_id'] ?? 0, 1);
                
                if ($regId === false) {
                    $message = "Invalid registration ID";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Get registration details for logging
                    $regStmt = $pdo->prepare("SELECT emp_number, selected_seats, hall_id, shift_id FROM registrations WHERE id = ?");
                    $regStmt->execute([$regId]);
                    $registration = $regStmt->fetch();
                    
                    if ($registration) {
                        // Release seats back to available status
                        $seats = json_decode($registration['selected_seats'], true);
                        if (is_array($seats)) {
                            $seatUpdateStmt = $pdo->prepare("UPDATE seats SET status = 'available', updated_at = NOW() WHERE seat_number = ? AND hall_id = ? AND shift_id = ?");
                            
                            foreach ($seats as $seat) {
                                $seatUpdateStmt->execute([$seat, $registration['hall_id'], $registration['shift_id']]);
                            }
                        }
                        
                        // Delete registration
                        $deleteStmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
                        $deleteStmt->execute([$regId]);
                        
                        $pdo->commit();
                        
                        // Log activity
                        $logActivity('delete_registration', 'registration', $regId, [
                            'emp_number' => $registration['emp_number'],
                            'seats_released' => $seats
                        ]);
                        
                        $message = "Registration deleted successfully and seats released ✅";
                        $messageType = "success";
                    } else {
                        $pdo->rollBack();
                        $message = "Registration not found";
                        $messageType = "error";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error deleting registration: " . $e->getMessage();
                    $messageType = "error";
                    error_log("Delete registration error: " . $e->getMessage());
                }
                break;
                
            case 'update_event_settings':
                $movieName = sanitizeInput($_POST['movie_name'] ?? '');
                $movieDate = sanitizeInput($_POST['movie_date'] ?? '');
                $movieTime = sanitizeInput($_POST['movie_time'] ?? '');
                $movieLocation = sanitizeInput($_POST['movie_location'] ?? '');
                $eventDescription = sanitizeInput($_POST['event_description'] ?? '');
                $registrationEnabled = isset($_POST['registration_enabled']) ? 'true' : 'false';
                $allowSeatSeparation = isset($_POST['allow_seat_separation']) ? 'true' : 'false';
                
                try {
                    $updateStmt = $pdo->prepare("UPDATE event_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $updateStmt->execute([$movieName, 'movie_name']);
                    $updateStmt->execute([$movieDate, 'movie_date']);
                    $updateStmt->execute([$movieTime, 'movie_time']);
                    $updateStmt->execute([$movieLocation, 'movie_location']);
                    $updateStmt->execute([$eventDescription, 'event_description']);
                    $updateStmt->execute([$registrationEnabled, 'registration_enabled']);
                    $updateStmt->execute([$allowSeatSeparation, 'allow_seat_separation']);
                    
                    $logActivity('update_event_settings', 'settings', null, [
                        'movie_name' => $movieName,
                        'registration_enabled' => $registrationEnabled,
                        'allow_seat_separation' => $allowSeatSeparation
                    ]);
                    
                    $message = "Event settings updated successfully ✅";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating settings";
                    $messageType = "error";
                    error_log("Settings update error: " . $e->getMessage());
                }
                break;
                
            case 'update_color_scheme':
                $primaryColor = sanitizeInput($_POST['primary_color'] ?? '#FFD700');
                $secondaryColor = sanitizeInput($_POST['secondary_color'] ?? '#2E8BFF');
                
                try {
                    $updateStmt = $pdo->prepare("UPDATE event_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $updateStmt->execute([$primaryColor, 'primary_color']);
                    $updateStmt->execute([$secondaryColor, 'secondary_color']);
                    
                    $logActivity('update_color_scheme', 'settings', null, [
                        'primary_color' => $primaryColor,
                        'secondary_color' => $secondaryColor
                    ]);
                    
                    $message = "Color scheme updated successfully ✅";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating color scheme";
                    $messageType = "error";
                    error_log("Color scheme update error: " . $e->getMessage());
                }
                break;
                
            case 'update_hall':
                $hallId = validateInteger($_POST['hall_id'] ?? 0, 1);
                $hallName = sanitizeInput($_POST['hall_name'] ?? '');
                $maxAttendees = validateInteger($_POST['max_attendees'] ?? 0, 1, 3); // Enforce max 3
                
                if ($hallId === false || !$hallName || $maxAttendees === false) {
                    $message = "Invalid hall data. Maximum 3 attendees allowed per hall.";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE cinema_halls SET hall_name = ?, max_attendees_per_booking = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hallName, $maxAttendees, $hallId]);
                    
                    $logActivity('update_hall', 'hall', $hallId, ['hall_name' => $hallName, 'max_attendees' => $maxAttendees]);
                    
                    $message = "Cinema hall updated successfully ✅";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating cinema hall";
                    $messageType = "error";
                    error_log("Hall update error: " . $e->getMessage());
                }
                break;
                
            case 'update_shift':
                $shiftId = validateInteger($_POST['shift_id'] ?? 0, 1);
                $shiftName = sanitizeInput($_POST['shift_name'] ?? '');
                
                if ($shiftId === false || !$shiftName) {
                    $message = "Invalid shift data";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE shifts SET shift_name = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$shiftName, $shiftId]);
                    
                    $logActivity('update_shift', 'shift', $shiftId, ['shift_name' => $shiftName]);
                    
                    $message = "Shift updated successfully ✅";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating shift";
                    $messageType = "error";
                    error_log("Shift update error: " . $e->getMessage());
                }
                break;
                
            case 'regenerate_seats':
                $hallId = validateInteger($_POST['hall_id'] ?? 0, 1);
                $shiftId = validateInteger($_POST['shift_id'] ?? 0, 1);
                $rows = validateInteger($_POST['rows'] ?? 0, 1, 20);
                $seatsPerRow = validateInteger($_POST['seats_per_row'] ?? 0, 1, 50);
                
                if ($hallId === false || $shiftId === false || $rows === false || $seatsPerRow === false) {
                    $message = "Invalid seating configuration";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Delete existing seats for this hall and shift
                    $deleteStmt = $pdo->prepare("DELETE FROM seats WHERE hall_id = ? AND shift_id = ?");
                    $deleteStmt->execute([$hallId, $shiftId]);
                    
                    // Generate new seats
                    $insertStmt = $pdo->prepare("
                        INSERT INTO seats (hall_id, shift_id, seat_number, row_letter, seat_position, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'available', NOW())
                    ");
                    
                    for ($row = 0; $row < $rows; $row++) {
                        $rowLetter = chr(65 + $row); // A, B, C, etc.
                        for ($seat = 1; $seat <= $seatsPerRow; $seat++) {
                            $seatNumber = $rowLetter . $seat;
                            $insertStmt->execute([$hallId, $shiftId, $seatNumber, $rowLetter, $seat]);
                        }
                    }
                    
                    // Update seat count in shifts table
                    $totalSeats = $rows * $seatsPerRow;
                    $updateShiftStmt = $pdo->prepare("UPDATE shifts SET seat_count = ? WHERE id = ?");
                    $updateShiftStmt->execute([$totalSeats, $shiftId]);
                    
                    $pdo->commit();
                    
                    $logActivity('regenerate_seats', 'seating', null, [
                        'hall_id' => $hallId,
                        'shift_id' => $shiftId,
                        'rows' => $rows,
                        'seats_per_row' => $seatsPerRow,
                        'total_seats' => $totalSeats
                    ]);
                    
                    $message = "Seating plan regenerated successfully ✅ ($totalSeats seats created)";
                    $messageType = "success";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error regenerating seats: " . $e->getMessage();
                    $messageType = "error";
                    error_log("Seat regeneration error: " . $e->getMessage());
                }
                break;
        }
    }
}

// Get statistics
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_registrations,
        SUM(attendee_count) as total_attendees,
        (SELECT COUNT(*) FROM seats WHERE status = 'available') as available_seats,
        (SELECT COUNT(*) FROM seats WHERE status = 'blocked') as blocked_seats,
        (SELECT COUNT(*) FROM seats WHERE status = 'reserved') as reserved_seats,
        (SELECT COUNT(*) FROM seats WHERE status = 'occupied') as occupied_seats
    FROM registrations WHERE status = 'active'
");
$stats = $statsStmt->fetch();

// Get event settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get cinema halls with current limits
$hallsStmt = $pdo->query("SELECT * FROM cinema_halls WHERE is_active = 1 ORDER BY id");
$halls = $hallsStmt->fetchAll();

// Get shifts with hall names
$shiftsStmt = $pdo->query("
    SELECT s.*, h.hall_name, h.max_attendees_per_booking 
    FROM shifts s 
    LEFT JOIN cinema_halls h ON s.hall_id = h.id 
    WHERE s.is_active = 1 
    ORDER BY s.id
");
$shifts = $shiftsStmt->fetchAll();

// Get recent activity
$activityStmt = $pdo->query("
    SELECT action, target_type, created_at, details 
    FROM admin_activity_log 
    ORDER BY created_at DESC 
    LIMIT 10
");
$recentActivity = $activityStmt->fetchAll();

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WD Movie Night</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #06b6d4;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-header {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .admin-title h1 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .admin-title p {
            color: var(--text-secondary);
        }

        .admin-nav {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: var(--bg-secondary);
            padding: 0.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
            border: 1px solid var(--border-color);
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            color: var(--text-secondary);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: var(--bg-primary);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-card {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .section-card h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        /* Color Scheme Selector */
        .color-scheme-section {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .color-schemes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .color-scheme {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
        }

        .color-scheme:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .color-scheme.active {
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.1);
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .scheme-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check input[type="checkbox"] {
            width: auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: var(--bg-primary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .table tr:hover {
            background: var(--bg-primary);
        }

        /* Activity Log */
        .activity-item {
            padding: 1rem;
            border-left: 4px solid var(--primary-color);
            background: var(--bg-primary);
            margin-bottom: 0.5rem;
            border-radius: 0 8px 8px 0;
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }

            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .admin-nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tab-navigation {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .color-schemes {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-title">
                <h1>🎬 Movie Night Admin Dashboard</h1>
                <p>Western Digital Event Management System</p>
            </div>
            <div class="admin-nav">
                <a href="index.php" class="btn btn-secondary">🎟️ Registration Form</a>
                <a href="export.php" class="btn btn-primary">📊 Export Data</a>
                <a href="?logout=1" class="btn btn-danger">🚪 Logout</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['total_registrations']; ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $stats['total_attendees']; ?></div>
                <div class="stat-label">Total Attendees</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💺</div>
                <div class="stat-number"><?php echo $stats['available_seats']; ?></div>
                <div class="stat-label">Available Seats</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚫</div>
                <div class="stat-number"><?php echo $stats['blocked_seats'] + $stats['reserved_seats']; ?></div>
                <div class="stat-label">Blocked/Reserved</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="showTab('registrations')">🔍 Registrations</button>
            <button class="tab-btn" onclick="showTab('settings')">⚙️ Event Settings</button>
            <button class="tab-btn" onclick="showTab('appearance')">🎨 Appearance</button>
            <button class="tab-btn" onclick="showTab('halls')">🏛️ Cinema Halls</button>
            <button class="tab-btn" onclick="showTab('shifts')">🕐 Shifts</button>
            <button class="tab-btn" onclick="showTab('seating')">🪑 Seating Plans</button>
            <button class="tab-btn" onclick="showTab('activity')">📋 Activity Log</button>
        </div>

        <!-- Registrations Tab -->
        <div id="registrations-tab" class="tab-content active">
            <div class="section-card">
                <h2>🔍 Registration Management</h2>
                
                <div style="margin-bottom: 1.5rem;">
                    <input type="text" id="searchInput" placeholder="Search by name or employee number..." 
                           style="width: 100%; max-width: 400px; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: 12px;">
                    <button onclick="searchRegistrations()" class="btn btn-primary" style="margin-left: 1rem;">Search</button>
                </div>

                <div id="registrationsContainer">
                    <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        Loading registrations...
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="section-card">
                <h2>⚙️ Event Settings</h2>
                
                <form method="POST" style="max-width: 600px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_event_settings">
                    
                    <div class="form-group">
                        <label for="movie_name" class="form-label">🎬 Movie Name</label>
                        <input type="text" id="movie_name" name="movie_name" class="form-control"
                               value="<?php echo htmlspecialchars($settings['movie_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="movie_date" class="form-label">📅 Movie Date</label>
                        <input type="text" id="movie_date" name="movie_date" class="form-control"
                               value="<?php echo htmlspecialchars($settings['movie_date'] ?? ''); ?>" 
                               placeholder="e.g., Friday, 16 May 2025" required>
                    </div>

                    <div class="form-group">
                        <label for="movie_time" class="form-label">🕐 Movie Time</label>
                        <input type="text" id="movie_time" name="movie_time" class="form-control"
                               value="<?php echo htmlspecialchars($settings['movie_time'] ?? ''); ?>" 
                               placeholder="e.g., 8:30 PM" required>
                    </div>

                    <div class="form-group">
                        <label for="movie_location" class="form-label">📍 Movie Location</label>
                        <input type="text" id="movie_location" name="movie_location" class="form-control"
                               value="<?php echo htmlspecialchars($settings['movie_location'] ?? ''); ?>" 
                               placeholder="e.g., WD Campus Cinema Complex" required>
                    </div>

                    <div class="form-group">
                        <label for="event_description" class="form-label">📝 Event Description</label>
                        <textarea id="event_description" name="event_description" class="form-control" rows="3"><?php echo htmlspecialchars($settings['event_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="registration_enabled" name="registration_enabled" 
                                   <?php echo ($settings['registration_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                            <label for="registration_enabled" class="form-label">🎟️ Enable Registration</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="allow_seat_separation" name="allow_seat_separation" 
                                   <?php echo ($settings['allow_seat_separation'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                            <label for="allow_seat_separation" class="form-label">🪑 Allow Seat Separation</label>
                            <small style="display: block; color: var(--text-secondary); margin-top: 0.5rem;">
                                When disabled, users must select consecutive seats
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">💾 Save Settings</button>
                </form>
            </div>
        </div>

        <!-- Appearance Tab -->
        <div id="appearance-tab" class="tab-content">
            <div class="color-scheme-section">
                <h2>🎨 Color Themes</h2>
                <p>Choose a color scheme for the registration website</p>
                
                <form method="POST" id="colorSchemeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_color_scheme">
                    <input type="hidden" name="primary_color" id="selected_primary">
                    <input type="hidden" name="secondary_color" id="selected_secondary">
                    
                    <div class="color-schemes">
                        <div class="color-scheme active" data-primary="#FFD700" data-secondary="#2E8BFF">
                            <div class="color-preview" style="background: linear-gradient(45deg, #FFD700, #2E8BFF);"></div>
                            <div>
                                <div class="scheme-name">Gold & Blue</div>
                                <small style="color: var(--text-secondary);">Classic & Professional</small>
                            </div>
                        </div>
                        
                        <div class="color-scheme" data-primary="#8B5CF6" data-secondary="#EC4899">
                            <div class="color-preview" style="background: linear-gradient(45deg, #8B5CF6, #EC4899);"></div>
                            <div>
                                <div class="scheme-name">Purple & Pink</div>
                                <small style="color: var(--text-secondary);">Modern & Vibrant</small>
                            </div>
                        </div>
                        
                        <div class="color-scheme" data-primary="#10B981" data-secondary="#06B6D4">
                            <div class="color-preview" style="background: linear-gradient(45deg, #10B981, #06B6D4);"></div>
                            <div>
                                <div class="scheme-name">Green & Teal</div>
                                <small style="color: var(--text-secondary);">Fresh & Natural</small>
                            </div>
                        </div>
                        
                        <div class="color-scheme" data-primary="#F59E0B" data-secondary="#EF4444">
                            <div class="color-preview" style="background: linear-gradient(45deg, #F59E0B, #EF4444);"></div>
                            <div>
                                <div class="scheme-name">Orange & Red</div>
                                <small style="color: var(--text-secondary);">Warm & Energetic</small>
                            </div>
                        </div>
                        
                        <div class="color-scheme" data-primary="#6366F1" data-secondary="#22D3EE">
                            <div class="color-preview" style="background: linear-gradient(45deg, #6366F1, #22D3EE);"></div>
                            <div>
                                <div class="scheme-name">Indigo & Cyan</div>
                                <small style="color: var(--text-secondary);">Cool & Tech</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">🎨 Apply Color Scheme</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cinema Halls Tab -->
        <div id="halls-tab" class="tab-content">
            <div class="section-card">
                <h2>🏛️ Cinema Halls Management</h2>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hall ID</th>
                                <th>Hall Name</th>
                                <th>Max Attendees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($halls as $hall): ?>
                            <tr>
                                <td><?php echo $hall['id']; ?></td>
                                <td>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="update_hall">
                                        <input type="hidden" name="hall_id" value="<?php echo $hall['id']; ?>">
                                        <input type="text" name="hall_name" value="<?php echo htmlspecialchars($hall['hall_name']); ?>" 
                                               style="border: 1px solid var(--border-color); padding: 0.25rem; border-radius: 4px;">
                                </td>
                                <td>
                                    <input type="number" name="max_attendees" value="<?php echo $hall['max_attendees_per_booking']; ?>" 
                                           min="1" max="3" style="border: 1px solid var(--border-color); padding: 0.25rem; border-radius: 4px; width: 80px;">
                                    <small style="display: block; color: var(--text-secondary); font-size: 0.8rem;">Max: 3</small>
                                </td>
                                <td>
                                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Shifts Tab -->
        <div id="shifts-tab" class="tab-content">
            <div class="section-card">
                <h2>🕐 Shifts Management</h2>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Shift ID</th>
                                <th>Shift Name</th>
                                <th>Cinema Hall</th>
                                <th>Seat Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo $shift['id']; ?></td>
                                <td>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="update_shift">
                                        <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                                        <input type="text" name="shift_name" value="<?php echo htmlspecialchars($shift['shift_name']); ?>" 
                                               style="border: 1px solid var(--border-color); padding: 0.25rem; border-radius: 4px; min-width: 200px;">
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($shift['hall_name'] ?? 'Unknown'); ?>
                                    <small style="display: block; color: var(--text-secondary);">
                                        Max <?php echo $shift['max_attendees_per_booking'] ?? 3; ?> attendees
                                    </small>
                                </td>
                                <td><?php echo $shift['seat_count'] ?? 0; ?></td>
                                <td>
                                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Seating Plans Tab -->
        <div id="seating-tab" class="tab-content">
            <div class="section-card">
                <h2>🪑 Seating Plan Generator</h2>
                <p>Generate new seating arrangements for cinema halls and shifts.</p>
                
                <form method="POST" style="max-width: 600px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="regenerate_seats">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="seating_hall_id" class="form-label">Cinema Hall</label>
                            <select id="seating_hall_id" name="hall_id" class="form-control" required>
                                <option value="">Select Hall</option>
                                <?php foreach ($halls as $hall): ?>
                                <option value="<?php echo $hall['id']; ?>"><?php echo htmlspecialchars($hall['hall_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="seating_shift_id" class="form-label">Shift</label>
                            <select id="seating_shift_id" name="shift_id" class="form-control" required>
                                <option value="">Select Shift</option>
                                <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>" data-hall-id="<?php echo $shift['hall_id']; ?>">
                                    <?php echo htmlspecialchars($shift['shift_name']); ?> (<?php echo htmlspecialchars($shift['hall_name'] ?? 'Unknown'); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rows" class="form-label">Number of Rows</label>
                            <input type="number" id="rows" name="rows" class="form-control" min="1" max="20" value="8" required>
                            <small class="form-text text-muted">Maximum 20 rows (A-T)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="seats_per_row" class="form-label">Seats per Row</label>
                            <input type="number" id="seats_per_row" name="seats_per_row" class="form-control" min="1" max="50" value="10" required>
                            <small class="form-text text-muted">Maximum 50 seats per row</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div style="padding: 1rem; background: var(--bg-primary); border-radius: 8px; margin-bottom: 1rem;">
                            <strong>Preview:</strong> <span id="seatPreview">8 rows × 10 seats = 80 total seats</span>
                        </div>
                    </div>
                    
                    <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--danger-color); margin-bottom: 1rem;">
                        <strong>⚠️ Warning:</strong> This will delete all existing seats for the selected hall and shift, including any reservations!
                    </div>
                    
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure? This will delete all existing seats and reservations for this hall and shift!')">
                        🔄 Regenerate Seating Plan
                    </button>
                </form>
            </div>
        </div>

        <!-- Activity Log Tab -->
        <div id="activity-tab" class="tab-content">
            <div class="section-card">
                <h2>📋 Recent Admin Activity</h2>
                
                <?php if (empty($recentActivity)): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                        No recent activity to display.
                    </p>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action']))); ?></strong>
                            <?php if ($activity['target_type']): ?>
                                on <?php echo htmlspecialchars($activity['target_type']); ?>
                            <?php endif; ?>
                            <div class="activity-time">
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
            
            // Load content based on tab
            if (tabName === 'registrations') {
                loadRegistrations();
            }
        }

        // Color scheme functionality
        document.querySelectorAll('.color-scheme').forEach(scheme => {
            scheme.addEventListener('click', function() {
                // Remove active class from all schemes
                document.querySelectorAll('.color-scheme').forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked scheme
                this.classList.add('active');
                
                // Set hidden form values
                document.getElementById('selected_primary').value = this.dataset.primary;
                document.getElementById('selected_secondary').value = this.dataset.secondary;
            });
        });

        // Set initial color scheme values
        const activeScheme = document.querySelector('.color-scheme.active');
        if (activeScheme) {
            document.getElementById('selected_primary').value = activeScheme.dataset.primary;
            document.getElementById('selected_secondary').value = activeScheme.dataset.secondary;
        }

        // Seating plan preview
        document.getElementById('rows').addEventListener('input', updateSeatPreview);
        document.getElementById('seats_per_row').addEventListener('input', updateSeatPreview);
        
        function updateSeatPreview() {
            const rows = parseInt(document.getElementById('rows').value) || 0;
            const seatsPerRow = parseInt(document.getElementById('seats_per_row').value) || 0;
            const total = rows * seatsPerRow;
            document.getElementById('seatPreview').textContent = `${rows} rows × ${seatsPerRow} seats = ${total} total seats`;
        }

        // Shift filtering for seating plan
        document.getElementById('seating_hall_id').addEventListener('change', function() {
            const hallId = this.value;
            const shiftSelect = document.getElementById('seating_shift_id');
            const options = shiftSelect.querySelectorAll('option[data-hall-id]');
            
            options.forEach(option => {
                if (hallId === '' || option.dataset.hallId === hallId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            shiftSelect.value = '';
        });

        // Registration management
        async function loadRegistrations() {
            try {
                const response = await fetch('admin-api.php?action=get_registrations');
                const data = await response.json();
                renderRegistrationsTable(data);
            } catch (error) {
                console.error('Error loading registrations:', error);
                document.getElementById('registrationsContainer').innerHTML = 
                    '<p style="color: var(--danger-color); text-align: center;">Error loading registrations.</p>';
            }
        }

        async function searchRegistrations() {
            const search = document.getElementById('searchInput').value;
            
            try {
                const response = await fetch(`admin-api.php?action=search_registrations&search=${encodeURIComponent(search)}`);
                const data = await response.json();
                renderRegistrationsTable(data);
            } catch (error) {
                console.error('Search error:', error);
            }
        }

        function renderRegistrationsTable(data) {
            const container = document.getElementById('registrationsContainer');
            
            if (!data.registrations || data.registrations.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">No registrations found.</p>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table"><thead><tr>';
            html += '<th>Employee #</th><th>Name</th><th>Attendees</th><th>Hall</th><th>Shift</th><th>Seats</th><th>Date</th><th>Actions</th>';
            html += '</tr></thead><tbody>';
            
            data.registrations.forEach(reg => {
                const seats = JSON.parse(reg.selected_seats).join(', ');
                const date = new Date(reg.registration_date).toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric', 
                    hour: '2-digit', minute: '2-digit'
                });
                
                html += `<tr>
                    <td>${escapeHtml(reg.emp_number)}</td>
                    <td>${escapeHtml(reg.staff_name)}</td>
                    <td>${reg.attendee_count}</td>
                    <td>${escapeHtml(reg.hall_name)}</td>
                    <td>${escapeHtml(reg.shift_name)}</td>
                    <td>${seats}</td>
                    <td>${date}</td>
                    <td><button onclick="deleteRegistration(${reg.id})" class="btn btn-danger btn-sm">🗑 Delete</button></td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        async function deleteRegistration(regId) {
            if (!confirm('Are you sure you want to delete this registration? This will release the reserved seats.')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_registration');
                formData.append('reg_id', regId);
                formData.append('csrf_token', '<?php echo $csrfToken; ?>');
                
                const response = await fetch('admin-dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    showToast('Registration deleted successfully ✅', 'success');
                    loadRegistrations();
                } else {
                    showToast('Error deleting registration', 'error');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showToast('Error deleting registration', 'error');
            }
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed; top: 20px; right: 20px; z-index: 10000;
                padding: 1rem 1.5rem; border-radius: 12px; color: white; font-weight: 600;
                background: ${type === 'success' ? 'var(--success-color)' : 'var(--danger-color)'};
                transform: translateX(400px); transition: transform 0.3s ease;
            `;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        // Auto-search on input
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                if (this.value.length >= 2 || this.value.length === 0) {
                    searchRegistrations();
                }
            }, 500);
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadRegistrations();
        });
    </script>
</body>
</html>
