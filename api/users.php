<?php
// ============================================================
// api/users.php - user directory for admin assignment workflows
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$db = getDB();
$where = ['status = ?'];
$params = ['active'];

if (!empty($_GET['role'])) {
    $where[] = 'role = ?';
    $params[] = $_GET['role'];
}

if (!empty($_GET['search'])) {
    $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $term = '%' . $_GET['search'] . '%';
    array_push($params, $term, $term, $term);
}

$whereSql = implode(' AND ', $where);
$stmt = $db->prepare("
    SELECT id, username, email, full_name, role, status
    FROM users
    WHERE $whereSql
    ORDER BY full_name ASC, username ASC
");
$stmt->execute($params);

respond(['data' => $stmt->fetchAll()]);
