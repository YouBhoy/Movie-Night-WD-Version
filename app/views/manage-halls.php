<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Halls & Shifts</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/manage-halls.css">
    <link rel="stylesheet" href="assets/css/icons.css">
</head>
<body>
<div class="container">
    <h1>Edit Cinema Halls & Shifts</h1>
    <a href="admin-dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left ui-icon" aria-hidden="true"></i> Back to Dashboard</a>
    <div class="section">
        <h2 style="color:#ffd700;">Cinema Halls</h2>
        <div class="tab-nav">
            <button class="tab-btn active" id="tabHallsActive">Active <span class="tab-badge"><?= count($activeHalls) ?></span></button>
            <button class="tab-btn" id="tabHallsDeactivated">Deactivated <span class="tab-badge"><?= count($deactivatedHalls) ?></span></button>
        </div>
        <div id="hallsActiveTable">
            <table>
                <thead>
                    <tr><th>Name</th><th>Seat Count</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($activeHalls as $hall): ?>
                <tr data-hall-id="<?= $hall['id'] ?>">
                    <td data-label="Name"><input type="text" value="<?= htmlspecialchars($hall['hall_name']) ?>" class="hall-name"></td>
                    <td data-label="Seat Count"><input type="number" value="<?= (int)$hall['total_seats'] ?>" min="1" class="hall-seats"></td>
                    <td data-label="Actions" class="actions-cell">
                        <button class="btn btn-primary btn-save-hall">Save</button>
                        <button class="btn btn-warning btn-deactivate-hall">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="add-row">
                <input type="text" placeholder="New Hall Name" id="newHallName">
                <input type="number" placeholder="Seats" id="newHallSeats" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>">
                <button class="btn btn-success" id="addHallBtn">Add Hall</button>
            </div>
        </div>
        <div id="hallsDeactivatedTable" style="display:none;">
            <table>
                <thead>
                    <tr><th>Name</th><th>Seat Count</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deactivatedHalls as $hall): ?>
                <tr data-hall-id="<?= $hall['id'] ?>" class="deactivated-row">
                    <td data-label="Name"><input type="text" value="<?= htmlspecialchars($hall['hall_name']) ?>" class="hall-name" disabled></td>
                    <td data-label="Seat Count"><input type="number" value="<?= (int)$hall['total_seats'] ?>" min="1" class="hall-seats" disabled></td>
                    <td data-label="Actions" class="actions-cell">
                        <button class="btn btn-success btn-restore-hall">Restore</button>
                        <button class="btn btn-danger btn-delete-hall-full">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="section">
        <h2 style="color:#ffd700;">Shifts</h2>
        <div class="tab-nav">
            <button class="tab-btn active" id="tabShiftsActive">Active <span class="tab-badge"><?= count($activeShifts) ?></span></button>
            <button class="tab-btn" id="tabShiftsDeactivated">Deactivated <span class="tab-badge"><?= count($deactivatedShifts) ?></span></button>
        </div>
        <div id="shiftsActiveTable">
            <table>
                <thead>
                    <tr><th>Name</th><th>Hall</th><th>Seat Count</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($activeShifts as $shift): ?>
                <tr data-shift-id="<?= $shift['id'] ?>">
                    <td data-label="Name"><input type="text" value="<?= htmlspecialchars($shift['shift_name']) ?>" class="shift-name"></td>
                    <td data-label="Hall">
                        <select class="shift-hall">
                            <option value="0" <?= $shift['hall_id'] == 0 ? 'selected' : '' ?>>Unassigned</option>
                            <?php foreach ($halls as $hall): ?>
                            <option value="<?= $hall['id'] ?>" <?= $hall['id'] == $shift['hall_id'] ? 'selected' : '' ?>><?= htmlspecialchars($hall['hall_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="Seat Count"><input type="number" value="<?= (int)$shift['seat_count'] ?>" min="1" class="shift-seats"></td>
                    <td data-label="Actions" class="actions-cell">
                        <button class="btn btn-primary btn-save-shift">Save</button>
                        <button class="btn btn-warning btn-deactivate-shift">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="add-row">
                <input type="text" placeholder="New Shift Name" id="newShiftName">
                <select id="newShiftHall">
                    <?php foreach ($halls as $hall): ?>
                    <option value="<?= $hall['id'] ?>"><?= htmlspecialchars($hall['hall_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" placeholder="Seats" id="newShiftSeats" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>">
                <button class="btn btn-success" id="addShiftBtn">Add Shift</button>
            </div>
        </div>
        <div id="shiftsDeactivatedTable" style="display:none;">
            <table>
                <thead>
                    <tr><th>Name</th><th>Hall</th><th>Seat Count</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deactivatedShifts as $shift): ?>
                <tr data-shift-id="<?= $shift['id'] ?>" class="deactivated-row">
                    <td data-label="Name"><input type="text" value="<?= htmlspecialchars($shift['shift_name']) ?>" class="shift-name" disabled></td>
                    <td data-label="Hall">
                        <select class="shift-hall" disabled>
                            <option value="0" <?= $shift['hall_id'] == 0 ? 'selected' : '' ?>>Unassigned</option>
                            <?php foreach ($halls as $hall): ?>
                            <option value="<?= $hall['id'] ?>" <?= $hall['id'] == $shift['hall_id'] ? 'selected' : '' ?>><?= htmlspecialchars($hall['hall_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="Seat Count"><input type="number" value="<?= (int)$shift['seat_count'] ?>" min="1" class="shift-seats" disabled></td>
                    <td data-label="Actions" class="actions-cell">
                        <button class="btn btn-success btn-restore-shift">Restore</button>
                        <button class="btn btn-danger btn-delete-shift-full">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="msgBox" class="msg" style="display:none;"></div>
</div>
<script>window.MovieNightPage = <?= json_encode(['csrfToken' => $adminCsrfToken], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/manage-halls.js"></script>
</body>
</html>