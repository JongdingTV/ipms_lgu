<?php
require_once __DIR__ . '/session.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$error = '';
$status = '';

if (isset($_GET['timeout'])) {
    $status = 'Your session expired after 30 minutes of inactivity. Please sign in again.';
} elseif (isset($_GET['error'])) {
    $status = trim((string) $_GET['error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $result = authenticateUser($identifier, $password);
        if ($result['success']) {
            redirectToRoleDashboard($result['user']['role']);
        }

        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg: #08111f;
            --panel: rgba(255, 255, 255, 0.97);
            --text: #10213b;
            --muted: #5b6b84;
            --line: #d8e1ef;
            --primary: #0f766e;
            --secondary: #2563eb;
            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.22), transparent 25%),
                radial-gradient(circle at bottom right, rgba(15, 118, 110, 0.24), transparent 28%),
                linear-gradient(140deg, #07101c 0%, #0f1b2f 50%, #153768 100%);
            display: grid;
            place-items: center;
            padding: 24px;
            color: #e5eefc;
        }

        .shell {
            width: min(1080px, 100%);
            display: grid;
            grid-template-columns: 1.2fr minmax(340px, 420px);
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 24px 80px rgba(1, 8, 20, 0.45);
        }

        .hero {
            padding: 56px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02)),
                linear-gradient(140deg, rgba(37, 99, 235, 0.18), rgba(15, 118, 110, 0.1));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 36px;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(8, 17, 31, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(2.1rem, 4vw, 3.7rem);
            line-height: 1;
        }

        .hero p {
            max-width: 580px;
            margin: 0;
            line-height: 1.7;
            color: rgba(229, 238, 252, 0.8);
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .role-card {
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(8, 17, 31, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .role-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.95rem;
        }

        .role-card span {
            font-size: 0.82rem;
            color: rgba(229, 238, 252, 0.75);
        }

        .panel {
            background: var(--panel);
            color: var(--text);
            padding: 42px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel h2 {
            margin: 0 0 10px;
            font-size: 1.9rem;
        }

        .panel-copy {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.6;
        }

        .message {
            border-radius: 14px;
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

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #334155;
        }

        .field {
            margin-bottom: 18px;
        }

        input {
            width: 100%;
            padding: 0.95rem 1rem;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #f8fafc;
            font: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            background: #fff;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.24);
            cursor: pointer;
        }

        .demo {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: linear-gradient(180deg, #f8fafc 0%, #eef4fb 100%);
            padding: 16px;
            font-size: 0.88rem;
            color: var(--muted);
        }

        .demo strong {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
        }

        code {
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 0.18rem 0.45rem;
        }

        .footer {
            margin-top: 18px;
            font-size: 0.8rem;
            color: var(--muted);
        }

        @media (max-width: 920px) {
            .shell { grid-template-columns: 1fr; }
            .hero { padding: 32px 28px; }
        }

        @media (max-width: 520px) {
            body { padding: 14px; }
            .panel, .hero { padding-left: 22px; padding-right: 22px; }
            .role-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div>
                <div class="eyebrow">Secure Access Portal</div>
                <h1>Login for project oversight, engineering, contractors, and citizens.</h1>
                <p>
                    This authentication layer supports role-based routing, session timeout handling,
                    account status checks, activity logging, and brute-force protection.
                </p>
            </div>

            <div class="role-grid">
                <div class="role-card"><strong>Super Admin</strong><span>Full platform control and security oversight.</span></div>
                <div class="role-card"><strong>Admin</strong><span>Infrastructure operations and project management access.</span></div>
                <div class="role-card"><strong>Engineer</strong><span>Technical updates, progress review, and field coordination.</span></div>
                <div class="role-card"><strong>Contractor</strong><span>Assigned project progress and deliverable tracking.</span></div>
            </div>
        </section>

        <section class="panel">
            <h2>Sign in</h2>
            <p class="panel-copy">Use your username or email address and password to continue.</p>

            <?php if ($status !== ''): ?>
                <div class="message info"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
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

            <div class="demo">
                <strong>Seed credentials</strong>
                <div>Super Admin: <code>superadmin</code></div>
                <div>Admin: <code>admin</code></div>
                <div>Engineer: <code>engineer</code></div>
                <div>Contractor: <code>contractor</code></div>
                <div>Citizen: <code>citizen</code></div>
                <div>Password for all demo users: <code>admin123</code></div>
            </div>

            <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></div>
        </section>
    </div>
</body>
</html>
