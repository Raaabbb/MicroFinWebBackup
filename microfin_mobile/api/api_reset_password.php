<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $email = $data['email'] ?? '';
    $tenant_id = $data['tenant_id'] ?? '';
    $new_password = $data['new_password'] ?? '';
    
    if(empty($email) || empty($tenant_id) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND tenant_id = ?");
    $stmt->bind_param("sss", $password_hash, $email, $tenant_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 1) {
            echo json_encode(['success' => true, 'message' => 'Password reset successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Password update failed. Make sure you are registered.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
