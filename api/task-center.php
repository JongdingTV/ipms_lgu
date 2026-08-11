<?php
// ============================================================
// api/task-center.php — Smart Task & Assignment Center.
//
// Every task is computed live by includes/TaskCenter.php from tables that
// are already the source of truth — this endpoint never stores the tasks
// themselves. task_center_dismissals is the only persisted state, and it
// only ever hides a task from one user's own view; it never marks anything
// "complete" (there is deliberately no such action here — completion is
// only ever a side effect of the real workflow action happening elsewhere).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/TaskCenter.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'hope']);
requireCsrfProtection();

$db = getDB();
taskCenterEnsureSchema($db);

$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);
$role = (string) ($user['role'] ?? '');

// "Recently completed" is a reasonable-effort read of the existing audit
// trail (module names this feature's task types already use), not a strict
// per-task completion ledger — building that would mean mapping every real
// workflow action name back to a synthetic task type, which is exactly the
// kind of duplicate bookkeeping this feature is required not to invent.
const TASK_CENTER_MODULES = [
    'Project Registration', 'Contractor Assignment', 'Payments', 'Project Approval',
    'Citizen Feedback', 'Citizen Ratings', 'Inspections', 'Milestones', 'Urban Planning',
    'Contract Award', 'Project Deletion', 'Project Edit', 'Procurement', 'Procurement Documents',
    'Contractor Accreditation', 'Accreditation Documents', 'Bidding', 'User Governance', 'Login Security',
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET' && $action === 'summary') {
    $tasks = taskCenterFilterDismissed($db, $userId, taskCenterForRole($db, $role, $userId));
    respond(['success' => true, 'counts' => taskCenterCounts($tasks)]);
}

if ($method === 'GET' && $action === 'list') {
    $filter = (string) ($_GET['filter'] ?? '');
    $search = trim((string) ($_GET['search'] ?? ''));
    $module = trim((string) ($_GET['module'] ?? ''));
    $projectId = !empty($_GET['project_id']) ? (int) $_GET['project_id'] : null;

    if ($filter === 'completed') {
        $placeholders = implode(',', array_fill(0, count(TASK_CENTER_MODULES), '?'));
        $stmt = $db->prepare("
            SELECT action, module, details, record_id, created_at
            FROM activity_logs
            WHERE user_id = ? AND module IN ($placeholders)
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmt->execute(array_merge([$userId], TASK_CENTER_MODULES));
        respond(['success' => true, 'completed' => $stmt->fetchAll(), 'counts' => null]);
    }

    $allTasks = taskCenterForRole($db, $role, $userId);

    if ($filter === 'dismissed') {
        $dismissStmt = $db->prepare('SELECT task_key FROM task_center_dismissals WHERE user_id = ?');
        $dismissStmt->execute([$userId]);
        $dismissedKeys = array_flip($dismissStmt->fetchAll(PDO::FETCH_COLUMN));
        $tasks = array_values(array_filter($allTasks, fn($t) => isset($dismissedKeys[$t['key']])));
    } else {
        $tasks = taskCenterFilterDismissed($db, $userId, $allTasks);

        if ($filter === 'urgent' || $filter === 'due_today' || $filter === 'upcoming') {
            $tasks = array_values(array_filter($tasks, fn($t) => $t['priority'] === $filter));
        } elseif ($filter === 'overdue') {
            $tasks = array_values(array_filter($tasks, fn($t) => $t['status'] === 'overdue'));
        }
    }

    if ($search !== '') {
        $needle = mb_strtolower($search);
        $tasks = array_values(array_filter($tasks, fn($t) =>
            str_contains(mb_strtolower($t['title']), $needle)
            || str_contains(mb_strtolower($t['description']), $needle)
            || str_contains(mb_strtolower((string) $t['project_name']), $needle)
        ));
    }
    if ($module !== '') {
        $tasks = array_values(array_filter($tasks, fn($t) => $t['module'] === $module));
    }
    if ($projectId !== null) {
        $tasks = array_values(array_filter($tasks, fn($t) => $t['project_id'] === $projectId));
    }

    $counts = taskCenterCounts(taskCenterFilterDismissed($db, $userId, $allTasks));
    respond(['success' => true, 'data' => $tasks, 'counts' => $counts]);
}

if ($method === 'POST' && $action === 'dismiss') {
    $body = requestBody();
    $taskKey = trim((string) ($body['task_key'] ?? ''));
    if ($taskKey === '') {
        respond(['success' => false, 'message' => 'task_key is required.'], 422);
    }

    $db->prepare('
        INSERT INTO task_center_dismissals (user_id, task_key, dismissed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE dismissed_at = NOW()
    ')->execute([$userId, $taskKey]);

    logActivity($userId, 'task_dismissed', "Dismissed task \"$taskKey\" from My Tasks.", 'Task Center', null);
    respond(['success' => true]);
}

if ($method === 'POST' && $action === 'undismiss') {
    $body = requestBody();
    $taskKey = trim((string) ($body['task_key'] ?? ''));
    if ($taskKey === '') {
        respond(['success' => false, 'message' => 'task_key is required.'], 422);
    }

    $db->prepare('DELETE FROM task_center_dismissals WHERE user_id = ? AND task_key = ?')->execute([$userId, $taskKey]);
    logActivity($userId, 'task_undismissed', "Restored task \"$taskKey\" to My Tasks.", 'Task Center', null);
    respond(['success' => true]);
}

if ($method === 'POST' && $action === 'opened') {
    $body = requestBody();
    $taskKey = trim((string) ($body['task_key'] ?? ''));
    if ($taskKey !== '') {
        logActivity($userId, 'task_opened', "Opened task \"$taskKey\" and navigated to perform the action.", 'Task Center', null);
    }
    respond(['success' => true]);
}

respond(['success' => false, 'message' => 'Unknown action.'], 404);
