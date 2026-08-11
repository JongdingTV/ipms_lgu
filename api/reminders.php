<?php
// ============================================================
// api/reminders.php — Automated Reminders.
//
// Reminders are computed live (includes/Reminders.php, same convention as
// api/task-center.php) — this endpoint never invents new task/workflow
// state. reminder_log (includes/workflow.php) is the only persisted state:
// a dedup ledger for actually-delivered notifications and a dismiss flag.
// Actual delivery happens via includes/Notifications.php's existing
// notifyUser() during a sweep — this file's `list` action is a read-only
// preview of the same live candidates, not a second data source.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Reminders.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'hope']);
requireCsrfProtection();

$db = getDB();
remindersEnsureSchema($db);

$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);
$role = (string) ($user['role'] ?? '');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET' && $action === 'list') {
    $filter = (string) ($_GET['filter'] ?? '');

    $logStmt = $db->prepare('SELECT reminder_key, status FROM reminder_log WHERE user_id = ?');
    $logStmt->execute([$userId]);
    $logByKey = [];
    foreach ($logStmt->fetchAll() as $row) {
        $logByKey[$row['reminder_key']] = $row['status'];
    }

    $items = [];
    foreach (reminderCandidatesForRole($db, $role, $userId) as $c) {
        $status = $logByKey[$c['reminder_key']] ?? 'sent';

        if ($filter === 'dismissed') {
            if ($status !== 'dismissed') continue;
        } else {
            if ($status === 'dismissed') continue;
            if ($filter === 'upcoming' && !in_array($c['bucket'], ['7d', '3d', '1d'], true)) continue;
            if ($filter === 'today' && $c['bucket'] !== 'today') continue;
            if ($filter === 'overdue' && $c['bucket'] !== 'overdue') continue;
        }

        [$title, $message, $link] = remindersFormatMessage($role, $c);
        $items[] = [
            'reminder_key' => $c['reminder_key'],
            'bucket' => $c['bucket'],
            'dismissible' => $c['dismissible'],
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'project_name' => $c['task']['project_name'],
            'module' => $c['task']['module'],
            'due_date' => $c['task']['due_date'],
        ];
    }

    respond(['success' => true, 'data' => $items]);
}

if ($method === 'POST' && $action === 'dismiss') {
    $body = requestBody();
    $reminderKey = trim((string) ($body['reminder_key'] ?? ''));
    if ($reminderKey === '') {
        respond(['success' => false, 'message' => 'reminder_key is required.'], 422);
    }

    $stmt = $db->prepare('
        UPDATE reminder_log SET status = "dismissed", dismissed_at = NOW()
        WHERE user_id = ? AND reminder_key = ? AND dismissible = 1
    ');
    $stmt->execute([$userId, $reminderKey]);

    if ($stmt->rowCount() === 0) {
        respond(['success' => false, 'message' => "This reminder can't be dismissed while the required action is still pending."], 422);
    }

    logActivity($userId, 'reminder_dismissed', "Dismissed reminder \"$reminderKey\".", 'Reminders', null);
    respond(['success' => true]);
}

if ($method === 'POST' && $action === 'sweep') {
    $generated = remindersSweep($db, $userId, $role);
    $escalated = remindersEscalationSweep($db);
    respond(['success' => true, 'generated' => $generated, 'escalated' => $escalated]);
}

respond(['success' => false, 'message' => 'Unknown action.'], 404);
