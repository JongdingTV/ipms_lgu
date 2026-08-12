<?php
// ============================================================
// includes/Reminders.php — Automated Reminders engine.
//
// This is deliberately NOT a second workflow system: every candidate comes
// straight from includes/TaskCenter.php's existing per-role predicates
// (taskCenterForEngineer/Admin/Hope/Bac/Contractor/SuperAdmin), with a
// timing layer on top that decides WHEN a given task is reminder-worthy and
// delivers it through the existing includes/Notifications.php::notifyUser()
// conduit. reminder_log (includes/workflow.php's remindersEnsureSchema()) is
// the only new persisted state — a dedup ledger + dismiss-state store,
// exactly mirroring task_center_dismissals' shape.
// ============================================================

require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/TaskCenter.php';
require_once __DIR__ . '/Notifications.php';

/**
 * Every threshold this due_date has now reached/passed, oldest-first. A
 * sweep that misses a calendar day still catches up next time (fires every
 * threshold still missing from reminder_log) instead of silently dropping
 * a reminder — the UNIQUE(user_id, reminder_key) constraint, not exact-day
 * timing, is what prevents a bucket from firing twice.
 */
function reminderTimingBuckets(string $dueDate): array
{
    $diff = (int) floor((strtotime($dueDate) - strtotime(date('Y-m-d'))) / 86400);
    $buckets = [];
    foreach ([7 => '7d', 3 => '3d', 1 => '1d', 0 => 'today'] as $threshold => $label) {
        if ($diff <= $threshold) {
            $buckets[] = $label;
        }
    }
    if ($diff < 0) {
        $buckets[] = 'overdue';
    }
    return $buckets;
}

/**
 * Maps a role to its portal's dashboard entry point, for building a real
 * clickable link (notifications.link has never been used anywhere in this
 * app until now — every existing notifyUser() call passes null).
 */
function remindersPortalPath(string $role): string
{
    return match ($role) {
        'engineer' => 'engineer/dashboard.php',
        'admin' => 'admin/dashboard.php',
        'hope' => 'hope/dashboard.php',
        'bac' => 'bac/dashboard.php',
        'contractor' => 'contractor/dashboard.php',
        'super_admin' => 'superadmin/dashboard.php',
        default => 'index.php',
    };
}

function remindersBuildLink(string $role, string $linkPage, array $linkParams = []): string
{
    $query = ['page' => $linkPage] + $linkParams;
    return appUrl('/' . remindersPortalPath($role) . '?' . http_build_query($query));
}

/**
 * Live-computed reminder candidates for one user — never stored, recomputed
 * fresh on every sweep. Respects task_center_dismissals the same way Task
 * Center's own badge computation already does, so dismissing something in
 * My Tasks stops Reminders from nagging about it too.
 */
function reminderCandidatesForRole(PDO $db, string $role, int $userId): array
{
    $tasks = taskCenterFilterDismissed($db, $userId, taskCenterForRole($db, $role, $userId));

    $candidates = [];
    foreach ($tasks as $t) {
        $infoOnly = $t['priority'] === 'info';

        if ($t['due_date'] !== null && $t['due_date'] !== '') {
            foreach (reminderTimingBuckets($t['due_date']) as $bucket) {
                $candidates[] = [
                    'reminder_key' => "{$t['key']}:{$t['due_date']}:{$bucket}",
                    'bucket' => $bucket,
                    'dismissible' => $infoOnly || in_array($bucket, ['7d', '3d', '1d'], true),
                    'task' => $t,
                ];
            }
        } elseif ($t['priority'] === 'urgent') {
            // Aging-only task (no real due date) — fires once, the moment
            // Task Center's own bucketing marks it urgent.
            $candidates[] = [
                'reminder_key' => "{$t['key']}:urgent",
                'bucket' => 'urgent',
                'dismissible' => $infoOnly,
                'task' => $t,
            ];
        }
    }
    return $candidates;
}

function remindersFormatMessage(string $role, array $candidate): array
{
    $t = $candidate['task'];
    $bucket = $candidate['bucket'];

    $icon = '🔔';
    if ($bucket === 'overdue' || $t['module'] === 'Login Security') {
        $icon = '🔴';
    } elseif (stripos($t['module'], 'Document') !== false) {
        $icon = '📄';
    } elseif ($t['due_date'] !== null) {
        $icon = '⏰';
    }

    $bucketLabel = [
        '7d' => 'in 7 days', '3d' => 'in 3 days', '1d' => 'tomorrow',
        'today' => 'due today', 'overdue' => 'overdue',
    ][$bucket] ?? null;

    $title = trim($icon . ' ' . $t['title'] . ($bucketLabel ? " — {$bucketLabel}" : ''));
    $title = mb_strimwidth($title, 0, 150, '…');

    $message = (string) $t['description'];
    if (!empty($t['project_name'])) {
        $message .= ' Project: ' . $t['project_name'] . '.';
    }
    if (!empty($t['due_date'])) {
        $message .= ' Due: ' . $t['due_date'] . '.';
    }

    $link = remindersBuildLink($role, $t['link_page'], $t['link_params'] ?? []);

    return [$title, $message, $link];
}

/**
 * Per-user candidate sweep — throttled to once per ~20 minutes per user
 * unless $force. Every notifyUser() call is gated by an INSERT IGNORE into
 * reminder_log succeeding (rowCount()===1), which is the real dedup
 * mechanism (notifyUser() itself has none) and is race-safe against two
 * concurrent pollers for the same user.
 */
