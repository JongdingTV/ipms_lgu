<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../includes/qc-locations.php';
require_once __DIR__ . '/../includes/feedback-categories.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
$pdo = getDB();

const FEEDBACK_MAX_PHOTOS = 4; // matches the CIMMS request form's evidence limit
const FEEDBACK_MAX_PHOTO_BYTES = 3 * 1024 * 1024; // 3MB, must match the client-side limit
const FEEDBACK_ALLOWED_PHOTO_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

// Get citizen ID + verification status
$stmt = $pdo->prepare("SELECT id, verification_status FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

if (!$citizenId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Citizen profile not found']);
    exit;
}

// Feedback is verified-citizens-only. The dashboard hides the form for
// unverified accounts, but this server-side gate is the actual enforcement.
if (($citizen['verification_status'] ?? 'unverified') !== 'verified') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your account must be verified before you can submit feedback. Upload a valid government ID in your Profile to get verified.',
    ]);
    exit;
}

// Validate input
$category = $_POST['category'] ?? '';
$priority = $_POST['priority'] ?? 'medium';
$message = $_POST['message'] ?? '';
$district = trim($_POST['district'] ?? '');
$barangay = trim($_POST['barangay'] ?? '');
$latitudeRaw = trim($_POST['latitude'] ?? '');
$longitudeRaw = trim($_POST['longitude'] ?? '');
$concernType = $_POST['concern_type'] ?? 'project';

$errors = [];

// Maintenance reports mirror the CIMMS request form: the affected
// infrastructure is required ("specify" text wins over the dropdown), and
// the contact number must be a valid 09XX-XXX-XXXX mobile number.
$infrastructureType = null;
if ($concernType === 'maintenance') {
    $infraOther = trim($_POST['infrastructure_other'] ?? '');
    $infrastructureType = $infraOther !== '' ? $infraOther : trim($_POST['infrastructure'] ?? '');
    if ($infrastructureType === '') {
        $errors[] = 'Please select the affected infrastructure type';
    } elseif (mb_strlen($infrastructureType) > 100) {
        $errors[] = 'Infrastructure type must be 100 characters or fewer';
    }

    $pureNumber = preg_replace('/\D/', '', $_POST['contact_phone'] ?? '');
    if (!preg_match('/^09\d{9}$/', $pureNumber)) {
        $errors[] = 'Contact number must be 11 digits (09XX-XXX-XXXX) and start with 09';
    }
}
if (empty($category) || !array_key_exists($category, feedbackCategories())) {
    $errors[] = 'Invalid category';
}
if (empty($priority) || !in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
    $errors[] = 'Invalid priority';
}
if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters';
}
if ($district === '' || $barangay === '') {
    $errors[] = 'Please select your district and barangay';
} elseif (!qcIsValidLocation($district, $barangay)) {
    // Rejects mismatched pairs (e.g. a D1 barangay submitted with D3) and unknown names.
    $errors[] = 'The selected barangay does not belong to the selected district';
}

// Optional exact pin: both coordinates or neither, and roughly within Quezon City.
$latitude = null;
$longitude = null;
if ($latitudeRaw !== '' || $longitudeRaw !== '') {
    if (!is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
        $errors[] = 'Invalid pinned location';
    } else {
        $latitude = (float) $latitudeRaw;
        $longitude = (float) $longitudeRaw;
        // QC bounding box with a little slack
        if ($latitude < 14.55 || $latitude > 14.82 || $longitude < 120.96 || $longitude > 121.16) {
            $errors[] = 'The pinned location must be within Quezon City';
        }
    }
}

// Validate proof photos (optional, up to 3, 3MB each, real images only)
$photoFiles = [];
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $count = count($_FILES['photos']['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($_FILES['photos']['name'][$i] ?? '') === '') {
            continue;
        }
        if (count($photoFiles) >= FEEDBACK_MAX_PHOTOS) {
            $errors[] = 'You can attach up to ' . FEEDBACK_MAX_PHOTOS . ' photos only';
            break;
        }

        $err = $_FILES['photos']['error'][$i];
        $size = $_FILES['photos']['size'][$i];
        $tmp = $_FILES['photos']['tmp_name'][$i];

        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
                ? 'One of the photos is too large. Each photo must be 3MB or smaller.'
                : 'One of the photos failed to upload. Please try again.';
            continue;
        }
        if ($size > FEEDBACK_MAX_PHOTO_BYTES) {
            $errors[] = 'Each photo must be 3MB or smaller';
            continue;
        }

        // Verify actual file content, not just the client-supplied name/type (which can be spoofed).
        $imageInfo = @getimagesize($tmp);
        if ($imageInfo === false || !isset(FEEDBACK_ALLOWED_PHOTO_MIME[$imageInfo['mime']])) {
            $errors[] = 'Photos must be valid JPG, PNG, GIF, or WEBP images';
            continue;
        }

        $photoFiles[] = ['tmp' => $tmp, 'ext' => FEEDBACK_ALLOWED_PHOTO_MIME[$imageInfo['mime']]];
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', array_unique($errors))]);
    exit;
}

$savedPaths = [];
try {
    $uploadDir = __DIR__ . '/../../assets/img/feedback-photos/';
    if ($photoFiles && !is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO feedback (project_id, citizen_id, message, category, infrastructure_type, priority, district, barangay, latitude, longitude, status)
        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
    ");
    $stmt->execute([$citizenId, $message, $category, $infrastructureType, $priority, $district, $barangay, $latitude, $longitude]);
    $feedbackId = (int) $pdo->lastInsertId();

    if ($photoFiles) {
        $photoStmt = $pdo->prepare("INSERT INTO feedback_photos (feedback_id, photo_path) VALUES (?, ?)");
        foreach ($photoFiles as $i => $photo) {
            $fileName = 'feedback_' . $feedbackId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $photo['ext'];
            $filePath = $uploadDir . $fileName;
            if (!move_uploaded_file($photo['tmp'], $filePath)) {
                throw new RuntimeException('Failed to save feedback photo #' . ($i + 1));
            }
            $savedPaths[] = $filePath;
            $photoStmt->execute([$feedbackId, '/assets/img/feedback-photos/' . $fileName]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully',
        'id' => $feedbackId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    error_log('Feedback submission failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error submitting feedback']);
}
