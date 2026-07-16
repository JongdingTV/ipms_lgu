<?php
/**
 * Recomputes contractors.performance_score from real project outcomes instead
 * of the static, admin-typed number it used to be. Weighting is modeled after
 * DPWH's Constructors' Performance Evaluation System (CPES): time/completion,
 * workmanship (proxied by field issue reports), and resources/financial
 * discipline (proxied by flagged expenses).
 */

function contractorPerformanceScoreWeights(): array
{
    return [
        'completion' => 35,
        'delay' => 30,
        'issues' => 20,
        'financial' => 15,
    ];
}

function contractorCalculatePerformanceScore(PDO $db, int $contractorId): int
{
    return contractorCalculatePerformanceScoreBreakdown($db, $contractorId)['score'];
}

/**
 * Same calculation as contractorCalculatePerformanceScore(), but returns the
 * four weighted components alongside the final score — used by the
 * Contractor Portal's Performance Rating page so a contractor can see why
 * their score is what it is, not just the final number.
 */
function contractorCalculatePerformanceScoreBreakdown(PDO $db, int $contractorId): array
{
    $weights = contractorPerformanceScoreWeights();

    $projectStmt = $db->prepare("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
        FROM projects
        WHERE contractor_id = ? AND status NOT IN ('draft', 'returned', 'planning', 'approved', 'bidding')
    ");
    $projectStmt->execute([$contractorId]);
    $projectRow = $projectStmt->fetch();
    $totalProjects = (int) ($projectRow['total'] ?? 0);

    if ($totalProjects === 0) {
        // Neutral default: no assignment history yet, neither penalized nor rewarded.
        return [
            'score' => 65,
            'components' => [
                'completion' => ['weight' => $weights['completion'], 'earned' => null],
                'delay' => ['weight' => $weights['delay'], 'earned' => null],
                'issues' => ['weight' => $weights['issues'], 'earned' => null],
                'financial' => ['weight' => $weights['financial'], 'earned' => null],
            ],
        ];
    }

    $completionRate = ((int) $projectRow['completed']) / $totalProjects;
    $completionScore = $completionRate * $weights['completion'];

    $delayStmt = $db->prepare("
        SELECT edr.severity, COUNT(*) AS total
        FROM engineer_delay_reports edr
        INNER JOIN projects p ON p.id = edr.project_id
        WHERE p.contractor_id = ?
        GROUP BY edr.severity
    ");
    $delayStmt->execute([$contractorId]);
    $delayPenaltyPerReport = ['low' => 2, 'medium' => 4, 'high' => 7, 'critical' => 12];
    $delayPenalty = 0;
    foreach ($delayStmt->fetchAll() as $row) {
        $delayPenalty += ($delayPenaltyPerReport[$row['severity']] ?? 4) * (int) $row['total'];
    }
    $delayScore = max(0, $weights['delay'] - $delayPenalty);

    $issueStmt = $db->prepare("
        SELECT eir.priority, COUNT(*) AS total
        FROM engineer_issue_reports eir
        INNER JOIN projects p ON p.id = eir.project_id
        WHERE p.contractor_id = ? AND eir.status != 'closed'
        GROUP BY eir.priority
    ");
    $issueStmt->execute([$contractorId]);
    $issuePenaltyPerReport = ['low' => 1, 'medium' => 2, 'high' => 4, 'urgent' => 8];
    $issuePenalty = 0;
    foreach ($issueStmt->fetchAll() as $row) {
        $issuePenalty += ($issuePenaltyPerReport[$row['priority']] ?? 2) * (int) $row['total'];
    }
    $issueScore = max(0, $weights['issues'] - $issuePenalty);

    $expenseStmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN flagged = 1 THEN 1 ELSE 0 END) AS flagged
        FROM expenses e
        INNER JOIN projects p ON p.id = e.project_id
        WHERE p.contractor_id = ?
    ");
    $expenseStmt->execute([$contractorId]);
    $expenseRow = $expenseStmt->fetch();
    $totalExpenses = (int) ($expenseRow['total'] ?? 0);
    $flaggedRatio = $totalExpenses > 0 ? ((int) $expenseRow['flagged']) / $totalExpenses : 0;
    $financialScore = (1 - $flaggedRatio) * $weights['financial'];

    $score = $completionScore + $delayScore + $issueScore + $financialScore;

    return [
        'score' => (int) round(max(0, min(100, $score))),
        'components' => [
            'completion' => ['weight' => $weights['completion'], 'earned' => round($completionScore, 1)],
            'delay' => ['weight' => $weights['delay'], 'earned' => round($delayScore, 1)],
            'issues' => ['weight' => $weights['issues'], 'earned' => round($issueScore, 1)],
            'financial' => ['weight' => $weights['financial'], 'earned' => round($financialScore, 1)],
        ],
    ];
}

/** Recomputes and persists every contractor's score. Never throws. */
function contractorRefreshPerformanceScores(PDO $db): void
{
    try {
        $ids = $db->query('SELECT id FROM contractors')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $score = contractorCalculatePerformanceScore($db, (int) $id);
            $db->prepare('UPDATE contractors SET performance_score = ? WHERE id = ? AND performance_score != ?')
                ->execute([$score, $id, $score]);
        }
    } catch (Throwable $e) {
    }
}
