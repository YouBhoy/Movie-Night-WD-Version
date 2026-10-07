<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find My Registration - Movie Night</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/find-registration.css">
    <link rel="stylesheet" href="assets/css/find-registration-2.css">
    <link rel="stylesheet" href="assets/css/icons.css">
</head>
<body>
    <div class="container">
        <div class="find-registration-container">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left ui-icon" aria-hidden="true"></i> Back to Registration
                </a>
            </div>
            <div class="find-registration-header">
                <h1><i class="fas fa-search"></i> Find My Registration</h1>
                <p>Enter your employee number to find your registration information</p>
                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    <strong>Need help?</strong> Use the same employee number you used during registration.
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$registration): ?>
                <form class="registration-form" method="POST">
                    <div class="form-group">
                        <label for="emp_number">
                            <i class="fas fa-id-card"></i> Employee Number
                        </label>
                        <input type="text" id="emp_number" name="emp_number"
                               value="<?php echo htmlspecialchars($_POST['emp_number'] ?? ''); ?>"
                               placeholder="Enter your employee number (e.g., WD001)" required>
                    </div>

                    <button type="submit" class="btn-find">
                        <i class="fas fa-search"></i> Find My Registration
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($registration): ?>
                <div class="registration-details">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Registration Found!</h3>

                    <div class="detail-row">
                        <span class="detail-label">Employee Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($registration['emp_number']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($registration['staff_name']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Hall:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($registration['hall_name'] ?? 'N/A'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Shift:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($registration['shift_name'] ?? 'N/A'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Number of Attendees:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($registration['attendee_count']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Selected Seats:</span>
                        <span class="detail-value">
                            <?php
                            $seats = json_decode($registration['selected_seats'], true);
                            if (is_array($seats) && !empty($seats)): ?>
                                <div class="seats-display">
                                    <?php foreach ($seats as $seat): ?>
                                        <span class="seat-tag"><?php echo htmlspecialchars($seat); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Registration Date:</span>
                        <span class="detail-value">
                            <?php echo date('M j, Y g:i A', strtotime($registration['created_at'])); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>