<?php
require_once __DIR__ . '/session.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$error = '';
$status = '';
$selectedRole = trim((string) ($_POST['portal_role'] ?? ''));
$portalRoles = [
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
} elseif (isset($_GET['error'])) {
    $status = trim((string) $_GET['error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Validate CSRF token for login form
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

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                linear-gradient(135deg, rgba(17, 100, 102, 0.12), transparent 34%),
                linear-gradient(315deg, rgba(216, 156, 39, 0.16), transparent 28%),
                var(--page);
            display: grid;
            place-items: center;
            padding: 28px;
            color: var(--text);
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

        .brand-panel {
            position: relative;
            isolation: isolate;
            padding: 46px;
            background:
                linear-gradient(145deg, rgba(15, 57, 58, 0.98), rgba(17, 100, 102, 0.94) 56%, rgba(47, 95, 187, 0.92)),
                #123b42;
            color: #f7fbfb;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 44px;
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
        .brand-footer span {
            display: block;
        }

        .brand-footer span {
            margin-top: 3px;
            color: rgba(247, 251, 251, 0.72);
            font-size: 0.88rem;
            line-height: 1.45;
        }

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

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0;
            color: #334155;
        }

        .field {
            margin-bottom: 18px;
        }

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

        button {
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
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(17, 100, 102, 0.24);
        }

        .demo {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--soft);
            font-size: 0.88rem;
            color: var(--muted);
        }

        .demo summary {
            cursor: pointer;
            padding: 13px 14px;
            font-weight: 700;
            color: var(--text);
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 0 14px 14px;
        }

        .demo-grid div {
            min-width: 0;
        }

        code {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 0.12rem 0.36rem;
            color: var(--primary-dark);
            font-size: 0.82rem;
        }

        .footer {
            margin-top: 18px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.5;
        }

        @media (max-width: 920px) {
            body { padding: 18px; }
            .login-shell {
                grid-template-columns: 1fr;
                min-height: 0;
            }

            .brand-panel {
                padding: 32px;
            }

            .brand-logo-wrap {
                width: 156px;
                min-height: 156px;
            }

            h1 {
                font-size: 2.4rem;
            }
        }

        @media (max-width: 520px) {
            body { padding: 12px; }

            .brand-panel,
            .login-panel {
                padding: 24px 20px;
            }

            .brand-logo-wrap {
                width: 132px;
                min-height: 132px;
            }

            h1 {
                font-size: 2rem;
            }

            .panel-top {
                align-items: flex-start;
            }

            .portal-options { grid-template-columns: 1fr; }
            .demo-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="brand-panel" aria-labelledby="login-title">
            <div class="brand-logo-wrap">
                <img
                    class="brand-logo"
                    src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>"
                    alt="<?= htmlspecialchars(APP_NAME) ?>"
                >
            </div>

            <div class="hero-copy">
                <div class="eyebrow">Employee access portal</div>
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

            <details class="demo">
                <summary>Seed credentials</summary>
                <div class="demo-grid">
                    <div>Admin: <code>admin</code></div>
                    <div>BAC: <code>bac</code></div>
                    <div>Engineer: <code>engineer</code></div>
                    <div>Contractor: <code>contractor</code></div>
                    <div>Admin: <code>admin123</code></div>
                    <div>BAC: <code>bac123</code></div>
                    <div>Engineer: <code>engineer123</code></div>
                    <div>Contractor: <code>contractor123</code></div>
                </div>
            </details>

            <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></div>
        </section>
    </main>
</body>
</html>
