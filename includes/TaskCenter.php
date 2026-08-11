<?php
// ============================================================
// includes/TaskCenter.php — Smart Task & Assignment Center engine.
//
// Every function here COMPUTES tasks fresh from tables that are already the
// source of truth (milestones, payment_requests, staff_account_requests,
// ...) — nothing here is ever stored. This mirrors api/sidebar-badges.php's
// live-query-per-role convention exactly; api/task-center.php is the only
// caller, and task_center_dismissals (includes/workflow.php) is the only
// persisted state the whole feature owns.
//
// Task shape (associative array):
//   key            deterministic "{type}:{record_id}" — never random, so
//                  dismissing/re-fetching the same real-world task is stable
//   title, description
//   project_id, project_name
//   module         human label, matches this app's existing module naming
//   priority       'urgent' | 'due_today' | 'upcoming' | 'info'
//   due_date       'Y-m-d' or null
//   created_date   'Y-m-d H:i:s'
//   status         'overdue' | 'pending' | 'in_progress' — derived, never stored
//   link_page      an existing page-* route in that role's own portal JS
//   link_params    array merged into the deep-link (e.g. ['project_id' => 8])
// ============================================================

require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/../contractor/includes/scope.php';
require_once __DIR__ . '/DocumentChecklist.php';

/**
 * No due date -> ages against created_date instead (pure FIFO approval
 * queues like HOPE's have no deadline column anywhere in this schema).
 */
function taskCenterPriorityBucket(?string $dueDate, string $createdDate, int $agingThresholdDays = 7): string
{
    $today = date('Y-m-d');

    if ($dueDate !== null && $dueDate !== '') {
        if ($dueDate < $today) return 'urgent';
        if ($dueDate === $today) return 'due_today';
        return 'upcoming';
    }

    $days = (strtotime($today) - strtotime(substr($createdDate, 0, 10))) / 86400;
    return $days >= $agingThresholdDays ? 'urgent' : 'upcoming';
}

function taskCenterStatusLabel(?string $dueDate): string
{
    if ($dueDate !== null && $dueDate !== '' && $dueDate < date('Y-m-d')) {
        return 'overdue';
    }
    return 'pending';
}

/** Urgent (most-overdue first) -> Due Today -> Upcoming (soonest first) -> Info. */
function taskCenterSort(array $tasks): array
{
    $weight = ['urgent' => 0, 'due_today' => 1, 'upcoming' => 2, 'info' => 3];
    usort($tasks, function (array $a, array $b) use ($weight): int {
        $wa = $weight[$a['priority']] ?? 4;
        $wb = $weight[$b['priority']] ?? 4;
        if ($wa !== $wb) {
            return $wa <=> $wb;
        }
        $da = $a['due_date'] ?? '9999-12-31';
        $db = $b['due_date'] ?? '9999-12-31';
        return $da <=> $db;
    });
    return $tasks;
}

function taskCenterCounts(array $tasks): array
{
    $counts = ['urgent' => 0, 'due_today' => 0, 'upcoming' => 0, 'info' => 0, 'total' => 0];
    foreach ($tasks as $t) {
        $counts[$t['priority']] = ($counts[$t['priority']] ?? 0) + 1;
        if ($t['priority'] !== 'info') {
            $counts['total']++;
        }
    }
    return $counts;
}

