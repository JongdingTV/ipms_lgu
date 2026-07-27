<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);
feedbackEnsureSchema($pdo);

// Get citizen data. A missing citizens row (e.g. the seeded demo account)
// only means "no personal submissions" — public project data still loads.
$stmt = $pdo->prepare("SELECT id FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

// Get statistics. 'completion_inspection' and 'turnover' are late workflow
// stages from the staff side — a project sitting there is still real to a
// citizen (in progress / finished), so they must not vanish from the counts.
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status IN ('approved','bidding','awarded','assigned','active','on_hold','completion_inspection') THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN status IN ('completed','turnover') THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed_projects
    FROM projects
");
$stmt->execute();
$projectStats = $stmt->fetch();

// Get citizen's feedback count
$feedbackStats = ['count' => 0];
if ($citizenId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE citizen_id = ?");
    $stmt->execute([$citizenId]);
    $feedbackStats = $stmt->fetch();
}

// Get recent projects
$stmt = $pdo->prepare("
    SELECT id, project_code, name, description, location, budget, start_date, end_date, progress, status
    FROM projects
    WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover')
    ORDER BY created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentProjects = $stmt->fetchAll();

// Get recent feedback
$recentFeedback = [];
if ($citizenId) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.project_id, f.message, f.category, f.concern_type, f.priority,
               f.district, f.barangay, f.latitude, f.longitude, f.status, f.created_at,
               f.cimm_sync_status, f.cimm_reference, f.cimm_request_id,
               p.name as project_name
        FROM feedback f
        LEFT JOIN projects p ON f.project_id = p.id
        WHERE f.citizen_id = ?
        ORDER BY f.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$citizenId]);
    $recentFeedback = $stmt->fetchAll();
}

// Latest field activity across all public projects (engineer status updates,
// falling back gracefully when there are none yet). Read-only view of the
// staff side's work for the dashboard's "Latest Updates" feed.
$stmt = $pdo->prepare("
    SELECT u.progress_percent, u.status, u.notes, u.created_at,
           p.id AS project_id, p.name AS project_name
    FROM engineer_status_updates u
    INNER JOIN projects p ON p.id = u.project_id
    WHERE p.status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover')
    ORDER BY u.created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentUpdates = $stmt->fetchAll();

// Attach proof photos in one query, grouped by feedback id
if ($recentFeedback) {
    $ids = array_column($recentFeedback, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $photoStmt = $pdo->prepare("SELECT feedback_id, photo_path FROM feedback_photos WHERE feedback_id IN ($placeholders) ORDER BY id");
    $photoStmt->execute($ids);

    $photosByFeedback = [];
    foreach ($photoStmt->fetchAll() as $row) {
        $photosByFeedback[$row['feedback_id']][] = $row['photo_path'];
    }
    foreach ($recentFeedback as &$item) {
        $item['photos'] = $photosByFeedback[$item['id']] ?? [];
    }
    unset($item);
}

// Monthly planned-vs-actual progress — the same simulated series the staff
// dashboard computes (api/dashboard.php), so both portals tell one story.
$progressRows = $pdo->query("
    SELECT MONTH(start_date) AS start_month, MONTH(end_date) AS end_month, progress
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
      AND start_date IS NOT NULL AND end_date IS NOT NULL
")->fetchAll();

$planned = array_fill(1, 12, 0);
$actual  = array_fill(1, 12, 0);
$counts  = array_fill(1, 12, 0);

foreach ($progressRows as $r) {
    $sm = max(1, (int) $r['start_month']);
    $em = min(12, (int) $r['end_month']);
    for ($m = $sm; $m <= $em; $m++) {
        $duration   = max(1, $em - $sm + 1);
        $monthIndex = $m - $sm + 1;
        $planned[$m] += round(($monthIndex / $duration) * 100);
        $actual[$m]  += round(($r['progress'] / 100) * ($monthIndex / $duration) * 100);
        $counts[$m]++;
    }
}

$progressChart = [];
for ($m = 1; $m <= 12; $m++) {
    $c = max(1, $counts[$m]);
    $progressChart[] = [
        'month'   => date('M', mktime(0, 0, 0, $m, 1)),
        'planned' => min(100, (int) round($planned[$m] / $c)),
        'actual'  => min(100, (int) round($actual[$m] / $c)),
    ];
}

// ── Budget by workflow stage ──
// Buckets mirror the Project Status tracker's stages so the dashboard and
// tracker speak the same language.
$stageBuckets = [
    'Preparation' => ['approved', 'bidding', 'awarded', 'assigned'],
    'Construction' => ['active', 'on_hold'],
    'Delayed' => ['delayed'],
    'Inspection' => ['completion_inspection'],
    'Completed' => ['completed', 'turnover'],
];
$stageBudget = array_fill_keys(array_keys($stageBuckets), 0.0);
foreach ($pdo->query("SELECT status, COALESCE(SUM(budget),0) total FROM projects GROUP BY status") as $row) {
    foreach ($stageBuckets as $label => $statuses) {
        if (in_array($row['status'], $statuses, true)) {
            $stageBudget[$label] += (float) $row['total'];
            break;
        }
    }
}
$budgetByStage = [];
foreach ($stageBudget as $label => $total) {
    $budgetByStage[] = ['stage' => $label, 'total' => $total];
}

// ── New projects per month, last 12 months (by start date) ──
$startedRaw = [];
foreach ($pdo->query("
    SELECT DATE_FORMAT(start_date, '%Y-%m') ym, COUNT(*) cnt
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
      AND start_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
      AND start_date <= CURDATE()
    GROUP BY ym
") as $row) {
    $startedRaw[$row['ym']] = (int) $row['cnt'];
}
$projectsStarted = [];
$cursor = new DateTime(date('Y-m-01'));
$cursor->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $projectsStarted[] = [
        'month' => $cursor->format('M'),
        'count' => $startedRaw[$cursor->format('Y-m')] ?? 0,
    ];
    $cursor->modify('+1 month');
}

// ── Community feedback by category (aggregate counts only — no content) ──
$feedbackByCategory = $pdo->query("
    SELECT category, COUNT(*) AS total
    FROM feedback
    GROUP BY category
    ORDER BY total DESC
")->fetchAll();

echo json_encode([
    'progress_chart' => $progressChart,
    'budget_by_stage' => $budgetByStage,
    'projects_started' => $projectsStarted,
    'feedback_by_category' => $feedbackByCategory,
    'stats' => [
        'active_projects' => (int)($projectStats['active_projects'] ?? 0),
        'completed_projects' => (int)($projectStats['completed_projects'] ?? 0),
        'delayed_projects' => (int)($projectStats['delayed_projects'] ?? 0),
        'my_submissions' => (int)($feedbackStats['count'] ?? 0)
    ],
    'recent_projects' => $recentProjects,
    'recent_feedback' => $recentFeedback,
    'recent_updates' => $recentUpdates
]);
