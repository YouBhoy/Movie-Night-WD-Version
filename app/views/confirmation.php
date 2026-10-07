<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - WD Movie Night</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons.css">
</head>
<body class="dark-theme">
    <div class="container">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <div class="success-icon"><i class="fas fa-circle-check ui-icon" aria-hidden="true"></i></div>
                <h1 class="confirmation-title">Registration Confirmed!</h1>
                <p class="confirmation-subtitle">Your seats have been successfully reserved</p>
            </div>

            <div class="confirmation-details">
                <div class="detail-section">
                    <h2 class="detail-title"><i class="fas fa-film ui-icon" aria-hidden="true"></i> Movie Details</h2>
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
                    <h2 class="detail-title"><i class="fas fa-user ui-icon" aria-hidden="true"></i> Registration Details</h2>
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
                    <h2 class="detail-title"><i class="fas fa-ticket ui-icon" aria-hidden="true"></i> Selected Seats</h2>
                    <div class="seat-display">
                        <?php foreach ($registrationData['selected_seats'] as $seat): ?>
                            <span class="seat-badge"><?php echo htmlspecialchars($seat); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="reminder-section">
                    <div class="reminder-box">
                        <div class="reminder-icon"><i class="fas fa-film ui-icon" aria-hidden="true"></i></div>
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

    <link rel="stylesheet" href="assets/css/confirmation.css">
</body>
</html>
