<?php
/**
 * Outbound client: push IPMS maintenance feedback to CIMMS (InfraGovServices).
 *
 * Receiver (canonical, LGU repo):
 *   https://github.com/EXEQUIELKENT/LGU
 *   lgu-portal/public/api/ipms-requests.php
 *
 * Inserts into CIMMS `requests` (req_id) + `evidence_images`, same queue as
 * citizenrepform.php.
 */
class CimmClient
{
    /**
     * CIMMS `requests.infrastructure` is a closed enum — mirrors
     * $allowedInfra in the LGU repo's ipms-requests.php exactly. CIMMS has
     * no "Other" option; anything outside this list gets a 422 from the
     * receiver, so callers must map or drop free text before sending it.
     */
    public const ALLOWED_INFRASTRUCTURE = [
        'Roads', 'Street Lights', 'Drainage', 'Public Facilities', 'Water Supply', 'Electrical',
    ];

    public static function isEnabled(): bool
    {
        return defined('CIMM_API_ENABLED')
            && CIMM_API_ENABLED
            && defined('CIMM_API_URL')
            && CIMM_API_URL !== ''
            && defined('CIMM_API_KEY')
            && CIMM_API_KEY !== '';
    }

    /**
     * Map IPMS feedback.category → CIMMS infrastructure type labels
     * (must match citizenrepform.php combobox values).
     */
    public static function mapInfrastructure(string $category): string
    {
        $map = [
            'road_damage' => 'Roads',
            'drainage_flooding' => 'Drainage',
            'streetlight' => 'Street Lights',
            'sidewalk_accessibility' => 'Public Facilities',
            'safety_hazard' => 'Public Facilities',
            'complaint' => 'Public Facilities',
            'suggestion' => 'Public Facilities',
            'inquiry' => 'Public Facilities',
            'commendation' => 'Public Facilities',
            'project_delay' => 'Public Facilities',
        ];

        return $map[$category] ?? 'Public Facilities';
    }

    /**
     * Location string for CIMMS requests.location (mirrors citizenrepform style).
     */
    public static function buildLocation(string $barangay, string $district): string
    {
        $barangay = trim($barangay);
        $district = trim($district);
        $parts = [];

        if ($barangay !== '') {
            $parts[] = (stripos($barangay, 'brgy') === false ? 'Brgy. ' : '') . $barangay;
        }
        if ($district !== '') {
            $parts[] = $district;
        }
        $parts[] = 'Quezon City';

        $location = implode(', ', $parts);
        return strlen($location) >= 5 ? $location : 'Quezon City';
    }

    /**
     * @param array{
     *   feedback_id:int,
     *   category:string,
     *   priority:string,
     *   message:string,
     *   district:string,
     *   barangay:string,
     *   latitude:?float,
     *   longitude:?float,
     *   name:?string,
     *   contact_number:string,
     *   email:?string,
     *   anonymous?:bool
     * } $payload
     * @param list<string> $absolutePhotoPaths Local filesystem paths for evidence[n]
     * @return array{success:bool,request_id:?string,reference:?string,message:string,http_status:int}
     */
    public static function submitRequest(array $payload, array $absolutePhotoPaths = []): array
    {
        if (!self::isEnabled()) {
            return [
                'success' => false,
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS integration is disabled or not configured',
                'http_status' => 0,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'request_id' => null,
                'reference' => null,
                'message' => 'PHP cURL extension is required for CIMMS integration',
                'http_status' => 0,
            ];
        }

        $barangay = (string) ($payload['barangay'] ?? '');
        $district = (string) ($payload['district'] ?? '');

        // Callers that already collected the CIMMS-native values (the citizen
        // portal's replica request form) pass them directly; otherwise they
        // are derived from the IPMS category and district/barangay pair.
        // Values outside CIMMS' closed enum (e.g. IPMS's "Other" free-text
        // option) are discarded here too, so mapInfrastructure() below
        // supplies a valid fallback instead of CIMMS rejecting the whole
        // request with a 422.
        $infrastructure = trim((string) ($payload['infrastructure'] ?? ''));
        if ($infrastructure !== '' && !in_array($infrastructure, self::ALLOWED_INFRASTRUCTURE, true)) {
            $infrastructure = '';
        }
        $location = trim((string) ($payload['location'] ?? ''));

        $fields = [
            'source' => 'ipms',
            'source_feedback_id' => (string) (int) ($payload['feedback_id'] ?? 0),
            'infrastructure' => $infrastructure !== '' ? $infrastructure : self::mapInfrastructure((string) ($payload['category'] ?? '')),
            'location' => $location !== '' ? $location : self::buildLocation($barangay, $district),
            'district' => $district,
            'barangay' => $barangay,
            'issue' => (string) ($payload['message'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'contact_number' => preg_replace('/\D+/', '', (string) ($payload['contact_number'] ?? '')),
            'req_email' => (string) ($payload['email'] ?? ''),
        ];

        if (isset($payload['latitude']) && $payload['latitude'] !== null && $payload['latitude'] !== '') {
            $fields['coord_lat'] = (string) $payload['latitude'];
        }
        if (isset($payload['longitude']) && $payload['longitude'] !== null && $payload['longitude'] !== '') {
            $fields['coord_lng'] = (string) $payload['longitude'];
        }

        // CIMMS ipms-requests.php accepts evidence[] (citizen form) or evidence[0]… (curl).
        $fileIndex = 0;
        foreach ($absolutePhotoPaths as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            $fields['evidence[' . $fileIndex . ']'] = new CURLFile($path, $mime, basename($path));
            $fileIndex++;
        }

        $ch = curl_init(CIMM_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) (defined('CIMM_API_TIMEOUT') ? CIMM_API_TIMEOUT : 20),
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . CIMM_API_KEY,
                'Accept: application/json',
                'User-Agent: IPMS-CIMM-Integration/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => !defined('CIMM_SSL_VERIFY') || CIMM_SSL_VERIFY,
            CURLOPT_SSL_VERIFYHOST => (!defined('CIMM_SSL_VERIFY') || CIMM_SSL_VERIFY) ? 2 : 0,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS connection failed: ' . $error,
                'http_status' => $status,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $snippet = trim(substr(strip_tags((string) $raw), 0, 120));
            return [
                'success' => false,
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS returned non-JSON (HTTP ' . $status . ')' . ($snippet !== '' ? ': ' . $snippet : ''),
                'http_status' => $status,
            ];
        }

        $ok = !empty($decoded['success']) && $status >= 200 && $status < 300;

        return [
            'success' => $ok,
            'request_id' => isset($decoded['request_id']) ? (string) $decoded['request_id'] : null,
            'reference' => isset($decoded['reference']) ? (string) $decoded['reference'] : null,
            'message' => (string) ($decoded['message'] ?? ($ok ? 'Synced to CIMMS' : 'CIMMS rejected the request')),
            'http_status' => $status,
        ];
    }
}
