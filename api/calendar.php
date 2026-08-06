<?php
// ============================================================
// api/calendar.php — Calendar & Schedule (Admin)
//
// Aggregates read-only calendar events from several existing tables
// (milestones, inspections, payment_requests, projects, calendar_meetings,
// bac_bid_announcements, bac_award_recommendations) into one unified feed,
// plus lets Admin create/delete its own first-class 'meeting' events. This
// file implements a FROZEN API contract shared byte-for-byte with a parallel
// frontend build — the JSON shapes below (including 422/404/500 bodies)
// must not drift from it.
//
// Each event type is fetched with its own small prepared statement rather
// than one giant UNION query, on purpose: seven very different sources with
// different join shapes and flag rules stay far more readable apart than
// forced into a single SQL statement.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
calendarMeetingEnsureSchema($db);
projectWorkflowEnsureProjectStatusSchema($db);

// The 7 frozen event 'type' values — no others are ever emitted or accepted.
function calendarEventTypes(): array
{
    return ['milestone', 'inspection', 'payment', 'deadline', 'meeting', 'hope_review', 'bac_activity'];
}

function calendarValidateDate(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function calendarValidateTime(string $value): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
}

// submitted_at is a TIMESTAMP (has time-of-day), but "days pending" only
// ever needs to compare whole calendar days, so both ends are truncated to
// a date before diffing.
function calendarDaysPending(string $submittedAt): int
{
    try {
        $submitted = new DateTime(substr($submittedAt, 0, 10));
        $today = new DateTime(date('Y-m-d'));
        return (int) $today->diff($submitted)->days;
    } catch (Throwable $e) {
        return 0;
    }
}

// ── action=events — per-type fetchers ──────────────────────────────────

function calendarFetchMilestoneEvents(PDO $db, string $start, string $end): array
{
    $stmt = $db->prepare("
        SELECT m.id, m.title, m.due_date, m.completed, p.id AS project_id, p.name AS project_name
        FROM milestones m
        JOIN projects p ON p.id = m.project_id
        WHERE m.due_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);

    $today = date('Y-m-d');
    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $completed = (bool) $row['completed'];
        $dueDate = (string) $row['due_date'];
        $events[] = [
            'id' => 'milestone-' . $row['id'],
            'type' => 'milestone',
            'title' => (string) $row['title'],
            'date' => $dueDate,
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => $completed ? 'Completed' : 'Pending',
            'is_flagged' => !$completed && $dueDate < $today,
        ];
    }
    return $events;
}

function calendarFetchInspectionEvents(PDO $db, string $start, string $end): array
{
    $stmt = $db->prepare("
        SELECT i.id, i.inspection_date, i.recommendation, p.id AS project_id, p.name AS project_name
        FROM inspections i
        JOIN projects p ON p.id = i.project_id
        WHERE i.inspection_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $recommendation = (string) $row['recommendation'];
        $events[] = [
            'id' => 'inspection-' . $row['id'],
            'type' => 'inspection',
            'title' => 'Inspection: ' . $row['project_name'],
            'date' => (string) $row['inspection_date'],
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => ucfirst(str_replace('_', ' ', $recommendation)),
            'is_flagged' => $recommendation !== 'approved',
        ];
    }
    return $events;
}

// payment_requests has no true due-date column in this schema — submitted_at
// is deliberately used as the anchor date, and requests pending too long are
// flagged instead (computed in PHP after fetching).
function calendarFetchPaymentEvents(PDO $db, string $start, string $end): array
{
    $stmt = $db->prepare("
        SELECT pr.id, pr.submitted_at, pr.status, pr.billing_no, p.id AS project_id, p.name AS project_name
        FROM payment_requests pr
        JOIN projects p ON p.id = pr.project_id
        WHERE DATE(pr.submitted_at) BETWEEN ? AND ?
          AND pr.status IN ('submitted', 'under_review')
    ");
    $stmt->execute([$start, $end]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $submittedAt = (string) $row['submitted_at'];
        $events[] = [
            'id' => 'payment-' . $row['id'],
            'type' => 'payment',
            'title' => 'Payment Request: ' . $row['billing_no'],
            'date' => substr($submittedAt, 0, 10),
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => ucfirst((string) $row['status']),
            'is_flagged' => calendarDaysPending($submittedAt) > 15,
        ];
    }
    return $events;
}

function calendarFetchDeadlineEvents(PDO $db, string $start, string $end): array
{
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.end_date, p.status, p.progress
        FROM projects p
        WHERE p.end_date BETWEEN ? AND ?
          AND p.status NOT IN ('completed', 'turnover', 'cancelled')
    ");
    $stmt->execute([$start, $end]);

    $today = date('Y-m-d');
    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $endDate = (string) $row['end_date'];
        $events[] = [
            'id' => 'deadline-' . $row['id'],
            'type' => 'deadline',
            'title' => $row['name'] . ' deadline',
            'date' => $endDate,
            'time' => null,
            'project_id' => (int) $row['id'],
            'project_name' => $row['name'],
            'status_label' => ucfirst((string) $row['status']),
            'is_flagged' => $endDate < $today,
        ];
    }
    return $events;
}

