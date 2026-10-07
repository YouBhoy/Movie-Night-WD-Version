<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WD Movie Night</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons.css">
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
                <span class="error-icon"><i class="fas fa-triangle-exclamation ui-icon" aria-hidden="true"></i></span>
                <?php echo sanitizeInput($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($loginAttempts >= 3 && $loginAttempts < 5): ?>
            <div class="warning-message">
                <span class="warning-icon"><i class="fas fa-triangle-exclamation ui-icon" aria-hidden="true"></i></span>
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
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left ui-icon" aria-hidden="true"></i> Back to Registration</a>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="assets/css/admin-login.css">
</body>
</html>
