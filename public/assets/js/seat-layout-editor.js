// Configuration
const csrfToken = window.MovieNightPage.csrfToken;
const halls = window.MovieNightPage.halls;
const shifts = window.MovieNightPage.shifts;

// State
let currentHallId = null;
let currentShiftId = null;
let currentSeats = [];
let gridSize = { rows: 10, cols: 10 }; // This variable is no longer used for grid size, but kept for potential future use or if other parts of the code rely on it.
let hasChanges = true;
let selectedSeatId = null;

// Track selected seat IDs for multi-delete
let selectedSeatIds = [];

// Make toggleSeatSelection globally accessible
function toggleSeatSelection(event, seatId) {
    event.stopPropagation();
    const idx = selectedSeatIds.indexOf(String(seatId));
    if (idx === -1) {
        selectedSeatIds.push(String(seatId));
    } else {
        selectedSeatIds.splice(idx, 1);
    }
    renderSeatGrid();
}

// DOM Elements
const hallSelector = document.getElementById('hallSelector');
const shiftSelector = document.getElementById('shiftSelector');
const seatGrid = document.getElementById('seatGrid');
const gridTitle = document.getElementById('gridTitle');
const saveLayoutBtn = document.getElementById('saveLayoutBtn');
const resetBtn = document.getElementById('resetBtn');
const addSeatBtn = document.getElementById('addSeatBtn');
const newRowLetter = document.getElementById('newRowLetter');
const newSeatPosition = document.getElementById('newSeatPosition');
const newSeatStatus = document.getElementById('newSeatStatus');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    updateShiftSelector();

    // New event listeners for hall and shift management
    document.getElementById('hallForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitHallForm();
    });

    document.getElementById('shiftForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitShiftForm();
    });



    document.getElementById('shiftHallSelector').addEventListener('change', function() {
        const hallId = this.value;
        document.getElementById('addShiftBtn').disabled = !hallId;
        if (hallId) {
            loadShiftsByHall(hallId);
        } else {
            document.getElementById('shiftList').innerHTML = '<div class="text-muted">Please select a hall first.</div>';
        }
    });
});

function setupEventListeners() {
    hallSelector.addEventListener('change', function() {
        currentHallId = parseInt(this.value);
        updateShiftSelector();
        if (currentHallId && currentShiftId) {
            loadSeatLayout();
        }
    });

    shiftSelector.addEventListener('change', function() {
        currentShiftId = parseInt(this.value);
        if (currentHallId && currentShiftId) {
            loadSeatLayout();
        }
    });

    saveLayoutBtn.addEventListener('click', saveLayout);
    resetBtn.addEventListener('click', resetLayout);
    addSeatBtn.addEventListener('click', addNewSeat);

    // Auto-uppercase row letter input
    newRowLetter.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
}

function updateShiftSelector() {
    shiftSelector.innerHTML = '<option value="">Select a shift</option>';
    currentShiftId = null;

    if (!currentHallId) return;

    const hallShifts = shifts.filter(shift => shift.hall_id == currentHallId);
    hallShifts.forEach(shift => {
        const option = document.createElement('option');
        option.value = shift.id;
        option.textContent = shift.shift_name;
        shiftSelector.appendChild(option);
    });
}

function loadSeatLayout() {
    if (!currentHallId || !currentShiftId) return;
    const hall = halls.find(h => h.id == currentHallId);
    const shift = shifts.find(s => s.id == currentShiftId);
    gridTitle.textContent = `${hall.hall_name} - ${shift.shift_name}`;
    seatGrid.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading"></div> Loading seats...</div>';
    fetch('admin-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_seat_layout&hall_id=${currentHallId}&shift_id=${currentShiftId}&admin_csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentSeats = data.seats || [];
            renderSeatGrid();
        } else {
            seatGrid.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 2rem;">Error loading seats</div>';
        }
    })
    .catch(error => {
        console.error('Error loading seats:', error);
        seatGrid.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 2rem;">Error loading seats</div>';
    });
}

