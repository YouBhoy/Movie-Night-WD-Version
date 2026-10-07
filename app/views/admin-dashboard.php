<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo sanitizeInput($movieName); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons.css">
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
                <a href="seat-layout-editor.php" class="btn btn-secondary"><i class="fas fa-chair ui-icon" aria-hidden="true"></i> Seat Layout</a>
                <a href="manage-halls.php" class="btn btn-secondary" id="manageHallsBtn"><i class="fas fa-building ui-icon" aria-hidden="true"></i> Manage Halls/Shifts</a>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left ui-icon" aria-hidden="true"></i> Back to Registration</a>
                <a href="admin.php" class="btn btn-primary"><i class="fas fa-gear ui-icon" aria-hidden="true"></i> Settings</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-right-from-bracket ui-icon" aria-hidden="true"></i> Logout</a>
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
                    <button class="btn btn-secondary" onclick="refreshRegistrations()"><i class="fas fa-rotate ui-icon" aria-hidden="true"></i> Refresh</button>
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
                                <div class="no-results-icon"><i class="fas fa-clipboard-list ui-icon" aria-hidden="true"></i></div>
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

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
