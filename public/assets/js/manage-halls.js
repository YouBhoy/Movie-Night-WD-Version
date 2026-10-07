// Make the admin CSRF token available to JS
const adminCsrfToken = window.MovieNightPage.csrfToken;
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to save hall. Please try again.', 'error');
    btn.disabled = false;
    console.error('Save hall error:', error);
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
    .then(r => {
if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
return r.json();
    })
    .then(data => {
showMsg(data.message, data.success ? 'success' : 'error');
addHallBtn.disabled = false;
if (data.success) setTimeout(()=>window.location.reload(), 1000);
    })
    .catch(error => {
showMsg('Network error: Unable to add hall. Please try again.', 'error');
addHallBtn.disabled = false;
console.error('Add hall error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to save shift. Please try again.', 'error');
    btn.disabled = false;
    console.error('Save shift error:', error);
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
    .then(r => {
if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
return r.json();
    })
    .then(data => {
showMsg(data.message, data.success ? 'success' : 'error');
addShiftBtn.disabled = false;
if (data.success) setTimeout(()=>window.location.reload(), 1000);
    })
    .catch(error => {
showMsg('Network error: Unable to add shift. Please try again.', 'error');
addShiftBtn.disabled = false;
console.error('Add shift error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to restore hall. Please try again.', 'error');
    btn.disabled = false;
    console.error('Restore hall error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to restore shift. Please try again.', 'error');
    btn.disabled = false;
    console.error('Restore shift error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to deactivate hall. Please try again.', 'error');
    btn.disabled = false;
    console.error('Deactivate hall error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to deactivate shift. Please try again.', 'error');
    btn.disabled = false;
    console.error('Deactivate shift error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to delete shift. Please try again.', 'error');
    btn.disabled = false;
    console.error('Delete shift error:', error);
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
.then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
    return r.json();
})
.then(data => {
    showMsg(data.message, data.success ? 'success' : 'error');
    btn.disabled = false;
    if (data.success) setTimeout(()=>window.location.reload(), 1000);
})
.catch(error => {
    showMsg('Network error: Unable to delete hall. Please try again.', 'error');
    btn.disabled = false;
    console.error('Delete hall error:', error);
});
    };
});
