<?php
// ============================================================
// hope/api/portal.php — HOPE (Head of Procuring Entity) executive portal.
// Project approve/return/reject ('decide') still lives in the shared
// api/projects.php, one source of truth for that decision. This file owns
// HOPE's other statutory authority — Contract Award Approval — plus the
// executive dashboard/reports aggregates.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../includes/ContractorScoring.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Notifications.php';

apiHeaders();
// super_admin keeps read-only oversight of HOPE's queues (same pattern as
// BAC's contractor-application list); only 'hope' itself can act — enforced
// per-action below, mirroring bac/api/portal.php's review_contractor_application.
requireAnyRole(['hope', 'super_admin']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);
projectWorkflowEnsureRoleConnectionTables($db);
contractorRefreshPerformanceScores($db);

$user = currentUser();
$actorId = (int) ($user['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';

/**
 * Real, rule-based, advisory-only risk signal for one project — reused by
 * both the Project Approval detail view and the Contract Award Approval
 * detail view. Same High/Medium/Low derivation already used elsewhere in
 * this codebase (bac/api/portal.php's bacPortalMapEvaluationRow), computed
 * independently here rather than depending on Admin's/BAC's own endpoints.
 */
function hopeProjectRiskSummary(PDO $db, int $projectId): array
{
    $stmt = $db->prepare("
        SELECT p.status, p.progress, p.contractor_id, c.name AS contractor_name, c.performance_score,
               (SELECT COUNT(*) FROM expenses e WHERE e.project_id = p.id AND e.flagged = 1) AS flagged_count
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.id = ?
    ");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['risk' => 'unknown', 'summary' => 'Project not found.'];
    }

    $factors = [];
    if ($row['status'] === 'delayed') {
        $factors[] = 'Project is currently flagged as delayed.';
    }
    if ((int) $row['flagged_count'] > 0) {
        $factors[] = (int) $row['flagged_count'] . ' flagged expense(s) recorded against this project.';
    }
    if ($row['contractor_id']) {
        $score = (int) ($row['performance_score'] ?? 0);
        $contractorRisk = $score >= 85 ? 'low' : ($score >= 70 ? 'medium' : 'high');
        if ($contractorRisk !== 'low') {
            $factors[] = $row['contractor_name'] . "'s performance score is {$score}/100 ({$contractorRisk} risk).";
        }
    }

    $riskLevel = count($factors) >= 2 ? 'high' : (count($factors) === 1 ? 'medium' : 'low');

    return [
        'risk' => $riskLevel,
        'summary' => $factors !== [] ? implode(' ', $factors) : 'No risk indicators detected — on track.',
    ];
}

if ($method === 'GET') {
    if ($action === 'summary') {
        // HOPE's project-approval queue is 'endorsed' projects — those that
        // already cleared Engineering Review.
        $pending = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'endorsed'")->fetchColumn();
        $pendingAwards = (int) $db->query("SELECT COUNT(*) FROM bac_award_recommendations WHERE status = 'sent_to_admin'")->fetchColumn();
        $approvedThisMonth = (int) $db->query("
            SELECT COUNT(*) FROM projects
            WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover')
              AND approved_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ")->fetchColumn();
        $returned = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'returned'")->fetchColumn();
        $rejected = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'cancelled'")->fetchColumn();
        $totalBudget = (float) $db->query("
            SELECT COALESCE(SUM(budget), 0) FROM projects WHERE status NOT IN ('draft','returned','cancelled')
        ")->fetchColumn();
        $delayed = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'delayed'")->fetchColumn();
        $nearCompletion = (int) $db->query("
            SELECT COUNT(*) FROM projects
            WHERE progress >= 90 AND status NOT IN ('completed','turnover','cancelled','draft','returned')
        ")->fetchColumn();

        $pendingStmt = $db->query("
            SELECT p.id, p.project_code, p.name, p.location, p.budget, p.created_at, u.full_name AS created_by_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.created_by
            WHERE p.status = 'endorsed'
            ORDER BY p.created_at ASC
            LIMIT 5
        ");

        // High-risk projects, advisory only: a project earns a risk factor
        // for being delayed, having any flagged expense, or having an
        // assigned contractor with a sub-70 performance score. 2+ factors
        // = high risk, surfaced on the executive dashboard.
        $riskRows = $db->query("
            SELECT p.id, p.project_code, p.name, p.status,
                   COALESCE((SELECT COUNT(*) FROM expenses e WHERE e.project_id = p.id AND e.flagged = 1), 0) AS flagged_count,
                   c.performance_score
            FROM projects p
            LEFT JOIN contractors c ON c.id = p.contractor_id
            WHERE p.status IN ('active','delayed','on_hold','bidding','awarded','assigned')
        ")->fetchAll();

        $highRisk = [];
        foreach ($riskRows as $row) {
            $factors = 0;
            if ($row['status'] === 'delayed') $factors++;
            if ((int) $row['flagged_count'] > 0) $factors++;
            if ($row['performance_score'] !== null && (int) $row['performance_score'] < 70) $factors++;
            if ($factors >= 2) {
                $highRisk[] = [
                    'id' => (int) $row['id'],
                    'project_code' => $row['project_code'],
                    'name' => $row['name'],
                    'status' => $row['status'],
                    'risk_factors' => $factors,
                ];
            }
        }
        usort($highRisk, static fn (array $a, array $b): int => $b['risk_factors'] <=> $a['risk_factors']);
        $highRisk = array_slice($highRisk, 0, 5);

        // Dashboard charts: full portfolio by status, and HOPE decision
        // volume over the last six months (same log actions the Decision
        // History module lists).
        $statusMix = $db->query("
            SELECT status, COUNT(*) AS total FROM projects GROUP BY status
        ")->fetchAll();

        $monthlyDecisions = $db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, action, COUNT(*) AS total
            FROM bac_procurement_logs
            WHERE action IN ('Project approved', 'Project returned', 'Project rejected')
              AND created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
            GROUP BY ym, action
        ")->fetchAll();

        respond([
            'stats' => [
                'pending_project_approvals' => $pending,
                'pending_award_approvals' => $pendingAwards,
                'approved_this_month' => $approvedThisMonth,
                'returned' => $returned,
                'rejected' => $rejected,
                'total_budget' => $totalBudget,
                'delayed' => $delayed,
                'near_completion' => $nearCompletion,
            ],
            'pending_preview' => $pendingStmt->fetchAll(),
            'high_risk_projects' => $highRisk,
            'status_mix' => $statusMix,
            'monthly_decisions' => $monthlyDecisions,
        ]);
    }

    if ($action === 'project_risk') {
        $projectId = (int) ($_GET['id'] ?? 0);
        if ($projectId <= 0) {
            respond(['error' => 'Project ID is required.'], 422);
        }
        respond(hopeProjectRiskSummary($db, $projectId));
    }

    if ($action === 'list_award_recommendations') {
        $stmt = $db->query("
            SELECT r.id, r.project_id, r.bid_submission_id, r.award_amount, r.basis, r.created_at,
                   p.project_code, p.name AS project_name, p.location, p.budget,
                   c.name AS contractor_name, c.performance_score
            FROM bac_award_recommendations r
            INNER JOIN projects p ON p.id = r.project_id
            INNER JOIN contractors c ON c.id = r.contractor_id
            WHERE r.status = 'sent_to_admin'
            ORDER BY r.created_at ASC
        ");
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'award_recommendation_detail') {
        $recId = (int) ($_GET['id'] ?? 0);
        if ($recId <= 0) {
            respond(['error' => 'Recommendation ID is required.'], 422);
        }

        $stmt = $db->prepare("
            SELECT r.*, p.project_code, p.name AS project_name, p.location, p.budget, p.description,
                   p.start_date, p.end_date,
                   c.id AS contractor_profile_id, c.name AS contractor_name, c.contact_person, c.email, c.phone,
                   c.pcab_license_no, c.pcab_classification, c.performance_score, c.credibility_score, c.is_blacklisted
            FROM bac_award_recommendations r
            INNER JOIN projects p ON p.id = r.project_id
            INNER JOIN contractors c ON c.id = r.contractor_id
            WHERE r.id = ?
        ");
        $stmt->execute([$recId]);
        $rec = $stmt->fetch();
        if (!$rec) {
            respond(['error' => 'Recommendation not found.'], 404);
        }

        $projectId = (int) $rec['project_id'];

        // Every bid on this project, for the bid-evaluation comparison table.
        $bidsStmt = $db->prepare("
            SELECT b.id, b.contractor_id, b.bid_amount, b.technical_score, b.delivery_days, b.status, b.source, b.submitted_at,
                   c.name AS contractor_name
            FROM bac_bid_submissions b
            INNER JOIN contractors c ON c.id = b.contractor_id
            WHERE b.project_id = ?
            ORDER BY b.bid_amount ASC
        ");
        $bidsStmt->execute([$projectId]);

        // Procurement documents BAC attached to this specific bid (Abstract of
        // Bids, Notice of Award draft, Board Resolution, etc.).
        $docsStmt = $db->prepare("
            SELECT id, document_type, title, original_name, file_path, status, created_at
            FROM supporting_documents
            WHERE owner_type = 'bac_bid' AND owner_id = ?
            ORDER BY created_at DESC
        ");
        $docsStmt->execute([(int) $rec['bid_submission_id']]);

        respond([
            'recommendation' => $rec,
            'bids' => $bidsStmt->fetchAll(),
            'documents' => $docsStmt->fetchAll(),
            'risk' => hopeProjectRiskSummary($db, $projectId),
        ]);
    }

    if ($action === 'decision_history') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 15)));

        // Reads the durable, append-only procurement log rather than the
        // upsertable bac_award_recommendations row — a re-recommend cycle
        // overwrites that row's own history, but every decision HOPE has
        // ever made is still preserved here via projectWorkflowLog().
        $actions = ['Project approved', 'Project returned', 'Project rejected', 'Contract award approved', 'Contract award returned', 'Contract award rejected'];
        $placeholders = implode(',', array_fill(0, count($actions), '?'));

        // Optional search over action text, details, and project code/name.
        $search = trim((string) ($_GET['search'] ?? ''));
        $searchSql = '';
        $params = $actions;
        if ($search !== '') {
            $searchSql = " AND (l.action LIKE ? OR l.details LIKE ? OR p.project_code LIKE ? OR p.name LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($actions, [$like, $like, $like, $like]);
        }

        $select = "
            SELECT l.id, l.created_at, l.action, l.details, p.project_code, p.name AS project_name, u.full_name AS actor_name
            FROM bac_procurement_logs l
            LEFT JOIN projects p ON p.id = l.project_id
            LEFT JOIN users u ON u.id = l.actor_id
            WHERE l.action IN ($placeholders)$searchSql
            ORDER BY l.created_at DESC, l.id DESC
        ";
        $count = "
            SELECT COUNT(*)
            FROM bac_procurement_logs l
            LEFT JOIN projects p ON p.id = l.project_id
            WHERE l.action IN ($placeholders)$searchSql
        ";

        respond(paginate($db, $select, $count, $params, $page, $perPage));
    }

    if ($action === 'budget_summary') {
        $totalBudget = (float) $db->query("
            SELECT COALESCE(SUM(budget), 0) FROM projects WHERE status NOT IN ('draft','returned','cancelled')
        ")->fetchColumn();
        $totalSpent = (float) $db->query("
            SELECT COALESCE(SUM(e.amount), 0)
            FROM expenses e INNER JOIN projects p ON p.id = e.project_id
            WHERE p.status NOT IN ('draft','returned','cancelled')
        ")->fetchColumn();

        $byProject = $db->query("
            SELECT p.id, p.project_code, p.name, p.status, p.budget,
                   COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS spent
            FROM projects p
            WHERE p.status NOT IN ('draft','returned','cancelled')
            ORDER BY p.budget DESC
        ")->fetchAll();

        respond([
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'utilization_pct' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
            'projects' => $byProject,
        ]);
    }

    if ($action === 'procurement_summary') {
        respond([
            'open_bids' => (int) $db->query("SELECT COUNT(*) FROM bac_bid_announcements WHERE status IN ('posted','pre_bid','open')")->fetchColumn(),
            'for_evaluation' => (int) $db->query("SELECT COUNT(*) FROM bac_bid_submissions WHERE status IN ('submitted','for_review')")->fetchColumn(),
            'pending_award_approvals' => (int) $db->query("SELECT COUNT(*) FROM bac_award_recommendations WHERE status = 'sent_to_admin'")->fetchColumn(),
            'awarded_this_month' => (int) $db->query("
                SELECT COUNT(*) FROM bac_award_recommendations
                WHERE status = 'approved' AND decided_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ")->fetchColumn(),
            'returned_recommendations' => (int) $db->query("SELECT COUNT(*) FROM bac_award_recommendations WHERE status = 'returned'")->fetchColumn(),
            'rejected_recommendations' => (int) $db->query("SELECT COUNT(*) FROM bac_award_recommendations WHERE status = 'rejected'")->fetchColumn(),
        ]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

if ($action === 'decide_award') {
    // Re-narrow within the file's broader ['hope','super_admin'] read gate —
    // same pattern as bac/api/portal.php's review_contractor_application.
    // super_admin keeps oversight visibility via the GET actions above but
    // has no authority to decide an award.
    requireAnyRole(['hope']);

    $body = requestBody();
    $validated = Validator::make($body, [
        'recommendation_id' => 'required|integer',
        'decision' => 'required|in:approve,return,reject',
        'remarks' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $recId = (int) $validated['recommendation_id'];
    $decision = (string) $validated['decision'];
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    if ($decision !== 'approve' && $remarks === '') {
        respond(['error' => 'A remark is required to return or reject a contract award recommendation.'], 422);
    }

    $stmt = $db->prepare("
        SELECT r.*, p.project_code, p.name AS project_name, p.start_date, p.end_date,
               c.name AS contractor_name, c.user_id AS contractor_user_id
        FROM bac_award_recommendations r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN contractors c ON c.id = r.contractor_id
        WHERE r.id = ?
    ");
    $stmt->execute([$recId]);
    $rec = $stmt->fetch();
    if (!$rec) {
        respond(['error' => 'Recommendation not found.'], 404);
    }
    if ($rec['status'] !== 'sent_to_admin') {
        respond(['error' => 'This recommendation has already been decided.'], 422);
    }

    $projectId = (int) $rec['project_id'];
    $contractorId = (int) $rec['contractor_id'];
    $bidId = (int) $rec['bid_submission_id'];
    $pastTense = ['approve' => 'approved', 'return' => 'returned', 'reject' => 'rejected'];

    if ($decision === 'approve') {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE projects SET contractor_id = ?, status = 'awarded' WHERE id = ?")
                ->execute([$contractorId, $projectId]);

            $contractNo = projectWorkflowContractNo(['project_code' => $rec['project_code'], 'id' => $projectId]);
            $contract = $db->prepare("
                INSERT INTO contracts
                    (project_id, bid_submission_id, contractor_id, contract_no, contract_amount, contract_start_date, contract_end_date, status, approved_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
                ON DUPLICATE KEY UPDATE
                    bid_submission_id = VALUES(bid_submission_id),
                    contractor_id = VALUES(contractor_id),
                    contract_amount = VALUES(contract_amount),
                    contract_start_date = VALUES(contract_start_date),
                    contract_end_date = VALUES(contract_end_date),
                    status = 'active',
                    approved_by = VALUES(approved_by),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $contract->execute([
                $projectId,
                $bidId,
                $contractorId,
                $contractNo,
                (float) $rec['award_amount'],
                $rec['start_date'] ?? null,
                $rec['end_date'] ?? null,
                $actorId ?: null,
            ]);

            $db->prepare("UPDATE bac_award_recommendations SET status = 'approved', hope_remarks = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")
                ->execute([$remarks !== '' ? $remarks : null, $actorId ?: null, $recId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            respond(['error' => 'Unable to approve the contract award.'], 500);
        }

        notifyUser(
            (int) ($rec['contractor_user_id'] ?: 0),
            'info',
            'Bid awarded',
            'Your bid for ' . $rec['project_name'] . ' has been approved by HOPE.'
        );
    } else {
        $newBidStatus = $decision === 'return' ? 'for_review' : 'rejected';

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE bac_bid_submissions SET status = ? WHERE id = ?")
                ->execute([$newBidStatus, $bidId]);
            $db->prepare("UPDATE bac_award_recommendations SET status = ?, hope_remarks = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")
                ->execute([$pastTense[$decision], $remarks, $actorId ?: null, $recId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            respond(['error' => 'Unable to process the decision.'], 500);
        }
    }

    $details = $rec['contractor_name'] . "'s award recommendation for " . $rec['project_name'] . ' was ' . $pastTense[$decision] . ($remarks !== '' ? ' — ' . $remarks : '') . '.';
    projectWorkflowLog($db, 'Contract award ' . $pastTense[$decision], $projectId, $details, $actorId ?: null);
    logActivity($actorId, 'contract_award_' . $pastTense[$decision], $details);

    if ($decision !== 'approve' && !empty($rec['recommended_by'])) {
        notifyUser((int) $rec['recommended_by'], 'warning', 'Contract award ' . $pastTense[$decision], $details);
    }

    respond(['success' => true, 'status' => $pastTense[$decision]]);
}

respond(['error' => 'Unknown action.'], 404);