// ── ENGINEER ──────────────────────────────────────────────────────────────
function taskCenterForEngineer(PDO $db, int $userId): array
{
    $tasks = [];

    // Inspection to conduct — same predicate as engineer/api/portal.php's
    // action=pending_inspections: a contractor progress report with no
    // matching inspection yet, or still submitted/under_review.
    $stmt = $db->prepare("
        SELECT r.id, r.project_id, r.report_date, r.status, r.created_at,
               p.project_code, p.name AS project_name
        FROM contractor_reports r
        INNER JOIN engineer_project_assignments a ON a.project_id = r.project_id AND a.engineer_id = ? AND a.status = 'active'
        INNER JOIN projects p ON p.id = r.project_id
        LEFT JOIN inspections i ON i.progress_report_id = r.id
        WHERE i.id IS NULL OR r.status IN ('submitted', 'under_review')
        ORDER BY r.report_date ASC
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $r) {
        $priority = taskCenterPriorityBucket(null, $r['created_at'], 2);
        $tasks[] = [
            'key' => 'inspection_pending:' . $r['id'],
            'title' => 'Inspection Required',
            'description' => 'Contractor progress report (' . htmlspecialchars($r['report_date']) . ') awaiting your inspection.',
            'project_id' => (int) $r['project_id'], 'project_name' => $r['project_code'] . ' — ' . $r['project_name'],
            'module' => 'Inspections', 'priority' => $priority, 'due_date' => null, 'created_date' => $r['created_at'],
            'status' => 'pending', 'link_page' => 'inspection-review', 'link_params' => ['project_id' => (int) $r['project_id']],
        ];
    }

    // Milestones — overdue/due-today/upcoming, own assigned projects only.
    $stmt = $db->prepare("
        SELECT m.id, m.project_id, m.title, m.due_date, m.created_at,
               p.project_code, p.name AS project_name
        FROM milestones m
        INNER JOIN engineer_project_assignments a ON a.project_id = m.project_id AND a.engineer_id = ? AND a.status = 'active'
        INNER JOIN projects p ON p.id = m.project_id
        WHERE m.completed = 0
        ORDER BY m.due_date IS NULL, m.due_date ASC
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $m) {
        $priority = $m['due_date'] !== null ? taskCenterPriorityBucket($m['due_date'], $m['created_at']) : 'upcoming';
        $tasks[] = [
            'key' => 'milestone_pending:' . $m['id'],
            'title' => 'Milestone Update Required',
            'description' => htmlspecialchars($m['title']) . ($m['due_date'] ? ' — due ' . $m['due_date'] : ' — no due date set'),
            'project_id' => (int) $m['project_id'], 'project_name' => $m['project_code'] . ' — ' . $m['project_name'],
            'module' => 'Milestones', 'priority' => $priority, 'due_date' => $m['due_date'], 'created_date' => $m['created_at'],
            'status' => taskCenterStatusLabel($m['due_date']), 'link_page' => 'milestone-update', 'link_params' => ['project_id' => (int) $m['project_id']],
        ];
    }

    // Payment requests to review — engineer's first pass (pushes to
    // under_review/rejected; admin gives the final approval).
    $stmt = $db->prepare("
        SELECT pr.id, pr.project_id, pr.submitted_at, pr.requested_amount,
               p.project_code, p.name AS project_name
        FROM payment_requests pr
        INNER JOIN engineer_project_assignments a ON a.project_id = pr.project_id AND a.engineer_id = ? AND a.status = 'active'
        INNER JOIN projects p ON p.id = pr.project_id
        WHERE pr.status = 'submitted'
        ORDER BY pr.submitted_at ASC
    ");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $pr) {
        $tasks[] = [
            'key' => 'payment_review:' . $pr['id'],
            'title' => 'Payment Request to Review',
            'description' => 'Billing amount ₱' . number_format((float) $pr['requested_amount'], 2) . ' awaiting your review.',
            'project_id' => (int) $pr['project_id'], 'project_name' => $pr['project_code'] . ' — ' . $pr['project_name'],
            'module' => 'Payments', 'priority' => taskCenterPriorityBucket(null, $pr['submitted_at'], 2), 'due_date' => null,
            'created_date' => $pr['submitted_at'], 'status' => 'pending', 'link_page' => 'payment-review', 'link_params' => ['project_id' => (int) $pr['project_id']],
        ];
    }

    // Urban Planning road inspection requests — unscoped by design (matches
    // engineer/api/urban-planning.php's own list_requests action, which any
    // engineer can see regardless of district).
    $stmt = $db->query("
        SELECT id, road_name, barangay, district, priority, request_date, status
        FROM urban_planning_inspections
        WHERE status IN ('pending', 'assigned', 'in_progress', 'returned')
        ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low'), request_date ASC
    ");
    foreach ($stmt->fetchAll() as $u) {
        $priority = in_array($u['priority'], ['urgent', 'high'], true)
            ? taskCenterPriorityBucket(null, $u['request_date'], 1)
            : taskCenterPriorityBucket(null, $u['request_date'], 5);
        $tasks[] = [
            'key' => 'urban_inspection:' . $u['id'],
            'title' => 'Road Inspection Requested',
            'description' => htmlspecialchars($u['road_name']) . ', Barangay ' . htmlspecialchars($u['barangay']) . ' (' . htmlspecialchars($u['district']) . ')',
            'project_id' => null, 'project_name' => null,
            'module' => 'Urban Planning', 'priority' => $priority, 'due_date' => null, 'created_date' => $u['request_date'],
            'status' => $u['status'] === 'in_progress' ? 'in_progress' : 'pending', 'link_page' => 'urban-planning-inspection', 'link_params' => [],
        ];
    }

    return taskCenterSort($tasks);
}

// ── ADMIN ─────────────────────────────────────────────────────────────────
function taskCenterForAdmin(PDO $db, int $userId): array
{
    $tasks = [];

    $stmt = $db->query("SELECT id, project_code, name, status, created_at FROM projects WHERE status IN ('draft', 'returned') ORDER BY updated_at DESC");
    foreach ($stmt->fetchAll() as $p) {
        $isReturned = $p['status'] === 'returned';
        $tasks[] = [
            'key' => 'project_registration:' . $p['id'],
            'title' => $isReturned ? 'Project Returned for Revision' : 'Project Registration Incomplete',
            'description' => $p['project_code'] . ' — ' . $p['name'],
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Project Registration', 'priority' => $isReturned ? 'urgent' : taskCenterPriorityBucket(null, $p['created_at']),
            'due_date' => null, 'created_date' => $p['created_at'], 'status' => 'pending',
            'link_page' => 'project-registration', 'link_params' => ['project_id' => (int) $p['id']],
        ];
    }

    $stmt = $db->query("SELECT id, project_code, name, updated_at FROM projects WHERE status = 'awarded' AND contractor_id IS NULL");
    foreach ($stmt->fetchAll() as $p) {
        $tasks[] = [
            'key' => 'contractor_assignment:' . $p['id'],
            'title' => 'Contractor Assignment Pending',
            'description' => $p['project_code'] . ' — ' . $p['name'] . ' has been awarded but has no contractor assigned yet.',
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Contractor Assignment', 'priority' => taskCenterPriorityBucket(null, $p['updated_at']),
            'due_date' => null, 'created_date' => $p['updated_at'], 'status' => 'pending',
            'link_page' => 'contractor-assignment', 'link_params' => ['project_id' => (int) $p['id']],
        ];
    }

    $stmt = $db->query("
        SELECT pr.id, pr.project_id, pr.submitted_at, pr.requested_amount, pr.status,
               p.project_code, p.name AS project_name
        FROM payment_requests pr INNER JOIN projects p ON p.id = pr.project_id
        WHERE pr.status IN ('submitted', 'under_review') ORDER BY pr.submitted_at ASC
    ");
    foreach ($stmt->fetchAll() as $pr) {
        $tasks[] = [
            'key' => 'admin_payment_review:' . $pr['id'],
            'title' => 'Payment Request Requires Review',
            'description' => 'Billing amount ₱' . number_format((float) $pr['requested_amount'], 2) . ' — status: ' . $pr['status'] . '.',
            'project_id' => (int) $pr['project_id'], 'project_name' => $pr['project_code'] . ' — ' . $pr['project_name'],
            'module' => 'Payments', 'priority' => taskCenterPriorityBucket(null, $pr['submitted_at'], 2), 'due_date' => null,
            'created_date' => $pr['submitted_at'], 'status' => $pr['status'] === 'under_review' ? 'in_progress' : 'pending',
            'link_page' => 'workflow-management', 'link_params' => [],
        ];
    }

    $stmt = $db->query("SELECT id, project_code, name, completion_inspected_at FROM projects WHERE status = 'completed'");
    foreach ($stmt->fetchAll() as $p) {
        $tasks[] = [
            'key' => 'project_turnover:' . $p['id'],
            'title' => 'Project Completion Requires Processing',
            'description' => $p['project_code'] . ' — ' . $p['name'] . ' passed completion inspection and is ready for turnover.',
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Project Approval', 'priority' => 'urgent', 'due_date' => null,
            'created_date' => $p['completion_inspected_at'] ?? date('Y-m-d H:i:s'), 'status' => 'pending',
            'link_page' => 'project-approval', 'link_params' => ['project_id' => (int) $p['id']],
        ];
    }

    // Citizen feedback — reuses the existing rule-based scorer verbatim
    // (api/feedback.php action=needs_attention) rather than re-deriving it.
    foreach (taskCenterFeedbackNeedsAttention($db) as $f) {
        $tasks[] = [
            'key' => 'feedback_attention:' . $f['id'],
            'title' => 'Citizen Feedback Requires Attention',
            'description' => mb_strimwidth($f['message'], 0, 120, '…') . ' (' . $f['reason'] . ')',
            'project_id' => null, 'project_name' => $f['project_name'] ?? 'General',
            'module' => 'Citizen Feedback', 'priority' => $f['score'] >= 40 ? 'urgent' : 'upcoming', 'due_date' => null,
            'created_date' => $f['created_at'], 'status' => 'pending', 'link_page' => 'citizen-feedback', 'link_params' => [],
        ];
    }

    $stmt = $db->query("SELECT id, project_id, rating, created_at FROM project_ratings WHERE status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $r) {
        $tasks[] = [
            'key' => 'rating_moderation:' . $r['id'],
            'title' => 'Citizen Rating Awaiting Moderation',
            'description' => $r['rating'] . '-star review submitted, needs approve/reject/flag decision.',
            'project_id' => (int) $r['project_id'], 'project_name' => null,
            'module' => 'Citizen Ratings', 'priority' => taskCenterPriorityBucket(null, $r['created_at'], 3), 'due_date' => null,
            'created_date' => $r['created_at'], 'status' => 'pending', 'link_page' => 'citizen-ratings', 'link_params' => [],
        ];
    }

    // Document Checklist gaps — reuses includes/DocumentChecklist.php's live
    // engine directly rather than re-deriving any of its predicates. Only
    // projects past 'draft' (nothing to check yet) and not 'cancelled'
    // (no longer actionable) are considered.
    $stmt = $db->query("SELECT id, project_code, name, updated_at FROM projects WHERE status NOT IN ('draft', 'cancelled')");
    foreach ($stmt->fetchAll() as $p) {
        $missing = documentChecklistMissingRequired(documentChecklistForProject($db, (int) $p['id']));
        if (!$missing) {
            continue;
        }
        $tasks[] = [
            'key' => 'checklist_gap:' . $p['id'],
            'title' => 'Documentation Gap',
            'description' => count($missing) . ' required item(s) missing: ' . implode(', ', array_column($missing, 'label')),
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Document Checklist', 'priority' => taskCenterPriorityBucket(null, $p['updated_at'], 5), 'due_date' => null,
            'created_date' => $p['updated_at'], 'status' => 'pending', 'link_page' => 'project-registration', 'link_params' => ['project_id' => (int) $p['id']],
        ];
    }

    return taskCenterSort($tasks);
}

/** Shared with api/feedback.php's action=needs_attention — same weights, same shape. */
function taskCenterFeedbackNeedsAttention(PDO $db): array
{
    $categoryWeight = ['safety_hazard' => 15, 'road_damage' => 8, 'drainage_flooding' => 8, 'project_delay' => 6];
    $priorityWeight = ['urgent' => 40, 'high' => 25, 'medium' => 10, 'low' => 0];

    $rows = $db->query("
        SELECT f.id, f.message, f.category, f.priority, f.status, f.created_at,
               p.name AS project_name, DATEDIFF(NOW(), f.created_at) AS days_open
        FROM feedback f LEFT JOIN projects p ON p.id = f.project_id
        WHERE f.status IN ('open', 'in_progress')
    ")->fetchAll();

    foreach ($rows as &$row) {
        $daysOpen = max(0, (int) $row['days_open']);
        $row['score'] = ($priorityWeight[$row['priority']] ?? 0) + ($categoryWeight[$row['category']] ?? 0)
            + ($row['status'] === 'open' ? 5 : 0) + min($daysOpen, 14);
        $reasons = [];
        if (in_array($row['priority'], ['urgent', 'high'], true)) $reasons[] = ucfirst($row['priority']) . ' priority';
        if (isset($categoryWeight[$row['category']])) $reasons[] = ucfirst(str_replace('_', ' ', $row['category']));
        if ($daysOpen >= 3) $reasons[] = "open {$daysOpen}d";
        $row['reason'] = implode(' · ', $reasons) ?: 'Recently submitted';
    }
    unset($row);

    usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($rows, 0, 5);
}

// ── HOPE ──────────────────────────────────────────────────────────────────
function taskCenterForHope(PDO $db, int $userId): array
{
    $tasks = [];

    $stmt = $db->query("SELECT id, project_code, name, updated_at FROM projects WHERE status = 'endorsed' ORDER BY updated_at ASC");
    foreach ($stmt->fetchAll() as $p) {
        $tasks[] = [
            'key' => 'hope_project_approval:' . $p['id'],
            'title' => 'Project Awaiting Approval',
            'description' => $p['project_code'] . ' — ' . $p['name'] . ' endorsed and awaiting your decision.',
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Project Approval', 'priority' => taskCenterPriorityBucket(null, $p['updated_at']),
            'due_date' => null, 'created_date' => $p['updated_at'], 'status' => 'pending',
            'link_page' => 'project-approvals', 'link_params' => ['project_id' => (int) $p['id']],
        ];
    }

    $stmt = $db->query("
        SELECT r.id, r.project_id, r.award_amount, r.created_at, p.project_code, p.name AS project_name
        FROM bac_award_recommendations r INNER JOIN projects p ON p.id = r.project_id
        WHERE r.status = 'sent_to_admin' ORDER BY r.created_at ASC
    ");
    foreach ($stmt->fetchAll() as $r) {
        $tasks[] = [
            'key' => 'hope_award_approval:' . $r['id'],
            'title' => 'Contract Award Requires Decision',
            'description' => 'Award of ₱' . number_format((float) $r['award_amount'], 2) . ' recommended by BAC.',
            'project_id' => (int) $r['project_id'], 'project_name' => $r['project_code'] . ' — ' . $r['project_name'],
            'module' => 'Contract Award', 'priority' => taskCenterPriorityBucket(null, $r['created_at']),
            'due_date' => null, 'created_date' => $r['created_at'], 'status' => 'pending',
            'link_page' => 'award-approvals', 'link_params' => ['recommendation_id' => (int) $r['id']],
        ];
    }

    $stmt = $db->query("SELECT id, project_code, project_name, reason, created_at FROM project_deletion_requests WHERE status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $d) {
        $tasks[] = [
            'key' => 'hope_deletion_request:' . $d['id'],
            'title' => 'Project Deletion Request',
            'description' => $d['project_code'] . ' — ' . $d['project_name'] . ': ' . mb_strimwidth($d['reason'], 0, 100, '…'),
            'project_id' => null, 'project_name' => $d['project_code'] . ' — ' . $d['project_name'],
            'module' => 'Project Deletion', 'priority' => taskCenterPriorityBucket(null, $d['created_at']),
            'due_date' => null, 'created_date' => $d['created_at'], 'status' => 'pending',
            'link_page' => 'deletion-requests', 'link_params' => ['deletion_request_id' => (int) $d['id']],
        ];
    }

    $stmt = $db->query("SELECT id, project_code, project_name, reason, created_at FROM project_edit_requests WHERE status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $e) {
        $tasks[] = [
            'key' => 'hope_edit_request:' . $e['id'],
            'title' => 'Project Edit Request',
            'description' => $e['project_code'] . ' — ' . $e['project_name'] . ': ' . mb_strimwidth($e['reason'], 0, 100, '…'),
            'project_id' => null, 'project_name' => $e['project_code'] . ' — ' . $e['project_name'],
            'module' => 'Project Edit', 'priority' => taskCenterPriorityBucket(null, $e['created_at']),
            'due_date' => null, 'created_date' => $e['created_at'], 'status' => 'pending',
            'link_page' => 'edit-requests', 'link_params' => ['edit_request_id' => (int) $e['id']],
        ];
    }

    return taskCenterSort($tasks);
}

// ── BAC ───────────────────────────────────────────────────────────────────
function taskCenterForBac(PDO $db, int $userId): array
{
    $tasks = [];

    $stmt = $db->query("SELECT id, project_code, name, updated_at FROM projects WHERE status = 'approved' ORDER BY updated_at ASC");
    foreach ($stmt->fetchAll() as $p) {
        $tasks[] = [
            'key' => 'bac_publish_bid:' . $p['id'],
            'title' => 'Procurement Activity Requires Action',
            'description' => $p['project_code'] . ' — ' . $p['name'] . ' approved and ready for bid posting.',
            'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
            'module' => 'Procurement', 'priority' => taskCenterPriorityBucket(null, $p['updated_at']),
            'due_date' => null, 'created_date' => $p['updated_at'], 'status' => 'pending',
            'link_page' => 'bidding-announcements', 'link_params' => [],
        ];
    }

    $stmt = $db->query("
        SELECT a.id, a.project_id, a.deadline, a.created_at, p.project_code, p.name AS project_name
        FROM bac_bid_announcements a INNER JOIN projects p ON p.id = a.project_id
        WHERE a.status IN ('posted', 'pre_bid', 'open') AND a.deadline IS NOT NULL
    ");
    foreach ($stmt->fetchAll() as $a) {
        $tasks[] = [
            'key' => 'bac_bid_deadline:' . $a['id'],
            'title' => 'Bid Submission Period Ending',
            'description' => $a['project_code'] . ' — ' . $a['project_name'] . ' bid deadline: ' . $a['deadline'],
            'project_id' => (int) $a['project_id'], 'project_name' => $a['project_code'] . ' — ' . $a['project_name'],
            'module' => 'Procurement', 'priority' => taskCenterPriorityBucket($a['deadline'], $a['created_at']),
            'due_date' => $a['deadline'], 'created_date' => $a['created_at'], 'status' => taskCenterStatusLabel($a['deadline']),
            'link_page' => 'bidding-announcements', 'link_params' => [],
        ];
    }

    $stmt = $db->query("
        SELECT b.id, b.project_id, b.status, b.created_at, p.project_code, p.name AS project_name
        FROM bac_bid_submissions b INNER JOIN projects p ON p.id = b.project_id
        WHERE b.status IN ('submitted', 'for_review') ORDER BY b.created_at ASC
    ");
    foreach ($stmt->fetchAll() as $b) {
        $isForReview = $b['status'] === 'for_review';
        $tasks[] = [
            'key' => 'bac_bid_evaluation:' . $b['id'],
            'title' => $isForReview ? 'Award Recommendation Pending' : 'Bid Evaluation Pending',
            'description' => $b['project_code'] . ' — ' . $b['project_name'],
            'project_id' => (int) $b['project_id'], 'project_name' => $b['project_code'] . ' — ' . $b['project_name'],
            'module' => 'Procurement', 'priority' => taskCenterPriorityBucket(null, $b['created_at'], 3),
            'due_date' => null, 'created_date' => $b['created_at'], 'status' => $isForReview ? 'in_progress' : 'pending',
            'link_page' => $isForReview ? 'award-recommendation' : 'contractor-evaluation', 'link_params' => ['project_id' => (int) $b['project_id']],
        ];
    }

    $stmt = $db->query("
        SELECT r.id, r.project_id, r.status, r.updated_at, p.project_code, p.name AS project_name
        FROM bac_award_recommendations r INNER JOIN projects p ON p.id = r.project_id
        WHERE r.status IN ('returned', 'rejected') ORDER BY r.updated_at ASC
    ");
    foreach ($stmt->fetchAll() as $r) {
        $tasks[] = [
            'key' => 'bac_recommendation_action:' . $r['id'],
            'title' => 'Award Recommendation ' . ucfirst($r['status']) . ' by HOPE',
            'description' => $r['project_code'] . ' — ' . $r['project_name'] . ' needs a new candidate or resubmission.',
            'project_id' => (int) $r['project_id'], 'project_name' => $r['project_code'] . ' — ' . $r['project_name'],
            'module' => 'Procurement', 'priority' => 'urgent', 'due_date' => null,
            'created_date' => $r['updated_at'], 'status' => 'pending', 'link_page' => 'award-recommendation', 'link_params' => ['project_id' => (int) $r['project_id']],
        ];
    }

    $stmt = $db->query("SELECT id, owner_id, title, created_at FROM supporting_documents WHERE owner_type = 'bac_bid' AND status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $d) {
        $tasks[] = [
            'key' => 'bac_document_review:' . $d['id'],
            'title' => 'Procurement Document Requires Review',
            'description' => htmlspecialchars($d['title']),
            'project_id' => null, 'project_name' => null,
            'module' => 'Procurement Documents', 'priority' => taskCenterPriorityBucket(null, $d['created_at'], 3),
            'due_date' => null, 'created_date' => $d['created_at'], 'status' => 'pending',
            'link_page' => 'procurement-documents', 'link_params' => [],
        ];
    }

    $stmt = $db->query("SELECT id, name, created_at FROM contractors WHERE application_status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $c) {
        $tasks[] = [
            'key' => 'bac_contractor_application:' . $c['id'],
            'title' => 'Contractor Application Pending',
            'description' => htmlspecialchars($c['name']) . ' applied for accreditation.',
            'project_id' => null, 'project_name' => null,
            'module' => 'Contractor Accreditation', 'priority' => taskCenterPriorityBucket(null, $c['created_at']),
            'due_date' => null, 'created_date' => $c['created_at'], 'status' => 'pending',
            'link_page' => 'contractor-applications', 'link_params' => ['contractor_id' => (int) $c['id']],
        ];
    }

    return taskCenterSort($tasks);
}

// ── CONTRACTOR ────────────────────────────────────────────────────────────
function taskCenterForContractor(PDO $db, int $userId): array
{
    $tasks = [];
    $contractorId = contractorScopeCurrentId($db);
    if ($contractorId === null) {
        return [];
    }

    // Eligible to request payment — same rule contractorPortalPayments()
    // already computes (progress>=30, unpaid balance remains).
    $stmt = $db->prepare("
        SELECT p.id, p.project_code, p.name, p.progress, p.budget, p.updated_at,
               COALESCE((SELECT SUM(pr.requested_amount) FROM payment_requests pr WHERE pr.project_id = p.id AND pr.status IN ('approved','paid')), 0) AS paid,
               (SELECT COUNT(*) FROM payment_requests pr WHERE pr.project_id = p.id AND pr.status IN ('submitted','under_review')) AS in_flight
        FROM projects p
        WHERE p.contractor_id = ? AND p.status IN ('assigned','active','delayed','on_hold','completion_inspection','completed')
    ");
    $stmt->execute([$contractorId]);
    foreach ($stmt->fetchAll() as $p) {
        $balance = (float) $p['budget'] - (float) $p['paid'];
        if ($p['progress'] >= 30 && $balance > 0 && (int) $p['in_flight'] === 0) {
            $tasks[] = [
                'key' => 'contractor_payment_eligible:' . $p['id'],
                'title' => 'Eligible to Request Payment',
                'description' => $p['project_code'] . ' — ' . $p['name'] . ' has an unbilled balance of ₱' . number_format($balance, 2) . '.',
                'project_id' => (int) $p['id'], 'project_name' => $p['project_code'] . ' — ' . $p['name'],
                'module' => 'Payments', 'priority' => 'info', 'due_date' => null, 'created_date' => $p['updated_at'],
                'status' => 'pending', 'link_page' => 'payment-requests', 'link_params' => ['project_id' => (int) $p['id']],
            ];
        }
    }

    $stmt = $db->prepare("SELECT id, title, created_at FROM supporting_documents WHERE owner_type = 'contractor' AND owner_id = ? AND status = 'rejected' ORDER BY created_at DESC");
    $stmt->execute([$contractorId]);
    foreach ($stmt->fetchAll() as $d) {
        $tasks[] = [
            'key' => 'contractor_doc_rejected:' . $d['id'],
            'title' => 'Document Requires Correction',
            'description' => htmlspecialchars($d['title']) . ' was rejected and needs to be re-uploaded.',
            'project_id' => null, 'project_name' => null,
            'module' => 'Accreditation Documents', 'priority' => 'urgent', 'due_date' => null,
            'created_date' => $d['created_at'], 'status' => 'pending', 'link_page' => 'accreditation-documents', 'link_params' => [],
        ];
    }

    $stmt = $db->prepare("
        SELECT a.id, a.project_id, a.deadline, a.published_at, p.project_code, p.name AS project_name
        FROM bac_bid_announcements a
        INNER JOIN projects p ON p.id = a.project_id
        LEFT JOIN bac_bid_submissions b ON b.project_id = a.project_id AND b.contractor_id = ?
        WHERE p.status = 'bidding' AND a.status != 'closed' AND (a.deadline IS NULL OR a.deadline >= CURDATE()) AND b.id IS NULL
    ");
    $stmt->execute([$contractorId]);
    foreach ($stmt->fetchAll() as $a) {
        $tasks[] = [
            'key' => 'contractor_open_bid:' . $a['id'],
            'title' => 'Open Bidding Available',
            'description' => $a['project_code'] . ' — ' . $a['project_name'] . ($a['deadline'] ? ' (deadline ' . $a['deadline'] . ')' : ''),
            'project_id' => (int) $a['project_id'], 'project_name' => $a['project_code'] . ' — ' . $a['project_name'],
            'module' => 'Bidding', 'priority' => taskCenterPriorityBucket($a['deadline'], $a['published_at']),
            'due_date' => $a['deadline'], 'created_date' => $a['published_at'], 'status' => 'pending',
            'link_page' => 'open-biddings', 'link_params' => [],
        ];
    }

    // Milestone approaching — informational only; contractors cannot mark
    // milestones complete (engineer-only), so this is 🔵 INFO, not actionable.
    $stmt = $db->prepare("
        SELECT m.id, m.project_id, m.title, m.due_date, p.project_code, p.name AS project_name
        FROM milestones m INNER JOIN projects p ON p.id = m.project_id
        WHERE p.contractor_id = ? AND m.completed = 0 AND m.due_date >= CURDATE()
        ORDER BY m.due_date ASC LIMIT 5
    ");
    $stmt->execute([$contractorId]);
    foreach ($stmt->fetchAll() as $m) {
        $tasks[] = [
            'key' => 'contractor_milestone_info:' . $m['id'],
            'title' => 'Upcoming Milestone',
            'description' => htmlspecialchars($m['title']) . ' — due ' . $m['due_date'],
            'project_id' => (int) $m['project_id'], 'project_name' => $m['project_code'] . ' — ' . $m['project_name'],
            'module' => 'Milestones', 'priority' => 'info', 'due_date' => $m['due_date'], 'created_date' => date('Y-m-d H:i:s'),
            'status' => 'pending', 'link_page' => 'project-timeline', 'link_params' => ['project_id' => (int) $m['project_id']],
        ];
    }

    return taskCenterSort($tasks);
}

// ── SUPER ADMIN — platform governance ONLY, per explicit requirement ──────
function taskCenterForSuperAdmin(PDO $db, int $userId): array
{
    $tasks = [];

    $stmt = $db->query("SELECT id, requested_role, full_name, created_at FROM staff_account_requests WHERE status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $r) {
        $tasks[] = [
            'key' => 'staff_request:' . $r['id'],
            'title' => 'Staff Registration Request',
            'description' => htmlspecialchars($r['full_name']) . ' requested a ' . ucfirst($r['requested_role']) . ' account.',
            'project_id' => null, 'project_name' => null,
            'module' => 'User Governance', 'priority' => taskCenterPriorityBucket(null, $r['created_at']),
            'due_date' => null, 'created_date' => $r['created_at'], 'status' => 'pending',
            'link_page' => 'user-governance', 'link_params' => ['staff_request_id' => (int) $r['id']],
        ];
    }

    $stmt = $db->query("SELECT id, first_name, last_name, created_at FROM citizens WHERE verification_status = 'unverified' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $c) {
        $tasks[] = [
            'key' => 'citizen_verification:' . $c['id'],
            'title' => 'Citizen ID Verification Pending',
            'description' => htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) . ' — ID verification requires review.',
            'project_id' => null, 'project_name' => null,
            'module' => 'User Governance', 'priority' => taskCenterPriorityBucket(null, $c['created_at']),
            'due_date' => null, 'created_date' => $c['created_at'], 'status' => 'pending',
            'link_page' => 'user-governance', 'link_params' => ['citizen_id' => (int) $c['id']],
        ];
    }

    $stmt = $db->query("SELECT id, owner_type, title, created_at FROM supporting_documents WHERE status = 'pending' ORDER BY created_at ASC");
    foreach ($stmt->fetchAll() as $d) {
        $tasks[] = [
            'key' => 'sa_document_review:' . $d['id'],
            'title' => 'Document Requires Review',
            'description' => htmlspecialchars($d['title']) . ' (' . $d['owner_type'] . ')',
            'project_id' => null, 'project_name' => null,
            'module' => 'User Governance', 'priority' => taskCenterPriorityBucket(null, $d['created_at'], 5),
            'due_date' => null, 'created_date' => $d['created_at'], 'status' => 'pending',
            'link_page' => 'user-governance', 'link_params' => ['document_id' => (int) $d['id']],
        ];
    }

    $stmt = $db->prepare("
        SELECT identifier, ip_address, COUNT(*) AS failed_count, MAX(attempted_at) AS last_attempt
        FROM login_attempts WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
        GROUP BY identifier, ip_address HAVING failed_count >= ?
    ");
    $stmt->execute([(int) LOGIN_LOCKOUT_MINUTES, (int) LOGIN_MAX_ATTEMPTS]);
    foreach ($stmt->fetchAll() as $l) {
        $tasks[] = [
            'key' => 'security_lockout:' . md5($l['identifier'] . '|' . $l['ip_address']),
            'title' => 'Account Locked Out',
            'description' => htmlspecialchars($l['identifier']) . ' — ' . $l['failed_count'] . ' failed attempts.',
            'project_id' => null, 'project_name' => null,
            'module' => 'Login Security', 'priority' => 'urgent', 'due_date' => null,
            'created_date' => $l['last_attempt'], 'status' => 'pending', 'link_page' => 'login-security', 'link_params' => [],
        ];
    }

    return taskCenterSort($tasks);
}

function taskCenterForRole(PDO $db, string $role, int $userId): array
{
    switch ($role) {
        case 'engineer': return taskCenterForEngineer($db, $userId);
        case 'admin': return taskCenterForAdmin($db, $userId);
        case 'hope': return taskCenterForHope($db, $userId);
        case 'bac': return taskCenterForBac($db, $userId);
        case 'contractor': return taskCenterForContractor($db, $userId);
        case 'super_admin': return taskCenterForSuperAdmin($db, $userId);
        default: return [];
    }
}

/** Removes tasks this user has dismissed. */
function taskCenterFilterDismissed(PDO $db, int $userId, array $tasks): array
{
    $stmt = $db->prepare('SELECT task_key FROM task_center_dismissals WHERE user_id = ?');
    $stmt->execute([$userId]);
    $dismissed = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_filter($tasks, fn($t) => !isset($dismissed[$t['key']])));
}
