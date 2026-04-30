<?php
// ============================================================
// api/dashboard.php — Dashboard KPIs & charts
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
apiHeaders();

requireAnyRole(['super_admin', 'admin', 'engineer']);

$db  = getDB();
$out = [];

// ── KPI: Active projects ──
$out['active_projects'] = (int) $db
    ->query("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed','cancelled')")
    ->fetchColumn();

// ── KPI: Delayed projects ──
$out['delayed_projects'] = (int) $db
    ->query("SELECT COUNT(*) FROM projects WHERE status = 'delayed'")
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
    WHERE p.status NOT IN ('cancelled')
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
    WHERE status NOT IN ('cancelled')
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

respond($out);
