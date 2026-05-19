<?php
// ============================================================
// api/contractors.php — Contractors CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../contractor/includes/scope.php';
require_once __DIR__ . '/../includes/workflow.php';
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

    // List
    $where  = ['1=1'];
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
if ($method === 'POST') {
    $b = requestBody();
    if (empty($b['name'])) respond(['error' => "'name' is required"], 422);

    $stmt = $db->prepare("
        INSERT INTO contractors
            (name, contact_person, email, phone, address, performance_score, status)
        VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([
             $b['name'],
             $b['contact_person']    ?? null,
             $b['email']             ?? null,
             $b['phone']             ?? null,
             $b['address']           ?? null,
        (int)($b['performance_score'] ?? 0),
             $b['status']            ?? 'active',
    ]);

    respond(['id' => (int) $db->lastInsertId()], 201);
}

// ── PUT ────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();

    $fields = [];
    $params = [];
    foreach (['name','contact_person','email','phone','address','performance_score','status'] as $f) {
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
