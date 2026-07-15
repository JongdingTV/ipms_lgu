<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

// Throttles the auto-resend below against repeated login submissions for the
// same not-yet-verified account (mirrors auth/forgot-password.php's cooldown).
define('CITIZEN_VERIFY_RESEND_COOLDOWN_SECONDS', 45);

$error = '';
$status = '';

if (isset($_GET['verified'])) {
    $status = 'Email verified! Your account is now active — please log in.';
    if (isset($_GET['needs_id'])) {
        $status .= ' Reminder: your account is unverified until you submit an ID photo — you can browse projects in the meantime.';
    }
} elseif (isset($_GET['registered'])) {
    $status = 'Registration successful! Please log in with your credentials.';
    if (isset($_GET['needs_id'])) {
        $status .= ' Reminder: your account is unverified until you submit an ID photo — you can browse projects in the meantime.';
    }
} elseif (isset($_GET['reset'])) {
    $status = 'Password reset! Please log in with your new password.';
} elseif (isset($_GET['timeout'])) {
    $status = 'Your session expired after 30 minutes of inactivity. Please sign in again.';
} elseif (isset($_GET['error'])) {
    $status = trim((string) $_GET['error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Validate CSRF for state-changing request
    requireCsrfProtection();

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your email/username and password.';
    } else {
        $result = authenticateUser($identifier, $password, 'citizen');
        if ($result['success']) {
            redirectToRoleDashboard($result['user']['role']);
        } elseif (!empty($result['inactive_user'])) {
            // Correct credentials, account just never finished email
            // verification (e.g. the original OTP session was lost). Resend a
            // fresh code through the same OTPManager path registration uses,
            // rather than leaving the citizen permanently stuck.
            $inactiveUser = $result['inactive_user'];
            $otp = new OTPManager();

            if (!$otp->hasRecentOTP($inactiveUser['user_id'], 'citizen_verification', CITIZEN_VERIFY_RESEND_COOLDOWN_SECONDS)) {
                $otpResult = $otp->createOTP($inactiveUser['user_id'], 'citizen_verification');
                if ($otpResult['success']) {
                    $sendResult = $otp->sendOTPEmail($inactiveUser['email'], $inactiveUser['full_name'], $otpResult['otp_code']);
                    if (!empty($sendResult['dev_fallback'])) {
                        $_SESSION['dev_otp_preview'] = $otpResult['otp_code'];
                    }
                }
            }

            $_SESSION['pending_otp_user_id'] = $inactiveUser['user_id'];
            $_SESSION['pending_otp_email'] = $inactiveUser['email'];
            header('Location: ' . appUrl('/citizen/verify-otp.php?resent=1'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Live counters for the brand panel — the same public numbers the landing
// page shows (no money figures here). Failure here should never block signing in.
$brandStats = null;
try {
    $brandStats = getDB()->query("
        SELECT COUNT(*) AS total,
               COALESCE(SUM(status = 'completed'), 0) AS completed,
               COALESCE(SUM(status IN ('active','delayed','on_hold')), 0) AS ongoing
        FROM projects
        WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completed')
    ")->fetch() ?: null;
} catch (Throwable $e) {
    $brandStats = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Login - IPMS</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#1e3a8a">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';">
    <style>
        /* Blue palette to match the QC City Hall photo backdrop. Variable names
           are kept from the original green theme so the layout CSS is untouched:
           --deep = navy, --green = primary blue, --mint = light blue. */
        :root {
            --ink: #0f1c2e;
            --muted: #51617a;
            --deep: #1e3a8a;
            --green: #2563eb;
            --mint: #dbeafe;
            --gold: #f6b83f;
            --red: #d64a3a;
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

        .login-shell {
            width: min(940px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 400px);
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
            background: linear-gradient(150deg, rgba(30, 58, 138, 0.94), rgba(37, 99, 235, 0.88) 65%, rgba(59, 130, 246, 0.84));
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
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            max-width: 340px;
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
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.72rem;
            margin-top: 1px;
        }

        /* Live counters pulled from the projects table */
        .brand-stats {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .brand-stat {
            flex: 1;
            min-width: 88px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            padding: 0.7rem 0.85rem;
        }

        .brand-stat strong {
            display: block;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .brand-stat span {
            display: block;
            margin-top: 2px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
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

        .login-panel h2 {
            font-size: 1.5rem;
            margin-bottom: 0.35rem;
        }

        .panel-copy {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .back-link {
            margin-bottom: 1.25rem;
        }

        .back-link a {
            color: var(--green);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.2s;
        }

        .back-link a:hover {
            color: var(--deep);
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .alert-error {
            background: #fdecea;
            color: #b3261e;
            border: 1px solid #f6cac6;
        }

        .alert-success {
            background: var(--mint);
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .form-group {
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
        input[type="email"],
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
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
            background: var(--white);
        }

        button[type="submit"] {
            width: 100%;
            min-height: 48px;
            margin-top: 0.4rem;
            padding: 0.8rem;
            background: linear-gradient(135deg, var(--green), var(--deep));
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
        }

        .login-footer p {
            color: var(--muted);
            font-size: 0.88rem;
            margin-bottom: 0.85rem;
        }

        .create-account-link {
            display: inline-block;
            width: 100%;
            padding: 0.65rem 1rem;
            background: var(--mint);
            color: #1e40af;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .create-account-link:hover {
            background: #bfdbfe;
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
    <div class="login-shell">
        <section class="brand-panel" aria-labelledby="login-title">
            <div class="brand-logo-wrap">
                <img class="brand-logo" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="IPMS logo">
            </div>

            <div class="brand-copy">
                <div class="eyebrow">Citizen Portal</div>
                <h1 id="login-title">Track infrastructure projects near you.</h1>
                <p>Sign in to follow project status, submit feedback, and stay updated on public works in your community.</p>
                <ul class="brand-features">
                    <li><i class="fa-solid fa-map-location-dot"></i>Browse projects on an interactive Quezon City map, barangay by barangay</li>
                    <li><i class="fa-solid fa-camera"></i>Report issues with photos and an exact pinned location</li>
                    <li><i class="fa-solid fa-chart-line"></i>See budgets, expenses, and progress in the transparency dashboard</li>
                </ul>
            </div>

            <?php if ($brandStats && (int) $brandStats['total'] > 0): ?>
            <div class="brand-stats">
                <div class="brand-stat"><strong><?= (int) $brandStats['total'] ?></strong><span>Projects tracked</span></div>
                <div class="brand-stat"><strong><?= (int) $brandStats['ongoing'] ?></strong><span>Ongoing</span></div>
                <div class="brand-stat"><strong><?= (int) $brandStats['completed'] ?></strong><span>Completed</span></div>
            </div>
            <?php endif; ?>

            <div class="brand-footer">
                <img class="city-seal" src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>" alt="" aria-hidden="true">
                <div>
                    <strong>Quezon City LGU</strong>
                    <span>Infrastructure Project Management System</span>
                </div>
            </div>
        </section>

        <section class="login-panel" aria-label="Login form">
            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/landing.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
            </div>

            <h2>Sign in</h2>
            <p class="panel-copy">Use your citizen account to continue.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($status): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($status) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label for="identifier">Email or Username</label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        placeholder="Enter your email or username"
                        required
                        autofocus
                        autocomplete="username"
                        inputmode="email"
                        value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit">Sign In</button>
            </form>

            <div class="login-footer">
                <p><a href="<?= htmlspecialchars(appUrl('/auth/forgot-password.php?from=citizen')) ?>">Forgot your password?</a></p>
                <p>Don't have an account yet?</p>
                <a href="<?= htmlspecialchars(appUrl('/citizen/register.php')) ?>" class="create-account-link">
                    Create Account
                </a>
            </div>
        </section>
    </div>
</body>
</html>
