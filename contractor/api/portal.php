<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/workflow.php';

apiHeaders();
requireAnyRole(['contractor']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);
projectWorkflowEnsureRoleConnectionTables($db);
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

function contractorPortalSafeFileName(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
    $name = trim((string) $name, '.-');

    return $name !== '' ? $name : 'document';
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

        respond([
            'contractor' => $contractorRow,
            'stats' => [
                'assigned_projects' => (int) ($contractorRow['assigned_projects'] ?? 0),
                'active_projects' => (int) ($contractorRow['active_projects'] ?? 0),
                'delayed_projects' => (int) ($contractorRow['delayed_projects'] ?? 0),
                'average_progress' => (int) ($contractorRow['average_progress'] ?? 0),
                'total_contract_value' => (float) ($contractorRow['total_contract_value'] ?? 0),
                'reports_submitted' => (int) $reports->fetchColumn(),
                'documents_uploaded' => (int) $documents->fetchColumn(),
                'pending_payment_amount' => $pendingPayments,
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
        $stmt = $db->prepare("
            SELECT r.*, p.project_code, p.name AS project_name
            FROM contractor_reports r
            INNER JOIN projects p ON p.id = r.project_id
            WHERE r.contractor_id = ?
            ORDER BY r.report_date DESC, r.id DESC
        ");
        $stmt->execute([$contractorId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'documents') {
        $stmt = $db->prepare("
            SELECT d.*, p.project_code, p.name AS project_name
            FROM contractor_documents d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE d.contractor_id = ?
            ORDER BY d.created_at DESC, d.id DESC
        ");
        $stmt->execute([$contractorId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'payments') {
        respond(['data' => contractorPortalPayments($db, contractorPortalProjects($db, $contractorId), $contractorId)]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method === 'POST') {
    if ($action === 'report') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $progress = max(0, min(100, (int) ($body['progress_percent'] ?? 0)));
        $reportDate = trim((string) ($body['report_date'] ?? date('Y-m-d')));
        $accomplishments = trim((string) ($body['accomplishments'] ?? ''));
        $issues = trim((string) ($body['issues'] ?? ''));
        $nextSteps = trim((string) ($body['next_steps'] ?? ''));

        if ($projectId <= 0 || $accomplishments === '') {
            respond(['error' => 'Project and accomplishments are required.'], 422);
        }

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

        $newProgress = max((int) $project['progress'], $progress);
        $newStatus = $project['status'];
        if ($newProgress >= 100) {
            $newStatus = 'completed';
        } elseif (in_array($newStatus, ['draft', 'planning', 'approved', 'bidding', 'awarded', 'assigned'], true)) {
            $newStatus = 'active';
        }

        $update = $db->prepare("UPDATE projects SET progress = ?, status = ? WHERE id = ? AND contractor_id = ?");
        $update->execute([$newProgress, $newStatus, $projectId, $contractorId]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'document') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $documentType = trim((string) ($_POST['document_type'] ?? 'General'));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        if ($projectId <= 0 || $title === '' || empty($_FILES['document'])) {
            respond(['error' => 'Project, title, and document file are required.'], 422);
        }

        $project = contractorPortalProject($db, $projectId, $contractorId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        $file = $_FILES['document'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond(['error' => 'Upload failed. Please choose a valid file.'], 422);
        }

        if ((int) $file['size'] > 10 * 1024 * 1024) {
            respond(['error' => 'File size must be 10MB or smaller.'], 422);
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedExtensions, true)) {
            respond(['error' => 'Allowed files: PDF, Word, Excel, PNG, JPG.'], 422);
        }

        $uploadRoot = dirname(__DIR__, 2) . '/uploads/contractor-documents/' . date('Y');
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            respond(['error' => 'Unable to prepare upload folder.'], 500);
        }

        $safeName = contractorPortalSafeFileName(pathinfo($originalName, PATHINFO_FILENAME));
        $storedName = $projectId . '-' . time() . '-' . bin2hex(random_bytes(4)) . '-' . $safeName . '.' . $extension;
        $destination = $uploadRoot . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            respond(['error' => 'Unable to save uploaded file.'], 500);
        }

        $relativePath = 'uploads/contractor-documents/' . date('Y') . '/' . $storedName;
        $mimeType = function_exists('mime_content_type') ? mime_content_type($destination) : ($file['type'] ?? null);

        $stmt = $db->prepare("
            INSERT INTO contractor_documents
                (project_id, contractor_id, uploaded_by, document_type, title, original_name, file_path, file_size, mime_type, remarks, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'uploaded')
        ");
        $stmt->execute([
            $projectId,
            $contractorId,
            $_SESSION['user_id'] ?? null,
            $documentType,
            $title,
            $originalName,
            $relativePath,
            (int) $file['size'],
            $mimeType,
            $remarks !== '' ? $remarks : null,
        ]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId(), 'file_path' => $relativePath], 201);
    }

    if ($action === 'payment_request') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $amount = (float) ($body['requested_amount'] ?? 0);
        $remarks = trim((string) ($body['remarks'] ?? ''));

        if ($projectId <= 0 || $amount <= 0) {
            respond(['error' => 'Project and requested amount are required.'], 422);
        }

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

    respond(['error' => 'Unknown action.'], 404);
}

respond(['error' => 'Method not allowed.'], 405);
