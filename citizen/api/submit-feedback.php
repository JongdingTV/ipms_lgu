<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../includes/qc-locations.php';
require_once __DIR__ . '/../includes/feedback-categories.php';
require_once __DIR__ . '/../../includes/CimmClient.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
$pdo = getDB();

const FEEDBACK_MAX_PHOTOS = 3;
const FEEDBACK_MAX_PHOTO_BYTES = 3 * 1024 * 1024; // 3MB, must match the client-side limit
const FEEDBACK_ALLOWED_PHOTO_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

// Get citizen ID + verification status + contact defaults for CIMMS sync
$stmt = $pdo->prepare("
    SELECT id, verification_status, first_name, middle_name, last_name, phone, email
    FROM citizens WHERE user_id = ?
");
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
$concernType = trim($_POST['concern_type'] ?? 'project');
$isAnonymous = !empty($_POST['anonymous']);
$contactName = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');

$errors = [];
if (!in_array($concernType, ['project', 'maintenance'], true)) {
    $errors[] = 'Invalid concern type';
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

if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid contact email';
}

// Resolve contact number: form override → citizen profile. CIMMS requires 09XXXXXXXXX.
$resolvedPhone = preg_replace('/\D+/', '', $contactPhone !== '' ? $contactPhone : (string) ($citizen['phone'] ?? ''));
if ($concernType === 'maintenance') {
    if ($resolvedPhone === '' || !preg_match('/^09\d{9}$/', $resolvedPhone)) {
        $errors[] = 'A valid Philippine mobile number (09XXXXXXXXX) is required for maintenance reports forwarded to CIMMS';
    }
}

$citizenFullName = trim(implode(' ', array_filter([
    $citizen['first_name'] ?? '',
    $citizen['middle_name'] ?? '',
    $citizen['last_name'] ?? '',
])));
$resolvedName = $isAnonymous
    ? ($contactName !== '' ? $contactName : null)
    : ($contactName !== '' ? $contactName : ($citizenFullName !== '' ? $citizenFullName : null));
$resolvedEmail = $contactEmail !== '' ? $contactEmail : (string) ($citizen['email'] ?? '');

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
$cimmSync = [
    'status' => 'none',
    'request_id' => null,
    'reference' => null,
    'message' => null,
];

try {
    $uploadDir = __DIR__ . '/../../assets/img/feedback-photos/';
    if ($photoFiles && !is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $pdo->beginTransaction();

    $cimmStatus = $concernType === 'maintenance' ? 'pending' : 'none';
    $citizenNameForRow = $isAnonymous ? null : $resolvedName;

    $stmt = $pdo->prepare("
        INSERT INTO feedback (
            project_id, citizen_id, citizen_name, message, category, concern_type,
            anonymous, contact_name, contact_phone, contact_email,
            cimm_sync_status, priority, district, barangay, latitude, longitude, status
        ) VALUES (
            NULL, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, 'open'
        )
    ");
    $stmt->execute([
        $citizenId,
        $citizenNameForRow,
        $message,
        $category,
        $concernType,
        $isAnonymous ? 1 : 0,
        $contactName !== '' ? $contactName : null,
        $resolvedPhone !== '' ? $resolvedPhone : null,
        $resolvedEmail !== '' ? $resolvedEmail : null,
        $cimmStatus,
        $priority,
        $district,
        $barangay,
        $latitude,
        $longitude,
    ]);
    $feedbackId = (int) $pdo->lastInsertId();

    $absolutePhotoPaths = [];
    if ($photoFiles) {
        $photoStmt = $pdo->prepare("INSERT INTO feedback_photos (feedback_id, photo_path) VALUES (?, ?)");
        foreach ($photoFiles as $i => $photo) {
            $fileName = 'feedback_' . $feedbackId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $photo['ext'];
            $filePath = $uploadDir . $fileName;
            if (!move_uploaded_file($photo['tmp'], $filePath)) {
                throw new RuntimeException('Failed to save feedback photo #' . ($i + 1));
            }
            $savedPaths[] = $filePath;
            $absolutePhotoPaths[] = $filePath;
            $photoStmt->execute([$feedbackId, '/assets/img/feedback-photos/' . $fileName]);
        }
    }

    $pdo->commit();

    // Forward maintenance concerns to CIMMS after the local commit so a CIMMS
    // outage never rolls back the citizen's IPMS submission.
    if ($concernType === 'maintenance') {
        if (CimmClient::isEnabled()) {
            $result = CimmClient::submitRequest([
                'feedback_id' => $feedbackId,
                'category' => $category,
                'priority' => $priority,
                'message' => $message,
                'district' => $district,
                'barangay' => $barangay,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $resolvedName,
                'contact_number' => $resolvedPhone,
                'email' => $resolvedEmail,
                'anonymous' => $isAnonymous,
            ], $absolutePhotoPaths);

            $cimmSync = [
                'status' => $result['success'] ? 'synced' : 'failed',
                'request_id' => $result['request_id'],
                'reference' => $result['reference'],
                'message' => $result['message'],
            ];

            $upd = $pdo->prepare("
                UPDATE feedback
                SET cimm_sync_status = ?,
                    cimm_request_id = ?,
                    cimm_reference = ?,
                    cimm_synced_at = CASE WHEN ? = 'synced' THEN NOW() ELSE NULL END,
                    cimm_last_error = ?
                WHERE id = ?
            ");
            $upd->execute([
                $cimmSync['status'],
                $cimmSync['request_id'],
                $cimmSync['reference'],
                $cimmSync['status'],
                $result['success'] ? null : $result['message'],
                $feedbackId,
            ]);
        } else {
            $cimmSync = [
                'status' => 'failed',
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS integration is not configured on this server',
            ];
            $upd = $pdo->prepare("
                UPDATE feedback
                SET cimm_sync_status = 'failed', cimm_last_error = ?
                WHERE id = ?
            ");
            $upd->execute([$cimmSync['message'], $feedbackId]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $concernType === 'maintenance'
            ? ($cimmSync['status'] === 'synced'
                ? 'Feedback submitted and forwarded to CIMMS'
                : 'Feedback saved in IPMS. Forwarding to CIMMS is pending or failed — staff can retry later.')
            : 'Feedback submitted successfully',
        'id' => $feedbackId,
        'concern_type' => $concernType,
        'cimm' => $cimmSync,
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
