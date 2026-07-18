<?php
/**
 * Outbound client: push IPMS maintenance feedback to CIMMS (InfraGovServices).
 *
 * Target: POST {CIMM_API_URL} with X-API-Key auth and multipart fields that
 * match the CIMMS citizen request form (citizenrepform.php / requests queue).
 */
class CimmClient
{
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
     * (Roads, Street Lights, Drainage, Public Facilities, Water Supply, Electrical).
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
     * @param list<string> $absolutePhotoPaths Local filesystem paths to attach as evidence[]
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

        $location = trim(($payload['barangay'] ?? '') . ', ' . ($payload['district'] ?? '') . ', Quezon City', ' ,');
        if ($location === 'Quezon City' || $location === '') {
            $location = 'Quezon City';
        }

        $fields = [
            'source' => 'ipms',
            'source_feedback_id' => (string) (int) ($payload['feedback_id'] ?? 0),
            'infrastructure' => self::mapInfrastructure((string) ($payload['category'] ?? '')),
            'location' => $location,
            'district' => (string) ($payload['district'] ?? ''),
            'barangay' => (string) ($payload['barangay'] ?? ''),
            'issue' => (string) ($payload['message'] ?? ''),
            'priority' => (string) ($payload['priority'] ?? 'medium'),
            'category' => (string) ($payload['category'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'contact_number' => preg_replace('/\D+/', '', (string) ($payload['contact_number'] ?? '')),
            'req_email' => (string) ($payload['email'] ?? ''),
            'consent_agree' => '1',
            'anonymous' => !empty($payload['anonymous']) ? '1' : '0',
        ];

        if (isset($payload['latitude']) && $payload['latitude'] !== null && $payload['latitude'] !== '') {
            $fields['coord_lat'] = (string) $payload['latitude'];
        }
        if (isset($payload['longitude']) && $payload['longitude'] !== null && $payload['longitude'] !== '') {
            $fields['coord_lng'] = (string) $payload['longitude'];
        }

        foreach ($absolutePhotoPaths as $i => $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            $fields['evidence[' . $i . ']'] = new CURLFile($path, $mime, basename($path));
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
            // Production CIMMS is HTTPS; keep verification on unless explicitly disabled for local stubs.
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
            return [
                'success' => false,
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS returned a non-JSON response (HTTP ' . $status . ')',
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
