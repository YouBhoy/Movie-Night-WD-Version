<?php
session_start();
require_once 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

$pdo = getDBConnection();
$csrfToken = generateCSRFToken();
$adminCsrfToken = generateAdminCSRFToken();

// Get event settings
$settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
$settingsStmt->execute();
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch all halls (active and inactive), but exclude the Unassigned hall (id=0) from the editable list
$halls = $pdo->query("SELECT * FROM cinema_halls WHERE id != 0 ORDER BY is_active DESC, id")->fetchAll();
// Fetch all shifts (active and inactive)
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY is_active DESC, hall_id, id")->fetchAll();
// Fetch the Unassigned hall for display
$unassignedHall = [ 'id' => 0, 'hall_name' => 'Unassigned' ];

// Separate halls and shifts into active and deactivated
$activeHalls = array_filter($halls, function($h) { return $h['is_active']; });
$deactivatedHalls = array_filter($halls, function($h) { return !$h['is_active']; });
// Separate shifts into active and deactivated, excluding the Unassigned shift (id=0) from editable lists
$activeShifts = array_filter($shifts, function($s) { return $s['is_active'] && $s['id'] != 0; });
$deactivatedShifts = array_filter($shifts, function($s) { return !$s['is_active'] && $s['id'] != 0; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Halls & Shifts</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #ffffff;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }
        h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 2rem;
        }
        .section {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        th, td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: left;
        }
        th {
            color: #ffd700;
            font-size: 1rem;
            font-weight: 600;
        }
        td input[type="text"],
        td input[type="number"] {
            width: 90%;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            border: 1px solid #ffd700;
            background: #232946;
            color: #fff;
            font-size: 1rem;
        }
        .btn {
            padding: 0.5rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }
        .btn-primary { background: #ffd700; color: #232946; }
        .btn-primary:hover { background: #ffed4e; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn[disabled] { opacity: 0.6; cursor: not-allowed; }
        .msg { margin: 1rem 0; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 500; }
        .msg.success { background: #1e293b; color: #4ade80; border: 1px solid #4ade80; }
        .msg.error { background: #1e293b; color: #f87171; border: 1px solid #f87171; }
        .add-row { background: rgba(255,255,255,0.05); border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .add-row input { margin-right: 0.5rem; }
        .deactivated-row {
            opacity: 0.5;
            background: #2d3748;
        }
        .deactivated-label {
            color: #f87171;
            font-weight: bold;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Edit Cinema Halls & Shifts</h1>
    <a href="admin-dashboard.php" class="btn btn-secondary" style="margin-bottom:2rem;">⬅️ Back to Dashboard</a>
    <div class="section">
        <h2 style="color:#ffd700;">Cinema Halls</h2>
        <div class="tab-nav">
            <button class="tab-btn active" id="tabHallsActive">Active <span class="tab-badge"><?= count($activeHalls) ?></span></button>
            <button class="tab-btn" id="tabHallsDeactivated">Deactivated <span class="tab-badge"><?= count($deactivatedHalls) ?></span></button>
        </div>
        <div id="hallsActiveTable">
            <table>
                <tr><th>Name</th><th>Seat Count</th><th>Actions</th></tr>
                <?php foreach ($activeHalls as $hall): ?>
                <tr data-hall-id="<?= $hall['id'] ?>">
                    <td><input type="text" value="<?= htmlspecialchars($hall['hall_name']) ?>" class="hall-name"></td>
                    <td><input type="number" value="<?= (int)$hall['total_seats'] ?>" min="1" class="hall-seats"></td>
                    <td>
                        <button class="btn btn-primary btn-save-hall">Save</button>
                        <button class="btn btn-warning btn-deactivate-hall">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div class="add-row">
                <input type="text" placeholder="New Hall Name" id="newHallName">
                <input type="number" placeholder="Seats" id="newHallSeats" min="1" value="<?php echo $settings['default_seat_count'] ?? 72; ?>">
                <button class="btn btn-success" id="addHallBtn">Add Hall</button>
            </div>
        </div>
        <div id="hallsDeactivatedTable" style="display:none;">
            <table>
                <tr><th>Name</th><th>Seat Count</th><th>Actions</th></tr>
                <?php foreach ($deactivatedHalls as $hall): ?>
                <tr data-hall-id="<?= $hall['id'] ?>" class="deactivated-row">
                    <td><input type="text" value="<?= htmlspecialchars($hall['hall_name']) ?>" class="hall-name" disabled></td>
                    <td><input type="number" value="<?= (int)$hall['total_seats'] ?>" min="1" class="hall-seats" disabled></td>
                    <td>
                        <button class="btn btn-success btn-restore-hall">Restore</button>
                        <button class="btn btn-danger btn-delete-hall-full">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
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
                <tr><th>Name</th><th>Hall</th><th>Seat Count</th><th>Actions</th></tr>
                <?php foreach ($activeShifts as $shift): ?>
                <tr data-shift-id="<?= $shift['id'] ?>">
                    <td><input type="text" value="<?= htmlspecialchars($shift['shift_name']) ?>" class="shift-name"></td>
                    <td>
                        <select class="shift-hall">
                            <option value="0" <?= $shift['hall_id'] == 0 ? 'selected' : '' ?>>Unassigned</option>
                            <?php foreach ($halls as $hall): ?>
                            <option value="<?= $hall['id'] ?>" <?= $hall['id'] == $shift['hall_id'] ? 'selected' : '' ?>><?= htmlspecialchars($hall['hall_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" value="<?= (int)$shift['seat_count'] ?>" min="1" class="shift-seats"></td>
                    <td>
                        <button class="btn btn-primary btn-save-shift">Save</button>
                        <button class="btn btn-warning btn-deactivate-shift">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
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
                <tr><th>Name</th><th>Hall</th><th>Seat Count</th><th>Actions</th></tr>
                <?php foreach ($deactivatedShifts as $shift): ?>
                <tr data-shift-id="<?= $shift['id'] ?>" class="deactivated-row">
                    <td><input type="text" value="<?= htmlspecialchars($shift['shift_name']) ?>" class="shift-name" disabled></td>
                    <td>
                        <select class="shift-hall" disabled>
                            <option value="0" <?= $shift['hall_id'] == 0 ? 'selected' : '' ?>>Unassigned</option>
                            <?php foreach ($halls as $hall): ?>
                            <option value="<?= $hall['id'] ?>" <?= $hall['id'] == $shift['hall_id'] ? 'selected' : '' ?>><?= htmlspecialchars($hall['hall_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" value="<?= (int)$shift['seat_count'] ?>" min="1" class="shift-seats" disabled></td>
                    <td>
                        <button class="btn btn-success btn-restore-shift">Restore</button>
                        <button class="btn btn-danger btn-delete-shift-full">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <div id="msgBox" class="msg" style="display:none;"></div>
</div>
<style>
.tab-nav { display: flex; gap: 1rem; margin-bottom: 1rem; }
.tab-btn { background: #232946; color: #ffd700; border: none; border-radius: 8px 8px 0 0; padding: 0.5rem 1.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s; position: relative; }
.tab-btn.active { background: #ffd700; color: #232946; }
.tab-badge { background: #ef4444; color: #fff; border-radius: 12px; padding: 0 8px; font-size: 0.85em; margin-left: 0.5em; }
</style>
<script>
// Make the admin CSRF token available to JS
const adminCsrfToken = '<?= $adminCsrfToken ?>';
function showMsg(msg, type) {
    const box = document.getElementById('msgBox');
    box.textContent = msg;
    box.className = 'msg ' + type;
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 3000);
}
// Save hall
[...document.querySelectorAll('.btn-save-hall')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-hall-id');
        const name = tr.querySelector('.hall-name').value.trim();
        const seats = tr.querySelector('.hall-seats').value;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_hall&hall_id=${id}&hall_name=${encodeURIComponent(name)}&total_seats=${seats}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
// Add hall
addHallBtn.onclick = function() {
    const name = newHallName.value.trim();
    const seats = newHallSeats.value;
    if (!name || !seats) return showMsg('Enter hall name and seats','error');
    addHallBtn.disabled = true;
    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_hall&hall_name=${encodeURIComponent(name)}&total_seats=${seats}&max_attendees_per_booking=${3}&admin_csrf_token=${adminCsrfToken}`
    })
    .then(r=>r.json()).then(data => {
        showMsg(data.message, data.success ? 'success' : 'error');
        addHallBtn.disabled = false;
        if (data.success) setTimeout(()=>window.location.reload(), 1000);
    });
};
// Save shift
[...document.querySelectorAll('.btn-save-shift')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-shift-id');
        const name = tr.querySelector('.shift-name').value.trim();
        const hallId = tr.querySelector('.shift-hall').value;
        const seats = tr.querySelector('.shift-seats').value;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_shift&shift_id=${id}&shift_name=${encodeURIComponent(name)}&hall_id=${hallId}&seat_count=${seats}&shift_code=${name.replace(/\s+/g,'_').toUpperCase()}&seat_prefix=&start_time=19:00:00&end_time=22:00:00&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
// Add shift
addShiftBtn.onclick = function() {
    const name = newShiftName.value.trim();
    const hallId = newShiftHall.value;
    const seats = newShiftSeats.value;
    if (!name || !hallId || !seats) return showMsg('Enter shift name, hall, and seats','error');
    addShiftBtn.disabled = true;
    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_shift&shift_name=${encodeURIComponent(name)}&hall_id=${hallId}&seat_count=${seats}&shift_code=${name.replace(/\s+/g,'_').toUpperCase()}&seat_prefix=&start_time=19:00:00&end_time=22:00:00&admin_csrf_token=${adminCsrfToken}`
    })
    .then(r=>r.json()).then(data => {
        showMsg(data.message, data.success ? 'success' : 'error');
        addShiftBtn.disabled = false;
        if (data.success) setTimeout(()=>window.location.reload(), 1000);
    });
};
// Add JS for delete buttons
[...document.querySelectorAll('.btn-delete-hall')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-hall-id');
        const name = tr.querySelector('.hall-name').value.trim();
        if (!confirm(`Are you sure you want to delete the hall: ${name}? This cannot be undone.`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=deactivate_hall&hall_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
function bindShiftDeleteButtons() {
    document.querySelectorAll('.btn-delete-shift').forEach(btn => {
        btn.onclick = function() {
            const tr = btn.closest('tr');
            const id = tr.getAttribute('data-shift-id');
            const name = tr.querySelector('.shift-name').value.trim();
            if (!confirm(`Are you sure you want to delete the shift: ${name}? This cannot be undone.`)) return;
            btn.disabled = true;
            fetch('admin-hall-shift-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=deactivate_shift&shift_id=${id}&admin_csrf_token=${adminCsrfToken}`
            })
            .then(r=>r.json()).then(data => {
                showMsg(data.message, data.success ? 'success' : 'error');
                btn.disabled = false;
                if (data.success) setTimeout(()=>window.location.reload(), 1000);
            });
        };
    });
}
bindShiftDeleteButtons();
[...document.querySelectorAll('.btn-restore-hall')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-hall-id');
        const name = tr.querySelector('.hall-name').value.trim();
        if (!confirm(`Restore hall: ${name}?`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=restore_hall&hall_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
[...document.querySelectorAll('.btn-restore-shift')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-shift-id');
        const name = tr.querySelector('.shift-name').value.trim();
        if (!confirm(`Restore shift: ${name}?`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=restore_shift&shift_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
document.getElementById('tabHallsActive').onclick = function() {
    this.classList.add('active');
    document.getElementById('tabHallsDeactivated').classList.remove('active');
    document.getElementById('hallsActiveTable').style.display = '';
    document.getElementById('hallsDeactivatedTable').style.display = 'none';
};
document.getElementById('tabHallsDeactivated').onclick = function() {
    this.classList.add('active');
    document.getElementById('tabHallsActive').classList.remove('active');
    document.getElementById('hallsActiveTable').style.display = 'none';
    document.getElementById('hallsDeactivatedTable').style.display = '';
};
document.getElementById('tabShiftsActive').onclick = function() {
    this.classList.add('active');
    document.getElementById('tabShiftsDeactivated').classList.remove('active');
    document.getElementById('shiftsActiveTable').style.display = '';
    document.getElementById('shiftsDeactivatedTable').style.display = 'none';
    bindShiftDeleteButtons();
};
document.getElementById('tabShiftsDeactivated').onclick = function() {
    this.classList.add('active');
    document.getElementById('tabShiftsActive').classList.remove('active');
    document.getElementById('shiftsActiveTable').style.display = 'none';
    document.getElementById('shiftsDeactivatedTable').style.display = '';
};
// Update JS for deactivate button
[...document.querySelectorAll('.btn-deactivate-hall')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-hall-id');
        const name = tr.querySelector('.hall-name').value.trim();
        if (!confirm(`Are you sure you want to deactivate the hall: ${name}? This will hide it from active use but can be restored later.`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=deactivate_hall&hall_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
// Update JS for deactivate shift button
[...document.querySelectorAll('.btn-deactivate-shift')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-shift-id');
        const name = tr.querySelector('.shift-name').value.trim();
        if (!confirm(`Are you sure you want to deactivate the shift: ${name}? This will hide it from active use but can be restored later.`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=deactivate_shift&shift_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
// Add JS for fully deleting a deactivated shift
[...document.querySelectorAll('.btn-delete-shift-full')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-shift-id');
        const name = tr.querySelector('.shift-name').value.trim();
        if (!confirm(`Are you sure you want to permanently delete the shift: ${name}?\n\nAll employees with this shift will be reassigned to Unassigned. This cannot be undone.`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_shift_full&shift_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
// Add JS for fully deleting a deactivated hall
[...document.querySelectorAll('.btn-delete-hall-full')].forEach(btn => {
    btn.onclick = function() {
        const tr = btn.closest('tr');
        const id = tr.getAttribute('data-hall-id');
        const name = tr.querySelector('.hall-name').value.trim();
        if (!confirm(`Are you sure you want to permanently delete the hall: ${name}?\n\nAll registrations for this hall will be deleted. This cannot be undone.`)) return;
        btn.disabled = true;
        fetch('admin-hall-shift-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_hall_full&hall_id=${id}&admin_csrf_token=${adminCsrfToken}`
        })
        .then(r=>r.json()).then(data => {
            showMsg(data.message, data.success ? 'success' : 'error');
            btn.disabled = false;
            if (data.success) setTimeout(()=>window.location.reload(), 1000);
        });
    };
});
</script>
</body>
</html> 