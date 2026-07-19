<?php

function envValue(string $key, ?string $default = null): ?string
{
    static $loaded = false;

    if (!$loaded) {
        $envPath = dirname(__DIR__) . '/.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, "\"'");

                if ($name !== '' && getenv($name) === false) {
                    putenv($name . '=' . $value);
                    $_ENV[$name] = $value;
                }
            }
        }
        $loaded = true;
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
}

function normalizeBasePath(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '' || $path === '/') {
        return '';
    }

    return '/' . trim($path, '/');
}

// PHP's ini-configured timezone (Europe/Berlin on this box) doesn't match
// MySQL's SYSTEM timezone (Asia/Manila) — without this, every PHP-computed
// timestamp (e.g. OTP expires_at) lands ~6 hours off from MySQL-generated
// ones (created_at via CURRENT_TIMESTAMP), corrupting any SQL-side NOW()
// comparison such as cleanExpiredOTPs()'s DELETE ... WHERE expires_at < NOW().
date_default_timezone_set('Asia/Manila');

define('APP_ENV', envValue('APP_ENV', 'local'));
define('APP_NAME', envValue('APP_NAME', 'Infrastructure Project Management System'));
define('APP_BASE_PATH', normalizeBasePath(envValue('APP_BASE_PATH', '/ipms.lgu')));
define('APP_LOGIN_PATH', APP_BASE_PATH . '/auth/login.php');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_NAME', envValue('DB_NAME', 'lgu_infrastructure'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));

// Email Configuration
define('MAIL_FROM_EMAIL', envValue('MAIL_FROM_EMAIL', 'ipms.systemlgu@gmail.com'));
define('MAIL_FROM_NAME', envValue('MAIL_FROM_NAME', 'LGU IPMS System'));
define('MAIL_HOST', envValue('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) envValue('MAIL_PORT', '587'));
define('MAIL_USERNAME', envValue('MAIL_USERNAME', 'ipms.systemlgu@gmail.com'));
define('MAIL_PASSWORD', envValue('MAIL_PASSWORD', '')); // Set your app password in .env
define('MAIL_ENCRYPTION', envValue('MAIL_ENCRYPTION', 'tls'));

// CIMMS (Community Infrastructure Maintenance) — outbound integration for
// maintenance-type citizen feedback. Canonical receiver is in the LGU repo:
// https://github.com/EXEQUIELKENT/LGU → lgu-portal/public/api/ipms-requests.php
define('CIMM_API_ENABLED', filter_var(envValue('CIMM_API_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN));
define('CIMM_API_URL', envValue(
    'CIMM_API_URL',
    'https://cimm.infragovservices.com/lgu-portal/public/api/ipms-requests.php'
));
define('CIMM_API_KEY', envValue('CIMM_API_KEY', ''));
define('CIMM_API_TIMEOUT', (int) envValue('CIMM_API_TIMEOUT', '20'));
define('CIMM_SSL_VERIFY', filter_var(envValue('CIMM_SSL_VERIFY', '1'), FILTER_VALIDATE_BOOLEAN));

// Urban Planning System — inbound integration for road inspection requests
// (opposite direction from CIMMS above: there, IPMS calls out; here, the
// separate Urban Planning System capstone project calls in). This repo
// hosts both endpoints it needs: integrations/urban-planning/inspection-
// requests.php (inbound receiver) and inspection-results.php (outbound,
// polled). Same shared-secret model as CIMMS: both sides must set the
// same key.
define('URBAN_PLANNING_API_KEY', envValue('URBAN_PLANNING_API_KEY', ''));

const APP_ROLES = ['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen', 'hope'];

const ROLE_LABELS = [
    'super_admin' => 'Super Admin',
    'admin' => 'LGU Admin / Engineering Head',
    'bac' => 'BAC (Bids & Awards Committee)',
    'engineer' => 'Engineer',
    'contractor' => 'Contractor',
    'citizen' => 'Citizen / Public User',
    'hope' => 'HOPE (Head of Procuring Entity)',
];

const ROLE_DASHBOARD_PATHS = [
    'super_admin' => '/superadmin/dashboard.php',
    'admin' => '/admin/dashboard.php',
    'bac' => '/bac/dashboard.php',
    'engineer' => '/engineer/dashboard.php',
    'contractor' => '/contractor/dashboard.php',
    'citizen' => '/citizen/dashboard.php',
    'hope' => '/hope/dashboard.php',
];

function appUrl(string $path = ''): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    return APP_BASE_PATH . ($path === '' ? '' : $normalizedPath);
}

/**
 * Same as appUrl() but appends a `?v=<mtime>` cache-buster for static assets
 * (JS/CSS) so browsers that cache aggressively (no revalidation request at
 * all, not even a 304) are forced to fetch a fresh copy the moment the file
 * on disk actually changes, instead of silently serving a stale build
 * indefinitely.
 */
function assetUrl(string $path): string
{
    $absolutePath = dirname(__DIR__) . '/' . ltrim($path, '/');
    $version = is_file($absolutePath) ? filemtime($absolutePath) : time();
    return appUrl($path) . '?v=' . $version;
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
