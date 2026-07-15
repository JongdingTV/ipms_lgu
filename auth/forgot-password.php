<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../includes/OTPManager.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

// How long a real account must wait before another reset OTP can be created —
// throttles unauthenticated repeated-submission email spam now that real SMTP
// is live. Deliberately shorter than the resend cooldown on reset-password.php
// (which the user themselves controls); this one guards the *first* request.
define('FORGOT_PASSWORD_COOLDOWN_SECONDS', 45);

// This one page serves every role (citizen + all staff portals), so it has no
// way to know which login page the visitor came from other than this
// explicit marker — carried via ?from= on arrival and a hidden field across
// the POST, then stashed in session so reset-password.php's own "start over"
// link can stay consistent too.
$origin = (($_POST['from'] ?? $_GET['from'] ?? '') === 'citizen') ? 'citizen' : 'staff';
$loginPath = $origin === 'citizen' ? '/citizen/login.php' : '/auth/login.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && isset($_GET['error'])) {
    $error = $_GET['error'] === 'expired'
        ? 'Your reset session expired. Please request a new code.'
        : (string) $_GET['error'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfProtection();
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = getDB()->prepare('SELECT id, email, full_name, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $isRealActive = $user && ($user['status'] ?? '') === 'active';

        // Fresh state every submission — deliberately identical for the real and
        // fake paths below, so nothing about the response can reveal whether the
        // account exists (enumeration resistance is the point of this design).
        unset(
            $_SESSION['pending_reset_user_id'],
            $_SESSION['pending_reset_email'],
            $_SESSION['pending_reset_started_at'],
            $_SESSION['pending_reset_last_sent_at'],
            $_SESSION['pending_reset_fake_attempts'],
            $_SESSION['pending_reset_origin'],
            $_SESSION['dev_otp_preview']
        );

        $_SESSION['pending_reset_started_at'] = time();
        $_SESSION['pending_reset_last_sent_at'] = time();
        $_SESSION['pending_reset_fake_attempts'] = 0;
        $_SESSION['pending_reset_email'] = $isRealActive ? $user['email'] : $email;
        $_SESSION['pending_reset_origin'] = $origin;

        if ($isRealActive) {
            $_SESSION['pending_reset_user_id'] = (int) $user['id'];

            $otp = new OTPManager();
            if (!$otp->hasRecentOTP($user['id'], 'password_reset', FORGOT_PASSWORD_COOLDOWN_SECONDS)) {
                $result = $otp->createOTP($user['id'], 'password_reset');
                if ($result['success']) {
                    $sendResult = $otp->sendOTPEmail($user['email'], $user['full_name'], $result['otp_code']);
                    if (!$sendResult['success'] && !empty($sendResult['dev_fallback'])) {
                        $_SESSION['dev_otp_preview'] = $result['otp_code'];
                    }
                    logActivity((int) $user['id'], 'password_reset_requested', 'Password reset OTP sent');
                }
            }
        } elseif ($user) {
            // Exists but inactive — audit server-side only, client sees the
            // identical response as a nonexistent email.
            logActivity((int) $user['id'], 'password_reset_blocked_inactive', 'Reset requested for inactive account');
        }

        header('Location: ' . appUrl('/auth/reset-password.php?from=' . $origin));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="<?= $origin === 'citizen' ? '#1e3a8a' : '#1e40af' ?>">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';">
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

        .form-group { margin-bottom: 1.15rem; }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            color: var(--ink);
            font-size: 0.85rem;
        }

        input[type="email"] {
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

        input[type="email"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--focus-ring);
            background: #ffffff;
        }

        button[type="submit"] {
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

        button[type="submit"]:hover { transform: translateY(-1px); box-shadow: 0 16px 28px var(--btn-shadow); }
        button[type="submit"]:active { transform: translateY(0); }

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

        /* How the reset works — three compact steps under the form */
        .reset-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 1.5rem;
            padding-top: 1.4rem;
            border-top: 1px solid var(--line, #e2e8f0);
        }

        .reset-step {
            text-align: center;
            padding: 0.4rem 0.3rem;
        }

        .reset-step i {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            margin: 0 auto 6px;
            border-radius: 10px;
            background: var(--focus-ring, rgba(37, 99, 235, 0.12));
            color: var(--accent-deep, #1e40af);
            font-size: 0.85rem;
        }

        .reset-step strong {
            display: block;
            font-size: 0.76rem;
            color: var(--ink, #1c2430);
        }

        .reset-step span {
            display: block;
            margin-top: 2px;
            font-size: 0.7rem;
            line-height: 1.35;
            color: var(--muted);
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
            <div class="header-icon"><i class="fa-solid fa-unlock-keyhole"></i></div>
            <h1>Forgot Password</h1>
            <p>We'll email you a code to reset it</p>
        </div>

        <div class="card-body">
            <p class="scope-note">
                Enter the email address on your account. If it matches an active account,
                we'll send a 6-digit reset code to it.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="from" value="<?= htmlspecialchars($origin) ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
                <button type="submit">Send Reset Code</button>
            </form>

            <div class="login-footer">
                <p><a href="<?= htmlspecialchars(appUrl($loginPath)) ?>"><i class="fa-solid fa-arrow-left"></i> Back to login</a></p>
            </div>

            <div class="reset-steps" aria-label="How the reset works">
                <div class="reset-step">
                    <i class="fa-solid fa-envelope"></i>
                    <strong>1. Get the code</strong>
                    <span>We email a 6-digit code to your inbox</span>
                </div>
                <div class="reset-step">
                    <i class="fa-solid fa-key"></i>
                    <strong>2. Enter it</strong>
                    <span>Type it on the next screen right away — codes expire quickly</span>
                </div>
                <div class="reset-step">
                    <i class="fa-solid fa-shield-halved"></i>
                    <strong>3. New password</strong>
                    <span>Choose a strong password and sign back in</span>
                </div>
            </div>

            <div class="brand-strip">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/logocityhall.png')) ?>" alt="" aria-hidden="true">
                <span>Quezon City LGU &middot; Infrastructure Project Management System</span>
            </div>
        </div>
    </div>
</body>
</html>
