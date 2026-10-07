// Configuration
const shiftsData = window.MovieNightPage.shifts;
const hallsData = window.MovieNightPage.halls;
const csrfToken = window.MovieNightPage.csrfToken;
const MAX_ATTENDEES = window.MovieNightPage.maxAttendees;
const registrationEnabled = window.MovieNightPage.registrationEnabled;

// Registration state
let selectedSeats = [];
let currentHallId = null;
let currentShiftId = null;
let allSeats = [];
let seatMap = {};
let pendingGapSeat = null;
let pendingWarning = null;
let selectionBeforeWarning = [];
const acceptedSeatWarnings = new Set();
let recommendedSeats = [];
let pendingNonAdjacentSelection = false;

// DOM Elements
const form = document.getElementById('registrationForm');
const shiftSelect = document.getElementById('shift_id');
const attendeeCountSelect = document.getElementById('attendee_count');
const seatSelectionGroup = document.querySelector('.seat-selection-group');
const seatMapElement = document.getElementById('seatMap');
const selectedSeatsDisplay = document.getElementById('selectedSeats');
const submitButton = document.querySelector('.submit-button');
const termsCheckbox = document.getElementById('terms');
const hallDisplay = document.getElementById('hall_display');
const assignedHall = document.getElementById('assigned_hall');
const hiddenHallId = document.getElementById('hidden_hall_id');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (registrationEnabled) {
        initializeForm();
        setupEventListeners();
    }
});

function initializeForm() {
    // Smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    updateSubmitButtonState();
}

function setupEventListeners() {
    // Shift selection change
    shiftSelect.addEventListener('change', function() {
        const shiftId = this.value;
        if (shiftId) {
            const selectedOption = this.options[this.selectedIndex];
            const shiftName = selectedOption.textContent.trim();

            // Find the shift object from shiftsData
            const shiftObj = shiftsData.find(s => s.shift_name === shiftName);
            const hallId = shiftObj ? shiftObj.hall_id : null;

            currentHallId = hallId;
            currentShiftId = shiftId;
            hiddenHallId.value = hallId;

            // Show assigned hall
            const hall = hallsData.find(h => h.id == hallId);
            const hallName = hall ? hall.hall_name : `Cinema Hall ${hallId}`;
            const hallIcon = hallId === 1 ? 'fa-film' : 'fa-masks-theater';
            assignedHall.innerHTML = `
                <div class="hall-info">
                    <span class="hall-icon"><i class="fas ${hallIcon} ui-icon" aria-hidden="true"></i></span>
                    <div class="hall-details">
                        <strong>${hallName}</strong>
                        <p>Automatically assigned for ${shiftName} (Max ${MAX_ATTENDEES} attendees)</p>
                    </div>
                </div>
            `;
            hallDisplay.style.display = 'block';

            loadSeatsForHallAndShift(hallId, shiftId);
        } else {
            resetSeatSelection();
            hallDisplay.style.display = 'none';
            currentHallId = null;
            currentShiftId = null;
            hiddenHallId.value = '';
        }
        updateSubmitButtonState();
    });

    // Attendee count change
    attendeeCountSelect.addEventListener('change', function() {
        const count = parseInt(this.value) || 0;

        // Clear selected seats but keep the map visible
        selectedSeats = [];
        clearSeatSelections();

        // Re-render the seat map if we have seats loaded
        if (allSeats.length > 0) {
            renderSeatMap(allSeats);
            // Ensure seat selection group stays visible
            seatSelectionGroup.style.display = 'block';
        }

        updateSubmitButtonState();
    });

    // Terms checkbox
    termsCheckbox.addEventListener('change', updateSubmitButtonState);

    // Form submission
    form.addEventListener('submit', handleFormSubmission);

    // Employee number lookup
    document.getElementById('emp_number').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        updateSubmitButtonState();
    });

    // Employee number lookup on blur (when user finishes typing)
    document.getElementById('emp_number').addEventListener('blur', function() {
        const empNumber = this.value.trim();
        if (empNumber.length >= 2) {
            lookupEmployee(empNumber);
        }
    });

    // Remove staff_name input listener since it's now readonly
    // document.getElementById('staff_name').addEventListener('input', updateSubmitButtonState);
}

