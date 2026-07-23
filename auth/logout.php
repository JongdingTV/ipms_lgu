<?php
require_once __DIR__ . '/session.php';

// Citizens have their own separate login page from every staff role — capture
// the role before logoutCurrentUser() wipes the session, so citizens land
// back on citizen/login.php instead of the shared staff auth/login.php.
$wasCitizen = (currentUser()['role'] ?? '') === 'citizen';

// If this session originated from a Main LGU SSO launch, send the admin
// back to the SSO hub instead of this system's own login page.
$returnToMainLgu = !empty($_SESSION['sso_from_mainlgu']);

logoutCurrentUser();

if ($returnToMainLgu) {
    $mainLguUrl = ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
        ? 'http://localhost/Main%20LGU/admin/dashboard.php'
        : 'https://infragovservices.com/admin/dashboard.php';
    header('Location: ' . $mainLguUrl);
    exit;
}

header('Location: ' . ($wasCitizen ? appUrl('/citizen/login.php') : APP_LOGIN_PATH));
exit;
