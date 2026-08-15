<?php

function envValue(string $key, ?string $default = null): ?string
{
    static $parsed = null;

    if ($parsed === null) {
        $parsed = [];
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

                if ($name !== '') {
                    $parsed[$name] = $value;
                }
            }
        }
    }

    // Deliberately NOT using putenv(): on threaded/persistent SAPIs
    // (e.g. Apache's WinNT MPM on Windows XAMPP), putenv() writes are
    // process-level and can leak into a *different* HTTP request handled
    // by the same reused worker. This surfaced for real — a sibling
    // system's DB_NAME leaked into this app's request and made it query
    // the wrong database. A static array is request-scoped and can't leak.
    if (array_key_exists($key, $parsed) && $parsed[$key] !== '') {
        return $parsed[$key];
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

// The scheme+host citizens should actually reach — this matters for anything
// generated once and used later outside the current request, like a QR
// code's encoded URL: it must always resolve to the real public site, not
// whatever hostname happened to be in the admin's browser address bar when
// it was generated (localhost during local dev, a LAN IP, etc.). Defaults to
// the real production host regardless of environment — deliberately not
// gated on APP_ENV, since a misconfigured APP_ENV on the live server would
// otherwise silently break every QR code. A developer who wants QR codes to
// resolve to their own machine while testing can override this in their own
// local .env with APP_PUBLIC_URL=http://localhost (or their LAN IP).
define('APP_PUBLIC_URL', rtrim((string) envValue('APP_PUBLIC_URL', 'https://ipms.infragovservices.com'), '/'));
define('APP_LOGIN_PATH', APP_BASE_PATH . '/auth/login.php');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_NAME', envValue('DB_NAME', 'lgu_infrastructure'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));

// Shared secret for verifying SSO tokens issued by Main LGU (infragovservices.com hub).
// Must match SSO_SECRET_IPMS in Main LGU's .env.
define('SSO_SHARED_SECRET', envValue('SSO_SHARED_SECRET', 'f56d2000a6be7cde816cb174274824462644e2255e9ee39b4946d166a933e490'));

// Email Configuration
define('MAIL_FROM_EMAIL', envValue('MAIL_FROM_EMAIL', 'ipms.infragovservicesph@gmail.com'));
define('MAIL_FROM_NAME', envValue('MAIL_FROM_NAME', 'LGU IPMS System'));
define('MAIL_HOST', envValue('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) envValue('MAIL_PORT', '587'));
define('MAIL_USERNAME', envValue('MAIL_USERNAME', 'ipms.infragovservicesph@gmail.com'));
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

// Facilities Reservation System — outbound integration, same shape as
// Urban Planning's inspection-results.php: their repo has no live endpoint
// yet for us to push to (its routes are still placeholder view files, per
// https://github.com/lmfollero123/facilities-reservation-system1), so this
// is a pull/poll feed they call once they build their consumer, not a push.
// Shared-secret model: both sides must set the same key.
define('FACILITIES_RESERVATION_API_KEY', envValue('FACILITIES_RESERVATION_API_KEY', ''));

// LG Road Monitoring System — outbound integration, same shape as Facilities
// Reservation and Urban Planning's road-geometry-feed.php: they have no
// live endpoint of their own yet (per https://github.com/conopioclarence96-commits/lg-road-monitoring,
// as of writing), so this is a pull/poll feed they call, not a push.
// Shared-secret model: both sides must set the same key.
define('ROAD_MONITORING_API_KEY', envValue('ROAD_MONITORING_API_KEY', ''));

// RGMap / LGU Road Project sync — outbound integration for Roads and Bridges
// projects created or updated in IPMS. The receiving side is expected to be an
// endpoint like:
// https://rgmap.infragovservices.com/lgu_staff/pages/api/ipms-road-projects-pull.php?key=...
// where the same shared secret must be configured on both sides.
define('RGMAP_API_ENABLED', filter_var(envValue('RGMAP_API_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN));
define('RGMAP_API_URL', envValue('RGMAP_API_URL', 'https://rgmap.infragovservices.com/lgu_staff/pages/api/ipms-road-projects-pull.php'));
define('RGMAP_API_KEY', envValue('RGMAP_API_KEY', ''));
define('RGMAP_API_TIMEOUT', (int) envValue('RGMAP_API_TIMEOUT', '20'));
define('RGMAP_SSL_VERIFY', filter_var(envValue('RGMAP_SSL_VERIFY', '1'), FILTER_VALIDATE_BOOLEAN));

// AI Chatbot — landing page + citizen dashboard widget (api/chatbot.php,
// includes/ChatbotClient.php). Free key (no credit card): https://aistudio.google.com/apikey
define('GEMINI_API_KEY', envValue('GEMINI_API_KEY', ''));
define('GEMINI_MODEL', envValue('GEMINI_MODEL', 'gemini-flash-lite-latest'));

// The one thing to change to sync additional barangays into the Public
// Facilities Integration (admin/api/public-facilities.php) and the
// Facilities Reservation feed (integrations/facilities-reservation/) later —
// nothing else in either file needs to change.
const PUBLIC_FACILITIES_BARANGAY_FILTER = 'Culiat';

const APP_ROLES = ['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen', 'hope'];

const ROLE_LABELS = [
    'super_admin' => 'Super Admin',
    'admin' => 'LGU Admin / Engineering Head',
    'bac' => 'BAC (Bids & Awards Committee)',
    'engineer' => 'Engineer',
    'contractor' => 'Contractor',
    'citizen' => 'Citizen / Public User',
    'hope' => 'City Mayor',
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

/** Same as appUrl(), but fully-qualified (scheme+host+path) — see APP_PUBLIC_URL above. */
function publicUrl(string $path = ''): string
{
    return APP_PUBLIC_URL . appUrl($path);
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
