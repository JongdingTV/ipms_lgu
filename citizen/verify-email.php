<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

const RESEND_COOLDOWN_SECONDS = 30;
const MAX_OTP_REQUESTS_PER_HOUR = 5;

function attemptResend(OTPManager $otpManager, array $user): array
{
    $latestToken = $otpManager->getLatestToken((int) $user['id']);
    if ($latestToken) {
        $wait = RESEND_COOLDOWN_SECONDS - (time() - strtotime($latestToken['created_at']));
        if ($wait > 0) {
            return ['status' => '', 'error' => "Please wait {$wait} more second(s) before requesting a new code."];
        }
    }

    if ($otpManager->countRecentRequests((int) $user['id']) >= MAX_OTP_REQUESTS_PER_HOUR) {
        return ['status' => '', 'error' => 'Too many code requests. Please try again in an hour.'];
    }

    $otpResult = $otpManager->createOTP((int) $user['id']);
    if (!$otpResult['success']) {
        return ['status' => '', 'error' => 'Could not generate a new code. Please try again.'];
    }

    unset($_SESSION['dev_otp_code']);
    $emailResult = $otpManager->sendOTPEmail($user['email'], $user['full_name'], $otpResult['otp_code']);
    if (!$emailResult['success']) {
        if (APP_ENV !== 'production') {
            // Local/dev fallback so the flow is testable without real SMTP credentials.
            $_SESSION['dev_otp_code'] = $otpResult['otp_code'];
            return ['status' => 'A new code was generated.', 'error' => ''];
        }
        return ['status' => '', 'error' => 'Could not send the email right now. Please try again shortly.'];
    }

    return ['status' => 'A new verification code was sent to your email.', 'error' => ''];
}

