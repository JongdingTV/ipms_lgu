<?php
// ============================================================
// api/projects.php — Projects CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET (list or single) ───────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        // Single project with expenses + milestones
        $stmt = $db->prepare("
            SELECT p.*, c.name AS contractor_name, c.performance_score,
                   COALESCE(SUM(e.amount),0) AS total_spent
            FROM projects p
            LEFT JOIN contractors c ON c.id = p.contractor_id
            LEFT JOIN expenses    e ON e.project_id = p.id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$id]);
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
    }
    if (!empty($_GET['contractor_id'])) {
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
    $limit    = 10;
    $offset   = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM projects p WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name,
               COALESCE(SUM(e.amount),0) AS total_spent
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
        $b['status'] ?? 'planning',
    ]);

    respond(['id' => (int) $db->lastInsertId(), 'project_code' => $code], 201);
}

// ── PUT (update) ───────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();

    $fields = [];
    $params = [];
    $allowed = ['name','description','location','contractor_id','budget',
                'start_date','end_date','progress','status'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $params[] = $b[$f] === '' ? null : $b[$f];
        }
    }
    if (empty($fields)) respond(['error' => 'Nothing to update'], 422);

    $params[] = $id;
    $db->prepare("UPDATE projects SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
