<?php
/**
 * Read-only headline metric for the Main LGU SSO hub dashboard.
 * Auth: Authorization: Bearer <SSO_SHARED_SECRET> (same secret used for SSO).
 */
require_once __DIR__ . '/includes/db.php';

apiHeaders();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
$token = preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) ? $m[1] : '';

if (!hash_equals(SSO_SHARED_SECRET, $token)) {
    respond(['error' => 'unauthorized'], 403);
}

$count = (int) getDB()->query('SELECT COUNT(*) FROM projects')->fetchColumn();

respond(['count' => $count, 'label' => 'Infrastructure Projects']);