function renderSeatGrid() {
    if (!currentHallId || !currentShiftId) return;

    // Determine grid size based on hall
    let rows = 10, cols = 10;
    const hall = halls.find(h => h.id == currentHallId);
    if (hall) {
        if (hall.hall_name.toLowerCase().includes('hall 1')) {
            rows = 12; // A-L
            cols = 11;
        } else if (hall.hall_name.toLowerCase().includes('hall 2')) {
            rows = 13; // A-M
            cols = 12;
        }
    }
    // Expand rows/cols if seat data or extraRows/extraCols require it
    const maxRowFromSeats = getMaxRowFromSeats();
    if (maxRowFromSeats + 1 > rows) rows = maxRowFromSeats + 1;
    rows += extraRows;
    const maxColFromSeats = getMaxColFromSeats();
    if (maxColFromSeats > cols) cols = maxColFromSeats;
    cols += extraCols;
    let html = '';

    // Create seat map for quick lookup
    const seatMap = {};
    currentSeats.forEach(seat => {
        if (!seatMap[seat.row_letter]) seatMap[seat.row_letter] = {};
        seatMap[seat.row_letter][seat.seat_position] = seat;
    });

    // Generate grid
    for (let row = 0; row < rows; row++) {
        const rowLetter = String.fromCharCode(65 + row); // A, B, C, etc.
        html += '<div class="seat-row">';
        html += `<div class="row-label">${rowLetter}</div>`;

        for (let col = 1; col <= cols; col++) {
            const seat = seatMap[rowLetter]?.[col];

            if (seat) {
                // Existing seat
                html += `
                    <div class="seat ${seat.status}${selectedSeatIds.includes(String(seat.id)) ? ' selected' : ''}"
                         data-seat-id="${seat.id}"
                         data-row="${rowLetter}"
                         data-col="${col}"
                         onclick="toggleSeatSelection(event, '${seat.id}')">
                        ${seat.seat_number}
                        ${seat.status === 'reserved' ? '<span class="lock-icon" style="margin-left:4px; color:#ffd700;" title="Reserved"><i class="fas fa-lock"></i></span>' : ''}
                        ${seat.status === 'blocked' ? '<span class="lock-icon" style="margin-left:4px; color:#6b7280;" title="Blocked"><i class="fas fa-ban"></i></span>' : ''}
                        ${seat.status === 'occupied' ? '<span class="lock-icon" style="margin-left:4px; color:#ef4444;" title="Occupied"><i class="fas fa-user"></i></span>' : ''}
                        <button class="delete-btn" onclick="deleteSeat('${seat.id}', event)"><i class="fas fa-times"></i></button>
                    </div>
                `;
            } else {
                // Empty seat position
                html += `
                    <div class="seat empty"
                         data-row="${rowLetter}"
                         data-col="${col}"
                         onclick="addSeatAtPosition('${rowLetter}', ${col})">
                        ${rowLetter}${col}
                    </div>
                `;
            }
        }
        html += '</div>';
    }

    seatGrid.innerHTML = html;
    hasChanges = false;
    updateSaveButton();
}

function selectSeat(event, seatId) {
    const seat = currentSeats.find(s => s.id == seatId);
    event.stopPropagation();
    if (selectedSeatId === seatId) {
        selectedSeatId = null;
    } else {
        selectedSeatId = seatId;
    }
    renderSeatGrid();
}

function toggleSeatStatus(seatId) {
    const seat = currentSeats.find(s => s.id == seatId);
    if (!seat) return;

    if (seat.status === 'occupied') {
        alert('Occupied seats are managed by bookings. Cancel the booking first.');
        return;
    }
    const statuses = ['available', 'blocked', 'reserved'];
    const currentIndex = statuses.indexOf(seat.status);
    const nextIndex = (currentIndex + 1) % statuses.length;

    seat.status = statuses[nextIndex];

    // Update visual
    const seatElement = document.querySelector(`[data-seat-id="${seatId}"]`);
    if (seatElement) {
        seatElement.className = `seat ${seat.status}`;
    }

    hasChanges = true;
    updateSaveButton();
}