function lookupEmployee(empNumber) {
    if (!empNumber || empNumber.length < 2) return;

    showLoading(true);

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=check_employee&emp_number=${encodeURIComponent(empNumber)}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Auto-fill employee details
            document.getElementById('staff_name').value = data.employee.name;

            // Find and select the matching shift
            const shiftSelect = document.getElementById('shift_id');
            const shiftName = data.employee.shift;

            // Find the shift object from shiftsData
            const shiftObj = shiftsData.find(s => s.shift_name === shiftName);
            const shiftId = shiftObj ? shiftObj.id : null;
            const hallId = shiftObj ? shiftObj.hall_id : null;

            // Enable shift select and populate options
            shiftSelect.disabled = false;
            shiftSelect.innerHTML = '<option value="">Select your shift</option>';

            // Add the employee's shift as the only option
            const option = document.createElement('option');
            option.value = shiftId;
            option.textContent = shiftName;
            option.dataset.hallId = hallId;
            shiftSelect.appendChild(option);

            // Auto-select the shift
            shiftSelect.value = option.value;

            // Set current shift and hall IDs
            currentShiftId = shiftId;
            currentHallId = hallId;
            hiddenHallId.value = hallId;

            // Show assigned hall
            const hall = hallsData.find(h => h.id == hallId);
            const hallName = hall ? hall.hall_name : `Cinema Hall ${hallId}`;
            const hallIcon = hallId === 1 ? 'fa-film' : 'fa-masks-theater';
            assignedHall.innerHTML = `
                <div class="hall-info">
                    <span class="hall-icon"><i class="fas ${hallIcon} ui-icon" aria-hidden="true"></i></span>
                    <div class="hall-details">
                        <strong>${hallName}</strong>
                        <p>Automatically assigned for ${shiftName} (Max ${MAX_ATTENDEES} attendees)</p>
                    </div>
                </div>
            `;
            hallDisplay.style.display = 'block';

            loadSeatsForHallAndShift(hallId, shiftId);
        } else {
            // Clear fields and show error
            document.getElementById('staff_name').value = '';
            document.getElementById('shift_id').innerHTML = '<option value="">Shift will be auto-filled</option>';
            document.getElementById('shift_id').disabled = true;
            resetSeatSelection();
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error looking up employee:', error);
        showError('Failed to look up employee. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function loadSeatsForHallAndShift(hallId, shiftId) {
    showLoading(true);

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_seats&hall_id=${hallId}&shift_id=${shiftId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allSeats = data.seats;
            buildSeatMap(data.seats);
            renderSeatMap(data.seats);
            seatSelectionGroup.style.display = 'block';
        } else {
            showError(data.message || 'Failed to load seats');
        }
    })
    .catch(error => {
        console.error('Error loading seats:', error);
        showError('Failed to load seat information');
    })
    .finally(() => {
        showLoading(false);
    });
}

function buildSeatMap(seats) {
    seatMap = {};
    seats.forEach(seat => {
        if (!seatMap[seat.row_letter]) {
            seatMap[seat.row_letter] = {};
        }
        seatMap[seat.row_letter][seat.seat_position] = seat;
    });
}

