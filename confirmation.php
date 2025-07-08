<?php
require_once 'config.php';

// Check if registration data is available in session
session_start();

if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header('Location: index.php');
    exit;
}

$registrationData = $_SESSION['registration_data'] ?? null;

if (!$registrationData) {
    header('Location: index.php');
    exit;
}

// Clear session data
unset($_SESSION['registration_success']);
unset($_SESSION['registration_data']);

$pdo = getDBConnection();

// Get event settings for display
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings WHERE is_public = 1");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$movieName = $settings['movie_name'] ?? 'Super Cool Movie';
$movieDate = $settings['movie_date'] ?? 'Friday, 16 May 2025';
$movieTime = $settings['movie_time'] ?? '8:30 PM';
$movieLocation = $settings['movie_location'] ?? 'Cinema Complex';
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
</head>
<body class="dark-theme">
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo-text">WD</h1>
                <nav class="nav">
                    <a href="index.php" class="nav-link">Back to Registration</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Confirmation Section -->
    <section class="confirmation-section">
        <div class="container">
            <div class="confirmation-container">
                <div class="success-icon">
                    <div class="checkmark">✓</div>
                </div>
                
                <h1 class="confirmation-title">Registration Confirmed!</h1>
                <p class="confirmation-subtitle">Your seats have been successfully reserved</p>
                
                <!-- Registration Details -->
                <div class="confirmation-details">
                    <div class="detail-card">
                        <h2 class="detail-title">🎬 Event Information</h2>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Movie:</span>
                                <span class="detail-value"><?php echo sanitizeInput($movieName); ?></span>
                            </div>
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
                    </div>
                    
                    <div class="detail-card">
                        <h2 class="detail-title">👤 Registration Details</h2>
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
                                <span class="detail-label">Attendees:</span>
                                <span class="detail-value"><?php echo (int)$registrationData['attendee_count']; ?> person(s)</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Registration ID:</span>
                                <span class="detail-value">#<?php echo (int)$registrationData['id']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h2 class="detail-title">🎫 Seat Information</h2>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Cinema Hall:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['hall_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Shift:</span>
                                <span class="detail-value"><?php echo sanitizeInput($registrationData['shift_name']); ?></span>
                            </div>
                            <div class="detail-item full-width">
                                <span class="detail-label">Reserved Seats:</span>
                                <div class="seat-tags">
                                    <?php foreach ($registrationData['selected_seats'] as $seat): ?>
                                        <span class="seat-tag"><?php echo sanitizeInput($seat); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Important Information -->
                <div class="important-info">
                    <h3 class="info-title">📋 Important Information</h3>
                    <ul class="info-list">
                        <li>Please arrive at least <strong>15 minutes before</strong> the screening time</li>
                        <li>Bring a valid ID for verification at the venue</li>
                        <li>Your seats are reserved and cannot be changed</li>
                        <li>Food and beverages will be provided at the venue</li>
                        <li>For any issues, contact the event organizers</li>
                    </ul>
                </div>
                
                <!-- Action Buttons -->
                <div class="confirmation-actions">
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Print Confirmation
                    </button>
                    <a href="index.php" class="btn btn-primary">
                        🏠 Back to Home
                    </a>
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
            </div>
        </div>
    </footer>

    <style>
        .confirmation-section {
            padding: 4rem 0;
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
        }

        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .success-icon {
            margin-bottom: 2rem;
        }

        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--success-color, #10b981), #059669);
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            animation: checkmarkPulse 0.6s ease-out;
        }

        @keyframes checkmarkPulse {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .confirmation-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .confirmation-subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
        }

        .confirmation-details {
            display: grid;
            gap: 2rem;
            margin-bottom: 3rem;
            text-align: left;
        }

        .detail-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .detail-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .seat-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .seat-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .important-info {
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid var(--primary-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: left;
        }

        .info-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-list li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--text-primary);
        }

        .info-list li::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .confirmation-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        @media (max-width: 768px) {
            .confirmation-title {
                font-size: 2rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .confirmation-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        @media print {
            .header, .footer, .confirmation-actions {
                display: none;
            }
            
            .confirmation-section {
                padding: 2rem 0;
            }
            
            .detail-card {
                break-inside: avoid;
                margin-bottom: 1rem;
            }
        }
    </style>
</body>
</html>
