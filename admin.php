<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Check session timeout (1 hour)
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > 3600) {
    session_destroy();
    header('Location: admin-login.php?timeout=1');
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
            case 'delete_registration':
                $regId = filter_var($_POST['reg_id'] ?? 0, FILTER_VALIDATE_INT);
                
                if (!$regId) {
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

            case 'archive_registration':
                $regId = filter_var($_POST['reg_id'] ?? 0, FILTER_VALIDATE_INT);
                
                if (!$regId) {
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
                        
                        // Archive registration (change status to cancelled)
                        $archiveStmt = $pdo->prepare("UPDATE registrations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                        $archiveStmt->execute([$regId]);
                        
                        $pdo->commit();
                        
                        $message = "Registration archived successfully and seats released ✅";
                        $messageType = "success";
                    } else {
                        $pdo->rollBack();
                        $message = "Registration not found";
                        $messageType = "error";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error archiving registration: " . $e->getMessage();
                    $messageType = "error";
                    error_log("Archive registration error: " . $e->getMessage());
                }
                break;

            case 'update_attendee_count':
                $regId = filter_var($_POST['reg_id'] ?? 0, FILTER_VALIDATE_INT);
                $newAttendeeCount = filter_var($_POST['new_attendee_count'] ?? 0, FILTER_VALIDATE_INT);
                
                if (!$regId || !$newAttendeeCount || $newAttendeeCount < 1 || $newAttendeeCount > 4) {
                    $message = "Invalid registration ID or attendee count";
                    $messageType = "error";
                    break;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Get current registration details
                    $regStmt = $pdo->prepare("SELECT emp_number, selected_seats, hall_id, shift_id, attendee_count FROM registrations WHERE id = ?");
                    $regStmt->execute([$regId]);
                    $registration = $regStmt->fetch();
                    
                    if ($registration) {
                        $currentSeats = json_decode($registration['selected_seats'], true);
                        $currentSeatCount = is_array($currentSeats) ? count($currentSeats) : 0;
                        
                        // Check if current seats match new attendee count
                        if ($currentSeatCount === $newAttendeeCount) {
                            // Perfect match - just update attendee count
                            $updateStmt = $pdo->prepare("UPDATE registrations SET attendee_count = ?, updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$newAttendeeCount, $regId]);
                            
                            $pdo->commit();
                            $message = "Attendee count updated successfully ✅";
                            $messageType = "success";
                        } else if ($currentSeatCount > $newAttendeeCount) {
                            // Too many seats - need to release some
                            $seatsToRelease = array_slice($currentSeats, $newAttendeeCount);
                            $seatsToKeep = array_slice($currentSeats, 0, $newAttendeeCount);
                            
                            // Release extra seats
                            $seatUpdateStmt = $pdo->prepare("UPDATE seats SET status = 'available', updated_at = NOW() WHERE seat_number = ? AND hall_id = ? AND shift_id = ?");
                            foreach ($seatsToRelease as $seat) {
                                $seatUpdateStmt->execute([$seat, $registration['hall_id'], $registration['shift_id']]);
                            }
                            
                            // Update registration
                            $updateStmt = $pdo->prepare("UPDATE registrations SET attendee_count = ?, selected_seats = ?, updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$newAttendeeCount, json_encode($seatsToKeep), $regId]);
                            
                            $pdo->commit();
                            $message = "Attendee count updated and extra seats released ✅";
                            $messageType = "success";
                        } else {
                            // Not enough seats - show warning
                            $pdo->rollBack();
                            $message = "Warning: Current registration has {$currentSeatCount} seats but you want {$newAttendeeCount} attendees. Please manually edit the seat selection first.";
                            $messageType = "error";
                        }
                    } else {
                        $pdo->rollBack();
                        $message = "Registration not found";
                        $messageType = "error";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error updating attendee count: " . $e->getMessage();
                    $messageType = "error";
                    error_log("Update attendee count error: " . $e->getMessage());
                }
                break;
                
            case 'update_event_settings':
                $movieName = sanitizeInput($_POST['movie_name'] ?? '');
                $movieDate = sanitizeInput($_POST['movie_date'] ?? '');
                $movieTime = sanitizeInput($_POST['movie_time'] ?? '');
                $movieLocation = sanitizeInput($_POST['movie_location'] ?? '');
                
                try {
                    $updateStmt = $pdo->prepare("UPDATE event_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $updateStmt->execute([$movieName, 'movie_name']);
                    $updateStmt->execute([$movieDate, 'movie_date']);
                    $updateStmt->execute([$movieTime, 'movie_time']);
                    $updateStmt->execute([$movieLocation, 'movie_location']);
                    
                    $message = "Event settings updated successfully ✅";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating settings";
                    $messageType = "error";
                    error_log("Settings update error: " . $e->getMessage());
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
        }

        .admin-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .back-to-registration {
            background: var(--secondary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-to-registration:hover {
            background: #0891b2;
            transform: translateY(-2px);
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-section {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .registrations-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .registrations-table th,
        .registrations-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .registrations-table th {
            background: var(--bg-primary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .registrations-table tr:hover {
            background: var(--bg-primary);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .seat-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .seat-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #dc2626;
        }

        .edit-attendee-form {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-attendee-form input {
            width: 60px;
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-align: center;
        }

        .edit-attendee-form button {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }

            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .registrations-table {
                font-size: 0.875rem;
            }

            .registrations-table th,
            .registrations-table td {
                padding: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">🎬 WD Movie Night Admin Dashboard</h1>
            <a href="index.php" class="back-to-registration">← Back to Registration</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['total_registrations']; ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--secondary-color);"><?php echo $stats['total_attendees']; ?></div>
                <div class="stat-label">Total Attendees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['available_seats']; ?></div>
                <div class="stat-label">Available Seats</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['occupied_seats']; ?></div>
                <div class="stat-label">Occupied Seats</div>
            </div>
        </div>

        <!-- Event Settings -->
        <div class="admin-section">
            <h2 class="section-title">📝 Event Settings</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update_event_settings">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="movie_name" class="form-label">Movie Name</label>
                        <input type="text" id="movie_name" name="movie_name" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['movie_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="movie_date" class="form-label">Movie Date</label>
                        <input type="text" id="movie_date" name="movie_date" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['movie_date'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="movie_time" class="form-label">Movie Time</label>
                        <input type="text" id="movie_time" name="movie_time" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['movie_time'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="movie_location" class="form-label">Movie Location</label>
                        <input type="text" id="movie_location" name="movie_location" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['movie_location'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Event Settings</button>
            </form>
        </div>

        <!-- Registrations Management -->
        <div class="admin-section">
            <h2 class="section-title">👥 Registration Management</h2>
            
            <?php
            // Get all registrations with hall and shift information
            $registrationsStmt = $pdo->query("
                SELECT 
                    r.*,
                    h.hall_name,
                    s.shift_name
                FROM registrations r
                LEFT JOIN cinema_halls h ON r.hall_id = h.id
                LEFT JOIN shifts s ON r.shift_id = s.id
                WHERE r.status = 'active'
                ORDER BY r.created_at DESC
            ");
            $registrations = $registrationsStmt->fetchAll();
            ?>
            
            <?php if (empty($registrations)): ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                    No active registrations found.
                </p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="registrations-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Name</th>
                                <th>Hall</th>
                                <th>Shift</th>
                                <th>Attendees</th>
                                <th>Seats</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($reg['emp_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['hall_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['shift_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <form method="POST" class="edit-attendee-form" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="update_attendee_count">
                                            <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                            <input type="number" name="new_attendee_count" value="<?php echo $reg['attendee_count']; ?>" min="1" max="4">
                                            <button type="submit" class="btn btn-primary btn-sm">✏️</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="seat-tags">
                                            <?php 
                                            $seats = json_decode($reg['selected_seats'], true);
                                            if (is_array($seats)) {
                                                foreach ($seats as $seat) {
                                                    echo '<span class="seat-tag">' . htmlspecialchars($seat) . '</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($reg['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this registration? Seats will be released.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="archive_registration">
                                                <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">📦 Archive</button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete this registration? This cannot be undone.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="delete_registration">
                                                <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Export Options -->
        <div class="admin-section">
            <h2 class="section-title">📊 Export Data</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="export.php?type=registrations&key=<?php echo ADMIN_KEY; ?>" class="btn btn-primary">
                    📋 Export Registrations (CSV)
                </a>
                <a href="export.php?type=attendees&key=<?php echo ADMIN_KEY; ?>" class="btn btn-primary">
                    👥 Export Attendee List (CSV)
                </a>
                <a href="export.php?type=seats&key=<?php echo ADMIN_KEY; ?>" class="btn btn-primary">
                    🎫 Export Seat Map (CSV)
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Confirm actions
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const confirmMessage = this.getAttribute('onsubmit').match(/confirm$$'([^']+)'$$/);
                if (confirmMessage && !confirm(confirmMessage[1])) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
