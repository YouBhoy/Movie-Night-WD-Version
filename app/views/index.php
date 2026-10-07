<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($movieName); ?> - WD Movie Night Registration</title>
    <meta name="description" content="<?php echo sanitizeInput($eventDescription); ?>">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/icons.css">
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
                    <a href="find-registration.php" class="nav-link find-registration-link">
                        <i class="fas fa-search"></i> Find Registration
                    </a>
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
                    <div class="employee-notice">
                        <i class="fas fa-users ui-icon" aria-hidden="true"></i> <strong>Employee Registration</strong> Enter your employee number to auto-fill your details and register for the movie night!
                    </div>

                    <div class="find-registration-notice">
                        <a href="find-registration.php" class="find-registration-btn">
                            <i class="fas fa-search"></i> Forgot your seats? Find your registration with employee number
                        </a>
                    </div>

                    <form id="registrationForm" class="registration-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="hall_id" id="hidden_hall_id">

                        <div class="form-group">
                            <label for="emp_number" class="form-label">Employee Number *</label>
                            <input type="text" id="emp_number" name="emp_number" class="form-control"
                                   required
                                   placeholder="Enter your employee number"
                                   title="Enter your employee number">
                            <div class="form-help">Enter your employee number to auto-fill your details</div>
                        </div>

                        <div class="form-group">
                            <label for="staff_name" class="form-label">Full Name *</label>
                            <input type="text" id="staff_name" name="staff_name" class="form-control"
                                   required minlength="2" maxlength="255"
                                   placeholder="Name will be auto-filled"
                                   readonly>
                            <div class="form-help">Name is auto-filled from employee records</div>
                        </div>

                        <div class="form-group">
                            <label for="shift_id" class="form-label">Shift *</label>
                            <select id="shift_id" name="shift_id" class="form-select" required disabled>
                                <option value="">Shift will be auto-filled</option>
                            </select>
                            <div class="form-help">Shift is auto-filled from employee records</div>
                        </div>

                        <div class="form-group" id="hall_display" style="display: none;">
                            <label class="form-label">Assigned Cinema Hall</label>
                            <div id="assigned_hall" class="assigned-hall-display"></div>
                        </div>

                        <div class="form-group">
                            <label for="attendee_count" class="form-label">Number of Attendees *</label>
                            <select id="attendee_count" name="attendee_count" class="form-select" required>
                                <option value="">Select number of attendees</option>
                                <?php for ($i = 1; $i <= $maxAttendees; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i === 1 ? 'person' : 'people'; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-help" id="attendee_help">Maximum <?php echo $maxAttendees; ?> attendees per registration</div>
                        </div>

                        <div class="form-group seat-selection-group" style="display: none;">
                            <label class="form-label">Select Your Seats *</label>
                            <div class="flexible-selection-info">
                                <strong><i class="fas fa-bullseye ui-icon" aria-hidden="true"></i> Flexible Seat Selection:</strong> Click to select your seats. We prioritize side-by-side seating, but you can choose nearby seats if needed. The system will confirm your selection if seats aren't adjacent.
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
                                    <div class="seat-demo suggested"></div>
                                    <span>Suggested</span>
                                </div>
                            </div>
                            <div id="selectedSeats" class="selected-seats-display"></div>
                            <div id="seatRecommendation" class="seat-recommendation" hidden><p id="seatRecommendationText"></p><button type="button" class="button-secondary" onclick="useSeatRecommendation()">Use recommended seats</button></div>
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
                            <li><strong>Employee-only registration</strong> - Only registered employees can participate</li>
                            <li>Enter your employee number to auto-fill your details</li>
                            <li>Each employee can register only once</li>
                            <li>Maximum <?php echo $maxAttendees; ?> attendees per registration (both halls)</li>
                            <li><strong>Flexible seat selection with smart suggestions</strong></li>
                            <li>Cinema hall assigned automatically based on your shift</li>
                            <li>Please arrive 15 minutes before screening time</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3 class="info-title">Enhanced Seat Selection</h3>
                        <div class="hall-assignment-info">
                            <div class="assignment-row">
                                <span class="assignment-label"><i class="fas fa-bullseye ui-icon" aria-hidden="true"></i> Flexible Selection:</span>
                                <div class="assignment-shifts">
                                    <span>• Prioritizes side-by-side seating</span>
                                    <span>• Allows nearby seats when needed</span>
                                    <span>• Confirms non-adjacent selections</span>
                                </div>
                            </div>
                            <div class="assignment-row">
                                <span class="assignment-label"><i class="fas fa-triangle-exclamation ui-icon" aria-hidden="true"></i> Smart Warnings:</span>
                                <div class="assignment-shifts">
                                    <span>• Warns about potential seat gaps</span>
                                    <span>• Suggests better seating options</span>
                                    <span>• Flexible for real-life needs</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3 class="info-title">Hall Assignment</h3>
                        <div class="hall-assignment-info" id="dynamic-hall-assignment">
                            <?php foreach ($halls as $hall): ?>
                            <div class="assignment-row">
                                <span class="assignment-label"><i class="fas <?php echo $hall['id'] === 1 ? 'fa-film' : 'fa-masks-theater'; ?> ui-icon" aria-hidden="true"></i> <?php echo htmlspecialchars($hall['hall_name']); ?>:</span>
                                <div class="assignment-shifts">
                                    <?php
                                    // Get shifts for this hall
                                    $hallShifts = array_filter($shifts, function($shift) use ($hall) {
                                        return $shift['hall_id'] == $hall['id'];
                                    });
                                    foreach ($hallShifts as $shift) {
                                        echo '<span>• ' . htmlspecialchars($shift['shift_name']) . '</span>';
                                    }
                                    ?>
                                    <span>• Max <?php echo $maxAttendees; ?> attendees</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                <p class="section-subtitle">An open movie experience for everyone</p>
            </div>

            <div class="about-content">
                <div class="about-text">
                    <p><?php echo sanitizeInput($eventDescription); ?></p>
                    <p>This exclusive employee screening welcomes all registered employees to join us for a memorable entertainment experience.</p>
                </div>

                <div class="about-features">
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-masks-theater ui-icon" aria-hidden="true"></i></div>
                            <h3>Premium Experience</h3>
                            <p>State-of-the-art cinema facilities with comfortable seating and superior sound quality</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-users ui-icon" aria-hidden="true"></i></div>
                            <h3>Family Friendly</h3>
                            <p>Bring your family members and enjoy quality time together</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-gift ui-icon" aria-hidden="true"></i></div>
                            <h3>Complimentary Treats</h3>
                            <p>Enjoy free popcorn, beverages, and movie theater snacks during the screening</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-handshake ui-icon" aria-hidden="true"></i></div>
                            <h3>Open Community</h3>
                            <p>Connect with others in a relaxed, fun environment</p>
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
                    <a href="admin-login.php" class="footer-link admin-link">Admin Login</a>
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
                <div class="success-icon"><i class="fas fa-circle-check ui-icon" aria-hidden="true"></i></div>
                <p>Your registration has been completed successfully.</p>
                <div id="registrationDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button-primary" onclick="window.location.href='confirmation.php'">View Confirmation</button>
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
                <div class="error-icon"><i class="fas fa-circle-xmark ui-icon" aria-hidden="true"></i></div>
                <p id="errorMessage">An error occurred during registration.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button-primary" onclick="closeModal('errorModal')">Try Again</button>
            </div>
        </div>
    </div>

    <!-- Non-Adjacent Selection Modal -->
    <div id="nonAdjacentModal" class="non-adjacent-modal">
        <div class="non-adjacent-content">
            <div class="non-adjacent-header">
                <h3><i class="fas fa-triangle-exclamation ui-icon" aria-hidden="true"></i> Non-Adjacent Seats</h3>
            </div>
            <div class="non-adjacent-body">
                <p id="nonAdjacentMessage">Some of your seats are not side-by-side.</p>
                <div class="selected-seats-preview">
                    <h4>Your Selected Seats:</h4>
                    <div class="seats-preview-list" id="nonAdjacentSeatsPreview">
                        <!-- Dynamic seat preview will be inserted here -->
                    </div>
                </div>
                <div class="seat-recommendation" data-warning-recommendation hidden><p></p><button type="button" class="button-secondary" onclick="useSeatRecommendation()">Use recommended seats</button></div>
                <div class="non-adjacent-actions">
                    <button class="non-adjacent-btn confirm" onclick="confirmNonAdjacentSelection()">Yes, Continue</button>
                    <button class="non-adjacent-btn cancel" onclick="cancelNonAdjacentSelection()">No, Choose Again</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Gap Warning Modal -->
    <div id="gapWarningModal" class="gap-warning-modal">
        <div class="gap-warning-content">
            <div class="gap-warning-header">
                <h3><i class="fas fa-triangle-exclamation ui-icon" aria-hidden="true"></i> Gap Warning</h3>
            </div>
            <div class="gap-warning-body">
                <p id="gapWarningMessage">This selection may leave a single-seat gap between reservations. Are you sure you want to continue?</p>
                <div class="seat-recommendation" data-warning-recommendation hidden><p></p><button type="button" class="button-secondary" onclick="useSeatRecommendation()">Use recommended seats</button></div>
                <div class="gap-warning-actions">
                    <button class="gap-warning-btn confirm" onclick="confirmGapSelection()">Yes, Continue</button>
                    <button class="gap-warning-btn cancel" onclick="cancelGapSelection()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/seat-selection.js"></script>
    <script>window.MovieNightPage = <?= json_encode(['shifts' => $shifts, 'halls' => $halls, 'csrfToken' => $csrfToken, 'maxAttendees' => (int)$maxAttendees, 'registrationEnabled' => (bool)$registrationEnabled], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/index.js"></script>
</body>
</html>
