<?php
// ============================================================
// api/projects.php — Projects CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../contractor/includes/scope.php';
require_once __DIR__ . '/../engineer/includes/scope.php';
require_once __DIR__ . '/../includes/workflow.php';
apiHeaders();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen']);
} else {
    requireAnyRole(['super_admin', 'admin', 'engineer']);
}

requireCsrfProtection();

$db     = getDB();
engineerScopeEnsureTables($db);
projectWorkflowEnsureProjectStatusSchema($db);
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user   = currentUser();
$isContractor = ($user['role'] ?? '') === 'contractor';
$contractorScopeId = null;

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

// ── POST (create) ──────────────────────────────────────────
if ($method === 'POST') {
    $b = requestBody();
    $required = ['name', 'budget', 'start_date', 'end_date'];
    foreach ($required as $f) {
        if (empty($b[$f])) respond(['error' => "Field '$f' is required"], 422);
    }

    // Auto project code
    $last = (int) $db->query("SELECT COUNT(*) FROM projects")->fetchColumn() + 1;
    $code = 'PRJ-' . str_pad($last, 3, '0', STR_PAD_LEFT);
    $status = $b['status'] ?? 'draft';
    if (!in_array($status, projectWorkflowStatuses(), true)) {
        respond(['error' => 'Invalid project status'], 422);
    }

    $stmt = $db->prepare("
        INSERT INTO projects
            (project_code, name, description, location, contractor_id,
             budget, start_date, end_date, progress, status)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $code,
        $b['name'],
        $b['description']   ?? null,
        $b['location']      ?? null,
        !empty($b['contractor_id']) ? (int) $b['contractor_id'] : null,
        (float) $b['budget'],
        $b['start_date'],
        $b['end_date'],
        (int) ($b['progress'] ?? 0),
        $status,
    ]);

    $newId = (int) $db->lastInsertId();
    projectWorkflowLog($db, 'Project registered', $newId, $b['name'] . ' was created with status ' . $status . '.', (int) ($user['user_id'] ?? 0) ?: null);

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
    }
    if (array_key_exists('contractor_id', $b) && (string) ($b['contractor_id'] ?? '') !== (string) ($before['contractor_id'] ?? '')) {
        projectWorkflowLog($db, 'Contractor assignment updated', $id, $before['name'] . ' contractor assignment was updated.', (int) ($user['user_id'] ?? 0) ?: null);
    }
    if ($engineerId !== null) {
        projectWorkflowLog($db, 'Engineer assignment updated', $id, $before['name'] . ' was assigned for field monitoring.', (int) ($user['user_id'] ?? 0) ?: null);
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