function calendarFetchMeetingEvents(PDO $db, string $start, string $end): array
{
    $stmt = $db->prepare("
        SELECT cm.*, p.name AS project_name
        FROM calendar_meetings cm
        LEFT JOIN projects p ON p.id = cm.project_id
        WHERE cm.meeting_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);

    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'id' => 'meeting-' . $row['id'],
            'type' => 'meeting',
            'title' => (string) $row['title'],
            'date' => (string) $row['meeting_date'],
            'time' => $row['meeting_time'] !== null ? substr((string) $row['meeting_time'], 0, 5) : null,
            'project_id' => $row['project_id'] !== null ? (int) $row['project_id'] : null,
            'project_name' => $row['project_name'],
            'status_label' => 'Scheduled',
            'is_flagged' => false,
        ];
    }
    return $events;
}

// Two unioned sub-sources, kept in different id prefixes ('proj'/'award') so
// they never collide, since both ever only ever occupy the same 'hope_review' type.
function calendarFetchHopeReviewEvents(PDO $db, string $start, string $end): array
{
    $events = [];
    $startDateTime = $start . ' 00:00:00';
    $endDateTime = $end . ' 23:59:59';

    // (a) HOPE's project-level approve/return/cancel decision.
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.status, p.approved_at
        FROM projects p
        WHERE p.approved_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDateTime, $endDateTime]);
    foreach ($stmt->fetchAll() as $row) {
        $approvedAt = (string) $row['approved_at'];
        $status = (string) $row['status'];
        $events[] = [
            'id' => 'hope_review-proj' . $row['id'],
            'type' => 'hope_review',
            'title' => 'HOPE Decision: ' . $row['name'],
            'date' => substr($approvedAt, 0, 10),
            'time' => substr($approvedAt, 11, 5),
            'project_id' => (int) $row['id'],
            'project_name' => $row['name'],
            'status_label' => ucfirst($status),
            'is_flagged' => in_array($status, ['returned', 'cancelled'], true),
        ];
    }

    // (b) HOPE's award-level approve/return/reject decision.
    $stmt = $db->prepare("
        SELECT bar.id, bar.status, bar.decided_at, p.id AS project_id, p.name AS project_name
        FROM bac_award_recommendations bar
        JOIN projects p ON p.id = bar.project_id
        WHERE bar.decided_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDateTime, $endDateTime]);
    foreach ($stmt->fetchAll() as $row) {
        $decidedAt = (string) $row['decided_at'];
        $status = (string) $row['status'];
        $events[] = [
            'id' => 'hope_review-award' . $row['id'],
            'type' => 'hope_review',
            'title' => 'Award Decision: ' . $row['project_name'],
            'date' => substr($decidedAt, 0, 10),
            'time' => substr($decidedAt, 11, 5),
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => ucfirst($status),
            'is_flagged' => in_array($status, ['returned', 'rejected'], true),
        ];
    }

    return $events;
}

