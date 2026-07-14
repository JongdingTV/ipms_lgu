<?php
require_once __DIR__ . '/session.php';

// Citizens have their own separate login page from every staff role — capture
// the role before logoutCurrentUser() wipes the session, so citizens land
// back on citizen/login.php instead of the shared staff auth/login.php.
$wasCitizen = (currentUser()['role'] ?? '') === 'citizen';

logoutCurrentUser();
header('Location: ' . ($wasCitizen ? appUrl('/citizen/login.php') : APP_LOGIN_PATH));
exit;
