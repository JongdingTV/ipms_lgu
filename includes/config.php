<?php

define('APP_NAME', 'Infrastructure Project Management System');
define('APP_BASE_PATH', '/ipms.lgu');
define('APP_LOGIN_PATH', APP_BASE_PATH . '/auth/login.php');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('DB_HOST', 'localhost');
define('DB_NAME', 'lgu_infrastructure');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

const APP_ROLES = ['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen'];

const ROLE_LABELS = [
    'super_admin' => 'Super Admin',
    'admin' => 'LGU Admin / Engineering Head',
    'bac' => 'BAC (Bids & Awards Committee)',
    'engineer' => 'Engineer',
    'contractor' => 'Contractor',
    'citizen' => 'Citizen / Public User',
];

const ROLE_DASHBOARD_PATHS = [
    'super_admin' => '/superadmin/dashboard.php',
    'admin' => '/admin/dashboard.php',
    'bac' => '/bac/dashboard.php',
    'engineer' => '/engineer/dashboard.php',
    'contractor' => '/contractor/dashboard.php',
    'citizen' => '/citizen/dashboard.php',
];

function appUrl(string $path = ''): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    return APP_BASE_PATH . ($path === '' ? '' : $normalizedPath);
}

function roleLabel(string $role): string
{
    return ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function roleDashboardPath(string $role): string
{
    return appUrl(ROLE_DASHBOARD_PATHS[$role] ?? '/auth/login.php');
}

function isValidRole(string $role): bool
{
    return in_array($role, APP_ROLES, true);
}
