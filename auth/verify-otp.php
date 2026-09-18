<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

define('PENDING_2FA_TIMEOUT_SECONDS', 600); // 10 minutes
define('RESEND_COOLDOWN_SECONDS', 30);

function clear2faSession(): void
{
    unset(
        $_SESSION['pending_2fa_user_id'],
        $_SESSION['pending_2fa_role'],
        $_SESSION['pending_2fa_email'],
        $_SESSION['pending_2fa_name'],
        $_SESSION['pending_2fa_started_at'],
        $_SESSION['pending_2fa_last_sent_at'],
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

$userId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . appUrl('/auth/login.php?error=' . rawurlencode('Please log in to continue.')));
    exit;
}

$startedAt = (int) ($_SESSION['pending_2fa_started_at'] ?? 0);
if ($startedAt <= 0 || (time() - $startedAt) > PENDING_2FA_TIMEOUT_SECONDS) {
    clear2faSession();
    header('Location: ' . appUrl('/auth/login.php?error=' . rawurlencode('Your verification session expired. Please log in again.')));
    exit;
}

$role = (string) ($_SESSION['pending_2fa_role'] ?? '');
$pendingEmail = (string) ($_SESSION['pending_2fa_email'] ?? '');
$pendingName = (string) ($_SESSION['pending_2fa_name'] ?? '');

$roleLabels = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'bac' => 'BAC'];
$roleThemes = [
    'super_admin' => ['from' => '#1a0d2e', 'to' => '#4c1d95', 'primary' => '#7c3aed', 'secondary' => '#a78bfa'],
    'admin' => ['from' => '#0a0a0a', 'to' => '#1c1c2e', 'primary' => '#6366f1', 'secondary' => '#818cf8'],
    'bac' => ['from' => '#4a0e0e', 'to' => '#7f1d1d', 'primary' => '#ef4444', 'secondary' => '#f87171'],
];
$theme = $roleThemes[$role] ?? ['from' => '#0f393a', 'to' => '#116466', 'primary' => '#116466', 'secondary' => '#2f5fbb'];

