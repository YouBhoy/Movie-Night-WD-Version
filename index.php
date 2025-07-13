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
$registrationEnabled = isRegistrationEnabled();

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Helper function to convert hex to RGB
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

// Default values
$movieName = $settings['movie_name'] ?? 'Thunderbolts*';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'WD Campus Cinema Complex';
$eventDescription = $settings['event_description'] ?? 'Join us for an exclusive movie screening event!';
$primaryColor = $settings['primary_color'] ?? '#FFD700';
$secondaryColor = $settings['secondary_color'] ?? '#2E8BFF';
$companyName = $settings['company_name'] ?? 'Western Digital';
$footerText = $settings['footer_text'] ?? "© 2025 {$companyName} – Internal Movie Night Event";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: <?php echo sanitizeInput($primaryColor); ?>;
            --secondary-color: <?php echo sanitizeInput($secondaryColor); ?>;
            --primary-color-rgb: <?php 
                $primaryRGB = hexToRgb($primaryColor);
                echo $primaryRGB ? $primaryRGB : '255, 215, 0';
            ?>;
            --secondary-color-rgb: <?php 
                $secondaryRGB = hexToRgb($secondaryColor);
                echo $secondaryRGB ? $secondaryRGB : '46, 139, 255';
            ?>;
        }
        
        /* Enhanced Seat Selection Styling */
        .seat.selected {
            background: #22c55e !important;
            border-color: #16a34a !important;
            color: white !important;
        }
        
        .seat.suggested {
            background: rgba(var(--primary-color-rgb), 0.4) !important;
            border-color: var(--primary-color) !important;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Non-Adjacent Warning Modal */
        .non-adjacent-modal {
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
        
        .non-adjacent-content {
            background: rgba(26, 26, 46, 0.95);
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .non-adjacent-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .non-adjacent-header h3 {
            color: #f59e0b;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .non-adjacent-body {
            padding: 1.5rem;
            text-align: center;
        }
        
        .non-adjacent-body p {
            color: #cbd5e1;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .selected-seats-preview {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .selected-seats-preview h4 {
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .seats-preview-list {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .preview-seat {
            background: #22c55e;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .non-adjacent-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        .non-adjacent-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .non-adjacent-btn.confirm {
            background: #22c55e;
            color: white;
        }
        
        .non-adjacent-btn.confirm:hover {
            background: #16a34a;
        }
        
        .non-adjacent-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .non-adjacent-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Find My Registration Button */
        .find-registration-link {
            color: #fff;
            background: none;
            border: none;
            border-radius: 0;
            font-weight: 400;
            padding: 0 16px;
            margin: 0 5px;
            transition: color 0.2s;
        }
        .find-registration-link:hover,
        .find-registration-link:focus {
            color: var(--primary-color);
            background: none;
            box-shadow: none;
            text-decoration: underline;
        }
        .find-registration-link i {
            display: none;
        }
        
        /* Find Registration Notice */
        .find-registration-notice {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .find-registration-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--primary-color);
            text-decoration: none;
            border: 1px solid rgba(var(--primary-color-rgb), 0.3);
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .find-registration-btn:hover {
            background: rgba(var(--primary-color-rgb), 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
        }
        
        .find-registration-btn i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        /* Gap Warning Modal */
        .gap-warning-modal {
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
        
        .gap-warning-content {
            background: rgba(26, 26, 46, 0.95);
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .gap-warning-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .gap-warning-header h3 {
            color: #f59e0b;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .gap-warning-body {
            padding: 1.5rem;
            text-align: center;
        }
        
        .gap-warning-body p {
            color: #cbd5e1;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .gap-warning-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        .gap-warning-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .gap-warning-btn.confirm {
            background: #f59e0b;
            color: #000;
        }
        
        .gap-warning-btn.confirm:hover {
            background: #d97706;
        }
        
        .gap-warning-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gap-warning-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .flexible-selection-info {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            padding: 0.75rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 0.9rem;
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
                        👥 <strong>Employee Registration</strong> Enter your employee number to auto-fill your details and register for the movie night!
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
                                <option value="1">1 person</option>
                                <option value="2">2 people</option>
                                <option value="3">3 people</option>
                            </select>
                            <div class="form-help" id="attendee_help">Maximum 3 attendees per registration</div>
                        </div>

                        <div class="form-group seat-selection-group" style="display: none;">
                            <label class="form-label">Select Your Seats *</label>
                            <div class="flexible-selection-info">
                                <strong>🎯 Flexible Seat Selection:</strong> Click to select your seats. We prioritize side-by-side seating, but you can choose nearby seats if needed. The system will confirm your selection if seats aren't adjacent.
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
                            <li>Maximum 3 attendees per registration (both halls)</li>
                            <li><strong>Flexible seat selection with smart suggestions</strong></li>
                            <li>Cinema hall assigned automatically based on your shift</li>
                            <li>Please arrive 15 minutes before screening time</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3 class="info-title">Enhanced Seat Selection</h3>
                        <div class="hall-assignment-info">
                            <div class="assignment-row">
                                <span class="assignment-label">🎯 Flexible Selection:</span>
                                <div class="assignment-shifts">
                                    <span>• Prioritizes side-by-side seating</span>
                                    <span>• Allows nearby seats when needed</span>
                                    <span>• Confirms non-adjacent selections</span>
                                </div>
                            </div>
                            <div class="assignment-row">
                                <span class="assignment-label">⚠️ Smart Warnings:</span>
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
                                <span class="assignment-label"><?php echo $hall['id'] === 1 ? '🎬' : '🎭'; ?> <?php echo htmlspecialchars($hall['hall_name']); ?>:</span>
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
                                    <span>• Max 3 attendees</span>
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
                            <div class="feature-icon">🎭</div>
                            <h3>Premium Experience</h3>
                            <p>State-of-the-art cinema facilities with comfortable seating and superior sound quality</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">👨‍👩‍👧‍👦</div>
                            <h3>Family Friendly</h3>
                            <p>Bring your family members and enjoy quality time together</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎁</div>
                            <h3>Complimentary Treats</h3>
                            <p>Enjoy free popcorn, beverages, and movie theater snacks during the screening</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🤝</div>
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
                <div class="success-icon">✅</div>
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
                <div class="error-icon">❌</div>
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
                <h3>⚠️ Non-Adjacent Seats</h3>
            </div>
            <div class="non-adjacent-body">
                <p>Some of your seats are not side-by-side. Do you want to continue with this selection?</p>
                <div class="selected-seats-preview">
                    <h4>Your Selected Seats:</h4>
                    <div class="seats-preview-list" id="nonAdjacentSeatsPreview">
                        <!-- Dynamic seat preview will be inserted here -->
                    </div>
                </div>
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
                <h3>⚠️ Gap Warning</h3>
            </div>
            <div class="gap-warning-body">
                <p id="gapWarningMessage">This selection may leave a single-seat gap between reservations. Are you sure you want to continue?</p>
                <div class="gap-warning-actions">
                    <button class="gap-warning-btn confirm" onclick="confirmGapSelection()">Yes, Continue</button>
                    <button class="gap-warning-btn cancel" onclick="cancelGapSelection()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const shiftsData = <?php echo json_encode($shifts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const hallsData = <?php echo json_encode($halls, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const csrfToken = '<?php echo $csrfToken; ?>';
        const MAX_ATTENDEES = <?php echo MAX_ATTENDEES_PER_BOOKING; ?>;
        const registrationEnabled = <?php echo $registrationEnabled ? 'true' : 'false'; ?>;
        
        // Registration state
        let selectedSeats = [];
        let currentHallId = null;
        let currentShiftId = null;
        let allSeats = [];
        let seatMap = {};
        let pendingGapSeat = null;
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
                    
                    // Determine hall based on shift name
                    const hallId = getHallIdForShift(shiftName);
                    
                    currentHallId = hallId;
                    currentShiftId = shiftId;
                    hiddenHallId.value = hallId;
                    
                    // Show assigned hall
                    const hall = hallsData.find(h => h.id == hallId);
                    const hallName = hall ? hall.hall_name : `Cinema Hall ${hallId}`;
                    const hallIcon = hallId === 1 ? '🎬' : '🎭';
                    assignedHall.innerHTML = `
                        <div class="hall-info">
                            <span class="hall-icon">${hallIcon}</span>
                            <div class="hall-details">
                                <strong>${hallName}</strong>
                                <p>Automatically assigned for ${shiftName} (Max 3 attendees)</p>
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
                    
                    // Enable shift select and populate options
                    shiftSelect.disabled = false;
                    shiftSelect.innerHTML = '<option value="">Select your shift</option>';
                    
                    // Add the employee's shift as the only option
                    const option = document.createElement('option');
                    option.value = getShiftIdByName(shiftName);
                    option.textContent = shiftName;
                    option.dataset.hallId = getHallIdForShift(shiftName);
                    shiftSelect.appendChild(option);
                    
                    // Auto-select the shift
                    shiftSelect.value = option.value;
                    
                    // Trigger shift change event to load seats
                    const event = new Event('change');
                    shiftSelect.dispatchEvent(event);
                    
                    showSuccess('Employee details loaded successfully!');
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

        function getShiftIdByName(shiftName) {
            // Find the shift ID from the shifts data
            const shift = shiftsData.find(s => s.shift_name === shiftName);
            return shift ? shift.id : null;
        }

        function getHallIdForShift(shiftName) {
            // Updated mapping based on shift names
            if (shiftName.includes('Normal Shift') || shiftName.includes('Crew C')) {
                return 1;
            } else if (shiftName.includes('Crew A') || shiftName.includes('Crew B')) {
                return 2;
            }
            
            // Fallback to hall 1 if no match
            return 1;
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

        function handleSeatClick(clickedSeat) {
            const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
            
            if (attendeeCount === 0) {
                showError('Please select the number of attendees first');
                return;
            }

            // Check if seat is already selected
            const seatIndex = selectedSeats.findIndex(s => s.id === clickedSeat.id);
            if (seatIndex > -1) {
                // Deselect seat
                selectedSeats.splice(seatIndex, 1);
                updateSeatDisplay();
                updateSelectedSeatsDisplay();
                updateSubmitButtonState();
                return;
            }

            // Check if we can add more seats
            if (selectedSeats.length >= attendeeCount) {
                showError(`You can only select ${attendeeCount} seat(s).`);
                return;
            }

            // Add seat to selection
            selectedSeats.push(clickedSeat);
            
            // Check if selection is complete
            if (selectedSeats.length === attendeeCount) {
                // Check for gaps first
                const gapCheck = checkSingleGap(selectedSeats);
                if (gapCheck.hasGap) {
                    // Remove the last seat temporarily
                    pendingGapSeat = selectedSeats.pop();
                    showGapWarning(gapCheck.message);
                    return;
                }
                
                // Check if seats are adjacent (side-by-side or nearby)
                if (!areSeatsAdjacent(selectedSeats)) {
                    // Show non-adjacent confirmation
                    showNonAdjacentModal();
                    return;
                }
            }
            
            updateSeatDisplay();
            updateSelectedSeatsDisplay();
            updateSubmitButtonState();
        }

        // Enhanced adjacency check - more flexible
        function areSeatsAdjacent(seats) {
            if (seats.length <= 1) return true;
            
            // Check if all seats are side-by-side in the same row
            const sameRowSeats = seats.filter(seat => seat.row_letter === seats[0].row_letter);
            if (sameRowSeats.length === seats.length) {
                // All in same row - check if they're consecutive
                const positions = sameRowSeats.map(s => parseInt(s.seat_position)).sort((a, b) => a - b);
                for (let i = 1; i < positions.length; i++) {
                    if (positions[i] - positions[i-1] > 2) { // Allow 1 seat gap
                        return false;
                    }
                }
                return true;
            }
            
            // Check if seats are in adjacent rows and nearby positions
            const rowLetters = [...new Set(seats.map(s => s.row_letter))].sort();
            if (rowLetters.length <= 2) {
                // Check if rows are adjacent
                if (rowLetters.length === 2) {
                    const rowDiff = Math.abs(rowLetters[1].charCodeAt(0) - rowLetters[0].charCodeAt(0));
                    if (rowDiff <= 1) {
                        // Rows are adjacent, check if positions are nearby
                        const positions = seats.map(s => parseInt(s.seat_position));
                        const minPos = Math.min(...positions);
                        const maxPos = Math.max(...positions);
                        return (maxPos - minPos) <= 3; // Allow some spread
                    }
                }
                return true; // Single row or close rows
            }
            
            return false; // Too spread out
        }

        // Check for single-seat gaps (more flexible)
        function checkSingleGap(seats) {
            // Group seats by row
            const seatsByRow = {};
            seats.forEach(seat => {
                if (!seatsByRow[seat.row_letter]) {
                    seatsByRow[seat.row_letter] = [];
                }
                seatsByRow[seat.row_letter].push(parseInt(seat.seat_position));
            });
            
            // Check each row for gaps
            for (const [row, positions] of Object.entries(seatsByRow)) {
                if (positions.length < 2) continue;
                
                positions.sort((a, b) => a - b);
                
                // Check for single-seat gaps between selected seats
                for (let i = 0; i < positions.length - 1; i++) {
                    const gap = positions[i + 1] - positions[i];
                    if (gap === 2) {
                        // There's a single seat gap
                        const gapPosition = positions[i] + 1;
                        const gapSeat = allSeats.find(s => 
                            s.row_letter === row && 
                            parseInt(s.seat_position) === gapPosition && 
                            s.status === 'available'
                        );
                        
                        if (gapSeat) {
                            return {
                                hasGap: true,
                                message: `This selection would leave seat ${row}${gapPosition} isolated between your selected seats. This may make it difficult for other guests to book. Are you sure you want to continue?`
                            };
                        }
                    }
                }
            }
            
            return { hasGap: false };
        }

        // Show non-adjacent selection modal
        function showNonAdjacentModal() {
            const modal = document.getElementById('nonAdjacentModal');
            const previewContainer = document.getElementById('nonAdjacentSeatsPreview');
            
            // Show selected seats
            const seatNumbers = selectedSeats
                .sort((a, b) => {
                    if (a.row_letter !== b.row_letter) {
                        return a.row_letter.localeCompare(b.row_letter);
                    }
                    return parseInt(a.seat_position) - parseInt(b.seat_position);
                })
                .map(seat => seat.seat_number);
            
            previewContainer.innerHTML = seatNumbers
                .map(seat => `<div class="preview-seat">${seat}</div>`)
                .join('');
            
            modal.style.display = 'flex';
            pendingNonAdjacentSelection = true;
        }

        // Confirm non-adjacent selection
        function confirmNonAdjacentSelection() {
            pendingNonAdjacentSelection = false;
            closeNonAdjacentModal();
            updateSeatDisplay();
            updateSelectedSeatsDisplay();
            updateSubmitButtonState();
        }

        // Cancel non-adjacent selection
        function cancelNonAdjacentSelection() {
            // Remove the last selected seat(s) to allow re-selection
            selectedSeats = [];
            pendingNonAdjacentSelection = false;
            closeNonAdjacentModal();
            updateSeatDisplay();
            updateSelectedSeatsDisplay();
            updateSubmitButtonState();
        }

        // Close non-adjacent modal
        function closeNonAdjacentModal() {
            document.getElementById('nonAdjacentModal').style.display = 'none';
        }

        // Show gap warning modal
        function showGapWarning(message) {
            const modal = document.getElementById('gapWarningModal');
            const messageEl = document.getElementById('gapWarningMessage');
            messageEl.textContent = message;
            modal.style.display = 'flex';
        }

        // Confirm gap selection
        function confirmGapSelection() {
            if (pendingGapSeat) {
                selectedSeats.push(pendingGapSeat);
                
                // Check if this completes the selection and if it's non-adjacent
                const attendeeCount = parseInt(attendeeCountSelect.value) || 0;
                if (selectedSeats.length === attendeeCount && !areSeatsAdjacent(selectedSeats)) {
                    showNonAdjacentModal();
                    closeGapWarning();
                    return;
                }
                
                updateSeatDisplay();
                updateSelectedSeatsDisplay();
                updateSubmitButtonState();
                pendingGapSeat = null;
            }
            closeGapWarning();
        }

        // Cancel gap selection
        function cancelGapSelection() {
            pendingGapSeat = null;
            closeGapWarning();
        }

        // Close gap warning modal
        function closeGapWarning() {
            document.getElementById('gapWarningModal').style.display = 'none';
        }

        function clearSeatSelections() {
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
            const lastSeat = selectedSeats[selectedSeats.length - 1];
            const row = lastSeat.row_letter;
            const position = parseInt(lastSeat.seat_position);
            
            // Suggest seats adjacent to the last selected seat
            const adjacentPositions = [
                { row: row, position: position - 1 },
                { row: row, position: position + 1 },
                { row: String.fromCharCode(row.charCodeAt(0) - 1), position: position },
                { row: String.fromCharCode(row.charCodeAt(0) + 1), position: position }
            ];
            
            adjacentPositions.forEach(pos => {
                const seat = allSeats.find(s => 
                    s.row_letter === pos.row && 
                    parseInt(s.seat_position) === pos.position && 
                    s.status === 'available' &&
                    !selectedSeats.some(selected => selected.id === s.id)
                );
                
                if (seat) {
                    const seatElement = document.querySelector(`[data-seat-id="${seat.id}"]`);
                    if (seatElement) {
                        seatElement.classList.add('suggested');
                    }
                }
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
                           !pendingNonAdjacentSelection;
            
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
            if (e.target.classList.contains('modal') || 
                e.target.classList.contains('non-adjacent-modal') ||
                e.target.classList.contains('gap-warning-modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>
