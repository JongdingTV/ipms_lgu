<?php
// ============================================================
// api/contractors.php — Contractors CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../contractor/includes/scope.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/ContractorScoring.php';
apiHeaders();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'contractor']);
} else {
    requireAnyRole(['super_admin', 'admin']);
}

requireCsrfProtection();

$db     = getDB();
projectWorkflowEnsureProjectStatusSchema($db);
contractorsEnsureApplicationSchema($db);
if ($method === 'GET') {
    contractorRefreshPerformanceScores($db);
}
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

        respond([
            'data' => [],
            'total' => 0,
            'page' => 1,
            'last_page' => 0,
        ]);
    }

    if ($id && $id !== $contractorScopeId) {
        respond(['error' => 'Access denied'], 403);
    }
}

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($contractorScopeId !== null && $id) {
        $id = $contractorScopeId;
    }

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM contractors WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) respond(['error' => 'Not found'], 404);

        // Their projects
        $proj = $db->prepare("
            SELECT id, project_code, name, status, progress, budget,
                   COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id=p.id),0) AS spent
            FROM projects p WHERE contractor_id = ?
            ORDER BY updated_at DESC
        ");
        $proj->execute([$id]);
        $row['projects'] = $proj->fetchAll();

        respond($row);
    }

    // List — only approved applications are eligible for assignment/bidding;
    // pending/rejected applications are reviewed separately by BAC.
    $where  = ["c.application_status = 'approved'"];
    $params = [];

    if ($contractorScopeId !== null) {
        $where[] = 'c.id = ?';
        $params[] = $contractorScopeId;
    }
    if (!empty($_GET['status'])) {
        $where[]  = 'c.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(c.name LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s, $s);
    }

    $whereSQL = implode(' AND ', $where);
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['_limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM contractors c WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT c.*,
               COUNT(p.id)    AS total_projects,
               SUM(CASE WHEN p.status IN ('assigned','active','delayed','on_hold') THEN 1 ELSE 0 END) AS active_projects,
               SUM(CASE WHEN p.status='delayed'   THEN 1 ELSE 0 END) AS delayed_projects,
               SUM(CASE WHEN p.status='completed' THEN 1 ELSE 0 END) AS completed_projects
        FROM contractors c
        LEFT JOIN projects p ON p.contractor_id = c.id
        WHERE $whereSQL
        GROUP BY c.id
        ORDER BY c.performance_score DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    respond([
        'data'      => $stmt->fetchAll(),
        'total'     => $totalRows,
        'page'      => $page,
        'last_page' => (int) ceil($totalRows / $limit),
    ]);
}

// ── POST ───────────────────────────────────────────────────
// Direct creation is retired — new contractors must go through the public
// application at contractor/apply.php, reviewed by BAC before an account
// exists. Editing an already-approved contractor's profile (PUT) still works.
if ($method === 'POST') {
    respond(['error' => 'Contractors can no longer be added directly. New contractors must apply at /contractor/apply.php for BAC review.'], 410);
}

// ── PUT ────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();

    $fields = [];
    $params = [];
    // performance_score is intentionally excluded: it's computed by
    // contractorRefreshPerformanceScores() from real project outcomes,
    // not manually editable.
    foreach (['name','contact_person','email','phone','address','status'] as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $params[] = $b[$f];
        }
    }
    if (empty($fields)) respond(['error' => 'Nothing to update'], 422);
    $params[] = $id;

    $db->prepare("UPDATE contractors SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $check = $db->prepare("SELECT COUNT(*) FROM projects WHERE contractor_id = ?");
    $check->execute([$id]);
    if ((int) $check->fetchColumn() > 0) {
        respond(['error' => 'Cannot delete: contractor has assigned projects'], 409);
    }
    $db->prepare("DELETE FROM contractors WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
