<?php
// ============================================================
// bac/api/portal.php - live BAC procurement workflow API
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/workflow.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';
$user = currentUser();
$actorId = (int) ($user['user_id'] ?? $_SESSION['user_id'] ?? 0);

function bacPortalMoneyVariance(float $bidAmount, float $budget): float
{
    return $budget > 0 ? round((($bidAmount - $budget) / $budget) * 100, 1) : 0.0;
}

function bacPortalReferenceNo(PDO $db, int $projectId = 0): string
{
    $year = date('Y');
    $sequence = $projectId > 0
        ? $projectId
        : ((int) $db->query("SELECT COUNT(*) FROM bac_bid_announcements")->fetchColumn() + 1);

    return 'BAC-' . $year . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function bacPortalProject(PDO $db, int $projectId): ?array
{
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    return $project ?: null;
}

function bacPortalSummary(PDO $db): array
{
    $approved = $db->query("
        SELECT p.*, c.name AS contractor_name
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.status = 'approved'
        ORDER BY p.updated_at DESC, p.id DESC
    ")->fetchAll();

    $announcements = $db->query("
        SELECT a.*, p.project_code, p.name AS project_name, p.location,
               p.budget, p.status AS project_status
        FROM bac_bid_announcements a
        INNER JOIN projects p ON p.id = a.project_id
        ORDER BY a.published_at DESC, a.id DESC
    ")->fetchAll();

    $contractors = $db->query("
        SELECT c.*,
               COUNT(p.id) AS total_projects,
               COALESCE(AVG(p.progress), 0) AS average_project_progress
        FROM contractors c
        LEFT JOIN projects p ON p.contractor_id = c.id
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.performance_score DESC, c.name ASC
    ")->fetchAll();

    $evaluations = array_map(static function (array $row): array {
        $score = (int) ($row['performance_score'] ?? 0);
        return [
            'contractor_id' => (int) $row['id'],
            'contractor' => $row['name'],
            'eligibility' => $score >= 70 ? 'qualified' : 'needs_review',
            'performance' => $score,
            'compliance' => $score >= 85 ? 'complete' : ($score >= 70 ? 'under_review' : 'pending'),
            'risk' => $score >= 85 ? 'low' : ($score >= 70 ? 'medium' : 'high'),
            'total_projects' => (int) ($row['total_projects'] ?? 0),
        ];
    }, $contractors);

    $bidRows = $db->query("
        SELECT b.*, p.project_code, p.name AS project_name, p.budget,
               c.name AS contractor_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        INNER JOIN contractors c ON c.id = b.contractor_id
        ORDER BY b.updated_at DESC, b.id DESC
    ")->fetchAll();

    $bids = array_map(static function (array $row): array {
        $amount = (float) $row['bid_amount'];
        $budget = (float) $row['budget'];
        return [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'project_code' => $row['project_code'],
            'project' => $row['project_name'],
            'contractor_id' => (int) $row['contractor_id'],
            'contractor' => $row['contractor_name'],
            'bid' => $amount,
            'budget' => $budget,
            'variance' => bacPortalMoneyVariance($amount, $budget),
            'technical' => (int) $row['technical_score'],
            'deliveryDays' => (int) ($row['delivery_days'] ?? 0),
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
            'remarks' => $row['remarks'],
        ];
    }, $bidRows);

    $recommendations = $db->query("
        SELECT r.*, p.project_code, p.name AS project_name,
               c.name AS awardee, ct.contract_no, ct.status AS contract_status
        FROM bac_award_recommendations r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN contractors c ON c.id = r.contractor_id
        LEFT JOIN contracts ct ON ct.project_id = r.project_id
        ORDER BY r.updated_at DESC, r.id DESC
    ")->fetchAll();

    $logs = $db->query("
        SELECT l.*, p.project_code, p.name AS project_name, u.full_name AS actor_name
        FROM bac_procurement_logs l
        LEFT JOIN projects p ON p.id = l.project_id
        LEFT JOIN users u ON u.id = l.actor_id
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 25
    ")->fetchAll();

    return [
        'approved_projects' => $approved,
        'announcements' => $announcements,
        'evaluations' => $evaluations,
        'contractors' => $contractors,
        'bids' => $bids,
        'recommendations' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'project_id' => (int) $row['project_id'],
                'bid_submission_id' => (int) ($row['bid_submission_id'] ?? 0),
                'project_code' => $row['project_code'],
                'project' => $row['project_name'],
                'contractor_id' => (int) $row['contractor_id'],
                'awardee' => $row['awardee'],
                'amount' => (float) $row['award_amount'],
                'basis' => $row['basis'],
                'status' => $row['status'],
                'contract_no' => $row['contract_no'],
                'contract_status' => $row['contract_status'],
                'created_at' => $row['created_at'],
            ];
        }, $recommendations),
        'logs' => array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'date' => $row['created_at'],
                'project' => $row['project_name'],
                'project_code' => $row['project_code'],
                'title' => $row['action'],
                'detail' => $row['details'],
                'actor' => $row['actor_name'],
                'status' => 'complete',
            ];
        }, $logs),
        'stats' => [
            'open_bids' => count(array_filter($announcements, static fn (array $row): bool => in_array($row['status'], ['posted', 'pre_bid', 'open'], true))),
            'for_evaluation' => count(array_filter($bids, static fn (array $row): bool => in_array($row['status'], ['submitted', 'for_review'], true))),
            'recommendations' => count($recommendations),
            'logs' => count($logs),
            'approved_waiting' => count($approved),
        ],
    ];
}

if ($method === 'GET') {
    if ($action === 'summary') {
        respond(bacPortalSummary($db));
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

$body = requestBody();

if ($action === 'publish') {
    $projectId = (int) ($body['project_id'] ?? 0);
    $deadline = trim((string) ($body['deadline'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));

    if ($projectId <= 0) {
        respond(['error' => 'Project is required.'], 422);
    }

    $project = bacPortalProject($db, $projectId);
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if (!in_array($project['status'], ['approved', 'bidding'], true)) {
        respond(['error' => 'Only approved projects can be posted for bidding.'], 422);
    }

    $referenceNo = $body['reference_no'] ?? bacPortalReferenceNo($db, $projectId);
    $publishedAt = trim((string) ($body['published_at'] ?? date('Y-m-d')));

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO bac_bid_announcements
                (project_id, reference_no, published_at, deadline, status, notes, created_by)
            VALUES (?, ?, ?, ?, 'posted', ?, ?)
            ON DUPLICATE KEY UPDATE
                deadline = VALUES(deadline),
                status = 'posted',
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $projectId,
            $referenceNo,
            $publishedAt,
            $deadline !== '' ? $deadline : null,
            $notes !== '' ? $notes : null,
            $actorId ?: null,
        ]);

        $db->prepare("UPDATE projects SET status = 'bidding' WHERE id = ?")
            ->execute([$projectId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to publish bidding notice.'], 500);
    }

    projectWorkflowLog($db, 'Bidding notice posted', $projectId, $project['name'] . ' was posted for BAC bidding.', $actorId ?: null);
    respond(['success' => true], 201);
}

if ($action === 'bid') {
    $projectId = (int) ($body['project_id'] ?? 0);
    $contractorId = (int) ($body['contractor_id'] ?? 0);
    $amount = (float) ($body['bid_amount'] ?? 0);
    $technicalScore = max(0, min(100, (int) ($body['technical_score'] ?? 0)));
    $deliveryDays = (int) ($body['delivery_days'] ?? 0);
    $remarks = trim((string) ($body['remarks'] ?? ''));

    if ($projectId <= 0 || $contractorId <= 0 || $amount <= 0) {
        respond(['error' => 'Project, contractor, and bid amount are required.'], 422);
    }

    $announcement = $db->prepare("SELECT id FROM bac_bid_announcements WHERE project_id = ?");
    $announcement->execute([$projectId]);
    if (!$announcement->fetchColumn()) {
        respond(['error' => 'Publish a bidding notice before recording bids.'], 422);
    }

    $contractor = $db->prepare("SELECT id, name FROM contractors WHERE id = ? AND status = 'active'");
    $contractor->execute([$contractorId]);
    $contractorRow = $contractor->fetch();
    if (!$contractorRow) {
        respond(['error' => 'Active contractor not found.'], 404);
    }

    $stmt = $db->prepare("
        INSERT INTO bac_bid_submissions
            (project_id, contractor_id, bid_amount, technical_score, delivery_days, status, submitted_at, remarks)
        VALUES (?, ?, ?, ?, ?, 'submitted', ?, ?)
    ");
    $stmt->execute([
        $projectId,
        $contractorId,
        $amount,
        $technicalScore,
        $deliveryDays > 0 ? $deliveryDays : null,
        $body['submitted_at'] ?? date('Y-m-d'),
        $remarks !== '' ? $remarks : null,
    ]);

    projectWorkflowLog($db, 'Contractor bid recorded', $projectId, $contractorRow['name'] . ' submitted a BAC bid.', $actorId ?: null);
    respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
}

if ($action === 'recommend') {
    $bidId = (int) ($body['bid_id'] ?? 0);
    $basis = trim((string) ($body['basis'] ?? 'Lowest calculated responsive bid with acceptable technical score.'));

    if ($bidId <= 0) {
        respond(['error' => 'Bid is required.'], 422);
    }

    $bidStmt = $db->prepare("
        SELECT b.*, p.project_code, p.name AS project_name, p.start_date, p.end_date, c.name AS contractor_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        INNER JOIN contractors c ON c.id = b.contractor_id
        WHERE b.id = ?
        LIMIT 1
    ");
    $bidStmt->execute([$bidId]);
    $bid = $bidStmt->fetch();
    if (!$bid) {
        respond(['error' => 'Bid not found.'], 404);
    }

    $projectId = (int) $bid['project_id'];
    $contractorId = (int) $bid['contractor_id'];

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE bac_bid_submissions SET status = 'for_review' WHERE project_id = ?")
            ->execute([$projectId]);
        $db->prepare("UPDATE bac_bid_submissions SET status = 'recommended' WHERE id = ?")
            ->execute([$bidId]);

        $stmt = $db->prepare("
            INSERT INTO bac_award_recommendations
                (project_id, bid_submission_id, contractor_id, award_amount, basis, status, recommended_by)
            VALUES (?, ?, ?, ?, ?, 'sent_to_admin', ?)
            ON DUPLICATE KEY UPDATE
                bid_submission_id = VALUES(bid_submission_id),
                contractor_id = VALUES(contractor_id),
                award_amount = VALUES(award_amount),
                basis = VALUES(basis),
                status = 'sent_to_admin',
                recommended_by = VALUES(recommended_by),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $projectId,
            $bidId,
            $contractorId,
            (float) $bid['bid_amount'],
            $basis !== '' ? $basis : null,
            $actorId ?: null,
        ]);

        $db->prepare("UPDATE projects SET contractor_id = ?, status = 'awarded' WHERE id = ?")
            ->execute([$contractorId, $projectId]);

        $contractNo = projectWorkflowContractNo($bid);
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
            (float) $bid['bid_amount'],
            $bid['start_date'] ?? null,
            $bid['end_date'] ?? null,
            $actorId ?: null,
        ]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to send award recommendation.'], 500);
    }

    projectWorkflowLog($db, 'Award recommendation sent', $projectId, $bid['contractor_name'] . ' was recommended for contractor assignment.', $actorId ?: null);
    respond(['success' => true], 201);
}

respond(['error' => 'Unknown action.'], 404);
