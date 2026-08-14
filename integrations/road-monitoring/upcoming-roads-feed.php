<?php
// ============================================================
// integrations/road-monitoring/upcoming-roads-feed.php
//
// Outbound feed: the LG Road Monitoring System polls this to pull the full
// lifecycle of Roads and Bridges projects — the road alignment (geometry)
// IPMS captured during Project Registration, plus the timeline, progress,
// and current status bucket (new/ongoing/completed/cancelled) that tells
// them what stage a road is at. Pull (GET) model, same reason as every
// other outbound integration in this codebase: their repo
// (https://github.com/conopioclarence96-commits/lg-road-monitoring, as of
// writing) has no live receiver endpoint of its own yet.
//
// Scope: everything from HOPE approval onward — i.e. still-internal
// drafts/reviews (draft, endorsed, returned, planning) are excluded because
// they haven't been approved yet and may still be rejected/reworked, but
// approved projects are included all the way through completed/turnover and
// cancelled, so the Road Monitoring System's dashboard reflects the whole
// public lifecycle, not just the "under construction" window.
//
// Read-only from our side too: no endpoint here accepts edits. IPMS remains
// the owner of the project and its geometry; the Road Monitoring System only
// ever consumes this data.
//
// Field list — see the README in this folder for the full contract. Budget
// and assigned-engineer name were added on the Road Monitoring System team's
// request (previously deliberately excluded); still no contractor identity,
// internal remarks, or documents.
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

function rm_feed_log(string $line): void
{
    $file = __DIR__ . '/feed_access.log';
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($file, "{$ts} {$line}\n", FILE_APPEND | LOCK_EX);
}

// Accept API key from multiple header variants or a GET param for easy testing.
$providedKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $providedKey = $_SERVER['HTTP_X_API_KEY'];
} elseif (!empty($_SERVER['HTTP_X_APIKEY'])) {
    $providedKey = $_SERVER['HTTP_X_APIKEY'];
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    // Accept "Bearer <key>" or raw key in Authorization header.
    $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (str_starts_with($auth, 'Bearer ')) {
        $providedKey = substr($auth, 7);
    } else {
        $providedKey = $auth;
    }
} elseif (!empty($_GET['api_key'])) {
    $providedKey = $_GET['api_key'];
}

if (ROAD_MONITORING_API_KEY === '' || !hash_equals((string) ROAD_MONITORING_API_KEY, (string) $providedKey)) {
    http_response_code(401);
    rm_feed_log(sprintf('AUTH_FAIL ip=%s path=%s provided=%s', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['REQUEST_URI'] ?? '-', substr($providedKey, 0, 12)));
    echo json_encode(['success' => false, 'message' => 'Invalid or missing API key']);
    exit;
}

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);
projectRoadGeometryEnsureSchema($db);

// Full public lifecycle = approved through completed/cancelled; still-
// internal drafts/reviews (not yet HOPE-approved) are excluded because they
// may still be rejected or reworked before anyone outside IPMS should see
// them.
const ROAD_MONITORING_FEED_STATUSES = [
    'approved', 'bidding', 'awarded', 'assigned',
    'active', 'delayed', 'on_hold', 'completion_inspection',
    'completed', 'turnover',
    'cancelled',
];

// Maps IPMS's internal status ENUM to the 4 buckets the Road Monitoring
// System actually cares about, so their side doesn't need to hardcode or
// guess our internal workflow states.
const ROAD_MONITORING_STATUS_BUCKETS = [
    'approved' => 'new', 'bidding' => 'new', 'awarded' => 'new', 'assigned' => 'new',
    'active' => 'ongoing', 'delayed' => 'ongoing', 'on_hold' => 'ongoing', 'completion_inspection' => 'ongoing',
    'completed' => 'completed', 'turnover' => 'completed',
    'cancelled' => 'cancelled',
];

$placeholders = implode(',', array_fill(0, count(ROAD_MONITORING_FEED_STATUSES), '?'));
$stmt = $db->prepare("
        SELECT p.id AS project_id, p.name AS project_name, p.status AS project_status,
            p.progress, p.start_date, p.end_date, p.budget,
            g.road_name, g.road_type, g.road_status,
            g.start_latitude, g.start_longitude, g.end_latitude, g.end_longitude,
            g.polyline_coordinates, g.estimated_length_meters,
            g.barangays_covered, g.districts_covered
        FROM projects p
        LEFT JOIN project_road_geometry g ON p.id = g.project_id
        WHERE LOWER(TRIM(COALESCE(p.category, ''))) = 'roads and bridges' AND p.status IN ($placeholders)
        ORDER BY p.start_date ASC, g.updated_at DESC
");
$stmt->execute(ROAD_MONITORING_FEED_STATUSES);
$rows = $stmt->fetchAll();

// Separate query rather than a JOIN above — a project can have more than one
// active engineer assignment, and joining directly would multiply each
// project's road-geometry row per engineer. Grouped in PHP by project_id instead.
$engineersByProject = [];
if ($rows !== []) {
    $projectIds = array_map(static fn(array $r): int => (int) $r['project_id'], $rows);
    $idPlaceholders = implode(',', array_fill(0, count($projectIds), '?'));
    $engineerStmt = $db->prepare("
        SELECT epa.project_id, u.full_name
        FROM engineer_project_assignments epa
        INNER JOIN users u ON u.id = epa.engineer_id
        WHERE epa.status IN ('active','assigned') AND epa.project_id IN ($idPlaceholders)
        ORDER BY u.full_name ASC
    ");
    $engineerStmt->execute($projectIds);
    foreach ($engineerStmt->fetchAll() as $engineerRow) {
        $engineersByProject[(int) $engineerRow['project_id']][] = $engineerRow['full_name'];
    }
}

$results = array_map(function (array $row) use ($engineersByProject): array {
    return [
        'project_id' => (int) $row['project_id'],
        'project_name' => $row['project_name'],
        'project_status' => $row['project_status'],
        'status_bucket' => ROAD_MONITORING_STATUS_BUCKETS[$row['project_status']] ?? 'new',
        'progress_percent' => (int) $row['progress'],
        'budget' => (float) $row['budget'],
        'assigned_engineers' => $engineersByProject[(int) $row['project_id']] ?? [],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
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
rm_feed_log(sprintf('OK ip=%s path=%s returned=%d', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['REQUEST_URI'] ?? '-', count($results)));
