<?php
// ============================================================
// api/public_project_search.php — Public, unauthenticated project lookup
// for the landing page search widget. Returns a teaser only: match count
// plus project code/name. Full details (budget, contractor, progress,
// documents) require citizen login — see citizen/api/projects.php.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
apiHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['error' => 'Method not allowed'], 405);
}

$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 80);

if (mb_strlen($search) < 2) {
    respond(['total' => 0, 'results' => []]);
}

try {
    $db = getDB();
    $like = '%' . $search . '%';

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM projects
         WHERE status NOT IN ('draft', 'returned')
           AND (name LIKE ? OR project_code LIKE ? OR location LIKE ?)"
    );
    $countStmt->execute([$like, $like, $like]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT project_code, name FROM projects
         WHERE status NOT IN ('draft', 'returned')
           AND (name LIKE ? OR project_code LIKE ? OR location LIKE ?)
         ORDER BY updated_at DESC
         LIMIT 6"
    );
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll();

    respond([
        'total' => $total,
        'results' => array_map(
            fn ($r) => ['code' => $r['project_code'], 'name' => $r['name']],
            $rows
        ),
    ]);
} catch (Throwable $e) {
    respond(['error' => 'Search is temporarily unavailable.'], 500);
}
