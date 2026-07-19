<?php
// ============================================================
// api/sidebar-badges.php — live sidebar badge counts, shared across all
// 7 portals (super_admin/admin/bac/engineer/contractor/hope/citizen).
//
// The client always tells us which portal's sidebar it's asking about
// via ?portal= (a role like super_admin can view more than one portal,
// so the session role alone isn't enough to know which sidebar is on
// screen). Every count below is a real, current query against actual
// table state — no fabricated numbers, and every module's comment says
// exactly what it means.
//
// Most badges count "new since this user last opened that page" —
// backed by sidebar_badge_views(user_id, badge_key, last_viewed_at),
// updated via action=mark_viewed when a sidebar item is clicked. A
// handful of modules have no usable event timestamp anywhere in the
// schema (see per-module comments) and stay as live backlog counts
// instead — clicking into them refreshes the number but doesn't zero
// it, because the underlying work genuinely hasn't gone away.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'hope', 'citizen']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);
projectDeletionEnsureSchema($db);
sidebarBadgesEnsureViewsTable($db);

$user = currentUser();
$userId = (int) ($user['user_id'] ?? $_SESSION['user_id'] ?? 0);
$role = (string) ($user['role'] ?? '');

const EPOCH = '1970-01-01 00:00:00';
const VALID_PORTALS = ['admin', 'superadmin', 'bac', 'engineer', 'contractor', 'hope', 'citizen'];

