<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../includes/qc-locations.php';
require_once __DIR__ . '/../includes/feedback-categories.php';
require_once __DIR__ . '/../../includes/CimmClient.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
$pdo = getDB();
feedbackEnsureSchema($pdo);

const FEEDBACK_MAX_PHOTOS = 4; // matches the CIMMS request form's evidence limit
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

// Maintenance reports mirror the CIMMS request form: the affected
// infrastructure is required ("specify" text wins over the dropdown).
$infrastructureType = null;
$maintenanceLocation = '';
if ($concernType === 'maintenance') {
    $infraOther = trim($_POST['infrastructure_other'] ?? '');
    $infrastructureType = $infraOther !== '' ? $infraOther : trim($_POST['infrastructure'] ?? '');
    if ($infrastructureType === '') {
        $errors[] = 'Please select the affected infrastructure type';
    } elseif (mb_strlen($infrastructureType) > 100) {
        $errors[] = 'Infrastructure type must be 100 characters or fewer';
    }

    // The CIMMS form uses a single free-text location instead of the IPMS
    // district/barangay pair. It travels in the existing barangay column;
    // district stays NULL for maintenance reports.
    $maintenanceLocation = trim($_POST['location'] ?? '');
    if ($maintenanceLocation === '') {
        $errors[] = 'Location is required';
    }
    $district = '';
    $barangay = mb_substr($maintenanceLocation, 0, 100);
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
if ($concernType !== 'maintenance') {
    if ($district === '' || $barangay === '') {
        $errors[] = 'Please select your district and barangay';
    } elseif (!qcIsValidLocation($district, $barangay)) {
        // Rejects mismatched pairs (e.g. a D1 barangay submitted with D3) and unknown names.
        $errors[] = 'The selected barangay does not belong to the selected district';
    }
}

// Maintenance reports forward straight to CIMMS, which either uses the exact
// pin we send or has to geocode the free-text location itself — the latter
// can resolve to a different spot each time the request is viewed, which is
// exactly the "pin moves around" bug this required pin fixes. Regular
// project feedback keeps the pin optional, same as before.
if ($concernType === 'maintenance' && ($latitudeRaw === '' || $longitudeRaw === '')) {
    $errors[] = 'Please tap the exact spot on the map to pin your location.';
}

// Both coordinates or neither, and roughly within Quezon City.
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
        $errors[] = 'Contact number must be 11 digits (09XX-XXX-XXXX) and start with 09';
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

// Validate proof photos (optional, 3MB each, real images only)
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
            project_id, citizen_id, citizen_name, message, category, infrastructure_type, concern_type,
            anonymous, contact_name, contact_phone, contact_email,
            cimm_sync_status, priority, district, barangay, latitude, longitude, status
        ) VALUES (
            NULL, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, 'open'
        )
    ");
    $stmt->execute([
        $citizenId,
        $citizenNameForRow,
        $message,
        $category,
        $infrastructureType,
        $concernType,
        $isAnonymous ? 1 : 0,
        $contactName !== '' ? $contactName : null,
        $resolvedPhone !== '' ? $resolvedPhone : null,
        $resolvedEmail !== '' ? $resolvedEmail : null,
        $cimmStatus,
        $priority,
        $district ?: null,
        $barangay ?: null,
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
                throw new RuntimeException(sprintf('Failed to save feedback photo #%d', $i + 1));
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
            // CIMMS' own request form only offers 6 fixed infrastructure
            // types (see ipms-requests.php's $allowedInfra) — it has no
            // "Other" option and rejects anything outside that enum with a
            // 422. Our replica form adds an "Other" free-text option for
            // IPMS's own records, so only forward the value when it's one
            // CIMMS actually recognizes; otherwise let CimmClient derive a
            // safe fallback from the category and keep the citizen's exact
            // wording in the issue text instead of silently losing it.
            $cimmInfrastructure = in_array($infrastructureType, CimmClient::ALLOWED_INFRASTRUCTURE, true)
                ? $infrastructureType
                : '';
            $cimmMessage = $message;
            if ($cimmInfrastructure === '' && $infrastructureType !== '') {
                $cimmMessage = '[' . $infrastructureType . '] ' . $message;
            }

            $result = CimmClient::submitRequest([
                'feedback_id' => $feedbackId,
                'category' => $category,
                'priority' => $priority,
                'message' => $cimmMessage,
                'district' => $district,
                'barangay' => $barangay,
                // Pass through only when it matches CIMMS' closed enum;
                // otherwise leave it blank so CimmClient falls back to
                // mapInfrastructure($category).
                'infrastructure' => $cimmInfrastructure,
                'location' => $maintenanceLocation,
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
                'status' => 'pending',
                'request_id' => null,
                'reference' => null,
                'message' => 'CIMMS integration is not configured on this server',
            ];
            $upd = $pdo->prepare("
                UPDATE feedback
                SET cimm_sync_status = 'pending', cimm_last_error = ?
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