function deleteSeat(seatId, event) {
    const seat = currentSeats.find(s => s.id == seatId);
    if (!seat) return;
    event.stopPropagation();
    // If seat is a new (unsaved) seat, just remove from currentSeats
    if (String(seatId).startsWith('temp_')) {
        currentSeats = currentSeats.filter(s => String(s.id) !== String(seatId));
        renderSeatGrid();
        hasChanges = true;
        updateSaveButton();
        return;
    }
    if (!confirm('Are you sure you want to delete this seat?')) return;

    fetch('seat-layout-editor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_seat&seat_id=${seatId}&admin_csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Seat could not be deleted');
        // Remove from current seats
        currentSeats = currentSeats.filter(s => s.id != seatId);
        renderSeatGrid();
        hasChanges = true;
        updateSaveButton();
    })
    .catch(error => {
        console.error('Error deleting seat:', error);
        alert(error.message || 'Error deleting seat');
    });
}

function addSeatAtPosition(rowLetter, col) {
    const seatNumber = rowLetter + col;

    // Check if seat already exists
    if (currentSeats.find(s => s.seat_number === seatNumber)) {
        alert('Seat already exists');
        return;
    }

    const newSeat = {
        id: 'temp_' + Date.now(),
        row_letter: rowLetter,
        seat_position: col,
        seat_number: seatNumber,
        status: 'available'
    };

    currentSeats.push(newSeat);
    // If the new row/col is beyond the current grid, trigger grid expansion
    const code = rowLetter.charCodeAt(0) - 65;
    let rows = 10, cols = 10;
    const hall = halls.find(h => h.id == currentHallId);
    if (hall) {
        if (hall.hall_name.toLowerCase().includes('hall 1')) { rows = 12; cols = 11; }
        else if (hall.hall_name.toLowerCase().includes('hall 2')) { rows = 13; cols = 12; }
    }
    if (code + 1 > rows + extraRows) {
        extraRows = code + 1 - rows;
    }
    if (col > cols + extraCols) {
        extraCols = col - cols;
    }
    renderSeatGrid();
    hasChanges = true;
    updateSaveButton();
}

function addNewSeat() {
    const rowLetter = newRowLetter.value.trim().toUpperCase();
    const seatPosition = parseInt(newSeatPosition.value);
    const status = newSeatStatus.value;

    if (!rowLetter || !seatPosition) {
        alert('Please enter both row letter and seat position');
        return;
    }

    if (!/^[A-Z]$/.test(rowLetter)) {
        alert('Row letter must be A-Z');
        return;
    }

    if (seatPosition < 1) {
        alert('Seat position must be positive');
        return;
    }

    if (!currentHallId || !currentShiftId) {
        alert('Please select a hall and shift first');
        return;
    }

    const seatNumber = rowLetter + seatPosition;

    // Check if seat already exists
    if (currentSeats.find(s => s.seat_number === seatNumber)) {
        alert('Seat already exists');
        return;
    }

    fetch('seat-layout-editor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_seat&hall_id=${currentHallId}&shift_id=${currentShiftId}&row_letter=${rowLetter}&seat_position=${seatPosition}&status=${status}&admin_csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Seat could not be added');
        // Clear form
        newRowLetter.value = '';
        newSeatPosition.value = '';

        // Reload layout
        loadSeatLayout();
    })
    .catch(error => {
        console.error('Error adding seat:', error);
        alert(error.message || 'Error adding seat');
    });
}

