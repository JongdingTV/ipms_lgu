<?php
// ============================================================
// api/dashboard.php — Dashboard KPIs & charts
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/ContractorScoring.php';
require_once __DIR__ . '/../includes/Notifications.php';
require_once __DIR__ . '/../includes/ProjectHealth.php';
require_once __DIR__ . '/../includes/DocumentChecklist.php';
apiHeaders();

requireAnyRole(['super_admin', 'admin', 'engineer']);

$db  = getDB();
projectWorkflowEnsureRoleConnectionTables($db);
contractorsEnsureApplicationSchema($db);
contractorRefreshPerformanceScores($db);
$user = currentUser();
$out = [];

// ── KPI: Total projects ──
$out['total_projects'] = (int) $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// ── KPI: Active projects ──
$out['active_projects'] = (int) $db
    ->query("SELECT COUNT(*) FROM projects WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold')")
    ->fetchColumn();

// ── KPI: Delayed projects ──
$out['delayed_projects'] = (int) $db
    ->query("SELECT COUNT(*) FROM projects WHERE status = 'delayed'")
    ->fetchColumn();

// ── KPI: Completed projects ──
$out['completed_projects'] = (int) $db
    ->query("SELECT COUNT(*) FROM projects WHERE status IN ('completed','turnover')")
    ->fetchColumn();

