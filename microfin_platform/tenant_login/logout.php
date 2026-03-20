<?php
session_start();

$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$tenant_key = $_SESSION['tenant_key'] ?? '';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params['path'],
		$params['domain'],
		$params['secure'],
		$params['httponly']
	);
}

session_destroy();

$redirect = 'login.php';
if ($tenant_slug !== '') {
	$redirect .= '?s=' . urlencode($tenant_slug);
}

header('Location: ' . $redirect);
exit;
?>
