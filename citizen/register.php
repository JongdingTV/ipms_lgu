<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

const MAX_ID_PHOTO_BYTES = 3 * 1024 * 1024; // 3MB
const ALLOWED_ID_PHOTO_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
const MAX_REGISTRATIONS_PER_WINDOW = 8;
const REGISTRATION_WINDOW_MINUTES = 30;

function isRegistrationRateLimited(string $ipAddress): bool
{
    if (APP_ENV !== 'production') {
        // Local/dev testing repeatedly hits this from the same machine IP (typos,
        // retries, automated smoke tests) with no abuse involved. Only enforce in prod.
        return false;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM registration_attempts
            WHERE ip_address = ? AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ipAddress, REGISTRATION_WINDOW_MINUTES]);
        return (int) $stmt->fetchColumn() >= MAX_REGISTRATIONS_PER_WINDOW;
    } catch (Throwable $e) {
        return false;
    }
}

function recordRegistrationAttempt(string $ipAddress): void
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO registration_attempts (ip_address, attempted_at) VALUES (?, NOW())");
        $stmt->execute([$ipAddress]);
        $pdo->exec("DELETE FROM registration_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    } catch (Throwable $e) {
    }
}

$errors = [];
$formData = [];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Get form data on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require valid CSRF token for registration
    requireCsrfProtection();
    recordRegistrationAttempt($ipAddress);
    $formData = $_POST;

    if (isRegistrationRateLimited($ipAddress)) {
        $errors[] = 'Too many registration attempts from this network. Please try again later.';
    }

    // Validation
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $civilStatus = trim($_POST['civil_status'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($dateOfBirth)) $errors[] = 'Date of birth is required';
    if (empty($gender)) $errors[] = 'Gender is required';
    if (empty($civilStatus)) $errors[] = 'Civil status is required';
    if (empty($address)) $errors[] = 'Address is required';
    if (empty($barangay)) $errors[] = 'Barangay is required';
    if (empty($city)) $errors[] = 'City is required';
    if (empty($province)) $errors[] = 'Province is required';
    if (empty($idType)) $errors[] = 'ID type is required';
    if (empty($idNumber)) $errors[] = 'ID number is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($confirmPassword)) $errors[] = 'Please confirm your password';

    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Phone validation (basic)
    if (!empty($phone) && !preg_match('/^\+?[0-9]{10,15}$/', str_replace([' ', '-', '(', ')'], '', $phone))) {
        $errors[] = 'Phone number must be 10-15 digits';
    }

    // Date of birth validation
    if (!empty($dateOfBirth)) {
        $dob = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
        if (!$dob || $dob->format('Y-m-d') !== $dateOfBirth) {
            $errors[] = 'Invalid date format. Please use YYYY-MM-DD';
        } else {
            $today = new DateTime();
            $age = $today->diff($dob)->y;
            if ($age < 18) {
                $errors[] = 'You must be at least 18 years old to register';
            }
        }
    }

    // Username validation
    if (!empty($username)) {
        if (strlen($username) < 5) {
            $errors[] = 'Username must be at least 5 characters long';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, hyphens, and underscores';
        }
    }

    // Password validation
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[!@#$%^&*]/', $password)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&*)';
        }
    }

    if (!empty($password) && $password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    // ID photo is optional: an account can be created without one, but stays
    // 'unverified' (see verification_status below) until the citizen submits it.
    $idPhotoExt = null;
    $idPhotoProvided = !empty($_FILES['id_photo']['name'] ?? '');
    if ($idPhotoProvided) {
        $file = $_FILES['id_photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ID photo is too large. Please upload a file 3MB or smaller.',
                UPLOAD_ERR_PARTIAL => 'ID photo upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Could not save the uploaded file. Please try again.',
                default => 'Failed to upload ID photo. Please try again.',
            };
        } elseif ($file['size'] > MAX_ID_PHOTO_BYTES) {
            $errors[] = 'ID photo must be 3MB or smaller. Please compress the image or retake a smaller photo.';
        } else {
            // Verify actual file content, not just the client-supplied name/type (which can be spoofed).
            $imageInfo = is_uploaded_file($file['tmp_name'] ?? '') ? @getimagesize($file['tmp_name']) : false;
            if ($imageInfo === false || !isset(ALLOWED_ID_PHOTO_MIME[$imageInfo['mime']])) {
                $errors[] = 'ID photo must be a valid JPG, PNG, GIF, or WEBP image.';
            } else {
                $idPhotoExt = ALLOWED_ID_PHOTO_MIME[$imageInfo['mime']];
            }
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $pdo = getDB();

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists. Please choose different credentials.';
            } else {
                $idPhotoPath = null;
                $filePath = null;

                if ($idPhotoProvided) {
                    $uploadDir = __DIR__ . '/../assets/img/citizen-ids/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileName = 'citizen_id_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $idPhotoExt;
                    $filePath = $uploadDir . $fileName;
                }

                $pdo->beginTransaction();
                try {
                    if ($idPhotoProvided) {
                        if (!move_uploaded_file($_FILES['id_photo']['tmp_name'], $filePath)) {
                            throw new RuntimeException('Failed to upload ID photo');
                        }
                        $idPhotoPath = '/assets/img/citizen-ids/' . $fileName;
                    }

                    // Create user account. Status starts 'inactive' — the account
                    // is only activated once the citizen verifies the OTP sent to
                    // their email (see citizen/verify-otp.php).
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, role, status)
                        VALUES (?, ?, ?, ?, 'citizen', 'inactive')
                    ");
                    $stmt->execute([$username, $email, $passwordHash, "$firstName $lastName"]);
                    $userId = $pdo->lastInsertId();

                    // Create citizen profile. verification_status stays 'unverified' either way —
                    // without a photo there's simply nothing yet for staff to cross-check.
                    $stmt = $pdo->prepare("
                        INSERT INTO citizens (
                            user_id, first_name, last_name, middle_name, email, phone,
                            date_of_birth, gender, civil_status, address, barangay, city, province, postal_code,
                            id_type, id_number, id_photo_path, verification_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unverified')
                    ");
                    $stmt->execute([
                        $userId, $firstName, $lastName, $middleName, $email, $phone,
                        $dateOfBirth, $gender, $civilStatus, $address, $barangay, $city, $province, $postalCode,
                        $idType, $idNumber, $idPhotoPath
                    ]);

                    $pdo->commit();

                    // Send an OTP to verify the citizen's email before their
                    // account can log in.
                    $otp = new OTPManager();
                    $otpResult = $otp->createOTP($userId, 'citizen_verification');

                    $_SESSION['pending_otp_user_id'] = $userId;
                    $_SESSION['pending_otp_email'] = $email;
                    // Carried through the OTP step so the post-verification login
                    // page can still remind them to submit an ID photo.
                    $_SESSION['pending_needs_id'] = !$idPhotoProvided;

                    if ($otpResult['success']) {
                        $sendResult = $otp->sendOTPEmail($email, "$firstName $lastName", $otpResult['otp_code']);
                        if (!$sendResult['success'] && !empty($sendResult['dev_fallback'])) {
                            // Mail isn't configured on this environment (e.g. local
                            // dev without SMTP credentials) — surface the code
                            // directly so the flow stays testable end-to-end.
                            $_SESSION['dev_otp_preview'] = $otpResult['otp_code'];
                        }
                    }

                    header('Location: ' . appUrl('/citizen/verify-otp.php'));
                    exit;
                } catch (Throwable $inner) {
                    $pdo->rollBack();
                    if ($filePath !== null && is_file($filePath)) {
                        unlink($filePath);
                    }
                    throw $inner;
                }
            }
        } catch (PDOException $e) {
            error_log('Citizen registration failed: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                $errors[] = 'That username, email, or ID number is already registered.';
            } else {
                $errors[] = 'Registration failed due to a system error. Please try again later.';
            }
        } catch (Throwable $e) {
            error_log('Citizen registration failed: ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again, and contact support if the problem continues.';
        }
    }
}