function renderSeatMap(seats) {
    seatMapElement.innerHTML = '';
    selectedSeats = [];

    // Group seats by row
    const seatsByRow = {};
    seats.forEach(seat => {
        if (!seatsByRow[seat.row_letter]) {
            seatsByRow[seat.row_letter] = [];
        }
        seatsByRow[seat.row_letter].push(seat);
    });

    // Sort rows alphabetically
    const sortedRows = Object.keys(seatsByRow).sort();

    sortedRows.forEach(rowLetter => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';
        rowDiv.dataset.rowLetter = rowLetter;

        const rowLabel = document.createElement('div');
        rowLabel.className = 'row-label';
        rowLabel.textContent = rowLetter;
        rowDiv.appendChild(rowLabel);

        // Sort seats by position
        const rowSeats = seatsByRow[rowLetter].sort((a, b) => a.seat_position - b.seat_position);

        rowSeats.forEach(seat => {
            const seatElement = document.createElement('div');
            seatElement.className = `seat ${seat.status}`;
            seatElement.textContent = seat.seat_number;
            seatElement.dataset.seatId = seat.id;
            seatElement.dataset.seatNumber = seat.seat_number;
            seatElement.dataset.rowLetter = seat.row_letter;
            seatElement.dataset.seatPosition = seat.seat_position;

            if (seat.status === 'available') {
                seatElement.addEventListener('click', () => handleSeatClick(seat));
            }

            rowDiv.appendChild(seatElement);
        });

        seatMapElement.appendChild(rowDiv);
    });

    updateSelectedSeatsDisplay();
}

function refreshSelection() {
    updateSeatDisplay();
    updateSelectedSeatsDisplay();
    updateSubmitButtonState();
}

function evaluateSeatSelection(previous) {
    const count = Number(attendeeCountSelect.value);
    const issue = SeatSelection.issues(allSeats, selectedSeats, count).find(problem => !acceptedSeatWarnings.has(problem.key));
    if (!issue) { refreshSelection(); return; }
    selectionBeforeWarning = previous.slice();
    pendingWarning = issue;
    pendingNonAdjacentSelection = true;
    if (issue.type === 'gap') showGapWarning(issue.message);
    else showNonAdjacentModal(issue.message);
    refreshSelection();
}

function handleSeatClick(clickedSeat) {
    if (pendingWarning) return;
    const count = Number(attendeeCountSelect.value);
    if (!count) { showError('Please select the number of attendees first'); return; }
    const current = allSeats.find(seat => seat.seat_number === clickedSeat.seat_number);
    if (!current || current.status !== 'available') { showError('This seat is no longer available.'); return; }
    const previous = selectedSeats.slice();
    const index = selectedSeats.findIndex(seat => seat.seat_number === current.seat_number);
    if (index >= 0) {
        selectedSeats.splice(index, 1);
        acceptedSeatWarnings.clear();
    } else {
        if (selectedSeats.length >= count) { showError('You can only select '+count+' seats.'); return; }
        selectedSeats.push(current);
    }
    evaluateSeatSelection(previous);
}

function areSeatsAdjacent(seats) { return SeatSelection.adjacent(seats); }
function checkSingleGap(seats) {
    const gaps = SeatSelection.gaps(allSeats,seats);
    return {hasGap: gaps.length > 0, message: gaps.length ? 'Seat '+gaps[0].seat_number+' would be left alone.' : ''};
}

function showNonAdjacentModal(message) {
    document.getElementById('nonAdjacentMessage').textContent = message;
    document.getElementById('nonAdjacentSeatsPreview').textContent = selectedSeats.map(seat => seat.seat_number).join(', ');
    document.getElementById('nonAdjacentModal').style.display = 'flex';
}
function showGapWarning(message) {
    document.getElementById('gapWarningMessage').textContent = message;
    document.getElementById('gapWarningModal').style.display = 'flex';
}
function closeNonAdjacentModal() { document.getElementById('nonAdjacentModal').style.display = 'none'; }
function closeGapWarning() { document.getElementById('gapWarningModal').style.display = 'none'; }
function dismissSeatWarnings() {
    pendingWarning = null;
    pendingGapSeat = null;
    pendingNonAdjacentSelection = false;
    closeGapWarning();
    closeNonAdjacentModal();
}
function acceptSeatWarning() {
    if (!pendingWarning) return;
    acceptedSeatWarnings.add(pendingWarning.key);
    const previous = selectionBeforeWarning.slice();
    dismissSeatWarnings();
    evaluateSeatSelection(previous);
}
function cancelSeatWarning() {
    selectedSeats = selectionBeforeWarning.slice();
    dismissSeatWarnings();
    refreshSelection();
}
function confirmGapSelection() { acceptSeatWarning(); }
function confirmNonAdjacentSelection() { acceptSeatWarning(); }
function cancelGapSelection() { cancelSeatWarning(); }
function cancelNonAdjacentSelection() { cancelSeatWarning(); }

