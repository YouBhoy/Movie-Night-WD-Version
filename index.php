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

// Check if registration is enabled
$regEnabledStmt = $pdo->prepare("SELECT setting_value FROM event_settings WHERE setting_key = 'registration_enabled'");
$regEnabledStmt->execute();
$registrationEnabled = $regEnabledStmt->fetchColumn() === 'true';

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Default values
$movieName = $settings['movie_name'] ?? 'Thunderbolts*';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'WD Campus Cinema Complex';
$eventDescription = $settings['event_description'] ?? 'Join us for an exclusive movie screening event!';
$primaryColor = $settings['primary_color'] ?? '#FFD700';
$secondaryColor = $settings['secondary_color'] ?? '#2E8BFF';
$footerText = $settings['footer_text'] ?? '© 2025 Western Digital – Internal Movie Night Event';
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
        
        /* Sticky Header Animation */
        .header {
            transform: translateY(0);
            transition: transform 0.3s ease-in-out;
        }
        
        .header.hidden {
            transform: translateY(-100%);
        }
        
        /* Smart Suggestion Modal */
        .suggestion-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2500;
            backdrop-filter: blur(5px);
        }
        
        .suggestion-content {
            background: rgba(26, 26, 46, 0.95);
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .suggestion-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .suggestion-header h3 {
            color: #ffd700;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .suggestion-body {
            padding: 1.5rem;
            text-align: center;
        }
        
        .suggestion-body p {
            color: #cbd5e1;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .suggestion-seats {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .suggestion-seats strong {
            color: #ffd700;
        }
        
        .suggestion-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .suggestion-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .suggestion-btn.accept {
            background: #ffd700;
            color: #000;
        }
        
        .suggestion-btn.accept:hover {
            background: #e6c200;
            transform: translateY(-2px);
        }
        
        .suggestion-btn.decline {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .suggestion-btn.decline:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Row highlighting for suggestions */
        .seat-row.suggested {
            background: rgba(255, 215, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem;
            margin: 0.25rem 0;
            border: 2px solid rgba(255, 215, 0, 0.3);
        }
        
        .seat.suggested-seat {
            background: rgba(255, 215, 0, 0.3) !important;
            border-color: #ffd700 !important;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="dark-theme">
    <!-- Header -->
    <header class="header" id="mainHeader">
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
                    <?php if ($registrationEnabled): ?>
                        <a href="#register" class="cta-button">Register Now</a>
                    <?php else: ?>
                        <div class="cta-button disabled">Registration Closed</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="register" class="registration-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Event Registration</h2>
                <?php if ($registrationEnabled): ?>
                    <p class="section-subtitle">Secure your seats for this exclusive screening</p>
                <?php else: ?>
                    <p class="section-subtitle" style="color: #ef4444;">Registration is currently disabled</p>
                <?php endif; ?>
            </div>
            
            <?php if ($registrationEnabled): ?>
            <div class="registration-container">
                <!-- Registration Form -->
                <div class="registration-form-container">
                    <form id="registrationForm" class="registration-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="hall_id" id="hidden_hall_id">
                        
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
                            <label for="shift_id" class="form-label">Shift *</label>
                            <select id="shift_id" name="shift_id" class="form-select" required>
                                <option value="">Select your shift</option>
                                <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo (int)$shift['id']; ?>" 
                                        data-hall-id="<?php echo (int)$shift['hall_id']; ?>">
                                    <?php echo sanitizeInput($shift['shift_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">Your cinema hall will be automatically assigned based on your shift</div>
                        </div>

                        <div class="form-group" id="hall_display" style="display: none;">
                            <label class="form-label">Assigned Cinema Hall</label>
                            <div id="assigned_hall" class="assigned-hall-display"></div>
                        </div>

                        <div class="form-group">
                            <label for="attendee_count" class="form-label">Number of Attendees *</label>
                            <select id="attendee_count" name="attendee_count" class="form-select" required>
                                <option value="">Select number of attendees</option>
                                <option value="1">1 person</option>
                                <option value="2">2 people</option>
                                <option value="3">3 people</option>
                                <option value="4" id="option_4" style="display: none;">4 people</option>
                            </select>
                            <div class="form-help" id="attendee_help">Maximum 3 attendees per registration</div>
                        </div>

                        <div class="form-group seat-selection-group" style="display: none;">
                            <label class="form-label">Select Your Seats *</label>
                            <div class="seat-selection-info">
                                <p><strong>Important:</strong> Please select seats next to each other. No gaps allowed between your selected seats.</p>
                            </div>
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
                            <li>Maximum attendees: 3 for Hall 1, 4 for Hall 2</li>
                            <li>Select seats next to each other (no gaps)</li>
                            <li>Cinema hall assigned automatically based on shift</li>
                            <li>Please arrive 15 minutes before screening time</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3 class="info-title">Hall Assignment</h3>
                        <div class="hall-assignment-info">
                            <div class="assignment-row">
                                <span class="assignment-label">🎬 Cinema Hall 1:</span>
                                <div class="assignment-shifts">
                                    <span>• Normal Shift</span>
                                    <span>• Crew C (Day Shift)</span>
                                    <span>• Max 3 attendees</span>
                                </div>
                            </div>
                            <div class="assignment-row">
                                <span class="assignment-label">🎭 Cinema Hall 2:</span>
                                <div class="assignment-shifts">
                                    <span>• Crew A (Off/Rest Day)</span>
                                    <span>• Crew B (Off/Rest Day)</span>
                                    <span>• Max 4 attendees</span>
                                </div>
                            </div>
                        </div>
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
            <?php else: ?>
            <div class="registration-disabled">
                <div class="info-card">
                    <h3 class="info-title">Registration Currently Unavailable</h3>
                    <p>Registration for this event is currently disabled. Please check back later or contact the event organizers for more information.</p>
                </div>
            </div>
            <?php endif; ?>
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
                    <p><?php echo sanitizeInput($footerText); ?></p>
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

    <!-- Smart Suggestion Modal -->
    <div id="suggestionModal" class="suggestion-modal">
        <div class="suggestion-content">
            <div class="suggestion-header">
                <h3>🎯 Smart Seat Suggestion</h3>
            </div>
            <div class="suggestion-body">
                <p id="suggestionMessage">Row doesn't have enough consecutive seats.</p>
                <div class="suggestion-seats" id="suggestionSeats">
                    <strong>Suggested seats:</strong> <span id="suggestedSeatsList"></span>
                </div>
                <div class="suggestion-actions">
                    <button class="suggestion-btn accept" onclick="acceptSuggestion()">✅ Accept Suggestion</button>
                    <button class="suggestion-btn decline" onclick="declineSuggestion()">❌ Manual Selection</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const shiftsData = <?php echo json_encode($shifts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const csrfToken = '<?php echo $csrfToken; ?>';
        const MAX_ATTENDEES = <?php echo MAX_ATTENDEES_PER_BOOKING; ?>;
        const registrationEnabled = <?php echo $registrationEnabled ? 'true' : 'false'; ?>;
        
        // Registration state
        let selectedSeats = [];
        let currentHallId = null;
        let currentShiftId = null;
        let allSeats = [];
        let lastScrollY = 0;
        let currentSuggestion = null;

        // DOM Elements
        const form = document.getElementById('registrationForm');
        const shiftSelect = document.getElementById('shift_id');
        const attendeeCountSelect = document.getElementById('attendee_count');
        const seatSelectionGroup = document.querySelector('.seat-selection-group');
        const seatMap = document.getElementById('seatMap');
        const selectedSeatsDisplay = document.getElementById('selectedSeats');
        const submitButton = document.querySelector('.submit-button');
        const termsCheckbox = document.getElementById('terms');
        const hallDisplay = document.getElementById('hall_display');
        const assignedHall = document.getElementById('assigned_hall');
        const hiddenHallId = document.getElementById('hidden_hall_id');
        const header = document.getElementById('mainHeader');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (registrationEnabled) {
                initializeForm();
                setupEventListeners();
                initializeStickyHeader();
            }
        });

        // Feature 1: Sticky Header with Hide-on-Scroll
        function initializeStickyHeader() {
            let ticking = false;

            function updateHeader() {
                const currentScrollY = window.pageYOffset;
                
                if (currentScrollY > 100) { // Only hide after scrolling past hero
                    if (currentScrollY > lastScrollY && currentScrollY > 200) {
                        // Scrolling down - hide header
                        header.classList.add('hidden');
                    } else {
                        // Scrolling up - show header
                        header.classList.remove('hidden');
                    }
                } else {
                    // At top - always show header
                    header.classList.remove('hidden');
                }
                
                lastScrollY = currentScrollY;
                ticking = false;
            }

            function requestTick() {
                if (!ticking) {
                    requestAnimationFrame(updateHeader);
                    ticking = true;
                }
            }

            window.addEventListener('scroll', requestTick, { passive: true });
        }

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
                    
                    // Determine hall based on shift name
                    const hallId = getHallIdForShift(shiftName);
                    
                    currentHallId = hallId;
                    currentShiftId = shiftId;
                    hiddenHallId.value = hallId;
                    
                    // Update attendee count options based on hall
                    updateAttendeeCountOptions(hallId);
                    
                    // Show assigned hall
                    const hallName = hallId === 1 ? 'Cinema Hall 1' : 'Cinema Hall 2';
                    const maxAttendees = hallId === 2 ? 4 : 3;
                    assignedHall.innerHTML = `
                        <div class="hall-info">
                            <span class="hall-icon">${hallId === 1 ? '🎬' : '🎭'}</span>
                            <div class="hall-details">
                                <strong>${hallName}</strong>
                                <p>Automatically assigned for ${shiftName} (Max ${maxAttendees} attendees)</p>
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
                    updateAttendeeCountOptions(1); // Reset to default
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

        function getHallIdForShift(shiftName) {
            // Default mapping based on shift names
            if (shiftName.includes('Normal Shift') || shiftName.includes('Crew C')) {
                return 1;
            } else if (shiftName.includes('Crew A') || shiftName.includes('Crew B')) {
                return 2;
            }
            
            // Fallback to hall 1 if no match
            return 1;
        }

        function updateAttendeeCountOptions(hallId) {
            const option4 = document.getElementById('option_4');
            const attendeeHelp = document.getElementById('attendee_help');
            
            if (hallId === 2) {
                // Cinema Hall 2 allows up to 4 attendees
                option4.style.display = 'block';
                attendeeHelp.textContent = 'Maximum 4 attendees per registration';
            } else {
                // Cinema Hall 1 allows up to 3 attendees
                option4.style.display = 'none';
                attendeeHelp.textContent = 'Maximum 3 attendees per registration';
                
                // If 4 is currently selected, reset to empty
                if (attendeeCountSelect.value === '4') {
                    attendeeCountSelect.value = '';
                }
            }
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
                        seatElement.addEventListener('click', () => handleSeatClick(seat, rowLetter));
                    }
                    
                    rowDiv.appendChild(seatElement);
                });
                
                seatMap.appendChild(rowDiv);
            });
            
            updateSelectedSeatsDisplay();
        }

        // Feature 2: Smart Seat Row Auto-Suggestion
        function handleSeatClick(seat, rowLetter) {
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            
            if (attendeeCount === 0) {
                showError('Please select the number of attendees first');
                return;
            }

            // Check if this row has enough consecutive seats
            if (!hasEnoughConsecutiveSeats(rowLetter, attendeeCount)) {
                // Find alternative rows
                const suggestion = findAlternativeRow(rowLetter, attendeeCount);
                if (suggestion) {
                    showSmartSuggestion(rowLetter, suggestion);
                    return;
                } else {
                    showError(`No rows found with ${attendeeCount} consecutive seats. Please select seats manually.`);
                    return;
                }
            }

            // Proceed with normal seat selection
            toggleSeat(seat);
        }

        function hasEnoughConsecutiveSeats(rowLetter, requiredCount) {
            const rowSeats = allSeats
                .filter(seat => seat.row_letter === rowLetter && seat.status === 'available')
                .sort((a, b) => a.seat_position - b.seat_position);

            if (rowSeats.length < requiredCount) return false;

            // Check for consecutive seats
            let consecutiveCount = 1;
            for (let i = 1; i < rowSeats.length; i++) {
                if (rowSeats[i].seat_position === rowSeats[i-1].seat_position + 1) {
                    consecutiveCount++;
                    if (consecutiveCount >= requiredCount) return true;
                } else {
                    consecutiveCount = 1;
                }
            }

            return consecutiveCount >= requiredCount;
        }

        function findAlternativeRow(originalRow, requiredCount) {
            const allRows = [...new Set(allSeats.map(seat => seat.row_letter))].sort();
            const originalIndex = allRows.indexOf(originalRow);
            
            // Search nearby rows (one above, one below, expanding outward)
            for (let distance = 1; distance < allRows.length; distance++) {
                // Check row above
                const upperIndex = originalIndex - distance;
                if (upperIndex >= 0) {
                    const upperRow = allRows[upperIndex];
                    if (hasEnoughConsecutiveSeats(upperRow, requiredCount)) {
                        return {
                            row: upperRow,
                            seats: getConsecutiveSeats(upperRow, requiredCount)
                        };
                    }
                }
                
                // Check row below
                const lowerIndex = originalIndex + distance;
                if (lowerIndex < allRows.length) {
                    const lowerRow = allRows[lowerIndex];
                    if (hasEnoughConsecutiveSeats(lowerRow, requiredCount)) {
                        return {
                            row: lowerRow,
                            seats: getConsecutiveSeats(lowerRow, requiredCount)
                        };
                    }
                }
            }
            
            return null;
        }

        function getConsecutiveSeats(rowLetter, count) {
            const rowSeats = allSeats
                .filter(seat => seat.row_letter === rowLetter && seat.status === 'available')
                .sort((a, b) => a.seat_position - b.seat_position);

            // Find the first set of consecutive seats
            for (let i = 0; i <= rowSeats.length - count; i++) {
                let isConsecutive = true;
                for (let j = 1; j < count; j++) {
                    if (rowSeats[i + j].seat_position !== rowSeats[i + j - 1].seat_position + 1) {
                        isConsecutive = false;
                        break;
                    }
                }
                if (isConsecutive) {
                    return rowSeats.slice(i, i + count);
                }
            }
            
            return [];
        }

        function showSmartSuggestion(originalRow, suggestion) {
            currentSuggestion = suggestion;
            
            document.getElementById('suggestionMessage').textContent = 
                `Row ${originalRow} doesn't have enough consecutive seats. Would you like to book Row ${suggestion.row} instead?`;
            
            document.getElementById('suggestedSeatsList').textContent = 
                suggestion.seats.map(seat => seat.seat_number).join(', ');
            
            // Highlight suggested row
            highlightSuggestedRow(suggestion.row, suggestion.seats);
            
            document.getElementById('suggestionModal').style.display = 'flex';
        }

        function highlightSuggestedRow(rowLetter, seats) {
            // Remove previous highlights
            document.querySelectorAll('.seat-row.suggested').forEach(row => {
                row.classList.remove('suggested');
            });
            document.querySelectorAll('.seat.suggested-seat').forEach(seat => {
                seat.classList.remove('suggested-seat');
            });

            // Highlight suggested row
            const rowElement = document.querySelector(`[data-row-letter="${rowLetter}"]`);
            if (rowElement) {
                rowElement.classList.add('suggested');
                
                // Highlight suggested seats
                seats.forEach(seat => {
                    const seatElement = document.querySelector(`[data-seat-number="${seat.seat_number}"]`);
                    if (seatElement) {
                        seatElement.classList.add('suggested-seat');
                    }
                });
            }
        }

        function acceptSuggestion() {
            if (currentSuggestion) {
                // Clear current selection
                selectedSeats = [];
                
                // Select suggested seats
                currentSuggestion.seats.forEach(seat => {
                    selectedSeats.push(seat);
                });
                
                updateSeatMap();
                updateSelectedSeatsDisplay();
                updateSubmitButtonState();
            }
            
            closeSuggestionModal();
        }

        function declineSuggestion() {
            closeSuggestionModal();
        }

        function closeSuggestionModal() {
            document.getElementById('suggestionModal').style.display = 'none';
            currentSuggestion = null;
            
            // Remove highlights
            document.querySelectorAll('.seat-row.suggested').forEach(row => {
                row.classList.remove('suggested');
            });
            document.querySelectorAll('.seat.suggested-seat').forEach(seat => {
                seat.classList.remove('suggested-seat');
            });
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
                
                // Check if this seat can be added without creating gaps
                if (!canAddSeat(seat)) {
                    return;
                }
                
                selectedSeats.push(seat);
            }
            
            updateSeatMap();
            updateSelectedSeatsDisplay();
            updateSubmitButtonState();
        }

        function canAddSeat(newSeat) {
            // If this is the first seat, allow it
            if (selectedSeats.length === 0) {
                return true;
            }
            
            // Check if the new seat is adjacent to any existing selected seat
            const isAdjacent = selectedSeats.some(selectedSeat => {
                // Same row and adjacent position
                if (selectedSeat.row_letter === newSeat.row_letter) {
                    const posDiff = Math.abs(parseInt(selectedSeat.seat_position) - parseInt(newSeat.seat_position));
                    return posDiff === 1;
                }
                return false;
            });
            
            if (!isAdjacent) {
                showError('Please select seats next to each other. No gaps allowed between selected seats.');
                return false;
            }
            
            // Additional check: ensure all selected seats (including new one) form a continuous block
            const allSelectedSeats = [...selectedSeats, newSeat];
            
            // Group by row
            const seatsByRow = {};
            allSelectedSeats.forEach(seat =>  {
                if (!seatsByRow[seat.row_letter]) {
                    seatsByRow[seat.row_letter] = [];
                }
                seatsByRow[seat.row_letter].push(parseInt(seat.seat_position));
            });
            
            // Check each row has continuous seats
            for (const row in seatsByRow) {
                const positions = seatsByRow[row].sort((a, b) => a - b);
                for (let i = 1; i < positions.length; i++) {
                    if (positions[i] - positions[i-1] !== 1) {
                        showError('Selected seats must be next to each other with no gaps');
                        return false;
                    }
                }
            }
            
            return true;
        }

        function updateSeatMap() {
            document.querySelectorAll('.seat').forEach(seatElement => {
                const seatId = parseInt(seatElement.dataset.seatId);
                const isSelected = selectedSeats.some(seat => seat.id === seatId);
                
                seatElement.classList.toggle('selected', isSelected);
            });
        }

        function updateSelectedSeatsDisplay() {
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
            selectedSeats = [];
            seatSelectionGroup.style.display = 'none';
            seatMap.innerHTML = '';
            updateSelectedSeatsDisplay();
        }

        function updateSubmitButtonState() {
            const empNumber = document.getElementById('emp_number').value.trim();
            const staffName = document.getElementById('staff_name').value.trim();
            const shiftId = shiftSelect.value;
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            const termsAccepted = termsCheckbox.checked;
            
            const isValid = empNumber.length >= 3 && 
                           staffName.length >= 2 && 
                           shiftId && 
                           attendeeCount > 0 && 
                           selectedSeats.length === attendeeCount && 
                           termsAccepted;
            
            submitButton.disabled = !isValid;
        }

        function handleFormSubmission(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            showLoading(true);
            
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
                    showSuccessModal(data.registration);
                    form.reset();
                    resetSeatSelection();
                    updateSubmitButtonState();
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
            const empNumber = document.getElementById('emp_number').value.trim();
            const staffName = document.getElementById('staff_name').value.trim();
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            
            if (!empNumber.match(/^[A-Z0-9]{3,20}$/)) {
                showError('Invalid employee number format');
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

        function showSuccessModal(registration) {
            const modal = document.getElementById('successModal');
            const detailsDiv = document.getElementById('registrationDetails');
            
            detailsDiv.innerHTML = `
                <div class="registration-summary">
                    <div class="summary-item">
                        <strong>Employee:</strong> ${registration.emp_number} - ${registration.staff_name}
                    </div>
                    <div class="summary-item">
                        <strong>Hall:</strong> ${registration.hall_name}
                    </div>
                    <div class="summary-item">
                        <strong>Shift:</strong> ${registration.shift_name}
                    </div>
                    <div class="summary-item">
                        <strong>Attendees:</strong> ${registration.attendee_count}
                    </div>
                    <div class="summary-item">
                        <strong>Seats:</strong> ${registration.selected_seats.join(', ')}
                    </div>
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

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = show ? 'flex' : 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') || e.target.classList.contains('suggestion-modal')) {
                e.target.style.display = 'none';
                if (e.target.id === 'suggestionModal') {
                    closeSuggestionModal();
                }
            }
        });
    </script>
</body>
</html>
