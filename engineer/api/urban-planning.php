<?php
// ============================================================
// engineer/api/urban-planning.php — Urban Planning System integration.
//
// Deliberately its own file, separate from engineer/api/portal.php: this
// is an external-system integration, not a native Engineer Portal module,
// so it gets its own small surface that can be extended (or unplugged)
// without touching the portal's existing actions at all. Same shape is
// meant to be reused for future integrations (Asset Management, GIS,
// Disaster Risk Management, CIMMS).
//
// Ownership boundary, enforced throughout: road_id/road_name/barangay/
// district/road_type/road_length/priority/requested_by/request_date/
// road_latitude/road_longitude arrive FROM the Urban Planning System via
// integrations/urban-planning/inspection-requests.php and are never
// written here. Everything else (engineer_id, inspection_date, the
// condition fields, severity, recommendation, remarks, photos) is this
// portal's own output.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../includes/urban-planning-schema.php';

apiHeaders();
requireAnyRole(['engineer']);
requireCsrfProtection();

$db = getDB();
$engineerId = engineerScopeCurrentId();
if ($engineerId === null) {
    respond(['error' => 'Engineer account is required.'], 403);
}

urbanPlanningEnsureSchema($db);

const URBAN_PLANNING_PHOTO_MAX_SIZE = 8 * 1024 * 1024;
const URBAN_PLANNING_PHOTO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

function urbanPlanningRow(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM urban_planning_inspections WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $photoStmt = $db->prepare("SELECT id, photo_path, caption, created_at FROM urban_planning_inspection_photos WHERE inspection_id = ? ORDER BY created_at");
    $photoStmt->execute([$id]);
    $row['photos'] = $photoStmt->fetchAll();

    return $row;
}

function urbanPlanningCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'list_requests';

if ($method === 'GET') {
    if ($action === 'list_requests') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));

        $result = paginate(
            $db,
            "SELECT * FROM urban_planning_inspections
             WHERE status IN ('pending','assigned','in_progress','returned')
             ORDER BY FIELD(priority,'urgent','high','medium','low'), request_date ASC",
            "SELECT COUNT(*) FROM urban_planning_inspections WHERE status IN ('pending','assigned','in_progress','returned')",
            [],
            $page,
            $perPage
        );
        respond($result);
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        $row = $id > 0 ? urbanPlanningRow($db, $id) : null;
        if (!$row) {
            respond(['error' => 'Inspection request not found.'], 404);
        }
        respond($row);
    }

    if ($action === 'list_history') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));

        // Every column here is qualified with upi. — the SELECT query below
        // joins `users`, which also has its own `status`/`created_at`/etc.
        // columns, so an unqualified WHERE column is ambiguous the moment
        // that join is present (the COUNT query gets the same upi alias
        // purely so it can share this exact $whereSql safely).
        $where = ["upi.status IN ('completed','returned')"];
        $params = [];

        if (!empty($_GET['search'])) {
            $where[] = '(upi.road_name LIKE ? OR upi.barangay LIKE ? OR upi.road_id LIKE ?)';
            $s = '%' . $_GET['search'] . '%';
            array_push($params, $s, $s, $s);
        }
        if (!empty($_GET['status']) && in_array($_GET['status'], ['completed', 'returned'], true)) {
            $where = array_filter($where, fn($c) => $c !== "upi.status IN ('completed','returned')");
            $where[] = 'upi.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['overall_condition']) && in_array($_GET['overall_condition'], URBAN_PLANNING_CONDITIONS, true)) {
            $where[] = 'upi.overall_condition = ?';
            $params[] = $_GET['overall_condition'];
        }
        if (!empty($_GET['recommendation']) && in_array($_GET['recommendation'], URBAN_PLANNING_RECOMMENDATIONS, true)) {
            $where[] = 'upi.recommendation = ?';
            $params[] = $_GET['recommendation'];
        }
        if (!empty($_GET['date_from'])) {
            $where[] = 'upi.inspection_date >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'upi.inspection_date <= ?';
            $params[] = $_GET['date_to'];
        }

        $whereSql = implode(' AND ', $where);
        $result = paginate(
            $db,
            "SELECT upi.*, u.full_name AS engineer_name,
                    (SELECT COUNT(*) FROM urban_planning_inspection_photos p WHERE p.inspection_id = upi.id) AS photo_count
             FROM urban_planning_inspections upi
             LEFT JOIN users u ON u.id = upi.engineer_id
             WHERE $whereSql
             ORDER BY upi.submitted_at DESC",
            "SELECT COUNT(*) FROM urban_planning_inspections upi WHERE $whereSql",
            $params,
            $page,
            $perPage
        );
        respond($result);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

