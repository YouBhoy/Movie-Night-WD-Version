<?php
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: admin-login.php?timeout=1');
    exit;
}

$pdo = getDBConnection();

// Handle archive/delete registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed.";
        $messageType = "error";
    } else {
        if ($_POST['action'] === 'archive_registration') {
            $regId = filter_var($_POST['registration_id'] ?? '', FILTER_VALIDATE_INT);
            
            if ($regId) {
                try {
                    $pdo->beginTransaction();
                    
                    // Get registration details
                    $stmt = $pdo->prepare("
                        SELECT r.*, h.hall_name, s.shift_name 
                        FROM registrations r
                        JOIN cinema_halls h ON r.hall_id = h.id
                        JOIN shifts s ON r.shift_id = s.id
                        WHERE r.id = ? AND r.status = 'active'
                    ");
                    $stmt->execute([$regId]);
                    $registration = $stmt->fetch();
                    
                    if ($registration) {
                        // Free up the seats
                        $selectedSeats = json_decode($registration['selected_seats'], true);
                        if (is_array($selectedSeats)) {
                            foreach ($selectedSeats as $seatNumber) {
                                $seatStmt = $pdo->prepare("
                                    UPDATE seats 
                                    SET status = 'available', updated_at = NOW() 
                                    WHERE hall_id = ? AND shift_id = ? AND seat_number = ?
                                ");
                                $seatStmt->execute([$registration['hall_id'], $registration['shift_id'], $seatNumber]);
                            }
                        }
                        
                        // Archive registration
                        $archiveStmt = $pdo->prepare("
                            UPDATE registrations 
                            SET status = 'cancelled', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $archiveStmt->execute([$regId]);
                        
                        $pdo->commit();
                        $message = "Registration archived successfully and seats released.";
                        $messageType = "success";
                    } else {
                        $pdo->rollBack();
                        $message = "Registration not found.";
                        $messageType = "error";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error archiving registration: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    }
}

// Get statistics
$stats = [];

// Total registrations
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM registrations WHERE status = 'active'");
$stmt->execute();
$stats['total_registrations'] = $stmt->fetchColumn();

// Total attendees
$stmt = $pdo->prepare("SELECT SUM(attendee_count) as total FROM registrations WHERE status = 'active'");
$stmt->execute();
$stats['total_attendees'] = $stmt->fetchColumn() ?: 0;

// Registrations by hall
$stmt = $pdo->prepare("
    SELECT h.hall_name, COUNT(r.id) as count, SUM(r.attendee_count) as attendees
    FROM cinema_halls h
    LEFT JOIN registrations r ON h.id = r.hall_id AND r.status = 'active'
    WHERE h.is_active = 1
    GROUP BY h.id, h.hall_name
    ORDER BY h.id
");
$stmt->execute();
$stats['by_hall'] = $stmt->fetchAll();

// Recent registrations
$stmt = $pdo->prepare("
    SELECT r.*, h.hall_name, s.shift_name
    FROM registrations r
    JOIN cinema_halls h ON r.hall_id = h.id
    JOIN shifts s ON r.shift_id = s.id
    WHERE r.status = 'active'
    ORDER BY r.registration_date DESC
    LIMIT 10
");
$stmt->execute();
$recentRegistrations = $stmt->fetchAll();

// Get only allowed event settings (removed unwanted ones)
$allowedSettings = [
    'movie_name',
    'movie_date', 
    'movie_time',
    'movie_location',
    'event_description',
    'footer_text'
];

$stmt = $pdo->prepare("
    SELECT setting_key, setting_value, setting_type, description 
    FROM event_settings 
    WHERE setting_key IN ('" . implode("','", $allowedSettings) . "')
    ORDER BY setting_key
");
$stmt->execute();
$eventSettings = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WD Movie Night</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-theme">
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div class="admin-header-content">
                <h1 class="admin-logo">WD Admin</h1>
                <nav class="admin-nav">
                    <a href="index.php" class="admin-nav-link">← Back to Registration</a>
                    <a href="#dashboard" class="admin-nav-link active">Dashboard</a>
                    <a href="#registrations" class="admin-nav-link">Registrations</a>
                    <a href="#settings" class="admin-nav-link">Settings</a>
                    <a href="logout.php" class="admin-nav-link logout">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin: 1rem; padding: 1rem; border-radius: 8px; <?php echo $messageType === 'success' ? 'background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3);' : 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <section id="dashboard" class="admin-section active">
        <div class="container">
            <div class="admin-page-header">
                <h2 class="admin-page-title">Dashboard Overview</h2>
                <p class="admin-page-subtitle">Movie Night Registration System Statistics</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_registrations']); ?></div>
                        <div class="stat-label">Total Registrations</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🎫</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_attendees']); ?></div>
                        <div class="stat-label">Total Attendees</div>
                    </div>
                </div>

                <?php foreach ($stats['by_hall'] as $hall): ?>
                <div class="stat-card">
                    <div class="stat-icon"><?php echo $hall['hall_name'] === 'Cinema Hall 1' ? '🎬' : '🎭'; ?></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($hall['attendees'] ?: 0); ?></div>
                        <div class="stat-label"><?php echo sanitizeInput($hall['hall_name']); ?></div>
                        <div class="stat-sub"><?php echo number_format($hall['count'] ?: 0); ?> registrations</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Registrations -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Recent Registrations</h3>
                    <a href="#registrations" class="view-all-link">View All</a>
                </div>
                <div class="admin-card-content">
                    <?php if (empty($recentRegistrations)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📝</div>
                            <p>No registrations yet</p>
                        </div>
                    <?php else: ?>
                        <div class="registrations-list">
                            <?php foreach ($recentRegistrations as $reg): ?>
                            <div class="registration-item">
                                <div class="registration-info">
                                    <div class="registration-name">
                                        <strong><?php echo sanitizeInput($reg['staff_name']); ?></strong>
                                        <span class="registration-emp"><?php echo sanitizeInput($reg['emp_number']); ?></span>
                                    </div>
                                    <div class="registration-details">
                                        <span class="detail-badge"><?php echo sanitizeInput($reg['hall_name']); ?></span>
                                        <span class="detail-badge"><?php echo sanitizeInput($reg['shift_name']); ?></span>
                                        <span class="detail-badge"><?php echo (int)$reg['attendee_count']; ?> attendees</span>
                                    </div>
                                </div>
                                <div class="registration-time">
                                    <?php echo date('M j, Y g:i A', strtotime($reg['registration_date'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Registrations Section -->
    <section id="registrations" class="admin-section">
        <div class="container">
            <div class="admin-page-header">
                <h2 class="admin-page-title">All Registrations</h2>
                <p class="admin-page-subtitle">Manage and view all event registrations</p>
            </div>

            <!-- Search and Filters -->
            <div class="admin-card">
                <div class="admin-card-content">
                    <div class="search-controls">
                        <div class="search-group">
                            <input type="text" id="searchInput" placeholder="Search by name or employee number..." class="search-input">
                            <button onclick="searchRegistrations()" class="search-button">Search</button>
                            <button onclick="clearSearch()" class="clear-button">Clear</button>
                        </div>
                        <div class="export-group">
                            <button onclick="exportRegistrations()" class="export-button">Export CSV</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registrations Table -->
            <div class="admin-card">
                <div class="admin-card-content">
                    <div id="registrationsContainer">
                        <div class="loading-state">Loading registrations...</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Settings Section -->
    <section id="settings" class="admin-section">
        <div class="container">
            <div class="admin-page-header">
                <h2 class="admin-page-title">Event Settings</h2>
                <p class="admin-page-subtitle">Configure event details</p>
            </div>

            <div class="settings-grid">
                <?php foreach ($eventSettings as $setting): ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></h3>
                    </div>
                    <div class="admin-card-content">
                        <form class="setting-form" data-setting="<?php echo sanitizeInput($setting['setting_key']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="form-group">
                                <textarea name="setting_value" class="form-control" rows="3"><?php echo sanitizeInput($setting['setting_value']); ?></textarea>
                            </div>
                            
                            <?php if ($setting['description']): ?>
                                <div class="setting-description">
                                    <?php echo sanitizeInput($setting['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="save-button">Save</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Success/Error Messages -->
    <div id="messageContainer" class="message-container"></div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
        let currentRegistrations = [];

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            setupNavigation();
            loadRegistrations();
            setupSettingsForms();
        });

        function setupNavigation() {
            const navLinks = document.querySelectorAll('.admin-nav-link');
            const sections = document.querySelectorAll('.admin-section');

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.classList.contains('logout') || this.getAttribute('href').startsWith('index.php')) return;
                    
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    
                    // Update active nav
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show target section
                    sections.forEach(s => s.classList.remove('active'));
                    document.getElementById(targetId).classList.add('active');
                });
            });
        }

        function loadRegistrations() {
            fetch('api.php?action=get_registrations')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentRegistrations = data.registrations;
                        displayRegistrations(data.registrations);
                    } else {
                        showMessage('Error loading registrations: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Failed to load registrations', 'error');
                });
        }

        function displayRegistrations(registrations) {
            const container = document.getElementById('registrationsContainer');
            
            if (registrations.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <p>No registrations found</p>
                    </div>
                `;
                return;
            }

            const table = `
                <div class="registrations-table">
                    <div class="table-header">
                        <div class="table-cell">Employee</div>
                        <div class="table-cell">Name</div>
                        <div class="table-cell">Hall</div>
                        <div class="table-cell">Shift</div>
                        <div class="table-cell">Attendees</div>
                        <div class="table-cell">Seats</div>
                        <div class="table-cell">Date</div>
                        <div class="table-cell">Actions</div>
                    </div>
                    ${registrations.map(reg => `
                        <div class="table-row">
                            <div class="table-cell">
                                <strong>${reg.emp_number}</strong>
                            </div>
                            <div class="table-cell">${reg.staff_name}</div>
                            <div class="table-cell">
                                <span class="hall-badge">${reg.hall_name}</span>
                            </div>
                            <div class="table-cell">
                                <span class="shift-badge">${reg.shift_name}</span>
                            </div>
                            <div class="table-cell">
                                <span class="attendee-count">${reg.attendee_count}</span>
                            </div>
                            <div class="table-cell">
                                <div class="seats-display">
                                    ${JSON.parse(reg.selected_seats).map(seat => 
                                        `<span class="seat-tag">${seat}</span>`
                                    ).join('')}
                                </div>
                            </div>
                            <div class="table-cell">
                                <span class="date-display">${formatDate(reg.registration_date)}</span>
                            </div>
                            <div class="table-cell">
                                <button onclick="archiveRegistration(${reg.id})" class="archive-button" title="Archive Registration">
                                    🗄️ Archive
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            container.innerHTML = table;
        }

        function archiveRegistration(regId) {
            if (!confirm('Are you sure you want to archive this registration? This will release the reserved seats and cannot be undone.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="archive_registration">
                <input type="hidden" name="registration_id" value="${regId}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function searchRegistrations() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            
            if (searchTerm === '') {
                displayRegistrations(currentRegistrations);
                return;
            }

            fetch(`api.php?action=search_registrations&search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRegistrations(data.registrations);
                    } else {
                        showMessage('Search failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    showMessage('Search failed', 'error');
                });
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            displayRegistrations(currentRegistrations);
        }

        function exportRegistrations() {
            window.open('export.php?type=csv&csrf_token=' + encodeURIComponent(csrfToken), '_blank');
        }

        function setupSettingsForms() {
            const forms = document.querySelectorAll('.setting-form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const settingKey = this.dataset.setting;
                    const formData = new FormData(this);
                    formData.append('action', 'update_setting');
                    formData.append('setting_key', settingKey);
                    
                    fetch('admin-api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage('Setting updated successfully', 'success');
                        } else {
                            showMessage('Failed to update setting: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Failed to update setting', 'error');
                    });
                });
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <span class="message-text">${message}</span>
                <button class="message-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            container.appendChild(messageDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentElement) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchRegistrations();
            }
        });
    </script>

    <style>
        .admin-header {
            background: rgba(0, 0, 0, 0.9);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFD700;
        }

        .admin-nav {
            display: flex;
            gap: 2rem;
        }

        .admin-nav-link {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            cursor: pointer;
        }

        .admin-nav-link:hover,
        .admin-nav-link.active {
            color: #FFD700;
        }

        .admin-nav-link.logout {
            color: #ef4444;
        }

        .admin-nav-link.logout:hover {
            color: #dc2626;
        }

        .admin-section {
            display: none;
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .admin-section.active {
            display: block;
        }

        .admin-page-header {
            margin-bottom: 2rem;
        }

        .admin-page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .admin-page-subtitle {
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            width: 4rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #FFD700;
        }

        .stat-label {
            color: #ffffff;
            font-weight: 500;
        }

        .stat-sub {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .admin-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
        }

        .admin-card-content {
            padding: 1.5rem;
        }

        .view-all-link {
            color: #FFD700;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .view-all-link:hover {
            color: #e6c200;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .registrations-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .registration-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .registration-name strong {
            color: #ffffff;
        }

        .registration-emp {
            color: #94a3b8;
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }

        .registration-details {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .detail-badge {
            background: rgba(255, 215, 0, 0.1);
            color: #FFD700;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .registration-time {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .search-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-group {
            display: flex;
            gap: 0.5rem;
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        .search-button, .clear-button, .export-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-button {
            background: #FFD700;
            color: #000;
        }

        .clear-button {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .export-button {
            background: #2E8BFF;
            color: #ffffff;
        }

        .registrations-table {
            display: flex;
            flex-direction: column;
            gap: 1px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .table-header {
            display: grid;
            grid-template-columns: 100px 1fr 120px 150px 80px 150px 120px 100px;
            background: rgba(255, 255, 255, 0.1);
            font-weight: 600;
            color: #FFD700;
        }

        .table-row {
            display: grid;
            grid-template-columns: 100px 1fr 120px 150px 80px 150px 120px 100px;
            background: rgba(255, 255, 255, 0.02);
        }

        .table-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .table-cell {
            padding: 0.75rem;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            color: #ffffff;
        }

        .table-cell:last-child {
            border-right: none;
        }

        .hall-badge, .shift-badge {
            background: rgba(46, 139, 255, 0.1);
            color: #2E8BFF;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .attendee-count {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .seats-display {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .seat-tag {
            background: #FFD700;
            color: #000;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 0.625rem;
            font-weight: 600;
        }

        .date-display {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .archive-button {
            background: #ef4444;
            color: #ffffff;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .archive-button:hover {
            background: #dc2626;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .setting-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-control {
            padding: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            resize: vertical;
        }

        .setting-description {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }

        .save-button {
            padding: 0.5rem 1rem;
            background: #22c55e;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .save-button:hover {
            background: #16a34a;
        }

        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .message-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
            margin-left: 1rem;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .admin-header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .admin-nav {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .table-header, .table-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .table-cell {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                justify-content: space-between;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
