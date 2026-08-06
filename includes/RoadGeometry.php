<?php
// ============================================================
// includes/RoadGeometry.php — Road Geometry module (Roads and Bridges
// category projects only) + the general QC-bounding-box coordinate check.
//
// Extracted out of api/projects.php so hope/api/portal.php's decide_edit
// action (approving a pending Project Edit Request) can apply a proposed
// road_geometry change the exact same way api/projects.php's own POST/PUT
// handlers do — one source of truth for the validation + storage logic
// instead of two copies drifting apart.
// ============================================================

// Same QC bounding box (with a little slack) as citizen/api/submit-feedback.php
// — keep both in sync if this ever changes. The GIS Map is Quezon-City-only,
// so a project's pin must actually be inside the city, not just anywhere on Earth.
function projectQcCoordinatesValid(float $lat, float $lng): bool
{
    return $lat >= 14.55 && $lat <= 14.82 && $lng >= 120.96 && $lng <= 121.16;
}

// Must match ROAD_TYPES/ROAD_STATUSES/ROAD_SURFACES in assets/js/script.js.
const ROAD_TYPES = ['National Road', 'City Road', 'Barangay Road', 'Secondary Road', 'Bridge', 'Intersection'];
const ROAD_STATUSES = ['Existing Road', 'Road Widening', 'New Road', 'Rehabilitation', 'Bridge Construction'];
const ROAD_SURFACES = ['Concrete', 'Asphalt', 'Gravel', 'Mixed'];

/** Sum of great-circle distances between consecutive points, in meters. Recomputed
 *  server-side rather than trusted from the client, same principle as validating
 *  coordinates instead of trusting a client-supplied "in QC" flag. */
