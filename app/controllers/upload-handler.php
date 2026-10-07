<?php
require_once dirname(__DIR__) . '/bootstrap.php';

requireAdminApi(true);

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
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true) || getimagesize($file['tmp_name']) === false) {
        echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
        exit;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        echo json_encode(['error' => 'File too large. Maximum size is 2MB.']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = PUBLIC_ROOT . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $extension = $extensions[$mimeType];
    $filename = 'logo_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    $uploadUrl = 'uploads/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update database
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE event_settings SET setting_value = ? WHERE setting_key = 'site_logo'");
            $stmt->execute([$uploadUrl]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'logo_path' => $uploadUrl
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
