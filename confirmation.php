<?php
require_once 'config.php';

// Check if registration data exists in session
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header('Location: index.php');
    exit;
}

$registrationData = $_SESSION['registration_data'] ?? null;

if (!$registrationData) {
    header('Location: index.php');
    exit;
}

// Clear session data after displaying
unset($_SESSION['registration_success']);
unset($_SESSION['registration_data']);

// Get event settings
$pdo = getDBConnection();
$settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
$settingsStmt->execute();
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$movieName = $settings['movie_name'] ?? 'Thunderbolts*';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'WD Campus Cinema Complex';
$primaryColor = $settings['primary_color'] ?? '#FFD700';
$secondaryColor = $settings['secondary_color'] ?? '#2E8BFF';
$footerText = $settings['footer_text'] ?? '© 2025 Western Digital – Internal Movie Night Event';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - WD Movie Night</title>
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
                    <a href="index.php" class="nav-link">Back to Home</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Confirmation Section -->
    <section class="confirmation-section">
        <div class="container">
            <div class="confirmation-container">
                <div class="confirmation-header">
                    <div class="success-icon">✅</div>
                    <h1 class="confirmation-title">Registration Confirmed!</h1>
                    <p class="confirmation-subtitle">Your seats have been successfully reserved</p>
                </div>

                <div class="confirmation-details">
                    <div class="details-card">
                        <h2 class="details-title">Registration Details</h2>
                        
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Employee Number:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['emp_number']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Name:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['staff_name']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Number of Attendees:</span>
                                <span class="detail-value"><?php echo (int)$registrationData['attendee_count']; ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Cinema Hall:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['hall_name']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Shift:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['shift_name']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Selected Seats:</span>
                                <span class="detail-value seat-numbers">
                                    <?php 
                                    $seats = json_decode($registrationData['selected_seats'], true);
                                    if (is_array($seats)) {
                                        foreach ($seats as $seat) {
                                            echo '<span class="seat-tag">' . sanitizeInput($seat) . '</span>';
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Registration Time:</span>
                                <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($registrationData['registration_date'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="event-card">
                        <h2 class="details-title">Event Information</h2>
                        
                        <div class="event-info">
                            <div class="event-item">
                                <span class="event-icon">🎬</span>
                                <div class="event-content">
                                    <strong><?php echo sanitizeInput($movieName); ?></strong>
                                    <p>Exclusive company screening</p>
                                </div>
                            </div>
                            
                            <div class="event-item">
                                <span class="event-icon">📅</span>
                                <div class="event-content">
                                    <strong><?php echo sanitizeInput($movieDate); ?></strong>
                                    <p><?php echo sanitizeInput($movieTime); ?></p>
                                </div>
                            </div>
                            
                            <div class="event-item">
                                <span class="event-icon">📍</span>
                                <div class="event-content">
                                    <strong><?php echo sanitizeInput($movieLocation); ?></strong>
                                    <p><?php echo sanitizeInput($registrationData['hall_name']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="important-notes">
                    <h3 class="notes-title">Important Reminders</h3>
                    <div class="notes-grid">
                        <div class="note-item">
                            <span class="note-icon">⏰</span>
                            <div class="note-content">
                                <strong>Arrival Time</strong>
                                <p>Please arrive at least 15 minutes before the screening time</p>
                            </div>
                        </div>
                        
                        <div class="note-item">
                            <span class="note-icon">🎫</span>
                            <div class="note-content">
                                <strong>Seat Assignment</strong>
                                <p>Your seats are reserved. Please proceed directly to your assigned seats</p>
                            </div>
                        </div>
                        
                        <div class="note-item">
                            <span class="note-icon">🍿</span>
                            <div class="note-content">
                                <strong>Complimentary Treats</strong>
                                <p>Popcorn and beverages will be provided at the venue</p>
                            </div>
                        </div>
                        
                        <div class="note-item">
                            <span class="note-icon">📱</span>
                            <div class="note-content">
                                <strong>Contact Support</strong>
                                <p>For any questions or changes, contact the event organizers</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="confirmation-actions">
                    <a href="index.php" class="button-primary">Back to Home</a>
                    <button onclick="window.print()" class="button-secondary">Print Confirmation</button>
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
            </div>
        </div>
    </footer>

    <style>
        .confirmation-section {
            min-height: 80vh;
            padding: 2rem 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .confirmation-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .confirmation-subtitle {
            font-size: 1.2rem;
            color: #94a3b8;
        }

        .confirmation-details {
            display: grid;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .details-card, .event-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .details-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .detail-grid {
            display: grid;
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #94a3b8;
        }

        .detail-value {
            font-weight: 600;
            color: #ffffff;
        }

        .seat-numbers {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .seat-tag {
            background: var(--primary-color);
            color: #000;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .event-info {
            display: grid;
            gap: 1.5rem;
        }

        .event-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .event-icon {
            font-size: 2rem;
            width: 3rem;
            text-align: center;
        }

        .event-content strong {
            color: #ffffff;
            display: block;
            margin-bottom: 0.25rem;
        }

        .event-content p {
            color: #94a3b8;
            margin: 0;
        }

        .important-notes {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 3rem;
        }

        .notes-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .note-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .note-icon {
            font-size: 1.5rem;
            width: 2rem;
            text-align: center;
            margin-top: 0.25rem;
        }

        .note-content strong {
            color: var(--primary-color);
            display: block;
            margin-bottom: 0.25rem;
        }

        .note-content p {
            color: #94a3b8;
            margin: 0;
            font-size: 0.9rem;
        }

        .confirmation-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .button-primary, .button-secondary {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .button-primary {
            background: var(--primary-color);
            color: #000;
        }

        .button-primary:hover {
            background: #e6c200;
            transform: translateY(-2px);
        }

        .button-secondary {
            background: transparent;
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .button-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .confirmation-container {
                padding: 1rem;
            }

            .confirmation-title {
                font-size: 2rem;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .notes-grid {
                grid-template-columns: 1fr;
            }

            .confirmation-actions {
                flex-direction: column;
            }
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
            }

            .header, .footer {
                display: none;
            }

            .confirmation-section {
                background: white !important;
            }

            .details-card, .event-card, .important-notes {
                background: white !important;
                border: 1px solid #ccc !important;
            }

            .confirmation-actions {
                display: none;
            }
        }
    </style>
</body>
</html>
