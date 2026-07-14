<?php
require_once __DIR__ . '/../../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
requireCsrfProtection();

const MAX_ID_PHOTO_BYTES = 3 * 1024 * 1024; // 3MB, must match register.php
const ALLOWED_ID_PHOTO_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, id_photo_path, verification_status FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();

if (!$citizen) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Citizen profile not found']);
    exit;
}

// A verified account's ID has already been cross-checked by staff; replacing it
// silently would bypass that review, so it's locked once verified.
if ($citizen['verification_status'] === 'verified') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Your account is already verified.']);
    exit;
}

if (empty($_FILES['id_photo']['name'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please choose an ID photo to upload.']);
    exit;
}

$file = $_FILES['id_photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ID photo is too large. Please upload a file 3MB or smaller.',
        UPLOAD_ERR_PARTIAL => 'ID photo upload was interrupted. Please try again.',
        default => 'Failed to upload ID photo. Please try again.',
    };
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($file['size'] > MAX_ID_PHOTO_BYTES) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID photo must be 3MB or smaller.']);
    exit;
}

// Verify actual file content, not just the client-supplied name/type (which can be spoofed).
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false || !isset(ALLOWED_ID_PHOTO_MIME[$imageInfo['mime']])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID photo must be a valid JPG, PNG, GIF, or WEBP image.']);
    exit;
}
$ext = ALLOWED_ID_PHOTO_MIME[$imageInfo['mime']];

try {
    $uploadDir = __DIR__ . '/../../assets/img/citizen-ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'citizen_id_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new RuntimeException('Failed to save uploaded ID photo');
    }

    $newPath = '/assets/img/citizen-ids/' . $fileName;
    $oldPath = $citizen['id_photo_path'];

    // Re-submissions (e.g. after a rejection) go back to 'unverified' for staff review.
    $stmt = $pdo->prepare("UPDATE citizens SET id_photo_path = ?, verification_status = 'unverified' WHERE id = ?");
    $stmt->execute([$newPath, (int) $citizen['id']]);

    if ($oldPath) {
        $oldFull = __DIR__ . '/../../' . ltrim($oldPath, '/');
        if (is_file($oldFull)) {
            unlink($oldFull);
        }
    }

    logActivity((int) $user['user_id'], 'id_photo_uploaded', 'Citizen submitted an ID photo for verification');

    echo json_encode([
        'success' => true,
        'message' => 'ID photo submitted! Our staff will review it and verify your account.',
        'id_photo_path' => $newPath,
    ]);
} catch (Throwable $e) {
    error_log('Citizen ID upload failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save your ID photo. Please try again.']);
}
