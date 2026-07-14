<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$errors = [];
$formData = [];

// Get form data on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require valid CSRF token for registration
    requireCsrfProtection();
    $formData = $_POST;
    
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
    if (empty($firstName)) $errors[] = '    ';
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

    // Check ID photo upload
    $idPhotoExt = null;
    if (empty($_FILES['id_photo']['name'] ?? '')) {
        $errors[] = 'ID photo is required for verification';
    } else {
        $file = $_FILES['id_photo'];
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'ID photo must be less than 5MB';
        }

        // Validate the actual file content, not the client-supplied
        // Content-Type header or filename extension (both attacker-controlled).
        $imageInfo = is_uploaded_file($file['tmp_name'] ?? '') ? @getimagesize($file['tmp_name']) : false;
        $extensionByImageType = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
        ];
        if ($imageInfo === false || !isset($extensionByImageType[$imageInfo[2]])) {
            $errors[] = 'ID photo must be a valid JPG, PNG, or GIF image';
        } else {
            $idPhotoExt = $extensionByImageType[$imageInfo[2]];
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
                // Create transaction
                $pdo->beginTransaction();

                // Upload ID photo
                $uploadDir = __DIR__ . '/../assets/img/citizen-ids/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = 'citizen_id_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $idPhotoExt;
                $filePath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($_FILES['id_photo']['tmp_name'], $filePath)) {
                    throw new Exception('Failed to upload ID photo');
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

                // Create citizen profile
                $idPhotoPath = '/assets/img/citizen-ids/' . $fileName;
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
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: blob:; script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self' blob:;">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial;
            background: linear-gradient(135deg, #e6f0ff 0%, #eef7ff 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .registration-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }

        .reg-header {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .reg-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .reg-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
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
            font-size: 0.95rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-info {
            background: #eef;
            color: #33c;
            border: 1px solid #ccf;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2563eb;
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
            color: #333;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
        }

        .required {
            color: #e74c3c;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="file"],
        select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="date"]:focus,
        input[type="file"]:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        .file-upload {
            border: 2px dashed #2563eb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
        }

        .file-upload:hover {
            background: #f0f7ff;
            border-color: #1e40af;
        }

        .file-upload input {
            display: none;
        }

        .file-upload p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #666;
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
            color: #27ae60;
        }

        .password-requirements li.unmet:before {
            content: "✗";
            color: #e74c3c;
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
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37,99,235,0.25);
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .back-link {
            margin-bottom: 1.5rem;
        }

        .back-link a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #1e40af;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                grid-template-columns: 1fr;
            }
        }

        /* AI Detection Styles */
        .ai-detection-container {
            background: #f8f9fa;
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
            border-radius: 6px;
            border: 2px solid #2563eb;
        }

        .detection-status {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 6px;
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
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .detection-status.warning {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #e65100;
        }

        .detection-status.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #c62828;
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
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .result-label {
            font-weight: 600;
            color: #333;
            flex: 0 0 150px;
        }

        .result-value {
            flex: 1;
            color: #666;
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
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-accept {
            background: #4caf50;
            color: white;
        }

        .btn-accept:hover {
            background: #45a049;
        }

        .btn-retry {
            background: #ff9800;
            color: white;
        }

        .btn-retry:hover {
            background: #e68900;
        }

        .verification-notice {
            background: #fff9c4;
            border-left: 4px solid #fbc02d;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #594000;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="reg-header">
            <h1>Create Citizen Account</h1>
            <p>Strict identity verification required</p>
        </div>

        <div class="reg-body">
            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>"><i class="fa fa-arrow-left"></i> Back to Login</a>
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
                    <strong>Note:</strong> Identity verification is required to prevent fraudulent accounts and ensure we serve only genuine citizens.
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" autocomplete="on">
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
                    <h3 class="section-title">Identification <span style="color: #e74c3c; font-size: 0.9rem;">(AI-Powered Verification)</span></h3>
                    
                    <div class="verification-notice">
                        <strong>🤖 AI-Powered Verification:</strong> Upload your ID photo. Our AI will detect your face and extract information automatically.
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
                        <label for="id_photo">ID Photo/Document <span class="required">*</span></label>
                        <label for="id_photo" class="file-upload" id="uploadLabel">
                            <input type="file" id="id_photo" name="id_photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <p>📷 Click to upload your ID photo</p>
                            <p style="font-size: 0.8rem; color: #999; margin-top: 0.5rem;">JPG, PNG, or GIF (Max 5MB)</p>
                        </label>
                        
                        <!-- AI Detection Container -->
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
                        <small style="color: #666; margin-top: 0.3rem; display: block;">5+ characters, letters, numbers, hyphens</small>
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
                    <button type="submit" class="btn-submit">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        // ========== AI DETECTION =========
        let detectedData = null;
        const idPhotoInput = document.getElementById('id_photo');
        const previewSection = document.getElementById('previewSection');
        const preview = document.getElementById('id_preview');
        const detectionStatus = document.getElementById('detectionStatus');
        const statusText = document.getElementById('statusText');
        const detectionResults = document.getElementById('detectionResults');

        // Load AI libraries
        const loadAILibraries = async () => {
            // Load face-api
            if (!window.faceapi) {
                const script1 = document.createElement('script');
                script1.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
                document.head.appendChild(script1);
                await new Promise(r => script1.onload = r);
            }

            // Load Tesseract OCR
            if (!window.Tesseract) {
                const script2 = document.createElement('script');
                script2.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js';
                document.head.appendChild(script2);
                await new Promise(r => script2.onload = r);
            }
        };

        // Handle file selection
        idPhotoInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Show preview
            const reader = new FileReader();
            reader.onload = async (event) => {
                preview.src = event.target.result;
                previewSection.classList.add('active');
                
                // Start detection
                await detectID(event.target.result);
            };
            reader.readAsDataURL(file);
        });

        // Main detection function
        async function detectID(imageSrc) {
            try {
                // Load libraries if not already loaded
                await loadAILibraries();

                // Show processing status
                detectionStatus.classList.add('active', 'processing');
                statusText.textContent = '🔍 Analyzing ID document...';
                detectionResults.classList.remove('active');
                detectedData = null;

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

                // Step 1: Face Detection
                await detectFace(imageSrc);

                // Step 2: OCR Text Extraction
                await extractText(imageSrc);

                // Show results
                displayResults();

            } catch (error) {
                console.error('Detection error:', error);
                detectionStatus.classList.add('active', 'error');
                statusText.textContent = '❌ Error processing ID: ' + error.message;
            }
        }

        // Detect face in image
        async function detectFace(imageSrc) {
            try {
                const img = new Image();
                img.src = imageSrc;
                
                await new Promise(r => {
                    img.onload = r;
                });

                // Load models
                const modelsUrl = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
                await faceapi.nets.tinyFaceDetector.load(modelsUrl);
                await faceapi.nets.faceLandmark68Net.load(modelsUrl);

                // Detect faces
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
                // Continue with OCR even if face detection fails
            }
        }

        // Extract text from image using OCR
        async function extractText(imageSrc) {
            try {
                statusText.textContent = '📝 Extracting text from ID...';

                const result = await Tesseract.recognize(imageSrc, 'eng', {
                    logger: m => console.log('OCR progress:', m.progress)
                });

                const text = result.data.text;
                console.log('OCR Text:', text);

                // Pattern matching for Philippines ID formats
                extractIDInfo(text);

            } catch (error) {
                console.error('OCR error:', error);
                detectedData.name = 'N/A';
                detectedData.idNumber = 'N/A';
                detectedData.address = 'N/A';
            }
        }

        // Extract specific info from OCR text
        function extractIDInfo(text) {
            const lines = text.split('\n');
            
            // Name extraction (usually 2-3 words uppercase)
            const nameMatch = text.match(/^([A-Z\s]+?)(?:\n|$)/m);
            if (nameMatch) {
                detectedData.name = nameMatch[1].trim();
                detectedData.nameConfidence = 75;
            }

            // ID Number - look for digits separated by space/dash
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

            // Address - look for keywords like "Address:", "Purok", "Barangay", etc.
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

        // Display detection results
        function displayResults() {
            detectionStatus.classList.remove('processing', 'error');
            statusText.textContent = '✓ Detection complete! Please review the extracted information below.';
            detectionResults.classList.add('active');

            // Update result displays
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

        // Update confidence badge color
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

        // Accept and fill detected data
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

        // Retry detection
        function retryDetection() {
            if (idPhotoInput.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    detectID(event.target.result);
                };
                reader.readAsDataURL(idPhotoInput.files[0]);
            }
        }
    </script>
</body>
</html>
