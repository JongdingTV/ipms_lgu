<?php
// ============================================================
// includes/EngineerScoring.php — Engineer performance scoring engine
//
// Mirrors includes/ContractorScoring.php's shape (weights function,
// calculate wrapper, breakdown function with a no-history neutral
// fallback) but with engineer-specific signals. Unlike ContractorScoring,
// this is read-only/computed-live only — there is no users.performance_score
// column to persist into, and adding one is out of scope. Every score here
// is recalculated fresh on each request.
// ============================================================

function engineerPerformanceScoreWeights(): array
{
    return [
        'quality' => 35,
        'responsiveness' => 30,
        'activity' => 20,
        'delay_impact' => 15,
    ];
}

function engineerCalculatePerformanceScore(PDO $db, int $engineerId): int
{
    return engineerCalculatePerformanceScoreBreakdown($db, $engineerId)['score'];
}

// contractor_reports rows on this engineer's ACTIVE assigned projects that
// still need an inspection — mirrors engineer/api/portal.php's own
// action=pending_inspections query byte-for-byte (LEFT JOIN inspections +
// "no inspection yet OR the report itself is still submitted/under_review"),
// the established pattern in this codebase, since the inspections table
// itself has no pending/scheduled state of its own.
function engineerPendingInspectionCount(PDO $db, int $engineerId): int
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM engineer_project_assignments a
        INNER JOIN projects p ON p.id = a.project_id
        INNER JOIN contractor_reports r ON r.project_id = p.id
        LEFT JOIN inspections i ON i.progress_report_id = r.id
        WHERE a.engineer_id = ?
          AND a.status = 'active'
          AND (i.id IS NULL OR r.status IN ('submitted', 'under_review'))
    ");
    $stmt->execute([$engineerId]);
    return (int) $stmt->fetchColumn();
}

// Combined count across all 5 engineer report tables — each carries
// engineer_id directly, no join needed.
function engineerReportsSubmittedCount(PDO $db, int $engineerId): int
{
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM engineer_milestone_updates WHERE engineer_id = ?) +
            (SELECT COUNT(*) FROM engineer_progress_photos WHERE engineer_id = ?) +
            (SELECT COUNT(*) FROM engineer_delay_reports WHERE engineer_id = ?) +
            (SELECT COUNT(*) FROM engineer_issue_reports WHERE engineer_id = ?) +
            (SELECT COUNT(*) FROM engineer_status_updates WHERE engineer_id = ?) AS total
    ");
    $stmt->execute([$engineerId, $engineerId, $engineerId, $engineerId, $engineerId]);
    return (int) $stmt->fetchColumn();
}

function engineerCalculatePerformanceScoreBreakdown(PDO $db, int $engineerId): array
{
    $weights = engineerPerformanceScoreWeights();

    $inspectionStmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN recommendation = 'approved' THEN 1 ELSE 0 END) AS approved
        FROM inspections
        WHERE engineer_id = ?
    ");
    $inspectionStmt->execute([$engineerId]);
    $inspectionRow = $inspectionStmt->fetch();
    $totalInspections = (int) ($inspectionRow['total'] ?? 0);

    if ($totalInspections === 0) {
        return [
            'score' => 65,
            'components' => [
                'quality' => ['weight' => $weights['quality'], 'earned' => null],
                'responsiveness' => ['weight' => $weights['responsiveness'], 'earned' => null],
                'activity' => ['weight' => $weights['activity'], 'earned' => null],
                'delay_impact' => ['weight' => $weights['delay_impact'], 'earned' => null],
            ],
        ];
    }

    $qualityScore = (((int) $inspectionRow['approved']) / $totalInspections) * $weights['quality'];

    $pendingCount = engineerPendingInspectionCount($db, $engineerId);
    $responsivenessScore = max(0, $weights['responsiveness'] - $pendingCount * 3);

    $assignedStmt = $db->prepare("SELECT COUNT(*) FROM engineer_project_assignments WHERE engineer_id = ? AND status = 'active'");
    $assignedStmt->execute([$engineerId]);
    $projectsAssigned = (int) $assignedStmt->fetchColumn();

    $reportsSubmitted = engineerReportsSubmittedCount($db, $engineerId);
    // Full 20 pts at a reports:projects ratio of 3 or better, scaled linearly
    // below that; 0 if the engineer has no active assignment to measure against.
    $activityScore = $projectsAssigned > 0
        ? min(1, ($reportsSubmitted / $projectsAssigned) / 3) * $weights['activity']
        : 0;

    $delayStmt = $db->prepare("SELECT severity, COUNT(*) AS total FROM engineer_delay_reports WHERE engineer_id = ? GROUP BY severity");
    $delayStmt->execute([$engineerId]);
    // Same per-report penalty table as ContractorScoring's delay component,
    // just weighed against a 15-pt max here instead of ContractorScoring's 30.
    $delayPenaltyPerReport = ['low' => 2, 'medium' => 4, 'high' => 7, 'critical' => 12];
    $delayPenalty = 0;
    foreach ($delayStmt->fetchAll() as $row) {
        $delayPenalty += ($delayPenaltyPerReport[$row['severity']] ?? 4) * (int) $row['total'];
    }
    $delayImpactScore = max(0, $weights['delay_impact'] - $delayPenalty);

    $score = $qualityScore + $responsivenessScore + $activityScore + $delayImpactScore;

    return [
        'score' => (int) round(max(0, min(100, $score))),
        'components' => [
            'quality' => ['weight' => $weights['quality'], 'earned' => round($qualityScore, 1)],
            'responsiveness' => ['weight' => $weights['responsiveness'], 'earned' => round($responsivenessScore, 1)],
            'activity' => ['weight' => $weights['activity'], 'earned' => round($activityScore, 1)],
            'delay_impact' => ['weight' => $weights['delay_impact'], 'earned' => round($delayImpactScore, 1)],
        ],
    ];
}
