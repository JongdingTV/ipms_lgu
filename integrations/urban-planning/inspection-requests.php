<?php
// ============================================================
// integrations/urban-planning/inspection-requests.php
//
// Inbound receiver: the Urban Planning System (a separate capstone
// project, its own repo) POSTs a road inspection request here. Opposite
// direction from the CIMMS integration (there, IPMS is the sender and
// the receiver lives in the other team's repo); here, the Engineer
// Portal is the receiver, since requests originate on their side.
//
// This endpoint only ever creates a new urban_planning_inspections row
// with status='pending' — it never touches road master data anywhere
// else in this system, and it has no update/delete action, matching the
// integration rule that road records stay exclusively owned by the
// Urban Planning System.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../engineer/includes/urban-planning-schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$validated = Validator::make($body, [
    'road_id' => 'required|string|max:40',
    'road_name' => 'required|string|max:200',
    'barangay' => 'required|string|max:100',
    'district' => 'required|string|max:20',
    'road_type' => 'nullable|string|max:80',
    'road_length' => 'nullable|numeric',
    'priority' => 'nullable|in:' . implode(',', URBAN_PLANNING_PRIORITIES),
    'requested_by' => 'nullable|string|max:150',
    'request_date' => 'required|date',
    'road_latitude' => 'nullable|numeric',
    'road_longitude' => 'nullable|numeric',
    'external_reference' => 'nullable|string|max:64',
])->stopOnFailure('Request rejected — see errors for details.');

$stmt = $db->prepare("
    INSERT INTO urban_planning_inspections
        (road_id, road_name, barangay, district, road_type, road_length, priority,
         requested_by, request_date, road_latitude, road_longitude, external_reference, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->execute([
    $validated['road_id'],
    $validated['road_name'],
    $validated['barangay'],
    $validated['district'],
    $validated['road_type'] ?? null,
    isset($validated['road_length']) && $validated['road_length'] !== '' ? (float) $validated['road_length'] : null,
    $validated['priority'] ?? 'medium',
    $validated['requested_by'] ?? null,
    $validated['request_date'],
    isset($validated['road_latitude']) && $validated['road_latitude'] !== '' ? (float) $validated['road_latitude'] : null,
    isset($validated['road_longitude']) && $validated['road_longitude'] !== '' ? (float) $validated['road_longitude'] : null,
    $validated['external_reference'] ?? null,
]);

$newId = (int) $db->lastInsertId();

echo json_encode([
    'success' => true,
    'inspection_request_id' => $newId,
    'status' => 'pending',
    'message' => 'Inspection request accepted into the Engineer Portal queue.',
]);
