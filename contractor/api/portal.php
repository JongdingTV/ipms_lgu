<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../includes/ContractorScoring.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';

apiHeaders();
requireAnyRole(['contractor']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);
projectWorkflowEnsureRoleConnectionTables($db);
contractorsEnsureApplicationSchema($db);
$contractorId = contractorScopeCurrentId($db);
if ($contractorId === null) {
    respond(['error' => 'No contractor profile is linked to this account.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';

function contractorPortalProject(PDO $db, int $projectId, int $contractorId): ?array
{
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name, c.contact_person, c.email AS contractor_email,
               COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS total_spent,
               ct.contract_no, ct.contract_amount, ct.notice_to_proceed_date, ct.status AS contract_status,
               COALESCE((SELECT COUNT(*) FROM milestones WHERE project_id = p.id), 0) AS milestone_count,
               (SELECT MAX(report_date) FROM contractor_reports WHERE project_id = p.id AND contractor_id = ?) AS latest_report_date
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN contracts ct ON ct.project_id = p.id
        WHERE p.id = ? AND p.contractor_id = ?
          AND p.status IN ('assigned','active','delayed','on_hold','completed')
        LIMIT 1
    ");
    $stmt->execute([$contractorId, $projectId, $contractorId]);
    $project = $stmt->fetch();

    return $project ?: null;
}

function contractorPortalProjects(PDO $db, int $contractorId): array
{
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name,
               COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS total_spent,
               ct.contract_no, ct.contract_amount, ct.notice_to_proceed_date, ct.status AS contract_status,
               COALESCE((SELECT COUNT(*) FROM milestones WHERE project_id = p.id), 0) AS milestone_count,
               (SELECT MAX(report_date) FROM contractor_reports WHERE project_id = p.id AND contractor_id = ?) AS latest_report_date
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN contracts ct ON ct.project_id = p.id
        WHERE p.contractor_id = ?
          AND p.status IN ('assigned','active','delayed','on_hold','completed')
        ORDER BY p.updated_at DESC, p.id DESC
    ");
    $stmt->execute([$contractorId, $contractorId]);

    return $stmt->fetchAll();
}

function contractorPortalPayments(PDO $db, array $projects, int $contractorId): array
{
    $requests = $db->prepare("
        SELECT pr.*, p.project_code, p.name
        FROM payment_requests pr
        INNER JOIN projects p ON p.id = pr.project_id
        WHERE pr.contractor_id = ?
        ORDER BY pr.submitted_at DESC, pr.id DESC
    ");
    $requests->execute([$contractorId]);
    $requestRows = $requests->fetchAll();

    $rows = array_map(static function (array $project): array {
        $budget = (float) ($project['budget'] ?? 0);
        $progress = (int) ($project['progress'] ?? 0);
        $released = (float) ($project['total_spent'] ?? 0);
        $eligible = round($budget * ($progress / 100), 2);
        $balance = max(0, $eligible - $released);

        if ($progress >= 100 && $balance <= 0) {
            $status = 'completed';
            $label = 'Fully Released';
        } elseif ($balance > 0 && $progress >= 30) {
            $status = 'for_review';
            $label = 'For Billing Review';
        } else {
            $status = 'pending';
            $label = 'Pending Progress';
        }

        return [
            'project_id' => (int) $project['id'],
            'project_code' => $project['project_code'],
            'name' => $project['name'],
            'budget' => $budget,
            'progress' => $progress,
            'eligible_amount' => $eligible,
            'released_amount' => $released,
            'balance_amount' => $balance,
            'status' => $status,
            'label' => $label,
            'source' => 'computed',
        ];
    }, $projects);

    foreach ($requestRows as $request) {
        $rows[] = [
            'id' => (int) $request['id'],
            'project_id' => (int) $request['project_id'],
            'project_code' => $request['project_code'],
            'name' => $request['name'],
            'billing_no' => $request['billing_no'],
            'budget' => 0,
            'progress' => null,
            'eligible_amount' => 0,
            'released_amount' => 0,
            'balance_amount' => (float) $request['requested_amount'],
            'requested_amount' => (float) $request['requested_amount'],
            'status' => $request['status'],
            'label' => 'Billing ' . str_replace('_', ' ', $request['status']),
            'submitted_at' => $request['submitted_at'],
            'source' => 'request',
        ];
    }

    return $rows;
}

function contractorPortalProjectExtras(PDO $db, array $project, int $contractorId): array
{
    $projectId = (int) $project['id'];

    $milestones = $db->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date ASC, id ASC");
    $milestones->execute([$projectId]);
    $project['milestones'] = $milestones->fetchAll();

    $expenses = $db->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY expense_date DESC, id DESC LIMIT 20");
    $expenses->execute([$projectId]);
    $project['expenses'] = $expenses->fetchAll();

    $reports = $db->prepare("
        SELECT id, report_date, progress_percent, accomplishments, issues, next_steps, status, created_at
        FROM contractor_reports
        WHERE project_id = ? AND contractor_id = ?
        ORDER BY report_date DESC, id DESC
        LIMIT 10
    ");
    $reports->execute([$projectId, $contractorId]);
    $project['reports'] = $reports->fetchAll();

    $documents = $db->prepare("
        SELECT id, document_type, title, original_name, file_path, file_size, remarks, status, created_at
        FROM contractor_documents
        WHERE project_id = ? AND contractor_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 10
    ");
    $documents->execute([$projectId, $contractorId]);
    $project['documents'] = $documents->fetchAll();

    $payments = $db->prepare("
        SELECT id, billing_no, requested_amount, status, remarks, submitted_at
        FROM payment_requests
        WHERE project_id = ? AND contractor_id = ?
        ORDER BY submitted_at DESC, id DESC
        LIMIT 10
    ");
    $payments->execute([$projectId, $contractorId]);
    $project['payment_requests'] = $payments->fetchAll();

    return $project;
}

/** Dashboard priorities before a contractor has won anything: accreditation + bidding. */
function contractorPortalPreAwardStage(PDO $db, int $contractorId): array
{
    $openBids = (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'bidding'")->fetchColumn();

    $submitted = $db->prepare("SELECT COUNT(*) FROM bac_bid_submissions WHERE contractor_id = ?");
    $submitted->execute([$contractorId]);

    $results = $db->prepare("
        SELECT b.*, p.project_code, p.name AS project_name
        FROM bac_bid_submissions b
        INNER JOIN projects p ON p.id = b.project_id
        WHERE b.contractor_id = ? AND b.status IN ('recommended', 'rejected')
        ORDER BY b.updated_at DESC, b.id DESC
        LIMIT 3
    ");
    $results->execute([$contractorId]);

    return [
        'open_biddings_count' => $openBids,
        'submitted_bids_count' => (int) $submitted->fetchColumn(),
        'recent_results' => $results->fetchAll(),
    ];
}

/** Dashboard priorities once a contractor has an awarded project: execution + payment. */
function contractorPortalPostAwardStage(PDO $db, int $contractorId): array
{
    $deadline = $db->prepare("
        SELECT MIN(m.due_date)
        FROM milestones m
        INNER JOIN projects p ON p.id = m.project_id
        WHERE p.contractor_id = ? AND m.completed = 0 AND m.due_date >= CURDATE()
    ");
    $deadline->execute([$contractorId]);

    // "Pending inspection" = a progress report with no inspection record at
    // all yet. Once an engineer has logged any inspections row — even a
    // needs_correction one — the contractor has already been informed of the
    // outcome, so it no longer counts as awaiting review.
    $pendingInspections = $db->prepare("
        SELECT COUNT(*) FROM contractor_reports cr
        WHERE cr.contractor_id = ?
          AND NOT EXISTS (SELECT 1 FROM inspections i WHERE i.progress_report_id = cr.id)
    ");
    $pendingInspections->execute([$contractorId]);

    $pendingPayments = $db->prepare("
        SELECT COUNT(*) FROM payment_requests WHERE contractor_id = ? AND status IN ('submitted', 'under_review')
    ");
    $pendingPayments->execute([$contractorId]);

    return [
        'upcoming_deadline' => $deadline->fetchColumn() ?: null,
        'pending_inspections_count' => (int) $pendingInspections->fetchColumn(),
        'pending_payment_requests_count' => (int) $pendingPayments->fetchColumn(),
    ];
}

/** Open-for-bidding projects, with this contractor's own submission (if any) attached. */
function contractorPortalOpenBiddings(PDO $db, int $contractorId): array
{
    $stmt = $db->prepare("
        SELECT p.id AS project_id, p.project_code, p.name, p.location, p.budget,
               a.reference_no, a.published_at, a.deadline, a.notes,
               b.id AS my_bid_id, b.bid_amount AS my_bid_amount, b.status AS my_bid_status
        FROM projects p
        INNER JOIN bac_bid_announcements a ON a.project_id = p.id
        LEFT JOIN bac_bid_submissions b ON b.project_id = p.id AND b.contractor_id = ?
        WHERE p.status = 'bidding'
          AND a.status != 'closed'
          AND (a.deadline IS NULL OR a.deadline >= CURDATE())
        ORDER BY (a.deadline IS NULL), a.deadline ASC, a.published_at DESC
    ");
    $stmt->execute([$contractorId]);

    return $stmt->fetchAll();
}

const CONTRACTOR_DOC_MAX_SIZE = 10 * 1024 * 1024;
const CONTRACTOR_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];

/** Same dynamic-row convention used by superadmin/bac: documents[N][title]/[document_type] + document_files[N]. */
function contractorCollectDocumentRows(array $textRows, array $filesField): array
{
    $indices = array_keys($textRows);
    foreach (array_keys($filesField['name'] ?? []) as $idx) {
        if (!in_array($idx, $indices, true)) {
            $indices[] = $idx;
        }
    }

    $rows = [];
    foreach ($indices as $idx) {
        $documentType = trim((string) ($textRows[$idx]['document_type'] ?? ''));
        $title = trim((string) ($textRows[$idx]['title'] ?? ''));
        $file = FileUpload::fromNestedFiles($filesField, (int) $idx);

        if ($title === '' && $file === null) {
            continue;
        }

        if ($title === '') {
            $error = 'Title is required when a file is attached.';
        } else {
            $error = FileUpload::validate($file, [
                'required' => true,
                'max_size' => CONTRACTOR_DOC_MAX_SIZE,
                'extensions' => CONTRACTOR_DOC_EXTENSIONS,
            ]);
        }

        $rows[] = [
            'document_type' => $documentType !== '' ? $documentType : 'General',
            'title' => $title,
            'file' => $file,
            'error' => $error,
        ];
    }

    return $rows;
}

function contractorCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if ($method === 'GET') {
    if ($action === 'summary') {
        $contractor = $db->prepare("
            SELECT c.*,
                   COUNT(p.id) AS assigned_projects,
                   SUM(CASE WHEN p.status IN ('assigned','active','delayed','on_hold') THEN 1 ELSE 0 END) AS active_projects,
                   SUM(CASE WHEN p.status = 'delayed' THEN 1 ELSE 0 END) AS delayed_projects,
                   COALESCE(ROUND(AVG(p.progress)), 0) AS average_progress,
                   COALESCE(SUM(p.budget), 0) AS total_contract_value
            FROM contractors c
            LEFT JOIN projects p ON p.contractor_id = c.id
                AND p.status IN ('assigned','active','delayed','on_hold','completed')
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $contractor->execute([$contractorId]);
        $contractorRow = $contractor->fetch();

        $reports = $db->prepare("SELECT COUNT(*) FROM contractor_reports WHERE contractor_id = ?");
        $reports->execute([$contractorId]);

        $documents = $db->prepare("SELECT COUNT(*) FROM contractor_documents WHERE contractor_id = ?");
        $documents->execute([$contractorId]);

        $projects = contractorPortalProjects($db, $contractorId);
        $payments = contractorPortalPayments($db, $projects, $contractorId);
        $pendingPayments = array_sum(array_map(static fn (array $row): float => (float) $row['balance_amount'], $payments));

        // has_awarded_projects drives the dashboard's two widget sets: a
        // contractor is only ever linked to a project (contractor_id set) once
        // BAC's award recommendation runs, regardless of what happens after —
        // so this is deliberately NOT the same status-filtered list as
        // contractorPortalProjects() above.
        $awardedCheck = $db->prepare("SELECT COUNT(*) FROM projects WHERE contractor_id = ?");
        $awardedCheck->execute([$contractorId]);
        $hasAwardedProjects = ((int) $awardedCheck->fetchColumn()) > 0;

        $stageData = $hasAwardedProjects
            ? contractorPortalPostAwardStage($db, $contractorId)
            : contractorPortalPreAwardStage($db, $contractorId);

        respond([
            'contractor' => $contractorRow,
            'has_awarded_projects' => $hasAwardedProjects,
            'stage' => $hasAwardedProjects ? 'post_award' : 'pre_award',
            'stage_data' => $stageData,
            'stats' => [
                'assigned_projects' => (int) ($contractorRow['assigned_projects'] ?? 0),
                'active_projects' => (int) ($contractorRow['active_projects'] ?? 0),
                'delayed_projects' => (int) ($contractorRow['delayed_projects'] ?? 0),
                'average_progress' => (int) ($contractorRow['average_progress'] ?? 0),
                'total_contract_value' => (float) ($contractorRow['total_contract_value'] ?? 0),
                'reports_submitted' => (int) $reports->fetchColumn(),
                'documents_uploaded' => (int) $documents->fetchColumn(),
                'pending_payment_amount' => $pendingPayments,
                'performance_score' => (int) ($contractorRow['performance_score'] ?? 0),
            ],
        ]);
    }

    if ($action === 'projects') {
        respond(['data' => contractorPortalProjects($db, $contractorId)]);
    }

    if ($action === 'project') {
        $projectId = (int) ($_GET['id'] ?? 0);
        if ($projectId <= 0) {
            respond(['error' => 'Project ID is required.'], 422);
        }

        $project = contractorPortalProject($db, $projectId, $contractorId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        respond(['data' => contractorPortalProjectExtras($db, $project, $contractorId)]);
    }

    if ($action === 'reports') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = 'r.contractor_id = ?';
        $params = [$contractorId];
        if ($search !== '') {
            $where .= ' AND (p.project_code LIKE ? OR p.name LIKE ? OR r.accomplishments LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $select = "
            SELECT r.*, p.project_code, p.name AS project_name
            FROM contractor_reports r
            INNER JOIN projects p ON p.id = r.project_id
            WHERE $where
            ORDER BY r.report_date DESC, r.id DESC
        ";
        $count = "
            SELECT COUNT(*)
            FROM contractor_reports r
            INNER JOIN projects p ON p.id = r.project_id
            WHERE $where
        ";
        respond(paginate($db, $select, $count, $params, $page, $perPage));
    }

    if ($action === 'documents') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $type = trim((string) ($_GET['type'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = 'd.contractor_id = ?';
        $params = [$contractorId];
        if ($type !== '') {
            $where .= ' AND d.document_type = ?';
            $params[] = $type;
        }
        if ($search !== '') {
            $where .= ' AND (p.project_code LIKE ? OR p.name LIKE ? OR d.title LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $select = "
            SELECT d.*, p.project_code, p.name AS project_name
            FROM contractor_documents d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE $where
            ORDER BY d.created_at DESC, d.id DESC
        ";
        $count = "
            SELECT COUNT(*)
            FROM contractor_documents d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE $where
        ";
        respond(paginate($db, $select, $count, $params, $page, $perPage));
    }

    if ($action === 'payments') {
        respond(['data' => contractorPortalPayments($db, contractorPortalProjects($db, $contractorId), $contractorId)]);
    }

    if ($action === 'list_open_biddings') {
        respond(['data' => contractorPortalOpenBiddings($db, $contractorId)]);
    }

    if ($action === 'my_bids') {
        $stmt = $db->prepare("
            SELECT b.*, p.project_code, p.name AS project_name, p.location
            FROM bac_bid_submissions b
            INNER JOIN projects p ON p.id = b.project_id
            WHERE b.contractor_id = ?
            ORDER BY b.submitted_at DESC, b.id DESC
        ");
        $stmt->execute([$contractorId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'accreditation_documents') {
        $stmt = $db->prepare("
            SELECT id, document_type, title, original_name, file_path, file_size, status, remarks, created_at
            FROM supporting_documents
            WHERE owner_type = 'contractor' AND owner_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([$contractorId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'performance') {
        $breakdown = contractorCalculatePerformanceScoreBreakdown($db, $contractorId);

        $contractor = $db->prepare("SELECT credibility_score, is_blacklisted, blacklist_reason, blacklist_date FROM contractors WHERE id = ?");
        $contractor->execute([$contractorId]);
        $contractorRow = $contractor->fetch() ?: [];

        $delayCount = $db->prepare("
            SELECT COUNT(*) FROM engineer_delay_reports edr
            INNER JOIN projects p ON p.id = edr.project_id
            WHERE p.contractor_id = ?
        ");
        $delayCount->execute([$contractorId]);

        $issueCount = $db->prepare("
            SELECT COUNT(*) FROM engineer_issue_reports eir
            INNER JOIN projects p ON p.id = eir.project_id
            WHERE p.contractor_id = ? AND eir.status != 'closed'
        ");
        $issueCount->execute([$contractorId]);

        respond([
            'score' => $breakdown['score'],
            'components' => $breakdown['components'],
            'credibility_score' => (float) ($contractorRow['credibility_score'] ?? 0),
            'is_blacklisted' => (bool) ($contractorRow['is_blacklisted'] ?? false),
            'blacklist_reason' => $contractorRow['blacklist_reason'] ?? null,
            'blacklist_date' => $contractorRow['blacklist_date'] ?? null,
            'delay_report_count' => (int) $delayCount->fetchColumn(),
            'open_issue_count' => (int) $issueCount->fetchColumn(),
        ]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method === 'POST') {
    if ($action === 'report') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'progress_percent' => 'required|integer|min:0|max:100',
            'report_date' => 'nullable|date',
            'accomplishments' => 'required|string|min:3',
            'issues' => 'nullable|string|max:2000',
            'next_steps' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $progress = max(0, min(100, (int) $validated['progress_percent']));
        $reportDate = ($validated['report_date'] ?? '') !== '' ? $validated['report_date'] : date('Y-m-d');
        $accomplishments = trim((string) $validated['accomplishments']);
        $issues = trim((string) ($validated['issues'] ?? ''));
        $nextSteps = trim((string) ($validated['next_steps'] ?? ''));

        $project = contractorPortalProject($db, $projectId, $contractorId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        $stmt = $db->prepare("
            INSERT INTO contractor_reports
                (project_id, contractor_id, submitted_by, report_date, progress_percent, accomplishments, issues, next_steps, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
        ");
        $stmt->execute([
            $projectId,
            $contractorId,
            $_SESSION['user_id'] ?? null,
            $reportDate,
            $progress,
            $accomplishments,
            $issues !== '' ? $issues : null,
            $nextSteps !== '' ? $nextSteps : null,
        ]);
        $newReportId = (int) $db->lastInsertId();

        $newProgress = max((int) $project['progress'], $progress);
        $newStatus = $project['status'];
        if ($newProgress >= 100) {
            $newStatus = 'completed';
        } elseif (in_array($newStatus, ['draft', 'planning', 'approved', 'bidding', 'awarded', 'assigned'], true)) {
            $newStatus = 'active';
        }

        $update = $db->prepare("UPDATE projects SET progress = ?, status = ? WHERE id = ? AND contractor_id = ?");
        $update->execute([$newProgress, $newStatus, $projectId, $contractorId]);

        respond(['success' => true, 'id' => $newReportId], 201);
    }

    if ($action === 'document') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        if ($projectId <= 0) {
            respond(['error' => 'Project is required.'], 422);
        }

        $project = contractorPortalProject($db, $projectId, $contractorId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        $documentRows = contractorCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
        if ($documentRows === []) {
            respond(['error' => 'At least one document (title + file) is required.'], 422);
        }
        foreach ($documentRows as $i => $row) {
            if ($row['error'] !== null) {
                respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
            }
        }

        $storedFiles = [];
        $insertedIds = [];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO contractor_documents
                    (project_id, contractor_id, uploaded_by, document_type, title, original_name, file_path, file_size, mime_type, remarks, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'uploaded')
            ");

            foreach ($documentRows as $row) {
                $stored = FileUpload::store($row['file'], 'contractor-documents', [
                    'max_size' => CONTRACTOR_DOC_MAX_SIZE,
                    'extensions' => CONTRACTOR_DOC_EXTENSIONS,
                ]);
                $storedFiles[] = $stored['stored_path'];

                $stmt->execute([
                    $projectId,
                    $contractorId,
                    $_SESSION['user_id'] ?? null,
                    $row['document_type'],
                    $row['title'],
                    $stored['original_name'],
                    $stored['stored_path'],
                    $stored['file_size'],
                    $stored['mime_type'],
                    $remarks !== '' ? $remarks : null,
                ]);
                $insertedIds[] = (int) $db->lastInsertId();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            contractorCleanupFiles($storedFiles);
            respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to upload documents.'], 422);
        }

        respond(['success' => true, 'ids' => $insertedIds], 201);
    }

    if ($action === 'payment_request') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'requested_amount' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $amount = (float) $validated['requested_amount'];
        $remarks = trim((string) ($validated['remarks'] ?? ''));

        $project = contractorPortalProject($db, $projectId, $contractorId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        $latestReport = $db->prepare("
            SELECT id
            FROM contractor_reports
            WHERE project_id = ? AND contractor_id = ?
            ORDER BY report_date DESC, id DESC
            LIMIT 1
        ");
        $latestReport->execute([$projectId, $contractorId]);
        $reportId = $latestReport->fetchColumn();
        if (!$reportId) {
            respond(['error' => 'Submit a progress report before requesting payment.'], 422);
        }

        $stmt = $db->prepare("
            INSERT INTO payment_requests
                (project_id, contractor_id, progress_report_id, requested_amount, billing_no, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $contractorId,
            (int) $reportId,
            $amount,
            projectWorkflowPaymentNo($projectId, $contractorId),
            $remarks !== '' ? $remarks : null,
        ]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'submit_bid') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'bid_amount' => 'required|numeric|min:1',
            'delivery_days' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $amount = (float) $validated['bid_amount'];
        $deliveryDays = (int) ($validated['delivery_days'] ?? 0);
        $remarks = trim((string) ($validated['remarks'] ?? ''));

        $projectStmt = $db->prepare("SELECT id, status FROM projects WHERE id = ?");
        $projectStmt->execute([$projectId]);
        $projectRow = $projectStmt->fetch();
        if (!$projectRow) {
            respond(['error' => 'Project not found.'], 404);
        }

        // Mirrors the exact message BAC's own manual `bid` action uses for the
        // same check (bac/api/portal.php) — same rule, same wording.
        $announcementStmt = $db->prepare("SELECT status, deadline FROM bac_bid_announcements WHERE project_id = ?");
        $announcementStmt->execute([$projectId]);
        $announcement = $announcementStmt->fetch();
        if (!$announcement) {
            respond(['error' => 'This project has not been posted for bidding yet.'], 422);
        }

        if ($projectRow['status'] !== 'bidding') {
            respond(['error' => 'This project is no longer open for bidding.'], 422);
        }
        // BAC closes the announcement the moment it recommends an awardee —
        // bidding stays closed for the entire window HOPE is deciding on that
        // recommendation, even though projects.status doesn't flip to
        // 'awarded' until HOPE actually approves it. Checking projects.status
        // alone would let a rival contractor submit a competing bid during
        // that pending-approval window.
        if ($announcement['status'] === 'closed') {
            respond(['error' => 'This project is no longer open for bidding.'], 422);
        }
        if (!empty($announcement['deadline']) && $announcement['deadline'] < date('Y-m-d')) {
            respond(['error' => 'The bidding deadline for this project has passed.'], 422);
        }

        $ownStmt = $db->prepare("SELECT status, is_blacklisted FROM contractors WHERE id = ?");
        $ownStmt->execute([$contractorId]);
        $own = $ownStmt->fetch();
        if (!$own || $own['status'] !== 'active' || (int) ($own['is_blacklisted'] ?? 0) === 1) {
            respond(['error' => 'Your company is not currently eligible to submit bids.'], 403);
        }

        $existingStmt = $db->prepare("SELECT id FROM bac_bid_submissions WHERE project_id = ? AND contractor_id = ?");
        $existingStmt->execute([$projectId, $contractorId]);
        if ($existingStmt->fetchColumn()) {
            respond(['error' => 'You have already submitted a bid for this project.'], 422);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO bac_bid_submissions
                    (project_id, contractor_id, bid_amount, delivery_days, status, submitted_at, remarks, source, submitted_by)
                VALUES (?, ?, ?, ?, 'submitted', ?, ?, 'contractor', ?)
            ");
            $stmt->execute([
                $projectId,
                $contractorId,
                $amount,
                $deliveryDays > 0 ? $deliveryDays : null,
                date('Y-m-d'),
                $remarks !== '' ? $remarks : null,
                $_SESSION['user_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Defense-in-depth: the unique index on (project_id, contractor_id)
            // may not have attached if a dirty duplicate row existed at
            // migration time (see includes/workflow.php) — don't trust it as
            // the only guard against a race between the check above and this
            // insert.
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                respond(['error' => 'You have already submitted a bid for this project.'], 422);
            }
            respond(['error' => 'Unable to submit bid. Please try again later.'], 422);
        }

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'upload_accreditation_document') {
        $documentRows = contractorCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
        if ($documentRows === []) {
            respond(['error' => 'At least one document (title + file) is required.'], 422);
        }
        foreach ($documentRows as $i => $row) {
            if ($row['error'] !== null) {
                respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
            }
        }

        $storedFiles = [];
        $insertedIds = [];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO supporting_documents
                    (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status)
                VALUES ('contractor', ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            foreach ($documentRows as $row) {
                $stored = FileUpload::store($row['file'], 'contractor-accreditation', [
                    'max_size' => CONTRACTOR_DOC_MAX_SIZE,
                    'extensions' => CONTRACTOR_DOC_EXTENSIONS,
                ]);
                $storedFiles[] = $stored['stored_path'];

                $stmt->execute([
                    $contractorId,
                    $row['document_type'],
                    $row['title'],
                    $stored['original_name'],
                    $stored['stored_path'],
                    $stored['file_size'],
                    $stored['mime_type'],
                    $_SESSION['user_id'] ?? null,
                ]);
                $insertedIds[] = (int) $db->lastInsertId();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            contractorCleanupFiles($storedFiles);
            respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to upload documents.'], 422);
        }

        respond(['success' => true, 'ids' => $insertedIds], 201);
    }

    if ($action === 'update_profile') {
        $validated = Validator::make(requestBody(), [
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
        ])->stopOnFailure();

        $db->prepare("UPDATE contractors SET contact_person = ?, phone = ?, address = ? WHERE id = ?")->execute([
            trim((string) ($validated['contact_person'] ?? '')) ?: null,
            trim((string) ($validated['phone'] ?? '')) ?: null,
            trim((string) ($validated['address'] ?? '')) ?: null,
            $contractorId,
        ]);

        respond(['success' => true]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

respond(['error' => 'Method not allowed.'], 405);