// ── KPI: Pending approvals — every workflow gate currently blocked on someone
// else's decision, the same components api/sidebar-badges.php's admin
// 'dashboard' badge bundles, just as an absolute count instead of a
// since-last-viewed delta. ──
$out['pending_approvals'] =
    (int) $db->query("SELECT COUNT(*) FROM projects WHERE status IN ('draft','returned')")->fetchColumn()
    + (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'endorsed'")->fetchColumn()
    + (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'awarded' AND contractor_id IS NULL")->fetchColumn()
    + (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'completion_inspection'")->fetchColumn()
    + (int) $db->query("SELECT COUNT(*) FROM payment_requests WHERE status IN ('submitted','under_review')")->fetchColumn();

// ── KPI: Budget risk projects — distinct projects carrying at least one
// flagged expense (same 'flagged expense' signal hopeProjectRiskSummary()
// uses per-project, hope/api/portal.php:42-78). ──
$out['budget_risk_projects'] = (int) $db
    ->query("SELECT COUNT(DISTINCT project_id) FROM expenses WHERE flagged = 1")
    ->fetchColumn();

// ── KPI: Active engineers / contractors ──
$out['active_engineers'] = (int) $db
    ->query("SELECT COUNT(*) FROM users WHERE role = 'engineer' AND status = 'active'")
    ->fetchColumn();
$out['active_contractors'] = (int) $db
    ->query("SELECT COUNT(*) FROM contractors WHERE status = 'active' AND application_status = 'approved'")
    ->fetchColumn();

// ── KPI: Citizen complaints currently open ──
$out['citizen_complaints'] = (int) $db
    ->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open','in_progress')")
    ->fetchColumn();

// ── KPI: Budget utilized ──
$row = $db->query("
    SELECT
        SUM(p.budget)        AS total_budget,
        COALESCE(SUM(e.total_spent),0) AS total_spent
    FROM projects p
    LEFT JOIN (
        SELECT project_id, SUM(amount) AS total_spent FROM expenses GROUP BY project_id
    ) e ON e.project_id = p.id
    WHERE p.status NOT IN ('draft','returned','cancelled')
")->fetch();
$out['total_budget']  = (float) $row['total_budget'];
$out['total_spent']   = (float) $row['total_spent'];
$out['budget_pct']    = $row['total_budget'] > 0
    ? round(($row['total_spent'] / $row['total_budget']) * 100, 1)
    : 0;

// ── KPI: High-risk alerts (flagged expenses) ──
$out['high_risk_alerts'] = (int) $db
    ->query("SELECT COUNT(*) FROM expenses WHERE flagged = 1")
    ->fetchColumn();

// ── Project Progress Overview (monthly planned vs actual) ──
// Simulated progress series per month based on project data
$progressRows = $db->query("
    SELECT
        MONTH(start_date)  AS start_month,
        MONTH(end_date)    AS end_month,
        progress
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
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
        'month'   => date('M', mktime(0,0,0,$m,1)),
        'planned' => min(100, (int) round($planned[$m] / $c)),
        'actual'  => min(100, (int) round($actual[$m] / $c)),
    ];
}
$out['progress_chart'] = $progressChart;

// ── Projects by status (bar chart) ──
$out['status_mix'] = $db->query("
    SELECT status, COUNT(*) AS total
    FROM projects
    GROUP BY status
    ORDER BY total DESC, status ASC
")->fetchAll();

// ── Budget Status for donut ──
$anomaly = (float) $db
    ->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE flagged=1")
    ->fetchColumn();
$remaining = max(0, $out['total_budget'] - $out['total_spent']);
$out['budget_donut'] = [
    'spent'     => $out['total_spent'],
    'remaining' => $remaining,
    'anomaly'   => $anomaly,
];

// ── Top delayed projects ──
$out['top_delayed'] = $db->query("
    SELECT p.id, p.project_code, p.name, p.progress, p.end_date,
           DATEDIFF(CURDATE(), p.end_date) AS days_overdue,
           c.name AS contractor_name
    FROM projects p
    LEFT JOIN contractors c ON c.id = p.contractor_id
    WHERE p.status = 'delayed'
    ORDER BY days_overdue DESC
    LIMIT 5
")->fetchAll();

// ── Budget anomalies ──
$out['budget_anomalies'] = $db->query("
    SELECT e.id, p.name AS project_name, e.description, e.amount, e.expense_date,
           p.budget,
           (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id = p.id) AS total_spent
    FROM expenses e
    JOIN projects p ON p.id = e.project_id
    WHERE e.flagged = 1
    ORDER BY e.expense_date DESC
")->fetchAll();

// ── Projects by category / funding source (Reports page breakdowns) ──
$out['category_breakdown'] = $db->query("
    SELECT COALESCE(category, 'Uncategorized') AS label, COUNT(*) AS total, COALESCE(SUM(budget),0) AS total_budget
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
    GROUP BY label
    ORDER BY total DESC
")->fetchAll();

$out['funding_source_breakdown'] = $db->query("
    SELECT COALESCE(funding_source, 'Unspecified') AS label, COUNT(*) AS total, COALESCE(SUM(budget),0) AS total_budget
    FROM projects
    WHERE status NOT IN ('draft','returned','cancelled')
    GROUP BY label
    ORDER BY total DESC
")->fetchAll();

// ── Monthly spending trend, last 12 calendar months with any expense ──
$out['monthly_spending'] = $db->query("
    SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, DATE_FORMAT(expense_date, '%b %Y') AS month, SUM(amount) AS total
    FROM expenses
    GROUP BY ym
    ORDER BY ym ASC
    LIMIT 12
")->fetchAll();

// ── Recent citizen feedback ──
$out['recent_feedback'] = $db->query("
    SELECT f.id, f.citizen_name, f.message, f.priority, f.status,
           f.created_at, p.name AS project_name
    FROM feedback f
    LEFT JOIN projects p ON p.id = f.project_id
    ORDER BY
        FIELD(f.priority,'urgent','high','medium','low'),
        f.created_at DESC
    LIMIT 5
")->fetchAll();

// ── AI Insights ──
$delayCount  = $out['delayed_projects'];
$overBudget  = count(array_filter($out['budget_anomalies'], fn($a) =>
    $a['total_spent'] > $a['budget']));
$topContractor = $db->query("
    SELECT name, performance_score FROM contractors
    WHERE status='active' ORDER BY performance_score DESC LIMIT 1
")->fetch();

$out['ai_insights'] = [
    'delay_risk'     => $delayCount >= 3 ? 'High' : ($delayCount >= 1 ? 'Medium' : 'Low'),
    'budget_alert'   => $out['high_risk_alerts'] > 0,
    'top_contractor' => $topContractor,
];

// ── Recent Activities — system-wide, from the Audit Trail's activity_logs
// (see auth/session.php's logActivity()/activityLogEnsureSchema()). A
// compact feed for the dashboard, not the full filterable Audit Trail page
// (that's Super Admin's superadmin/api/portal.php action=list_audit_trail). ──
$out['recent_activities'] = $db->query("
    SELECT al.action, al.module, al.status, al.created_at,
           COALESCE(u.full_name, 'System') AS actor_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT 8
")->fetchAll();

// ── Latest Notifications — this admin's own, same data the topbar bell reads. ──
$stmt = $db->prepare("
    SELECT id, type, title, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([(int) ($user['user_id'] ?? 0)]);
$out['latest_notifications'] = $stmt->fetchAll();

// ── Upcoming Deadlines — open milestones due in the next 14 days, across
// every project (api/sidebar-badges.php:176 uses the same completed=0
// predicate for its overdue count; this widens it to a forward-looking window). ──
$out['upcoming_deadlines'] = $db->query("
    SELECT m.id, m.title, m.due_date, p.id AS project_id, p.project_code, p.name AS project_name
    FROM milestones m
    INNER JOIN projects p ON p.id = m.project_id
    WHERE m.completed = 0 AND m.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY m.due_date ASC
    LIMIT 8
")->fetchAll();

// ── Today's Inspections — inspection_date is engineer-picked (can be a
// future date), not always "today"; see engineer/api/mobile-inspection.php's
// inspection_submit handler. This is exact-match on today, not a range.
// Submitted only — an in-progress field session hasn't produced a real
// recommendation yet (still just the placeholder default). ──
$out['todays_inspections'] = $db->query("
    SELECT i.id, i.inspection_date, i.actual_progress_percent, i.recommendation,
           p.id AS project_id, p.project_code, p.name AS project_name,
           u.full_name AS engineer_name
    FROM inspections i
    INNER JOIN projects p ON p.id = i.project_id
    INNER JOIN users u ON u.id = i.engineer_id
    WHERE i.inspection_date = CURDATE() AND i.status = 'submitted'
    ORDER BY i.created_at DESC
")->fetchAll();

// ── GIS health map — every coordinate-bearing, in-progress project, colored
// by the same canonical Project Health Score every other health display in
// the app uses (includes/ProjectHealth.php — Project Details, Project
// Cards, and the Reports summary below all read the same function). ──
$gisIdRows = $db->query("
    SELECT p.id, p.project_code, p.name, p.status, p.progress, p.latitude, p.longitude,
           c.name AS contractor_name
    FROM projects p
    LEFT JOIN contractors c ON c.id = p.contractor_id
    WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
      AND p.status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection')
    ORDER BY p.updated_at DESC
    LIMIT 200
")->fetchAll();

$gisHealthScores = projectHealthScoresForIds($db, array_column($gisIdRows, 'id'));

$gisProjects = [];
foreach ($gisIdRows as $row) {
    $health = $gisHealthScores[(int) $row['id']] ?? ['score' => 0, 'status' => 'critical'];

    $gisProjects[] = [
        'id' => (int) $row['id'],
        'project_code' => $row['project_code'],
        'name' => $row['name'],
        'status' => $row['status'],
        'progress' => (int) $row['progress'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'contractor_name' => $row['contractor_name'],
        'health_score' => $health['score'],
        'health' => $health['status'],
    ];
}
$out['gis_projects'] = $gisProjects;

// ── Project Health overview — worst-first, feeds the Reports page. ──
$out['project_health_summary'] = projectHealthAllScores($db, 20);

// ── AI Recommendations — advisory-only, rule-based synthesis of the KPIs
// already computed above (same "advisory, not a real model" framing as
// hope/api/portal.php's per-project risk summary — no ML call happens here). ──
$worstContractor = $db->query("
    SELECT name, performance_score FROM contractors
    WHERE status = 'active' AND performance_score < 70
    ORDER BY performance_score ASC LIMIT 1
")->fetch();

$recommendations = [];
if ($out['delayed_projects'] > 0) {
    $recommendations[] = $out['delayed_projects'] . ' project(s) are currently delayed — review Top Delayed Projects and consider reallocating resources.';
}
if ($out['budget_risk_projects'] > 0) {
    $recommendations[] = $out['budget_risk_projects'] . ' project(s) have flagged expenses — review Budget Anomalies before approving further payments.';
}
if ($out['pending_approvals'] > 0) {
    $recommendations[] = $out['pending_approvals'] . ' item(s) are awaiting a decision — clearing the queue keeps contractors and engineers from stalling.';
}
if (count($out['todays_inspections']) > 0) {
    $recommendations[] = count($out['todays_inspections']) . ' inspection(s) are on the books for today — confirm engineer availability.';
}
if ($worstContractor) {
    $recommendations[] = $worstContractor['name'] . ' has a low performance score (' . (int) $worstContractor['performance_score'] . '/100) — consider closer oversight on their active projects.';
}
if ($recommendations === []) {
    $recommendations[] = 'No urgent risk signals detected — all monitored metrics are within normal range.';
}
$out['ai_recommendations'] = $recommendations;

$out['workflow_connections'] = [
    'contracts' => (int) $db->query("SELECT COUNT(*) FROM contracts")->fetchColumn(),
    'active_contracts' => (int) $db->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn(),
    'inspections' => (int) $db->query("SELECT COUNT(*) FROM inspections")->fetchColumn(),
    'pending_payment_requests' => (int) $db->query("SELECT COUNT(*) FROM payment_requests WHERE status IN ('submitted','under_review')")->fetchColumn(),
];

$out['recent_workflow'] = $db->query("
    SELECT 'Contract' AS record_type,
           ct.created_at AS record_date,
           p.project_code,
           p.name AS project_name,
           c.name AS actor_name,
           ct.status,
           CONCAT(ct.contract_no, ' - PHP ', FORMAT(ct.contract_amount, 2)) AS details
    FROM contracts ct
    INNER JOIN projects p ON p.id = ct.project_id
    INNER JOIN contractors c ON c.id = ct.contractor_id
    UNION ALL
    SELECT 'Inspection' AS record_type,
           i.created_at AS record_date,
           p.project_code,
           p.name AS project_name,
           u.full_name AS actor_name,
           i.recommendation AS status,
           CONCAT('Actual progress ', i.actual_progress_percent, '%') AS details
    FROM inspections i
    INNER JOIN projects p ON p.id = i.project_id
    INNER JOIN users u ON u.id = i.engineer_id
    WHERE i.status = 'submitted'
    UNION ALL
    SELECT 'Payment Request' AS record_type,
           pr.submitted_at AS record_date,
           p.project_code,
           p.name AS project_name,
           c.name AS actor_name,
           pr.status,
           CONCAT(pr.billing_no, ' - PHP ', FORMAT(pr.requested_amount, 2)) AS details
    FROM payment_requests pr
    INNER JOIN projects p ON p.id = pr.project_id
    INNER JOIN contractors c ON c.id = pr.contractor_id
    ORDER BY record_date DESC
    LIMIT 8
")->fetchAll();

// ── Documentation Completeness — feeds the Reports page's new panel.
// Reuses includes/DocumentChecklist.php directly (same engine behind the
// project-detail Document Checklist section and Task Center's checklist_gap
// task) rather than re-deriving any of its rules. ──
$docProjects = $db->query("SELECT id, project_code, name FROM projects WHERE status NOT IN ('draft', 'cancelled')")->fetchAll();
$docBuckets = ['complete' => 0, 'in_progress' => 0, 'has_gaps' => 0];
$docProjectsWithGaps = [];
foreach ($docProjects as $p) {
    $items = documentChecklistForProject($db, (int) $p['id']);
    $summary = documentChecklistSummary($items);
    $missing = documentChecklistMissingRequired($items);
    if ($missing) {
        $docBuckets['has_gaps']++;
        $docProjectsWithGaps[] = [
            'project_code' => $p['project_code'], 'name' => $p['name'],
            'missing_count' => count($missing),
            'missing_labels' => implode(', ', array_column($missing, 'label')),
        ];
    } elseif ($summary['percent'] >= 100) {
        $docBuckets['complete']++;
    } else {
        $docBuckets['in_progress']++;
    }
}
usort($docProjectsWithGaps, fn($a, $b) => $b['missing_count'] <=> $a['missing_count']);
$out['document_checklist_summary'] = [
    'buckets' => $docBuckets,
    'projects_with_gaps' => array_slice($docProjectsWithGaps, 0, 20),
];

respond($out);
