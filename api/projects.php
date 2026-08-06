<?php
// ============================================================
// api/projects.php — Projects CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../contractor/includes/scope.php';
require_once __DIR__ . '/../engineer/includes/scope.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Notifications.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/ProjectHealth.php';
require_once __DIR__ . '/../includes/RoadGeometry.php';
apiHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// Phase 1 lifecycle gates: each new stage is carved out of the broad
// admin/super_admin mutation gate below, the same way 'decide' already is,
// so every new authority lands on exactly the role that owns it and no
// role picks up the unscoped create/edit/delete access as a side effect.
const PROJECT_ENGINEER_ACTIONS = ['engineering_review', 'completion_decide'];
const PROJECT_ENGINEER_OR_ADMIN_ACTIONS = ['issue_ntp', 'request_completion_inspection', 'upload_document_version'];
const PROJECT_ADMIN_ONLY_ACTIONS = ['turnover'];

// Must match the ENUM values self-healed onto projects.category/funding_source
// by projectCategoryEnumSql()/projectFundingSourceEnumSql() in includes/workflow.php.
const PROJECT_CATEGORIES = ['Roads and Bridges', 'Drainage and Flood Control', 'Water Supply', 'Public Buildings and Facilities', 'Street Lighting', 'Parks and Recreation', 'Other'];
const PROJECT_FUNDING_SOURCES = ['LGU General Fund', '20% Development Fund', 'National Government Fund', 'Grant/Donor Fund', 'Special Education Fund', 'Other'];

if ($method === 'GET') {
    requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen', 'hope']);
} elseif ($method === 'POST' && $action === 'decide') {
    // Project approval is HOPE's specific statutory authority under RA 12009
    // (Head of the Procuring Entity).
    requireAnyRole(['hope']);
} elseif ($method === 'POST' && in_array($action, PROJECT_ENGINEER_ACTIONS, true)) {
    requireAnyRole(['engineer']);
} elseif ($method === 'POST' && in_array($action, PROJECT_ENGINEER_OR_ADMIN_ACTIONS, true)) {
    requireAnyRole(['engineer', 'admin']);
} elseif ($method === 'POST' && in_array($action, PROJECT_ADMIN_ONLY_ACTIONS, true)) {
    requireAnyRole(['admin']);
} else {
    // Mutations here are unscoped (any project id, any field, including
    // contractor/budget/status). Engineers only have a scoped mutation path
    // via engineer/api/portal.php's own 'status' action, so they must not be
    // allowed to hit this unscoped endpoint.
    requireAnyRole(['super_admin', 'admin']);
}

requireCsrfProtection();

$db     = getDB();
engineerScopeEnsureTables($db);
projectWorkflowEnsureProjectStatusSchema($db);
documentsEnsureVersioningSchema($db);
projectDeletionEnsureSchema($db);
projectEditEnsureSchema($db);
projectRoadGeometryEnsureSchema($db);
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user   = currentUser();
$isContractor = ($user['role'] ?? '') === 'contractor';
$contractorScopeId = null;

const PROJECT_DOC_MAX_SIZE = 10 * 1024 * 1024;
const PROJECT_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
// Keeps per-project file storage bounded — matches the client-side row cap in
// assets/js/script.js's wireProjectDocRows(), enforced again here since a raw
// API call could otherwise submit more rows than the form allows.
const PROJECT_DOC_ROW_LIMIT = 3;

/** Same dynamic documents[N][document_type]/[title] + flat document_files[N] convention used in superadmin/api/accounts.php. */
function projectCollectDocumentRows(array $textRows, array $filesField): array
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
                'max_size' => PROJECT_DOC_MAX_SIZE,
                'extensions' => PROJECT_DOC_EXTENSIONS,
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