function saveLayout() {
    if (!currentHallId || !currentShiftId) return;
    const formData = new FormData();
    formData.append('action', 'save_layout');
    formData.append('hall_id', currentHallId);
    formData.append('shift_id', currentShiftId);
    formData.append('seats', JSON.stringify(currentSeats));
    formData.append('admin_csrf_token', csrfToken); // Use admin_csrf_token

    saveLayoutBtn.disabled = true;
    saveLayoutBtn.textContent = 'Saving...';

    fetch('seat-layout-editor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Parse as JSON
    .then(data => {
        if (data.success) {
            alert(data.message); // Or use showToast(data.message, 'success');
            loadSeatLayout();
        } else {
            alert(data.message); // Or use showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving layout:', error);
        alert('Error saving layout');
    })
    .finally(() => {
        saveLayoutBtn.disabled = false;
        saveLayoutBtn.textContent = 'Save Layout';
    });
}

function resetLayout() {
    if (confirm('Are you sure you want to reset the layout? This will reload the current saved layout.')) {
        loadSeatLayout();
    }
}

function updateSaveButton() {
    saveLayoutBtn.disabled = !hasChanges;
}

// Add after the seat-grid-actions div
// Add button to add a new row
const seatGridActions = document.querySelector('.seat-grid-actions');
if (seatGridActions && !document.getElementById('addRowBtn')) {
    const addRowBtn = document.createElement('button');
    addRowBtn.type = 'button';
    addRowBtn.id = 'addRowBtn';
    addRowBtn.className = 'btn btn-secondary';
    addRowBtn.innerHTML = '<i class="fas fa-plus"></i> Add Row';
    seatGridActions.appendChild(addRowBtn);
    addRowBtn.addEventListener('click', function() {
        addNewRow();
    });
}

// Add button to delete selected seats
if (seatGridActions && !document.getElementById('deleteSelectedSeatsBtn')) {
    const deleteSelectedBtn = document.createElement('button');
    deleteSelectedBtn.type = 'button';
    deleteSelectedBtn.id = 'deleteSelectedSeatsBtn';
    deleteSelectedBtn.className = 'btn btn-danger';
    deleteSelectedBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Selected Seats';
    seatGridActions.appendChild(deleteSelectedBtn);
    deleteSelectedBtn.addEventListener('click', function() {
        if (selectedSeatIds.length === 0) {
            alert('No seats selected.');
            return;
        }
        if (!confirm('Are you sure you want to delete the selected seats?')) return;
        currentSeats = currentSeats.filter(seat => !selectedSeatIds.includes(String(seat.id)));
        selectedSeatIds = [];
        renderSeatGrid();
        hasChanges = true;
        updateSaveButton();
    });
}

// Track extra rows added by the admin
let extraRows = 0;

function getMaxRowFromSeats() {
    let maxRow = 0;
    currentSeats.forEach(seat => {
        const code = seat.row_letter.charCodeAt(0) - 65;
        if (code > maxRow) maxRow = code;
    });
    return maxRow;
}

function addNewRow() {
    extraRows++;
    renderSeatGrid();
}

// Track extra columns added by the admin
let extraCols = 0;

function getMaxColFromSeats() {
    let maxCol = 0;
    currentSeats.forEach(seat => {
        if (seat.seat_position > maxCol) maxCol = seat.seat_position;
    });
    return maxCol;
}

// Add after the Add Row button
if (seatGridActions && !document.getElementById('addColBtn')) {
    const addColBtn = document.createElement('button');
    addColBtn.type = 'button';
    addColBtn.id = 'addColBtn';
    addColBtn.className = 'btn btn-secondary';
    addColBtn.innerHTML = '<i class="fas fa-plus"></i> Add Column';
    seatGridActions.appendChild(addColBtn);
    addColBtn.addEventListener('click', function() {
        addNewCol();
    });
}

function addNewCol() {
    extraCols++;
    renderSeatGrid();
}

// ===== CINEMA HALL MANAGEMENT FUNCTIONS =====

function showHallManagement() {
    document.getElementById('hallManagementSection').style.display = 'block';
    document.getElementById('shiftManagementSection').style.display = 'none';
    loadCinemaHalls();
}

function hideHallManagement() {
    document.getElementById('hallManagementSection').style.display = 'none';
}

function loadCinemaHalls() {
    fetch('admin-hall-shift-api.php?action=get_active_halls')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayHalls(data.halls);
            } else {
                showToast('Error loading halls: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error loading halls:', error);
            showToast('Error loading halls', 'error');
        });
}

