<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WD Movie Night</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <meta name="admin-csrf-token" content="<?php echo $adminCsrfToken; ?>">
    <link rel="stylesheet" href="assets/css/icons.css">
</head>
<body>
    <div class="container">
            <!-- Header with navigation -->
            <header class="admin-header" style="background: var(--card); color: var(--text-primary); padding: 1.5rem 2rem 1rem 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2); border-bottom: 1px solid var(--border);">
                <div class="header-title" style="font-size: 2rem; font-weight: 700; letter-spacing: 2px; color: var(--secondary-color); display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-film"></i> Admin Panel
                </div>
                <nav style="display: flex; align-items: center; gap: 2rem;">
                    <ul class="tab-list" style="display: flex; gap: 1.5rem; list-style: none; margin: 0; padding: 0;">
                        <li class="tab-item<?php if ($current_tab === 'settings') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=settings" style="color: <?php echo $current_tab === 'settings' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'settings' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Event Settings</a>
                        </li>
                        <li class="tab-item<?php if ($current_tab === 'employees') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=employees" style="color: <?php echo $current_tab === 'employees' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'employees' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Employee Settings</a>
                        </li>
                        <li class="tab-item<?php if ($current_tab === 'export') echo ' active'; ?>" style="font-weight: 600;">
                            <a href="?tab=export" style="color: <?php echo $current_tab === 'export' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'export' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Export</a>
                        </li>
                        <li class="tab-item<?php if (
                            $current_tab === 'admins') echo ' active'; ?>" style="font-weight: 600;">
                            <?php if ($_SESSION['admin_role'] === 'admin'): ?>
                                <a href="?tab=admins" style="color: <?php echo $current_tab === 'admins' ? 'var(--secondary-color)' : 'var(--text-primary)'; ?>; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; background: <?php echo $current_tab === 'admins' ? 'var(--hover)' : 'transparent'; ?>; transition: background 0.2s;">Admin Users</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                    <div style="display: flex; gap: 0.75rem; margin-left: 2rem;">
                        <a href="index.php" class="btn btn-secondary" style="background: var(--hover); color: var(--text-primary); border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600; text-decoration: none;">Back to Registration</a>
                        <a href="admin-dashboard.php" class="btn btn-secondary" style="background: var(--hover); color: var(--text-primary); border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600; text-decoration: none;">Back to Dashboard</a>
                    </div>
                </nav>
            </header>
            <!-- End Header -->

            <a href="logout.php" class="logout-link">
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
                <?php if ($current_tab === 'settings'): ?>
                    <!-- Event Settings Tab -->
                    <div class="form-section" style="background: var(--card); border-radius: 16px; box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2); border: 1px solid var(--border);">
                        <h2 style="color: var(--secondary-color); font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem;"><i class="fas fa-cog"></i> Event Settings</h2>
                        <p style="color: var(--text-muted);">Click the save button next to each setting to update it individually.</p>
                        <div class="form-grid" style="gap: 2rem;">
                            <div class="form-group">
                                <label for="movie_name" style="color: var(--secondary-color); font-weight: 600;"><i class="fas fa-film ui-icon" aria-hidden="true"></i> Movie Name</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="movie_name" name="movie_name" value="<?= htmlspecialchars($settings['movie_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter movie name" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('movie_name')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="movie_time" style="color: var(--secondary-color); font-weight: 600;"><i class="fas fa-clock ui-icon" aria-hidden="true"></i> Movie Time</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="movie_time" name="movie_time" value="<?= htmlspecialchars($settings['movie_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., 7:00 PM" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('movie_time')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="movie_location" style="color: var(--secondary-color); font-weight: 600;"><i class="fas fa-building ui-icon" aria-hidden="true"></i> Venue / Cinema Hall Name</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="text" id="movie_location" name="movie_location" value="<?= htmlspecialchars($settings['movie_location'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter venue name" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('movie_location')">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="max_attendees" style="color: var(--secondary-color); font-weight: 600;"><i class="fas fa-users ui-icon" aria-hidden="true"></i> Max Attendees per Booking</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="number" id="max_attendees" name="max_attendees" min="1" max="10" value="<?= (int)($settings['max_attendees'] ?? MAX_ATTENDEES_PER_BOOKING) ?>" style="background: var(--background); color: var(--text-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; font-size: 1rem;">
                                    <button type="button" class="btn btn-primary" style="background: var(--primary-color); color: #fff; border-radius: 8px;" onclick="saveSetting('max_attendees')">
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

                            <!-- Help Section -->
                            <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                                <h4 style="color: #3b82f6; margin-bottom: 0.5rem; font-size: 1rem;">
                                    <i class="fas fa-info-circle"></i> Employee Deactivation & Seat Management
                                </h4>
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0; line-height: 1.5;">
                                    <strong>Automatic Seat Freeing:</strong> When you deactivate an employee, the system automatically:
                                </p>
                                <ul style="color: var(--text-muted); font-size: 0.9rem; margin: 0.5rem 0 0 1.5rem; line-height: 1.5;">
                                    <li>Finds their active registration (if any)</li>
                                    <li>Frees their reserved seat(s)</li>
                                    <li>Cancels their registration</li>
                                    <li>Marks the employee as inactive</li>
                                </ul>
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.5rem 0 0 0; line-height: 1.5;">
                                    <strong>Visual Indicators:</strong> Employees with active registrations are marked with <i class="fas fa-clipboard-list ui-icon" aria-hidden="true"></i> "Has Registration"
                                    to help you identify who will have their seats freed when deactivated.
                                </p>
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
                                <input type="hidden" name="admin_csrf_token" value="<?php echo $adminCsrfToken; ?>">
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addNewEmployee()">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                        </div>

                        <!-- Employees List Area -->
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                <div class="tab-nav" id="employeeTabNav">
                                    <button class="tab-btn active" id="tabActiveEmployees">Active Employees</button>
                                    <button class="tab-btn" id="tabDeactivatedEmployees">Deactivated Employees</button>
                                </div>
                                <div class="tab-nav" id="registrationFilter" style="margin-left: 2rem;">
                                    <button class="tab-btn active" id="filterAll">All</button>
                                    <button class="tab-btn" id="filterRegistered">Has Registration</button>
                                    <button class="tab-btn" id="filterNotRegistered">Not Registered</button>
                                </div>
                                <input type="text" id="employeeSearchInput" class="search-input" placeholder="Search by Employee Number..." style="max-width: 300px; margin-left: 1rem;">
                            </div>
                            <div id="employeeSummary" style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; border: 1px solid rgba(255, 255, 255, 0.1);">
                                <div style="display: flex; gap: 2rem; justify-content: space-around;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: var(--secondary-color);" id="totalEmployees">-</div>
                                        <div style="font-size: 0.875rem; color: var(--text-muted);">Total Employees</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: #22c55e;" id="activeEmployees">-</div>
                                        <div style="font-size: 0.875rem; color: var(--text-muted);">Active</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: #ef4444;" id="inactiveEmployees">-</div>
                                        <div style="font-size: 0.875rem; color: var(--text-muted);">Inactive</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: #f59e0b;" id="employeesWithRegistration">-</div>
                                        <div style="font-size: 0.875rem; color: var(--text-muted);">With Registration</div>
                                    </div>
                                </div>
                            </div>
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
                <?php elseif ($current_tab === 'admins' && $_SESSION['admin_role'] === 'admin'): ?>
                    <div class="form-section" style="background: var(--card); border-radius: 16px; box-shadow: 0 2px 16px 0 rgba(0,0,0,0.2); border: 1px solid var(--border);">
                        <h2 style="color: var(--secondary-color); font-weight: 700; letter-spacing: 1px; margin-bottom: 1rem;"><i class="fas fa-users-cog"></i> Admin Users</h2>
                        <p style="color: var(--text-muted);">Manage admin accounts. You cannot delete your own account.</p>
                        <?php
                        // Fetch all admin users
                        $adminUsers = $pdo->query("SELECT id, username, role, is_active, last_login, created_at FROM admin_users ORDER BY id")->fetchAll();
                        ?>
                        <table style="width:100%; margin-bottom:2rem;">
                            <tr style="color:var(--secondary-color); font-weight:600;"><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
                            <?php foreach ($adminUsers as $admin): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($admin['role'])) ?></td>
                                <td><?= $admin['is_active'] ? '<span class="status-badge status-success">Active</span>' : '<span class="status-badge status-danger">Inactive</span>' ?></td>
                                <td><?= $admin['last_login'] ? htmlspecialchars($admin['last_login']) : 'Never' ?></td>
                                <td>
                                    <button class="btn btn-secondary btn-edit-admin" data-id="<?= $admin['id'] ?>" data-username="<?= htmlspecialchars($admin['username']) ?>" data-role="<?= $admin['role'] ?>" data-active="<?= $admin['is_active'] ?>">Edit</button>
                                    <?php if ($admin['username'] !== $_SESSION['admin_username']): ?>
                                        <button class="btn btn-danger btn-delete-admin" data-id="<?= $admin['id'] ?>" data-username="<?= htmlspecialchars($admin['username']) ?>">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <h3 style="color:var(--secondary-color);">Add New Admin</h3>
                        <form method="POST" id="addAdminForm" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                            <input type="hidden" name="action" value="add_admin">
                            <input type="hidden" name="admin_csrf_token" value="<?= htmlspecialchars($adminCsrfToken) ?>">
                            <input type="text" name="new_admin_username" placeholder="Username" required style="padding:0.5rem; border-radius:6px;">
                            <input type="password" name="new_admin_password" placeholder="Password" required style="padding:0.5rem; border-radius:6px;">
                            <select name="new_admin_role" required style="padding:0.5rem; border-radius:6px;">
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="viewer">Viewer</option>
                            </select>
                            <label style="color:var(--text-muted); font-size:0.95em;">
                                <input type="checkbox" name="new_admin_active" value="1" checked> Active
                            </label>
                            <button type="submit" class="btn btn-primary">Add Admin</button>
                        </form>
                        <!-- Edit Admin Modal (hidden by default, shown via JS) -->
                        <div id="editAdminModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
                            <div style="background:var(--card); padding:2rem; border-radius:12px; min-width:320px; max-width:90vw;">
                                <h3 style="color:var(--secondary-color);">Edit Admin</h3>
                                <form method="POST" id="editAdminForm" style="display:flex; flex-direction:column; gap:1rem;">
                                    <input type="hidden" name="action" value="edit_admin">
                                    <input type="hidden" name="admin_csrf_token" value="<?= htmlspecialchars($adminCsrfToken) ?>">
                                    <input type="hidden" name="edit_admin_id" id="edit_admin_id">
                                    <label>Username: <input type="text" name="edit_admin_username" id="edit_admin_username" required></label>
                                    <label>Role:
                                        <select name="edit_admin_role" id="edit_admin_role" required>
                                            <option value="admin">Admin</option>
                                            <option value="manager">Manager</option>
                                            <option value="viewer">Viewer</option>
                                        </select>
                                    </label>
                                    <label>Active: <input type="checkbox" name="edit_admin_active" id="edit_admin_active" value="1"></label>
                                    <label>New Password (leave blank to keep current): <input type="password" name="edit_admin_password" id="edit_admin_password"></label>
                                    <div style="display:flex; gap:1rem;">
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                        <button type="button" class="btn btn-secondary" id="cancelEditAdmin">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script src="assets/js/admin-users.js"></script>
                    <script src="assets/js/admin-users-form.js"></script>
                <?php endif; ?>
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
                <input type="hidden" name="admin_csrf_token" value="<?php echo $adminCsrfToken; ?>">
                <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditEmployeeModal()">Cancel</button>
                </div>
            </form>
            <button onclick="closeEditEmployeeModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--secondary-color);font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
    </div>

    <script>window.MovieNightPage = <?= json_encode(['currentTab' => $current_tab], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
