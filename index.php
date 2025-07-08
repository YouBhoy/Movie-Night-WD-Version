<?php
require_once 'config.php';

// Get event settings and cinema data
$pdo = getDBConnection();

// Get cinema halls and shifts
$hallsStmt = $pdo->prepare("SELECT * FROM cinema_halls WHERE is_active = 1 ORDER BY id");
$hallsStmt->execute();
$halls = $hallsStmt->fetchAll();

$shiftsStmt = $pdo->prepare("SELECT * FROM shifts WHERE is_active = 1 ORDER BY id");
$shiftsStmt->execute();
$shifts = $shiftsStmt->fetchAll();

// Get event settings
$settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
$settingsStmt->execute();
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Default values
$movieName = $settings['movie_name'] ?? 'Super Cool Movie';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'Cinema Complex';
$eventDescription = $settings['event_description'] ?? 'Join us for an exclusive movie screening event!';
$primaryColor = $settings['primary_color'] ?? '#6366F1';
$secondaryColor = $settings['secondary_color'] ?? '#22D3EE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($movieName); ?> - WD Movie Night Registration</title>
    <meta name="description" content="<?php echo sanitizeInput($eventDescription); ?>">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: <?php echo sanitizeInput($primaryColor); ?>;
            --secondary-color: <?php echo sanitizeInput($secondaryColor); ?>;
        }
    </style>
