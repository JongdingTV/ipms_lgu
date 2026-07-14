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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial;
            background: linear-gradient(135deg, #e6f0ff 0%, #eef7ff 100%);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .login-header p { color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; }
        .login-body { padding: 2rem; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }

        .form-group { margin-bottom: 1.5rem; }

        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; font-size: 0.95rem; }

        input[type="email"] {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        button {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        button:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(37,99,235,0.25); }
        button:active { transform: translateY(0); }

        .login-footer {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .login-footer p { color: #666; font-size: 0.9rem; }

        .scope-note { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Forgot Password</h1>
            <p>We'll email you a code to reset it</p>
        </div>

        <div class="login-body">
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
                <p><a href="<?= htmlspecialchars(appUrl($loginPath)) ?>">Back to login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