/** Stores each row's file + inserts its supporting_documents row (owner_type='project'). Throws FileUploadException on failure. */
function projectStoreDocuments(PDO $db, array $rows, int $projectId, int $uploadedBy, array &$storedFiles): void
{
    $stmt = $db->prepare('
        INSERT INTO supporting_documents
            (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status)
        VALUES ("project", ?, ?, ?, ?, ?, ?, ?, ?, "pending")
    ');

    foreach ($rows as $row) {
        $stored = FileUpload::store($row['file'], 'supporting-documents/project', [
            'max_size' => PROJECT_DOC_MAX_SIZE,
            'extensions' => PROJECT_DOC_EXTENSIONS,
        ]);
        $storedFiles[] = $stored['stored_path'];

        $stmt->execute([
            $projectId,
            $row['document_type'],
            $row['title'],
            $stored['original_name'],
            $stored['stored_path'],
            $stored['file_size'],
            $stored['mime_type'],
            $uploadedBy,
        ]);
    }
}

/** Best-effort cleanup when a DB step fails after files were already moved (not covered by SQL rollback). */
function projectCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if ($method === 'GET' && $isContractor) {
    $contractorScopeId = contractorScopeCurrentId($db);
    if ($contractorScopeId === null) {
        if ($id) {
            respond(['error' => 'No contractor profile is linked to this account.'], 403);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        respond(contractorScopeEmptyProjectList($page));
    }
}

// ── GET (list or single) ───────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        // Single project with expenses + milestones
        $projectWhere = 'p.id = ?';
        $projectParams = [$id];
        if ($contractorScopeId !== null) {
            $projectWhere .= ' AND p.contractor_id = ?';
            $projectParams[] = $contractorScopeId;
            $projectWhere .= " AND p.status IN ('assigned','active','delayed','on_hold','completed')";
        }

        $stmt = $db->prepare("
            SELECT p.*, c.name AS contractor_name, c.performance_score,
                   COALESCE(SUM(e.amount),0) AS total_spent,
                   (SELECT a.engineer_id FROM engineer_project_assignments a WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_id,
                   (SELECT u.full_name FROM engineer_project_assignments a INNER JOIN users u ON u.id = a.engineer_id WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_name,
                   (SELECT COUNT(*) FROM project_edit_requests r WHERE r.project_id = p.id AND r.status = 'pending') AS has_pending_edit_request
            FROM projects p
            LEFT JOIN contractors c ON c.id = p.contractor_id
            LEFT JOIN expenses    e ON e.project_id = p.id
            WHERE $projectWhere
            GROUP BY p.id
        ");
        $stmt->execute($projectParams);
        $project = $stmt->fetch();
        if (!$project) respond(['error' => 'Project not found'], 404);

        // Milestones
        $ms = $db->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date");
        $ms->execute([$id]);
        $project['milestones'] = $ms->fetchAll();

        // Recent expenses
        $ex = $db->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY expense_date DESC LIMIT 10");
        $ex->execute([$id]);
        $project['expenses'] = $ex->fetchAll();

        // Supporting documents — current version of each document slot only;
        // use action=document_versions to see a specific document's history.
        $docs = $db->prepare("
            SELECT id, document_type, title, original_name, file_path, file_size, version, status, created_at
            FROM supporting_documents
            WHERE owner_type = 'project' AND owner_id = ? AND is_current = 1
            ORDER BY created_at ASC
        ");
        $docs->execute([$id]);
        $project['documents'] = $docs->fetchAll();

        if ($project['category'] === 'Roads and Bridges') {
            $geoStmt = $db->prepare("SELECT * FROM project_road_geometry WHERE project_id = ?");
            $geoStmt->execute([$id]);
            $geometry = $geoStmt->fetch();
            if ($geometry) {
                $geometry['polyline_coordinates'] = json_decode((string) $geometry['polyline_coordinates'], true);
                $geometry['bounding_box'] = json_decode((string) $geometry['bounding_box'], true);
                $geometry['barangays_covered'] = json_decode((string) $geometry['barangays_covered'], true);
                $geometry['districts_covered'] = json_decode((string) $geometry['districts_covered'], true);
            }
            $project['road_geometry'] = $geometry ?: null;
        }

        $health = projectHealthScoreForOne($db, $id);
        $project['health_score'] = $health['score'];
        $project['health_status'] = $health['status'];
        $project['health_breakdown'] = $health['breakdown'];

        respond($project);
    }

    if ($action === 'document_versions') {
        $documentId = (int) ($_GET['document_id'] ?? 0);
        if ($documentId <= 0) {
            respond(['error' => 'Document ID is required.'], 422);
        }

        $root = $db->prepare("SELECT COALESCE(root_document_id, id) AS root_id FROM supporting_documents WHERE id = ? AND owner_type = 'project'");
        $root->execute([$documentId]);
        $rootId = $root->fetchColumn();
        if (!$rootId) {
            respond(['error' => 'Document not found.'], 404);
        }

        $versions = $db->prepare("
            SELECT id, title, original_name, file_path, file_size, version, is_current, status, uploaded_by, created_at, superseded_at
            FROM supporting_documents
            WHERE owner_type = 'project' AND (id = ? OR root_document_id = ?)
            ORDER BY version DESC
        ");
        $versions->execute([$rootId, $rootId]);
        respond(['data' => $versions->fetchAll()]);
    }

    // List with filters
    $where   = ['1=1'];
    $params  = [];

    if (!empty($_GET['status'])) {
        $where[]  = 'p.status = ?';
        $params[] = $_GET['status'];
    } elseif (!empty($_GET['status_in'])) {
        $statuses = array_values(array_intersect(
            array_map('trim', explode(',', (string) $_GET['status_in'])),
            projectWorkflowStatuses()
        ));
        if ($statuses !== []) {
            $where[] = 'p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            array_push($params, ...$statuses);
        }
    }
    if ($contractorScopeId !== null) {
        $where[]  = 'p.contractor_id = ?';
        $params[] = $contractorScopeId;
        $where[] = "p.status IN ('assigned','active','delayed','on_hold','completed')";
    } elseif (!empty($_GET['contractor_id'])) {
        $where[]  = 'p.contractor_id = ?';
        $params[] = (int) $_GET['contractor_id'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(p.name LIKE ? OR p.project_code LIKE ? OR p.location LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s, $s);
    }
    if (is_numeric($_GET['min_budget'] ?? null)) {
        $where[]  = 'p.budget >= ?';
        $params[] = (float) $_GET['min_budget'];
    }
    if (is_numeric($_GET['max_budget'] ?? null)) {
        $where[]  = 'p.budget <= ?';
        $params[] = (float) $_GET['max_budget'];
    }
    if (!empty($_GET['has_coordinates'])) {
        $where[] = 'p.latitude IS NOT NULL AND p.longitude IS NOT NULL';
    }

    $whereSQL = implode(' AND ', $where);
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $limit    = min(100, max(1, (int) ($_GET['_limit'] ?? 10)));
    $offset   = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM projects p WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name,
               COALESCE(SUM(e.amount),0) AS total_spent,
               (SELECT a.engineer_id FROM engineer_project_assignments a WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_id,
               (SELECT u.full_name FROM engineer_project_assignments a INNER JOIN users u ON u.id = a.engineer_id WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_name,
               (SELECT COUNT(*) FROM project_edit_requests r WHERE r.project_id = p.id AND r.status = 'pending') AS has_pending_edit_request
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN expenses e    ON e.project_id = p.id
        WHERE $whereSQL
        GROUP BY p.id
        ORDER BY p.updated_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $healthScores = projectHealthScoresForIds($db, array_column($rows, 'id'));
    foreach ($rows as &$row) {
        $health = $healthScores[(int) $row['id']] ?? ['score' => 0, 'status' => 'critical'];
        $row['health_score'] = $health['score'];
        $row['health_status'] = $health['status'];
    }
    unset($row);

    respond([
        'data'       => $rows,
        'total'      => $totalRows,
        'page'       => $page,
        'last_page'  => (int) ceil($totalRows / $limit),
    ]);
}

// ── POST action=decide (HOPE only — approve/return/reject) ──
if ($method === 'POST' && $action === 'decide') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'decision' => 'required|in:approve,return,reject',
        'reason' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $decision = (string) $validated['decision'];
    $reason = trim((string) ($validated['reason'] ?? ''));

    if ($decision !== 'approve' && $reason === '') {
        respond(['error' => 'A reason is required to return or reject a project.'], 422);
    }

    $stmt = $db->prepare("SELECT id, name, status, created_by FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if ($project['status'] !== 'endorsed') {
        respond(['error' => 'This project has not been endorsed by Engineering Review yet.'], 422);
    }

    $statusMap = ['approve' => 'approved', 'return' => 'returned', 'reject' => 'cancelled'];
    $pastTense = ['approve' => 'approved', 'return' => 'returned', 'reject' => 'rejected'];
    $newStatus = $statusMap[$decision];

    $db->prepare("
        UPDATE projects
        SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
        WHERE id = ?
    ")->execute([$newStatus, (int) ($user['user_id'] ?? 0), $reason !== '' ? $reason : null, $projectId]);

    $details = $project['name'] . ' was ' . $pastTense[$decision] . ($reason !== '' ? ' — ' . $reason : '') . '.';
    projectWorkflowLog($db, 'Project ' . $pastTense[$decision], $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_' . $newStatus, $details, 'Approvals', $projectId);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Project ' . $pastTense[$decision], $details);
    }

    respond(['success' => true, 'status' => $newStatus]);
}

// ── POST action=engineering_review (Engineer only — endorse/return/reject a draft project) ──
if ($method === 'POST' && $action === 'engineering_review') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'decision' => 'required|in:endorse,return,reject',
        'reason' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $decision = (string) $validated['decision'];
    $reason = trim((string) ($validated['reason'] ?? ''));

    if ($decision !== 'endorse' && $reason === '') {
        respond(['error' => 'A reason is required to return or reject a project.'], 422);
    }

    $stmt = $db->prepare("SELECT id, name, status, created_by FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if ($project['status'] !== 'draft') {
        respond(['error' => 'This project is not awaiting engineering review.'], 422);
    }

    $statusMap = ['endorse' => 'endorsed', 'return' => 'returned', 'reject' => 'cancelled'];
    $pastTense = ['endorse' => 'endorsed', 'return' => 'returned', 'reject' => 'rejected'];
    $newStatus = $statusMap[$decision];

    $db->prepare("
        UPDATE projects
        SET status = ?, engineering_reviewed_by = ?, engineering_reviewed_at = NOW(), engineering_remarks = ?
        WHERE id = ?
    ")->execute([$newStatus, (int) ($user['user_id'] ?? 0), $reason !== '' ? $reason : null, $projectId]);

    $details = $project['name'] . ' was ' . $pastTense[$decision] . ' by Engineering Review' . ($reason !== '' ? ' — ' . $reason : '') . '.';
    projectWorkflowLog($db, 'Engineering review: ' . $pastTense[$decision], $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_' . $newStatus, $details, 'Approvals', $projectId);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Engineering review: ' . $pastTense[$decision], $details);
    }

    respond(['success' => true, 'status' => $newStatus]);
}

// ── POST action=issue_ntp (Engineer/Admin — assigned -> active; the contract clock starts here, not at award) ──
if ($method === 'POST' && $action === 'issue_ntp') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'notes' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $notes = trim((string) ($validated['notes'] ?? ''));

    $stmt = $db->prepare("SELECT id, name, status, created_by, contractor_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if ($project['status'] !== 'assigned') {
        respond(['error' => 'Notice to Proceed can only be issued once a contractor is assigned.'], 422);
    }

    $db->prepare("
        UPDATE projects
        SET status = 'active', ntp_issued_by = ?, ntp_issued_at = NOW(), ntp_notes = ?
        WHERE id = ?
    ")->execute([(int) ($user['user_id'] ?? 0), $notes !== '' ? $notes : null, $projectId]);

    $details = 'Notice to Proceed issued for ' . $project['name'] . ' — the contract implementation period begins today.';
    projectWorkflowLog($db, 'Notice to Proceed issued', $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_active', $details, 'Projects', $projectId);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Notice to Proceed issued', $details);
    }
    if (!empty($project['contractor_id'])) {
        $contractorUserStmt = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
        $contractorUserStmt->execute([(int) $project['contractor_id']]);
        $contractorUserId = $contractorUserStmt->fetchColumn();
        if ($contractorUserId) {
            notifyUser((int) $contractorUserId, 'info', 'Notice to Proceed issued', $details);
        }
    }

    respond(['success' => true, 'status' => 'active']);
}

// ── POST action=request_completion_inspection (Engineer/Admin — final milestone reached) ──
if ($method === 'POST' && $action === 'request_completion_inspection') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];

    $stmt = $db->prepare("SELECT id, name, status, created_by, contractor_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if (!in_array($project['status'], ['active', 'delayed', 'on_hold'], true)) {
        respond(['error' => 'Only an active project can be sent for completion inspection.'], 422);
    }

    $db->prepare("UPDATE projects SET status = 'completion_inspection' WHERE id = ?")->execute([$projectId]);

    $details = $project['name'] . ' was submitted for completion inspection.';
    projectWorkflowLog($db, 'Completion inspection requested', $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_completion_inspection', $details, 'Inspections', $projectId);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Completion inspection requested', $details);
    }
    if (!empty($project['contractor_id'])) {
        $contractorUserStmt = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
        $contractorUserStmt->execute([(int) $project['contractor_id']]);
        $contractorUserId = $contractorUserStmt->fetchColumn();
        if ($contractorUserId) {
            notifyUser((int) $contractorUserId, 'info', 'Completion inspection scheduled', $details);
        }
    }

    respond(['success' => true, 'status' => 'completion_inspection']);
}

// ── POST action=completion_decide (Engineer only — accept as complete, or return with a punch-list) ──
if ($method === 'POST' && $action === 'completion_decide') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'decision' => 'required|in:accept,return',
        'reason' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $decision = (string) $validated['decision'];
    $reason = trim((string) ($validated['reason'] ?? ''));

    if ($decision === 'return' && $reason === '') {
        respond(['error' => 'A punch-list reason is required to return a project from completion inspection.'], 422);
    }

    $stmt = $db->prepare("SELECT id, name, status, created_by FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if ($project['status'] !== 'completion_inspection') {
        respond(['error' => 'This project is not awaiting a completion inspection decision.'], 422);
    }

    $newStatus = $decision === 'accept' ? 'completed' : 'active';

    $db->prepare("
        UPDATE projects
        SET status = ?, completion_inspected_by = ?, completion_inspected_at = NOW(), completion_remarks = ?, progress = IF(? = 'accept', 100, progress)
        WHERE id = ?
    ")->execute([$newStatus, (int) ($user['user_id'] ?? 0), $reason !== '' ? $reason : null, $decision, $projectId]);

    $verb = $decision === 'accept' ? 'accepted as complete' : 'returned with punch-list items';
    $details = $project['name'] . ' was ' . $verb . ($reason !== '' ? ' — ' . $reason : '') . '.';
    projectWorkflowLog($db, 'Completion inspection: ' . $verb, $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_' . $newStatus, $details, 'Inspections', $projectId);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Completion inspection: ' . $verb, $details);
    }

    respond(['success' => true, 'status' => $newStatus]);
}

// ── POST action=turnover (Admin only — completed -> turnover) ──
if ($method === 'POST' && $action === 'turnover') {
    $body = requestBody();
    $validated = Validator::make($body, [
        'project_id' => 'required|integer',
        'turnover_office' => 'required|string|max:180',
        'notes' => 'nullable|string|max:1000',
    ])->stopOnFailure();

    $projectId = (int) $validated['project_id'];
    $turnoverOffice = trim((string) $validated['turnover_office']);
    $notes = trim((string) ($validated['notes'] ?? ''));

    $stmt = $db->prepare("SELECT id, name, status, created_by FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }
    if ($project['status'] !== 'completed') {
        respond(['error' => 'Only a completed project can be turned over.'], 422);
    }

    $db->prepare("
        UPDATE projects
        SET status = 'turnover', turnover_by = ?, turnover_at = NOW(), turnover_office = ?, turnover_notes = ?
        WHERE id = ?
    ")->execute([(int) ($user['user_id'] ?? 0), $turnoverOffice, $notes !== '' ? $notes : null, $projectId]);

    $details = $project['name'] . ' was turned over to ' . $turnoverOffice . '.';
    projectWorkflowLog($db, 'Project turned over', $projectId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_turnover', $details, 'Projects', $projectId);

    respond(['success' => true, 'status' => 'turnover']);
}

// ── POST action=upload_document_version (Engineer/Admin — supersedes an existing project document) ──
if ($method === 'POST' && $action === 'upload_document_version') {
    $documentId = (int) ($_POST['document_id'] ?? 0);
    if ($documentId <= 0) {
        respond(['error' => 'Document ID is required.'], 422);
    }

    $doc = $db->prepare("SELECT id FROM supporting_documents WHERE id = ? AND owner_type = 'project' AND is_current = 1");
    $doc->execute([$documentId]);
    if (!$doc->fetchColumn()) {
        respond(['error' => 'Document not found, or it is not the current version.'], 404);
    }

    $error = FileUpload::validate($_FILES['file'] ?? null, [
        'required' => true,
        'max_size' => PROJECT_DOC_MAX_SIZE,
        'extensions' => PROJECT_DOC_EXTENSIONS,
    ]);
    if ($error !== null) {
        respond(['error' => $error], 422);
    }

    try {
        $stored = FileUpload::store($_FILES['file'], 'supporting-documents/project', [
            'max_size' => PROJECT_DOC_MAX_SIZE,
            'extensions' => PROJECT_DOC_EXTENSIONS,
        ]);
        $newDocId = documentsCreateNewVersion($db, $documentId, $stored, (int) ($user['user_id'] ?? 0));
    } catch (Throwable $e) {
        respond(['error' => 'Unable to upload new version.'], 422);
    }

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'document_uploaded', 'New version uploaded for document #' . $documentId . '.', 'Documents', $newDocId);

    respond(['success' => true, 'id' => $newDocId], 201);
}

// ── POST action=request_deletion (Admin/super_admin — submits a reasoned
//    request; HOPE reviews it via hope/api/portal.php's decide_deletion) ──
if ($method === 'POST' && $action === 'request_deletion') {
    if (!$id) respond(['error' => 'Project ID is required.'], 400);

    $body = requestBody();
    $reason = trim((string) ($body['reason'] ?? ''));
    if ($reason === '') {
        respond(['error' => 'A reason is required to request project deletion.'], 422);
    }

    $stmt = $db->prepare("SELECT id, project_code, name FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }

    $existing = $db->prepare("SELECT id FROM project_deletion_requests WHERE project_id = ? AND status = 'pending'");
    $existing->execute([$id]);
    if ($existing->fetchColumn()) {
        respond(['error' => 'A deletion request for this project is already pending HOPE review.'], 409);
    }

    $db->prepare("
        INSERT INTO project_deletion_requests (project_id, project_code, project_name, reason, requested_by)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$id, $project['project_code'], $project['name'], $reason, (int) ($user['user_id'] ?? 0) ?: null]);

    projectWorkflowLog($db, 'Project deletion requested', (int) $id, $project['name'] . ' (' . $project['project_code'] . ') — ' . $reason, (int) ($user['user_id'] ?? 0) ?: null);

    $hopeUserIds = $db->query("SELECT id FROM users WHERE role = 'hope' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($hopeUserIds as $hopeUserId) {
        notifyUser((int) $hopeUserId, 'info', 'Project deletion requested', $project['name'] . ' (' . $project['project_code'] . ') has a pending deletion request awaiting your review.');
    }

    respond(['success' => true, 'message' => 'Deletion request submitted for HOPE review.']);
}

// ── POST action=request_edit (Admin/super_admin — submits a reasoned set of
//    field changes; HOPE reviews it via hope/api/portal.php's decide_edit.
//    Same maker-checker shape as request_deletion above, but the request
//    carries the actual proposed values (as JSON) instead of just a reason,
//    since HOPE needs a before/after comparison to decide on. Only the
//    Project Registration page's Edit button goes through this — contractor/
//    engineer assignment and status-transition actions elsewhere in this
//    file are untouched and remain instant. ──
if ($method === 'POST' && $action === 'request_edit') {
    if (!$id) respond(['error' => 'Project ID is required.'], 400);

    $b = requestBody();
    $reason = trim((string) ($b['reason'] ?? ''));
    if ($reason === '') {
        respond(['error' => 'A reason is required to request a project edit.'], 422);
    }

    $before = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $before->execute([$id]);
    $project = $before->fetch();
    if (!$project) {
        respond(['error' => 'Project not found.'], 404);
    }

    $existing = $db->prepare("SELECT id FROM project_edit_requests WHERE project_id = ? AND status = 'pending'");
    $existing->execute([$id]);
    if ($existing->fetchColumn()) {
        respond(['error' => 'An edit request for this project is already pending HOPE review.'], 409);
    }

    // Same plain-field whitelist as the PUT handler below, minus
    // contractor_id/engineer_id/status — those belong to Contractor
    // Assignment and the dedicated workflow actions, not this form, and stay
    // instant rather than routing through HOPE.
    $editableFields = ['name', 'description', 'location', 'budget', 'start_date', 'end_date',
        'progress', 'latitude', 'longitude', 'category', 'funding_source', 'implementing_office', 'physical_target'];

    $proposedFields = [];
    foreach ($editableFields as $f) {
        if (!array_key_exists($f, $b)) {
            continue;
        }

        $newVal = $b[$f];
        if ($f === 'progress') {
            $newVal = max(0, min(100, (int) $newVal));
        } elseif ($f === 'budget') {
            $newVal = (float) $newVal;
        } elseif ($f === 'latitude' || $f === 'longitude') {
            $newVal = ($newVal === '' || $newVal === null) ? null : (float) $newVal;
        } else {
            $newVal = $newVal === '' ? null : $newVal;
        }

        if ($f === 'category' && $newVal !== null && !in_array($newVal, PROJECT_CATEGORIES, true)) {
            respond(['error' => 'Invalid project category'], 422);
        }
        if ($f === 'funding_source' && $newVal !== null && !in_array($newVal, PROJECT_FUNDING_SOURCES, true)) {
            respond(['error' => 'Invalid funding source'], 422);
        }

        $currentVal = $project[$f] ?? null;
        if ((string) $newVal !== (string) $currentVal) {
            $proposedFields[$f] = $newVal;
        }
    }

    $effectiveLat = array_key_exists('latitude', $proposedFields) ? $proposedFields['latitude'] : (isset($project['latitude']) ? (float) $project['latitude'] : null);
    $effectiveLng = array_key_exists('longitude', $proposedFields) ? $proposedFields['longitude'] : (isset($project['longitude']) ? (float) $project['longitude'] : null);
    if ($effectiveLat !== null && $effectiveLng !== null && !projectQcCoordinatesValid($effectiveLat, $effectiveLng)) {
        respond(['error' => 'The pinned location must be within Quezon City.'], 422);
    }

    // Road Geometry: validated now (so the admin gets immediate feedback on a
    // bad drawing) but only actually stored once HOPE approves.
    $proposedRoadGeometry = null;
    $effectiveCategory = $proposedFields['category'] ?? $project['category'] ?? null;
    if ($effectiveCategory === 'Roads and Bridges' && array_key_exists('road_geometry', $b)) {
        $roadGeometryError = null;
        $proposedRoadGeometry = projectValidateRoadGeometry($b['road_geometry'], $roadGeometryError);
        if ($proposedRoadGeometry === null) {
            respond(['error' => $roadGeometryError], 422);
        }
    }

    if ($proposedFields === [] && $proposedRoadGeometry === null) {
        respond(['error' => 'No changes were made.'], 422);
    }

    $payload = ['fields' => $proposedFields, 'road_geometry' => $proposedRoadGeometry];

    $db->prepare("
        INSERT INTO project_edit_requests (project_id, project_code, project_name, proposed_changes, reason, requested_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$id, $project['project_code'], $project['name'], json_encode($payload), $reason, (int) ($user['user_id'] ?? 0) ?: null]);

    projectWorkflowLog($db, 'Project edit requested', (int) $id, $project['name'] . ' (' . $project['project_code'] . ') — ' . $reason, (int) ($user['user_id'] ?? 0) ?: null);

    $hopeUserIds = $db->query("SELECT id FROM users WHERE role = 'hope' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($hopeUserIds as $hopeUserId) {
        notifyUser((int) $hopeUserId, 'info', 'Project edit requested', $project['name'] . ' (' . $project['project_code'] . ') has a pending edit request awaiting your review.');
    }

    respond(['success' => true, 'message' => 'Edit request submitted for HOPE review.']);
}

// ── POST (create) ──────────────────────────────────────────
if ($method === 'POST') {
    $b = $_POST;
    $required = ['name', 'budget', 'start_date', 'end_date', 'location', 'description'];
    foreach ($required as $f) {
        if (empty($b[$f])) respond(['error' => "Field '$f' is required"], 422);
    }

    $documentRows = projectCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
    if ($documentRows === []) {
        respond(['error' => 'At least one supporting document (e.g. feasibility study, site assessment, budget justification) is required.'], 422);
    }
    if (count($documentRows) > PROJECT_DOC_ROW_LIMIT) {
        respond(['error' => 'A maximum of ' . PROJECT_DOC_ROW_LIMIT . ' supporting documents is allowed per project.'], 422);
    }
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
        }
    }

    if (isset($b['latitude']) && $b['latitude'] !== '' && isset($b['longitude']) && $b['longitude'] !== ''
        && !projectQcCoordinatesValid((float) $b['latitude'], (float) $b['longitude'])) {
        respond(['error' => 'The pinned location must be within Quezon City.'], 422);
    }

    if (!empty($b['category']) && !in_array($b['category'], PROJECT_CATEGORIES, true)) {
        respond(['error' => 'Invalid project category'], 422);
    }
    if (!empty($b['funding_source']) && !in_array($b['funding_source'], PROJECT_FUNDING_SOURCES, true)) {
        respond(['error' => 'Invalid funding source'], 422);
    }

    // Road Geometry: conditional on category, exactly like the spec requires —
    // every other category never even looks at $b['road_geometry'].
    $roadGeometry = null;
    if (($b['category'] ?? '') === 'Roads and Bridges') {
        $roadGeometryError = null;
        $roadGeometry = projectValidateRoadGeometry($b['road_geometry'] ?? null, $roadGeometryError);
        if ($roadGeometry === null) {
            respond(['error' => $roadGeometryError], 422);
        }
    }

    // Auto project code
    $last = (int) $db->query("SELECT COUNT(*) FROM projects")->fetchColumn() + 1;
    $code = 'PRJ-' . str_pad($last, 3, '0', STR_PAD_LEFT);
    // Registration always starts at 'draft' — only the decide action (super_admin
    // only) can move a project to 'approved', so a caller can no longer skip the
    // review step by passing an arbitrary initial status.
    $status = 'draft';

    $storedFiles = [];
    $newId = null;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO projects
                (project_code, name, description, location, contractor_id,
                 budget, start_date, end_date, progress, status, created_by, latitude, longitude,
                 category, funding_source, implementing_office, physical_target)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $code,
            $b['name'],
            $b['description'],
            $b['location'],
            !empty($b['contractor_id']) ? (int) $b['contractor_id'] : null,
            (float) $b['budget'],
            $b['start_date'],
            $b['end_date'],
            (int) ($b['progress'] ?? 0),
            $status,
            (int) ($user['user_id'] ?? 0) ?: null,
            isset($b['latitude']) && $b['latitude'] !== '' ? (float) $b['latitude'] : null,
            isset($b['longitude']) && $b['longitude'] !== '' ? (float) $b['longitude'] : null,
            !empty($b['category']) ? $b['category'] : null,
            !empty($b['funding_source']) ? $b['funding_source'] : null,
            !empty($b['implementing_office']) ? trim($b['implementing_office']) : null,
            !empty($b['physical_target']) ? trim($b['physical_target']) : null,
        ]);

        $newId = (int) $db->lastInsertId();
        projectStoreDocuments($db, $documentRows, $newId, (int) ($user['user_id'] ?? 0), $storedFiles);

        if ($roadGeometry !== null) {
            projectStoreRoadGeometry($db, $newId, $roadGeometry);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        projectCleanupFiles($storedFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to register project.'], 422);
    }

    $details = $b['name'] . ' was registered with status ' . $status . ' and ' . count($documentRows) . ' supporting document(s).';
    projectWorkflowLog($db, 'Project registered', $newId, $details, (int) ($user['user_id'] ?? 0) ?: null);
    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_created', $details, 'Projects', $newId);

    respond(['id' => $newId, 'project_code' => $code], 201);
}

