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

        .login-shell {
            width: min(940px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 400px);
            border-radius: 16px;
            overflow: hidden;
            background: var(--white);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }

        /* ── Brand panel ── */
        .brand-panel {
            position: relative;
            isolation: isolate;
            padding: 3rem 2.5rem;
            background: linear-gradient(150deg, var(--deep), var(--green) 65%, #128a6c);
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
            box-shadow: 0 10px 24px rgba(6, 59, 51, 0.3);
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
            color: #0b5c46;
            border: 1px solid #b7e6d3;
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
            background: #fbfaf5;
            color: var(--ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(15, 122, 95, 0.14);
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
            box-shadow: 0 12px 24px rgba(15, 122, 95, 0.22);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(15, 122, 95, 0.26);
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
            color: #0b5c46;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .create-account-link:hover {
            background: #c7ecdc;
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
            </div>

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
