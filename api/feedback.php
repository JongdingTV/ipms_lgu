<?php
// ============================================================
// api/feedback.php — Feedback & Complaints CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("
            SELECT f.*, p.name AS project_name
            FROM feedback f
            LEFT JOIN projects p ON p.id = f.project_id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) respond(['error' => 'Not found'], 404);
        respond($row);
    }

    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[]  = 'f.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['priority'])) {
        $where[]  = 'f.priority = ?';
        $params[] = $_GET['priority'];
    }
    if (!empty($_GET['project_id'])) {
        $where[]  = 'f.project_id = ?';
        $params[] = (int) $_GET['project_id'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(f.message LIKE ? OR f.citizen_name LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s);
    }

    $whereSQL = implode(' AND ', $where);
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM feedback f WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT f.*, p.name AS project_name
        FROM feedback f
        LEFT JOIN projects p ON p.id = f.project_id
        WHERE $whereSQL
        ORDER BY FIELD(f.priority,'urgent','high','medium','low'), f.created_at DESC
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
    if (empty($b['message'])) respond(['error' => "'message' is required"], 422);

    $stmt = $db->prepare("
        INSERT INTO feedback (project_id, citizen_name, message, category, priority, status)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([
        !empty($b['project_id']) ? (int) $b['project_id'] : null,
        $b['citizen_name'] ?? null,
        $b['message'],
        $b['category']  ?? 'complaint',
        $b['priority']  ?? 'medium',
        $b['status']    ?? 'open',
    ]);

    respond(['id' => (int) $db->lastInsertId()], 201);
}

// ── PUT (update status / priority) ────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();

    $fields = [];
    $params = [];
    foreach (['project_id','citizen_name','message','category','priority','status'] as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $params[] = $b[$f];
        }
    }
    if (empty($fields)) respond(['error' => 'Nothing to update'], 422);
    $params[] = $id;

    $db->prepare("UPDATE feedback SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