function displayHalls(halls) {
    const hallList = document.getElementById('hallList');
    if (halls.length === 0) {
        hallList.innerHTML = '<div class="text-muted">No active halls found.</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-dark table-hover">';
    html += '<thead><tr><th>Hall Name</th><th>Max Attendees</th><th>Total Seats</th><th>Actions</th></tr></thead><tbody>';

    halls.forEach(hall => {
        html += `
            <tr>
                <td>${hall.hall_name}</td>
                <td>${hall.max_attendees_per_booking}</td>
                <td>${hall.total_seats}</td>
                <td>
                    <button class="btn btn-sm btn-warning me-1" onclick="editHall(${hall.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deactivateHall(${hall.id})" title="Deactivate">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    hallList.innerHTML = html;
}

function showAddHallForm() {
    document.getElementById('hallListSection').style.display = 'none';
    document.getElementById('hallFormSection').style.display = 'block';
    document.getElementById('hallFormTitle').textContent = 'Add New Cinema Hall';
    document.getElementById('hallForm').reset();
    document.getElementById('hallId').value = '';
}

function showHallList() {
    document.getElementById('hallFormSection').style.display = 'none';
    document.getElementById('hallListSection').style.display = 'block';
}

function editHall(hallId) {
    // Find hall data from the current halls array
    const hall = halls.find(h => h.id == hallId);
    if (!hall) {
        showToast('Hall not found', 'error');
        return;
    }

    document.getElementById('hallId').value = hall.id;
    document.getElementById('hallName').value = hall.hall_name;
    document.getElementById('maxAttendees').value = hall.max_attendees_per_booking || 3;
    document.getElementById('totalSeats').value = hall.total_seats || window.MovieNightPage.defaultSeatCount;

    document.getElementById('hallFormTitle').textContent = 'Edit Cinema Hall';
    showAddHallForm();
}

function deactivateHall(hallId) {
    if (!confirm('Are you sure you want to deactivate this hall? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'deactivate_hall');
    formData.append('hall_id', hallId);
    formData.append('admin_csrf_token', csrfToken);

    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Hall deactivated successfully', 'success');
            loadCinemaHalls();
            refreshDropdowns();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deactivating hall:', error);
        showToast('Error deactivating hall', 'error');
    });
}

function submitHallForm() {
    const form = document.getElementById('hallForm');
    const formData = new FormData(form);

    const hallId = document.getElementById('hallId').value;
    const action = hallId ? 'update_hall' : 'add_hall';
    formData.append('action', action);
    formData.append('admin_csrf_token', csrfToken);

    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            showHallList();
            loadCinemaHalls();
            refreshDropdowns();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving hall:', error);
        showToast('Error saving hall', 'error');
    });
}

// ===== SHIFT MANAGEMENT FUNCTIONS =====

function showShiftManagement() {
    document.getElementById('shiftManagementSection').style.display = 'block';
    document.getElementById('hallManagementSection').style.display = 'none';
    refreshDropdowns();
}

function hideShiftManagement() {
    document.getElementById('shiftManagementSection').style.display = 'none';
}

function loadShiftsByHall(hallId) {
    if (!hallId) {
        document.getElementById('shiftList').innerHTML = '<div class="text-muted">Please select a hall first.</div>';
        return;
    }

    fetch(`admin-hall-shift-api.php?action=get_shifts_by_hall&hall_id=${hallId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayShifts(data.shifts);
            } else {
                showToast('Error loading shifts: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error loading shifts:', error);
            showToast('Error loading shifts', 'error');
        });
}

function displayShifts(shifts) {
    const shiftList = document.getElementById('shiftList');
    if (shifts.length === 0) {
        shiftList.innerHTML = '<div class="text-muted">No active shifts found for this hall.</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-dark table-hover">';
    html += '<thead><tr><th>Shift Name</th><th>Code</th><th>Seat Count</th><th>Time</th><th>Actions</th></tr></thead><tbody>';

    shifts.forEach(shift => {
        const startTime = shift.start_time.substring(0, 5);
        const endTime = shift.end_time.substring(0, 5);
        html += `
            <tr>
                <td>${shift.shift_name}</td>
                <td><span class="badge bg-secondary">${shift.shift_code}</span></td>
                <td>${shift.seat_count}</td>
                <td>${startTime} - ${endTime}</td>
                <td>
                    <button class="btn btn-sm btn-warning me-1" onclick="editShift(${shift.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deactivateShift(${shift.id})" title="Deactivate">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    shiftList.innerHTML = html;
}

function showAddShiftForm() {
    const hallId = document.getElementById('shiftHallSelector').value;
    if (!hallId) {
        showToast('Please select a hall first', 'error');
        return;
    }

    document.getElementById('shiftListSection').style.display = 'none';
    document.getElementById('shiftFormSection').style.display = 'block';
    document.getElementById('shiftFormTitle').textContent = 'Add New Shift';
    document.getElementById('shiftForm').reset();
    document.getElementById('shiftId').value = '';
    document.getElementById('shiftHallId').value = hallId;
}

function showShiftList() {
    document.getElementById('shiftFormSection').style.display = 'none';
    document.getElementById('shiftListSection').style.display = 'block';
}

function editShift(shiftId) {
    const hallId = document.getElementById('shiftHallSelector').value;
    if (!hallId) {
        showToast('Please select a hall first', 'error');
        return;
    }

    // Find shift data from the current shifts array
    const shift = shifts.find(s => s.id == shiftId && s.hall_id == hallId);
    if (!shift) {
        showToast('Shift not found', 'error');
        return;
    }

    document.getElementById('shiftId').value = shift.id;
    document.getElementById('shiftHallId').value = shift.hall_id;
    document.getElementById('shiftName').value = shift.shift_name;
    document.getElementById('shiftCode').value = shift.shift_code;
    document.getElementById('seatPrefix').value = shift.seat_prefix || '';
    document.getElementById('seatCount').value = shift.seat_count || window.MovieNightPage.defaultSeatCount;
    document.getElementById('startTime').value = shift.start_time;
    document.getElementById('endTime').value = shift.end_time;

    document.getElementById('shiftFormTitle').textContent = 'Edit Shift';
    showAddShiftForm();
}

function deactivateShift(shiftId) {
    if (!confirm('Are you sure you want to deactivate this shift? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'deactivate_shift');
    formData.append('shift_id', shiftId);
    formData.append('admin_csrf_token', csrfToken);

    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Shift deactivated successfully', 'success');
            loadShiftsByHall(document.getElementById('shiftHallSelector').value);
            refreshDropdowns();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deactivating shift:', error);
        showToast('Error deactivating shift', 'error');
    });
}

function submitShiftForm() {
    const form = document.getElementById('shiftForm');
    const formData = new FormData(form);

    const shiftId = document.getElementById('shiftId').value;
    const action = shiftId ? 'update_shift' : 'add_shift';
    formData.append('action', action);
    formData.append('admin_csrf_token', csrfToken);

    fetch('admin-hall-shift-api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            showShiftList();
            loadShiftsByHall(document.getElementById('shiftHallSelector').value);
            refreshDropdowns();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error saving shift:', error);
        showToast('Error saving shift', 'error');
    });
}

// ===== UTILITY FUNCTIONS =====

function refreshDropdowns() {
    // Refresh halls dropdown
    fetch('admin-hall-shift-api.php?action=get_active_halls')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update main hall selector
                const hallSelector = document.getElementById('hallSelector');
                const currentValue = hallSelector.value;
                hallSelector.innerHTML = '<option value="">Select a cinema hall</option>';

                data.halls.forEach(hall => {
                    const option = document.createElement('option');
                    option.value = hall.id;
                    option.textContent = hall.hall_name;
                    hallSelector.appendChild(option);
                });

                if (currentValue) {
                    hallSelector.value = currentValue;
                }

                // Update shift hall selector
                const shiftHallSelector = document.getElementById('shiftHallSelector');
                const currentShiftHallValue = shiftHallSelector.value;
                shiftHallSelector.innerHTML = '<option value="">Choose a hall...</option>';

                data.halls.forEach(hall => {
                    const option = document.createElement('option');
                    option.value = hall.id;
                    option.textContent = hall.hall_name;
                    shiftHallSelector.appendChild(option);
                });

                if (currentShiftHallValue) {
                    shiftHallSelector.value = currentShiftHallValue;
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing dropdowns:', error);
        });
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    // Add to page
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    toastContainer.appendChild(toast);

    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Remove after hidden
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}
