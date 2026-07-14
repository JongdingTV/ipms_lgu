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
                unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_email'], $_SESSION['dev_otp_preview']);
                header('Location: ' . appUrl('/citizen/login.php?verified=1'));
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
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
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .alert-dev { background: #fff8e1; color: #8a6d00; border: 1px solid #ffe082; font-family: monospace; }

        .form-group { margin-bottom: 1.5rem; }

        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; font-size: 0.95rem; }

        input[type="text"] {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1.4rem;
            letter-spacing: 0.4rem;
            text-align: center;
            font-family: monospace;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus {
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

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            box-shadow: none;
            margin-top: 0.75rem;
        }
        .btn-secondary:hover { transform: none; box-shadow: none; background: #e2e2e2; }

        .login-footer {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .login-footer p { color: #666; font-size: 0.9rem; }

        .scope-note { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .scope-note strong { color: #333; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fa fa-envelope-circle-check"></i> Verify Your Email</h1>
            <p>One more step to activate your citizen account</p>
        </div>

        <div class="login-body">
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
        </div>
    </div>
</body>
</html>
