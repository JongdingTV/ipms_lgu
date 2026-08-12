<?php
// ============================================================
// api/engineer-performance.php — Engineer Performance Dashboard (Admin)
//
// Read-only reporting endpoint. Reuses includes/EngineerScoring.php's
// scoring engine (mirrors includes/ContractorScoring.php's shape) — score
// and risk_label are always computed live per request; there is no
// users.performance_score column to read from or write to.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/EngineerScoring.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);

function engineerPerfRiskLabel(int $score): string
{
    if ($score >= 85) {
        return 'low';
    }
    if ($score >= 70) {
        return 'medium';
    }
    return 'high';
}

function engineerPerfInspectionStats(PDO $db, int $engineerId): array
{
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN recommendation = 'approved' THEN 1 ELSE 0 END) AS approved
        FROM inspections
        WHERE engineer_id = ? AND status = 'submitted'
    ");
    $stmt->execute([$engineerId]);
    $row = $stmt->fetch();
    return [
        'total_inspections' => (int) ($row['total'] ?? 0),
        'completed_inspections' => (int) ($row['approved'] ?? 0),
    ];
}

// Only inspections tied back to a progress report have a meaningful
// "how long after the report came in" measurement — ad hoc inspections with
// no progress_report_id are excluded from this average, same as the
// contract specifies.
function engineerPerfAvgInspectionDays(PDO $db, int $engineerId): ?float
{
    $stmt = $db->prepare("
        SELECT AVG(DATEDIFF(i.created_at, r.report_date)) AS avg_days
        FROM inspections i
        JOIN contractor_reports r ON r.id = i.progress_report_id
        WHERE i.engineer_id = ? AND i.progress_report_id IS NOT NULL AND i.status = 'submitted'
    ");
    $stmt->execute([$engineerId]);
    $avg = $stmt->fetchColumn();
    return $avg !== null ? round((float) $avg, 1) : null;
}

function engineerPerfProjectsAssigned(PDO $db, int $engineerId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM engineer_project_assignments WHERE engineer_id = ? AND status = 'active'");
    $stmt->execute([$engineerId]);
    return (int) $stmt->fetchColumn();
}

function engineerPerfBuildRow(PDO $db, array $engineer): array
{
    $id = (int) $engineer['id'];
    $score = engineerCalculatePerformanceScore($db, $id);

    return array_merge(
        [
            'engineer_id' => $id,
            'name' => (string) $engineer['full_name'],
            'score' => $score,
            'risk_label' => engineerPerfRiskLabel($score),
            'pending_inspections' => engineerPendingInspectionCount($db, $id),
            'avg_inspection_days' => engineerPerfAvgInspectionDays($db, $id),
            'projects_assigned' => engineerPerfProjectsAssigned($db, $id),
            'reports_submitted' => engineerReportsSubmittedCount($db, $id),
            'is_active' => ($engineer['status'] ?? '') === 'active',
        ],
        engineerPerfInspectionStats($db, $id)
    );
}

function engineerPerfSortAndRank(array $rows): array
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

function engineerPerfHandleLeaderboard(PDO $db): void
{
    $engineers = $db->query("SELECT id, full_name, status FROM users WHERE role = 'engineer' ORDER BY full_name ASC")->fetchAll();

    $rows = [];
    foreach ($engineers as $engineer) {
        $rows[] = engineerPerfBuildRow($db, $engineer);
    }

    respond(['success' => true, 'engineers' => engineerPerfSortAndRank($rows)]);
}

function engineerPerfMonthWindow(): array
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

function engineerPerfTrend(PDO $db, int $engineerId): array
{
    $months = engineerPerfMonthWindow();
    $windowStart = $months[0]['key'] . '-01';

    $inspectionsByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM inspections
        WHERE engineer_id = ? AND recommendation = 'approved' AND status = 'submitted' AND created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$engineerId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $inspectionsByMonth[$row['ym']] = (int) $row['cnt'];
    }

    $reportsByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(combined.created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM (
            SELECT created_at FROM engineer_milestone_updates WHERE engineer_id = ?
            UNION ALL SELECT created_at FROM engineer_progress_photos WHERE engineer_id = ?
            UNION ALL SELECT created_at FROM engineer_delay_reports WHERE engineer_id = ?
            UNION ALL SELECT created_at FROM engineer_issue_reports WHERE engineer_id = ?
            UNION ALL SELECT created_at FROM engineer_status_updates WHERE engineer_id = ?
        ) combined
        WHERE combined.created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$engineerId, $engineerId, $engineerId, $engineerId, $engineerId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $reportsByMonth[$row['ym']] = (int) $row['cnt'];
    }

    $delaysByMonth = [];
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM engineer_delay_reports
        WHERE engineer_id = ? AND created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$engineerId, $windowStart]);
    foreach ($stmt->fetchAll() as $row) {
        $delaysByMonth[$row['ym']] = (int) $row['cnt'];
    }

    return [
        'months' => array_map(fn($m) => $m['label'], $months),
        'inspections_completed' => array_map(fn($m) => $inspectionsByMonth[$m['key']] ?? 0, $months),
        'reports_submitted' => array_map(fn($m) => $reportsByMonth[$m['key']] ?? 0, $months),
        'delay_reports' => array_map(fn($m) => $delaysByMonth[$m['key']] ?? 0, $months),
    ];
}

// Recomputes the full leaderboard ordering to find one engineer's rank —
// cheap at this system's engineer headcount, and keeps rank always
// consistent with action=leaderboard's own sort.
function engineerPerfRankOf(PDO $db, int $engineerId): int
{
    $all = $db->query("SELECT id, full_name FROM users WHERE role = 'engineer'")->fetchAll();
    $scored = [];
    foreach ($all as $e) {
        $scored[] = [
            'id' => (int) $e['id'],
            'name' => (string) $e['full_name'],
            'score' => engineerCalculatePerformanceScore($db, (int) $e['id']),
        ];
    }
    usort($scored, function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
    foreach ($scored as $i => $e) {
        if ($e['id'] === $engineerId) {
            return $i + 1;
        }
    }
    return 0;
}

function engineerPerfHandleDetail(PDO $db): void
{
    $id = (int) ($_GET['engineer_id'] ?? 0);
    if ($id <= 0) {
        respond(['success' => false, 'message' => 'A valid engineer_id is required.'], 422);
    }

    $stmt = $db->prepare('SELECT id, full_name, status, role FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $engineer = $stmt->fetch();
    if (!$engineer || $engineer['role'] !== 'engineer') {
        respond(['success' => false, 'message' => 'Engineer not found'], 404);
    }

    $row = engineerPerfBuildRow($db, $engineer);
    $row['rank'] = engineerPerfRankOf($db, $id);
    $row['components'] = engineerCalculatePerformanceScoreBreakdown($db, $id)['components'];
    $row['trend'] = engineerPerfTrend($db, $id);

    respond(['success' => true, 'engineer' => $row]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');

    if ($method === 'GET' && $action === 'leaderboard') {
        engineerPerfHandleLeaderboard($db);
    } elseif ($method === 'GET' && $action === 'detail') {
        engineerPerfHandleDetail($db);
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