// ── PUT (update) ───────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();
    $beforeStmt = $db->prepare("SELECT id, name, status, contractor_id, latitude, longitude, category FROM projects WHERE id = ?");
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch();
    if (!$before) {
        respond(['error' => 'Project not found'], 404);
    }

    $fields = [];
    $params = [];
    $touchedFields = [];
    $allowed = ['name','description','location','contractor_id','budget',
                'start_date','end_date','progress','status','latitude','longitude',
                'category','funding_source','implementing_office','physical_target'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) {
            if ($f === 'status' && !in_array((string) $b[$f], projectWorkflowStatuses(), true)) {
                respond(['error' => 'Invalid project status'], 422);
            }
            if ($f === 'status' && in_array((string) $b[$f], ['endorsed', 'approved', 'completion_inspection', 'completed', 'turnover'], true)) {
                respond(['error' => 'This status can only be reached through its dedicated workflow action, not a direct edit.'], 422);
            }
            if ($f === 'category' && $b[$f] !== '' && !in_array($b[$f], PROJECT_CATEGORIES, true)) {
                respond(['error' => 'Invalid project category'], 422);
            }
            if ($f === 'funding_source' && $b[$f] !== '' && !in_array($b[$f], PROJECT_FUNDING_SOURCES, true)) {
                respond(['error' => 'Invalid funding source'], 422);
            }

            $fields[] = "$f = ?";
            $touchedFields[] = $f;
            if ($f === 'contractor_id') {
                $params[] = $b[$f] === '' || $b[$f] === null ? null : (int) $b[$f];
            } elseif ($f === 'progress') {
                $params[] = max(0, min(100, (int) $b[$f]));
            } elseif ($f === 'budget') {
                $params[] = (float) $b[$f];
            } elseif ($f === 'latitude' || $f === 'longitude') {
                $params[] = $b[$f] === '' || $b[$f] === null ? null : (float) $b[$f];
            } else {
                $params[] = $b[$f] === '' ? null : $b[$f];
            }
        }
    }
    if (array_key_exists('latitude', $b) || array_key_exists('longitude', $b)) {
        $effectiveLat = array_key_exists('latitude', $b)
            ? ($b['latitude'] === '' || $b['latitude'] === null ? null : (float) $b['latitude'])
            : (isset($before['latitude']) ? (float) $before['latitude'] : null);
        $effectiveLng = array_key_exists('longitude', $b)
            ? ($b['longitude'] === '' || $b['longitude'] === null ? null : (float) $b['longitude'])
            : (isset($before['longitude']) ? (float) $before['longitude'] : null);

        if ($effectiveLat !== null && $effectiveLng !== null && !projectQcCoordinatesValid($effectiveLat, $effectiveLng)) {
            respond(['error' => 'The pinned location must be within Quezon City.'], 422);
        }
    }

    $engineerId = array_key_exists('engineer_id', $b) && $b['engineer_id'] !== ''
        ? (int) $b['engineer_id']
        : null;

    // Road Geometry: only touched when the caller actually sends it, and only
    // ever stored when the (possibly just-changed) category is Roads and
    // Bridges — every other category's edit flow never looks at this field.
    $roadGeometry = null;
    $effectiveCategory = array_key_exists('category', $b) ? $b['category'] : ($before['category'] ?? null);
    if ($effectiveCategory === 'Roads and Bridges' && array_key_exists('road_geometry', $b)) {
        $roadGeometryError = null;
        $roadGeometry = projectValidateRoadGeometry($b['road_geometry'], $roadGeometryError);
        if ($roadGeometry === null) {
            respond(['error' => $roadGeometryError], 422);
        }
    }

    if (empty($fields) && $engineerId === null && $roadGeometry === null) respond(['error' => 'Nothing to update'], 422);

    $db->beginTransaction();
    try {
        if (!empty($fields)) {
            $params[] = $id;
            $db->prepare("UPDATE projects SET " . implode(', ', $fields) . " WHERE id = ?")
               ->execute($params);
        }

        if ($engineerId !== null) {
            $engineer = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'engineer' AND status = 'active'");
            $engineer->execute([$engineerId]);
            if (!$engineer->fetchColumn()) {
                $db->rollBack();
                respond(['error' => 'Active engineer not found'], 422);
            }

            $notes = trim((string) ($b['assignment_notes'] ?? 'Assigned from contractor assignment workflow.'));
            $db->prepare("
                INSERT INTO engineer_project_assignments
                    (engineer_id, project_id, assigned_by, assignment_notes, status)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                    assigned_by = VALUES(assigned_by),
                    assignment_notes = VALUES(assignment_notes),
                    status = 'active'
            ")->execute([
                $engineerId,
                $id,
                (int) ($user['user_id'] ?? 0) ?: null,
                $notes !== '' ? $notes : null,
            ]);
        }

        if ($roadGeometry !== null) {
            projectStoreRoadGeometry($db, $id, $roadGeometry);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        respond(['error' => 'Unable to update project'], 500);
    }

    $plainFieldChanges = array_diff($touchedFields, ['status', 'contractor_id']);
    if (!empty($plainFieldChanges)) {
        $fieldLabels = [
            'name' => 'Name', 'description' => 'Description', 'location' => 'Location',
            'budget' => 'Budget', 'start_date' => 'Start Date', 'end_date' => 'End Date',
            'progress' => 'Progress', 'latitude' => 'Latitude', 'longitude' => 'Longitude',
            'category' => 'Category', 'funding_source' => 'Funding Source',
            'implementing_office' => 'Implementing Office', 'physical_target' => 'Physical Target',
        ];
        $changedLabels = array_map(fn($f) => $fieldLabels[$f] ?? $f, $plainFieldChanges);
        $updateDetails = $before['name'] . ' — updated: ' . implode(', ', $changedLabels) . '.';
        projectWorkflowLog($db, 'Project updated', $id, $updateDetails, (int) ($user['user_id'] ?? 0) ?: null);
        logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_updated', $updateDetails, 'Projects', $id);
    }

    if (isset($b['status']) && $b['status'] !== $before['status']) {
        $statusDetails = $before['name'] . ' changed from ' . $before['status'] . ' to ' . $b['status'] . '.';
        projectWorkflowLog($db, 'Project status updated', $id, $statusDetails, (int) ($user['user_id'] ?? 0) ?: null);
        logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_status_updated', $statusDetails, 'Projects', $id);

        $statusRecipients = [];
        if (!empty($before['contractor_id'])) {
            $cu = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
            $cu->execute([$before['contractor_id']]);
            $statusRecipients[] = (int) ($cu->fetchColumn() ?: 0);
        }
        $eu = $db->prepare("SELECT engineer_id FROM engineer_project_assignments WHERE project_id = ? AND status = 'active' ORDER BY assigned_at DESC LIMIT 1");
        $eu->execute([$id]);
        $statusRecipients[] = (int) ($eu->fetchColumn() ?: 0);

        foreach (array_unique(array_filter($statusRecipients)) as $recipientId) {
            notifyUser($recipientId, 'info', 'Project status updated', $before['name'] . ' is now "' . $b['status'] . '".');
        }
    }
    if (array_key_exists('contractor_id', $b) && (string) ($b['contractor_id'] ?? '') !== (string) ($before['contractor_id'] ?? '')) {
        $contractorAssignDetails = $before['name'] . ' contractor assignment was updated.';
        projectWorkflowLog($db, 'Contractor assignment updated', $id, $contractorAssignDetails, (int) ($user['user_id'] ?? 0) ?: null);
        logActivity((int) ($user['user_id'] ?? 0) ?: null, 'contractor_assignment_updated', $contractorAssignDetails, 'Contractors', $id);

        if (!empty($b['contractor_id'])) {
            $cu = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
            $cu->execute([(int) $b['contractor_id']]);
            $contractorUserId = (int) ($cu->fetchColumn() ?: 0);
            notifyUser($contractorUserId, 'info', 'New project assignment', 'You have been assigned to ' . $before['name'] . '.');
        }
    }
    if ($engineerId !== null) {
        $engineerAssignDetails = $before['name'] . ' was assigned for field monitoring.';
        projectWorkflowLog($db, 'Engineer assignment updated', $id, $engineerAssignDetails, (int) ($user['user_id'] ?? 0) ?: null);
        logActivity((int) ($user['user_id'] ?? 0) ?: null, 'engineer_assignment_updated', $engineerAssignDetails, 'Projects', $id);
        notifyUser($engineerId, 'info', 'New project assignment', 'You have been assigned to ' . $before['name'] . ' for field monitoring.');
    }

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    respond(['error' => 'Direct project deletion is disabled. Submit a deletion request (action=request_deletion) for HOPE review instead.'], 410);
}

respond(['error' => 'Method not allowed'], 405);