$idTypes = [
    'National ID' => 'National ID (PhilID)',
    'Passport' => 'Passport',
    'Driver License' => "Driver's License",
    'Senior Citizen ID' => 'Senior Citizen ID',
    'PWD ID' => 'PWD ID',
    'Barangay ID' => 'Barangay ID',
];

$genders = ['Male', 'Female', 'Other'];
$civilStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Registration - IPMS</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#063b33">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: blob:; script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self' blob:;">
    <style>
        :root {
            --ink: #10201d;
            --muted: #52615d;
            --deep: #063b33;
            --green: #0f7a5f;
            --mint: #d9f3e7;
            --gold: #f6b83f;
            --red: #d64a3a;
            --paper: #fbfaf5;
            --white: #ffffff;
            --line: #dce4dd;
            --shadow: 0 24px 60px rgba(16, 32, 29, .14);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            padding: 2rem 1rem;
            -webkit-font-smoothing: antialiased;
        }

        .registration-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            overflow: hidden;
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
        }

        .reg-header {
            background: linear-gradient(150deg, var(--deep), var(--green) 65%, #128a6c);
            color: var(--white);
            padding: 2.25rem 2rem;
            text-align: center;
        }

        .reg-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            margin: 0 auto 0.85rem;
            display: block;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 12px;
            padding: 8px;
        }

        .reg-header h1 {
            font-size: 1.7rem;
            margin-bottom: 0.4rem;
        }

        .reg-header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
        }

        .reg-body {
            padding: 2rem;
        }

        .alerts {
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .alert-error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f6cac6;
        }

        .alert-info {
            background: var(--mint);
            color: #0b5c46;
            border: 1px solid #b7e6d3;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--green);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.4rem;
            font-size: 0.88rem;
        }

        .required {
            color: var(--red);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="file"],
        select {
            padding: 0.7rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 0.93rem;
            font-family: inherit;
            background: #fbfaf5;
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="date"]:focus,
        input[type="file"]:focus,
        select:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(15, 122, 95, 0.14);
            background: var(--white);
        }

        .file-upload {
            border: 2px dashed var(--green);
            border-radius: 10px;
            padding: 1.1rem;
            text-align: center;
            background: var(--mint);
            cursor: pointer;
            transition: all 0.2s;
            display: block;
        }

        .file-upload:hover {
            background: #c7ecdc;
            border-color: var(--deep);
        }

        .file-upload input {
            /* Not display:none — a hidden *required* file input fails the browser's
               native form validation with no visible error (Chrome silently refuses
               to submit and only logs a console warning), which reads as "the submit
               button does nothing." Visually hidden but still focusable/validatable. */
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .file-upload p {
            color: #0b5c46;
            font-size: 0.9rem;
            margin: 0;
        }

        .password-requirements {
            background: #f5f8f6;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 0.5rem;
        }

        .password-requirements ul {
            list-style: none;
            margin: 0.5rem 0 0;
            padding-left: 0;
        }

        .password-requirements li {
            margin: 0.3rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .password-requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--green);
        }

        .password-requirements li.unmet:before {
            content: "✗";
            color: var(--red);
        }

        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        button {
            padding: 0.85rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--green), var(--deep));
            color: var(--white);
            box-shadow: 0 12px 24px rgba(15, 122, 95, 0.22);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(15, 122, 95, 0.26);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
            border: 1px solid var(--line);
        }

        .btn-cancel:hover {
            background: #e4e9e6;
        }

        .back-link {
            margin-bottom: 1.5rem;
        }

        .back-link a {
            color: var(--green);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: var(--deep);
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                grid-template-columns: 1fr;
            }
        }

        /* AI-assist styles */
        .ai-detection-container {
            background: #f5f8f6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .preview-section {
            display: none;
            text-align: center;
            margin-bottom: 1rem;
        }

        .preview-section.active {
            display: block;
        }

        #id_preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 2px solid var(--green);
        }

        .detection-status {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .detection-status.active {
            display: block;
        }

        .detection-status.processing {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #1976d2;
        }

        .detection-status.success {
            background: var(--mint);
            color: #0b5c46;
            border: 1px solid #b7e6d3;
        }

        .detection-status.warning {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #e65100;
        }

        .detection-status.error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f6cac6;
        }

        .detection-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(25, 118, 210, 0.3);
            border-top-color: #1976d2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .detection-results {
            display: none;
            margin-top: 1rem;
        }

        .detection-results.active {
            display: block;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .result-label {
            font-weight: 600;
            color: var(--ink);
            flex: 0 0 150px;
        }

        .result-value {
            flex: 1;
            color: var(--muted);
            margin: 0 1rem;
            word-break: break-word;
        }

        .result-confidence {
            flex: 0 0 80px;
            text-align: right;
        }

        .confidence-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .confidence-high {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .confidence-medium {
            background: #ffe0b2;
            color: #e65100;
        }

        .confidence-low {
            background: #ffcdd2;
            color: #c62828;
        }

        .ai-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-small {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-accept {
            background: var(--green);
            color: white;
        }

        .btn-accept:hover {
            background: var(--deep);
        }

        .btn-retry {
            background: var(--gold);
            color: #4a3200;
        }

        .btn-retry:hover {
            background: #e0a72c;
        }

        .verification-notice {
            background: #fff9c4;
            border-left: 4px solid var(--gold);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.88rem;
            color: #594000;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="reg-header">
            <img class="reg-logo" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="IPMS logo">
            <h1>Create Citizen Account</h1>
            <p>Identity verification helps us serve genuine residents of Quezon City</p>
        </div>

        <div class="reg-body">
            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alerts">
                    <div class="alert alert-error">
                        <strong>Registration Error:</strong>
                        <ul style="margin: 0.5rem 0 0; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="alerts">
                <div class="alert alert-info">
                    <strong>Note:</strong> Your account is created right away, but your ID stays <strong>unverified</strong> until our staff manually cross-checks it. You can browse projects immediately; verified status unlocks features that require a confirmed identity (e.g. filing formal feedback tied to your name).
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" autocomplete="on" id="registerForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <!-- Personal Information -->
                <div class="form-section">
                    <h3 class="section-title">Personal Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($formData['middle_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($formData['date_of_birth'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <?php foreach ($genders as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>" <?= ($formData['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status">Civil Status <span class="required">*</span></label>
                            <select id="civil_status" name="civil_status" required>
                                <option value="">Select Civil Status</option>
                                <?php foreach ($civilStatuses as $cs): ?>
                                    <option value="<?= htmlspecialchars($cs) ?>" <?= ($formData['civil_status'] ?? '') === $cs ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cs) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3 class="section-title">Contact Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                            <input type="text" id="phone" name="phone" placeholder="+63 or 09xx xxxx xxxx" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <h3 class="section-title">Address</h3>

                    <div class="form-group">
                        <label for="address">Street Address <span class="required">*</span></label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($formData['address'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="barangay">Barangay <span class="required">*</span></label>
                            <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($formData['barangay'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="city">City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" value="<?= htmlspecialchars($formData['city'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="province">Province <span class="required">*</span></label>
                            <input type="text" id="province" name="province" value="<?= htmlspecialchars($formData['province'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($formData['postal_code'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Identification -->
                <div class="form-section">
                    <h3 class="section-title">Identification</h3>

                    <div class="verification-notice">
                        <strong>📷 Optional AI-assisted autofill:</strong> After you upload your ID, we'll try to read the face and text on it to pre-fill some fields below. This is only a convenience — it does not verify your identity. Please double-check every field before submitting.
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_type">ID Type <span class="required">*</span></label>
                            <select id="id_type" name="id_type" required>
                                <option value="">Select ID Type</option>
                                <?php foreach ($idTypes as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= ($formData['id_type'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_number">ID Number <span class="required">*</span></label>
                            <input type="text" id="id_number" name="id_number" value="<?= htmlspecialchars($formData['id_number'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_photo">ID Photo/Document <span style="color: var(--muted); font-weight: 500;">(optional — you can add this later, but your account stays unverified until you do)</span></label>
                        <label for="id_photo" class="file-upload" id="uploadLabel">
                            <input type="file" id="id_photo" name="id_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <p>📷 Click to upload your ID photo</p>
                            <p style="font-size: 0.8rem; color: #4d6b60; margin-top: 0.5rem;">JPG, PNG, GIF, or WEBP — max 3MB. Large photos are automatically resized to fit.</p>
                        </label>

                        <!-- Upload / AI assist status -->
                        <div class="ai-detection-container">
                            <div class="preview-section" id="previewSection">
                                <img id="id_preview" alt="ID Preview">
                            </div>

                            <div class="detection-status" id="detectionStatus">
                                <span class="detection-spinner"></span>
                                <span id="statusText">Processing ID image...</span>
                            </div>

                            <div class="detection-results" id="detectionResults">
                                <div class="result-item">
                                    <div class="result-label">Face Detected</div>
                                    <div class="result-value" id="resultFace">-</div>
                                    <div class="result-confidence"><span class="confidence-badge confidence-high" id="faceBadge">-</span></div>
                                </div>

                                <div class="result-item">
                                    <div class="result-label">Name</div>
                                    <div class="result-value" id="resultName">-</div>
                                    <div class="result-confidence"><span class="confidence-badge confidence-medium" id="nameBadge">-</span></div>
                                </div>

                                <div class="result-item">
                                    <div class="result-label">ID Number</div>
                                    <div class="result-value" id="resultIDNumber">-</div>
                                    <div class="result-confidence"><span class="confidence-badge confidence-medium" id="idnumBadge">-</span></div>
                                </div>

                                <div class="result-item">
                                    <div class="result-label">Address</div>
                                    <div class="result-value" id="resultAddress">-</div>
                                    <div class="result-confidence"><span class="confidence-badge confidence-medium" id="addressBadge">-</span></div>
                                </div>

                                <div class="ai-actions">
                                    <button type="button" class="btn-small btn-accept" onclick="acceptDetectedData()">✓ Accept & Fill</button>
                                    <button type="button" class="btn-small btn-retry" onclick="retryDetection()">↻ Try Again</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Credentials -->
                <div class="form-section">
                    <h3 class="section-title">Account Credentials</h3>

                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($formData['username'] ?? '') ?>" required>
                        <small style="color: var(--muted); margin-top: 0.3rem; display: block;">We suggest one based on your Gmail so it's easy to remember — feel free to change it. 5+ characters, letters, numbers, hyphens.</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                        <div class="password-requirements">
                            <p><strong>Password must contain:</strong></p>
                            <ul>
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-upper">One uppercase letter (A-Z)</li>
                                <li id="req-lower">One lowercase letter (a-z)</li>
                                <li id="req-number">One number (0-9)</li>
                                <li id="req-special">One special character (!@#$%^&*)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>" class="btn-cancel" style="text-align: center; display: flex; align-items: center; justify-content: center;">Cancel</a>
                    <button type="submit" class="btn-submit" id="submitBtn">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Suggest a username from the email's local part, since most people register
        // with the Gmail they already remember. Only ever fills a username the user
        // hasn't typed into themselves, and never overwrites a manual edit.
        const emailInput = document.getElementById('email');
        const usernameInput = document.getElementById('username');
        let usernameTouched = usernameInput.value.trim() !== '';

        usernameInput.addEventListener('input', () => {
            usernameTouched = usernameInput.value.trim() !== '';
        });

        emailInput.addEventListener('input', () => {
            if (usernameTouched) return;
            const localPart = emailInput.value.split('@')[0] || '';
            usernameInput.value = localPart.toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 50);
        });

        const passwordInput = document.getElementById('password');

        function validatePassword() {
            const password = passwordInput.value;

            document.getElementById('req-length').classList.toggle('unmet', password.length < 8);
            document.getElementById('req-upper').classList.toggle('unmet', !/[A-Z]/.test(password));
            document.getElementById('req-lower').classList.toggle('unmet', !/[a-z]/.test(password));
            document.getElementById('req-number').classList.toggle('unmet', !/[0-9]/.test(password));
            document.getElementById('req-special').classList.toggle('unmet', !/[!@#$%^&*]/.test(password));
        }

        passwordInput.addEventListener('input', validatePassword);
        validatePassword(); // Initial check

        // ========== UPLOAD + COMPRESSION + AI ASSIST =========
        const MAX_UPLOAD_BYTES = 3 * 1024 * 1024; // 3MB, must match server-side limit
        const MAX_DIMENSION = 1920;

        let detectedData = null;
        const idPhotoInput = document.getElementById('id_photo');
        const previewSection = document.getElementById('previewSection');
        const preview = document.getElementById('id_preview');
        const detectionStatus = document.getElementById('detectionStatus');
        const statusText = document.getElementById('statusText');
        const detectionResults = document.getElementById('detectionResults');
        const submitBtn = document.getElementById('submitBtn');

        function setStatus(message, cls) {
            detectionStatus.className = 'detection-status active ' + cls;
            statusText.textContent = message;
        }

        function clearStatus() {
            detectionStatus.className = 'detection-status';
            detectionResults.classList.remove('active');
        }

        function loadImage(src) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = src;
            });
        }

        function readAsDataURL(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        // Downscale + re-encode oversized photos client-side so a clear phone photo
        // still fits the 3MB cap instead of being rejected outright.
        async function compressImageFile(file) {
            const dataUrl = await readAsDataURL(file);
            const img = await loadImage(dataUrl);

            let { width, height } = img;
            if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
                const scale = MAX_DIMENSION / Math.max(width, height);
                width = Math.round(width * scale);
                height = Math.round(height * scale);
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);

            let quality = 0.9;
            let blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', quality));
            while (blob && blob.size > MAX_UPLOAD_BYTES && quality > 0.3) {
                quality -= 0.1;
                blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', quality));
            }

            if (!blob || blob.size > MAX_UPLOAD_BYTES) {
                return null;
            }

            const newName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
            return new File([blob], newName, { type: 'image/jpeg' });
        }

        // Handle file selection
        idPhotoInput.addEventListener('change', async (e) => {
            let file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                setStatus('Please select an image file (JPG, PNG, GIF, or WEBP).', 'error');
                idPhotoInput.value = '';
                return;
            }

            if (file.size > MAX_UPLOAD_BYTES) {
                setStatus('🗜️ This photo is over 3MB — optimizing it, please wait...', 'processing');
                try {
                    const compressed = await compressImageFile(file);
                    if (!compressed) {
                        setStatus('This image is too large even after compression. Please retake the photo at a lower resolution.', 'error');
                        idPhotoInput.value = '';
                        return;
                    }
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    idPhotoInput.files = dt.files;
                    file = compressed;
                } catch (err) {
                    console.error('Compression failed:', err);
                    setStatus('Could not process this image. Please try a different photo.', 'error');
                    idPhotoInput.value = '';
                    return;
                }
            }

            clearStatus();

            // Show preview
            const dataUrl = await readAsDataURL(file);
            preview.src = dataUrl;
            previewSection.classList.add('active');

            // Start optional AI assist
            await detectID(dataUrl);
        });

        // Load AI libraries
        const loadAILibraries = async () => {
            if (!window.faceapi) {
                const script1 = document.createElement('script');
                script1.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
                document.head.appendChild(script1);
                await new Promise((resolve, reject) => {
                    script1.onload = resolve;
                    script1.onerror = reject;
                });
            }

            if (!window.Tesseract) {
                const script2 = document.createElement('script');
                script2.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js';
                document.head.appendChild(script2);
                await new Promise((resolve, reject) => {
                    script2.onload = resolve;
                    script2.onerror = reject;
                });
            }
        };

        // Main detection function
        async function detectID(imageSrc) {
            try {
                await loadAILibraries();

                setStatus('🔍 Analyzing ID document...', 'processing');
                detectionResults.classList.remove('active');
                detectedData = {
                    faceDetected: false,
                    faceConfidence: 0,
                    name: '',
                    nameConfidence: 0,
                    idNumber: '',
                    idNumberConfidence: 0,
                    address: '',
                    addressConfidence: 0
                };

                await detectFace(imageSrc);
                await extractText(imageSrc);
                displayResults();
            } catch (error) {
                console.error('Detection error:', error);
                setStatus('The optional AI-assist could not run, but you can still fill in the form and submit normally.', 'warning');
            }
        }

        async function detectFace(imageSrc) {
            try {
                const img = await loadImage(imageSrc);

                const modelsUrl = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
                await faceapi.nets.tinyFaceDetector.load(modelsUrl);
                await faceapi.nets.faceLandmark68Net.load(modelsUrl);

                const detections = await faceapi.detectAllFaces(img, new faceapi.TinyFaceDetectorOptions());

                if (detections.length > 0) {
                    detectedData.faceDetected = true;
                    detectedData.faceConfidence = Math.round(detections[0].score * 100);
                } else {
                    detectedData.faceDetected = false;
                    detectedData.faceConfidence = 0;
                }
            } catch (error) {
                console.warn('Face detection not available:', error.message);
            }
        }

        async function extractText(imageSrc) {
            try {
                statusText.textContent = '📝 Extracting text from ID...';

                const result = await Tesseract.recognize(imageSrc, 'eng', {
                    logger: m => console.log('OCR progress:', m.progress)
                });

                extractIDInfo(result.data.text);
            } catch (error) {
                console.error('OCR error:', error);
                detectedData.name = 'N/A';
                detectedData.idNumber = 'N/A';
                detectedData.address = 'N/A';
            }
        }

        function extractIDInfo(text) {
            const nameMatch = text.match(/^([A-Z\s]+?)(?:\n|$)/m);
            if (nameMatch) {
                detectedData.name = nameMatch[1].trim();
                detectedData.nameConfidence = 75;
            }

            const idPatterns = [
                /ID\s*[:=]?\s*(\d{2,}[-\s]?\d{2,}[-\s]?\d{2,})/i,
                /(\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4})/,
                /(\d{12,})/
            ];

            for (let pattern of idPatterns) {
                const match = text.match(pattern);
                if (match) {
                    detectedData.idNumber = match[1].replace(/[-\s]/g, '');
                    detectedData.idNumberConfidence = 70;
                    break;
                }
            }

            const addressPatterns = [
                /(?:Address|Address:)\s*[:=]?\s*([^\n]{10,})/i,
                /(.*?(?:Purok|Brgy|Barangay|St\.|Ave|Rd)[^\n]*)/i,
                /([A-Z][a-z\s,]{15,})/
            ];

            for (let pattern of addressPatterns) {
                const match = text.match(pattern);
                if (match) {
                    detectedData.address = match[1].trim().substring(0, 100);
                    detectedData.addressConfidence = 60;
                    break;
                }
            }
        }

        function displayResults() {
            detectionStatus.className = 'detection-status active success';
            statusText.textContent = '✓ Scan complete. Review the extracted info below, then Accept & Fill if it looks right.';
            detectionResults.classList.add('active');

            document.getElementById('resultFace').textContent = detectedData.faceDetected ? '✓ Yes' : '✗ No';
            document.getElementById('faceBadge').textContent = detectedData.faceDetected ? 'Yes' : 'No';
            document.getElementById('faceBadge').className = detectedData.faceDetected ? 'confidence-badge confidence-high' : 'confidence-badge confidence-low';

            document.getElementById('resultName').textContent = detectedData.name || 'Not detected';
            document.getElementById('nameBadge').textContent = detectedData.nameConfidence + '%';
            updateConfidenceBadge('nameBadge', detectedData.nameConfidence);

            document.getElementById('resultIDNumber').textContent = detectedData.idNumber || 'Not detected';
            document.getElementById('idnumBadge').textContent = detectedData.idNumberConfidence + '%';
            updateConfidenceBadge('idnumBadge', detectedData.idNumberConfidence);

            document.getElementById('resultAddress').textContent = detectedData.address || 'Not detected';
            document.getElementById('addressBadge').textContent = detectedData.addressConfidence + '%';
            updateConfidenceBadge('addressBadge', detectedData.addressConfidence);
        }

        function updateConfidenceBadge(elementId, confidence) {
            const el = document.getElementById(elementId);
            el.className = 'confidence-badge';
            if (confidence >= 75) {
                el.classList.add('confidence-high');
            } else if (confidence >= 50) {
                el.classList.add('confidence-medium');
            } else {
                el.classList.add('confidence-low');
            }
        }

        function acceptDetectedData() {
            if (detectedData.name) {
                const nameParts = detectedData.name.split(' ');
                document.getElementById('first_name').value = nameParts[0] || '';
                document.getElementById('last_name').value = nameParts.slice(1).join(' ') || '';
            }

            if (detectedData.idNumber) {
                document.getElementById('id_number').value = detectedData.idNumber;
            }

            if (detectedData.address) {
                document.getElementById('address').value = detectedData.address;
            }

            alert('✓ Detected information filled into form. Please verify and complete any remaining fields.');
        }

        function retryDetection() {
            if (idPhotoInput.files[0]) {
                readAsDataURL(idPhotoInput.files[0]).then(dataUrl => detectID(dataUrl));
            }
        }

        // Prevent double-submit while the request is in flight
        document.getElementById('registerForm').addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
        });
    </script>
</body>
</html>
