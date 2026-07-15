<?php
// TEMPORARY dev helper for local screenshot testing — delete after use.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../auth/session.php';

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE role='citizen' AND status='active' ORDER BY id LIMIT 1");
$stmt->execute();
$u = $stmt->fetch();
if (!$u) exit('No citizen user');

establishUserSession($u);

$page = preg_replace('/[^a-z-]/', '', $_GET['page'] ?? '');
header('Location: ' . appUrl('/citizen/dashboard.php') . ($page !== '' ? '#' . $page : ''));
exit;
