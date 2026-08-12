<?php
// ============================================================
// api/project-ratings.php — Citizen Project Ratings (Admin read-only view)
//
// Reviews are public the moment a citizen submits them (see
// citizen/api/project-rating.php) — admin/super_admin can see every review
// here for visibility, but this endpoint has no mutation route at all.
// Staff cannot approve, reject, flag, archive, or otherwise hide a citizen's
// review; that would let admin control public opinion of a public project.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Notifications.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);

$db = getDB();
projectRatingsEnsureSchema($db);

$moderationStatuses = ['pending', 'approved', 'rejected', 'flagged', 'archived'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    respond(['success' => false, 'message' => 'Not found'], 404);
}

if (!empty($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT r.*, p.project_code, p.name AS project_name,
               CONCAT(c.first_name, ' ', c.last_name) AS citizen_name,
               mu.full_name AS moderated_by_name
        FROM project_ratings r
        INNER JOIN citizens c ON c.id = r.citizen_id
        INNER JOIN projects p ON p.id = r.project_id
        LEFT JOIN users mu ON mu.id = r.moderated_by
        WHERE r.id = ?
    ");
    $stmt->execute([(int) $_GET['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Not found'], 404);
    }
    respond(['success' => true, 'data' => $row]);
}

$where = ['1=1'];
$params = [];

if (!empty($_GET['project_id'])) {
    $where[] = 'r.project_id = ?';
    $params[] = (int) $_GET['project_id'];
}
if (!empty($_GET['status']) && in_array($_GET['status'], $moderationStatuses, true)) {
    $where[] = 'r.status = ?';
    $params[] = $_GET['status'];
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
    SELECT r.id, r.rating, r.comment, r.status, r.is_anonymous, r.created_at, r.updated_at, r.project_id,
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
