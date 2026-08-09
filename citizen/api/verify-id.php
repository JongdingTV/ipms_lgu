<?php
// ============================================================
// citizen/api/verify-id.php — inline "Verify My ID" pre-check
//
// Public endpoint (no account exists yet at this point in the registration
// wizard) called from citizen/register.php's Identification step so the
// applicant finds out about a rejected/mismatched ID before filling in the
// rest of the form (password, etc.) rather than only at final submit.
//
// This is a UX convenience only — it never creates or touches any record.
// The real, unbypassable gate is citizen/register.php's own server-side
// call to IdVerification::verify() at final submission, which re-validates
// from scratch against whatever file was actually posted then. Both call
// sites share the exact same IdVerification::verify()/validateUploadedPhoto()
// logic, so this endpoint's answer can never diverge from what actually
// decides the account.
// ============================================================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/IdVerification.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrfProtection();

// Per-session rate limit — this hits the same free-tier Gemini quota as the
// chatbot, so it gets the same cost-conscious cap (api/chatbot.php uses an
// identical shape under its own session keys).
$rateLimit = 10;
$rateWindowSeconds = 600;
$now = time();
if (empty($_SESSION['id_verify_rl_start']) || ($now - $_SESSION['id_verify_rl_start']) > $rateWindowSeconds) {
    $_SESSION['id_verify_rl_start'] = $now;
    $_SESSION['id_verify_rl_count'] = 0;
}
if ($_SESSION['id_verify_rl_count'] >= $rateLimit) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => "You've tried this a lot of times recently — please wait a few minutes and try again."]);
    exit;
}
$_SESSION['id_verify_rl_count']++;

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$middleName = trim((string) ($_POST['middle_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));

if ($firstName === '' || $lastName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in your first and last name before verifying your ID.']);
    exit;
}

$photoCheck = IdVerification::validateUploadedPhoto($_FILES['id_photo'] ?? null);
if (!$photoCheck['ok']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $photoCheck['error']]);
    exit;
}

$fullName = trim(preg_replace('/\s+/', ' ', "$firstName $middleName $lastName"));
$result = IdVerification::verify($_FILES['id_photo']['tmp_name'], $photoCheck['mime_type'], $fullName);

if (!$result['success']) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode([
    'success' => true,
    'passed' => $result['passed'],
    'reasons' => $result['reasons'],
    'is_id_document' => $result['is_id_document'],
    'name_matches' => $result['name_matches'],
    'is_qc_address' => $result['is_qc_address'],
    'extracted_name' => $result['extracted_name'],
    'extracted_address' => $result['extracted_address'],
    'id_type_guess' => $result['id_type_guess'],
]);
