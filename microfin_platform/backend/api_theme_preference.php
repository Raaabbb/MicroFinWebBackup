<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$theme = strtolower(trim((string)($payload['theme'] ?? '')));
if (!in_array($theme, ['light', 'dark'], true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid theme value']);
    exit;
}

$user_id = 0;
$role_context = strtolower(trim((string)($payload['role'] ?? '')));

if ($role_context === 'super_admin' && !empty($_SESSION['super_admin_logged_in']) && !empty($_SESSION['super_admin_id'])) {
    $user_id = (int)$_SESSION['super_admin_id'];
} elseif ($role_context === 'tenant' && !empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
} else {
    // Fallback: Check which one seems to have initiated the request, or just pick what's available
    if (!empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {      
        $user_id = (int)$_SESSION['user_id'];
    } elseif (!empty($_SESSION['super_admin_logged_in']) && !empty($_SESSION['super_admin_id'])) {
        $user_id = (int)$_SESSION['super_admin_id'];
    }

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET ui_theme = ? WHERE user_id = ?');
    $stmt->execute([$theme, $user_id]);

    $_SESSION['ui_theme'] = $theme;

    echo json_encode(['status' => 'success', 'theme' => $theme]);
} catch (PDOException $ex) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save theme preference']);
}
}