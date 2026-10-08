<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/services/EmployeeService.php';
require_once dirname(__DIR__) . '/services/EventSettingsService.php';
require_once dirname(__DIR__) . '/repositories/MysqlBookingRepository.php';

// Use the dedicated login flow so lockout and CSRF apply consistently.
requireAdminLogin();

// Handle logout
if (isset($_GET['logout'])) {
    header('Location: logout.php');
    exit;
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireAdminApi(true);
    if (!checkAdminRateLimit($_POST['action'] ?? 'admin_action')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait and try again.']);
        exit;
    }
    header('Content-Type: application/json');

    try {
        $pdo = getDBConnection();

        switch ($_POST['action']) {
            case 'delete_registration':
                $regId = (int)($_POST['reg_id'] ?? 0);
                $registration = $regId > 0 ? bookingService($pdo)->cancel($regId) : null;
                echo json_encode(['success' => $registration !== null, 'message' => $registration ? 'Registration cancelled and seats released' : 'Registration not found']);
                exit;

            case 'update_event_setting':
                try {
                    $value = (new EventSettingsService($pdo))->update(
                        $_POST['setting_key'] ?? '', $_POST['setting_value'] ?? ''
                    );
                } catch (DomainException $exception) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Setting updated successfully', 'setting_value' => $value]);
                exit;

            case 'add_employee':
                $employeeId = (new EmployeeService($pdo))->add(
                    (string)($_POST['emp_number'] ?? ''),
                    (string)($_POST['full_name'] ?? ''),
                    (int)($_POST['shift_id'] ?? 0)
                );
                logAdminActivity('add_employee', 'employees', $employeeId);
                echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
                exit;

            case 'delete_employee':
                $emp_id = (int)($_POST['emp_id'] ?? 0);

                if ($emp_id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ? AND is_active = 0 AND NOT EXISTS (SELECT 1 FROM registrations WHERE registrations.emp_number = employees.emp_number AND status = 'active')");
                    $stmt->execute([$emp_id]);

                    echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() > 0 ? 'Employee deleted successfully' : 'Deactivate the employee and cancel their bookings before deletion']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                }
                exit;

            case 'toggle_employee_status':
                $emp_id = (int)($_POST['emp_id'] ?? 0);
                $current_status = (int)($_POST['current_status'] ?? 0);

                if ($emp_id > 0) {
                    $pdo->beginTransaction();
                    $lockEmployee = $pdo->prepare('SELECT is_active FROM employees WHERE id = ? FOR UPDATE');
                    $lockEmployee->execute([$emp_id]);
                    $currentEmployee = $lockEmployee->fetch();
                    if (!$currentEmployee) throw new RuntimeException('Employee not found');
                    $new_status = (int)$currentEmployee['is_active'] === 1 ? 0 : 1;

                    // If deactivating employee, free their seats first
                    if ($new_status == 0) {
                        // Get employee number
                        $empStmt = $pdo->prepare("SELECT emp_number FROM employees WHERE id = ?");
                        $empStmt->execute([$emp_id]);
                        $employee = $empStmt->fetch();

                        if ($employee) {
                            // Find active registration for this employee
                            $regStmt = $pdo->prepare("SELECT id FROM registrations WHERE emp_number = ? AND status = 'active' LIMIT 1");
                            $regStmt->execute([$employee['emp_number']]);
                            $registration = $regStmt->fetch();

                            if ($registration) {
                                // Free seats and cancel registration
                                $reg_id = $registration['id'];
                                bookingService($pdo)->cancel((int)$reg_id);
                            }
                        }
                    }

                    // Update employee status
                    $stmt = $pdo->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $emp_id]);

                    $pdo->commit();

                    // Get employee details for logging
                    $empDetailsStmt = $pdo->prepare("SELECT emp_number, full_name FROM employees WHERE id = ?");
                    $empDetailsStmt->execute([$emp_id]);
                    $empDetails = $empDetailsStmt->fetch();

                    // Log the activity
                    $adminUser = $_SESSION['admin_username'] ?? 'admin';
                    $action = $new_status == 1 ? 'employee_activated' : 'employee_deactivated';
                    $details = $new_status == 1 ?
                        "Employee activated: {$empDetails['emp_number']} - {$empDetails['full_name']}" :
                        "Employee deactivated: {$empDetails['emp_number']} - {$empDetails['full_name']}";

                    if ($new_status == 0 && isset($registration) && $registration) {
                        $details .= " (Registration cancelled, seats freed)";
                    }

                    $logStmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $logStmt->execute([
                        $adminUser,
                        $action,
                        'employee',
                        $emp_id,
                        $details,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);

                    $status_text = $new_status == 1 ? 'activated' : 'deactivated';
                    $message = 'Employee ' . $status_text . ' successfully';
                    if ($new_status == 0) {
                        $message .= ' and seat(s) freed';
                    }
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                }
                exit;

            case 'update_seat_status':
                $seatId = (int)($_POST['seat_id'] ?? 0);
                $status = $_POST['status'] ?? 'available';

                if ($seatId > 0 && in_array($status, ['available', 'blocked', 'reserved'], true)) {
                    $stmt = $pdo->prepare("UPDATE seats SET status = ? WHERE id = ? AND status != 'occupied'");
                    $stmt->execute([$status, $seatId]);

                    echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() > 0 ? 'Seat status updated' : 'Seat is occupied, missing, or unchanged']);
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
                $pdo->beginTransaction();
                // Get current employee info
                $empStmt = $pdo->prepare("SELECT emp_number, shift_id FROM employees WHERE id = ? FOR UPDATE");
                $empStmt->execute([$id]);
                $currentEmp = $empStmt->fetch();
                if (!$currentEmp) throw new RuntimeException('Employee not found');
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
                            bookingService($pdo)->cancel((int)$reg_id);
                            $registrationCancelled = true;
                        }
                    }
                }
                $stmt = $pdo->prepare("UPDATE employees SET emp_number = ?, full_name = ?, shift_id = ? WHERE id = ?");
                $stmt->execute([$emp_number, $full_name, $shift_id, $id]);
                $pdo->commit();
                $msg = 'Employee updated';
                if ($registrationCancelled) {
                    $msg .= '. Registration cancelled and seat(s) freed.';
                }
                echo json_encode(['success' => true, 'message' => $msg, 'registration_cancelled' => $registrationCancelled]);
                exit;
            case 'delete_employee_cleanup':
                $emp_id = (int)($_POST['emp_id'] ?? 0);
                if ($emp_id > 0) {
                    $pdo->beginTransaction();
                    // Check if employee is deactivated
                    $empStmt = $pdo->prepare("SELECT emp_number, full_name, is_active FROM employees WHERE id = ? FOR UPDATE");
                    $empStmt->execute([$emp_id]);
                    $employee = $empStmt->fetch();
                    if (!$employee) {
                        echo json_encode(['success' => false, 'message' => 'Employee not found']);
                        exit;
                    }
                    if ($employee['is_active'] != 0) {
                        echo json_encode(['success' => false, 'message' => 'Employee must be deactivated before deletion']);
                        exit;
                    }
                    // Delete all registrations and free seats for this employee
                    $regStmt = $pdo->prepare("SELECT id, status FROM registrations WHERE emp_number = ?");
                    $regStmt->execute([$employee['emp_number']]);
                    $registrations = $regStmt->fetchAll();
                    foreach ($registrations as $registration) {
                        $reg_id = $registration['id'];
                        if ($registration['status'] === 'active') {
                            // Free seats if any (call stored procedure if exists)
                            bookingService($pdo)->cancel((int)$reg_id);
                        }
                        // Delete registration
                        $delRegStmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
                        $delRegStmt->execute([$reg_id]);
                    }
                    // Delete employee
                    $delEmpStmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $delEmpStmt->execute([$emp_id]);
                    $pdo->commit();
                    // Log the deletion
                    $adminUser = $_SESSION['admin_username'] ?? 'admin';
                    $details = "Employee deleted: {$employee['emp_number']} - {$employee['full_name']} (all registrations and seats freed)";
                    $logStmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_user, action, target_type, target_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $logStmt->execute([
                        $adminUser,
                        'employee_deleted',
                        'employee',
                        $emp_id,
                        $details,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Employee and related data deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                }
                exit;
            case 'add_admin':
                if ($_SESSION['admin_role'] !== 'admin') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
                $username = trim($_POST['new_admin_username'] ?? '');
                $password = $_POST['new_admin_password'] ?? '';
                $role = $_POST['new_admin_role'] ?? 'admin';
                $is_active = isset($_POST['new_admin_active']) ? 1 : 0;
                if (strlen($username) < 3 || strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Username or password too short']); exit; }
                $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                $checkStmt->execute([$username]);
                if ($checkStmt->fetch()) { echo json_encode(['success'=>false,'message'=>'Username already exists']); exit; }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $role, $is_active]);
                echo json_encode(['success'=>true,'message'=>'Admin added successfully']); exit;
            case 'edit_admin':
                if ($_SESSION['admin_role'] !== 'admin') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
                $id = (int)($_POST['edit_admin_id'] ?? 0);
                $username = trim($_POST['edit_admin_username'] ?? '');
                $role = $_POST['edit_admin_role'] ?? 'admin';
                $is_active = isset($_POST['edit_admin_active']) ? 1 : 0;
                $password = $_POST['edit_admin_password'] ?? '';
                if ($id <= 0 || strlen($username) < 3) { echo json_encode(['success'=>false,'message'=>'Invalid input']); exit; }
                $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
                $checkStmt->execute([$username, $id]);
                if ($checkStmt->fetch()) { echo json_encode(['success'=>false,'message'=>'Username already exists']); exit; }
                if ($password) {
                    if (strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password too short']); exit; }
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE admin_users SET username=?, role=?, is_active=?, password_hash=? WHERE id=?");
                    $stmt->execute([$username, $role, $is_active, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admin_users SET username=?, role=?, is_active=? WHERE id=?");
                    $stmt->execute([$username, $role, $is_active, $id]);
                }
                echo json_encode(['success'=>true,'message'=>'Admin updated successfully']); exit;
            case 'delete_admin':
                if ($_SESSION['admin_role'] !== 'admin') { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
                $id = (int)($_POST['delete_admin_id'] ?? 0);
                // Prevent self-delete
                $selfIdStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                $selfIdStmt->execute([$_SESSION['admin_username']]);
                $selfId = $selfIdStmt->fetchColumn();
                if ($id == $selfId) { echo json_encode(['success'=>false,'message'=>'You cannot delete your own account']); exit; }
                $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success'=>true,'message'=>'Admin deleted successfully']); exit;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin action: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Unable to complete the request. Please try again.']);
        exit;
    }
}

// Get database connection
try {
    $pdo = getDBConnection();

    // Get event settings
    $settings = getEventSettings(true);

    // Get halls data for JavaScript
    $halls = $pdo->query("SELECT id, hall_name FROM cinema_halls ORDER BY id")->fetchAll();
    // Fetch all active shifts for the employee form dropdown
    $shifts = $pdo->query("SELECT id, shift_name, hall_id FROM shifts WHERE is_active = 1 ORDER BY id")->fetchAll();
} catch (Exception $e) {
    error_log('Admin page: ' . $e->getMessage());
    $db_error = 'Unable to load administration data.';
    $halls = [];
    $shifts = [];
    $settings = [];
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'settings';
if (!in_array($current_tab, ['settings', 'employees', 'export', 'admins'], true)) $current_tab = 'settings';
if ($current_tab === 'admins' && ($_SESSION['admin_role'] ?? '') !== 'admin') $current_tab = 'settings';
$adminUsers = $current_tab === 'admins' && !isset($db_error) ? $pdo->query('SELECT id, username, role, is_active, last_login, created_at FROM admin_users ORDER BY id')->fetchAll() : [];

// After session_start()
$adminCsrfToken = generateAdminCSRFToken();

require dirname(__DIR__) . '/views/admin.php';