if ($action === 'submit_inspection') {
    $body = $_POST;
    $id = (int) ($body['id'] ?? 0);
    $existing = $id > 0 ? urbanPlanningRow($db, $id) : null;
    if (!$existing) {
        respond(['error' => 'Inspection request not found.'], 404);
    }
    if (in_array($existing['status'], ['completed', 'returned'], true)) {
        respond(['error' => 'This inspection has already been submitted.'], 422);
    }

    $validated = Validator::make($body, [
        'inspection_date' => 'required|date',
        'road_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'surface_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'drainage_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'sidewalk_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'streetlight_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'traffic_sign_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'overall_condition' => 'required|in:' . implode(',', URBAN_PLANNING_CONDITIONS),
        'severity' => 'required|in:' . implode(',', URBAN_PLANNING_SEVERITIES),
        'recommendation' => 'required|in:' . implode(',', URBAN_PLANNING_RECOMMENDATIONS),
        'remarks' => 'nullable|string|max:2000',
        'latitude' => 'nullable|numeric',
        'longitude' => 'nullable|numeric',
    ])->stopOnFailure();

    $lat = isset($body['latitude']) && $body['latitude'] !== '' ? (float) $body['latitude'] : null;
    $lng = isset($body['longitude']) && $body['longitude'] !== '' ? (float) $body['longitude'] : null;
    if (($lat !== null) !== ($lng !== null)) {
        respond(['error' => 'GPS coordinates need both latitude and longitude, or neither.'], 422);
    }

    $photoFiles = [];
    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['photos']['name'][$i] ?? '') === '') {
                continue;
            }
            $entry = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i] ?? '',
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i] ?? 0,
            ];
            try {
                $stored = FileUpload::store($entry, 'urban-planning-inspections', [
                    'max_size' => URBAN_PLANNING_PHOTO_MAX_SIZE,
                    'extensions' => URBAN_PLANNING_PHOTO_EXTENSIONS,
                    'sniff_pdf' => false,
                ]);
                $photoFiles[] = $stored['stored_path'];
            } catch (Throwable $e) {
                respond(['error' => 'Photo ' . ($i + 1) . ': ' . $e->getMessage()], 422);
            }
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            UPDATE urban_planning_inspections SET
                status = 'completed',
                engineer_id = ?,
                inspection_date = ?,
                road_condition = ?,
                surface_condition = ?,
                drainage_condition = ?,
                sidewalk_condition = ?,
                streetlight_condition = ?,
                traffic_sign_condition = ?,
                overall_condition = ?,
                severity = ?,
                recommendation = ?,
                remarks = ?,
                inspection_latitude = ?,
                inspection_longitude = ?,
                submitted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $engineerId,
            $validated['inspection_date'],
            $validated['road_condition'],
            $validated['surface_condition'],
            $validated['drainage_condition'],
            $validated['sidewalk_condition'],
            $validated['streetlight_condition'],
            $validated['traffic_sign_condition'],
            $validated['overall_condition'],
            $validated['severity'],
            $validated['recommendation'],
            trim((string) ($validated['remarks'] ?? '')) !== '' ? trim($validated['remarks']) : null,
            $lat,
            $lng,
            $id,
        ]);

        if ($photoFiles) {
            $photoStmt = $db->prepare("INSERT INTO urban_planning_inspection_photos (inspection_id, photo_path) VALUES (?, ?)");
            foreach ($photoFiles as $path) {
                $photoStmt->execute([$id, $path]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        urbanPlanningCleanupFiles($photoFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to submit inspection.'], 422);
    }

    respond(['success' => true, 'id' => $id]);
}

respond(['error' => 'Unknown action.'], 404);
