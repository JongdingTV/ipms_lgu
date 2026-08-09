<?php
// ============================================================
// api/project-ratings.php — Citizen Project Ratings (Admin, READ-ONLY)
//
// Admin can only observe citizen star ratings/reviews, never edit or delete
// them — deliberately NOT following api/feedback.php's precedent, where
// Admin has full PUT/DELETE over citizen complaints. There is no mutation
// route wired up at all here: anything but GET is rejected before dispatch
// even looks at ?action=, so there is nothing for a future edit to
// accidentally expose.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);

$db = getDB();
projectRatingsEnsureSchema($db);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['success' => false, 'message' => 'Not found'], 404);
}

$where = ['1=1'];
$params = [];

if (!empty($_GET['project_id'])) {
    $where[] = 'r.project_id = ?';
    $params[] = (int) $_GET['project_id'];
}
if (!empty($_GET['search'])) {
    $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR p.name LIKE ? OR p.project_code LIKE ?)";
    $s = '%' . $_GET['search'] . '%';
    array_push($params, $s, $s, $s);
}

$whereSQL = implode(' AND ', $where);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$baseFrom = "
    FROM project_ratings r
    INNER JOIN citizens c ON c.id = r.citizen_id
    INNER JOIN projects p ON p.id = r.project_id
    WHERE $whereSQL
";

$total = $db->prepare("SELECT COUNT(*) $baseFrom");
$total->execute($params);
$totalRows = (int) $total->fetchColumn();

$avgStmt = $db->prepare("SELECT COALESCE(AVG(r.rating), 0) $baseFrom");
$avgStmt->execute($params);
$average = round((float) $avgStmt->fetchColumn(), 1);

$stmt = $db->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at, r.updated_at, r.project_id,
           p.project_code, p.name AS project_name,
           CONCAT(c.first_name, ' ', c.last_name) AS citizen_name
    $baseFrom
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);

respond([
    'success' => true,
    'data' => $stmt->fetchAll(),
    'total' => $totalRows,
    'page' => $page,
    'last_page' => (int) ceil($totalRows / $limit),
    'average' => $average,
]);
