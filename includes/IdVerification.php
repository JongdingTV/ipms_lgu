<?php
// ============================================================
// includes/IdVerification.php — AI-based ID verification for citizen
// registration (Quezon City residency gate).
//
// Sends the citizen's uploaded ID photo to Gemini vision
// (ChatbotClient::analyzeImage()) and asks it to extract the printed name/
// address/ID type, then applies three pass/fail rules on top of that
// extraction:
//   1. is_id_document — the image is actually a legible ID, not a random photo.
//   2. name_matches   — the name printed on the ID matches what the applicant typed.
//   3. is_qc_address  — the address printed on the ID is within Quezon City.
// All three must pass for verify() to report passed=true. Used by both
// citizen/api/verify-id.php (inline pre-check during the registration
// wizard) and citizen/register.php (the real, server-side gate on account
// creation) — the same function backs both, so the wizard's "looks good"
// can never diverge from what actually blocks the account.
// ============================================================
require_once __DIR__ . '/ChatbotClient.php';

final class IdVerification
{
    public const MAX_PHOTO_BYTES = 3 * 1024 * 1024; // 3MB
    public const ALLOWED_PHOTO_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validates an uploaded ID photo — content-sniffed via getimagesize(),
     * never trusted from the client-supplied MIME type. Shared by
     * citizen/api/verify-id.php's inline pre-check and citizen/register.php's
     * final server-side gate, so the two can never validate differently.
     *
     * @param ?array $fileEntry One $_FILES[...] entry, or null if not submitted.
     * @return array{ok:bool, error:?string, extension:?string, mime_type:?string}
     */
    public static function validateUploadedPhoto(?array $fileEntry): array
    {
        if ($fileEntry === null || ($fileEntry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Please upload a photo of your ID.', 'extension' => null, 'mime_type' => null];
        }

        if ($fileEntry['error'] !== UPLOAD_ERR_OK) {
            $message = match ($fileEntry['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ID photo is too large. Please upload a file 3MB or smaller.',
                UPLOAD_ERR_PARTIAL => 'ID photo upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Could not save the uploaded file. Please try again.',
                default => 'Failed to upload ID photo. Please try again.',
            };
            return ['ok' => false, 'error' => $message, 'extension' => null, 'mime_type' => null];
        }

        if ((int) $fileEntry['size'] > self::MAX_PHOTO_BYTES) {
            return ['ok' => false, 'error' => 'ID photo must be 3MB or smaller. Please compress the image or retake a smaller photo.', 'extension' => null, 'mime_type' => null];
        }

        $imageInfo = is_uploaded_file($fileEntry['tmp_name'] ?? '') ? @getimagesize($fileEntry['tmp_name']) : false;
        if ($imageInfo === false || !isset(self::ALLOWED_PHOTO_MIME[$imageInfo['mime']])) {
            return ['ok' => false, 'error' => 'ID photo must be a valid JPG, PNG, GIF, or WEBP image.', 'extension' => null, 'mime_type' => null];
        }

        return ['ok' => true, 'error' => null, 'extension' => self::ALLOWED_PHOTO_MIME[$imageInfo['mime']], 'mime_type' => (string) $imageInfo['mime']];
    }

    /**
     * @return array{
     *   success:bool, message:?string, passed:?bool, reasons:string[],
     *   is_id_document:?bool, name_matches:?bool, is_qc_address:?bool,
     *   extracted_name:?string, extracted_address:?string, id_type_guess:?string, notes:?string
     * } success=false means the AI check itself could not run (transient —
     *   network/quota/parse failure); the caller must NOT treat that as a
     *   pass. passed is only meaningful when success=true.
     */
    public static function verify(string $imagePath, string $mimeType, string $expectedFullName): array
    {
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            return self::serviceError('The uploaded ID photo could not be read. Please try uploading it again.');
        }

        $raw = file_get_contents($imagePath);
        if ($raw === false || $raw === '') {
            return self::serviceError('The uploaded ID photo could not be read. Please try uploading it again.');
        }

        $result = ChatbotClient::analyzeImage(base64_encode($raw), $mimeType, self::buildPrompt($expectedFullName));
        if (!$result['success']) {
            return self::serviceError($result['message'] ?: 'We could not verify your ID right now. Please try again in a moment.');
        }

        $parsed = self::parseReply((string) $result['reply']);
        if ($parsed === null) {
            return self::serviceError('We could not analyze your ID photo. Please try again with a clear, well-lit photo showing the full document.');
        }

        $isIdDocument = (bool) ($parsed['is_id_document'] ?? false);
        $nameMatches = (bool) ($parsed['name_matches_applicant'] ?? false);
        $isQcAddress = (bool) ($parsed['is_quezon_city_address'] ?? false);

        $reasons = [];
        if (!$isIdDocument) {
            $reasons[] = 'We could not recognize this image as a valid government or barangay-issued ID. Please upload a clear photo of your ID.';
        } else {
            if (!$nameMatches) {
                $reasons[] = 'The name on your ID does not match the name you entered in this form.';
            }
            if (!$isQcAddress) {
                $reasons[] = 'The address on your ID is not within Quezon City. This portal is only for verified Quezon City residents.';
            }
        }

        return [
            'success' => true,
            'message' => null,
            'passed' => $isIdDocument && $nameMatches && $isQcAddress,
            'reasons' => $reasons,
            'is_id_document' => $isIdDocument,
            'name_matches' => $nameMatches,
            'is_qc_address' => $isQcAddress,
            'extracted_name' => self::nullableString($parsed['extracted_name'] ?? null),
            'extracted_address' => self::nullableString($parsed['extracted_address'] ?? null),
            'id_type_guess' => self::nullableString($parsed['id_type_guess'] ?? null),
            'notes' => self::nullableString($parsed['confidence_notes'] ?? null),
        ];
    }

    private static function serviceError(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'passed' => null,
            'reasons' => [],
            'is_id_document' => null,
            'name_matches' => null,
            'is_qc_address' => null,
            'extracted_name' => null,
            'extracted_address' => null,
            'id_type_guess' => null,
            'notes' => null,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' ? mb_substr($value, 0, 250) : null;
    }

    private static function buildPrompt(string $expectedFullName): string
    {
        // Newlines/quotes stripped so the interpolated name can never be
        // mistaken for prompt structure by the model.
        $safeName = trim((string) preg_replace('/[\r\n"]+/', ' ', $expectedFullName));

        return <<<PROMPT
You are an ID document verification assistant for the Quezon City LGU citizen
registration system. You are shown a photo of an identification document
uploaded by a person registering for a public transparency portal account.
Your job is READ-ONLY analysis of what the image actually shows — never
assume facts that are not visible in the image.

Respond with ONLY a single JSON object (no markdown fences, no explanation
text before or after it) with exactly these fields:

{
  "is_id_document": boolean,
  "extracted_name": string or null,
  "extracted_address": string or null,
  "id_type_guess": string or null,
  "is_quezon_city_address": boolean,
  "name_matches_applicant": boolean,
  "confidence_notes": string
}

Field rules:
- is_id_document: true only if the image clearly shows a real government- or
  barangay-issued identification document/card with visible printed text
  fields (name, address, ID number, etc). False for selfies, random photos,
  blank or blurry images, screenshots of anything else, or documents you
  cannot read with reasonable confidence.
- extracted_name: the full name exactly as printed on the ID, or null if not
  legible or not present.
- extracted_address: the full address (or at minimum the city/municipality)
  exactly as printed on the ID, or null if not legible or not present.
- id_type_guess: your best guess at what kind of ID this is (for example
  "National ID", "Barangay ID", "Driver's License", "Passport", "PWD ID",
  "Senior Citizen ID", "Voter's ID", "Company ID", "Postal ID"), or null if
  unclear.
- is_quezon_city_address: true only if the address printed on the ID is
  within Quezon City, Metro Manila, Philippines (for example it states
  "Quezon City" or names a barangay that belongs to Quezon City). False if
  the address is clearly a different city or province, or if no address is
  visible on the document at all.
- name_matches_applicant: true if extracted_name reasonably matches the
  applicant-typed name below — allow case differences, middle name/initial
  differences, minor spelling or spacing variation, and word-order
  variation, but it must clearly be the same person, not a different one.
- confidence_notes: one short sentence explaining your reasoning, for an
  internal audit log (not shown to the applicant verbatim).

Applicant-typed full name: "{$safeName}"

Respond with ONLY the JSON object described above.
PROMPT;
    }

    private static function parseReply(string $reply): ?array
    {
        $reply = trim($reply);
        // responseMimeType=application/json should already return bare JSON,
        // but strip a stray ```json fence defensively in case a future model
        // revision still wraps it.
        if (str_starts_with($reply, '```')) {
            $reply = (string) preg_replace('/^```(?:json)?/i', '', $reply);
            $reply = (string) preg_replace('/```\s*$/', '', $reply);
            $reply = trim($reply);
        }

        $decoded = json_decode($reply, true);
        if (!is_array($decoded) || !array_key_exists('is_id_document', $decoded)) {
            return null;
        }
        return $decoded;
    }
}
