<?php
require_once __DIR__ . '/config.php';

header('Location: ' . appUrl('/auth/logout.php'));
exit;
