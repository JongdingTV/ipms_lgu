<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';
require_once __DIR__ . '/../includes/Validator.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

// Same 10-minute justification as auth/verify-otp.php's PENDING_2FA_TIMEOUT_SECONDS.
define('PENDING_RESET_TIMEOUT_SECONDS', 600);
define('RESEND_COOLDOWN_SECONDS', 30);

function clearResetSession(): void
{
    unset(
        $_SESSION['pending_reset_user_id'],
        $_SESSION['pending_reset_email'],
        $_SESSION['pending_reset_started_at'],
        $_SESSION['pending_reset_last_sent_at'],
        $_SESSION['pending_reset_fake_attempts'],
        $_SESSION['pending_reset_origin'],
        $_SESSION['dev_otp_preview']
    );
}

function maskEmail(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($name === '') {
        return $email;
    }
    $visible = mb_substr($name, 0, min(2, mb_strlen($name)));
    return $visible . str_repeat('*', max(1, mb_strlen($name) - mb_strlen($visible))) . '@' . $domain;
}

if (!isset($_SESSION['pending_reset_started_at'])) {
    header('Location: ' . appUrl('/auth/forgot-password.php'));
    exit;
}

$origin = ($_SESSION['pending_reset_origin'] ?? '') === 'citizen' ? 'citizen' : 'staff';

$startedAt = (int) ($_SESSION['pending_reset_started_at'] ?? 0);
if ((time() - $startedAt) > PENDING_RESET_TIMEOUT_SECONDS) {
    clearResetSession();
    header('Location: ' . appUrl('/auth/forgot-password.php?from=' . $origin . '&error=expired'));
    exit;
}

$userId = isset($_SESSION['pending_reset_user_id']) ? (int) $_SESSION['pending_reset_user_id'] : null;
$pendingEmail = (string) ($_SESSION['pending_reset_email'] ?? '');

