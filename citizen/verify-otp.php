<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$userId = (int) ($_SESSION['pending_otp_user_id'] ?? 0);
$pendingEmail = (string) ($_SESSION['pending_otp_email'] ?? '');

if ($userId <= 0 || $pendingEmail === '') {
    header('Location: ' . appUrl('/citizen/register.php'));
    exit;
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

$error = '';
$status = isset($_GET['resent']) ? 'We sent a fresh verification code to your email since your account was not yet verified.' : '';
$otp = new OTPManager();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        $result = $otp->createOTP($userId, 'citizen_verification');
        if ($result['success']) {
            $stmt = getDB()->prepare('SELECT full_name FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $fullName = (string) ($stmt->fetchColumn() ?: 'Citizen');

            $sendResult = $otp->sendOTPEmail($pendingEmail, $fullName, $result['otp_code']);
            unset($_SESSION['dev_otp_preview']);
            if (!$sendResult['success'] && !empty($sendResult['dev_fallback'])) {
                $_SESSION['dev_otp_preview'] = $result['otp_code'];
            }
            $status = 'A new code has been sent to ' . maskEmail($pendingEmail) . '.';
        } else {
            $error = 'Could not generate a new code. Please try again.';
        }
    } else {
        $code = trim((string) ($_POST['otp_code'] ?? ''));
        if ($code === '') {
            $error = 'Please enter the code sent to your email.';
        } else {
            $result = $otp->verifyOTP($userId, $code, 'citizen_verification');
            if ($result['success']) {
                getDB()->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$userId]);
                // Registration sets pending_needs_id when no ID photo was submitted,
                // so the login page can keep reminding them after verification.
                $needsId = !empty($_SESSION['pending_needs_id']);
                unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_email'], $_SESSION['dev_otp_preview'], $_SESSION['pending_needs_id']);
                header('Location: ' . appUrl('/citizen/login.php?verified=1' . ($needsId ? '&needs_id=1' : '')));
                exit;
            }
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - IPMS</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#1e3a8a">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';">
    <style>
        /* Citizen-portal design tokens (mirrors citizen/login.php).
           Blue palette; variable names kept from the original green theme:
           --deep = navy, --green = primary blue, --mint = light blue. */
        :root {
            --ink: #0f1c2e;
            --muted: #51617a;
            --deep: #1e3a8a;
            --green: #2563eb;
            --mint: #dbeafe;
            --paper: #f2f7fd;
            --line: #d8e3f2;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

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

        /* Blurred City Hall photo backdrop (fixed, behind everything). */
        body::before {
            content: "";
            position: fixed;
            inset: -24px;
            z-index: -2;
            background: url('<?= htmlspecialchars(appUrl('/assets/img/cityhall.jpeg')) ?>') center / cover no-repeat;
            filter: blur(7px) saturate(1.05);
            transform: scale(1.04);
        }

        /* Soft light-blue wash so the glass card stays readable over the photo. */
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.16), rgba(242, 247, 253, 0.42));
        }

        .auth-card {
            width: min(460px, 100%);
            background: rgba(255, 255, 255, 0.74);
            backdrop-filter: blur(14px) saturate(1.4);
            -webkit-backdrop-filter: blur(14px) saturate(1.4);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        }

        .card-header {
            position: relative;
            isolation: isolate;
            padding: 2.25rem 2rem 2rem;
            background: linear-gradient(150deg, rgba(30, 58, 138, 0.94), rgba(37, 99, 235, 0.88) 65%, rgba(59, 130, 246, 0.84));
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
            color: var(--green);
            font-size: 1.4rem;
            box-shadow: 0 10px 24px rgba(30, 58, 138, 0.35);
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
        .alert-success { background: var(--mint); color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-dev { background: #fff8e1; color: #8a6d00; border: 1px solid #ffe082; font-family: monospace; }

        .form-group { margin-bottom: 1.15rem; }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            color: var(--ink);
            font-size: 0.85rem;
        }

        input[type="text"] {
            width: 100%;
            min-height: 52px;
            padding: 0.7rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 1.4rem;
            letter-spacing: 0.4rem;
            text-align: center;
            font-family: 'Courier New', monospace;
            background: var(--paper);
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
            background: #ffffff;
        }

        button {
            width: 100%;
            min-height: 48px;
            margin-top: 0.4rem;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--green), var(--deep));
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button:hover { transform: translateY(-1px); box-shadow: 0 16px 28px rgba(37, 99, 235, 0.28); }
        button:active { transform: translateY(0); }

        .btn-secondary {
            background: var(--mint);
            color: #1e40af;
            box-shadow: none;
            margin-top: 0.75rem;
        }
        .btn-secondary:hover { transform: none; box-shadow: none; background: #bfdbfe; }

        .login-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            text-align: center;
        }

        .login-footer p { color: var(--muted); font-size: 0.88rem; }

        .login-footer a {
            color: var(--green);
            font-weight: 600;
            text-decoration: none;
        }
        .login-footer a:hover { color: var(--deep); text-decoration: underline; }

        .scope-note {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.55;
        }
        .scope-note strong { color: var(--ink); }

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
<body>
    <div class="auth-card">
        <div class="card-header">
            <div class="header-icon"><i class="fa-solid fa-envelope-circle-check"></i></div>
            <h1>Verify Your Email</h1>
            <p>One more step to activate your citizen account</p>
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
                <input type="hidden" name="action" value="verify">
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
                <button type="submit">Verify & Activate Account</button>
            </form>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn-secondary">Resend Code</button>
            </form>

            <div class="login-footer">
                <p>Wrong email? <a href="<?= htmlspecialchars(appUrl('/citizen/register.php')) ?>">Start over</a></p>
            </div>

            <div class="brand-strip">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>" alt="" aria-hidden="true">
                <span>Quezon City LGU &middot; Infrastructure Project Management System</span>
            </div>
        </div>
    </div>
</body>
</html>
