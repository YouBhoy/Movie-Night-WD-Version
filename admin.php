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
                $department = $_POST['department'] ?? '';
                
                if (empty($emp_number) || empty($full_name)) {
                    echo json_encode(['success' => false, 'message' => 'Employee number and name are required']);
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
                $stmt = $pdo->prepare("INSERT INTO employees (emp_number, full_name, department, is_active) VALUES (?, ?, ?, 1)");
                $stmt->execute([$emp_number, $full_name, $department]);
                
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
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Get database connection
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $db_error = 'Database connection failed: ' . $e->getMessage();
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #FFD700;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        
        .logout-link {
            position: absolute;
            top: 0;
            right: 0;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .logout-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .back-to-registration-link {
            position: absolute;
            top: 0;
            left: 0;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .back-to-registration-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
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
        
        .form-group label {
            font-weight: 500;
            color: #ffffff;
        }
        
        .form-group input {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }
        
        .btn {
            padding: 0.875rem;
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
            background: #FFD700;
            color: #000;
        }
        
        .btn-primary:hover {
            background: #e6c200;
            transform: translateY(-2px);
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
            background: #ef4444;
            color: #ffffff;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: #ffffff;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
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
            color: #94a3b8;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .nav-tab:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-tab.active {
            background: #FFD700;
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
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #94a3b8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #FFD700;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        /* Tables */
        .data-table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            font-weight: 600;
            color: #FFD700;
        }
        
        .table-content {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            align-items: center;
        }
        
        .table-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .table-row:last-child {
            border-bottom: none;
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
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
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
            background: #10b981;
            color: #ffffff;
        }
        
        .seat.occupied {
            background: #ef4444;
            color: #ffffff;
        }
        
        .seat.blocked {
            background: #6b7280;
            color: #ffffff;
        }
        
        .seat.reserved {
            background: #f59e0b;
            color: #ffffff;
        }
        
        .seat:hover {
            transform: scale(1.1);
            border-color: #FFD700;
        }
        
        /* Forms */
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
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
            color: #ffffff;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #10b981;
        }
        
        .toast.error {
            background: #ef4444;
        }
        
        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #FFD700;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .table-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .table-row > div {
                padding: 0.25rem 0;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .seat-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($logged_in): ?>
            <!-- Dashboard -->
            <a href="?logout=1" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            
            <a href="index.php" class="back-to-registration-link">
                <i class="fas fa-arrow-left"></i> Back to Registration
            </a>
            
            <div class="header">
                <h1><i class="fas fa-film"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
            </div>
            
            <?php if (isset($db_error)): ?>
                <div class="error"><?php echo htmlspecialchars($db_error); ?></div>
            <?php else: ?>
                <!-- Navigation Tabs -->
                <div class="nav-tabs">
                    <a href="?tab=dashboard" class="nav-tab <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Dashboard
                    </a>
                    <a href="?tab=registrations" class="nav-tab <?php echo $current_tab === 'registrations' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Registrations
                    </a>
                    <a href="?tab=seats" class="nav-tab <?php echo $current_tab === 'seats' ? 'active' : ''; ?>">
                        <i class="fas fa-chair"></i> Seat Layout
                    </a>
                    <a href="?tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Event Settings
                    </a>
                    <a href="?tab=employees" class="nav-tab <?php echo $current_tab === 'employees' ? 'active' : ''; ?>">
                        <i class="fas fa-id-card"></i> Employees
                    </a>
                    <a href="?tab=export" class="nav-tab <?php echo $current_tab === 'export' ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i> Export
                    </a>
                    <a href="?tab=labels" class="nav-tab <?php echo $current_tab === 'labels' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i> Labels & Names
                    </a>
                </div>
                
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
                            <a href="?tab=seats" class="btn btn-secondary">
                                <i class="fas fa-chair"></i> Edit Seat Layout
                            </a>
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
                                            SELECT emp_number, full_name, department, hall_assigned, created_at 
                                            FROM registrations 
                                            WHERE status = 'active' 
                                            ORDER BY created_at DESC 
                                            LIMIT 10
                                        ");
                                        $registrations = $stmt->fetchAll();
                                    } catch (Exception $e) {
                                        $registrations = [];
                                    }
                                }
                                
                                if ($registrations): ?>
                                    <div class="table-row" style="font-weight: 600; color: #FFD700;">
                                        <div>Staff Name</div>
                                        <div>Employee #</div>
                                        <div>Cinema Hall</div>
                                        <div>Shift</div>
                                        <div>Attendees</div>
                                        <div>Selected Seats</div>
                                        <div>Registration Date</div>
                                        <div>Actions</div>
                                    </div>
                                    <?php foreach ($registrations as $reg): ?>
                                        <div class="table-row">
                                            <div><?php echo htmlspecialchars($reg['full_name']); ?></div>
                                            <div><?php echo htmlspecialchars($reg['emp_number']); ?></div>
                                            <div><?php echo htmlspecialchars($reg['hall_assigned']); ?></div>
                                            <div><?php echo htmlspecialchars($reg['department']); ?></div>
                                            <div><?php echo htmlspecialchars($reg['attendee_count'] ?? 1); ?></div>
                                            <div><?php echo htmlspecialchars($reg['selected_seats'] ?? 'N/A'); ?></div>
                                            <div><?php echo date('M j, Y g:i A', strtotime($reg['created_at'])); ?></div>
                                            <div>
                                                <button class="btn btn-danger btn-sm" onclick="deleteRegistration(<?php echo $reg['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="padding: 2rem; text-align: center; color: #94a3b8;">
                                        <i class="fas fa-inbox"></i> No registrations found.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'registrations'): ?>
                    <!-- Registrations Tab -->
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by name or employee number...">
                    </div>
                    
                    <div class="data-table">
                        <div class="table-header">
                            <i class="fas fa-users"></i> Registration Management
                        </div>
                        <div class="table-content" id="registrationsTable">
                            <!-- Table content will be loaded via AJAX -->
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'seats'): ?>
                    <!-- Seat Layout Tab -->
                    <div class="seat-layout">
                        <h2><i class="fas fa-chair"></i> Seat Layout Editor</h2>
                        <p>Click on seats to change their status</p>
                        <?php
                        $halls = $pdo->query("SELECT id, hall_name FROM cinema_halls ORDER BY id")->fetchAll();
                        ?>
                        <div class="form-group" style="max-width:350px;margin-bottom:1.5rem;">
                            <label for="hallSelector" style="color:#FFD700;font-weight:600;">Select Cinema Hall:</label>
                            <select id="hallSelector" class="form-select" style="width:100%;padding:0.5rem;"></select>
                        </div>
                        <div class="form-group" style="max-width:350px;margin-bottom:1.5rem;">
                            <label for="shiftSelector" style="color:#FFD700;font-weight:600;">Select Shift:</label>
                            <select id="shiftSelector" class="form-select" style="width:100%;padding:0.5rem;">
                                <option value="">Select a shift</option>
                            </select>
                        </div>
                        <div class="actions">
                            <button class="btn btn-primary" onclick="saveSeatLayout()">
                                <i class="fas fa-save"></i> Save Layout
                            </button>
                            <button class="btn btn-secondary" onclick="resetSeatLayout()">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <div class="seat-grid" id="seatGrid">
                            <!-- Seat grid will be generated via JavaScript -->
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'settings'): ?>
                    <!-- Event Settings Tab -->
                    <div class="form-section">
                        <h2><i class="fas fa-cog"></i> Event Settings</h2>
                        <p>Click the save button next to each setting to update it individually.</p>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="movie_name">🎬 Movie Name</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" id="movie_name" name="movie_name" placeholder="Enter movie name">
                                    <button type="button" class="btn btn-primary" onclick="saveSetting('movie_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="movie_time">⏰ Movie Time</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" id="movie_time" name="movie_time" placeholder="e.g., 7:00 PM">
                                    <button type="button" class="btn btn-primary" onclick="saveSetting('movie_time')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="venue_name">🏢 Venue / Cinema Hall Name</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" id="venue_name" name="venue_name" placeholder="Enter venue name">
                                    <button type="button" class="btn btn-primary" onclick="saveSetting('venue_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_attendees">👥 Max Attendees per Booking</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="number" id="max_attendees" name="max_attendees" min="1" max="10" value="3">
                                    <button type="button" class="btn btn-primary" onclick="saveSetting('max_attendees')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="button" class="btn btn-secondary" onclick="loadEventSettings()">
                                <i class="fas fa-refresh"></i> Load Current Settings
                            </button>
                        </div>
                    </div>
                    
                <?php elseif ($current_tab === 'employees'): ?>
                    <!-- Employees Tab -->
                    <div class="form-section">
                        <h2><i class="fas fa-id-card"></i> Employee Management</h2>
                        
                        <!-- Add Employee Form -->
                        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(255, 255, 255, 0.1);">
                            <h3 style="margin-bottom: 1rem; color: #FFD700;">Add New Employee</h3>
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
                                    <label for="new_department">Department</label>
                                    <input type="text" id="new_department" placeholder="Enter department">
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addNewEmployee()">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                        </div>
                        
                        <div class="actions">
                            <button class="btn btn-secondary" onclick="loadEmployees()">
                                <i class="fas fa-refresh"></i> Refresh List
                            </button>
                        </div>
                        
                        <div class="data-table">
                            <div class="table-header">
                                <i class="fas fa-users"></i> Eligible Employees
                            </div>
                            <div class="table-content" id="employeesTable">
                                <!-- Employee list will be loaded via AJAX -->
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
                <?php elseif ($current_tab === 'labels'): ?>
                    <!-- Labels & Names Tab -->
                    <div class="form-section">
                        <h2><i class="fas fa-tags"></i> Labels & Names</h2>
                        <p>Edit the names of shifts and cinema halls as they appear to users.</p>
                        <div class="form-grid" style="margin-bottom:2rem;">
                            <h3 style="color:#FFD700;">Shifts</h3>
                            <div id="shiftNamesTable">
                                <?php
                                $shifts = $pdo->query("SELECT id, shift_name FROM shifts ORDER BY id")->fetchAll();
                                foreach ($shifts as $shift): ?>
                                    <div class="form-group" style="display:flex;align-items:center;gap:1rem;">
                                        <label style="min-width:80px;">Shift #<?php echo $shift['id']; ?></label>
                                        <input type="text" id="shift_name_<?php echo $shift['id']; ?>" value="<?php echo htmlspecialchars($shift['shift_name']); ?>" style="flex:1;">
                                        <button type="button" class="btn btn-primary" onclick="saveShiftName(<?php echo $shift['id']; ?>)"><i class="fas fa-save"></i> Save</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-grid">
                            <h3 style="color:#FFD700;">Cinema Halls</h3>
                            <div id="hallNamesTable">
                                <?php
                                $halls = $pdo->query("SELECT id, hall_name FROM cinema_halls ORDER BY id")->fetchAll();
                                foreach ($halls as $hall): ?>
                                    <div class="form-group" style="display:flex;align-items:center;gap:1rem;">
                                        <label style="min-width:80px;">Hall #<?php echo $hall['id']; ?></label>
                                        <input type="text" id="hall_name_<?php echo $hall['id']; ?>" value="<?php echo htmlspecialchars($hall['hall_name']); ?>" style="flex:1;">
                                        <button type="button" class="btn btn-primary" onclick="saveHallName(<?php echo $hall['id']; ?>)"><i class="fas fa-save"></i> Save</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
                    <a href="index.php" style="color: #94a3b8; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Registration
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Container -->
    <div id="toastContainer"></div>
    
    <script>
        // Global variables
        let currentPage = 1;
        let searchTerm = '';
        let currentHallId = 1;
        let currentShiftId = '';
        let allHalls = <?php echo json_encode($halls); ?>;
        let allShifts = [];
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
            
            if (document.getElementById('searchInput')) {
                document.getElementById('searchInput').addEventListener('input', function() {
                    searchTerm = this.value;
                    loadRegistrations();
                });
            }
            
            // Load initial data based on current tab
            const currentTab = '<?php echo $current_tab; ?>';
            if (currentTab === 'registrations') {
                loadRegistrations();
            } else if (currentTab === 'seats') {
                loadSeatLayout();
            } else if (currentTab === 'employees') {
                loadEmployees();
            } else if (currentTab === 'settings') {
                loadEventSettings();
            }
        });
        
        // Load registrations with search and pagination
        function loadRegistrations() {
            const table = document.getElementById('registrationsTable');
            if (!table) return;
            
            table.innerHTML = '<div style="padding: 2rem; text-align: center;"><div class="loading"></div> Loading...</div>';
            
            fetch('admin-api.php?action=get_registrations&search=' + encodeURIComponent(searchTerm) + '&page=' + currentPage)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRegistrations(data.registrations);
                    } else {
                        showToast('Error loading registrations: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error loading registrations', 'error');
                });
        }
        
        // Display registrations in table
        function displayRegistrations(registrations) {
            const table = document.getElementById('registrationsTable');
            
            if (registrations.length === 0) {
                table.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8;"><i class="fas fa-inbox"></i> No registrations found.</div>';
                return;
            }
            
            let html = `
                <div class="table-row" style="font-weight: 600; color: #FFD700;">
                    <div>Staff Name</div>
                    <div>Employee #</div>
                    <div>Attendees</div>
                    <div>Selected Seats</div>
                    <div>Registration Date</div>
                    <div>Actions</div>
                </div>
            `;
            
            registrations.forEach(reg => {
                html += `
                    <div class="table-row">
                        <div>${reg.staff_name}</div>
                        <div>${reg.emp_number}</div>
                        <div>${reg.attendee_count || 1}</div>
                        <div>${reg.selected_seats || 'N/A'}</div>
                        <div>${new Date(reg.created_at).toLocaleString()}</div>
                        <div>
                            <button class="btn btn-danger btn-sm" onclick="deleteRegistration(${reg.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            table.innerHTML = html;
        }
        
        // Delete registration
        function deleteRegistration(regId) {
            if (!confirm('Are you sure you want to delete this registration? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_registration');
            formData.append('reg_id', regId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Registration deleted successfully', 'success');
                    loadRegistrations();
                } else {
                    showToast('Error deleting registration: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting registration', 'error');
            });
        }
        
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
                    showToast(settingKey.replace('_', ' ') + ' saved successfully', 'success');
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
        
        // Load employees
        function loadEmployees() {
            const table = document.getElementById('employeesTable');
            if (!table) return;
            
            table.innerHTML = '<div style="padding: 2rem; text-align: center;"><div class="loading"></div> Loading...</div>';
            
            fetch('admin-api.php?action=get_employees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayEmployees(data.employees);
                    } else {
                        showToast('Error loading employees: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error loading employees', 'error');
                });
        }
        
        // Display employees in table
        function displayEmployees(employees) {
            const table = document.getElementById('employeesTable');
            
            if (employees.length === 0) {
                table.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8;"><i class="fas fa-users"></i> No employees found.</div>';
                return;
            }
            
            let html = `
                <div class="table-row" style="font-weight: 600; color: #FFD700;">
                    <div>Employee #</div>
                    <div>Name</div>
                    <div>Department</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
            `;
            
            employees.forEach(emp => {
                const statusClass = emp.is_active == 1 ? 'success' : 'danger';
                const statusText = emp.is_active == 1 ? 'Active' : 'Inactive';
                const statusIcon = emp.is_active == 1 ? 'check-circle' : 'times-circle';
                
                html += `
                    <div class="table-row">
                        <div>${emp.emp_number}</div>
                        <div>${emp.full_name}</div>
                        <div>${emp.department || 'N/A'}</div>
                        <div>
                            <span class="status-badge status-${statusClass}">
                                <i class="fas fa-${statusIcon}"></i> ${statusText}
                            </span>
                        </div>
                        <div>
                            <button class="btn btn-${emp.is_active == 1 ? 'warning' : 'success'} btn-sm" onclick="toggleEmployeeStatus(${emp.id}, ${emp.is_active})">
                                <i class="fas fa-${emp.is_active == 1 ? 'pause' : 'play'}"></i> ${emp.is_active == 1 ? 'Deactivate' : 'Activate'}
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteEmployee(${emp.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            table.innerHTML = html;
        }
        
        // Add new employee
        function addNewEmployee() {
            const empNumber = document.getElementById('new_emp_number').value.trim();
            const fullName = document.getElementById('new_full_name').value.trim();
            const department = document.getElementById('new_department').value.trim();
            
            if (!empNumber || !fullName) {
                showToast('Employee number and name are required', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_employee');
            formData.append('emp_number', empNumber);
            formData.append('full_name', fullName);
            formData.append('department', department);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Employee added successfully', 'success');
                    // Clear form
                    document.getElementById('new_emp_number').value = '';
                    document.getElementById('new_full_name').value = '';
                    document.getElementById('new_department').value = '';
                    // Reload employee list
                    loadEmployees();
                } else {
                    showToast('Error adding employee: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error adding employee', 'error');
            });
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
    </script>
</body>
</html>
