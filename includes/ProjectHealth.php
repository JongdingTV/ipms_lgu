<?php
/**
 * Project Health Score — a single canonical 0-100 score + Healthy/Warning/
 * Critical status per project, computed fresh on every read (never stored),
 * so it's always in sync with whatever changed the project. Used by:
 *   - api/projects.php (Project Details, Project Cards/list)
 *   - api/dashboard.php (Command Center's health-colored GIS map, and the
 *     Reports page's health overview)
 *
 * Six weighted factors, summing to 100:
 *   Timeline Progress (25) — actual progress vs. schedule-expected progress.
 *   Budget Consumption (20) — spend rate vs. work done (penalizes spending
 *     ahead of progress, not spend alone).
 *   Delay Status (20) — 'delayed'/'on_hold' status.
 *   Inspection Results (15) — most recent engineer inspection recommendation.
 *   Citizen Complaints (10) — open/in_progress feedback tied to the project.
 *   AI Risk Analysis (10) — flagged expenses + contractor performance score,
 *     the same two signals hope/api/portal.php's hopeProjectRiskSummary()
 *     already uses (delay is scored separately above, so it's excluded here
 *     to avoid double-counting).
 *
 * Advisory/rule-based, not a real ML model — same framing as
 * hopeProjectRiskSummary() and api/dashboard.php's ai_recommendations.
 */

/** Pure function: raw project data in, {score, status, breakdown} out. */
function projectHealthComputeScore(array $row): array
{
    $status = (string) ($row['status'] ?? '');
    $progress = (float) ($row['progress'] ?? 0);
    $budget = (float) ($row['budget'] ?? 0);
    $spent = (float) ($row['total_spent'] ?? 0);
    $flaggedCount = (int) ($row['flagged_count'] ?? 0);
    $performanceScore = $row['performance_score'] !== null ? (int) $row['performance_score'] : null;
    $recommendation = $row['latest_inspection_recommendation'] ?? null;
    $complaintCount = (int) ($row['complaint_count'] ?? 0);

    $breakdown = [];

    // Timeline Progress (25 pts) — how actual progress compares to how much
    // of the project's own start-to-end window has already elapsed.
    $expectedProgress = 100.0;
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        $start = strtotime((string) $row['start_date']);
        $end = strtotime((string) $row['end_date']);
        if ($start !== false && $end !== false && $end > $start) {
            $expectedProgress = max(0.0, min(100.0, ((time() - $start) / ($end - $start)) * 100));
        }
    }
    $timelineScore = $expectedProgress > 0
        ? (int) round(25 * min(1, $progress / $expectedProgress))
        : 25;
    $breakdown['timeline_progress'] = $timelineScore;

    // Budget Consumption (20 pts) — penalize spend running ahead of progress,
    // not spend on its own (a project that's 80% spent at 80% progress is
    // fine; 80% spent at 30% progress is the actual warning sign).
    $spentPct = $budget > 0 ? ($spent / $budget) * 100 : 0.0;
    $overrun = max(0.0, $spentPct - max($progress, 1.0));
    $budgetScore = (int) min(20, max(0, round(20 - ($overrun / 5))));
    $breakdown['budget_consumption'] = $budgetScore;

    // Delay Status (20 pts)
    if ($status === 'delayed') {
        $delayScore = 0;
    } elseif ($status === 'on_hold') {
        $delayScore = 10;
    } else {
        $delayScore = 20;
    }
    $breakdown['delay_status'] = $delayScore;

    // Inspection Results (15 pts) — most recent engineer inspection, if any.
    if ($recommendation === 'approved') {
        $inspectionScore = 15;
    } elseif ($recommendation === 'for_reinspection') {
        $inspectionScore = 7;
    } elseif ($recommendation === 'needs_correction') {
        $inspectionScore = 3;
    } else {
        $inspectionScore = 10; // no inspection recorded yet — neutral, not penalized
    }
    $breakdown['inspection_results'] = $inspectionScore;

    // Citizen Complaints (10 pts) — open/in_progress feedback on this project.
    $complaintScore = max(0, 10 - ($complaintCount * 3));
    $breakdown['citizen_complaints'] = $complaintScore;

    // AI Risk Analysis (10 pts) — flagged expenses + contractor performance,
    // same signals GIS health/HOPE's risk summary use (delay excluded here).
    $riskFactors = 0;
    if ($flaggedCount > 0) {
        $riskFactors++;
    }
    if ($performanceScore !== null && $performanceScore < 70) {
        $riskFactors++;
    }
    if ($riskFactors >= 2) {
        $aiRiskScore = 0;
    } elseif ($riskFactors === 1) {
        $aiRiskScore = 5;
    } else {
        $aiRiskScore = 10;
    }
    $breakdown['ai_risk_analysis'] = $aiRiskScore;

    $total = $timelineScore + $budgetScore + $delayScore + $inspectionScore + $complaintScore + $aiRiskScore;
    $total = (int) max(0, min(100, $total));

    if ($total >= 75) {
        $healthStatus = 'healthy';
    } elseif ($total >= 50) {
        $healthStatus = 'warning';
    } else {
        $healthStatus = 'critical';
    }

    return [
        'score' => $total,
        'status' => $healthStatus,
        'breakdown' => $breakdown,
    ];
}

