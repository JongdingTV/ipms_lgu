<?php
require_once __DIR__ . '/session.php';

logoutCurrentUser();
header('Location: ' . APP_LOGIN_PATH);
exit;