function useSeatRecommendation() {
    if (!recommendedSeats.length) return;
    const previous = selectedSeats.slice();
    selectedSeats = recommendedSeats.slice();
    acceptedSeatWarnings.clear();
    dismissSeatWarnings();
    evaluateSeatSelection(previous);
}

function updateSeatRecommendation() {
    const count = Number(attendeeCountSelect.value);
    recommendedSeats = SeatSelection.recommend(allSeats, selectedSeats, count);
    const same = recommendedSeats.length === selectedSeats.length && recommendedSeats.every(seat => selectedSeats.some(chosen => chosen.seat_number === seat.seat_number));
    const visible = count > 0 && recommendedSeats.length > 0 && !same;
    const labels = recommendedSeats.map(seat => seat.seat_number).join(', ');
    const text = 'Recommended group: '+labels+'. '+(recommendedSeats.every(seat => selectedSeats.some(chosen => chosen.seat_number === seat.seat_number)) ? '' : 'Seats together for all '+count+' attendees.');
    document.getElementById('seatRecommendation').hidden = !visible;
    document.getElementById('seatRecommendationText').textContent = text;
    document.querySelectorAll('[data-warning-recommendation]').forEach(panel => {
        panel.hidden = !visible;
        panel.querySelector('p').textContent = text;
    });
}

function clearSeatSelections() {
    acceptedSeatWarnings.clear();
    dismissSeatWarnings();
    selectedSeats = [];

    // Remove all selection classes
    document.querySelectorAll('.seat').forEach(seatElement => {
        seatElement.classList.remove('selected', 'suggested');
    });
}

function updateSeatDisplay() {
    // Clear all special classes first
    document.querySelectorAll('.seat').forEach(seatElement => {
        seatElement.classList.remove('selected', 'suggested');
    });

    // Mark selected seats
    selectedSeats.forEach(seat => {
        const seatElement = document.querySelector(`[data-seat-id="${seat.id}"]`);
        if (seatElement) {
            seatElement.classList.add('selected');
        }
    });

    // Suggest adjacent seats if we have partial selection
    const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
    if (selectedSeats.length > 0 && selectedSeats.length < attendeeCount) {
        suggestAdjacentSeats();
    }
}

function suggestAdjacentSeats() {
    const group = SeatSelection.recommend(allSeats,selectedSeats,Number(attendeeCountSelect.value));
    group.filter(seat => !selectedSeats.some(chosen => chosen.seat_number === seat.seat_number)).forEach(seat => {
        const element = document.querySelector('[data-seat-id="'+seat.id+'"]');
        if (element) element.classList.add('suggested');
    });
}

function updateSelectedSeatsDisplay() {
    updateSeatRecommendation();
    if (selectedSeats.length === 0) {
        selectedSeatsDisplay.innerHTML = '<p class="no-seats">No seats selected</p>';
    } else {
        const seatNumbers = selectedSeats
            .sort((a, b) => {
                if (a.row_letter !== b.row_letter) {
                    return a.row_letter.localeCompare(b.row_letter);
                }
                return parseInt(a.seat_position) - parseInt(b.seat_position);
            })
            .map(seat => seat.seat_number);

        selectedSeatsDisplay.innerHTML = `
            <p class="selected-seats-label">Selected Seats:</p>
            <div class="selected-seats-list">
                ${seatNumbers.map(seat => `<span class="selected-seat-tag">${seat}</span>`).join('')}
            </div>
        `;
    }
}

