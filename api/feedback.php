<?php
// ============================================================
// api/feedback.php — Feedback & Complaints CRUD
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/Notifications.php';
apiHeaders();

// Staff-only endpoint: citizens submit via citizen/api/submit-feedback.php and
// read their own records via citizen/api/my-feedback.php, both of which scope
// correctly to the caller's own citizen_id. Engineers have no scoped path here
// either, so mutating/reading arbitrary feedback records is restricted to
// admin roles only.
$method = $_SERVER['REQUEST_METHOD'];
requireAnyRole(['super_admin', 'admin']);

requireCsrfProtection();

$db     = getDB();
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Mirrors assets/js/script.js's FEEDBACK_CATEGORY_LABELS — kept in sync by
// hand since this is the only server-side spot that needs the human label.
const FEEDBACK_CATEGORY_LABELS_PHP = [
    'complaint' => 'General Complaint',
    'road_damage' => 'Road Damage',
    'drainage_flooding' => 'Drainage / Flooding',
    'streetlight' => 'Streetlight',
    'sidewalk_accessibility' => 'Sidewalk / Accessibility',
    'safety_hazard' => 'Safety Hazard',
    'project_delay' => 'Project Delay',
    'suggestion' => 'Suggestion',
    'inquiry' => 'Inquiry',
    'commendation' => 'Commendation',
];

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    // Rule-based triage, not a live read of the message content — an
    // explainable urgency score from signals already on the row (priority,
    // how long it's been sitting untouched, category, status), same
    // "advisory only" honesty convention as the dashboard's AI Insights
    // widget and the contractor/engineer score breakdowns. Only open/
    // in_progress entries are scored; resolved/closed never "need attention".
    if (($_GET['action'] ?? '') === 'needs_attention') {
        $categoryWeight = [
            'safety_hazard' => 15,
            'road_damage' => 8,
            'drainage_flooding' => 8,
            'project_delay' => 6,
        ];
        $priorityWeight = ['urgent' => 40, 'high' => 25, 'medium' => 10, 'low' => 0];

        $rows = $db->query("
            SELECT f.id, f.citizen_name, f.message, f.category, f.priority, f.status, f.created_at,
                   p.name AS project_name, DATEDIFF(NOW(), f.created_at) AS days_open
            FROM feedback f
            LEFT JOIN projects p ON p.id = f.project_id
            WHERE f.status IN ('open', 'in_progress')
        ")->fetchAll();

        foreach ($rows as &$row) {
            $daysOpen = max(0, (int) $row['days_open']);
            $row['score'] = ($priorityWeight[$row['priority']] ?? 0)
                + ($categoryWeight[$row['category']] ?? 0)
                + ($row['status'] === 'open' ? 5 : 0)
                + min($daysOpen, 14);
            $row['days_open'] = $daysOpen;

            $reasons = [];
            if (in_array($row['priority'], ['urgent', 'high'], true)) $reasons[] = ucfirst($row['priority']) . ' priority';
            if (isset($categoryWeight[$row['category']])) $reasons[] = FEEDBACK_CATEGORY_LABELS_PHP[$row['category']] ?? $row['category'];
            if ($daysOpen >= 3) $reasons[] = "open {$daysOpen}d";
            $row['reason'] = implode(' · ', $reasons) ?: 'Recently submitted';
        }
        unset($row);

        usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
        respond(['success' => true, 'data' => array_slice($rows, 0, 5)]);
    }

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

        $photoStmt = $db->prepare("SELECT photo_path FROM feedback_photos WHERE feedback_id = ? ORDER BY id");
        $photoStmt->execute([$id]);
        $row['photos'] = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

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
    if (!empty($_GET['category'])) {
        $where[]  = 'f.category = ?';
        $params[] = $_GET['category'];
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

    $before = null;
    if (isset($b['status'])) {
        $beforeStmt = $db->prepare("SELECT citizen_id, status FROM feedback WHERE id = ?");
        $beforeStmt->execute([$id]);
        $before = $beforeStmt->fetch();
    }

    $params[] = $id;

    $db->prepare("UPDATE feedback SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    if ($before && $before['status'] !== $b['status'] && !empty($before['citizen_id'])) {
        $cu = $db->prepare("SELECT user_id FROM citizens WHERE id = ?");
        $cu->execute([$before['citizen_id']]);
        notifyUser(
            (int) ($cu->fetchColumn() ?: 0),
            'info',
            'Feedback update',
            'Your submitted feedback is now "' . $b['status'] . '".'
        );
    }

    respond(['success' => true]);
}

// ── DELETE ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respond(['error' => 'ID required'], 400);
    $db->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