// Three unioned sub-sources, id-prefixed 'deadline'/'published'/'award' so
// none collide, since all three ever only occupy the same 'bac_activity' type.
function calendarFetchBacActivityEvents(PDO $db, string $start, string $end): array
{
    $events = [];
    $today = date('Y-m-d');

    // (a) Bid submission deadline.
    $stmt = $db->prepare("
        SELECT b.id, b.project_id, b.reference_no, b.deadline, b.status, p.name AS project_name
        FROM bac_bid_announcements b
        JOIN projects p ON p.id = b.project_id
        WHERE b.deadline BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $deadline = (string) $row['deadline'];
        $events[] = [
            'id' => 'bac_activity-deadline' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Bid Deadline: ' . $row['reference_no'],
            'date' => $deadline,
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Bid Deadline',
            'is_flagged' => $deadline < $today && $row['status'] === 'open',
        ];
    }

    // (b) Bid announcement published.
    $stmt = $db->prepare("
        SELECT b.id, b.project_id, b.reference_no, b.published_at, p.name AS project_name
        FROM bac_bid_announcements b
        JOIN projects p ON p.id = b.project_id
        WHERE b.published_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'id' => 'bac_activity-published' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Bid Announcement Published: ' . $row['reference_no'],
            'date' => (string) $row['published_at'],
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Published',
            'is_flagged' => false,
        ];
    }

    // (c) Award recommendation sent to HOPE.
    $stmt = $db->prepare("
        SELECT bar.id, bar.project_id, bar.created_at, p.name AS project_name
        FROM bac_award_recommendations bar
        JOIN projects p ON p.id = bar.project_id
        WHERE DATE(bar.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $createdAt = (string) $row['created_at'];
        $events[] = [
            'id' => 'bac_activity-award' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Award Recommended: ' . $row['project_name'],
            'date' => substr($createdAt, 0, 10),
            'time' => substr($createdAt, 11, 5),
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Sent to HOPE',
            'is_flagged' => false,
        ];
    }

    return $events;
}

function calendarHandleEvents(PDO $db): void
{
    $start = (string) ($_GET['start'] ?? '');
    $end = (string) ($_GET['end'] ?? '');

    if (!calendarValidateDate($start) || !calendarValidateDate($end)) {
        respond(['success' => false, 'message' => 'A valid start and end date (YYYY-MM-DD) are required.'], 422);
    }

    $events = [];
    array_push($events, ...calendarFetchMilestoneEvents($db, $start, $end));
    array_push($events, ...calendarFetchInspectionEvents($db, $start, $end));
    array_push($events, ...calendarFetchPaymentEvents($db, $start, $end));
    array_push($events, ...calendarFetchDeadlineEvents($db, $start, $end));
    array_push($events, ...calendarFetchMeetingEvents($db, $start, $end));
    array_push($events, ...calendarFetchHopeReviewEvents($db, $start, $end));
    array_push($events, ...calendarFetchBacActivityEvents($db, $start, $end));

    usort($events, function (array $a, array $b): int {
        $aKey = $a['date'] . ' ' . ($a['time'] ?? '00:00');
        $bKey = $b['date'] . ' ' . ($b['time'] ?? '00:00');
        return $aKey <=> $bKey;
    });

    respond(['success' => true, 'events' => $events]);
}

// ── action=event_detail — per-type single-row detail fetchers ─────────────
// Each re-derives the numeric source row id from the id param (stripping the
// same prefix convention the list fetchers above used to build it), then
// queries that one row fully, including the type's extra contract fields.

