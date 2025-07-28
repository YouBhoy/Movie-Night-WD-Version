<?php
require_once 'config.php';

// Check if registration data exists in session
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header('Location: index.php');
    exit;
}

// Get registration data from session
$registrationData = $_SESSION['registration_data'] ?? null;
if (!$registrationData) {
    header('Location: index.php');
    exit;
}

// Get event settings for display
$pdo = getDBConnection();
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM event_settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Clear the session data to prevent refresh issues
unset($_SESSION['registration_success']);
unset($_SESSION['registration_data']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - WD Movie Night</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-theme">
    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="success-icon">✅</div>
                <h1 class="confirmation-title">Registration Confirmed!</h1>
                <p class="confirmation-subtitle">Your seats have been successfully reserved</p>
            </div>

            <div class="confirmation-details">
                <div class="detail-section">
                    <h2 class="detail-title">🎬 Movie Details</h2>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Movie:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($settings['movie_name'] ?? 'Movie Night'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($settings['movie_date'] ?? 'TBA'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($settings['movie_time'] ?? 'TBA'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($settings['movie_location'] ?? 'Cinema Complex'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h2 class="detail-title">👤 Registration Details</h2>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Employee ID:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($registrationData['emp_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($registrationData['staff_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Hall:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($registrationData['hall_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Shift:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($registrationData['shift_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Attendees:</span>
                            <span class="detail-value"><?php echo $registrationData['attendee_count']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h2 class="detail-title">🎫 Selected Seats</h2>
                    <div class="seat-display">
                        <?php foreach ($registrationData['selected_seats'] as $seat): ?>
                            <span class="seat-badge"><?php echo htmlspecialchars($seat); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="reminder-section">
                    <div class="reminder-box">
                        <div class="reminder-icon">🎬</div>
                        <p class="reminder-text">
                            Please arrive at least 15 minutes before the movie starts to ensure a smooth seating experience.
                        </p>
                    </div>
                </div>
            </div>

            <div class="confirmation-actions">
                <a href="index.php" class="btn btn-primary">Back to Home</a>
                <button onclick="window.print()" class="btn btn-secondary">Print Confirmation</button>
            </div>
        </div>
    </div>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .confirmation-container {
            max-width: 800px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
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
            color: #FFD700;
            margin-bottom: 0.5rem;
        }

        .confirmation-subtitle {
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .confirmation-details {
            margin-bottom: 3rem;
        }

        .detail-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #FFD700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .seat-display {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .seat-badge {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .reminder-section {
            margin-top: 2rem;
        }

        .reminder-box {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 165, 0, 0.1));
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .reminder-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .reminder-text {
            color: #FFD700;
            font-weight: 500;
            font-size: 1.1rem;
            margin: 0;
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
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .confirmation-container {
                margin: 1rem;
                padding: 2rem;
            }

            .confirmation-title {
                font-size: 2rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .reminder-box {
                flex-direction: column;
                text-align: center;
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

            .confirmation-container {
                background: white !important;
                border: 1px solid #ccc !important;
                box-shadow: none !important;
            }

            .confirmation-actions {
                display: none !important;
            }
        }
    </style>
</body>
</html>