/**
 * Bulk-fetches the raw inputs projectHealthComputeScore() needs, for a given
 * set of project IDs (or every non-draft project when $projectIds is empty).
 * One query regardless of how many projects — avoids an N+1 per project.
 */
function projectHealthRawRows(PDO $db, array $projectIds = []): array
{
    $where = "p.status <> 'draft'";
    $params = [];
    if ($projectIds !== []) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $where = "p.id IN ($placeholders)";
        $params = $projectIds;
    }

    $stmt = $db->prepare("
        SELECT p.id, p.project_code, p.name, p.status, p.progress, p.start_date, p.end_date, p.budget,
               COALESCE(exp.total_spent, 0) AS total_spent,
               COALESCE(exp.flagged_count, 0) AS flagged_count,
               c.performance_score,
               li.recommendation AS latest_inspection_recommendation,
               COALESCE(fb.complaint_count, 0) AS complaint_count
        FROM projects p
        LEFT JOIN (
            SELECT project_id, SUM(amount) AS total_spent, SUM(flagged) AS flagged_count
            FROM expenses GROUP BY project_id
        ) exp ON exp.project_id = p.id
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN (
            SELECT i1.project_id, i1.recommendation
            FROM inspections i1
            INNER JOIN (
                SELECT project_id, MAX(created_at) AS max_created FROM inspections WHERE status = 'submitted' GROUP BY project_id
            ) i2 ON i2.project_id = i1.project_id AND i2.max_created = i1.created_at
            WHERE i1.status = 'submitted'
        ) li ON li.project_id = p.id
        LEFT JOIN (
            SELECT project_id, COUNT(*) AS complaint_count
            FROM feedback WHERE status IN ('open','in_progress') GROUP BY project_id
        ) fb ON fb.project_id = p.id
        WHERE $where
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }
    return $rows;
}

/** [project_id => {score, status, breakdown}] for a given set of IDs. */
function projectHealthScoresForIds(PDO $db, array $projectIds): array
{
    if ($projectIds === []) {
        return [];
    }

    $rows = projectHealthRawRows($db, $projectIds);
    $scores = [];
    foreach ($rows as $id => $row) {
        $scores[$id] = projectHealthComputeScore($row);
    }
    return $scores;
}

/** Single-project convenience wrapper — {score, status, breakdown} or a neutral default if not found. */
function projectHealthScoreForOne(PDO $db, int $projectId): array
{
    $scores = projectHealthScoresForIds($db, [$projectId]);
    return $scores[$projectId] ?? ['score' => 0, 'status' => 'critical', 'breakdown' => []];
}

/**
 * Every non-draft project's health, worst-first — feeds the Reports page's
 * health overview. $limit = 0 means no cap.
 */
function projectHealthAllScores(PDO $db, int $limit = 20): array
{
    $rows = projectHealthRawRows($db);
    $out = [];
    foreach ($rows as $id => $row) {
        $result = projectHealthComputeScore($row);
        $out[] = [
            'id' => $id,
            'project_code' => $row['project_code'],
            'name' => $row['name'],
            'status' => $row['status'],
            'health_score' => $result['score'],
            'health_status' => $result['status'],
        ];
    }

    usort($out, fn($a, $b) => $a['health_score'] <=> $b['health_score']);

    return $limit > 0 ? array_slice($out, 0, $limit) : $out;
}
