<?php
/**
 * Outbound client: push Roads and Bridges project data to the RGMap system.
 *
 * The receiver endpoint is expected to accept a JSON payload and the matching
 * shared key, for example:
 *   https://rgmap.infragovservices.com/lgu_staff/pages/api/ipms-road-projects-pull.php?key=...
 *
 * The call is intentionally non-blocking for IPMS operations: a sync error does
 * not fail project creation or status updates, but it is logged so the admin can
 * retry or inspect the external integration.
 */
class RgmapClient
{
    public static function isEnabled(): bool
    {
        return defined('RGMAP_API_ENABLED')
            && RGMAP_API_ENABLED
            && defined('RGMAP_API_URL')
            && RGMAP_API_URL !== ''
            && defined('RGMAP_API_KEY')
            && RGMAP_API_KEY !== '';
    }

    public static function syncProject(PDO $db, int $projectId): array
    {
        if (!self::isEnabled()) {
            return [
                'success' => false,
                'message' => 'RGMap integration is disabled or not configured',
                'http_status' => 0,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'PHP cURL extension is required for RGMap integration',
                'http_status' => 0,
            ];
        }

        $stmt = $db->prepare("
            SELECT p.id, p.project_code, p.name, p.description, p.location, p.district,
                   p.budget, p.start_date, p.end_date, p.progress, p.status,
                   p.category, p.latitude, p.longitude, p.funding_source,
                   p.implementing_office, p.physical_target,
                   g.road_name, g.road_type, g.road_status,
                   g.start_latitude, g.start_longitude, g.end_latitude, g.end_longitude,
                   g.polyline_coordinates, g.estimated_length_meters,
                   g.barangays_covered, g.districts_covered
            FROM projects p
            LEFT JOIN project_road_geometry g ON g.project_id = p.id
            WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            return [
                'success' => false,
                'message' => 'Project not found for RGMap sync',
                'http_status' => 0,
            ];
        }

        if ((string) ($project['category'] ?? '') !== 'Roads and Bridges') {
            return [
                'success' => true,
                'message' => 'Skipped non-road project sync',
                'http_status' => 200,
            ];
        }

        $payload = [
            'source' => 'ipms',
            'sync_type' => 'road_project',
            'project_id' => (int) $project['id'],
            'project_code' => (string) ($project['project_code'] ?? ''),
            'project_name' => (string) ($project['name'] ?? ''),
            'description' => (string) ($project['description'] ?? ''),
            'location' => (string) ($project['location'] ?? ''),
            'district' => (string) ($project['district'] ?? ''),
            'budget' => (float) ($project['budget'] ?? 0),
            'start_date' => $project['start_date'] ?? null,
            'end_date' => $project['end_date'] ?? null,
            'progress_percent' => (int) ($project['progress'] ?? 0),
            'status' => (string) ($project['status'] ?? ''),
            'category' => (string) ($project['category'] ?? ''),
            'funding_source' => (string) ($project['funding_source'] ?? ''),
            'implementing_office' => (string) ($project['implementing_office'] ?? ''),
            'physical_target' => (string) ($project['physical_target'] ?? ''),
            'latitude' => isset($project['latitude']) && $project['latitude'] !== null ? (float) $project['latitude'] : null,
            'longitude' => isset($project['longitude']) && $project['longitude'] !== null ? (float) $project['longitude'] : null,
            'road_name' => (string) ($project['road_name'] ?? ''),
            'road_type' => (string) ($project['road_type'] ?? ''),
            'road_status' => (string) ($project['road_status'] ?? ''),
            'start_coordinate' => [
                'lat' => isset($project['start_latitude']) && $project['start_latitude'] !== null ? (float) $project['start_latitude'] : null,
                'lng' => isset($project['start_longitude']) && $project['start_longitude'] !== null ? (float) $project['start_longitude'] : null,
            ],
            'end_coordinate' => [
                'lat' => isset($project['end_latitude']) && $project['end_latitude'] !== null ? (float) $project['end_latitude'] : null,
                'lng' => isset($project['end_longitude']) && $project['end_longitude'] !== null ? (float) $project['end_longitude'] : null,
            ],
            'polyline_coordinates' => json_decode((string) ($project['polyline_coordinates'] ?? '[]'), true) ?: [],
            'road_length_meters' => isset($project['estimated_length_meters']) && $project['estimated_length_meters'] !== null ? (float) $project['estimated_length_meters'] : 0.0,
            'barangays_covered' => json_decode((string) ($project['barangays_covered'] ?? '[]'), true) ?: [],
            'districts_covered' => json_decode((string) ($project['districts_covered'] ?? '[]'), true) ?: [],
            'updated_at' => gmdate('c'),
        ];

        $url = (string) RGMAP_API_URL;
        $separator = strpos($url, '?') === false ? '?' : '&';
        if (stripos($url, 'key=') === false) {
            $url .= $separator . 'key=' . rawurlencode((string) RGMAP_API_KEY);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) (defined('RGMAP_API_TIMEOUT') ? RGMAP_API_TIMEOUT : 20),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-Key: ' . RGMAP_API_KEY,
                'User-Agent: IPMS-RGMap-Integration/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => !defined('RGMAP_SSL_VERIFY') || RGMAP_SSL_VERIFY,
            CURLOPT_SSL_VERIFYHOST => (!defined('RGMAP_SSL_VERIFY') || RGMAP_SSL_VERIFY) ? 2 : 0,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'message' => 'RGMap connection failed: ' . $error,
                'http_status' => $status,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        $ok = ($status >= 200 && $status < 300) || (!empty($decoded['success']) && is_bool($decoded['success']) && $decoded['success']);

        return [
            'success' => $ok,
            'message' => is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : ($ok ? 'Project synced to RGMap' : 'RGMap rejected the project payload'),
            'http_status' => $status,
            'raw' => $decoded,
        ];
    }
}