function sidebarBadgesEnsureViewsTable(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS sidebar_badge_views (
            user_id INT NOT NULL,
            badge_key VARCHAR(60) NOT NULL,
            last_viewed_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, badge_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function sidebarBadgesLastViewed(PDO $db, int $userId): array
{
    $stmt = $db->prepare("SELECT badge_key, last_viewed_at FROM sidebar_badge_views WHERE user_id = ?");
    $stmt->execute([$userId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['badge_key']] = $row['last_viewed_at'];
    }
    return $map;
}

function lv(array $map, string $key): string
{
    return $map[$key] ?? EPOCH;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    if ($action !== 'mark_viewed') {
        respond(['error' => 'Unknown action.'], 404);
    }

    $body = requestBody();
    $badgeKey = (string) ($body['badge_key'] ?? '');
    if ($badgeKey === '' || !preg_match('/^[a-z0-9-]{1,60}$/', $badgeKey)) {
        respond(['error' => 'Invalid badge key.'], 422);
    }

    $stmt = $db->prepare("
        INSERT INTO sidebar_badge_views (user_id, badge_key, last_viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_viewed_at = NOW()
    ");
    $stmt->execute([$userId, $badgeKey]);

    respond(['success' => true]);
}

if ($method !== 'GET') {
    respond(['error' => 'Method not allowed.'], 405);
}

$portal = (string) ($_GET['portal'] ?? '');
if (!in_array($portal, VALID_PORTALS, true)) {
    respond(['error' => 'Unknown or missing portal.'], 422);
}

// Each portal is gated to the role(s) that actually use it — mirrors the
// role checks already enforced on that portal's own api/portal.php.
$portalRoleGates = [
    'admin'       => ['super_admin', 'admin'],
    'superadmin'  => ['super_admin'],
    'bac'         => ['super_admin', 'admin', 'bac'],
    'engineer'    => ['engineer'],
    'contractor'  => ['contractor'],
    'hope'        => ['hope', 'super_admin'],
    'citizen'     => ['citizen'],
];
if (!in_array($role, $portalRoleGates[$portal], true)) {
    respond(['error' => 'You do not have access to this portal.'], 403);
}

$lastViewed = sidebarBadgesLastViewed($db, $userId);
$badges = [];

switch ($portal) {
    case 'admin':
        $badges = computeAdminBadges($db, $userId, $lastViewed);
        break;
    case 'superadmin':
        $badges = computeSuperAdminBadges($db, $lastViewed);
        break;
    case 'bac':
        $badges = computeBacBadges($db, $lastViewed);
        break;
    case 'engineer':
        $badges = computeEngineerBadges($db, $userId, $lastViewed);
        break;
    case 'contractor':
        $badges = computeContractorBadges($db, $userId, $lastViewed);
        break;
    case 'hope':
        $badges = computeHopeBadges($db, $userId, $lastViewed);
        break;
    case 'citizen':
        $badges = computeCitizenBadges($db, $userId);
        break;
}

respond(['badges' => $badges]);

// ============================================================
// ADMIN
// ============================================================
function computeAdminBadges(PDO $db, int $userId, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status IN ('draft','returned') AND GREATEST(created_at, updated_at) > ?");
    $stmt->execute([lv($lv, 'project-registration')]);
    $b['project-registration'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status IN ('endorsed','returned') AND GREATEST(created_at, updated_at) > ?");
    $stmt->execute([lv($lv, 'project-approval')]);
    $b['project-approval'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'awarded' AND contractor_id IS NULL AND updated_at > ?");
    $stmt->execute([lv($lv, 'contractor-assignment')]);
    $b['contractor-assignment'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM payment_requests WHERE status IN ('submitted','under_review') AND submitted_at > ?");
    $stmt->execute([lv($lv, 'workflow-management')]);
    $newPayments = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'completion_inspection' AND updated_at > ?");
    $stmt->execute([lv($lv, 'workflow-management')]);
    $b['workflow-management'] = ['type' => 'red', 'count' => $newPayments + (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM expenses WHERE flagged = 1 AND created_at > ?");
    $stmt->execute([lv($lv, 'budget-monitoring')]);
    $b['budget-monitoring'] = ['type' => 'orange', 'count' => (int) $stmt->fetchColumn()];

    $overdueMilestones = (int) $db->query("SELECT COUNT(*) FROM milestones WHERE completed = 0 AND due_date < CURDATE()")->fetchColumn();
    $delayedProjects = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'delayed'")->fetchColumn();
    $b['milestone-overview'] = ['type' => 'orange', 'count' => $overdueMilestones + $delayedProjects];

    $b['ai-risk-insights'] = [
        'type' => $delayedProjects >= 1 ? 'red' : null,
        'label' => 'AI',
        'count' => $delayedProjects >= 1 ? $delayedProjects : 0,
    ];

    $stmt = $db->prepare("SELECT COUNT(*) FROM feedback WHERE status = 'open' AND created_at > ?");
    $stmt->execute([lv($lv, 'citizen-feedback')]);
    $urgentOpenFeedback = (int) $db->query("SELECT COUNT(*) FROM feedback WHERE priority = 'urgent' AND status IN ('open','in_progress')")->fetchColumn();
    $b['citizen-feedback'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn(), 'urgent' => $urgentOpenFeedback > 0];

    $stmt = $db->prepare("SELECT COUNT(*) FROM staff_account_requests WHERE status = 'pending' AND requested_by = ? AND created_at > ?");
    $stmt->execute([$userId, lv($lv, 'staff-requests')]);
    $b['staff-requests'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'turnover' AND turnover_at > ?");
    $stmt->execute([lv($lv, 'completed-projects')]);
    $b['completed-projects'] = ['label' => $stmt->fetchColumn() > 0 ? 'NEW' : null];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'cancelled' AND updated_at > ?");
    $stmt->execute([lv($lv, 'cancelled-projects')]);
    $b['cancelled-projects'] = ['label' => $stmt->fetchColumn() > 0 ? 'UPDATED' : null];

    $b['dashboard'] = ['type' => 'red', 'count' =>
        $b['project-registration']['count'] + $b['project-approval']['count'] + $b['contractor-assignment']['count']
        + $b['workflow-management']['count'] + $b['budget-monitoring']['count'] + $b['milestone-overview']['count']
        + $b['citizen-feedback']['count'] + $b['staff-requests']['count']];

    return $b;
}

// ============================================================
// SUPER ADMIN
// ============================================================
function computeSuperAdminBadges(PDO $db, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM staff_account_requests WHERE status = 'pending' AND created_at > ?");
    $stmt->execute([lv($lv, 'user-governance')]);
    $b['user-governance'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE created_at > ?");
    $stmt->execute([lv($lv, 'audit-trail')]);
    $b['audit-trail'] = ['type' => 'blue', 'count' => (int) $stmt->fetchColumn()];

    // login_attempts is a rolling ~24h window (pruned by pruneOldLoginAttempts()),
    // so this only ever reflects genuinely recent failed attempts.
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND attempted_at > ?");
    $stmt->execute([lv($lv, 'login-security')]);
    $b['login-security'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $b['dashboard'] = ['type' => 'red', 'count' => $b['user-governance']['count'] + $b['login-security']['count']];

    return $b;
}

// ============================================================
// BAC
// ============================================================
function computeBacBadges(PDO $db, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_bid_submissions WHERE status = 'submitted' AND created_at > ?");
    $stmt->execute([lv($lv, 'contractor-evaluation')]);
    $b['contractor-evaluation'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_bid_submissions WHERE status = 'for_review' AND updated_at > ?");
    $stmt->execute([lv($lv, 'bid-comparison')]);
    $forReviewCount = (int) $stmt->fetchColumn();
    $b['bid-comparison'] = ['type' => 'orange', 'count' => $forReviewCount];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_bid_submissions WHERE status = 'for_review' AND updated_at > ?");
    $stmt->execute([lv($lv, 'award-recommendation')]);
    $b['award-recommendation'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM supporting_documents WHERE owner_type = 'bac_bid' AND status = 'pending' AND created_at > ?");
    $stmt->execute([lv($lv, 'procurement-documents')]);
    $b['procurement-documents'] = ['type' => 'orange', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM contractors WHERE application_status = 'pending' AND created_at > ?");
    $stmt->execute([lv($lv, 'contractor-applications')]);
    $b['contractor-applications'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $b['dashboard'] = ['type' => 'red', 'count' =>
        $b['contractor-evaluation']['count'] + $b['award-recommendation']['count']
        + $b['procurement-documents']['count'] + $b['contractor-applications']['count']];

    return $b;
}

// ============================================================
// ENGINEER
// ============================================================
function computeEngineerBadges(PDO $db, int $userId, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM engineer_project_assignments WHERE engineer_id = ? AND status = 'active' AND assigned_at > ?");
    $stmt->execute([$userId, lv($lv, 'assigned-projects')]);
    $b['assigned-projects'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    // Any engineer can review any draft (no per-engineer ownership gate exists
    // at the draft stage), so this is a shared, not per-engineer, queue.
    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'draft' AND created_at > ?");
    $stmt->execute([lv($lv, 'engineering-review')]);
    $b['engineering-review'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    // Overdue milestones on this engineer's assigned projects — live, not
    // view-tracked: milestones has no "became overdue" event, only a
    // schedule due_date, so there's no honest way to know what's "new".
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM milestones m
        INNER JOIN engineer_project_assignments epa ON epa.project_id = m.project_id AND epa.engineer_id = ? AND epa.status = 'active'
        WHERE m.completed = 0 AND m.due_date < CURDATE()
    ");
    $stmt->execute([$userId]);
    $b['milestone-update'] = ['type' => 'orange', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM contractor_reports cr
        INNER JOIN engineer_project_assignments epa ON epa.project_id = cr.project_id AND epa.engineer_id = ? AND epa.status = 'active'
        WHERE cr.status = 'submitted' AND cr.created_at > ?
    ");
    $stmt->execute([$userId, lv($lv, 'inspection-review')]);
    $b['inspection-review'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM payment_requests pr
        INNER JOIN engineer_project_assignments epa ON epa.project_id = pr.project_id AND epa.engineer_id = ? AND epa.status = 'active'
        WHERE pr.status = 'submitted' AND pr.submitted_at > ?
    ");
    $stmt->execute([$userId, lv($lv, 'payment-review')]);
    $b['payment-review'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    // Status Tracker also hosts Issue NTP / Request Completion Inspection —
    // new gate-openings on this engineer's assigned projects since last view.
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM projects p
        INNER JOIN engineer_project_assignments epa ON epa.project_id = p.id AND epa.engineer_id = ? AND epa.status = 'active'
        WHERE p.status IN ('assigned','completion_inspection') AND p.updated_at > ?
    ");
    $stmt->execute([$userId, lv($lv, 'status-tracker')]);
    $b['status-tracker'] = ['type' => 'orange', 'count' => (int) $stmt->fetchColumn()];

    $b['dashboard'] = ['type' => 'red', 'count' =>
        $b['assigned-projects']['count'] + $b['engineering-review']['count']
        + $b['inspection-review']['count'] + $b['payment-review']['count']];

    return $b;
}

// ============================================================
// CONTRACTOR
// ============================================================
function computeContractorBadges(PDO $db, int $userId, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT id FROM contractors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $contractorId = (int) ($stmt->fetchColumn() ?: 0);
    if ($contractorId === 0) {
        return $b;
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_bid_announcements WHERE status IN ('posted','open') AND created_at > ?");
    $stmt->execute([lv($lv, 'open-biddings')]);
    $b['open-biddings'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_bid_submissions WHERE contractor_id = ? AND status IN ('recommended','rejected') AND updated_at > ?");
    $stmt->execute([$contractorId, lv($lv, 'bid-results')]);
    $b['bid-results'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE contractor_id = ? AND status IN ('awarded','assigned') AND updated_at > ?");
    $stmt->execute([$contractorId, lv($lv, 'assigned-projects')]);
    $b['assigned-projects'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM payment_requests WHERE contractor_id = ? AND status IN ('approved','paid','rejected') AND updated_at > ?");
    $stmt->execute([$contractorId, lv($lv, 'payment-status')]);
    $b['payment-status'] = ['type' => 'blue', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM supporting_documents WHERE owner_type = 'contractor' AND owner_id = ? AND status IN ('verified','rejected') AND reviewed_at > ?");
    $stmt->execute([$contractorId, lv($lv, 'accreditation-documents')]);
    $b['accreditation-documents'] = ['type' => 'blue', 'count' => (int) $stmt->fetchColumn()];

    $b['dashboard'] = ['type' => 'red', 'count' =>
        $b['open-biddings']['count'] + $b['bid-results']['count'] + $b['assigned-projects']['count']];

    return $b;
}

// ============================================================
// HOPE
// ============================================================
function computeHopeBadges(PDO $db, int $userId, array $lv): array
{
    $b = [];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'endorsed' AND updated_at > ?");
    $stmt->execute([lv($lv, 'project-approvals')]);
    $b['project-approvals'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM bac_award_recommendations WHERE status = 'sent_to_admin' AND updated_at > ?");
    $stmt->execute([lv($lv, 'award-approvals')]);
    $b['award-approvals'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE status = 'returned' AND updated_at > ?");
    $stmt->execute([lv($lv, 'returned-projects')]);
    $b['returned-projects'] = ['type' => 'orange', 'count' => (int) $stmt->fetchColumn()];

    $stmt = $db->prepare("SELECT COUNT(*) FROM project_deletion_requests WHERE status = 'pending' AND created_at > ?");
    $stmt->execute([lv($lv, 'deletion-requests')]);
    $b['deletion-requests'] = ['type' => 'red', 'count' => (int) $stmt->fetchColumn()];

    // Reuses the same shared per-user notifications feed the topbar bell uses.
    require_once __DIR__ . '/../includes/Notifications.php';
    $b['notifications'] = ['type' => 'blue', 'count' => (int) Notifications::unreadCount($userId)];

    $b['dashboard'] = ['type' => 'red', 'count' =>
        $b['project-approvals']['count'] + $b['award-approvals']['count'] + $b['returned-projects']['count'] + $b['deletion-requests']['count']];

    return $b;
}

// ============================================================
// CITIZEN
// ============================================================
function computeCitizenBadges(PDO $db, int $userId): array
{
    $b = [];

    $stmt = $db->prepare("SELECT id FROM citizens WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $citizenId = (int) ($stmt->fetchColumn() ?: 0);
    if ($citizenId === 0) {
        return $b;
    }

    // feedback has no updated_at column, so "just changed" isn't detectable —
    // this is a live count of your own still-open reports, not a "new" queue.
    $stmt = $db->prepare("SELECT COUNT(*) FROM feedback WHERE citizen_id = ? AND status IN ('open','in_progress')");
    $stmt->execute([$citizenId]);
    $count = (int) $stmt->fetchColumn();
    $b['track-feedback'] = ['type' => $count > 0 ? 'orange' : null, 'count' => $count];
    $b['dashboard'] = ['type' => 'orange', 'count' => $count];

    return $b;
}
