<?php
// ============================================================
// integrations/urban-planning/inspection-results.php
//
// Outbound feed: the Urban Planning System polls this to pull completed
// inspection results back out. A pull (GET) model rather than a push,
// since (unlike CIMMS, where a real partner URL exists) there is no live
// Urban Planning System endpoint yet for us to push to — this is the
// buildable half of that direction today. Swapping to a push later is a
// contained change to this one file, not to the Engineer Portal itself.
//
// Read-only from our side too: this never accepts edits back from the
// Urban Planning System — inspection reports stay owned by the Engineer
// Portal, only exposed for reading here.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../engineer/includes/urban-planning-schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (URBAN_PLANNING_API_KEY === '' || !hash_equals(URBAN_PLANNING_API_KEY, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing API key']);
    exit;
}

$db = getDB();
urbanPlanningEnsureSchema($db);

// Default: only results not yet handed off. ?all=1 re-lists everything
// completed, e.g. for a first backfill or a re-sync after data loss on
// their end.
$onlyUnsynced = empty($_GET['all']);
$markSynced = !isset($_GET['peek']); // ?peek=1 to inspect without consuming

// Qualified with upi. — the query below joins `users`, which also has its
// own status/created_at/etc. columns, so an unqualified column is
// ambiguous the moment that join is present.
$where = "upi.status = 'completed'";
if ($onlyUnsynced) {
    $where .= " AND upi.synced_to_urban_planning_at IS NULL";
}

$stmt = $db->query("
    SELECT upi.id, upi.road_id, upi.external_reference, upi.inspection_date,
           upi.road_condition, upi.surface_condition, upi.drainage_condition,
           upi.sidewalk_condition, upi.streetlight_condition, upi.traffic_sign_condition,
           upi.overall_condition, upi.severity, upi.recommendation, upi.remarks,
           upi.inspection_latitude, upi.inspection_longitude, upi.submitted_at,
           u.full_name AS engineer_name
    FROM urban_planning_inspections upi
    LEFT JOIN users u ON u.id = upi.engineer_id
    WHERE $where
    ORDER BY upi.submitted_at ASC
    LIMIT 200
");
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $photoStmt = $db->prepare("SELECT photo_path FROM urban_planning_inspection_photos WHERE inspection_id = ?");
    $photoStmt->execute([$row['id']]);
    $row['photo_urls'] = array_map(
        fn($p) => rtrim(APP_BASE_PATH, '/') . '/' . ltrim($p, '/'),
        $photoStmt->fetchAll(PDO::FETCH_COLUMN)
    );
}
unset($row);

if ($markSynced && $rows) {
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("UPDATE urban_planning_inspections SET synced_to_urban_planning_at = NOW() WHERE id IN ($placeholders)")
       ->execute($ids);
}

echo json_encode(['success' => true, 'count' => count($rows), 'results' => $rows]);
