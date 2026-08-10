<?php
// ============================================================
// api/project-ratings.php — Citizen Project Ratings (Admin moderation)
//
// Admin/super_admin can moderate (approve/reject/flag/archive) citizen
// ratings, but the UPDATE below structurally never references `rating` or
// `comment` — staff can change whether a review is public, never what it
// says. Mirrors api/feedback.php's PUT-status-update UX pattern but keeps
// its own dedicated table/page, since ratings and complaints are shaped
// differently (star+optional text vs. category/priority/message).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Notifications.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
projectRatingsEnsureSchema($db);

$moderationStatuses = ['pending', 'approved', 'rejected', 'flagged', 'archived'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' && ($_GET['action'] ?? '') === 'moderate') {
    $body = requestBody();
    $id = (int) ($body['id'] ?? 0);
    $newStatus = (string) ($body['status'] ?? '');
    $remarks = trim((string) ($body['decision_remarks'] ?? ''));

    if ($id <= 0 || !in_array($newStatus, $moderationStatuses, true)) {
        respond(['success' => false, 'message' => 'Invalid moderation request.'], 422);
    }

    $stmt = $db->prepare("
        SELECT r.id, r.status, r.rating, r.project_id, p.name AS project_name, p.project_code,
               c.user_id AS citizen_user_id
        FROM project_ratings r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN citizens c ON c.id = r.citizen_id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Rating not found.'], 404);
    }

    $actorId = (int) (currentUser()['user_id'] ?? 0);

    // Structural guarantee, not a UI convention: rating/comment are not in
    // this SET list, so no future edit here can accidentally expose them.
    $db->prepare('UPDATE project_ratings SET status = ?, moderated_by = ?, moderated_at = NOW(), decision_remarks = ? WHERE id = ?')
        ->execute([$newStatus, $actorId ?: null, $remarks !== '' ? $remarks : null, $id]);

    $actionMap = [
        'pending' => 'project_rating_reset_to_pending',
        'approved' => 'project_rating_approved',
        'rejected' => 'project_rating_rejected',
        'flagged' => 'project_rating_flagged',
        'archived' => 'project_rating_archived',
    ];
    logActivity(
        $actorId ?: null,
        $actionMap[$newStatus],
        "Set rating #$id on {$row['project_name']} ({$row['project_code']}) to \"$newStatus\"." . ($remarks !== '' ? " Remarks: $remarks" : ''),
        'Project Ratings',
        $id
    );

    if (in_array($newStatus, ['approved', 'rejected'], true) && $row['status'] !== $newStatus) {
        notifyUser(
            (int) $row['citizen_user_id'],
            $newStatus === 'approved' ? 'info' : 'warning',
            $newStatus === 'approved' ? 'Your review was approved' : 'Your review was not approved',
            $newStatus === 'approved'
                ? "Your {$row['rating']}-star review on {$row['project_name']} is now publicly visible. Thank you for your feedback!"
                : "Your review on {$row['project_name']} was not approved for public display." . ($remarks !== '' ? " Reason: $remarks" : '')
        );
    }

    respond(['success' => true, 'status' => $newStatus]);
}

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
    SELECT r.id, r.rating, r.comment, r.status, r.created_at, r.updated_at, r.project_id,
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
