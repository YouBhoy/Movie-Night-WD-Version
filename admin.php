<?php
require_once 'config.php';

// Check admin access
if (!isset($_GET['key']) || $_GET['key'] !== ADMIN_KEY) {
    http_response_code(403);
    die('Access denied. Invalid admin key.');
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

        /* Search */
        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-input {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
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
                <a href="export.php?key=<?php echo ADMIN_KEY; ?>" class="btn btn-primary">📊 Export Data</a>
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
                <div class="stat-number"><?php echo $stats['blocked_seats'] + $stats['occupied_seats']; ?></div>
                <div class="stat-label">Occupied/Blocked</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="showTab('registrations')">🔍 Registrations</button>
            <button class="tab-btn" onclick="showTab('settings')">⚙️ Event Settings</button>
        </div>

        <!-- Registrations Tab -->
        <div id="registrations-tab" class="tab-content active">
            <div class="section-card">
                <h2>🔍 Registration Management</h2>
                
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" 
                           placeholder="Search by name or employee number..." 
                           onkeyup="searchRegistrations()">
                </div>

                <div id="registrationsContainer">
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
                        <p>Loading registrations...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="section-card">
                <h2>⚙️ Event Settings</h2>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_event_settings">
                    
                    <div class="form-row">
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
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="movie_time" class="form-label">Movie Time</label>
                            <input type="text" id="movie_time" name="movie_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['movie_time'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="movie_location" class="form-label">Location</label>
                            <input type="text" id="movie_location" name="movie_location" class="form-control" 
                                   value="<?php echo htmlspecialchars($settings['movie_location'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">💾 Update Settings</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
        let allRegistrations = [];

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
            
            // Load registrations if switching to registrations tab
            if (tabName === 'registrations') {
                loadRegistrations();
            }
        }

        // Load registrations
        function loadRegistrations() {
            const container = document.getElementById('registrationsContainer');
            
            fetch('admin-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_registrations&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allRegistrations = data.registrations;
                    displayRegistrations(allRegistrations);
                } else {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">❌</div>
                            <p>Error loading registrations: ${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--danger-color);">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">❌</div>
                        <p>Failed to load registrations</p>
                    </div>
                `;
            });
        }

        // Display registrations
        function displayRegistrations(registrations) {
            const container = document.getElementById('registrationsContainer');
            
            if (registrations.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">📝</div>
                        <p>No registrations found</p>
                    </div>
                `;
                return;
            }

            const tableHTML = `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Name</th>
                                <th>Attendees</th>
                                <th>Hall</th>
                                <th>Shift</th>
                                <th>Seats</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${registrations.map(reg => `
                                <tr>
                                    <td><strong>${reg.emp_number}</strong></td>
                                    <td>${reg.staff_name}</td>
                                    <td><span style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem;">${reg.attendee_count}</span></td>
                                    <td>${reg.hall_name}</td>
                                    <td>${reg.shift_name}</td>
                                    <td><code style="background: var(--bg-primary); padding: 0.25rem 0.5rem; border-radius: 4px;">${JSON.parse(reg.selected_seats).join(', ')}</code></td>
                                    <td>${new Date(reg.created_at).toLocaleDateString()}</td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="deleteRegistration(${reg.id}, '${reg.emp_number}')">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = tableHTML;
        }

        // Search registrations
        function searchRegistrations() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            if (!searchTerm) {
                displayRegistrations(allRegistrations);
                return;
            }
            
            const filtered = allRegistrations.filter(reg => 
                reg.emp_number.toLowerCase().includes(searchTerm) ||
                reg.staff_name.toLowerCase().includes(searchTerm)
            );
            
            displayRegistrations(filtered);
        }

        // Delete registration
        function deleteRegistration(regId, empNumber) {
            if (!confirm(`Are you sure you want to delete registration for ${empNumber}?\n\nThis will:\n- Remove the registration\n- Release their seats\n- Cannot be undone`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_registration');
            formData.append('reg_id', regId);
            formData.append('csrf_token', csrfToken);
            
            fetch('admin.php?key=<?php echo ADMIN_KEY; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Reload the page to show the success message and updated data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete registration. Please try again.');
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadRegistrations();
        });
    </script>
</body>
</html>