function projectRoadGeometryLengthMeters(array $points): float
{
    $earthRadius = 6371000.0; // meters
    $total = 0.0;
    for ($i = 1; $i < count($points); $i++) {
        [$lat1, $lng1] = $points[$i - 1];
        [$lat2, $lng2] = $points[$i];
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $total += $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
    return round($total, 2);
}

function projectRoadGeometryBoundingBox(array $points): array
{
    $lats = array_column($points, 0);
    $lngs = array_column($points, 1);
    return [
        'min_lat' => min($lats), 'max_lat' => max($lats),
        'min_lng' => min($lngs), 'max_lng' => max($lngs),
    ];
}

/**
 * Validates the road_geometry payload (JSON string, from the form's single
 * hidden field — same shape whether it arrived via multipart POST or a JSON
 * PUT/edit-request body) and returns the decoded+normalized array ready to
 * persist, or null with $error set. Only ever called when category is
 * 'Roads and Bridges' — every other category skips this module entirely.
 */
function projectValidateRoadGeometry(?string $raw, ?string &$error): ?array
{
    if ($raw === null || trim($raw) === '') {
        $error = 'Road Geometry is required for Roads and Bridges projects — draw the road on the map.';
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $error = 'Invalid Road Geometry data.';
        return null;
    }

    $roadName = trim((string) ($data['road_name'] ?? ''));
    if ($roadName === '') {
        $error = 'Road Name is required.';
        return null;
    }

    $points = $data['points'] ?? [];
    if (!is_array($points) || count($points) < 2) {
        $error = 'Draw at least two points on the map to form the road (a start point and an end point).';
        return null;
    }
    foreach ($points as $pt) {
        if (!is_array($pt) || count($pt) !== 2 || !is_numeric($pt[0]) || !is_numeric($pt[1])
            || !projectQcCoordinatesValid((float) $pt[0], (float) $pt[1])) {
            $error = 'One of the road points is invalid or falls outside Quezon City.';
            return null;
        }
    }
    $points = array_map(fn($pt) => [(float) $pt[0], (float) $pt[1]], $points);

    $start = $data['start'] ?? null;
    $end = $data['end'] ?? null;
    if (!is_array($start) || !is_array($end) || !isset($start['lat'], $start['lng'], $end['lat'], $end['lng'])) {
        $error = 'Both a start point and an end point are required.';
        return null;
    }

    if (!empty($data['road_type']) && !in_array($data['road_type'], ROAD_TYPES, true)) {
        $error = 'Invalid road type.';
        return null;
    }
    if (!empty($data['road_status']) && !in_array($data['road_status'], ROAD_STATUSES, true)) {
        $error = 'Invalid road status.';
        return null;
    }
    if (!empty($data['road_surface']) && !in_array($data['road_surface'], ROAD_SURFACES, true)) {
        $error = 'Invalid road surface.';
        return null;
    }

    return [
        'road_name' => $roadName,
        'road_type' => !empty($data['road_type']) ? $data['road_type'] : null,
        'road_status' => !empty($data['road_status']) ? $data['road_status'] : null,
        'start_latitude' => (float) $start['lat'],
        'start_longitude' => (float) $start['lng'],
        'start_address' => !empty($start['address']) ? mb_substr((string) $start['address'], 0, 255) : null,
        'start_barangay' => !empty($start['barangay']) ? mb_substr((string) $start['barangay'], 0, 100) : null,
        'start_district' => !empty($start['district']) ? mb_substr((string) $start['district'], 0, 100) : null,
        'end_latitude' => (float) $end['lat'],
        'end_longitude' => (float) $end['lng'],
        'end_address' => !empty($end['address']) ? mb_substr((string) $end['address'], 0, 255) : null,
        'end_barangay' => !empty($end['barangay']) ? mb_substr((string) $end['barangay'], 0, 100) : null,
        'end_district' => !empty($end['district']) ? mb_substr((string) $end['district'], 0, 100) : null,
        'polyline_coordinates' => json_encode($points),
        'estimated_length_meters' => projectRoadGeometryLengthMeters($points),
        'num_segments' => count($points) - 1,
        'bounding_box' => json_encode(projectRoadGeometryBoundingBox($points)),
        'barangays_covered' => json_encode(array_values(array_unique(array_filter((array) ($data['barangays_covered'] ?? []))))),
        'districts_covered' => json_encode(array_values(array_unique(array_filter((array) ($data['districts_covered'] ?? []))))),
        'road_width' => is_numeric($data['road_width'] ?? null) ? (float) $data['road_width'] : null,
        'num_lanes' => is_numeric($data['num_lanes'] ?? null) ? (int) $data['num_lanes'] : null,
        'road_surface' => !empty($data['road_surface']) ? $data['road_surface'] : null,
        'bridge_included' => !empty($data['bridge_included']) ? 1 : 0,
        'drainage_included' => !empty($data['drainage_included']) ? 1 : 0,
        'bike_lane' => !empty($data['bike_lane']) ? 1 : 0,
        'sidewalk' => !empty($data['sidewalk']) ? 1 : 0,
        'streetlights' => !empty($data['streetlights']) ? 1 : 0,
    ];
}

/** Upserts one project's road geometry row — always exactly 0 or 1 per project. */
function projectStoreRoadGeometry(PDO $db, int $projectId, array $g): void
{
    $stmt = $db->prepare("
        INSERT INTO project_road_geometry
            (project_id, road_name, road_type, road_status,
             start_latitude, start_longitude, start_address, start_barangay, start_district,
             end_latitude, end_longitude, end_address, end_barangay, end_district,
             polyline_coordinates, estimated_length_meters, num_segments, bounding_box,
             barangays_covered, districts_covered,
             road_width, num_lanes, road_surface,
             bridge_included, drainage_included, bike_lane, sidewalk, streetlights)
        VALUES (?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?, ?,?,?, ?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            road_name = VALUES(road_name), road_type = VALUES(road_type), road_status = VALUES(road_status),
            start_latitude = VALUES(start_latitude), start_longitude = VALUES(start_longitude),
            start_address = VALUES(start_address), start_barangay = VALUES(start_barangay), start_district = VALUES(start_district),
            end_latitude = VALUES(end_latitude), end_longitude = VALUES(end_longitude),
            end_address = VALUES(end_address), end_barangay = VALUES(end_barangay), end_district = VALUES(end_district),
            polyline_coordinates = VALUES(polyline_coordinates), estimated_length_meters = VALUES(estimated_length_meters),
            num_segments = VALUES(num_segments), bounding_box = VALUES(bounding_box),
            barangays_covered = VALUES(barangays_covered), districts_covered = VALUES(districts_covered),
            road_width = VALUES(road_width), num_lanes = VALUES(num_lanes), road_surface = VALUES(road_surface),
            bridge_included = VALUES(bridge_included), drainage_included = VALUES(drainage_included),
            bike_lane = VALUES(bike_lane), sidewalk = VALUES(sidewalk), streetlights = VALUES(streetlights),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $projectId, $g['road_name'], $g['road_type'], $g['road_status'],
        $g['start_latitude'], $g['start_longitude'], $g['start_address'], $g['start_barangay'], $g['start_district'],
        $g['end_latitude'], $g['end_longitude'], $g['end_address'], $g['end_barangay'], $g['end_district'],
        $g['polyline_coordinates'], $g['estimated_length_meters'], $g['num_segments'], $g['bounding_box'],
        $g['barangays_covered'], $g['districts_covered'],
        $g['road_width'], $g['num_lanes'], $g['road_surface'],
        $g['bridge_included'], $g['drainage_included'], $g['bike_lane'], $g['sidewalk'], $g['streetlights'],
    ]);
}
