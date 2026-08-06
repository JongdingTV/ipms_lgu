<?php
// ============================================================
// api/contractor-performance.php — Contractor Performance Dashboard (Admin)
//
// Read-only reporting endpoint. Reuses includes/ContractorScoring.php's
// existing weighted scoring engine rather than reimplementing it — score and
// risk_label are always computed live per request, never read from (or
// written to) the persisted contractors.performance_score column.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/ContractorScoring.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);

function contractorPerfRiskLabel(int $score): string
{
    if ($score >= 85) {
        return 'low';
    }
    if ($score >= 70) {
        return 'medium';
    }
    return 'high';
}

function contractorPerfProjectStats(PDO $db, int $contractorId): array
{
    $stmt = $db->prepare("
        SELECT SUM(CASE WHEN status IN ('completed', 'turnover') THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) AS delayed_count
        FROM projects
        WHERE contractor_id = ?
    ");
    $stmt->execute([$contractorId]);
    $row = $stmt->fetch();
    return [
        'projects_completed' => (int) ($row['completed'] ?? 0),
        'projects_delayed' => (int) ($row['delayed_count'] ?? 0),
    ];
}

// Completion time is measured start_date -> whichever completion timestamp
// the project actually has (turnover takes precedence over the earlier
// completion-inspection milestone when both exist).
function contractorPerfAvgCompletionDays(PDO $db, int $contractorId): ?int
{
    $stmt = $db->prepare("
        SELECT AVG(DATEDIFF(COALESCE(turnover_at, completion_inspected_at), start_date)) AS avg_days
        FROM projects
        WHERE contractor_id = ? AND status IN ('completed', 'turnover')
          AND start_date IS NOT NULL
          AND COALESCE(turnover_at, completion_inspected_at) IS NOT NULL
    ");
    $stmt->execute([$contractorId]);
    $avg = $stmt->fetchColumn();
    return $avg !== null ? (int) round((float) $avg) : null;
}

function contractorPerfInspectionStats(PDO $db, int $contractorId): array
{
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN i.recommendation = 'approved' THEN 1 ELSE 0 END) AS approved
        FROM inspections i
        JOIN projects p ON p.id = i.project_id
        WHERE p.contractor_id = ?
    ");
    $stmt->execute([$contractorId]);
    $row = $stmt->fetch();
    $total = (int) ($row['total'] ?? 0);
    return [
        'total_inspections' => $total,
        'inspection_pct_approved' => $total > 0 ? round(((int) $row['approved']) / $total * 100, 1) : null,
    ];
}

// Only 'commendation' reads as positive citizen sentiment; the seven
// operational-issue categories read as negative; 'suggestion'/'inquiry' are
// neutral and excluded from both the counts and the percentage denominator —
// there is no numeric rating column anywhere in this schema to derive from.
const CONTRACTOR_PERF_NEGATIVE_CATEGORIES = ['complaint', 'road_damage', 'drainage_flooding', 'streetlight', 'sidewalk_accessibility', 'safety_hazard', 'project_delay'];

function contractorPerfFeedbackStats(PDO $db, int $contractorId): array
{
    $stmt = $db->prepare("
        SELECT f.category, COUNT(*) AS total
        FROM feedback f
        JOIN projects p ON p.id = f.project_id
        WHERE p.contractor_id = ?
        GROUP BY f.category
    ");
    $stmt->execute([$contractorId]);

    $commendation = 0;
    $negative = 0;
    foreach ($stmt->fetchAll() as $row) {
        $category = (string) $row['category'];
        $count = (int) $row['total'];
        if ($category === 'commendation') {
            $commendation += $count;
        } elseif (in_array($category, CONTRACTOR_PERF_NEGATIVE_CATEGORIES, true)) {
            $negative += $count;
        }
    }

    $denominator = $commendation + $negative;
    return [
        'feedback_positive_pct' => $denominator > 0 ? (int) round($commendation / $denominator * 100) : null,
        'feedback_commendation_count' => $commendation,
        'feedback_complaint_count' => $negative,
    ];
}

// Averages spent/budget% across the contractor's own completed/turnover
// projects, skipping any with a zero budget or zero recorded spend (neither
// is a meaningful signal of budget discipline, just missing data).
function contractorPerfBudgetPerformance(PDO $db, int $contractorId): ?float
{
    $stmt = $db->prepare("
        SELECT p.budget, COALESCE(SUM(e.amount), 0) AS spent
        FROM projects p
        LEFT JOIN expenses e ON e.project_id = p.id
        WHERE p.contractor_id = ? AND p.status IN ('completed', 'turnover')
        GROUP BY p.id, p.budget
    ");
    $stmt->execute([$contractorId]);

    $ratios = [];
    foreach ($stmt->fetchAll() as $row) {
        $budget = (float) $row['budget'];
        $spent = (float) $row['spent'];
        if ($budget > 0 && $spent > 0) {
            $ratios[] = $spent / $budget * 100;
        }
    }

    if ($ratios === []) {
        return null;
    }
    return round(array_sum($ratios) / count($ratios), 1);
}

function contractorPerfBuildRow(PDO $db, array $contractor): array
{
    $id = (int) $contractor['id'];
    $score = contractorCalculatePerformanceScore($db, $id);

    return array_merge(
        [
            'contractor_id' => $id,
            'name' => (string) $contractor['name'],
            'score' => $score,
            'risk_label' => contractorPerfRiskLabel($score),
            'avg_completion_days' => contractorPerfAvgCompletionDays($db, $id),
            'budget_performance_pct' => contractorPerfBudgetPerformance($db, $id),
            'is_blacklisted' => (bool) ($contractor['is_blacklisted'] ?? false),
        ],
        contractorPerfProjectStats($db, $id),
        contractorPerfInspectionStats($db, $id),
        contractorPerfFeedbackStats($db, $id)
    );
}

function contractorPerfSortAndRank(array $rows): array
{
    usort($rows, function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $rank = 1;
    foreach ($rows as &$row) {
        $row['rank'] = $rank++;
    }
    unset($row);

    return $rows;
}

// This dashboard is an internal oversight tool, so it lists every contractor
// on file (not just active/approved ones) — the whole point is visibility
// into who is underperforming, including already-flagged ones.
function contractorPerfHandleLeaderboard(PDO $db): void
{
    $contractors = $db->query('SELECT id, name, is_blacklisted FROM contractors ORDER BY name ASC')->fetchAll();

    $rows = [];
    foreach ($contractors as $contractor) {
        $rows[] = contractorPerfBuildRow($db, $contractor);
    }

    respond(['success' => true, 'contractors' => contractorPerfSortAndRank($rows)]);
}

function contractorPerfMonthWindow(): array
{
    $months = [];
    $cursor = new DateTime('first day of this month');
    $cursor->modify('-11 months');
    for ($i = 0; $i < 12; $i++) {
        $months[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M Y')];
        $cursor->modify('+1 month');
    }
    return $months;
}

function contractorPerfTrend(PDO $db, int $contractorId): array
{
    $months = contractorPerfMonthWindow();
    $windowStart = $months[0]['key'] . '-01';

    $completionsByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(COALESCE(turnover_at, completion_inspected_at), '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM projects
        WHERE contractor_id = ? AND status IN ('completed', 'turnover')
          AND COALESCE(turnover_at, completion_inspected_at) >= ?
        GROUP BY ym
    ");
    $stmt->execute([$contractorId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $completionsByMonth[$row['ym']] = (int) $row['cnt'];
    }

    $delaysByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(edr.created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM engineer_delay_reports edr
        JOIN projects p ON p.id = edr.project_id
        WHERE p.contractor_id = ? AND edr.created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$contractorId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $delaysByMonth[$row['ym']] = (int) $row['cnt'];
    }

    $flaggedByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(e.expense_date, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM expenses e
        JOIN projects p ON p.id = e.project_id
        WHERE p.contractor_id = ? AND e.flagged = 1 AND e.expense_date >= ?
        GROUP BY ym
    ");
    $stmt->execute([$contractorId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $flaggedByMonth[$row['ym']] = (int) $row['cnt'];
    }

    return [
        'months' => array_map(fn($m) => $m['label'], $months),
        'completions' => array_map(fn($m) => $completionsByMonth[$m['key']] ?? 0, $months),
        'delay_reports' => array_map(fn($m) => $delaysByMonth[$m['key']] ?? 0, $months),
        'flagged_expenses' => array_map(fn($m) => $flaggedByMonth[$m['key']] ?? 0, $months),
    ];
}

// Recomputes the full leaderboard ordering to find one contractor's rank.
// Contractor counts in this system are small (single digits to low tens),
// so a second full pass here is cheap and keeps rank always consistent with
// action=leaderboard rather than duplicating its sort logic separately.
function contractorPerfRankOf(PDO $db, int $contractorId): int
{
    $all = $db->query('SELECT id, name FROM contractors')->fetchAll();
    $scored = [];
    foreach ($all as $c) {
        $scored[] = [
            'id' => (int) $c['id'],
            'name' => (string) $c['name'],
            'score' => contractorCalculatePerformanceScore($db, (int) $c['id']),
        ];
    }
    usort($scored, function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
    foreach ($scored as $i => $c) {
        if ($c['id'] === $contractorId) {
            return $i + 1;
        }
    }
    return 0;
}

function contractorPerfHandleDetail(PDO $db): void
{
    $id = (int) ($_GET['contractor_id'] ?? 0);
    if ($id <= 0) {
        respond(['success' => false, 'message' => 'A valid contractor_id is required.'], 422);
    }

    $stmt = $db->prepare('SELECT id, name, is_blacklisted, credibility_score FROM contractors WHERE id = ?');
    $stmt->execute([$id]);
    $contractor = $stmt->fetch();
    if (!$contractor) {
        respond(['success' => false, 'message' => 'Contractor not found'], 404);
    }

    $row = contractorPerfBuildRow($db, $contractor);
    $row['rank'] = contractorPerfRankOf($db, $id);
    $row['components'] = contractorCalculatePerformanceScoreBreakdown($db, $id)['components'];
    $row['credibility_score'] = (float) $contractor['credibility_score'];

    $delayStmt = $db->prepare("
        SELECT COUNT(*) FROM engineer_delay_reports edr
        JOIN projects p ON p.id = edr.project_id
        WHERE p.contractor_id = ?
    ");
    $delayStmt->execute([$id]);
    $row['delay_report_count'] = (int) $delayStmt->fetchColumn();

    $issueStmt = $db->prepare("
        SELECT COUNT(*) FROM engineer_issue_reports eir
        JOIN projects p ON p.id = eir.project_id
        WHERE p.contractor_id = ? AND eir.status != 'closed'
    ");
    $issueStmt->execute([$id]);
    $row['open_issue_count'] = (int) $issueStmt->fetchColumn();

    $row['trend'] = contractorPerfTrend($db, $id);

    respond(['success' => true, 'contractor' => $row]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');

    if ($method === 'GET' && $action === 'leaderboard') {
        contractorPerfHandleLeaderboard($db);
    } elseif ($method === 'GET' && $action === 'detail') {
        contractorPerfHandleDetail($db);
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