function remindersSweep(PDO $db, int $userId, string $role, bool $force = false): int
{
    remindersEnsureSchema($db);

    if (!$force) {
        $stmt = $db->prepare('SELECT last_swept_at FROM reminder_sweep_state WHERE user_id = ?');
        $stmt->execute([$userId]);
        $last = $stmt->fetchColumn();
        if ($last && strtotime((string) $last) > strtotime('-20 minutes')) {
            return 0;
        }
    }

    $generated = 0;
    foreach (reminderCandidatesForRole($db, $role, $userId) as $c) {
        $ins = $db->prepare('
            INSERT IGNORE INTO reminder_log (user_id, reminder_key, dismissible, status, created_at)
            VALUES (?, ?, ?, "sent", NOW())
        ');
        $ins->execute([$userId, $c['reminder_key'], $c['dismissible'] ? 1 : 0]);

        if ($ins->rowCount() === 1) {
            [$title, $message, $link] = remindersFormatMessage($role, $c);
            notifyUser($userId, 'reminder', $title, $message, $link);
            logActivity($userId, 'reminder_generated', $message, 'Reminders', $c['task']['project_id'] ?? null);
            $generated++;
        }
    }

    $db->prepare('
        INSERT INTO reminder_sweep_state (user_id, last_swept_at) VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_swept_at = NOW()
    ')->execute([$userId]);

    return $generated;
}

/**
 * Escalation is deliberately its OWN sweep, not folded into remindersSweep()
 * — an engineer/contractor whose overdue item needs escalating is exactly
 * the person who is NOT actively polling, so escalation is triggered
 * opportunistically by any authenticated poll (regardless of who), throttled
 * by the single-row reminder_escalation_state singleton, and queries overdue
 * records directly across ALL engineers/contractors rather than looping
 * per-user Task Center calls.
 */
function remindersEscalationSweep(PDO $db, bool $force = false, int $escalationThresholdDays = 3): int
{
    remindersEnsureSchema($db);

    if (!$force) {
        $last = $db->query('SELECT last_swept_at FROM reminder_escalation_state WHERE id = 1')->fetchColumn();
        if ($last && strtotime((string) $last) > strtotime('-20 minutes')) {
            return 0;
        }
    }

    $escalated = 0;

    // Engineer: inspection still pending well after the reminder first fired.
    $stmt = $db->prepare("
        SELECT r.id AS report_id, r.project_id, p.project_code, p.name AS project_name, rl.user_id AS owner_user_id
        FROM contractor_reports r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN engineer_project_assignments a ON a.project_id = r.project_id AND a.status = 'active'
        LEFT JOIN inspections i ON i.progress_report_id = r.id
        INNER JOIN reminder_log rl ON rl.user_id = a.engineer_id AND rl.reminder_key = CONCAT('inspection_pending:', r.id, ':urgent')
        WHERE (i.id IS NULL OR (i.status = 'submitted' AND r.status IN ('submitted','under_review')))
          AND rl.created_at <= (NOW() - INTERVAL ? DAY)
    ");
    $stmt->execute([$escalationThresholdDays]);
    foreach ($stmt->fetchAll() as $row) {
        $escalated += remindersEscalateOne(
            $db,
            "inspection_pending:{$row['report_id']}:escalated",
            "🔴 Inspection Still Pending — {$row['project_code']}",
            "{$row['project_code']} — {$row['project_name']} has an unreviewed progress report that has been overdue for over {$escalationThresholdDays} days.",
            (int) $row['project_id']
        );
    }

    // Contractor: rejected accreditation document still not corrected.
    $stmt = $db->prepare("
        SELECT d.id AS document_id, d.title, c.id AS contractor_id, c.name AS contractor_name, rl.user_id AS owner_user_id
        FROM supporting_documents d
        INNER JOIN contractors c ON c.id = d.owner_id AND d.owner_type = 'contractor'
        INNER JOIN reminder_log rl ON rl.user_id = c.user_id AND rl.reminder_key = CONCAT('contractor_doc_rejected:', d.id, ':urgent')
        WHERE d.status = 'rejected'
          AND rl.created_at <= (NOW() - INTERVAL ? DAY)
    ");
    $stmt->execute([$escalationThresholdDays]);
    foreach ($stmt->fetchAll() as $row) {
        $escalated += remindersEscalateOne(
            $db,
            "contractor_doc_rejected:{$row['document_id']}:escalated",
            "🔴 Rejected Document Still Outstanding",
            htmlspecialchars($row['contractor_name']) . '\'s rejected document "' . htmlspecialchars($row['title']) . '" has not been re-uploaded for over ' . $escalationThresholdDays . ' days.',
            null
        );
    }

    $db->exec('
        INSERT INTO reminder_escalation_state (id, last_swept_at) VALUES (1, NOW())
        ON DUPLICATE KEY UPDATE last_swept_at = NOW()
    ');

    return $escalated;
}

/** Notifies every active admin once for a given escalation key; no-ops if already escalated. */
function remindersEscalateOne(PDO $db, string $escalationKey, string $title, string $message, ?int $projectId): int
{
    $already = $db->prepare('SELECT COUNT(*) FROM reminder_log WHERE reminder_key = ?');
    $already->execute([$escalationKey]);
    if ((int) $already->fetchColumn() > 0) {
        return 0;
    }

    $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;
    foreach ($admins as $adminId) {
        $ins = $db->prepare('
            INSERT IGNORE INTO reminder_log (user_id, reminder_key, dismissible, status, created_at)
            VALUES (?, ?, 1, "sent", NOW())
        ');
        $ins->execute([(int) $adminId, $escalationKey]);
        if ($ins->rowCount() === 1) {
            notifyUser((int) $adminId, 'reminder', $title, $message, null);
            logActivity((int) $adminId, 'reminder_escalated', $message, 'Reminders', $projectId);
            $count++;
        }
    }
    return $count;
}
