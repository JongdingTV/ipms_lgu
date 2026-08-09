<?php
// ============================================================
// citizen/api/id-photo.php — controlled read gateway for uploaded ID photos
//
// assets/img/citizen-ids/.htaccess denies all direct HTTP access to that
// folder ("Allow server-side scripts to read files from disk and serve them
// via a controlled endpoint" — this is that endpoint, which didn't exist
// yet). Access is gated to the citizen who owns the photo, or staff
// (super_admin/admin) reviewing ID verification — never the public.
// ============================================================
require_once __DIR__ . '/../../auth/session.php';

$user = requireLogin();

$citizenId = (int) ($_GET['citizen_id'] ?? 0);
if ($citizenId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT user_id, id_photo_path FROM citizens WHERE id = ?');
$stmt->execute([$citizenId]);
$citizen = $stmt->fetch();

if (!$citizen || empty($citizen['id_photo_path'])) {
    http_response_code(404);
    exit('Not found.');
}

$isOwner = ((int) $citizen['user_id']) === (int) ($user['user_id'] ?? 0);
$isStaffReviewer = in_array((string) ($user['role'] ?? ''), ['super_admin', 'admin'], true);
if (!$isOwner && !$isStaffReviewer) {
    http_response_code(403);
    exit('Forbidden.');
}

// id_photo_path is stored as an app-relative path like
// "/assets/img/citizen-ids/citizen_id_....png" — resolved and re-confirmed
// to stay inside that one folder before ever touching the filesystem, so a
// tampered/corrupt DB value can't be used to read anything else on disk.
$appRoot = dirname(__DIR__, 2);
$realBase = realpath($appRoot . '/assets/img/citizen-ids');
$realPath = realpath($appRoot . $citizen['id_photo_path']);

if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase)) {
    http_response_code(404);
    exit('Not found.');
}

$mimeType = function_exists('mime_content_type') ? (mime_content_type($realPath) ?: 'application/octet-stream') : 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($realPath));
header('Cache-Control: private, max-age=0, no-store');
header('X-Content-Type-Options: nosniff');
readfile($realPath);
exit;
