<?php
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$uploadType = $_POST['upload_type'] ?? '';

if ($uploadType === 'logo') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['logo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
        exit;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        echo json_encode(['error' => 'File too large. Maximum size is 2MB.']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update database
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE event_settings SET setting_value = ? WHERE setting_key = 'site_logo'");
            $stmt->execute([$uploadPath]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'logo_path' => $uploadPath
            ]);
        } catch (Exception $e) {
            // Delete uploaded file if database update fails
            unlink($uploadPath);
            echo json_encode(['error' => 'Database update failed']);
        }
    } else {
        echo json_encode(['error' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['error' => 'Invalid upload type']);
}
?>
