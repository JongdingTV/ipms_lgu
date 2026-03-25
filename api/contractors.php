<?php
// ============================================================
// api/contractors.php — Contractors CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
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

    if (!empty($_GET['status'])) {
        $where[]  = 'status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(name LIKE ? OR contact_person LIKE ? OR email LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s, $s);
    }

    $whereSQL = implode(' AND ', $where);
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM contractors WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT c.*,
               COUNT(p.id)    AS total_projects,
               SUM(CASE WHEN p.status='active'    THEN 1 ELSE 0 END) AS active_projects,
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
