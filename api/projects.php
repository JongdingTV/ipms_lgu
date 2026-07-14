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
apiHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method === 'GET') {
    requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen']);
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
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user   = currentUser();
$isContractor = ($user['role'] ?? '') === 'contractor';
$contractorScopeId = null;

const PROJECT_DOC_MAX_SIZE = 10 * 1024 * 1024;
const PROJECT_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];

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
                   (SELECT u.full_name FROM engineer_project_assignments a INNER JOIN users u ON u.id = a.engineer_id WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_name
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

        respond($project);
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
               (SELECT u.full_name FROM engineer_project_assignments a INNER JOIN users u ON u.id = a.engineer_id WHERE a.project_id = p.id AND a.status = 'active' ORDER BY a.assigned_at DESC LIMIT 1) AS assigned_engineer_name
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN expenses e    ON e.project_id = p.id
        WHERE $whereSQL
        GROUP BY p.id
        ORDER BY p.updated_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    respond([
        'data'       => $stmt->fetchAll(),
        'total'      => $totalRows,
        'page'       => $page,
        'last_page'  => (int) ceil($totalRows / $limit),
    ]);
}

// ── POST action=decide (super_admin only — approve/return/reject) ──
if ($method === 'POST' && $action === 'decide') {
    requireAnyRole(['super_admin']);

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
    logActivity((int) ($user['user_id'] ?? 0), 'project_status_' . $newStatus, $details);

    if (!empty($project['created_by'])) {
        notifyUser((int) $project['created_by'], 'info', 'Project ' . $pastTense[$decision], $details);
    }

    respond(['success' => true, 'status' => $newStatus]);
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
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
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
                 budget, start_date, end_date, progress, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
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
        ]);

        $newId = (int) $db->lastInsertId();
        projectStoreDocuments($db, $documentRows, $newId, (int) ($user['user_id'] ?? 0), $storedFiles);

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

    respond(['id' => $newId, 'project_code' => $code], 201);
}

// ── PUT (update) ───────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();
    $beforeStmt = $db->prepare("SELECT id, name, status, contractor_id FROM projects WHERE id = ?");
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch();
    if (!$before) {
        respond(['error' => 'Project not found'], 404);
    }

    $fields = [];
    $params = [];
    $allowed = ['name','description','location','contractor_id','budget',
                'start_date','end_date','progress','status'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) {
            if ($f === 'status' && !in_array((string) $b[$f], projectWorkflowStatuses(), true)) {
                respond(['error' => 'Invalid project status'], 422);
            }
            if ($f === 'status' && (string) $b[$f] === 'approved') {
                respond(['error' => 'Use the project approval action to approve a project.'], 422);
            }

            $fields[] = "$f = ?";
            if ($f === 'contractor_id') {
                $params[] = $b[$f] === '' || $b[$f] === null ? null : (int) $b[$f];
            } elseif ($f === 'progress') {
                $params[] = max(0, min(100, (int) $b[$f]));
            } elseif ($f === 'budget') {
                $params[] = (float) $b[$f];
            } else {
                $params[] = $b[$f] === '' ? null : $b[$f];
            }
        }
    }
    $engineerId = array_key_exists('engineer_id', $b) && $b['engineer_id'] !== ''
        ? (int) $b['engineer_id']
        : null;
    if (empty($fields) && $engineerId === null) respond(['error' => 'Nothing to update'], 422);

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

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        respond(['error' => 'Unable to update project'], 500);
    }

    if (isset($b['status']) && $b['status'] !== $before['status']) {
        projectWorkflowLog($db, 'Project status updated', $id, $before['name'] . ' changed from ' . $before['status'] . ' to ' . $b['status'] . '.', (int) ($user['user_id'] ?? 0) ?: null);

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
        projectWorkflowLog($db, 'Contractor assignment updated', $id, $before['name'] . ' contractor assignment was updated.', (int) ($user['user_id'] ?? 0) ?: null);

        if (!empty($b['contractor_id'])) {
            $cu = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
            $cu->execute([(int) $b['contractor_id']]);
            $contractorUserId = (int) ($cu->fetchColumn() ?: 0);
            notifyUser($contractorUserId, 'info', 'New project assignment', 'You have been assigned to ' . $before['name'] . '.');
        }
    }
    if ($engineerId !== null) {
        projectWorkflowLog($db, 'Engineer assignment updated', $id, $before['name'] . ' was assigned for field monitoring.', (int) ($user['user_id'] ?? 0) ?: null);
        notifyUser($engineerId, 'info', 'New project assignment', 'You have been assigned to ' . $before['name'] . ' for field monitoring.');
    }

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