$error = '';
$status = '';
$otp = new OTPManager();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'cancel') {
        clear2faSession();
        logActivity($userId, 'otp_cancelled', 'Staff cancelled 2FA challenge (' . $role . ')');
        header('Location: ' . appUrl('/auth/login.php'));
        exit;
    }

    if ($action === 'resend') {
        $wait = RESEND_COOLDOWN_SECONDS - (time() - (int) ($_SESSION['pending_2fa_last_sent_at'] ?? 0));
        if ($wait > 0) {
            $error = "Please wait {$wait}s before requesting another code.";
        } else {
            $result = $otp->createOTP($userId, 'staff_login');
            if ($result['success']) {
                $sendResult = $otp->sendOTPEmail($pendingEmail, $pendingName, $result['otp_code']);
                unset($_SESSION['dev_otp_preview']);
                $_SESSION['pending_2fa_last_sent_at'] = time();
                if ($sendResult['success']) {
                    $status = 'A new code has been sent to ' . maskEmail($pendingEmail) . '.';
                    logActivity($userId, 'otp_challenge_sent', 'Resent 2FA code (' . $role . ')');
                } elseif (!empty($sendResult['dev_fallback'])) {
                    $_SESSION['dev_otp_preview'] = $result['otp_code'];
                    $status = 'A new code has been generated (dev preview shown below).';
                    logActivity($userId, 'otp_challenge_sent', 'Resent 2FA code (dev preview, ' . $role . ')');
                } else {
                    $error = 'Unable to send verification code, contact your administrator.';
                    logActivity($userId, 'otp_challenge_send_failed', $sendResult['message'] ?? '');
                }
            } else {
                $error = 'Could not generate a new code. Please try again.';
            }
        }
    } else {
        $code = trim((string) ($_POST['otp_code'] ?? ''));
        if ($code === '') {
            $error = 'Please enter the code sent to your email.';
        } else {
            $result = $otp->verifyOTP($userId, $code, 'staff_login');
            if ($result['success']) {
                $stmt = getDB()->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $freshUser = $stmt->fetch();

                if (!$freshUser || ($freshUser['status'] ?? '') !== 'active' || !isValidRole((string) $freshUser['role'])) {
                    logActivity($userId, 'otp_verified_but_blocked', 'Account no longer active/valid at OTP completion');
                    clear2faSession();
                    header('Location: ' . appUrl('/auth/login.php?error=' . rawurlencode('Your account is no longer active. Please contact your administrator.')));
                    exit;
                }

                session_regenerate_id(true);
                $_SESSION['auth_user'] = [
                    'user_id' => (int) $freshUser['id'],
                    'username' => $freshUser['username'],
                    'email' => $freshUser['email'],
                    'full_name' => $freshUser['full_name'],
                    'role' => $freshUser['role'],
                ];
                $_SESSION['user_id'] = (int) $freshUser['id'];
                $_SESSION['username'] = $freshUser['username'];
                $_SESSION['email'] = $freshUser['email'];
                $_SESSION['full_name'] = $freshUser['full_name'];
                $_SESSION['role'] = $freshUser['role'];
                $_SESSION['last_activity'] = time();

                clear2faSession();
                logActivity((int) $freshUser['id'], 'otp_verified', '2FA code verified for staff login');
                redirectToRoleDashboard($freshUser['role']);
            } else {
                $error = $result['message'];
                logActivity($userId, 'otp_failed', $result['message']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Identity - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --page: #edf3f4;
            --panel: #ffffff;
            --text: #13201f;
            --muted: #65737b;
            --line: #d8e3e5;
            --primary: <?= htmlspecialchars($theme['primary']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary']) ?>;
            --soft: #f5f8f9;
            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
            --dev-bg: #fff8e1;
            --dev-text: #8a6d00;
        }

        * { box-sizing: border-box; }
        html { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--page);
            display: grid;
            place-items: center;
            padding: 28px;
            color: var(--text);
        }

        .shell {
            width: min(920px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(360px, 420px);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(19, 32, 31, 0.1);
            background: var(--panel);
            box-shadow: 0 22px 70px rgba(20, 35, 38, 0.18);
        }

        .brand-panel {
            padding: 46px;
            background: linear-gradient(145deg, <?= htmlspecialchars($theme['from']) ?>, <?= htmlspecialchars($theme['to']) ?> 70%);
            color: #f7fbfb;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 7px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .brand-panel h1 { margin: 0; font-size: 2.1rem; line-height: 1.15; }
        .brand-panel p { margin: 0; color: rgba(247, 251, 251, 0.8); font-size: 0.96rem; line-height: 1.6; max-width: 420px; }

        .panel {
            padding: 42px 38px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel h2 { margin: 0 0 6px; font-size: 1.6rem; }
        .scope-note { margin: 0 0 18px; color: var(--muted); font-size: 0.92rem; line-height: 1.55; }
        .scope-note strong { color: var(--text); }

        .message { border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 14px; font-size: 0.9rem; }
        .message.error { background: var(--danger-bg); color: var(--danger-text); border: 1px solid #fecaca; }
        .message.info { background: var(--info-bg); color: var(--info-text); border: 1px solid #bfdbfe; }
        .message.dev { background: var(--dev-bg); color: var(--dev-text); border: 1px solid #ffe082; font-family: 'DM Mono', monospace; }

        label { display: block; margin-bottom: 8px; font-size: 0.8rem; font-weight: 700; color: #334155; }
        .field { margin-bottom: 14px; }

        input[type="text"] {
            width: 100%;
            min-height: 54px;
            padding: 0.85rem;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: var(--soft);
            font-size: 1.4rem;
            letter-spacing: 0.4rem;
            text-align: center;
            font-family: 'DM Mono', monospace;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 16%, transparent);
            background: #fff;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 8px;
            min-height: 50px;
            padding: 0.9rem;
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        button:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(0, 0, 0, 0.16); }
        button:active { transform: translateY(0); }

        .btn-secondary {
            background: #f0f0f0;
            color: #334155;
            margin-top: 10px;
        }
        .btn-secondary:hover { box-shadow: none; background: #e2e8f0; }

        .footer-row {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            text-align: center;
            font-size: 0.85rem;
        }
        .footer-row a { color: var(--primary); font-weight: 600; }

        @media (max-width: 820px) {
            .shell { grid-template-columns: 1fr; }
            .brand-panel { padding: 30px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="brand-panel">
            <div class="eyebrow"><?= htmlspecialchars($roleLabels[$role] ?? 'Staff') ?> Portal</div>
            <h1>One more step to confirm it's really you.</h1>
            <p>We've sent a 6-digit verification code to protect this account. This extra check only takes a moment.</p>
        </section>

        <section class="panel">
            <h2>Verify your identity</h2>
            <p class="scope-note">
                Code sent to <strong><?= htmlspecialchars(maskEmail($pendingEmail)) ?></strong>.
                It expires <?= (int) $otp->getValidityMinutes() ?> minute(s) after being sent.
            </p>

            <?php if ($status !== ''): ?>
                <div class="message info"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['dev_otp_preview'])): ?>
                <div class="message dev">
                    DEV MODE (mail not configured): your code is <?= htmlspecialchars((string) $_SESSION['dev_otp_preview']) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="verify">
                <div class="field">
                    <label for="otp_code">Verification code</label>
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
                <button type="submit">Verify &amp; continue</button>
            </form>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn-secondary">Resend code</button>
            </form>

            <div class="footer-row">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" style="background:none;color:var(--muted);font-weight:600;width:auto;padding:0;min-height:0;">Use a different account</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
