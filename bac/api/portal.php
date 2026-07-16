<?php
// ============================================================
// bac/api/portal.php - live BAC procurement workflow API
// Document upload/review lives in the sibling bac/api/documents.php
// (multipart handling is a structurally different shape from this
// file's pure-JSON actions — mirrors the superadmin portal split).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../includes/ContractorScoring.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Notifications.php';
require_once __DIR__ . '/../../includes/OTPManager.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);
contractorRefreshPerformanceScores($db);
contractorsEnsureApplicationSchema($db);

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

function bacPortalMapBidRow(array $row): array
{
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
        'source' => $row['source'] ?? 'bac_recorded',
    ];
}

function bacPortalMapRecommendationRow(array $row): array
{
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
}

function bacPortalMapLogRow(array $row): array
{
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
}

function bacPortalMapEvaluationRow(array $row): array
{
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
}

/** Dashboard-tab payload only: stats + short previews. Full lists live in the list_* actions below. */
function bacPortalSummary(PDO $db): array
{
    $stats = [
        'open_bids' => (int) $db->query("SELECT COUNT(*) FROM bac_bid_announcements WHERE status IN ('posted', 'pre_bid', 'open')")->fetchColumn(),
        'for_evaluation' => (int) $db->query("SELECT COUNT(*) FROM bac_bid_submissions WHERE status IN ('submitted', 'for_review')")->fetchColumn(),
        'recommendations' => (int) $db->query("SELECT COUNT(*) FROM bac_award_recommendations")->fetchColumn(),
        'logs' => (int) $db->query("SELECT COUNT(*) FROM bac_procurement_logs")->fetchColumn(),
        'approved_waiting' => (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'approved'")->fetchColumn(),
    ];

    $announcements = $db->query("
        SELECT a.*, p.project_code, p.name AS project_name, p.location, p.budget, p.status AS project_status
        FROM bac_bid_announcements a INNER JOIN projects p ON p.id = a.project_id
        ORDER BY a.published_at DESC, a.id DESC LIMIT 3
    ")->fetchAll();

    $recommendations = $db->query("
        SELECT r.*, p.project_code, p.name AS project_name, c.name AS awardee, ct.contract_no, ct.status AS contract_status
        FROM bac_award_recommendations r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN contractors c ON c.id = r.contractor_id
        LEFT JOIN contracts ct ON ct.project_id = r.project_id
        ORDER BY r.updated_at DESC, r.id DESC LIMIT 3
    ")->fetchAll();

    $evaluations = $db->query("
        SELECT * FROM contractors WHERE status = 'active' ORDER BY performance_score DESC, name ASC LIMIT 3
    ")->fetchAll();

    $bids = $db->query("
        SELECT b.*, p.project_code, p.name AS project_name, p.budget, c.name AS contractor_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        INNER JOIN contractors c ON c.id = b.contractor_id
        ORDER BY b.updated_at DESC, b.id DESC LIMIT 3
    ")->fetchAll();

    $logs = $db->query("
        SELECT l.*, p.project_code, p.name AS project_name, u.full_name AS actor_name
        FROM bac_procurement_logs l
        LEFT JOIN projects p ON p.id = l.project_id
        LEFT JOIN users u ON u.id = l.actor_id
        ORDER BY l.created_at DESC, l.id DESC LIMIT 3
    ")->fetchAll();

    return [
        'stats' => $stats,
        'announcements_preview' => $announcements,
        'recommendations_preview' => array_map('bacPortalMapRecommendationRow', $recommendations),
        'evaluations_preview' => array_map('bacPortalMapEvaluationRow', $evaluations),
        'bids_preview' => array_map('bacPortalMapBidRow', $bids),
        'logs_preview' => array_map('bacPortalMapLogRow', $logs),
    ];
}

function bacListAnnouncements(PDO $db, int $page, int $perPage, string $search): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(a.reference_no LIKE ? OR p.name LIKE ? OR p.project_code LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $whereSql = implode(' AND ', $where);
    $select = "SELECT a.*, p.project_code, p.name AS project_name, p.location, p.budget, p.status AS project_status
               FROM bac_bid_announcements a INNER JOIN projects p ON p.id = a.project_id
               WHERE $whereSql ORDER BY a.published_at DESC, a.id DESC";
    $count = "SELECT COUNT(*) FROM bac_bid_announcements a INNER JOIN projects p ON p.id = a.project_id WHERE $whereSql";
    return paginate($db, $select, $count, $params, $page, $perPage);
}

function bacListBids(PDO $db, int $page, int $perPage, string $search): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.project_code LIKE ? OR c.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $whereSql = implode(' AND ', $where);
    $select = "SELECT b.*, p.project_code, p.name AS project_name, p.budget, c.name AS contractor_name
               FROM bac_bid_submissions b
               INNER JOIN projects p ON p.id = b.project_id
               INNER JOIN contractors c ON c.id = b.contractor_id
               WHERE $whereSql ORDER BY b.updated_at DESC, b.id DESC";
    $count = "SELECT COUNT(*) FROM bac_bid_submissions b
               INNER JOIN projects p ON p.id = b.project_id
               INNER JOIN contractors c ON c.id = b.contractor_id
               WHERE $whereSql";
    return paginate($db, $select, $count, $params, $page, $perPage);
}

function bacListRecommendations(PDO $db, int $page, int $perPage, string $search): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR c.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }
    $whereSql = implode(' AND ', $where);
    $select = "SELECT r.*, p.project_code, p.name AS project_name, c.name AS awardee, ct.contract_no, ct.status AS contract_status
               FROM bac_award_recommendations r
               INNER JOIN projects p ON p.id = r.project_id
               INNER JOIN contractors c ON c.id = r.contractor_id
               LEFT JOIN contracts ct ON ct.project_id = r.project_id
               WHERE $whereSql ORDER BY r.updated_at DESC, r.id DESC";
    $count = "SELECT COUNT(*) FROM bac_award_recommendations r
               INNER JOIN projects p ON p.id = r.project_id
               INNER JOIN contractors c ON c.id = r.contractor_id
               WHERE $whereSql";
    return paginate($db, $select, $count, $params, $page, $perPage);
}

function bacListLogs(PDO $db, int $page, int $perPage, string $search): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(l.action LIKE ? OR l.details LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }
    $whereSql = implode(' AND ', $where);
    $select = "SELECT l.*, p.project_code, p.name AS project_name, u.full_name AS actor_name
               FROM bac_procurement_logs l
               LEFT JOIN projects p ON p.id = l.project_id
               LEFT JOIN users u ON u.id = l.actor_id
               WHERE $whereSql ORDER BY l.created_at DESC, l.id DESC";
    $count = "SELECT COUNT(*) FROM bac_procurement_logs l WHERE $whereSql";
    return paginate($db, $select, $count, $params, $page, $perPage);
}

/** Picker lists (not paginated — naturally small working-sets, not historical logs). */
function bacListApprovedProjects(PDO $db): array
{
    return $db->query("
        SELECT p.*, c.name AS contractor_name
        FROM projects p LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.status = 'approved'
        ORDER BY p.updated_at DESC, p.id DESC
        LIMIT 100
    ")->fetchAll();
}

function bacListActiveContractors(PDO $db): array
{
    return $db->query("
        SELECT c.*, COUNT(p.id) AS total_projects, COALESCE(AVG(p.progress), 0) AS average_project_progress
        FROM contractors c LEFT JOIN projects p ON p.contractor_id = c.id
        WHERE c.status = 'active' AND c.application_status = 'approved'
        GROUP BY c.id
        ORDER BY c.performance_score DESC, c.name ASC
        LIMIT 200
    ")->fetchAll();
}

/** Bids not yet recommended — feeds the Award Recommendation queue panel. */
function bacListCandidateBids(PDO $db): array
{
    // Excludes both 'recommended' (already in front of HOPE) and 'rejected'
    // (HOPE already turned this one down) — only a live, undecided bid
    // belongs in the recommendation queue.
    return $db->query("
        SELECT b.*, p.project_code, p.name AS project_name, p.budget, c.name AS contractor_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        INNER JOIN contractors c ON c.id = b.contractor_id
        WHERE b.status IN ('submitted', 'for_review')
        ORDER BY b.updated_at DESC, b.id DESC
        LIMIT 10
    ")->fetchAll();
}

if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $search = trim((string) ($_GET['search'] ?? ''));

    if ($action === 'summary') {
        respond(bacPortalSummary($db));
    }

    if ($action === 'list_announcements') {
        respond(bacListAnnouncements($db, $page, $perPage, $search));
    }

    if ($action === 'list_bids') {
        $result = bacListBids($db, $page, $perPage, $search);
        $result['data'] = array_map('bacPortalMapBidRow', $result['data']);
        respond($result);
    }

    if ($action === 'list_recommendations') {
        $result = bacListRecommendations($db, $page, $perPage, $search);
        $result['data'] = array_map('bacPortalMapRecommendationRow', $result['data']);
        respond($result);
    }

    if ($action === 'list_logs') {
        $result = bacListLogs($db, $page, $perPage, $search);
        $result['data'] = array_map('bacPortalMapLogRow', $result['data']);
        respond($result);
    }

    if ($action === 'list_approved_projects') {
        respond(['data' => bacListApprovedProjects($db)]);
    }

    if ($action === 'list_contractors') {
        respond(['data' => bacListActiveContractors($db)]);
    }

    if ($action === 'list_evaluations') {
        respond(['data' => array_map('bacPortalMapEvaluationRow', bacListActiveContractors($db))]);
    }

    if ($action === 'list_candidate_bids') {
        respond(['data' => array_map('bacPortalMapBidRow', bacListCandidateBids($db))]);
    }

    if ($action === 'list_contractor_applications') {
        $select = "SELECT c.id, c.name, c.contact_person, c.email, c.phone, c.address, c.pcab_license_no, c.pcab_classification, c.application_status, c.created_at
                   FROM contractors c
                   WHERE c.application_status = 'pending'
                   ORDER BY c.created_at ASC";
        $count = "SELECT COUNT(*) FROM contractors c WHERE c.application_status = 'pending'";
        $result = paginate($db, $select, $count, [], $page, $perPage);

        foreach ($result['data'] as &$row) {
            $docStmt = $db->prepare("
                SELECT id, document_type, title, original_name, file_path, created_at
                FROM supporting_documents
                WHERE owner_type = 'contractor' AND owner_id = ?
                ORDER BY created_at ASC
            ");
            $docStmt->execute([$row['id']]);
            $row['documents'] = $docStmt->fetchAll();
        }
        unset($row);

        respond($result);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

$body = requestBody();

if ($action === 'publish') {
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'deadline' => 'nullable|date',
        'notes' => 'nullable|string|max:1000',
        'reference_no' => 'nullable|string|max:40',
        'published_at' => 'nullable|date',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $deadline = trim((string) ($validated['deadline'] ?? ''));
    $notes = trim((string) ($validated['notes'] ?? ''));

    $project = bacPortalProject($db, $projectId);
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if (!in_array($project['status'], ['approved', 'bidding'], true)) {
        respond(['error' => 'Only approved projects can be posted for bidding.'], 422);
    }

    $referenceNo = ($validated['reference_no'] ?? '') !== '' ? $validated['reference_no'] : bacPortalReferenceNo($db, $projectId);
    $publishedAt = ($validated['published_at'] ?? '') !== '' ? $validated['published_at'] : date('Y-m-d');

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
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'contractor_id' => 'required|integer',
        'bid_amount' => 'required|numeric|min:1',
        'technical_score' => 'nullable|integer|min:0|max:100',
        'delivery_days' => 'nullable|integer|min:1',
        'remarks' => 'nullable|string|max:1000',
        'submitted_at' => 'nullable|date',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $contractorId = (int) $validated['contractor_id'];
    $amount = (float) $validated['bid_amount'];
    $technicalScore = max(0, min(100, (int) ($validated['technical_score'] ?? 0)));
    $deliveryDays = (int) ($validated['delivery_days'] ?? 0);
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    $announcement = $db->prepare("SELECT id FROM bac_bid_announcements WHERE project_id = ?");
    $announcement->execute([$projectId]);
    if (!$announcement->fetchColumn()) {
        respond(['error' => 'Publish a bidding notice before recording bids.'], 422);
    }

    $contractor = $db->prepare("SELECT id, name, is_blacklisted FROM contractors WHERE id = ? AND status = 'active'");
    $contractor->execute([$contractorId]);
    $contractorRow = $contractor->fetch();
    if (!$contractorRow) {
        respond(['error' => 'Active contractor not found.'], 404);
    }
    if ((int) ($contractorRow['is_blacklisted'] ?? 0) === 1) {
        respond(['error' => 'This contractor is blacklisted and is not eligible for bidding.'], 422);
    }

    $stmt = $db->prepare("
        INSERT INTO bac_bid_submissions
            (project_id, contractor_id, bid_amount, technical_score, delivery_days, status, submitted_at, remarks, source)
        VALUES (?, ?, ?, ?, ?, 'submitted', ?, ?, 'bac_recorded')
    ");
    $stmt->execute([
        $projectId,
        $contractorId,
        $amount,
        $technicalScore,
        $deliveryDays > 0 ? $deliveryDays : null,
        ($validated['submitted_at'] ?? '') !== '' ? $validated['submitted_at'] : date('Y-m-d'),
        $remarks !== '' ? $remarks : null,
    ]);
    $newBidId = (int) $db->lastInsertId();

    projectWorkflowLog($db, 'Contractor bid recorded', $projectId, $contractorRow['name'] . ' submitted a BAC bid.', $actorId ?: null);
    respond(['success' => true, 'id' => $newBidId], 201);
}

if ($action === 'score_bid') {
    $validated = Validator::make($body, [
        'bid_id' => 'required|integer',
        'technical_score' => 'required|integer|min:0|max:100',
        'remarks' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $bidId = (int) $validated['bid_id'];
    $technicalScore = max(0, min(100, (int) $validated['technical_score']));
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    // Scoped to submitted/for_review only — once a bid has been recommended
    // or rejected, the technical evaluation that fed that decision shouldn't
    // be editable after the fact (same cutoff bacListCandidateBids already
    // uses: status != 'recommended').
    $bidStmt = $db->prepare("
        SELECT b.id, b.project_id, b.status, p.name AS project_name, c.name AS contractor_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        INNER JOIN contractors c ON c.id = b.contractor_id
        WHERE b.id = ? AND b.status IN ('submitted', 'for_review')
    ");
    $bidStmt->execute([$bidId]);
    $bid = $bidStmt->fetch();
    if (!$bid) {
        respond(['error' => 'Bid not found or already decided.'], 404);
    }

    $db->prepare("UPDATE bac_bid_submissions SET technical_score = ?, remarks = COALESCE(?, remarks) WHERE id = ?")
        ->execute([$technicalScore, $remarks !== '' ? $remarks : null, $bidId]);

    projectWorkflowLog(
        $db,
        'Bid technical score set',
        (int) $bid['project_id'],
        $bid['contractor_name'] . '\'s bid for ' . $bid['project_name'] . ' was scored ' . $technicalScore . '/100.',
        $actorId ?: null
    );

    respond(['success' => true]);
}

if ($action === 'recommend') {
    $validated = Validator::make($body, [
        'bid_id' => 'required|integer',
        'basis' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $bidId = (int) $validated['bid_id'];
    $basis = trim((string) ($validated['basis'] ?? 'Lowest calculated responsive bid with acceptable technical score.'));

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

    // Only a live, undecided bid can be recommended — this is what stops BAC
    // from re-recommending a bid HOPE has already rejected (or re-triggering
    // a bid that's already sitting in front of HOPE as 'recommended').
    if (in_array($bid['status'], ['recommended', 'rejected'], true)) {
        respond(['error' => 'This bid has already been decided and cannot be recommended again.'], 422);
    }

    $projectId = (int) $bid['project_id'];
    $contractorId = (int) $bid['contractor_id'];

    // This only records BAC's recommendation and sends it to HOPE for the
    // real approval decision (hope/api/portal.php's decide_award action) —
    // it deliberately does NOT touch projects.status or contracts anymore.
    // That finalization used to happen right here, single-handedly; moving
    // it to HOPE's decision is the whole point of the Contract Award
    // Approval workflow.
    $db->beginTransaction();
    try {
        // Only revert the bid that was previously recommended for this
        // project — a bid HOPE has already rejected must stay rejected even
        // when BAC recommends a different candidate for the same project.
        $db->prepare("UPDATE bac_bid_submissions SET status = 'for_review' WHERE project_id = ? AND status = 'recommended'")
            ->execute([$projectId]);
        $db->prepare("UPDATE bac_bid_submissions SET status = 'recommended' WHERE id = ?")
            ->execute([$bidId]);

        $stmt = $db->prepare("
            INSERT INTO bac_award_recommendations
                (project_id, bid_submission_id, contractor_id, award_amount, basis, status, recommended_by, hope_remarks, decided_by, decided_at)
            VALUES (?, ?, ?, ?, ?, 'sent_to_admin', ?, NULL, NULL, NULL)
            ON DUPLICATE KEY UPDATE
                bid_submission_id = VALUES(bid_submission_id),
                contractor_id = VALUES(contractor_id),
                award_amount = VALUES(award_amount),
                basis = VALUES(basis),
                status = 'sent_to_admin',
                recommended_by = VALUES(recommended_by),
                hope_remarks = NULL,
                decided_by = NULL,
                decided_at = NULL,
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

        // Bidding is effectively closed the moment BAC recommends — HOPE's
        // decision works from this fixed candidate, not a moving pool. Both
        // contractor/api/portal.php's list_open_biddings and submit_bid check
        // this same column, which is why this line matters beyond hygiene.
        $db->prepare("UPDATE bac_bid_announcements SET status = 'closed' WHERE project_id = ?")
            ->execute([$projectId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to send award recommendation.'], 500);
    }

    projectWorkflowLog($db, 'Award recommendation sent', $projectId, $bid['contractor_name'] . ' was recommended for contractor assignment, pending HOPE approval.', $actorId ?: null);

    respond(['success' => true], 201);
}

if ($action === 'review_contractor_application') {
    // RA 12009 (New Government Procurement Act) vests contractor eligibility
    // review in the BAC specifically — a constituted body with legally defined
    // membership, not a generic system role. super_admin can still view this
    // list (file-level gate below) for oversight, but must not be able to
    // approve/reject in its place, since that action has no legal standing
    // outside an actual BAC member exercising it.
    requireAnyRole(['bac']);

    $validated = Validator::make($body, [
        'contractor_id' => 'required|integer',
        'decision' => 'required|in:approve,reject',
        'remarks' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $contractorId = (int) $validated['contractor_id'];
    $decision = (string) $validated['decision'];
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    if ($decision === 'reject' && $remarks === '') {
        respond(['error' => 'A reason is required to reject an application.'], 422);
    }

    $stmt = $db->prepare("SELECT id, name, email, application_status FROM contractors WHERE id = ?");
    $stmt->execute([$contractorId]);
    $contractor = $stmt->fetch();
    if (!$contractor) {
        respond(['error' => 'Application not found.'], 404);
    }
    if ($contractor['application_status'] !== 'pending') {
        respond(['error' => 'This application has already been reviewed.'], 422);
    }

    if ($decision === 'reject') {
        $db->prepare("
            UPDATE contractors
            SET application_status = 'rejected', application_reviewed_by = ?, application_reviewed_at = NOW(), application_remarks = ?
            WHERE id = ?
        ")->execute([$actorId, $remarks !== '' ? $remarks : null, $contractorId]);

        $details = $contractor['name'] . '\'s contractor application was rejected' . ($remarks !== '' ? ' — ' . $remarks : '') . '.';
        auditLog($db, $actorId, 'contractor_application_rejected', 'contractors', $contractorId, $details);
        logActivity($actorId, 'contractor_application_rejected', $details);

        respond(['success' => true, 'status' => 'rejected']);
    }

    // Approve: generate credentials and create the login account now that BAC has vetted the business.
    $tempPassword = bin2hex(random_bytes(6));
    $username = 'contractor' . $contractorId . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, status)
            VALUES (?, ?, ?, ?, 'contractor', 'active')
        ");
        $stmt->execute([
            $username,
            $contractor['email'],
            password_hash($tempPassword, PASSWORD_BCRYPT),
            $contractor['name'],
        ]);
        $newUserId = (int) $db->lastInsertId();

        $db->prepare("
            UPDATE contractors
            SET user_id = ?, application_status = 'approved', application_reviewed_by = ?, application_reviewed_at = NOW(), application_remarks = ?
            WHERE id = ?
        ")->execute([$newUserId, $actorId, $remarks !== '' ? $remarks : null, $contractorId]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        respond(['error' => 'Unable to approve application.'], 500);
    }

    $details = $contractor['name'] . '\'s contractor application was approved; portal account created.';
    auditLog($db, $actorId, 'contractor_application_approved', 'contractors', $contractorId, $details);
    logActivity($actorId, 'contractor_application_approved', $details);
    notifyUser($newUserId, 'info', 'Application approved', 'Your contractor application has been approved. Check your email to set up portal access.');

    $otp = new OTPManager();
    $loginUrl = appUrl('/auth/forgot-password.php?from=staff');
    $emailBody = '
        <p>Hello <strong>' . htmlspecialchars($contractor['name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>Congratulations — your contractor application has been reviewed and <strong>approved</strong> by the Bids and Awards Committee.</p>
        <p>To access your contractor portal, set your password here:</p>
        <p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>
        <p>Your username is: <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong></p>
    ';
    $otp->sendPlainEmail($contractor['email'], 'Your Contractor Application Has Been Approved', $emailBody);

    respond(['success' => true, 'status' => 'approved', 'temp_password' => $tempPassword]);
}

respond(['error' => 'Unknown action.'], 404);
