<?php
// backend/api_auth.php
// Handles all authentication and tenant-lookup requests

require_once 'db_connect.php';

header('Content-Type: application/json');

// --- Helper Functions ---
function jsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// Ensure it's a POST or GET request
$method = $_SERVER['REQUEST_METHOD'];

// ==========================================================
// ENDPOINT 1: GET /api_auth.php?action=get_tenant_theme&slug=...
// Purpose: Called by login.html to paint the screen BEFORE the user logs in.
// ==========================================================
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_tenant_theme') {
    
    if (empty($_GET['slug'])) {
        jsonResponse('error', 'Missing tenant slug (TID) in URL.');
    }
    
    $slug = $_GET['slug'];
    
    // Query the database securely
    $stmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, b.theme_primary_color, b.theme_secondary_color, b.theme_text_main, b.theme_text_muted, b.theme_bg_body, b.theme_bg_card, b.font_family, b.logo_path FROM tenants t LEFT JOIN tenant_branding b ON t.tenant_id = b.tenant_id WHERE t.tenant_slug = ? AND t.status = 'Active'");
    $stmt->execute([$slug]);
    $tenant = $stmt->fetch();
    
    if ($tenant) {
        jsonResponse('success', 'Tenant found', $tenant);
    } else {
        jsonResponse('error', 'Invalid or inactive Tenant ID.');
    }
}

// ==========================================================
// ENDPOINT 2: POST /api_auth.php
// Purpose: Handles the actual login form submission
// ==========================================================
if ($method === 'POST') {
    
    // Parse JSON payload from frontend
    $jsonData = file_get_contents('php://input');
    $request = json_decode($jsonData, true);
    
    if (!isset($request['action'])) {
        jsonResponse('error', 'Missing action parameter');
    }

    // ==========================================================
    // ACTION: SUPER ADMIN LOGIN (Platform Owner)
    // ==========================================================
    if ($request['action'] === 'super_admin_login') {
        $email = $request['email'] ?? '';
        $password = $request['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            jsonResponse('error', 'Email and password are required.');
        }
        
        $stmt = $pdo->prepare("SELECT super_admin_id, username, password_hash FROM super_admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            jsonResponse('error', 'Invalid super admin credentials.');
        }
        
        // Success
        $sessionToken = bin2hex(random_bytes(32));
        jsonResponse('success', 'Super Admin Login successful.', [
            'token' => $sessionToken,
            'user' => [
                'id' => $admin['super_admin_id'],
                'username' => $admin['username'],
                'role' => 'Platform Owner'
            ]
        ]);
    }
    
    // ==========================================================
    // ACTION: TENANT LOGIN (Employees & Clients)
    // ==========================================================
    if ($request['action'] === 'login') {
    
    // Validate inputs
    $email = $request['email'] ?? '';
    $password = $request['password'] ?? '';
    $tenant_id = $request['tenant_id'] ?? '';
    
    if (empty($email) || empty($password) || empty($tenant_id)) {
        jsonResponse('error', 'Email, password, and tenant_id are required.');
    }
    
    // 1. Find user securely scoped BY TENANT ID
    // Crucial: We MUST enforce the tenant_id in the WHERE clause, 
    // otherwise someone from Plaridel could log into Fundline with the same email.
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.password_hash, u.role_id, u.user_type, u.status, r.role_name 
        FROM users u
        JOIN user_roles r ON u.role_id = r.role_id
        WHERE u.email = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$email, $tenant_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse('error', 'Invalid email or password.');
    }
    
    if ($user['status'] !== 'Active') {
        jsonResponse('error', 'Account is suspended or inactive. Please contact your administrator.');
    }
    
    // 2. Verify Password
    // In your schema, the default passwords were 'password' hashed with bcrypt.
    if (password_verify($password, $user['password_hash'])) {
        
        // 3. Create Session
        $sessionToken = bin2hex(random_bytes(32)); // Super secure random 64-character hex string
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $insertStmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, tenant_id, session_token, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $user['user_id'], 
            $tenant_id, 
            $sessionToken, 
            $_SERVER['REMOTE_ADDR'], 
            $expiresAt
        ]);
        
        // Return Success
        jsonResponse('success', 'Login successful.', [
            'token' => $sessionToken,
            'user' => [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'role' => $user['role_name'],
                'type' => $user['user_type']
            ]
        ]);
        
    } else {
        jsonResponse('error', 'Invalid email or password.');
    }
    } // end if action === 'login'
} // end if POST

// Fallback for unmatched routes
jsonResponse('error', 'Endpoint not found or method not allowed.');
?>
