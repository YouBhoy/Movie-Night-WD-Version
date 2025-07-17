<?php
require_once 'config.php';

// Simple admin authentication
session_start();

// Hardcoded admin credentials (simple and reliable)
$admin_username = 'admin';
$admin_password = 'admin123';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // User is logged in, show dashboard
    $logged_in = true;
} else {
    $logged_in = false;
    $error = '';
    
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($username === $admin_username && $password === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle AJAX actions
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection();
        
        switch ($_POST['action']) {
            case 'delete_registration':
                $regId = (int)($_POST['reg_id'] ?? 0);
                if ($regId > 0) {
                    // Get registration details first
                    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
                    $stmt->execute([$regId]);
                    $registration = $stmt->fetch();
                    
                    if ($registration) {
                        // Delete the registration
                        $deleteStmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
                        $deleteStmt->execute([$regId]);
                        
                        echo json_encode(['success' => true, 'message' => 'Registration deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Registration not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid registration ID']);
                }
                exit;
                
            case 'update_event_setting':
                $setting_key = $_POST['setting_key'] ?? '';
                $setting_value = $_POST['setting_value'] ?? '';
                
                if (empty($setting_key)) {
                    echo json_encode(['success' => false, 'message' => 'Setting key is required']);
                    exit;
                }
                
                // Update or insert single setting
                $stmt = $pdo->prepare("INSERT INTO event_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$setting_key, $setting_value, $setting_value]);
                
                echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
                exit;
                
            case 'add_employee':
                $emp_number = $_POST['emp_number'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $shift_id = $_POST['shift_id'] ?? '';
                if (empty($emp_number) || empty($full_name) || empty($shift_id)) {
                    echo json_encode(['success' => false, 'message' => 'Employee number, name, and shift are required']);
                    exit;
                }
                
                // Check if employee already exists
                $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE emp_number = ?");
                $checkStmt->execute([$emp_number]);
                
                if ($checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Employee number already exists']);
                    exit;
                }
                
                // Insert new employee with is_active = 1
                $stmt = $pdo->prepare("INSERT INTO employees (emp_number, full_name, shift_id, is_active) VALUES (?, ?, ?, 1)");
                $stmt->execute([$emp_number, $full_name, $shift_id]);
                
                echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
                exit;
                
            case 'delete_employee':
                $emp_id = (int)($_POST['emp_id'] ?? 0);
                
                if ($emp_id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $stmt->execute([$emp_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                }
                exit;
                
            case 'toggle_employee_status':
                $emp_id = (int)($_POST['emp_id'] ?? 0);
                $current_status = (int)($_POST['current_status'] ?? 0);
                
                if ($emp_id > 0) {
                    $new_status = $current_status == 1 ? 0 : 1;
                    $stmt = $pdo->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $emp_id]);
                    
                    $status_text = $new_status == 1 ? 'activated' : 'deactivated';
                    echo json_encode(['success' => true, 'message' => 'Employee ' . $status_text . ' successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                }
                exit;
                
            case 'update_seat_status':
                $seatId = (int)($_POST['seat_id'] ?? 0);
                $status = $_POST['status'] ?? 'available';
                
                if ($seatId > 0) {
                    $stmt = $pdo->prepare("UPDATE seats SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $seatId]);
                    
                    echo json_encode(['success' => true, 'message' => 'Seat status updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid seat ID']);
                }
                exit;
            case 'update_shift_name':
                $shift_id = (int)($_POST['shift_id'] ?? 0);
                $shift_name = trim($_POST['shift_name'] ?? '');
                if ($shift_id > 0 && $shift_name !== '') {
                    $stmt = $pdo->prepare("UPDATE shifts SET shift_name = ? WHERE id = ?");
                    $stmt->execute([$shift_name, $shift_id]);
                    echo json_encode(['success' => true, 'message' => 'Shift name updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid shift ID or name']);
                }
                exit;
            case 'update_hall_name':
                $hall_id = (int)($_POST['hall_id'] ?? 0);
                $hall_name = trim($_POST['hall_name'] ?? '');
                if ($hall_id > 0 && $hall_name !== '') {
                    $stmt = $pdo->prepare("UPDATE cinema_halls SET hall_name = ? WHERE id = ?");
                    $stmt->execute([$hall_name, $hall_id]);
                    echo json_encode(['success' => true, 'message' => 'Hall name updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid hall ID or name']);
                }
                exit;
            case 'deactivate_employee':
                $emp_number = $_POST['emp_number'] ?? '';
                if (empty($emp_number)) {
                    echo json_encode(['success' => false, 'message' => 'Employee number required']);
                    exit;
                }
                // Find active registration for this employee
                $stmt = $pdo->prepare("SELECT id FROM registrations WHERE emp_number = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$emp_number]);
                $reg = $stmt->fetch();
                if ($reg) {
                    // Free seats and cancel registration
                    $reg_id = $reg['id'];
                    $pdo->query("SET @reg_id = " . intval($reg_id));
                    $pdo->query("CALL freeSeatsByRegistration(@reg_id)");
                }
                // Deactivate employee
                $updateStmt = $pdo->prepare("UPDATE employees SET is_active = 0 WHERE emp_number = ?");
                $updateStmt->execute([$emp_number]);
                echo json_encode(['success' => true, 'message' => 'Employee deactivated and seat freed']);
                exit;
            case 'activate_employee':
                $emp_number = $_POST['emp_number'] ?? '';
                if (empty($emp_number)) {
                    echo json_encode(['success' => false, 'message' => 'Employee number required']);
                    exit;
                }
                $updateStmt = $pdo->prepare("UPDATE employees SET is_active = 1 WHERE emp_number = ?");
                $updateStmt->execute([$emp_number]);
                echo json_encode(['success' => true, 'message' => 'Employee activated']);
                exit;
            case 'update_employee':
                $id = (int)($_POST['id'] ?? 0);
                $emp_number = trim($_POST['emp_number'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $shift_id = (int)($_POST['shift_id'] ?? 0);
                if ($id <= 0 || !$emp_number || !$full_name || $shift_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit;
                }
                // Check for unique employee number (exclude current employee)
                $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE emp_number = ? AND id != ?");
                $checkStmt->execute([$emp_number, $id]);
                if ($checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Employee number already exists']);
                    exit;
                }
                // Get current employee info
                $empStmt = $pdo->prepare("SELECT emp_number, shift_id FROM employees WHERE id = ?");
                $empStmt->execute([$id]);
                $currentEmp = $empStmt->fetch();
                $registrationCancelled = false;
                if ($currentEmp) {
                    $old_emp_number = $currentEmp['emp_number'];
                    $old_shift_id = $currentEmp['shift_id'];
                    // Check for active registration
                    $regStmt = $pdo->prepare("SELECT id FROM registrations WHERE emp_number = ? AND status = 'active' LIMIT 1");
                    $regStmt->execute([$old_emp_number]);
                    $reg = $regStmt->fetch();
                    if ($reg) {
                        $reg_id = $reg['id'];
                        // If shift is changing or emp_number is changing, cancel registration and free seats
                        if ($shift_id != $old_shift_id || $emp_number !== $old_emp_number) {
                            $pdo->query("SET @reg_id = " . intval($reg_id));
                            $pdo->query("CALL freeSeatsByRegistration(@reg_id)");
                            $registrationCancelled = true;
                        }
                    }
                }
                $stmt = $pdo->prepare("UPDATE employees SET emp_number = ?, full_name = ?, shift_id = ? WHERE id = ?");
                $stmt->execute([$emp_number, $full_name, $shift_id, $id]);
                $msg = 'Employee updated';
                if ($registrationCancelled) {
                    $msg .= '. Registration cancelled and seat(s) freed.';
                }
                echo json_encode(['success' => true, 'message' => $msg, 'registration_cancelled' => $registrationCancelled]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Helper function to convert hex to RGB
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

// Get database connection
try {
    $pdo = getDBConnection();
    
    // Get event settings
    $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
    $settingsStmt->execute();
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get halls data for JavaScript
    $halls = $pdo->query("SELECT id, hall_name FROM cinema_halls ORDER BY id")->fetchAll();
    // Fetch all active shifts for the employee form dropdown
    $shifts = $pdo->query("SELECT id, shift_name, hall_id FROM shifts WHERE is_active = 1 ORDER BY id")->fetchAll();
} catch (Exception $e) {
    $db_error = 'Database connection failed: ' . $e->getMessage();
    $halls = [];
    $settings = [];
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WD Movie Night</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --background: #121212;
            --card: #1E1E1E;
            --primary-color: #E50914;
            --secondary-color: #FFD700;
            --text-primary: #FFFFFF;
            --text-muted: #B0B0B0;
            --border: #333333;
            --hover: #292929;
            --success: #00C853;
            --danger: #D32F2F;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 2rem 0 1rem 0;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
        }
        
        .header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .logout-link, .back-to-registration-link {
            position: absolute;
            top: 0;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .logout-link { right: 0; }
        
        .back-to-registration-link { left: 0; }
        
        .back-to-dashboard-link {
            position: absolute;
            top: 0;
            left: 180px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .back-to-dashboard-link:hover {
            color: var(--secondary-color);
            background: var(--hover);
        }
        
        /* Login Form */
        .login-container {
            max-width: 400px;
            margin: 4rem auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label, .form-label {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--background);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.1);
        }
        
        .btn {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
            color: #000;
        }
        
        .btn-secondary {
            background: var(--hover);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--card);
        }
        
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
        }
        
        .btn-warning {
            background: var(--secondary-color);
            color: #000;
        }
        
        .btn-warning:hover {
            background: #bfa100;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: rgba(0, 200, 83, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 200, 83, 0.3);
        }
        
        .status-danger {
            background: rgba(211, 47, 47, 0.2);
            color: var(--danger);
            border: 1px solid rgba(211, 47, 47, 0.3);
        }
        
        .error {
            background: rgba(211, 47, 47, 0.1);
            border: 1px solid rgba(211, 47, 47, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        
        .nav-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .nav-tab:hover {
            color: var(--secondary-color);
            background: var(--hover);
        }
        
        .nav-tab.active {
            background: var(--secondary-color);
            color: #000;
        }
        
        /* Dashboard */
        .dashboard {
            display: grid;
            gap: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            text-align: center;
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2);
        }
        
        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        /* Tables */
        .data-table {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2);
        }
        
        .table-header {
            background: var(--hover);
            padding: 1rem;
            font-weight: 600;
            color: var(--secondary-color);
            border-radius: 8px 8px 0 0;
        }
        
        .table-content {
            color: var(--text-primary);
        }
        
        /* Search and Filters */
        .search-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--background);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(239, 215, 74, 0.1);
        }
        
        /* Seat Layout */
        .seat-layout {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 0.5rem;
            margin: 2rem 0;
        }
        
        .seat {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .seat.available {
            background: var(--success);
            color: #fff;
        }
        
        .seat.occupied {
            background: var(--danger);
            color: #fff;
        }
        
        .seat.blocked {
            background: var(--text-muted);
            color: #fff;
        }
        
        .seat.reserved {
            background: var(--secondary-color);
            color: #000;
        }
        
        .seat:hover {
            transform: scale(1.1);
            border-color: var(--secondary-color);
        }
        
        /* Forms */
        .form-section {
            background: var(--card);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 500;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            background: var(--hover);
            border: 1px solid var(--border);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: var(--success);
        }
        
        .toast.error {
            background: var(--danger);
        }
        
        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--secondary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .table-row, .data-table .table-row {
                grid-template-columns: 1fr 1fr 1fr 1fr;
            }
            .table-row > div {
                padding: 0.25rem 0;
            }
            .actions {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }
            .nav-tabs, .tab-list {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            .seat-grid {
                grid-template-columns: repeat(5, 1fr);
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
                padding: 1rem 0.5rem 0.5rem 0.5rem !important;
            }
            .admin-header nav {
                width: 100%;
                flex-direction: column;
                gap: 1rem;
            }
            .tab-list {
                width: 100%;
                flex-direction: column;
                gap: 0.5rem;
            }
            .tab-item a {
                width: 100%;
                display: block;
                text-align: left;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 0.5rem;
            }
            .header, .admin-header {
                padding: 0.5rem 0.25rem 0.5rem 0.25rem !important;
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start !important;
            }
            .header-title {
                font-size: 1.2rem !important;
            }
            .tab-list {
                flex-direction: column;
                gap: 0.25rem;
                width: 100%;
            }
            .actions {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .data-table, .form-section, .stat-card, .info-card {
                padding: 0.5rem;
            }
            .data-table {
                overflow-x: auto;
            }
            .table-header, .table-content {
                font-size: 0.9rem;
            }
            .btn, .btn-primary, .btn-secondary, .btn-danger, .btn-warning {
                font-size: 1rem;
                padding: 0.7rem 1rem;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($logged_in): ?>
            <!-- Header with navigation -->
            <header class="admin-header" style="background: var(--card); color: var(--text-primary); padding: 1.5rem 2rem 1rem 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2); border-bottom: 1px solid var(--border);">
                <div class="header-title" style="font-size: 2rem; font-weight: 700; letter-spacing: 2px; color: var(--secondary-color); display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-film"></i> Admin Panel
                </div>
                <nav style="display: flex; align-items: center; gap: 2rem;">
                    <ul class="tab-list" style="display: flex; gap: 1.5rem; list-style: none; margin: 0; padding: 0;">
                        <li class="tab-item<?php if ($current_tab === 'dashboard') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=dashboard" style="color: <?php echo $current_tab === 'dashboard' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'dashboard' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Dashboard</a>
                        </li>
                        <li class="tab-item<?php if ($current_tab === 'settings') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=settings" style="color: <?php echo $current_tab === 'settings' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'settings' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Event Settings</a>
                        </li>
                        <li class="tab-item<?php if ($current_tab === 'employees') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=employees" style="color: <?php echo $current_tab === 'employees' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'employees' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Employee Settings</a>
                        </li>
                        <li class="tab-item<?php if ($current_tab === 'export') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=export" style="color: <?php echo $current_tab === 'export' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'export' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Export</a>
                        </li>
                    </ul>
                    <div style="display: flex; gap: 0.75rem; margin-left: 2rem;">
                        <a href="index.php" class="btn btn-secondary" style="background: var(--hover); color: var(--text-primary); border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600; text-decoration: none;">Back to Registration</a>
                        <a href="admin-dashboard.php" class="btn btn-secondary" style="background: var(--hover); color: var(--text-primary); border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600; text-decoration: none;">Back to Dashboard</a>
                    </div>
                </nav>
            </header>
            <!-- End Header -->
            
            <a href="?logout=1" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            
            <div class="header">
                <h1><i class="fas fa-film"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
            </div>
            
            <?php if (isset($db_error)): ?>
                <div class="error"><?php echo htmlspecialchars($db_error); ?></div>
            <?php else: ?>
                <!-- Tab Content -->
                <?php if ($current_tab === 'dashboard'): ?>
                    <!-- Dashboard Tab -->
                    <div class="dashboard">
                        <!-- Live Statistics -->
                        <div class="stats-grid">
                            <?php
                            // Check if registrations table exists
                            $tableExists = false;
                            try {
                                $checkStmt = $pdo->query("SHOW TABLES LIKE 'registrations'");
                                $tableExists = $checkStmt->rowCount() > 0;
                            } catch (Exception $e) {
                                $tableExists = false;
                            }
                            
                            if ($tableExists) {
                                try {
                                    $totalStmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'active'");
                                    $totalRegistrations = $totalStmt->fetchColumn();
                                    
                                    $todayStmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE() AND status = 'active'");
                                    $todayRegistrations = $todayStmt->fetchColumn();
                                    
                                                            $hall1Count = 0; // Hall assignment not available
                        $hall2Count = 0; // Hall assignment not available
                                } catch (Exception $e) {
                                    $totalRegistrations = 0;
                                    $todayRegistrations = 0;
                                    $hall1Count = 0;
                                    $hall2Count = 0;
                                }
                            } else {
                                $totalRegistrations = 0;
                                $todayRegistrations = 0;
                                $hall1Count = 0;
                                $hall2Count = 0;
                            }
                            ?>
                            
                            <div class="stat-card">
                                <h3><i class="fas fa-users"></i> Total Registrations</h3>
                                <div class="number"><?php echo $totalRegistrations; ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <h3><i class="fas fa-calendar-day"></i> Today's Registrations</h3>
                                <div class="number"><?php echo $todayRegistrations; ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <h3><i class="fas fa-building"></i> Hall 1</h3>
                                <div class="number"><?php echo $hall1Count; ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <h3><i class="fas fa-building"></i> Hall 2</h3>
                                <div class="number"><?php echo $hall2Count; ?></div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="actions">
                            <a href="?tab=registrations" class="btn btn-primary">
                                <i class="fas fa-users"></i> Manage Registrations
                            </a>
                            <!-- Removed Edit Seat Layout Button -->
                            <a href="export.php" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export Data
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-external-link-alt"></i> View Registration Form
                            </a>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="data-table">
                            <div class="table-header">
                                <i class="fas fa-clock"></i> Recent Registrations
                            </div>
                            <div class="table-content">
                                <?php
                                $registrations = [];
                                if ($tableExists) {
                                    try {
                                        $stmt = $pdo->query("
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
                                            LIMIT 10
                                        ");
                                        $registrations = $stmt->fetchAll();
                                    } catch (Exception $e) {
                                        $registrations = [];
                                    }
                                }
                                if ($registrations): ?>
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr style="font-weight:600;color:var(--secondary-color);background:var(--hover);">
                                                <th style="padding:0.75rem 0.5rem;">Staff Name</th>
                                                <th>Employee #</th>
                                                <th>Cinema Hall</th>
                                                <th>Shift</th>
                                                <th>Attendees</th>
                                                <th>Selected Seats</th>
                                                <th>Registration Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <td style="padding:0.75rem 0.5rem;"><?php echo htmlspecialchars($reg['staff_name']); ?></td>
                                                <td><?php echo htmlspecialchars($reg['emp_number']); ?></td>
                                                <td><?php echo htmlspecialchars($reg['hall_name']); ?></td>
                                                <td><?php echo htmlspecialchars($reg['shift_name']); ?></td>
                                                <td><?php echo htmlspecialchars($reg['attendee_count'] ?? 1); ?></td>
                                                <td><?php echo htmlspecialchars($reg['selected_seats'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($reg['registration_date'])); ?></td>
                                                <td>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteRegistration(<?php echo $reg['id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                        <i class="fas fa-inbox"></i> No registrations found.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'registrations'): ?>
                    <!-- Registrations Tab (Registration Management section removed) -->
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Registration Management has been removed.
                    </div>
                    
                <?php elseif ($current_tab === 'seats'): ?>
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Seat Layout Editor is currently disabled.
                    </div>
                <?php elseif ($current_tab === 'settings'): ?>
                    <!-- Event Settings Tab -->
                    <div class="form-section" style="background: var(--card); border-radius: 16px; box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2); border: 1px solid var(--border);">
                        <h2 style="color: var(--secondary-color); font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem;"><i class="fas fa-cog"></i> Event Settings</h2>
                        <p style="color: var(--text-muted);">Click the save button next to each setting to update it individually.</p>
                        <div class="form-grid" style="gap: 2rem;">
                            <div class="form-group">
                                <label for="movie_name" style="color: var(--secondary-color); font-weight: 600;">🎬 Movie Name</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="movie_name" name="movie_name" placeholder="Enter movie name" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('movie_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="movie_time" style="color: var(--secondary-color); font-weight: 600;">⏰ Movie Time</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="movie_time" name="movie_time" placeholder="e.g., 7:00 PM" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('movie_time')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="venue_name" style="color: var(--secondary-color); font-weight: 600;">🏢 Venue / Cinema Hall Name</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="venue_name" name="venue_name" placeholder="Enter venue name" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('venue_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="max_attendees" style="color: var(--secondary-color); font-weight: 600;">👥 Max Attendees per Booking</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="number" id="max_attendees" name="max_attendees" min="1" max="10" value="3" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('max_attendees')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="default_seat_count" style="color: var(--secondary-color); font-weight: 600;">🪑 Default Seat Count</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="number" id="default_seat_count" name="default_seat_count" min="1" value="72" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('default_seat_count')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="company_name" style="color: var(--secondary-color); font-weight: 600;">🏢 Company Name</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="company_name" name="company_name" placeholder="Enter company name" value="Western Digital" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('company_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 2rem;">
                            <button type="button" class="btn btn-secondary" style="background: var(--hover); color: var(--text-primary); border-radius: 8px;" onclick="loadEventSettings()">
                                <i class="fas fa-refresh"></i> Load Current Settings
                            </button>
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'employees'): ?>
                    <!-- Employees Tab -->
                    <div class="form-section">
                        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1rem;">
                            <h2 style="margin-bottom: 0;"><i class="fas fa-id-card"></i> Employee Management</h2>
                        </div>
                        <!-- Add Employee Form -->
                        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(255, 255, 255, 0.1);">
                            <h3 style="margin-bottom: 1rem; color: var(--secondary-color);">Add New Employee</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_emp_number">Employee Number</label>
                                    <input type="text" id="new_emp_number" placeholder="Enter employee number">
                                </div>
                                <div class="form-group">
                                    <label for="new_full_name">Full Name</label>
                                    <input type="text" id="new_full_name" placeholder="Enter full name">
                                </div>
                                <div class="form-group">
                                    <label for="new_shift_id">Shift</label>
                                    <select id="new_shift_id">
                                        <option value="">Select shift</option>
                                        <?php foreach ($shifts as $shift): ?>
                                            <option value="<?php echo $shift['id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addNewEmployee()">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                        </div>
                        
                        <!-- Employees List Area -->
                        <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                            <div class="tab-nav" id="employeeTabNav" style="display: flex; gap: 0.5rem;">
                                <button class="tab-btn active" id="tabActiveEmployees">Active Employees</button>
                                <button class="tab-btn" id="tabDeactivatedEmployees">Deactivated Employees</button>
                            </div>
                            <input type="text" id="employeeSearchInput" class="search-input" placeholder="Search by Employee Number..." style="max-width: 300px; margin-left: 1rem;">
                        </div>
                        <div class="data-table" style="margin-top:2rem;">
                            <div class="table-header">
                                <i class="fas fa-users"></i> Employees
                            </div>
                            <div class="table-content" id="employeesTable">
                                <!-- Employee list will be loaded here as a table -->
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'export'): ?>
                    <!-- Export Tab -->
                    <div class="form-section">
                        <h2><i class="fas fa-download"></i> Export Options</h2>
                        <div class="actions">
                            <a href="export.php?type=registrations" class="btn btn-primary">
                                <i class="fas fa-file-csv"></i> Export Registrations (CSV)
                            </a>
                            <a href="export.php?type=attendees" class="btn btn-primary">
                                <i class="fas fa-users"></i> Export Attendee List (CSV)
                            </a>
                            <a href="export.php?type=seats" class="btn btn-primary">
                                <i class="fas fa-chair"></i> Export Seat Map (CSV)
                            </a>
                            <a href="export.php?type=employees" class="btn btn-primary">
                                <i class="fas fa-id-card"></i> Export Employee List (CSV)
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Login Form -->
            <div class="header">
                <h1><i class="fas fa-film"></i> Admin Login</h1>
                <p>WD Movie Night Registration System</p>
            </div>
            
            <div class="login-container">
                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="index.php" style="color: var(--text-muted); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Registration
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div id="toastContainer"></div>
    
    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
        <div style="background:#1a1a2e;padding:2rem;border-radius:12px;min-width:320px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,0.3);position:relative;">
            <h3 style="color:var(--secondary-color);margin-bottom:1rem;">Edit Employee</h3>
            <form id="editEmployeeForm">
                <div class="form-group">
                    <label for="edit_emp_number">Employee Number</label>
                    <input type="text" id="edit_emp_number" name="emp_number" required>
                </div>
                <div class="form-group">
                    <label for="edit_full_name">Full Name</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_shift_id">Shift</label>
                    <select id="edit_shift_id" name="shift_id" required>
                        <option value="">Select shift</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo $shift['id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" id="edit_employee_id" name="id">
                <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditEmployeeModal()">Cancel</button>
                </div>
            </form>
            <button onclick="closeEditEmployeeModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--secondary-color);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
    </div>
    
    <script>
        // Global variables
        let currentPage = 1;
        let searchTerm = '';
        let currentHallId = 1;
        let currentShiftId = '';
        let allHalls = <?php echo json_encode($halls); ?>;
        let allShifts = [];
        let allEmployees = [];
        let showActiveEmployees = true;
        let editingEmployee = null;

        // On page load, set up hall and shift selectors
        document.addEventListener('DOMContentLoaded', function() {
            const hallSelector = document.getElementById('hallSelector');
            const shiftSelector = document.getElementById('shiftSelector');
            // Populate hall selector
            hallSelector.innerHTML = '';
            allHalls.forEach(hall => {
                const opt = document.createElement('option');
                opt.value = hall.id;
                opt.textContent = hall.hall_name;
                hallSelector.appendChild(opt);
            });
            currentHallId = parseInt(hallSelector.value);
            // Load shifts for the selected hall
            loadShiftsForHall(currentHallId);
            hallSelector.addEventListener('change', function() {
                currentHallId = parseInt(this.value);
                loadShiftsForHall(currentHallId);
            });
            shiftSelector.addEventListener('change', function() {
                currentShiftId = this.value;
                loadSeatLayout();
            });
        });
        
        // Save individual event setting
        function saveSetting(settingKey) {
            const input = document.getElementById(settingKey);
            const value = input.value.trim();
            
            if (value === '') {
                showToast('Please enter a value for ' + settingKey.replace('_', ' '), 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_event_setting');
            formData.append('setting_key', settingKey);
            formData.append('setting_value', value);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(settingKey.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()) + ' Saved Successfully', 'success');
                } else {
                    showToast('Error saving setting: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error saving setting', 'error');
            });
        }
        
        // Load event settings
        function loadEventSettings() {
            fetch('admin-api.php?action=get_event_settings')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.settings;
                        if (settings.movie_name) document.getElementById('movie_name').value = settings.movie_name;
                        if (settings.movie_time) document.getElementById('movie_time').value = settings.movie_time;
                        if (settings.venue_name) document.getElementById('venue_name').value = settings.venue_name;
                        if (settings.max_attendees) document.getElementById('max_attendees').value = settings.max_attendees;
                        if (settings.default_seat_count) document.getElementById('default_seat_count').value = settings.default_seat_count;
                        if (settings.company_name) document.getElementById('company_name').value = settings.company_name;
                        showToast('Settings loaded successfully', 'success');
                    } else {
                        showToast('Error loading settings: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error loading settings', 'error');
                });
        }
        
        // Load shifts for a given hall
        function loadShiftsForHall(hallId) {
            const shiftSelector = document.getElementById('shiftSelector');
            shiftSelector.innerHTML = '<option value="">Loading shifts...</option>';
            fetch('admin-api.php?action=get_shifts_for_hall&hall_id=' + encodeURIComponent(hallId))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allShifts = data.shifts;
                        shiftSelector.innerHTML = '<option value="">Select a shift</option>';
                        allShifts.forEach(shift => {
                            const opt = document.createElement('option');
                            opt.value = shift.id;
                            opt.textContent = shift.shift_name;
                            shiftSelector.appendChild(opt);
                        });
                        if (allShifts.length > 0) {
                            shiftSelector.value = allShifts[0].id;
                            currentShiftId = allShifts[0].id;
                            loadSeatLayout();
                        } else {
                            shiftSelector.value = '';
                            currentShiftId = '';
                            document.getElementById('seatGrid').innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;">No shifts found for this hall.</div>';
                        }
                    } else {
                        shiftSelector.innerHTML = '<option value="">No shifts found</option>';
                        currentShiftId = '';
                        document.getElementById('seatGrid').innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;">No shifts found for this hall.</div>';
                    }
                })
                .catch(() => {
                    shiftSelector.innerHTML = '<option value="">Error loading shifts</option>';
                    currentShiftId = '';
                    document.getElementById('seatGrid').innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading shifts</div>';
                });
        }
        // Load seat layout for selected hall and shift
        function loadSeatLayout() {
            const grid = document.getElementById('seatGrid');
            if (!grid || !currentHallId || !currentShiftId) {
                grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;">Select a hall and shift to view seats.</div>';
                return;
            }
            grid.innerHTML = '<div style="padding:2rem;text-align:center;"><div class="loading"></div> Loading seats...</div>';
            fetch('admin-api.php?action=get_seat_layout&hall_id=' + encodeURIComponent(currentHallId) + '&shift_id=' + encodeURIComponent(currentShiftId))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderSeatGrid(data.seats);
                    } else {
                        grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading seats</div>';
                    }
                })
                .catch(() => {
                    grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading seats</div>';
                });
        }
        // Render seat grid for a hall
        function renderSeatGrid(seats) {
            const grid = document.getElementById('seatGrid');
            if (!grid) return;
            if (!seats || seats.length === 0) {
                grid.innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;">No seats found for this hall.</div>';
                return;
            }
            let html = '';
            // Example: 10x10 grid (customize as needed)
            const seatsPerRow = 10;
            for (let i = 0; i < seats.length; i++) {
                if (i % seatsPerRow === 0) html += '<div class="seat-row" style="display:flex;gap:4px;margin-bottom:4px;">';
                const seat = seats[i];
                html += `<div class="seat ${seat.status}" data-seat-id="${seat.id}" onclick="toggleSeatStatus(${seat.id})">${seat.seat_number}</div>`;
                if ((i + 1) % seatsPerRow === 0) html += '</div>';
            }
            if (seats.length % seatsPerRow !== 0) html += '</div>';
            grid.innerHTML = html;
        }
        
        // Toggle seat status
        function toggleSeatStatus(seatId) {
            const seat = document.querySelector(`[data-seat-id="${seatId}"]`);
            const statuses = ['available', 'occupied', 'blocked', 'reserved'];
            const currentStatus = seat.className.includes('available') ? 'available' : 
                                seat.className.includes('occupied') ? 'occupied' :
                                seat.className.includes('blocked') ? 'blocked' : 'reserved';
            
            const currentIndex = statuses.indexOf(currentStatus);
            const nextStatus = statuses[(currentIndex + 1) % statuses.length];
            
            seat.className = `seat ${nextStatus}`;
            seat.setAttribute('data-status', nextStatus);
        }
        
        // Save seat layout
        function saveSeatLayout() {
            const seats = document.querySelectorAll('.seat');
            const seatData = [];
            
            seats.forEach(seat => {
                seatData.push({
                    id: seat.getAttribute('data-seat-id'),
                    status: seat.getAttribute('data-status') || 'available'
                });
            });
            
            // Here you would send the data to the server
            showToast('Seat layout saved successfully', 'success');
        }
        
        // Reset seat layout
        function resetSeatLayout() {
            if (confirm('Are you sure you want to reset all seats to available?')) {
                const seats = document.querySelectorAll('.seat');
                seats.forEach(seat => {
                    seat.className = 'seat available';
                    seat.setAttribute('data-status', 'available');
                });
                showToast('Seat layout reset', 'success');
            }
        }
        
        // Add new employee
        function addNewEmployee() {
            const empNumber = document.getElementById('new_emp_number').value.trim();
            const fullName = document.getElementById('new_full_name').value.trim();
            const shiftId = document.getElementById('new_shift_id').value;
            if (!empNumber || !fullName || !shiftId) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_employee&emp_number=${encodeURIComponent(empNumber)}&full_name=${encodeURIComponent(fullName)}&shift_id=${encodeURIComponent(shiftId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee added successfully', 'success');
                    setTimeout(loadEmployees, 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => showToast('Error adding employee', 'error'));
        }
        
        // Delete employee
        function deleteEmployee(empId) {
            if (!confirm('Are you sure you want to delete this employee?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_employee');
            formData.append('emp_id', empId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee deleted successfully', 'success');
                    loadEmployees();
                } else {
                    showToast('Error deleting employee: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting employee', 'error');
            });
        }
        
        // Toggle employee status
        function toggleEmployeeStatus(empId, currentStatus) {
            const action = currentStatus == 1 ? 'deactivate' : 'activate';
            if (!confirm(`Are you sure you want to ${action} this employee?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'toggle_employee_status');
            formData.append('emp_id', empId);
            formData.append('current_status', currentStatus);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadEmployees();
                } else {
                    showToast('Error updating employee: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error updating employee', 'error');
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            container.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Hide and remove toast
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => container.removeChild(toast), 300);
            }, 3000);
        }

        // Save shift name
        function saveShiftName(shiftId) {
            const input = document.getElementById('shift_name_' + shiftId);
            const value = input.value.trim();
            if (value === '') {
                showToast('Please enter a shift name', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'update_shift_name');
            formData.append('shift_id', shiftId);
            formData.append('shift_name', value);
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Shift name updated', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Error updating shift name', 'error'));
        }
        // Save hall name
        function saveHallName(hallId) {
            const input = document.getElementById('hall_name_' + hallId);
            const value = input.value.trim();
            if (value === '') {
                showToast('Please enter a hall name', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'update_hall_name');
            formData.append('hall_id', hallId);
            formData.append('hall_name', value);
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Hall name updated', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Error updating hall name', 'error'));
        }

        // Load and display all employees
        function loadEmployees() {
            const table = document.getElementById('employeesTable');
            if (!table) return;
            table.innerHTML = '<div style="padding: 2rem; text-align: center;"><div class="loading"></div> Loading...</div>';
            fetch('admin-api.php?action=get_employees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allEmployees = data.employees;
                        renderEmployeesTable(getFilteredEmployees());
                    } else {
                        table.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading employees: ' + data.message + '</div>';
                    }
                })
                .catch(() => {
                    table.innerHTML = '<div style="padding:2rem;text-align:center;color:#ef4444;">Error loading employees</div>';
                });
        }

        // Get filtered employees based on tab and search
        function getFilteredEmployees() {
            const searchInput = document.getElementById('employeeSearchInput');
            let filtered = allEmployees.filter(emp => showActiveEmployees ? emp.is_active == 1 : emp.is_active == 0);
            if (searchInput && searchInput.value.trim()) {
                const searchValue = searchInput.value.trim().toLowerCase();
                filtered = filtered.filter(emp =>
                    emp.emp_number && emp.emp_number.toLowerCase().includes(searchValue)
                );
            }
            return filtered;
        }

        // Render employee table
        function renderEmployeesTable(employees) {
            let html = `<table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="font-weight:600;color:var(--secondary-color);background:var(--hover);">
                        <th style="padding:0.75rem 0.5rem;">Employee #</th>
                        <th>Name</th>
                        <th>Shift</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;
            employees.forEach(emp => {
                html += `<tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:0.75rem 0.5rem;">${emp.emp_number}</td>
                    <td>${emp.full_name}</td>
                    <td>${emp.shift_name || 'N/A'}</td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="openEditEmployeeModal(${emp.id})">Edit</button>
                        <button class="btn btn-warning btn-sm" onclick="toggleEmployeeStatus(${emp.id}, ${emp.is_active})">${emp.is_active ? 'Deactivate' : 'Activate'}</button>
                    </td>
                </tr>`;
            });
            html += `</tbody></table>`;
            document.getElementById('employeesTable').innerHTML = html;
        }

        // Search/filter employees by employee number
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('employeeSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    renderEmployeesTable(getFilteredEmployees());
                });
            }

            // Tab switching logic for employees
            const tabActive = document.getElementById('tabActiveEmployees');
            const tabDeactivated = document.getElementById('tabDeactivatedEmployees');
            if (tabActive && tabDeactivated) {
                tabActive.addEventListener('click', function() {
                    showActiveEmployees = true;
                    tabActive.classList.add('active');
                    tabDeactivated.classList.remove('active');
                    renderEmployeesTable(getFilteredEmployees());
                });
                tabDeactivated.addEventListener('click', function() {
                    showActiveEmployees = false;
                    tabDeactivated.classList.add('active');
                    tabActive.classList.remove('active');
                    renderEmployeesTable(getFilteredEmployees());
                });
            }
        });

        // Load employees on page load if on employees tab
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?php echo $current_tab; ?>';
            if (currentTab === 'employees') {
                loadEmployees();
            }
        });

        // Deactivate employee function
        function deactivateEmployee(empNumber) {
            if (!confirm('Are you sure you want to deactivate this employee and free their seat?')) return;
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=deactivate_employee&emp_number=${encodeURIComponent(empNumber)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee deactivated and seat freed', 'success');
                    loadEmployees();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Error deactivating employee', 'error'));
        }

        // Activate employee function
        function activateEmployee(empNumber) {
            if (!confirm('Are you sure you want to activate this employee?')) return;
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=activate_employee&emp_number=${encodeURIComponent(empNumber)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee activated', 'success');
                    loadEmployees();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => showToast('Error activating employee', 'error'));
        }

        function openEditEmployeeModal(empId) {
            const emp = allEmployees.find(e => e.id == empId);
            if (!emp) return;
            editingEmployee = emp;
            document.getElementById('edit_employee_id').value = emp.id;
            document.getElementById('edit_emp_number').value = emp.emp_number;
            document.getElementById('edit_full_name').value = emp.full_name;
            document.getElementById('edit_shift_id').value = emp.shift_id || '';
            document.getElementById('editEmployeeModal').style.display = 'flex';
        }

        function closeEditEmployeeModal() {
            document.getElementById('editEmployeeModal').style.display = 'none';
            editingEmployee = null;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editEmployeeForm');
            if (form) {
                form.onsubmit = function(e) {
                    e.preventDefault();
                    const id = document.getElementById('edit_employee_id').value;
                    const emp_number = document.getElementById('edit_emp_number').value.trim();
                    const full_name = document.getElementById('edit_full_name').value.trim();
                    const shift_id = document.getElementById('edit_shift_id').value;
                    if (!emp_number || !full_name || !shift_id) {
                        showToast('All fields are required', 'error');
                        return;
                    }
                    // Check if shift or emp_number is changing and employee has active registration
                    const emp = allEmployees.find(e => e.id == id);
                    let needsConfirm = false;
                    let confirmMsg = '';
                    if (emp && emp.is_active == 1) {
                        if (emp.shift_id != shift_id) {
                            needsConfirm = true;
                            confirmMsg = 'This employee has an active registration. Changing their shift will cancel their current registration and free their seat(s). Continue?';
                        } else if (emp.emp_number !== emp_number) {
                            needsConfirm = true;
                            confirmMsg = 'This employee has an active registration. Changing their employee number will cancel their current registration and free their seat(s). Continue?';
                        }
                    }
                    function doUpdate() {
                        fetch('admin.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=update_employee&id=${encodeURIComponent(id)}&emp_number=${encodeURIComponent(emp_number)}&full_name=${encodeURIComponent(full_name)}&shift_id=${encodeURIComponent(shift_id)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                closeEditEmployeeModal();
                                loadEmployees();
                            } else {
                                showToast('Error: ' + data.message, 'error');
                            }
                        })
                        .catch(() => showToast('Error updating employee', 'error'));
                    }
                    if (needsConfirm) {
                        if (confirm(confirmMsg)) {
                            doUpdate();
                        }
                    } else {
                        doUpdate();
                    }
                };
            }
        });
    </script>
</body>
</html>
