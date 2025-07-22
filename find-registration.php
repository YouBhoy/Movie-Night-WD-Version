<?php
require_once 'config.php';

$error = '';
$registration = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting by IP address
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($clientIP)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $empNumber = strtoupper(trim($_POST['emp_number'] ?? ''));
        
        if (empty($empNumber)) {
            $error = 'Please enter your employee number.';
        } else {
            try {
                $pdo = getDBConnection();
                
                // Check if employee exists
                $stmt = $pdo->prepare("SELECT full_name FROM employees WHERE emp_number = ? AND is_active = 1");
                $stmt->execute([$empNumber]);
                $employee = $stmt->fetch();
                
                if (!$employee) {
                    $error = 'Employee not found. Please check your employee number.';
                } else {
                    // Find registration
                    $stmt = $pdo->prepare("
                        SELECT r.*, h.hall_name, s.shift_name 
                        FROM registrations r
                        LEFT JOIN cinema_halls h ON r.hall_id = h.id
                        LEFT JOIN shifts s ON r.shift_id = s.id
                        WHERE r.emp_number = ? AND r.status = 'active'
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$empNumber]);
                    $registration = $stmt->fetch();
                    
                    if (!$registration) {
                        $error = 'No active registration found for this employee.';
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again.';
                error_log("Error in find-registration: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find My Registration - Movie Night</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
        }
        .find-registration-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .find-registration-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .find-registration-header h1 {
            color: #FFD700;
            margin-bottom: 0.5rem;
        }
        
        .find-registration-header p {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        
        .help-text {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            color: #FFD700;
            font-size: 0.9rem;
        }
        
        .help-text i {
            margin-right: 8px;
        }
        
        .registration-form {
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #FFD700;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }
        
        .btn-find {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .btn-find:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }
        
        .btn-back {
            width: 100%;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .registration-details {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .registration-details h3 {
            color: #4ade80;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.5rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #94a3b8;
            font-weight: 500;
        }
        
        .detail-value {
            color: #ffffff;
            font-weight: 600;
        }
        
        .seats-display {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .seat-tag {
            display: inline-block;
            background: #FFD700;
            color: #1a1a1a;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            margin: 0.25rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .success-icon {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .success-icon i {
            font-size: 3rem;
            color: #4ade80;
        }
        .btn.btn-secondary {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
    </style>
    <style>
    a.btn-secondary, .btn-secondary {
      display: inline-block;
      background: transparent;
      color: #FFD700 !important;
      border: 2px solid #FFD700;
      border-radius: 8px;
      padding: 0.5rem 1.25rem;
      font-size: 1rem;
      font-weight: 600;
      text-align: center;
      text-decoration: none !important;
      transition: background 0.2s, color 0.2s, box-shadow 0.2s;
      box-shadow: none;
      cursor: pointer;
      vertical-align: middle;
    }
    a.btn-secondary:hover, .btn-secondary:hover, a.btn-secondary:focus, .btn-secondary:focus {
      background: #FFD700;
      color: #1a1a1a !important;
      box-shadow: 0 4px 16px rgba(255, 215, 0, 0.15);
      text-decoration: none !important;
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="find-registration-container">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <a href="index.php" class="btn btn-secondary">
                    🔙 Back to Registration
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