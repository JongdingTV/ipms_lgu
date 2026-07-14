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
} elseif (isset($_GET['registered'])) {
    $status = 'Registration successful! Please log in with your credentials.';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }

        .login-body {
            padding: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        .form-group.remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .form-group.remember input {
            width: auto;
        }

        .form-group.remember label {
            margin: 0;
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

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37,99,235,0.25);
        }

        button:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .login-footer p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .login-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: #1e40af;
        }

        .back-link {
            margin-bottom: 1.5rem;
        }

        .back-link a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Citizen Portal</h1>
            <p>Project Transparency & Public Engagement</p>
        </div>

        <div class="login-body">
            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/landing.php')) ?>"><i class="fa fa-home"></i> Back to Home</a>
            </div>

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
                        autocomplete="username"
                        inputmode="email"
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
                <a href="<?= htmlspecialchars(appUrl('/citizen/register.php')) ?>" style="display: inline-block; padding: 0.5rem 1rem; background: #f0f0f0; border-radius: 6px; width: 100%; text-align: center;">
                    Create Account
                </a>
            </div>
        </div>
    </div>
</body>
</html>
