<?php
require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$loginAttempts = 0;

// Check login attempts from this IP
$pdo = getDBConnection();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Clean old login attempts (older than 1 hour)
$cleanStmt = $pdo->prepare("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$cleanStmt->execute();

// Check recent failed attempts
$attemptStmt = $pdo->prepare("
    SELECT COUNT(*) as attempts 
    FROM login_attempts 
    WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$attemptStmt->execute([$ip]);
$loginAttempts = $attemptStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check with delay for failed attempts
    if ($loginAttempts >= MAX_LOGIN_ATTEMPTS) {
        sleep(3); // Add delay for repeated failed attempts
        $error = 'Too many failed login attempts. Please try again in 15 minutes.';
    } else {
        // Add small delay for any login attempt to prevent timing attacks
        usleep(500000); // 0.5 second delay
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid security token. Please refresh the page.';
        } else {
            // Check credentials using the adminLogin function
            if (adminLogin($username, $password)) {
                // Successful login - regenerate session ID for security
                session_regenerate_id(true);
                
                // Log successful login
                $logStmt = $pdo->prepare("
                    INSERT INTO login_attempts (ip_address, username, success, message, created_at) 
                    VALUES (?, ?, 1, 'Successful login', NOW())
                ");
                $logStmt->execute([$ip, $username]);
                
                // Log admin activity
                logActivity('admin_login', 'Admin login successful', $username);
                
                header('Location: admin-dashboard.php');
                exit;
            } else {
                // Failed login with rate limiting
                if (!checkRateLimit($ip . '_login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME, true)) {
                    $error = 'Too many failed attempts. Please try again later.';
                } else {
                    $error = 'Invalid username or password.';
                }
                
                // Log failed attempt
                $logStmt = $pdo->prepare("
                    INSERT INTO login_attempts (ip_address, username, success, message, created_at) 
                    VALUES (?, ?, 0, 'Invalid credentials', NOW())
                ");
                $logStmt->execute([$ip, $username]);
                
                $loginAttempts++;
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WD Movie Night</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-theme">
    <div class="admin-login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Admin Login</h1>
                <p class="login-subtitle">WD Movie Night Registration System</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <?php echo sanitizeInput($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($loginAttempts >= 3 && $loginAttempts < 5): ?>
            <div class="warning-message">
                <span class="warning-icon">⚠️</span>
                Warning: <?php echo (5 - $loginAttempts); ?> login attempts remaining before temporary lockout.
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           required autocomplete="username"
                           value="<?php echo sanitizeInput($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           required autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-button" <?php echo ($loginAttempts >= 5) ? 'disabled' : ''; ?>>
                    <?php echo ($loginAttempts >= 5) ? 'Locked Out' : 'Login'; ?>
                </button>
            </form>
            
            <div class="login-footer">
                <a href="index.php" class="back-link">← Back to Registration</a>
            </div>
        </div>
    </div>

    <style>
        body, .dark-theme {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
        }
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: #FFD700;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .error-message, .warning-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .warning-message {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        .login-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ffffff;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }

        .login-button {
            width: 100%;
            padding: 0.875rem;
            background: #FFD700;
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-button:hover:not(:disabled) {
            background: #e6c200;
            transform: translateY(-2px);
        }

        .login-button:disabled {
            background: #666;
            color: #999;
            cursor: not-allowed;
        }

        .login-footer {
            text-align: center;
        }

        .back-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #FFD700;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</body>
</html>