$error = '';
$status = '';
$otp = new OTPManager();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();
    $action = $_POST['action'] ?? 'reset';

    if ($action === 'resend') {
        $wait = RESEND_COOLDOWN_SECONDS - (time() - (int) ($_SESSION['pending_reset_last_sent_at'] ?? 0));
        if ($wait > 0) {
            $error = "Please wait {$wait}s before requesting another code.";
        } else {
            $_SESSION['pending_reset_last_sent_at'] = time();

            if ($userId !== null) {
                $result = $otp->createOTP($userId, 'password_reset');
                if ($result['success']) {
                    $stmt = getDB()->prepare('SELECT full_name FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    $fullName = (string) ($stmt->fetchColumn() ?: 'there');

                    $sendResult = $otp->sendOTPEmail($pendingEmail, $fullName, $result['otp_code']);
                    unset($_SESSION['dev_otp_preview']);
                    if ($sendResult['success']) {
                        $status = 'A new code has been sent to ' . maskEmail($pendingEmail) . '.';
                        logActivity($userId, 'password_reset_otp_resent', 'Password reset OTP resent');
                    } elseif (!empty($sendResult['dev_fallback'])) {
                        $_SESSION['dev_otp_preview'] = $result['otp_code'];
                        $status = 'A new code has been sent to ' . maskEmail($pendingEmail) . '.';
                    } else {
                        $error = 'Unable to send a new code. Please try again later.';
                    }
                } else {
                    $error = 'Could not generate a new code. Please try again.';
                }
            } else {
                // Fake path — no DB/mail touch, identical success shape to the real path.
                $_SESSION['pending_reset_fake_attempts'] = 0;
                $status = 'A new code has been sent to ' . maskEmail($pendingEmail) . '.';
            }
        }
    } else {
        $code = trim((string) ($_POST['otp_code'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($code === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please fill in all fields.';
        } elseif (($strengthError = Validator::passwordStrength($newPassword)) !== null) {
            $error = $strengthError;
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif ($userId !== null) {
            $result = $otp->verifyOTP($userId, $code, 'password_reset');
            if ($result['success']) {
                $stmt = getDB()->prepare('SELECT id, role, status FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $freshUser = $stmt->fetch();

                if (!$freshUser || ($freshUser['status'] ?? '') !== 'active') {
                    clearResetSession();
                    header('Location: ' . appUrl('/auth/forgot-password.php?from=' . $origin . '&error=' . rawurlencode('Your account is no longer active. Please contact your administrator.')));
                    exit;
                }

                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);
                logActivity($userId, 'password_reset_completed', 'Password reset via forgot-password flow');
                clearResetSession();

                $loginPath = $freshUser['role'] === 'citizen' ? '/citizen/login.php' : '/auth/login.php';
                header('Location: ' . appUrl($loginPath . '?reset=1'));
                exit;
            }
            $error = $result['message'];
        } else {
            // Fake path — mirrors the real path's rejection shape exactly, never
            // touches OTPManager or the database.
            $attempts = (int) ($_SESSION['pending_reset_fake_attempts'] ?? 0);
            $remaining = max(0, 2 - $attempts);
            $_SESSION['pending_reset_fake_attempts'] = $attempts + 1;
            $error = $remaining > 0
                ? "Incorrect code. {$remaining} attempt(s) remaining."
                : 'Too many incorrect attempts. Please request a new code.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="<?= $origin === 'citizen' ? '#1e3a8a' : '#1e40af' ?>">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        /* Staff palette is the default; body.theme-citizen swaps in the citizen
           portal's green look (mirrors citizen/login.php's tokens). */
        body {
            --accent: #2563eb;
            --accent-deep: #1e40af;
            --focus-ring: rgba(37, 99, 235, 0.14);
            --ink: #1c2430;
            --muted: #5b6572;
            --paper: #eef3fb;
            --line: #dbe3ef;
            --soft: #e3ecfb;
            --soft-ink: #1e40af;
            --btn-shadow: rgba(37, 99, 235, 0.22);

            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        body.theme-citizen {
            --accent: #2563eb;
            --accent-deep: #1e3a8a;
            --focus-ring: rgba(37, 99, 235, 0.16);
            --ink: #0f1c2e;
            --muted: #51617a;
            --paper: #f2f7fd;
            --line: #d8e3f2;
            --soft: #dbeafe;
            --soft-ink: #1e40af;
            --btn-shadow: rgba(37, 99, 235, 0.24);
        }

        /* Citizen flow: blurred City Hall photo backdrop + glass card,
           matching citizen/login.php. Staff flow keeps the flat background. */
        body.theme-citizen::before {
            content: "";
            position: fixed;
            inset: -24px;
            z-index: -2;
            background: url('<?= htmlspecialchars(appUrl('/assets/img/cityhall.jpeg')) ?>') center / cover no-repeat;
            filter: blur(7px) saturate(1.05);
            transform: scale(1.04);
        }

        body.theme-citizen::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.16), rgba(242, 247, 253, 0.42));
        }

        body.theme-citizen .auth-card {
            background: rgba(255, 255, 255, 0.74);
            backdrop-filter: blur(14px) saturate(1.4);
            -webkit-backdrop-filter: blur(14px) saturate(1.4);
            border-color: rgba(255, 255, 255, 0.55);
        }

        .auth-card {
            width: min(460px, 100%);
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(16, 32, 29, 0.14);
        }

        .card-header {
            position: relative;
            isolation: isolate;
            padding: 2.25rem 2rem 2rem;
            background: linear-gradient(150deg, var(--accent-deep), var(--accent));
            color: #ffffff;
            text-align: center;
        }

        .card-header::after {
            content: "";
            position: absolute;
            inset: auto -50px -70px auto;
            width: 190px;
            height: 190px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 50%;
            z-index: 0;
        }

        .header-icon {
            position: relative;
            z-index: 1;
            width: 58px;
            height: 58px;
            margin: 0 auto 0.9rem;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            color: var(--accent);
            font-size: 1.4rem;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
        }

        .card-header h1 {
            position: relative;
            z-index: 1;
            font-size: 1.45rem;
            margin-bottom: 0.3rem;
        }

        .card-header p {
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
        }

        .card-body { padding: 2rem; }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            line-height: 1.4;
        }
        .alert-error { background: #fdecea; color: #b3261e; border: 1px solid #f6cac6; }
        .alert-success { background: var(--soft); color: var(--soft-ink); border: 1px solid var(--line); }
        .alert-dev { background: #fff8e1; color: #8a6d00; border: 1px solid #ffe082; font-family: monospace; }

        .form-group { margin-bottom: 1.15rem; }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            color: var(--ink);
            font-size: 0.85rem;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            min-height: 46px;
            padding: 0.7rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: var(--paper);
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        #otp_code {
            font-size: 1.4rem;
            letter-spacing: 0.4rem;
            text-align: center;
            font-family: 'Courier New', monospace;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--focus-ring);
            background: #ffffff;
        }

        button {
            width: 100%;
            min-height: 48px;
            margin-top: 0.4rem;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-deep));
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 12px 24px var(--btn-shadow);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button:hover { transform: translateY(-1px); box-shadow: 0 16px 28px var(--btn-shadow); }
        button:active { transform: translateY(0); }

        .btn-secondary {
            background: var(--soft);
            color: var(--soft-ink);
            box-shadow: none;
            margin-top: 0.75rem;
        }
        .btn-secondary:hover { transform: none; box-shadow: none; filter: brightness(0.97); }

        .login-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            text-align: center;
        }

        .login-footer p { color: var(--muted); font-size: 0.88rem; }

        .login-footer a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .login-footer a:hover { color: var(--accent-deep); text-decoration: underline; }

        .scope-note {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.55;
        }
        .scope-note strong { color: var(--ink); }

        .password-requirements {
            background: var(--paper);
            border: 1px solid var(--line);
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
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
            color: var(--muted);
        }
        .password-requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: 700;
        }
        .password-requirements li.unmet:before {
            content: "✗";
            color: #e74c3c;
        }

        .brand-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 1.4rem;
            color: var(--muted);
            font-size: 0.78rem;
        }

        .brand-strip img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }

        @media (max-width: 420px) {
            body { padding: 0; }
            .auth-card { border-radius: 0; min-height: 100vh; }
        }
    </style>
