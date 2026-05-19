<?php
require_once __DIR__ . '/auth/session.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

header('Location: ' . appUrl('/landing.php'));
exit;
