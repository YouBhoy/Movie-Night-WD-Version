<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$error = '';
$registration = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting by IP address
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($clientIP)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $empNumber = strtoupper(trim($_POST['emp_number'] ?? ''));

        if (empty($empNumber)) {
            $error = 'Please enter your employee number.';
        } else {
            try {
                $pdo = getDBConnection();

                // Check if employee exists
                $stmt = $pdo->prepare("SELECT full_name FROM employees WHERE emp_number = ? AND is_active = 1");
                $stmt->execute([$empNumber]);
                $employee = $stmt->fetch();

                if (!$employee) {
                    $error = 'Employee not found. Please check your employee number.';
                } else {
                    // Find registration
                    $stmt = $pdo->prepare("
                        SELECT r.*, h.hall_name, s.shift_name
                        FROM registrations r
                        LEFT JOIN cinema_halls h ON r.hall_id = h.id
                        LEFT JOIN shifts s ON r.shift_id = s.id
                        WHERE r.emp_number = ? AND r.status = 'active'
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$empNumber]);
                    $registration = $stmt->fetch();

                    if (!$registration) {
                        $error = 'No active registration found for this employee.';
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again.';
                error_log("Error in find-registration: " . $e->getMessage());
            }
        }
    }
}

require dirname(__DIR__) . '/views/find-registration.php';
