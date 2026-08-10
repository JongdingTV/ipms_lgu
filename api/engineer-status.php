<?php
// ============================================================
// api/engineer-status.php — backs the floating Engineer Live Status widget
// (Admin/Head Office, see assets/js/engineer-status-widget.js) and the
// engineer's own "My Field Status" control (topbar user-menu, see
// assets/js/engineer.js). Two audiences, one file, same shape as the
// portal.php convention used elsewhere in this codebase.
//
// Actions:
//   GET  list       — admin/hope/super_admin: full engineer roster + summary.
//   GET  mine        — engineer: own current status + active assignments.
//   POST set_status  — engineer: change work_status/project/activity.
//   POST heartbeat    — engineer: presence ping (users.last_seen_at = NOW()).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/engineer-status.php';
require_once __DIR__ . '/../engineer/includes/scope.php';

apiHeaders();
requireAnyRole(['admin', 'hope', 'super_admin', 'engineer']);
requireCsrfProtection();

$db = getDB();
engineerStatusEnsureSchema($db);

$user = currentUser();
$role = (string) ($user['role'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    if (!in_array($role, ['admin', 'hope', 'super_admin'], true)) {
        respond(['error' => 'Access denied'], 403);
    }

    $engineers = engineerStatusFetchList($db);
    respond([
        'summary' => engineerStatusSummarize($engineers),
        'has_alerts' => (bool) array_filter($engineers, static fn($e) => $e['needs_attention']),
        'engineers' => $engineers,
    ]);
}

if ($method === 'GET' && $action === 'mine') {
    if ($role !== 'engineer') {
        respond(['error' => 'Access denied'], 403);
    }

    $engineerId = (int) $user['user_id'];
    $status = engineerStatusFetchOne($db, $engineerId);

    $stmt = $db->prepare("
        SELECT p.id, p.project_code, p.name
        FROM engineer_project_assignments a
        INNER JOIN projects p ON p.id = a.project_id
        WHERE a.engineer_id = ? AND a.status = 'active'
        ORDER BY p.name ASC
    ");
    $stmt->execute([$engineerId]);

    respond([
        'work_status' => $status['work_status'] ?? 'available',
        'activity' => $status['activity'] ?? null,
        'project_id' => $status['project']['id'] ?? null,
        'projects' => $stmt->fetchAll(),
    ]);
}

if ($method === 'POST' && $action === 'set_status') {
    if ($role !== 'engineer') {
        respond(['error' => 'Access denied'], 403);
    }

    $engineerId = (int) $user['user_id'];
    $body = requestBody();
    $workStatus = (string) ($body['work_status'] ?? '');
    if (!in_array($workStatus, ENGINEER_STATUS_WORK_STATUSES, true)) {
        respond(['error' => 'Invalid work status.'], 422);
    }

    $needsProject = in_array($workStatus, ENGINEER_STATUS_PROJECT_REQUIRED, true);
    $projectId = $needsProject ? (int) ($body['project_id'] ?? 0) : null;
    $activity = $needsProject ? trim((string) ($body['activity'] ?? '')) : null;

    if ($needsProject) {
        if ($projectId <= 0 || !engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Choose one of your active assigned projects.'], 422);
        }
        $activity = $activity !== '' ? substr($activity, 0, 150) : null;
    }

    // Only stamp a fresh started_at when the status/project actually changed —
    // re-saving the same activity text shouldn't reset "how long they've been
    // on this" back to zero.
    $existing = $db->prepare("SELECT work_status, project_id FROM engineer_work_status WHERE engineer_id = ?");
    $existing->execute([$engineerId]);
    $prev = $existing->fetch();
    $changed = !$prev || $prev['work_status'] !== $workStatus || (int) ($prev['project_id'] ?? 0) !== (int) $projectId;

    $stmt = $db->prepare("
        INSERT INTO engineer_work_status (engineer_id, work_status, project_id, activity, started_at)
        VALUES (?, ?, ?, ?, IF(?, NOW(), NULL))
        ON DUPLICATE KEY UPDATE
            work_status = VALUES(work_status),
            project_id = VALUES(project_id),
            activity = VALUES(activity),
            started_at = IF(?, NOW(), started_at)
    ");
    $stmt->execute([$engineerId, $workStatus, $projectId, $activity, $changed ? 1 : 0, $changed ? 1 : 0]);

    respond(['success' => true]);
}

if ($method === 'POST' && $action === 'heartbeat') {
    if ($role !== 'engineer') {
        respond(['error' => 'Access denied'], 403);
    }

    $stmt = $db->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?");
    $stmt->execute([(int) $user['user_id']]);

    respond(['success' => true]);
}

respond(['error' => 'Unknown action.'], 404);
