<?php
// ============================================================
// api/expenses.php — Budget & Expenses CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
apiHeaders();

requireCsrfProtection();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    // Budget summary per project
    if (!empty($_GET['summary'])) {
        $rows = $db->query("
            SELECT p.id, p.project_code, p.name AS project_name,
                   p.budget,
                   COALESCE(SUM(e.amount),0)   AS total_spent,
                   COALESCE(SUM(CASE WHEN e.flagged=1 THEN e.amount ELSE 0 END),0) AS flagged_amount,
                   COUNT(CASE WHEN e.flagged=1 THEN 1 END) AS flag_count,
                   p.budget - COALESCE(SUM(e.amount),0) AS remaining
            FROM projects p
            LEFT JOIN expenses e ON e.project_id = p.id
            WHERE p.status NOT IN ('cancelled')
            GROUP BY p.id
            ORDER BY (total_spent / NULLIF(p.budget,0)) DESC
        ")->fetchAll();
        respond(['data' => $rows]);
    }

    if ($id) {
        $stmt = $db->prepare("
            SELECT e.*, p.name AS project_name
            FROM expenses e
            JOIN projects p ON p.id = e.project_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) respond(['error' => 'Not found'], 404);
        respond($row);
    }

    // List with optional filters
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['project_id'])) {
        $where[]  = 'e.project_id = ?';
        $params[] = (int) $_GET['project_id'];
    }
    if (!empty($_GET['flagged'])) {
        $where[]  = 'e.flagged = 1';
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(e.description LIKE ? OR e.category LIKE ? OR p.name LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s, $s);
    }

    $whereSQL = implode(' AND ', $where);
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $limit    = 15;
    $offset   = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM expenses e JOIN projects p ON p.id=e.project_id WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT e.*, p.name AS project_name, p.budget AS project_budget
        FROM expenses e
        JOIN projects p ON p.id = e.project_id
        WHERE $whereSQL
        ORDER BY e.expense_date DESC
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
    foreach (['project_id','amount','expense_date'] as $f) {
        if (empty($b[$f])) respond(['error' => "Field '$f' is required"], 422);
    }

    // Auto-flag if this pushes project over budget
    $budget = (float) $db->prepare("SELECT budget FROM projects WHERE id=?")
        ->execute([$b['project_id']]) ?: 0;
    $spent = (float) $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id=?")
        ->execute([$b['project_id']]) ?: 0;
    $newAmount = (float) $b['amount'];
    $autoFlag  = ($spent + $newAmount) > $budget ? 1 : 0;

    $stmt = $db->prepare("
        INSERT INTO expenses (project_id, category, description, amount, expense_date, flagged)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([
        (int)   $b['project_id'],
                $b['category']    ?? 'General',
                $b['description'] ?? null,
        (float) $newAmount,
                $b['expense_date'],
        (int)   ($b['flagged'] ?? $autoFlag),
    ]);

    respond(['id' => (int) $db->lastInsertId()], 201);
}

// ── PUT ────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $b = requestBody();

    $fields = [];
    $params = [];
    foreach (['project_id','category','description','amount','expense_date','flagged'] as $f) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $params[] = $b[$f];
        }
    }
    if (empty($fields)) respond(['error' => 'Nothing to update'], 422);
    $params[] = $id;

    $db->prepare("UPDATE expenses SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
