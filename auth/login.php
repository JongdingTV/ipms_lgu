<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/Settings.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$error = '';
$status = '';
$selectedRole = trim((string) ($_POST['portal_role'] ?? ''));
$portalRoles = [
    'super_admin' => [
        'label' => 'Super Admin',
        'description' => 'Platform governance',
    ],
    'admin' => [
        'label' => 'Admin',
        'description' => 'Project office',
    ],
    'bac' => [
        'label' => 'BAC',
        'description' => 'Bids and awards',
    ],
    'engineer' => [
        'label' => 'Engineer',
        'description' => 'Field team',
    ],
    'contractor' => [
        'label' => 'Contractor',
        'description' => 'Delivery partner',
    ],
];

if (isset($_GET['timeout'])) {
    $status = 'Your session expired after 30 minutes of inactivity. Please sign in again.';
} elseif (isset($_GET['reset'])) {
    $status = 'Password reset! Please log in with your new password.';
} elseif (isset($_GET['error'])) {
    $status = trim((string) $_GET['error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($selectedRole === '' || !array_key_exists($selectedRole, $portalRoles)) {
        $error = 'Please choose the portal you are logging in to.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $result = authenticateUser($identifier, $password, $selectedRole);

        if ($result['success']) {
            $authedUser = $result['user'];
            $staff2faRoles = ['super_admin', 'admin', 'bac'];

            if (in_array($authedUser['role'], $staff2faRoles, true) && getSetting('require_staff_2fa', false)) {
                // authenticateUser() already fully established the session (auth_user +
                // mirror keys). Undo that here so isLoggedIn() is false again until the
                // OTP step passes — mirrors the existing pending_otp_user_id pattern
                // citizen registration already uses (see citizen/verify-otp.php).
                unset(
                    $_SESSION['auth_user'],
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    $_SESSION['email'],
                    $_SESSION['full_name'],
                    $_SESSION['role']
                );
                unset($_SESSION['dev_otp_preview']);

                $_SESSION['pending_2fa_user_id'] = $authedUser['user_id'];
                $_SESSION['pending_2fa_role'] = $authedUser['role'];
                $_SESSION['pending_2fa_email'] = $authedUser['email'];
                $_SESSION['pending_2fa_name'] = $authedUser['full_name'];
                $_SESSION['pending_2fa_started_at'] = time();
                $_SESSION['pending_2fa_last_sent_at'] = time();

                $otp = new OTPManager();
                $otpResult = $otp->createOTP($authedUser['user_id'], 'staff_login');
                $sendOk = false;

                if ($otpResult['success']) {
                    $sendResult = $otp->sendOTPEmail($authedUser['email'], $authedUser['full_name'], $otpResult['otp_code']);
                    if ($sendResult['success']) {
                        $sendOk = true;
                        logActivity($authedUser['user_id'], 'otp_challenge_sent', 'Staff 2FA code sent (' . $authedUser['role'] . ')');
                    } elseif (!empty($sendResult['dev_fallback'])) {
                        $sendOk = true;
                        $_SESSION['dev_otp_preview'] = $otpResult['otp_code'];
                        logActivity($authedUser['user_id'], 'otp_challenge_sent', 'Staff 2FA code generated (dev preview, ' . $authedUser['role'] . ')');
                    } else {
                        logActivity($authedUser['user_id'], 'otp_challenge_send_failed', $sendResult['message'] ?? '');
                    }
                } else {
                    logActivity($authedUser['user_id'], 'otp_challenge_send_failed', $otpResult['message'] ?? 'Unable to generate OTP');
                }

                if ($sendOk) {
                    header('Location: ' . appUrl('/auth/verify-otp.php'));
                    exit;
                }

                // Fail-closed: never silently grant access when 2FA can't be delivered.
                unset(
                    $_SESSION['pending_2fa_user_id'],
                    $_SESSION['pending_2fa_role'],
                    $_SESSION['pending_2fa_email'],
                    $_SESSION['pending_2fa_name'],
                    $_SESSION['pending_2fa_started_at'],
                    $_SESSION['pending_2fa_last_sent_at']
                );
                $error = 'Unable to send verification code, contact your administrator.';
            } else {
                redirectToRoleDashboard($authedUser['role']);
            }
        } else {
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
    <title>Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --page: #edf3f4;
            --panel: #ffffff;
            --text: #13201f;
            --muted: #65737b;
            --line: #d8e3e5;
            --primary: #116466;
            --primary-dark: #0f393a;
            --secondary: #2f5fbb;
            --accent: #d89c27;
            --soft: #f5f8f9;
            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
        }

        * { box-sizing: border-box; }

        html { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #edf3f4;
            display: grid;
            place-items: center;
            padding: 28px;
            color: var(--text);
            transition: background 0.55s ease;
        }

        .login-shell {
            width: min(1040px, 100%);
            min-height: 640px;
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(380px, 440px);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(19, 32, 31, 0.1);
            background: var(--panel);
            box-shadow: 0 22px 70px rgba(20, 35, 38, 0.18);
        }

        /* ── Brand panel ── */
        .brand-panel {
            position: relative;
            isolation: isolate;
            padding: 46px;
            background: linear-gradient(145deg, #0f393a, #116466 56%, rgba(47, 95, 187, 0.92));
            color: #f7fbfb;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 44px;
            transition: background 0.55s ease;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            inset: auto 34px 28px auto;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            opacity: 0.45;
            z-index: -1;
        }

        .brand-logo-wrap {
            width: 198px;
            min-height: 198px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 18px;
        }

        .brand-logo {
            display: block;
            width: 100%;
            height: auto;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 14px;
            padding: 7px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0;
            transition: background 0.3s ease;
        }

        h1 {
            max-width: 560px;
            margin: 0 0 18px;
            font-size: 3rem;
            line-height: 1.05;
            letter-spacing: 0;
        }

        .hero-copy p {
            max-width: 520px;
            margin: 0;
            color: rgba(247, 251, 251, 0.78);
            font-size: 1rem;
            line-height: 1.65;
        }

        .brand-footer {
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: 420px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.16);
        }

        .city-seal {
            width: 54px;
            height: 54px;
            object-fit: contain;
            flex: 0 0 auto;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.92);
            padding: 5px;
        }

        .brand-footer strong,
        .brand-footer span { display: block; }

        .brand-footer span {
            margin-top: 3px;
            color: rgba(247, 251, 251, 0.72);
            font-size: 0.88rem;
            line-height: 1.45;
        }

        /* ── Login panel ── */
        .login-panel {
            background: var(--panel);
            color: var(--text);
            padding: 42px 38px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 26px;
        }

        .panel-icon {
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .login-panel h2 {
            margin: 0 0 5px;
            font-size: 1.85rem;
            letter-spacing: 0;
        }

        .panel-copy {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
            font-size: 0.94rem;
        }

        /* ── Messages ── */
        .message {
            border-radius: 8px;
            padding: 0.95rem 1rem;
            margin-bottom: 16px;
            font-size: 0.92rem;
        }

        .message.error {
            background: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid #fecaca;
        }

        .message.info {
            background: var(--info-bg);
            color: var(--info-text);
            border: 1px solid #bfdbfe;
        }

        /* ── Form ── */
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0;
            color: #334155;
        }

        .field { margin-bottom: 18px; }

        input {
            width: 100%;
            min-height: 48px;
            padding: 0.85rem 0.95rem;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: var(--soft);
            font: inherit;
            color: var(--text);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(17, 100, 102, 0.14);
            background: #fff;
        }

        /* ── Portal tiles ── */
        .portal-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .portal-option {
            position: relative;
            display: block;
            margin-bottom: 0;
            cursor: pointer;
            color: inherit;
            font: inherit;
            letter-spacing: 0;
            text-transform: none;
        }

        .portal-option input[type="radio"] {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: 0;
            padding: 0;
            border: 0;
            opacity: 0;
            pointer-events: none;
        }

        .portal-option-content {
            display: flex;
            min-height: 64px;
            flex-direction: column;
            justify-content: center;
            gap: 3px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .portal-option-content strong {
            color: var(--text);
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .portal-option-content span {
            color: var(--muted);
            font-size: 0.76rem;
            line-height: 1.35;
        }

        .portal-option:hover .portal-option-content {
            border-color: rgba(17, 100, 102, 0.45);
            transform: translateY(-1px);
        }

        .portal-option input[type="radio"]:focus-visible + .portal-option-content {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(17, 100, 102, 0.14);
        }

        .portal-option input[type="radio"]:checked + .portal-option-content {
            border-color: var(--primary);
            background: #edf9f7;
            box-shadow: inset 4px 0 0 var(--accent);
        }

        /* ── Submit button ── */
        button[type="submit"] {
            width: 100%;
            border: 0;
            border-radius: 8px;
            min-height: 50px;
            padding: 0.95rem 1.1rem;
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 14px 28px rgba(17, 100, 102, 0.22);
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.45s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(17, 100, 102, 0.24);
        }

        .footer {
            margin-top: 18px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* ── Responsive ── */
        @media (max-width: 920px) {
            body { padding: 18px; }
            .login-shell {
                grid-template-columns: 1fr;
                min-height: 0;
            }
            .brand-panel { padding: 32px; }
            .brand-logo-wrap { width: 156px; min-height: 156px; }
            h1 { font-size: 2.4rem; }
        }

        @media (max-width: 520px) {
            body { padding: 12px; }
            .brand-panel,
            .login-panel { padding: 24px 20px; }
            .brand-logo-wrap { width: 132px; min-height: 132px; }
            h1 { font-size: 2rem; }
            .panel-top { align-items: flex-start; }
            .portal-options { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body id="pageBody">
    <main class="login-shell">
        <section class="brand-panel" id="brandPanel" aria-labelledby="login-title">
            <div class="brand-logo-wrap">
                <img
                    class="brand-logo"
                    src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>"
                    alt="<?= htmlspecialchars(APP_NAME) ?>"
                >
            </div>

            <div class="hero-copy">
                <div class="eyebrow" id="eyebrowLabel">Employee access portal</div>
                <h1 id="login-title">One secure sign-in for infrastructure project teams.</h1>
                <p>Track projects, coordinate field updates, and manage delivery records through the portal assigned to your account.</p>
            </div>

            <div class="brand-footer">
                <img
                    class="city-seal"
                    src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>"
                    alt=""
                    aria-hidden="true"
                >
                <div>
                    <strong>LGU Infrastructure Office</strong>
                    <span>Protected access for authorized project personnel.</span>
                </div>
            </div>
        </section>

        <section class="login-panel" aria-label="Login form">
            <div class="panel-top">
                <img
                    class="panel-icon"
                    src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>"
                    alt=""
                    aria-hidden="true"
                >
                <div>
                    <h2>Sign in</h2>
                    <p class="panel-copy">Use the portal assigned to your account.</p>
                </div>
            </div>

            <?php if ($status !== ''): ?>
                <div class="message info"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="field">
                    <label>Portal</label>
                    <div class="portal-options" role="radiogroup" aria-label="Portal role">
                        <?php foreach ($portalRoles as $role => $portal): ?>
                            <label class="portal-option">
                                <input
                                    type="radio"
                                    name="portal_role"
                                    value="<?= htmlspecialchars($role) ?>"
                                    <?= $selectedRole === $role ? 'checked' : '' ?>
                                    onchange="switchPortal(this)"
                                    required
                                >
                                <span class="portal-option-content">
                                    <strong><?= htmlspecialchars($portal['label']) ?></strong>
                                    <span><?= htmlspecialchars($portal['description']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="identifier">Username or Email</label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        placeholder="Enter username or email"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        required
                    >
                </div>

                <button type="submit">Login</button>
            </form>

            <div class="footer">
                <a href="<?= htmlspecialchars(appUrl('/auth/forgot-password.php?from=staff')) ?>">Forgot your password?</a>
            </div>
            <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></div>
        </section>
    </main>

    <script>
        const portalThemes = {
            super_admin: {
                bodyBg:       '#1a0d2e',
                brandFrom:    '#1a0d2e',
                brandTo:      '#4c1d95',
                brandAccent:  'rgba(109,40,217,0.92)',
                primary:      '#7c3aed',
                secondary:    '#a78bfa',
                accent:       '#c4b5fd',
                eyebrow:      'Super Admin Portal',
            },
            admin: {
                bodyBg:       '#0d0d0d',
                brandFrom:    '#0a0a0a',
                brandTo:      '#1c1c2e',
                brandAccent:  'rgba(50,50,70,0.97)',
                primary:      '#6366f1',
                secondary:    '#818cf8',
                accent:       '#a5b4fc',
                eyebrow:      'Admin Portal',
            },
            bac: {
                bodyBg:       '#3b0a0a',
                brandFrom:    '#4a0e0e',
                brandTo:      '#7f1d1d',
                brandAccent:  'rgba(185,28,28,0.92)',
                primary:      '#ef4444',
                secondary:    '#f87171',
                accent:       '#fca5a5',
                eyebrow:      'BAC Portal',
            },
            engineer: {
                bodyBg:       '#0a2e1a',
                brandFrom:    '#052e16',
                brandTo:      '#166534',
                brandAccent:  'rgba(21,128,61,0.92)',
                primary:      '#16a34a',
                secondary:    '#22c55e',
                accent:       '#86efac',
                eyebrow:      'Engineer Portal',
            },
            contractor: {
                bodyBg:       '#1a1500',
                brandFrom:    '#1c1206',
                brandTo:      '#92400e',
                brandAccent:  'rgba(180,83,9,0.92)',
                primary:      '#d97706',
                secondary:    '#f59e0b',
                accent:       '#fcd34d',
                eyebrow:      'Contractor Portal',
            },
        };

        const defaultTheme = {
            bodyBg:      '#edf3f4',
            brandFrom:   '#0f393a',
            brandTo:     '#116466',
            brandAccent: 'rgba(47,95,187,0.92)',
            primary:     '#116466',
            secondary:   '#2f5fbb',
            accent:      '#d89c27',
            eyebrow:     'Employee access portal',
        };

        function applyTheme(t) {
            const root    = document.documentElement;
            const body    = document.getElementById('pageBody');
            const panel   = document.getElementById('brandPanel');
            const eyebrow = document.getElementById('eyebrowLabel');

            body.style.background  = t.bodyBg;
            panel.style.background = `linear-gradient(145deg, ${t.brandFrom}, ${t.brandTo} 56%, ${t.brandAccent})`;

            root.style.setProperty('--primary',   t.primary);
            root.style.setProperty('--secondary', t.secondary);
            root.style.setProperty('--accent',    t.accent);

            if (eyebrow) {
                eyebrow.textContent = t.eyebrow;
            }
        }

        function switchPortal(radio) {
            const theme = portalThemes[radio.value] || defaultTheme;
            applyTheme(theme);
        }

        /* Restore theme on page load if a role was already POST-selected (PHP re-render) */
        (function () {
            const checked = document.querySelector('input[name="portal_role"]:checked');
            if (checked) {
                switchPortal(checked);
            }
        })();
    </script>
</body>
</html>