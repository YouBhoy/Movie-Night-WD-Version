<?php
require_once 'config.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check
    $clientIP = $_SERVER['REMOTE_ADDR'];
    if (!checkRateLimit($clientIP, 10, 900)) { // 10 attempts per 15 minutes
        $error = 'Too many login attempts. Please try again later.';
    } else {
        // CSRF validation
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please refresh the page.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $adminKey = trim($_POST['admin_key'] ?? '');
            
            if ($username && $password && $adminKey) {
                $loginResult = checkAdminLogin($username, $password, $adminKey);
                
                if ($loginResult['success']) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_login_time'] = time();
                    
                    // Log successful admin login
                    try {
                        $pdo = getDBConnection();
                        $logStmt = $pdo->prepare("
                            INSERT INTO admin_activity_log (admin_user, action, ip_address, user_agent) 
                            VALUES (?, 'login', ?, ?)
                        ");
                        $logStmt->execute([
                            $username,
                            $_SERVER['REMOTE_ADDR'],
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);
                    } catch (Exception $e) {
                        error_log("Failed to log admin activity: " . $e->getMessage());
                    }
                    
                    header('Location: admin-dashboard.php');
                    exit;
                } else {
                    $error = $loginResult['message'];
                }
            } else {
                $error = 'Please fill in all required fields.';
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
    <title>Admin Login - WD Movie Night</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input {
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error-message {
            background: #fee;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid #fed7d7;
        }
        
        .login-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .login-button:hover {
            transform: translateY(-1px);
        }
        
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .security-notice {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 12px;
            color: #4a5568;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Admin Login</h1>
                <p class="login-subtitle">WD Movie Night Management System</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <?php echo sanitizeInput($error); ?>
            </div>
            <?php endif; ?>
            
            <form class="login-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-input" 
                           required autocomplete="username" value="<?php echo sanitizeInput($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" 
                           required autocomplete="current-password">
                </div>
                
                <div class="form-group">
                    <label for="admin_key" class="form-label">Admin Key</label>
                    <input type="password" id="admin_key" name="admin_key" class="form-input" 
                           required autocomplete="off">
                </div>
                
                <button type="submit" class="login-button">Login to Admin Panel</button>
            </form>
            
            <div class="security-notice">
                <strong>Security Notice:</strong> This is a secure admin area. All login attempts are logged and monitored. 
                Unauthorized access attempts will be reported.
            </div>
            
            <div class="back-link">
                <a href="index.php">← Back to Registration</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            for (let input of inputs) {
                if (!input.value) {
                    input.focus();
                    break;
                }
            }
        });
        
        // Form validation
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const adminKey = document.getElementById('admin_key').value.trim();
            
            if (!username || !password || !adminKey) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>
