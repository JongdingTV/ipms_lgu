<?php
// ============================================================
// integrations/facilities-reservation/facility-status-feed.php
//
// Outbound feed: the Barangay Culiat Facilities Reservation System
// (https://github.com/lmfollero123/facilities-reservation-system1) can poll
// this to find out which Culiat facilities/locations currently have an
// IPMS infrastructure project affecting them, so it can hold off letting
// residents book a facility that's mid-renovation or under construction.
//
// Pull (GET) model, not push: as of this integration being built, that repo
// has no live endpoint of its own for IPMS to push to (its routes are still
// placeholder view files, not a working API) — same reasoning as
// integrations/urban-planning/inspection-results.php. Swapping to a push
// later, once they have a receiver, is a contained change to this one file.
//
// Read-only from our side: this never accepts writes, and it exposes
// nothing beyond project name/status/schedule — no budgets, no contractor
// or engineer identities, no internal remarks.
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
if (FACILITIES_RESERVATION_API_KEY === '' || !hash_equals(FACILITIES_RESERVATION_API_KEY, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing API key']);
    exit;
}

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);

/** Shared shape for both the "affecting now" and "coming soon" lists below. */
function facilitiesFeedRow(array $p): array
{
    return [
        'project_id' => (int) $p['id'],
        'project_code' => $p['project_code'],
        'name' => $p['name'],
        'category' => $p['category'],
        'location' => $p['location'],
        'status' => $p['status'],
        'progress' => (int) $p['progress'],
        'start_date' => $p['start_date'],
        'expected_completion' => $p['end_date'],
        'latitude' => $p['latitude'] !== null ? (float) $p['latitude'] : null,
        'longitude' => $p['longitude'] !== null ? (float) $p['longitude'] : null,
    ];
}

// Work actually happening right now — the set a reservation system should
// treat as "this facility is unavailable."
$activeStatuses = ['active', 'delayed', 'on_hold', 'completion_inspection'];
$activeStmt = $db->prepare("
    SELECT id, project_code, name, category, location, status, progress, start_date, end_date, latitude, longitude
    FROM projects
    WHERE location LIKE ? AND status IN (" . implode(',', array_fill(0, count($activeStatuses), '?')) . ")
    ORDER BY start_date ASC
");
$activeStmt->execute(array_merge(['%' . PUBLIC_FACILITIES_BARANGAY_FILTER . '%'], $activeStatuses));
$affectedNow = array_map('facilitiesFeedRow', $activeStmt->fetchAll());

$response = [
    'success' => true,
    'barangay' => PUBLIC_FACILITIES_BARANGAY_FILTER,
    'count' => count($affectedNow),
    'facilities_affected' => $affectedNow,
];

// Optional: projects approved but not yet under way — useful for planning
// ahead, not for blocking bookings today.
if (!empty($_GET['include_upcoming'])) {
    $upcomingStatuses = ['approved', 'bidding', 'awarded', 'assigned'];
    $upcomingStmt = $db->prepare("
        SELECT id, project_code, name, category, location, status, progress, start_date, end_date, latitude, longitude
        FROM projects
        WHERE location LIKE ? AND status IN (" . implode(',', array_fill(0, count($upcomingStatuses), '?')) . ")
        ORDER BY start_date ASC
    ");
    $upcomingStmt->execute(array_merge(['%' . PUBLIC_FACILITIES_BARANGAY_FILTER . '%'], $upcomingStatuses));
    $response['upcoming_work'] = array_map('facilitiesFeedRow', $upcomingStmt->fetchAll());
}

echo json_encode($response);
