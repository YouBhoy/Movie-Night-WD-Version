<?php
session_start();
require_once 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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
                        
                        logAdminActivity('delete_registration', 'registrations', $regId, [
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
                
                try {
                    $updateStmt = $pdo->prepare("UPDATE event_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $updateStmt->execute([$movieName, 'movie_name']);
                    $updateStmt->execute([$movieDate, 'movie_date']);
                    $updateStmt->execute([$movieTime, 'movie_time']);
                    $updateStmt->execute([$movieLocation, 'movie_location']);
                    
                    logAdminActivity('update_settings', 'event_settings', null, [
                        'movie_name' => $movieName,
                        'movie_date' => $movieDate,
                        'movie_time' => $movieTime,
                        'movie_location' => $movieLocation
                    ]);
                    
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

// Get event settings
$settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings");
$settingsStmt->execute();
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get registration statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_registrations,
        SUM(attendee_count) as total_attendees,
        COUNT(DISTINCT hall_id) as halls_used
    FROM registrations 
    WHERE status = 'active'
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Get registrations by hall
$hallStatsStmt = $pdo->prepare("
    SELECT 
        h.hall_name,
        COUNT(r.id) as registration_count,
        SUM(r.attendee_count) as attendee_count
    FROM cinema_halls h
    LEFT JOIN registrations r ON h.id = r.hall_id AND r.status = 'active'
    WHERE h.is_active = 1
    GROUP BY h.id, h.hall_name
    ORDER BY h.id
");
$hallStatsStmt->execute();
$hallStats = $hallStatsStmt->fetchAll();

// Get recent registrations
$recentStmt = $pdo->prepare("
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
    LIMIT 50
");
$recentStmt->execute();
$recentRegistrations = $recentStmt->fetchAll();

$movieName = $settings['movie_name'] ?? 'WD Movie Night';
$registrationEnabled = ($settings['registration_enabled'] ?? '1') === '1';

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo sanitizeInput($movieName); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: #ef4444;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(26, 26, 46, 0.8);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .stat-card h3 {
            color: #ffd700;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .hall-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .hall-stat {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        .hall-stat h4 {
            color: #ffd700;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .hall-stat .count {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
        }

        .registrations-section {
            background: rgba(26, 26, 46, 0.8);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
        }

        .search-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #ffd700;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        .search-input::placeholder {
            color: #94a3b8;
        }

        .search-results-info {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(0, 0, 0, 0.2);
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table th {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            color: #cbd5e1;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .emp-number {
            font-weight: 600;
            color: #ffd700;
        }

        .staff-name {
            font-weight: 500;
            color: #ffffff;
        }

        .seats-display {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .seat-tag {
            background: #ffd700;
            color: #000;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .date-display {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .hall-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .shift-badge {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .attendee-count {
            background: rgba(168, 85, 247, 0.2);
            color: #c084fc;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .no-results-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }

        .spinner {
            width: 2rem;
            height: 2rem;
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-top: 2px solid #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-enabled {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .status-disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-container {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                max-width: none;
            }

            .table-container {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Admin Dashboard</h1>
                <div class="status-indicator <?php echo $registrationEnabled ? 'status-enabled' : 'status-disabled'; ?>">
                    <span><?php echo $registrationEnabled ? '●' : '●'; ?></span>
                    Registration <?php echo $registrationEnabled ? 'Enabled' : 'Disabled'; ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="seat-layout-editor.php" class="btn btn-secondary">🪑 Seat Layout</a>
                <a href="manage-halls.php" class="btn btn-secondary" id="manageHallsBtn">🏢 Manage Halls/Shifts</a>
                <a href="index.php" class="btn btn-secondary">🔙 Back to Registration</a>
                <a href="admin.php" class="btn btn-primary">⚙️ Settings</a>
                <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Registrations</h3>
                <div class="stat-value"><?php echo number_format($stats['total_registrations'] ?? 0); ?></div>
                <div class="stat-label">Active registrations</div>
            </div>
            <div class="stat-card">
                <h3>Total Attendees</h3>
                <div class="stat-value"><?php echo number_format($stats['total_attendees'] ?? 0); ?></div>
                <div class="stat-label">People registered</div>
            </div>
            <div class="stat-card">
                <h3>Cinema Halls</h3>
                <div class="stat-value"><?php echo number_format($stats['halls_used'] ?? 0); ?></div>
                <div class="stat-label">Halls in use</div>
                <div class="hall-stats">
                    <?php foreach ($hallStats as $hall): ?>
                    <div class="hall-stat">
                        <h4><?php echo sanitizeInput($hall['hall_name']); ?></h4>
                        <div class="count"><?php echo number_format($hall['attendee_count'] ?? 0); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Registrations Section -->
        <div class="registrations-section">
            <div class="section-header">
                <h2 class="section-title">Recent Registrations</h2>
                <div class="quick-actions">
                    <button class="btn btn-secondary" onclick="refreshRegistrations()">🔄 Refresh</button>
                </div>
            </div>

            <!-- Search Container -->
            <div class="search-container">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input" 
                    placeholder="Search by employee number or name..."
                    autocomplete="off"
                >
                <button class="btn btn-secondary" onclick="clearSearch()">Clear</button>
            </div>

            <!-- Search Results Info -->
            <div class="search-results-info" id="searchResultsInfo" style="display: none;">
                Showing <span id="resultCount">0</span> results for "<span id="searchTerm"></span>"
            </div>

            <!-- Loading Indicator -->
            <div class="loading" id="loadingIndicator" style="display: none;">
                <div class="spinner"></div>
                <p>Searching registrations...</p>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee #</th>
                            <th>Name</th>
                            <th>Attendees</th>
                            <th>Hall</th>
                            <th>Shift</th>
                            <th>Seats</th>
                            <th>Registration Date</th>
                        </tr>
                    </thead>
                    <tbody id="registrationsTableBody">
                        <?php if (empty($recentRegistrations)): ?>
                        <tr>
                            <td colspan="7" class="no-results">
                                <div class="no-results-icon">📋</div>
                                <p>No registrations found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentRegistrations as $registration): ?>
                        <tr class="registration-row" 
                            data-emp-number="<?php echo strtolower(sanitizeInput($registration['emp_number'])); ?>"
                            data-staff-name="<?php echo strtolower(sanitizeInput($registration['staff_name'])); ?>">
                            <td>
                                <span class="emp-number"><?php echo sanitizeInput($registration['emp_number']); ?></span>
                            </td>
                            <td>
                                <span class="staff-name"><?php echo sanitizeInput($registration['staff_name']); ?></span>
                            </td>
                            <td>
                                <span class="attendee-count"><?php echo $registration['attendee_count']; ?> people</span>
                            </td>
                            <td>
                                <span class="hall-badge"><?php echo sanitizeInput($registration['hall_name']); ?></span>
                            </td>
                            <td>
                                <span class="shift-badge"><?php echo sanitizeInput($registration['shift_name']); ?></span>
                            </td>
                            <td>
                                <div class="seats-display">
                                    <?php 
                                    $seats = json_decode($registration['selected_seats'], true);
                                    if (is_array($seats)) {
                                        foreach ($seats as $seat) {
                                            echo '<span class="seat-tag">' . sanitizeInput($seat) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="date-display">
                                    <?php echo date('M j, Y g:i A', strtotime($registration['registration_date'])); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        let searchTimeout;
        let allRegistrations = [];
        let filteredRegistrations = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            
            // Store all registration rows for filtering
            allRegistrations = Array.from(document.querySelectorAll('.registration-row'));
            filteredRegistrations = [...allRegistrations];
            
            // Setup search input event listener
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(this.value.trim());
                }, 300); // Debounce search by 300ms
            });

            // Setup keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
                
                // Escape to clear search
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    clearSearch();
                }
            });

            // The manageHallsBtn is now a link, so no direct event listener needed here
            // The manage-halls.php page will handle its own navigation
        });

        function performSearch(query) {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const searchResultsInfo = document.getElementById('searchResultsInfo');
            const tableBody = document.getElementById('registrationsTableBody');
            
            if (!query) {
                // Show all registrations
                showAllRegistrations();
                return;
            }

            // Show loading
            loadingIndicator.style.display = 'block';
            
            // Simulate search delay for better UX
            setTimeout(() => {
                const searchTerm = query.toLowerCase();
                
                // Filter registrations
                filteredRegistrations = allRegistrations.filter(row => {
                    const empNumber = row.dataset.empNumber;
                    const staffName = row.dataset.staffName;
                    
                    return empNumber.includes(searchTerm) || staffName.includes(searchTerm);
                });
                
                // Update display
                updateRegistrationsDisplay(filteredRegistrations, query);
                
                // Hide loading
                loadingIndicator.style.display = 'none';
                
                // Show search results info
                document.getElementById('resultCount').textContent = filteredRegistrations.length;
                document.getElementById('searchTerm').textContent = query;
                searchResultsInfo.style.display = 'block';
                
            }, 200);
        }

        function updateRegistrationsDisplay(registrations, searchTerm = '') {
            const tableBody = document.getElementById('registrationsTableBody');
            
            // Hide all rows first
            allRegistrations.forEach(row => {
                row.style.display = 'none';
            });
            
            if (registrations.length === 0) {
                // Show no results message
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-results">
                            <div class="no-results-icon">🔍</div>
                            <p>No registrations found${searchTerm ? ` for "${searchTerm}"` : ''}</p>
                            <small style="color: #64748b; margin-top: 0.5rem; display: block;">
                                Try searching by employee number or name
                            </small>
                        </td>
                    </tr>
                `;
            } else {
                // Show filtered registrations
                registrations.forEach(row => {
                    row.style.display = '';
                });
                
                // Highlight search terms
                if (searchTerm) {
                    highlightSearchTerms(registrations, searchTerm);
                }
            }
        }

        function highlightSearchTerms(registrations, searchTerm) {
            const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
            
            registrations.forEach(row => {
                const empNumberEl = row.querySelector('.emp-number');
                const staffNameEl = row.querySelector('.staff-name');
                
                // Highlight employee number
                if (empNumberEl) {
                    const originalText = empNumberEl.textContent;
                    empNumberEl.innerHTML = originalText.replace(regex, '<mark style="background: #ffd700; color: #000; padding: 0.1rem 0.2rem; border-radius: 3px;">$1</mark>');
                }
                
                // Highlight staff name
                if (staffNameEl) {
                    const originalText = staffNameEl.textContent;
                    staffNameEl.innerHTML = originalText.replace(regex, '<mark style="background: #ffd700; color: #000; padding: 0.1rem 0.2rem; border-radius: 3px;">$1</mark>');
                }
            });
        }

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function showAllRegistrations() {
            const searchResultsInfo = document.getElementById('searchResultsInfo');
            const tableBody = document.getElementById('registrationsTableBody');
            
            // Hide search results info
            searchResultsInfo.style.display = 'none';
            
            // Show all registrations
            allRegistrations.forEach(row => {
                row.style.display = '';
                
                // Remove highlights
                const empNumberEl = row.querySelector('.emp-number');
                const staffNameEl = row.querySelector('.staff-name');
                
                if (empNumberEl) {
                    empNumberEl.innerHTML = empNumberEl.textContent;
                }
                if (staffNameEl) {
                    staffNameEl.innerHTML = staffNameEl.textContent;
                }
            });
            
            filteredRegistrations = [...allRegistrations];
        }

        function clearSearch() {
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            showAllRegistrations();
            searchInput.focus();
        }

        function refreshRegistrations() {
            // Show loading
            const loadingIndicator = document.getElementById('loadingIndicator');
            loadingIndicator.style.display = 'block';
            
            // Refresh the page to get latest data
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

        // Add some visual feedback for search
        document.getElementById('searchInput').addEventListener('focus', function() {
            this.style.borderColor = '#ffd700';
            this.style.boxShadow = '0 0 0 3px rgba(255, 215, 0, 0.1)';
        });

        document.getElementById('searchInput').addEventListener('blur', function() {
            this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            this.style.boxShadow = 'none';
        });
    </script>
</body>
</html>