function calendarDetailMilestone(PDO $db, string $idParam): ?array
{
    $id = (int) $idParam;
    $stmt = $db->prepare("
        SELECT m.id, m.title, m.due_date, m.completed, p.id AS project_id, p.name AS project_name
        FROM milestones m
        JOIN projects p ON p.id = m.project_id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $completed = (bool) $row['completed'];
    $dueDate = (string) $row['due_date'];
    return [
        'id' => 'milestone-' . $row['id'],
        'type' => 'milestone',
        'title' => (string) $row['title'],
        'date' => $dueDate,
        'time' => null,
        'project_id' => (int) $row['project_id'],
        'project_name' => $row['project_name'],
        'status_label' => $completed ? 'Completed' : 'Pending',
        'is_flagged' => !$completed && $dueDate < date('Y-m-d'),
        'completed' => $completed,
        'due_date' => $dueDate,
    ];
}

function calendarDetailInspection(PDO $db, string $idParam): ?array
{
    $id = (int) $idParam;
    $stmt = $db->prepare("
        SELECT i.id, i.inspection_date, i.recommendation, i.findings, i.actual_progress_percent,
               p.id AS project_id, p.name AS project_name, u.full_name AS engineer_name
        FROM inspections i
        JOIN projects p ON p.id = i.project_id
        JOIN users u ON u.id = i.engineer_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $recommendation = (string) $row['recommendation'];
    return [
        'id' => 'inspection-' . $row['id'],
        'type' => 'inspection',
        'title' => 'Inspection: ' . $row['project_name'],
        'date' => (string) $row['inspection_date'],
        'time' => null,
        'project_id' => (int) $row['project_id'],
        'project_name' => $row['project_name'],
        'status_label' => ucfirst(str_replace('_', ' ', $recommendation)),
        'is_flagged' => $recommendation !== 'approved',
        'engineer_name' => $row['engineer_name'],
        'recommendation' => $recommendation,
        'findings' => $row['findings'],
        'actual_progress_percent' => (int) $row['actual_progress_percent'],
    ];
}

function calendarDetailPayment(PDO $db, string $idParam): ?array
{
    $id = (int) $idParam;
    $stmt = $db->prepare("
        SELECT pr.id, pr.submitted_at, pr.status, pr.billing_no, pr.requested_amount,
               p.id AS project_id, p.name AS project_name
        FROM payment_requests pr
        JOIN projects p ON p.id = pr.project_id
        WHERE pr.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $submittedAt = (string) $row['submitted_at'];
    $daysPending = calendarDaysPending($submittedAt);

    return [
        'id' => 'payment-' . $row['id'],
        'type' => 'payment',
        'title' => 'Payment Request: ' . $row['billing_no'],
        'date' => substr($submittedAt, 0, 10),
        'time' => null,
        'project_id' => (int) $row['project_id'],
        'project_name' => $row['project_name'],
        'status_label' => ucfirst((string) $row['status']),
        'is_flagged' => $daysPending > 15,
        'billing_no' => (string) $row['billing_no'],
        'requested_amount' => (float) $row['requested_amount'],
        'status' => (string) $row['status'],
        'submitted_at' => $submittedAt,
        'days_pending' => $daysPending,
    ];
}

function calendarDetailDeadline(PDO $db, string $idParam): ?array
{
    $id = (int) $idParam;
    $stmt = $db->prepare("
        SELECT id, name, start_date, end_date, status, progress
        FROM projects
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $endDate = (string) $row['end_date'];
    return [
        'id' => 'deadline-' . $row['id'],
        'type' => 'deadline',
        'title' => $row['name'] . ' deadline',
        'date' => $endDate,
        'time' => null,
        'project_id' => (int) $row['id'],
        'project_name' => $row['name'],
        'status_label' => ucfirst((string) $row['status']),
        'is_flagged' => $endDate < date('Y-m-d'),
        'start_date' => (string) $row['start_date'],
        'end_date' => $endDate,
        'progress' => (int) $row['progress'],
        'status' => (string) $row['status'],
    ];
}

function calendarDetailMeeting(PDO $db, string $idParam): ?array
{
    $id = (int) $idParam;
    $stmt = $db->prepare("
        SELECT cm.*, p.name AS project_name, u.full_name AS created_by_name
        FROM calendar_meetings cm
        LEFT JOIN projects p ON p.id = cm.project_id
        LEFT JOIN users u ON u.id = cm.created_by
        WHERE cm.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $time = $row['meeting_time'] !== null ? substr((string) $row['meeting_time'], 0, 5) : null;

    return [
        'id' => 'meeting-' . $row['id'],
        'type' => 'meeting',
        'title' => (string) $row['title'],
        'date' => (string) $row['meeting_date'],
        'time' => $time,
        'project_id' => $row['project_id'] !== null ? (int) $row['project_id'] : null,
        'project_name' => $row['project_name'],
        'status_label' => 'Scheduled',
        'is_flagged' => false,
        'description' => $row['description'],
        'meeting_date' => (string) $row['meeting_date'],
        'meeting_time' => $time,
        'location' => $row['location'],
        'created_by_name' => $row['created_by_name'],
    ];
}

// idParam carries its sub-source prefix baked in ('proj123' / 'award123'),
// the same way the list events' id field does after its 'hope_review-' type prefix.
function calendarDetailHopeReview(PDO $db, string $idParam): ?array
{
    if (str_starts_with($idParam, 'proj')) {
        $id = (int) substr($idParam, 4);
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.status, p.approved_at, p.rejection_reason, u.full_name AS decided_by_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.approved_by
            WHERE p.id = ? AND p.approved_at IS NOT NULL
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $approvedAt = (string) $row['approved_at'];
        $status = (string) $row['status'];
        return [
            'id' => 'hope_review-proj' . $row['id'],
            'type' => 'hope_review',
            'title' => 'HOPE Decision: ' . $row['name'],
            'date' => substr($approvedAt, 0, 10),
            'time' => substr($approvedAt, 11, 5),
            'project_id' => (int) $row['id'],
            'project_name' => $row['name'],
            'status_label' => ucfirst($status),
            'is_flagged' => in_array($status, ['returned', 'cancelled'], true),
            'subtype' => 'project_decision',
            'decision_status' => $status,
            'remarks' => $row['rejection_reason'],
            'decided_by_name' => $row['decided_by_name'],
        ];
    }

    if (str_starts_with($idParam, 'award')) {
        $id = (int) substr($idParam, 5);
        $stmt = $db->prepare("
            SELECT bar.id, bar.status, bar.decided_at, bar.hope_remarks,
                   p.id AS project_id, p.name AS project_name, u.full_name AS decided_by_name
            FROM bac_award_recommendations bar
            JOIN projects p ON p.id = bar.project_id
            LEFT JOIN users u ON u.id = bar.decided_by
            WHERE bar.id = ? AND bar.decided_at IS NOT NULL
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $decidedAt = (string) $row['decided_at'];
        $status = (string) $row['status'];
        return [
            'id' => 'hope_review-award' . $row['id'],
            'type' => 'hope_review',
            'title' => 'Award Decision: ' . $row['project_name'],
            'date' => substr($decidedAt, 0, 10),
            'time' => substr($decidedAt, 11, 5),
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => ucfirst($status),
            'is_flagged' => in_array($status, ['returned', 'rejected'], true),
            'subtype' => 'award_decision',
            'decision_status' => $status,
            'remarks' => $row['hope_remarks'],
            'decided_by_name' => $row['decided_by_name'],
        ];
    }

    return null;
}

// idParam carries its sub-source prefix baked in ('deadline123' / 'published123' / 'award123').
function calendarDetailBacActivity(PDO $db, string $idParam): ?array
{
    if (str_starts_with($idParam, 'deadline')) {
        $id = (int) substr($idParam, 8);
        $stmt = $db->prepare("
            SELECT b.id, b.project_id, b.reference_no, b.deadline, b.status, b.notes, p.name AS project_name
            FROM bac_bid_announcements b
            JOIN projects p ON p.id = b.project_id
            WHERE b.id = ? AND b.deadline IS NOT NULL
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $deadline = (string) $row['deadline'];
        return [
            'id' => 'bac_activity-deadline' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Bid Deadline: ' . $row['reference_no'],
            'date' => $deadline,
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Bid Deadline',
            'is_flagged' => $deadline < date('Y-m-d') && $row['status'] === 'open',
            'subtype' => 'bid_deadline',
            'reference_no' => (string) $row['reference_no'],
            'extra_notes' => $row['notes'],
        ];
    }

    if (str_starts_with($idParam, 'published')) {
        $id = (int) substr($idParam, 9);
        $stmt = $db->prepare("
            SELECT b.id, b.project_id, b.reference_no, b.published_at, b.notes, p.name AS project_name
            FROM bac_bid_announcements b
            JOIN projects p ON p.id = b.project_id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id' => 'bac_activity-published' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Bid Announcement Published: ' . $row['reference_no'],
            'date' => (string) $row['published_at'],
            'time' => null,
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Published',
            'is_flagged' => false,
            'subtype' => 'bid_published',
            'reference_no' => (string) $row['reference_no'],
            'extra_notes' => $row['notes'],
        ];
    }

    if (str_starts_with($idParam, 'award')) {
        $id = (int) substr($idParam, 5);
        $stmt = $db->prepare("
            SELECT bar.id, bar.project_id, bar.created_at, bar.basis, p.name AS project_name
            FROM bac_award_recommendations bar
            JOIN projects p ON p.id = bar.project_id
            WHERE bar.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $createdAt = (string) $row['created_at'];
        return [
            'id' => 'bac_activity-award' . $row['id'],
            'type' => 'bac_activity',
            'title' => 'Award Recommended: ' . $row['project_name'],
            'date' => substr($createdAt, 0, 10),
            'time' => substr($createdAt, 11, 5),
            'project_id' => (int) $row['project_id'],
            'project_name' => $row['project_name'],
            'status_label' => 'Sent to HOPE',
            'is_flagged' => false,
            'subtype' => 'award_recommended',
            'reference_no' => null,
            'extra_notes' => $row['basis'],
        ];
    }

    return null;
}

function calendarHandleEventDetail(PDO $db): void
{
    $type = (string) ($_GET['type'] ?? '');
    $idParam = (string) ($_GET['id'] ?? '');

    if (!in_array($type, calendarEventTypes(), true) || $idParam === '') {
        respond(['success' => false, 'message' => 'A valid type and id are required.'], 422);
    }

    $event = match ($type) {
        'milestone' => calendarDetailMilestone($db, $idParam),
        'inspection' => calendarDetailInspection($db, $idParam),
        'payment' => calendarDetailPayment($db, $idParam),
        'deadline' => calendarDetailDeadline($db, $idParam),
        'meeting' => calendarDetailMeeting($db, $idParam),
        'hope_review' => calendarDetailHopeReview($db, $idParam),
        'bac_activity' => calendarDetailBacActivity($db, $idParam),
        default => null,
    };

    if ($event === null) {
        respond(['success' => false, 'message' => 'Event not found'], 404);
    }

    respond(['success' => true, 'event' => $event]);
}

// ── action=create_meeting / delete_meeting ─────────────────────────────

function calendarHandleCreateMeeting(PDO $db, ?array $user): void
{
    $body = requestBody();

    $title = trim((string) ($body['title'] ?? ''));
    $meetingDate = trim((string) ($body['meeting_date'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $meetingTime = trim((string) ($body['meeting_time'] ?? ''));
    $location = trim((string) ($body['location'] ?? ''));
    $projectIdRaw = $body['project_id'] ?? null;

    $errors = [];
    if ($title === '') {
        $errors['title'] = 'Title is required.';
    }
    if ($meetingDate === '' || !calendarValidateDate($meetingDate)) {
        $errors['meeting_date'] = 'A valid meeting date (YYYY-MM-DD) is required.';
    }
    if ($meetingTime !== '' && !calendarValidateTime($meetingTime)) {
        $errors['meeting_time'] = 'Meeting time must be in HH:MM format.';
    }

    $projectId = null;
    if ($projectIdRaw !== null && $projectIdRaw !== '') {
        if (!is_numeric($projectIdRaw)) {
            $errors['project_id'] = 'Invalid project.';
        } else {
            $projectId = (int) $projectIdRaw;
            $check = $db->prepare("SELECT id FROM projects WHERE id = ?");
            $check->execute([$projectId]);
            if (!$check->fetchColumn()) {
                $errors['project_id'] = 'Project not found.';
            }
        }
    }

    if ($errors !== []) {
        respond(['success' => false, 'message' => 'Please correct the errors below.', 'errors' => $errors], 422);
    }

    $createdBy = (int) ($user['user_id'] ?? 0) ?: null;

    $stmt = $db->prepare("
        INSERT INTO calendar_meetings (title, description, meeting_date, meeting_time, location, project_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title,
        $description !== '' ? $description : null,
        $meetingDate,
        $meetingTime !== '' ? $meetingTime : null,
        $location !== '' ? $location : null,
        $projectId,
        $createdBy,
    ]);

    $newId = (int) $db->lastInsertId();

    logActivity($createdBy, 'calendar_meeting_created', $title . ' scheduled for ' . $meetingDate . '.', 'Calendar', $newId);

    respond(['success' => true, 'id' => $newId], 201);
}

function calendarHandleDeleteMeeting(PDO $db, ?array $user): void
{
    $body = requestBody();
    $id = (int) ($body['id'] ?? 0);

    if ($id <= 0) {
        respond(['success' => false, 'message' => 'A valid meeting id is required.'], 422);
    }

    $existing = $db->prepare("SELECT title FROM calendar_meetings WHERE id = ?");
    $existing->execute([$id]);
    $title = $existing->fetchColumn();
    if ($title === false) {
        respond(['success' => false, 'message' => 'Meeting not found'], 404);
    }

    $db->prepare("DELETE FROM calendar_meetings WHERE id = ?")->execute([$id]);

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'calendar_meeting_deleted', $title . ' was deleted.', 'Calendar', $id);

    respond(['success' => true]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
// Wrapped in try/catch so any unexpected failure (a missing table on a
// not-yet-migrated install, a DB error, etc.) still comes back as the
// contract's JSON error shape instead of a raw PHP warning/error body.
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');
    $user = currentUser();

    if ($method === 'GET' && $action === 'events') {
        calendarHandleEvents($db);
    } elseif ($method === 'GET' && $action === 'event_detail') {
        calendarHandleEventDetail($db);
    } elseif ($method === 'POST' && $action === 'create_meeting') {
        calendarHandleCreateMeeting($db, $user);
    } elseif ($method === 'POST' && $action === 'delete_meeting') {
        calendarHandleDeleteMeeting($db, $user);
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
