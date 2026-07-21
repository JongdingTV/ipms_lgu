<?php
// ============================================================
// integrations/urban-planning/road-geometry-feed.php
//
// Outbound feed: the Urban Planning System polls this to pull the road
// alignment (geometry) IPMS captured for Roads and Bridges projects during
// Project Registration. Same pull (GET) model as inspection-results.php in
// this same folder, for the same reason — no live push endpoint exists on
// their side yet.
//
// Read-only from our side too: no endpoint here accepts edits. IPMS remains
// the owner of the project and its geometry; the Urban Planning System only
// ever consumes this data.
//
// Field list is intentionally narrow — see the README in this folder for
// the full contract. Nothing beyond what's listed there is exposed (no
// budget, no contractor/engineer identities, no internal remarks).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/workflow.php';

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
projectWorkflowEnsureProjectStatusSchema($db);
projectRoadGeometryEnsureSchema($db);

$stmt = $db->query("
    SELECT p.id AS project_id, p.name AS project_name, p.status AS project_status,
           g.road_name, g.road_type, g.road_status,
           g.start_latitude, g.start_longitude, g.end_latitude, g.end_longitude,
           g.polyline_coordinates, g.estimated_length_meters,
           g.barangays_covered, g.districts_covered
    FROM project_road_geometry g
    INNER JOIN projects p ON p.id = g.project_id
    WHERE p.category = 'Roads and Bridges'
    ORDER BY g.updated_at DESC
");
$rows = $stmt->fetchAll();

$results = array_map(function (array $row): array {
    return [
        'project_id' => (int) $row['project_id'],
        'project_name' => $row['project_name'],
        'project_status' => $row['project_status'],
        'road_name' => $row['road_name'],
        'road_type' => $row['road_type'],
        'road_status' => $row['road_status'],
        'polyline_coordinates' => json_decode((string) $row['polyline_coordinates'], true) ?: [],
        'road_length_meters' => (float) $row['estimated_length_meters'],
        'start_coordinate' => ['lat' => (float) $row['start_latitude'], 'lng' => (float) $row['start_longitude']],
        'end_coordinate' => ['lat' => (float) $row['end_latitude'], 'lng' => (float) $row['end_longitude']],
        'barangays_covered' => json_decode((string) $row['barangays_covered'], true) ?: [],
        'districts_covered' => json_decode((string) $row['districts_covered'], true) ?: [],
    ];
}, $rows);

echo json_encode(['success' => true, 'count' => count($results), 'roads' => $results]);