$pendingUserId = $_SESSION['pending_verification_user_id'] ?? null;
if (!$pendingUserId) {
    header('Location: ' . appUrl('/citizen/login.php'));
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, username, email, full_name, role, email_verified_at FROM users WHERE id = ? AND role = 'citizen'");
$stmt->execute([(int) $pendingUserId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email'], $_SESSION['dev_otp_code']);
    header('Location: ' . appUrl('/citizen/login.php'));
    exit;
}

if (!empty($user['email_verified_at'])) {
    // Already verified (stale session, double submit, etc.) — just sign them in.
    establishUserSession($user);
    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email'], $_SESSION['dev_otp_code']);
    redirectToRoleDashboard($user['role']);
}

$otpManager = new OTPManager();
$status = '';
$error = '';
$latestToken = $otpManager->getLatestToken((int) $user['id']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();

    if (($_POST['action'] ?? '') === 'resend') {
        $result = attemptResend($otpManager, $user);
        $status = $result['status'];
        $error = $result['error'];
    } else {
        $code = trim($_POST['otp_code'] ?? '');
        if ($code === '') {
            $error = 'Please enter the 6-digit code.';
        } else {
            $result = $otpManager->verifyOTP((int) $user['id'], $code);
            if ($result['success']) {
                $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([(int) $user['id']]);
                establishUserSession($user);
                logActivity((int) $user['id'], 'email_verified', 'Citizen verified email via OTP');
                unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email'], $_SESSION['dev_otp_code']);
                redirectToRoleDashboard($user['role']);
            }
            $error = $result['message'];
        }
    }

    $latestToken = $otpManager->getLatestToken((int) $user['id']);
} elseif (isset($_GET['resend']) || !$latestToken) {
    // Auto-send: either explicitly requested (post-login gate) or there's no code yet at all
    // (e.g. the original send failed transiently during registration).
    $result = attemptResend($otpManager, $user);
    $status = $result['status'];
    $error = $result['error'];
    $latestToken = $otpManager->getLatestToken((int) $user['id']);
}

$resendWaitSeconds = 0;
if ($latestToken) {
    $resendWaitSeconds = max(0, RESEND_COOLDOWN_SECONDS - (time() - strtotime($latestToken['created_at'])));
}

$devOtpCode = $_SESSION['dev_otp_code'] ?? null;
$maskedEmail = $user['email'];
if (($atPos = strpos($maskedEmail, '@')) !== false) {
    $localPart = substr($maskedEmail, 0, $atPos);
    $domainPart = substr($maskedEmail, $atPos);
    $visible = mb_substr($localPart, 0, min(2, mb_strlen($localPart)));
    $maskedEmail = $visible . str_repeat('*', max(1, mb_strlen($localPart) - mb_strlen($visible))) . $domainPart;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - IPMS</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#063b33">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
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
            display: grid;
            place-items: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .verify-card {
            width: 100%;
            max-width: 460px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            overflow: hidden;
        }

        .verify-header {
            background: linear-gradient(150deg, var(--deep), var(--green) 65%, #128a6c);
            color: var(--white);
            padding: 2.25rem 2rem;
            text-align: center;
        }

        .verify-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            margin: 0 auto 0.85rem;
            display: block;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 12px;
            padding: 8px;
        }

        .verify-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
        }

        .verify-header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
        }

        .verify-body {
            padding: 2rem;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            line-height: 1.45;
        }

        .alert-error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f6cac6;
        }

        .alert-success {
            background: var(--mint);
            color: #0b5c46;
            border: 1px solid #b7e6d3;
        }

        .alert-dev {
            background: #fff9c4;
            color: #594000;
            border: 1px solid #f6b83f;
            font-family: 'Courier New', monospace;
        }

        .otp-input {
            width: 100%;
            min-height: 56px;
            padding: 0.7rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.5rem;
            text-align: center;
            background: #fbfaf5;
            color: var(--ink);
            font-family: 'Courier New', monospace;
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(15, 122, 95, 0.14);
            background: var(--white);
        }

        .field-hint {
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: var(--muted);
            text-align: center;
        }

        button[type="submit"] {
            width: 100%;
            min-height: 48px;
            margin-top: 1.25rem;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--green), var(--deep));
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(15, 122, 95, 0.22);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(15, 122, 95, 0.26);
        }

        .resend-row {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            text-align: center;
        }

        .resend-row p {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 0.6rem;
        }

        .btn-resend {
            background: none;
            border: 1px solid var(--green);
            color: var(--green);
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-resend:disabled {
            border-color: var(--line);
            color: var(--muted);
            cursor: not-allowed;
        }

        .back-link {
            text-align: center;
            margin-top: 1.25rem;
        }

        .back-link a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .back-link a:hover {
            color: var(--green);
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="verify-header">
            <img class="verify-logo" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="IPMS logo">
            <h1>Verify your email</h1>
            <p>We sent a 6-digit code to <strong><?= htmlspecialchars($maskedEmail) ?></strong></p>
        </div>

        <div class="verify-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($status): ?>
                <div class="alert alert-success"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>

            <?php if ($devOtpCode !== null): ?>
                <div class="alert alert-dev">
                    <strong>DEV MODE:</strong> email delivery isn't configured on this server yet (no MAIL_PASSWORD in .env), so here's the code directly: <strong><?= htmlspecialchars($devOtpCode) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input
                    type="text"
                    name="otp_code"
                    class="otp-input"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="------"
                    required
                    autofocus
                >
                <p class="field-hint">Code expires <?= (int) $otpManager->getValidityMinutes() ?> minutes after it's sent.</p>
                <button type="submit">Verify & Continue</button>
            </form>

            <div class="resend-row">
                <p>Didn't get it? Check your spam folder, or:</p>
                <form method="POST" action="" id="resendForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn-resend" id="resendBtn" <?= $resendWaitSeconds > 0 ? 'disabled' : '' ?>>
                        <span id="resendBtnText"><?= $resendWaitSeconds > 0 ? "Resend in {$resendWaitSeconds}s" : 'Resend code' ?></span>
                    </button>
                </form>
            </div>

            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>">Cancel and back to login</a>
            </div>
        </div>
    </div>

    <script>
        let remaining = <?= (int) $resendWaitSeconds ?>;
        const btn = document.getElementById('resendBtn');
        const btnText = document.getElementById('resendBtnText');

        if (remaining > 0) {
            const timer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btnText.textContent = 'Resend code';
                } else {
                    btnText.textContent = `Resend in ${remaining}s`;
                }
            }, 1000);
        }
    </script>
</body>
</html>