</head>
<body class="dark-theme">
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo-text">WD</h1>
                <nav class="nav">
                    <a href="#home" class="nav-link">Home</a>
                    <a href="#register" class="nav-link">Register</a>
                    <a href="#about" class="nav-link">About</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title"><?php echo sanitizeInput($movieName); ?></h1>
                    <div class="movie-details">
                        <div class="detail-item">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value"><?php echo sanitizeInput($movieDate); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value"><?php echo sanitizeInput($movieTime); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value"><?php echo sanitizeInput($movieLocation); ?></span>
                        </div>
                    </div>
                    <p class="hero-description"><?php echo sanitizeInput($eventDescription); ?></p>
                    <a href="#register" class="cta-button">Register Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="register" class="registration-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Event Registration</h2>
                <p class="section-subtitle">Secure your seats for this exclusive screening</p>
            </div>
            
            <div class="registration-container">
                <!-- Registration Form -->
                <div class="registration-form-container">
                    <form id="registrationForm" class="registration-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="form-group">
                            <label for="emp_number" class="form-label">Employee Number *</label>
                            <input type="text" id="emp_number" name="emp_number" class="form-control" 
                                   required pattern="[A-Z0-9]{3,20}" 
                                   placeholder="e.g., WD001" 
                                   title="Employee number should be 3-20 characters, letters and numbers only">
                            <div class="form-help">Enter your Western Digital employee number</div>
                        </div>

                        <div class="form-group">
                            <label for="staff_name" class="form-label">Full Name *</label>
                            <input type="text" id="staff_name" name="staff_name" class="form-control" 
                                   required minlength="2" maxlength="255"
                                   placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="hall_id" class="form-label">Cinema Hall *</label>
                            <select id="hall_id" name="hall_id" class="form-select" required>
                                <option value="">Select a cinema hall</option>
                                <?php foreach ($halls as $hall): ?>
                                <option value="<?php echo (int)$hall['id']; ?>">
                                    <?php echo sanitizeInput($hall['hall_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="shift_id" class="form-label">Shift *</label>
                            <select id="shift_id" name="shift_id" class="form-select" required>
                                <option value="">Select a shift</option>
                                <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo (int)$shift['id']; ?>" 
                                        data-hall-id="<?php echo (int)$shift['hall_id']; ?>">
                                    <?php echo sanitizeInput($shift['shift_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="attendee_count" class="form-label">Number of Attendees *</label>
                            <select id="attendee_count" name="attendee_count" class="form-select" required>
                                <option value="">Select number of attendees</option>
                                <option value="1">1 person</option>
                                <option value="2">2 people</option>
                                <option value="3">3 people</option>
                            </select>
                            <div class="form-help">Maximum 3 attendees per registration</div>
                        </div>

                        <div class="form-group seat-selection-group" style="display: none;">
                            <label class="form-label">Select Your Seats *</label>
                            <div id="seatMap" class="seat-map">
                                <!-- Seat map will be loaded dynamically -->
                            </div>
                            <div class="seat-legend">
                                <div class="legend-item">
                                    <div class="seat-demo available"></div>
                                    <span>Available</span>
                                </div>
                                <div class="legend-item">
                                    <div class="seat-demo occupied"></div>
                                    <span>Occupied</span>
                                </div>
                                <div class="legend-item">
                                    <div class="seat-demo selected"></div>
                                    <span>Selected</span>
                                </div>
                                <div class="legend-item">
                                    <div class="seat-demo blocked"></div>
                                    <span>Blocked</span>
                                </div>
                            </div>
                            <div id="selectedSeats" class="selected-seats-display"></div>
                        </div>

                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="terms" name="terms" required>
                                <label for="terms" class="checkbox-label">
                                    I agree to the terms and conditions and confirm that all information provided is accurate *
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="submit-button" disabled>
                                <span class="button-text">Complete Registration</span>
                                <span class="button-loader" style="display: none;">Processing...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Registration Info -->
                <div class="registration-info">
                    <div class="info-card">
                        <h3 class="info-title">Registration Guidelines</h3>
                        <ul class="info-list">
                            <li>Each employee can register only once</li>
                            <li>Maximum 3 attendees per registration</li>
                            <li>Seats are assigned on a first-come, first-served basis</li>
                            <li>Please arrive 15 minutes before the screening time</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3 class="info-title">Event Details</h3>
                        <div class="event-details">
                            <div class="detail-row">
                                <span class="detail-icon">🎬</span>
                                <div class="detail-content">
                                    <strong><?php echo sanitizeInput($movieName); ?></strong>
                                    <p>Exclusive company screening</p>
                                </div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-icon">📅</span>
                                <div class="detail-content">
                                    <strong><?php echo sanitizeInput($movieDate); ?></strong>
                                    <p><?php echo sanitizeInput($movieTime); ?></p>
                                </div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-icon">📍</span>
                                <div class="detail-content">
                                    <strong><?php echo sanitizeInput($movieLocation); ?></strong>
                                    <p>Multiple cinema halls available</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">About This Event</h2>
                <p class="section-subtitle">An exclusive movie experience for WD employees and their families</p>
            </div>
            
            <div class="about-content">
                <div class="about-text">
                    <p><?php echo sanitizeInput($eventDescription); ?></p>
                    <p>This exclusive screening is part of Western Digital's employee engagement initiatives, designed to bring our team together for a memorable entertainment experience.</p>
                </div>
                
                <div class="about-features">
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon">🎭</div>
                            <h3>Premium Experience</h3>
                            <p>State-of-the-art cinema facilities with comfortable seating and superior sound quality</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">👨‍👩‍👧‍👦</div>
                            <h3>Family Friendly</h3>
                            <p>Bring your family members and enjoy quality time together outside of work</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎁</div>
                            <h3>Complimentary Treats</h3>
                            <p>Enjoy free popcorn, beverages, and movie theater snacks during the screening</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🤝</div>
                            <h3>Team Building</h3>
                            <p>Connect with colleagues in a relaxed, fun environment outside the office</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-text">
                    <p>© 2025 Western Digital – Internal Movie Night Event</p>
                </div>
                <div class="footer-links">
                    <a href="#" class="footer-link">Privacy Policy</a>
                    <a href="#" class="footer-link">Terms of Service</a>
                    <a href="#" class="footer-link">Contact Support</a>
                    <a href="admin.php?key=<?php echo ADMIN_KEY; ?>" class="footer-link admin-link">Admin</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing your registration...</p>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registration Successful!</h3>
                <button type="button" class="modal-close" onclick="closeModal('successModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="success-icon">✅</div>
                <p>Your registration has been completed successfully.</p>
                <div id="registrationDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button-primary" onclick="closeModal('successModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registration Error</h3>
                <button type="button" class="modal-close" onclick="closeModal('errorModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="error-icon">❌</div>
                <p id="errorMessage">An error occurred during registration.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button-primary" onclick="closeModal('errorModal')">Try Again</button>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const shiftsData = <?php echo json_encode($shifts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const csrfToken = '<?php echo $csrfToken; ?>';
        const MAX_ATTENDEES = <?php echo MAX_ATTENDEES_PER_BOOKING; ?>;
        
        // Registration state
        let selectedSeats = [];
        let currentHallId = null;
        let currentShiftId = null;

        // DOM Elements
        const form = document.getElementById('registrationForm');
        const hallSelect = document.getElementById('hall_id');
        const shiftSelect = document.getElementById('shift_id');
        const attendeeCountSelect = document.getElementById('attendee_count');
        const seatSelectionGroup = document.querySelector('.seat-selection-group');
        const seatMap = document.getElementById('seatMap');
        const selectedSeatsDisplay = document.getElementById('selectedSeats');
        const submitButton = document.querySelector('.submit-button');
        const termsCheckbox = document.getElementById('terms');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            setupEventListeners();
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
            // Hall selection change
            hallSelect.addEventListener('change', function() {
                const hallId = this.value;
                currentHallId = hallId;
                
                // Filter shifts based on selected hall
                filterShiftsByHall(hallId);
                resetSeatSelection();
                updateSubmitButtonState();
            });

            // Shift selection change
            shiftSelect.addEventListener('change', function() {
                const shiftId = this.value;
                currentShiftId = shiftId;
                
                if (shiftId && currentHallId) {
                    loadSeatsForHallAndShift(currentHallId, shiftId);
                } else {
                    resetSeatSelection();
                }
                updateSubmitButtonState();
            });

            // Attendee count change
            attendeeCountSelect.addEventListener('change', function() {
                const count = parseInt(this.value) || 0;
                if (count > 0 && selectedSeats.length > count) {
                    selectedSeats = selectedSeats.slice(0, count);
                    updateSeatMap();
                    updateSelectedSeatsDisplay();
                }
                updateSubmitButtonState();
            });

            // Terms checkbox
            termsCheckbox.addEventListener('change', updateSubmitButtonState);

            // Form submission
            form.addEventListener('submit', handleFormSubmission);

            // Input validation
            document.getElementById('emp_number').addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                updateSubmitButtonState();
            });

            document.getElementById('staff_name').addEventListener('input', updateSubmitButtonState);
        }

        function filterShiftsByHall(hallId) {
            const shiftOptions = shiftSelect.querySelectorAll('option');
            
            // Reset shift selection
            shiftSelect.value = '';
            
            shiftOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const optionHallId = option.dataset.hallId;
                if (!hallId || optionHallId === hallId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
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

        function renderSeatMap(seats) {
            seatMap.innerHTML = '';
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
                        seatElement.addEventListener('click', () => toggleSeat(seat));
                    }
                    
                    rowDiv.appendChild(seatElement);
                });
                
                seatMap.appendChild(rowDiv);
            });
            
            updateSelectedSeatsDisplay();
        }

        function toggleSeat(seat) {
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            
            if (attendeeCount === 0) {
                showError('Please select the number of attendees first');
                return;
            }
            
            const seatIndex = selectedSeats.findIndex(s => s.id === seat.id);
            
            if (seatIndex > -1) {
                // Remove seat
                selectedSeats.splice(seatIndex, 1);
            } else {
                // Add seat
                if (selectedSeats.length >= attendeeCount) {
                    showError(`You can only select ${attendeeCount} seat(s)`);
                    return;
                }
                
                // Check for gap prevention (adjacent seat rule)
                if (!checkSeatGapRule(seat)) {
                    return;
                }
                
                selectedSeats.push(seat);
            }
            
            updateSeatMap();
            updateSelectedSeatsDisplay();
            updateSubmitButtonState();
        }

        function checkSeatGapRule(newSeat) {
            // Get all seats in the same row
            const rowSeats = Array.from(document.querySelectorAll(`[data-row-letter="${newSeat.row_letter}"]`));
            const seatPositions = rowSeats.map(s => parseInt(s.dataset.seatPosition)).sort((a, b) => a - b);
            
            // Find adjacent seats
            const newPosition = parseInt(newSeat.seat_position);
            const leftSeat = rowSeats.find(s => parseInt(s.dataset.seatPosition) === newPosition - 1);
            const rightSeat = rowSeats.find(s => parseInt(s.dataset.seatPosition) === newPosition + 1);
            
            // Check if selecting this seat would create a single-seat gap
            let wouldCreateGap = false;
            
            if (leftSeat && leftSeat.classList.contains('available') && 
                rightSeat && (rightSeat.classList.contains('occupied') || rightSeat.classList.contains('selected'))) {
                const leftLeftSeat = rowSeats.find(s => parseInt(s.dataset.seatPosition) === newPosition - 2);
                if (leftLeftSeat && (leftLeftSeat.classList.contains('occupied') || leftLeftSeat.classList.contains('selected'))) {
                    wouldCreateGap = true;
                }
            }
            
            if (rightSeat && rightSeat.classList.contains('available') && 
                leftSeat && (leftSeat.classList.contains('occupied') || leftSeat.classList.contains('selected'))) {
                const rightRightSeat = rowSeats.find(s => parseInt(s.dataset.seatPosition) === newPosition + 2);
                if (rightRightSeat && (rightRightSeat.classList.contains('occupied') || rightRightSeat.classList.contains('selected'))) {
                    wouldCreateGap = true;
                }
            }
            
            if (wouldCreateGap) {
                showError('Please avoid leaving single-seat gaps. Select adjacent seats when possible.');
                return false;
            }
            
            return true;
        }

        function updateSeatMap() {
            document.querySelectorAll('.seat.available').forEach(seatElement => {
                const seatId = parseInt(seatElement.dataset.seatId);
                const isSelected = selectedSeats.some(seat => seat.id === seatId);
                
                if (isSelected) {
                    seatElement.classList.add('selected');
                } else {
                    seatElement.classList.remove('selected');
                }
            });
        }

        function updateSelectedSeatsDisplay() {
            if (selectedSeats.length === 0) {
                selectedSeatsDisplay.innerHTML = '<p class="no-seats">No seats selected</p>';
            } else {
                const seatNumbers = selectedSeats.map(seat => seat.seat_number).sort();
                selectedSeatsDisplay.innerHTML = `
                    <p class="selected-seats-label">Selected Seats:</p>
                    <div class="selected-seats-list">
                        ${seatNumbers.map(number => `<span class="seat-tag">${number}</span>`).join('')}
                    </div>
                `;
            }
        }

        function updateSubmitButtonState() {
            const empNumber = document.getElementById('emp_number').value.trim();
            const staffName = document.getElementById('staff_name').value.trim();
            const hallId = hallSelect.value;
            const shiftId = shiftSelect.value;
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            const termsAccepted = termsCheckbox.checked;
            const hasRequiredSeats = selectedSeats.length === attendeeCount && attendeeCount > 0;
            
            const isValid = empNumber && staffName && hallId && shiftId && 
                           attendeeCount && hasRequiredSeats && termsAccepted;
            
            submitButton.disabled = !isValid;
        }

        function handleFormSubmission(e) {
            e.preventDefault();
            
            if (selectedSeats.length === 0) {
                showError('Please select your seats');
                return;
            }
            
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            if (selectedSeats.length !== attendeeCount) {
                showError(`Please select exactly ${attendeeCount} seat(s)`);
                return;
            }
            
            showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('csrf_token', csrfToken);
            formData.append('emp_number', document.getElementById('emp_number').value.trim());
            formData.append('staff_name', document.getElementById('staff_name').value.trim());
            formData.append('hall_id', hallSelect.value);
            formData.append('shift_id', shiftSelect.value);
            formData.append('attendee_count', attendeeCount);
            formData.append('selected_seats', JSON.stringify(selectedSeats.map(seat => seat.seat_number)));
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data);
                    form.reset();
                    resetFormState();
                } else {
                    showError(data.message || 'Registration failed');
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                showError('An unexpected error occurred. Please try again.');
            })
            .finally(() => {
                showLoading(false);
            });
        }

        function resetFormState() {
            selectedSeats = [];
            currentHallId = null;
            currentShiftId = null;
            
            seatSelectionGroup.style.display = 'none';
            seatMap.innerHTML = '';
            selectedSeatsDisplay.innerHTML = '';
            
            updateSubmitButtonState();
        }

        function resetSeatSelection() {
            selectedSeats = [];
            seatSelectionGroup.style.display = 'none';
            seatMap.innerHTML = '';
            selectedSeatsDisplay.innerHTML = '';
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }

        function showSuccess(data) {
            const modal = document.getElementById('successModal');
            const detailsDiv = document.getElementById('registrationDetails');
            
            detailsDiv.innerHTML = `
                <div class="registration-summary">
                    <p><strong>Employee:</strong> ${data.registration.emp_number} - ${data.registration.staff_name}</p>
                    <p><strong>Attendees:</strong> ${data.registration.attendee_count}</p>
                    <p><strong>Seats:</strong> ${data.registration.selected_seats.join(', ')}</p>
                    <p><strong>Hall:</strong> ${data.registration.hall_name}</p>
                    <p><strong>Shift:</strong> ${data.registration.shift_name}</p>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        function showError(message) {
            const modal = document.getElementById('errorModal');
            const messageElement = document.getElementById('errorMessage');
            messageElement.textContent = message;
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>
