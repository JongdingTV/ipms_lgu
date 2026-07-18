<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/Settings.php';
require_once __DIR__ . '/../includes/OTPManager.php';
require_once __DIR__ . '/../includes/workflow.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

usersEnsureLifecycleRoles(getDB());

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
    'hope' => [
        'label' => 'HOPE',
        'description' => 'Project approval authority',
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
            $staff2faRoles = ['super_admin', 'admin', 'bac', 'hope'];

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
    <title>Staff Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#1e3a8a">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';">
    <style>
        /* Same glass-over-City-Hall recipe as citizen/login.php so both
           entrances read as one product — but deliberately differentiated:
           the brand panel wears the staff sidebar's deep navy with the old
           staff login's gold accent, so employees can tell at a glance this
           is the internal entrance, not the citizen one. Unified for every
           portal (the old per-role recolor scheme was dropped deliberately). */
        :root {
            --ink: #0f1c2e;
            --muted: #51617a;
            --deep: #1e3a8a;
            --primary: #2563eb;
            --navy: #182333;
            --navy-mid: #22334d;
            --navy-soft: #28507d;
            --gold: #d89c27;
            --mint: #dbeafe;
            --paper: #f2f7fd;
            --white: #ffffff;
            --line: #d8e3f2;
            --shadow: 0 24px 60px rgba(15, 23, 42, .22);
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

        h1, h2 {
            font-family: 'Sora', 'Plus Jakarta Sans', system-ui, sans-serif;
            letter-spacing: -0.015em;
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

        /* Navy-leaning wash (darker than the citizen login's light-blue one)
           so the staff entrance feels more official over the same photo. */
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background: linear-gradient(180deg, rgba(24, 35, 51, 0.38), rgba(242, 247, 253, 0.35));
        }

        .login-shell {
            width: min(960px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(330px, 410px);
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(14px) saturate(1.4);
            -webkit-backdrop-filter: blur(14px) saturate(1.4);
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.55);
        }

        /* ── Brand panel ── */
        .brand-panel {
            position: relative;
            isolation: isolate;
            padding: 3rem 2.5rem;
            background: linear-gradient(150deg, rgba(15, 23, 42, 0.96), rgba(24, 35, 51, 0.93) 55%, rgba(40, 80, 125, 0.88));
            color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 2.5rem;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            inset: auto -40px -60px auto;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 50%;
            z-index: 0;
        }

        .brand-logo-wrap {
            position: relative;
            z-index: 1;
            width: 84px;
            height: 84px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 24px rgba(30, 58, 138, 0.35);
            padding: 12px;
        }

        .brand-logo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-copy {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 0.9rem;
            padding: 0.4rem 0.7rem;
            border-radius: 8px;
            background: rgba(216, 156, 39, 0.16);
            border: 1px solid rgba(216, 156, 39, 0.45);
            color: #f3c96b;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .brand-copy h1 {
            font-size: 1.9rem;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }

        .brand-copy p {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 360px;
        }

        /* Feature bullets under the headline */
        .brand-features {
            position: relative;
            z-index: 1;
            list-style: none;
            margin: 1.4rem 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .brand-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.88rem;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.45;
        }

        .brand-features i {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            background: rgba(216, 156, 39, 0.14);
            border: 1px solid rgba(216, 156, 39, 0.35);
            color: #f3c96b;
            font-size: 0.72rem;
            margin-top: 1px;
        }

        .brand-footer {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }

        .city-seal {
            width: 42px;
            height: 42px;
            object-fit: contain;
            flex: 0 0 auto;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.94);
            padding: 4px;
        }

        .brand-footer strong {
            display: block;
            font-size: 0.85rem;
        }

        .brand-footer span {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.78rem;
            line-height: 1.4;
        }

        /* ── Login panel ── */
        .login-panel {
            padding: 2.75rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .panel-icon {
            width: 46px;
            height: 46px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .login-panel h2 {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }

        .panel-copy {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .message {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .message.error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f6cac6;
        }

        .message.info {
            background: var(--mint);
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .field {
            margin-bottom: 1.15rem;
        }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            color: var(--ink);
            font-size: 0.85rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            min-height: 46px;
            padding: 0.7rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.65);
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
            background: var(--white);
        }

        /* ── Portal dropdown ── */
        .select-wrap {
            position: relative;
        }

        .select-wrap select {
            width: 100%;
            min-height: 46px;
            padding: 0.7rem 2.4rem 0.7rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.65);
            color: var(--ink);
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .select-wrap select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
            background: var(--white);
        }

        /* Placeholder state before a portal is picked */
        .select-wrap select:invalid {
            color: var(--muted);
            font-weight: 500;
        }

        .select-wrap select option {
            color: var(--ink);
            font-weight: 500;
        }

        .select-wrap::after {
            content: "\f078"; /* fa-chevron-down */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 0.95rem;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--muted);
            font-size: 0.75rem;
        }

        button[type="submit"] {
            width: 100%;
            min-height: 48px;
            margin-top: 0.4rem;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--primary), var(--deep));
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(37, 99, 235, 0.28);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .login-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer a:hover {
            color: var(--deep);
            text-decoration: underline;
        }

        /* ── Responsive ── */
        @media (max-width: 760px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                padding: 2rem 1.75rem;
                gap: 1.5rem;
            }

            .brand-copy p {
                max-width: none;
            }

            .login-panel {
                padding: 2rem 1.75rem;
            }
        }

        @media (max-width: 420px) {
            body {
                padding: 0;
            }

            .login-shell {
                border-radius: 0;
                min-height: 100vh;
            }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="brand-panel" aria-labelledby="login-title">
            <div class="brand-logo-wrap">
                <img class="brand-logo" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
            </div>

            <div class="brand-copy">
                <div class="eyebrow">Employee Access Portal</div>
                <h1 id="login-title">One secure sign-in for infrastructure project teams.</h1>
                <p>Track projects, coordinate field updates, and manage delivery records through the portal assigned to your account.</p>
                <ul class="brand-features">
                    <li><i class="fa-solid fa-diagram-project"></i>Manage the full project pipeline from registration to turnover</li>
                    <li><i class="fa-solid fa-file-signature"></i>Handle approvals, bidding, and contractor coordination in one place</li>
                    <li><i class="fa-solid fa-helmet-safety"></i>Post field updates, inspections, and payment records as work progresses</li>
                </ul>
            </div>

            <div class="brand-footer">
                <img class="city-seal" src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>" alt="" aria-hidden="true">
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
                    <label for="portal_role">Portal</label>
                    <div class="select-wrap">
                        <select id="portal_role" name="portal_role" required>
                            <option value="" disabled <?= $selectedRole === '' ? 'selected' : '' ?>>Select your portal</option>
                            <?php foreach ($portalRoles as $role => $portal): ?>
                                <option
                                    value="<?= htmlspecialchars($role) ?>"
                                    <?= $selectedRole === $role ? 'selected' : '' ?>
                                ><?= htmlspecialchars($portal['label']) ?> &mdash; <?= htmlspecialchars($portal['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        autocomplete="username"
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
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit">Login</button>
            </form>

            <div class="login-footer">
                <p><a href="<?= htmlspecialchars(appUrl('/auth/forgot-password.php?from=staff')) ?>">Forgot your password?</a></p>
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></p>
            </div>
        </section>
    </main>
</body>
</html>