</head>
<body class="<?= $origin === 'citizen' ? 'theme-citizen' : 'theme-staff' ?>">
    <div class="auth-card">
        <div class="card-header">
            <div class="header-icon"><i class="fa-solid fa-key"></i></div>
            <h1>Reset Password</h1>
            <p>Enter the code we sent you and choose a new password</p>
        </div>

        <div class="card-body">
            <p class="scope-note">
                We sent a 6-digit code to <strong><?= htmlspecialchars(maskEmail($pendingEmail)) ?></strong>.
                It expires <?= (int) $otp->getValidityMinutes() ?> minute(s) after being sent.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($status): ?>
                <div class="alert alert-success"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['dev_otp_preview'])): ?>
                <div class="alert alert-dev">
                    DEV MODE (mail not configured): your code is <?= htmlspecialchars((string) $_SESSION['dev_otp_preview']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="reset">
                <div class="form-group">
                    <label for="otp_code">Verification Code</label>
                    <input
                        type="text"
                        id="otp_code"
                        name="otp_code"
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="new_password" required>
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
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit">Reset Password</button>
            </form>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn-secondary">Resend Code</button>
            </form>

            <div class="login-footer">
                <p>Wrong email? <a href="<?= htmlspecialchars(appUrl('/auth/forgot-password.php?from=' . $origin)) ?>">Start over</a></p>
            </div>

            <div class="brand-strip">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>" alt="" aria-hidden="true">
                <span>Quezon City LGU &middot; Infrastructure Project Management System</span>
            </div>
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
        validatePassword();
    </script>
</body>
</html>