function resetSeatSelection() {
    acceptedSeatWarnings.clear();
    dismissSeatWarnings();
    selectedSeats = [];
    seatSelectionGroup.style.display = 'none';
    seatMapElement.innerHTML = '';
    updateSelectedSeatsDisplay();
}

function updateSubmitButtonState() {
    const empNumber = document.getElementById('emp_number').value.trim();
    const staffName = document.getElementById('staff_name').value.trim();
    const shiftId = shiftSelect.value;
    const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
    const termsAccepted = termsCheckbox.checked;

    const isValid = empNumber.length >= 2 &&
                   staffName.length >= 2 &&
                   shiftId &&
                   attendeeCount > 0 &&
                   selectedSeats.length === attendeeCount &&
                   termsAccepted &&
                   !pendingNonAdjacentSelection && !pendingGapSeat;

    submitButton.disabled = !isValid;
}

async function handleFormSubmission(e) {
    e.preventDefault();

    if (!validateForm()) {
        return;
    }

    showLoading(true);

    try {
        const response = await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_seats', hall_id:currentHallId, shift_id:currentShiftId, csrf_token:csrfToken})});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to refresh seat availability.');
        const previous = selectedSeats.slice();
        allSeats = result.seats;
        selectedSeats = previous.map(chosen => allSeats.find(seat => seat.seat_number === chosen.seat_number && seat.status === 'available')).filter(Boolean);
        const current = selectedSeats.slice();
        buildSeatMap(allSeats);
        renderSeatMap(allSeats);
        selectedSeats = current;
        refreshSelection();
        if (current.length !== previous.length) {
            acceptedSeatWarnings.clear();
            showLoading(false);
            showError('Some selected seats were taken. Available selections were kept; choose the remaining seats or use the recommendation.');
            return;
        }
        evaluateSeatSelection(previous);
        if (pendingWarning) { showLoading(false); return; }
    } catch (error) {
        showLoading(false);
        showError(error.message || 'Unable to refresh seat availability. Please try again.');
        return;
    }
    const formData = new FormData(form);
    formData.append('action', 'register');
    formData.append('selected_seats', JSON.stringify(selectedSeats.map(seat => seat.seat_number)));
    formData.append('shift_id', currentShiftId);
    formData.append('hall_id', currentHallId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                showSuccessModal();
            }
        } else {
            showError(data.message || 'Registration failed');
        }
    })
    .catch(error => {
        console.error('Registration error:', error);
        showError('Registration failed. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function validateForm() {
    if (pendingNonAdjacentSelection || pendingGapSeat) return false;
    const empNumber = document.getElementById('emp_number').value.trim();
    const staffName = document.getElementById('staff_name').value.trim();
    const attendeeCount = parseInt(attendeeCountSelect.value) || 0;

    if (empNumber.length < 2) {
        showError('Employee number must be at least 2 characters');
        return false;
    }

    if (staffName.length < 2) {
        showError('Name must be at least 2 characters long');
        return false;
    }

    if (selectedSeats.length !== attendeeCount) {
        showError(`Please select exactly ${attendeeCount} seat(s)`);
        return false;
    }

    return true;
}

function showSuccessModal() {
    const modal = document.getElementById('successModal');
    modal.style.display = 'flex';
}

function showError(message) {
    const modal = document.getElementById('errorModal');
    const messageElement = document.getElementById('errorMessage');
    messageElement.textContent = message;
    modal.style.display = 'flex';
}

function showSuccess(message) {
    // Create a temporary success notification
    const notification = document.createElement('div');
    notification.className = 'success-notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #22c55e;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 3000;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = show ? 'flex' : 'none';
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('non-adjacent-modal')) {
        cancelNonAdjacentSelection();
    } else if (e.target.classList.contains('gap-warning-modal')) {
        cancelGapSelection();
    } else if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});
