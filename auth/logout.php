<?php
require_once __DIR__ . '/session.php';

// Capture the role before the session is destroyed so each portal's users
// land back on the login page they actually came from.
$role = $_SESSION['auth_user']['role'] ?? '';

logoutCurrentUser();

if ($role === 'citizen') {
    header('Location: ' . appUrl('/citizen/login.php'));
} else {
    header('Location: ' . APP_LOGIN_PATH);
}
exit;
