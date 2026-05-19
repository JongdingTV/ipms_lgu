<?php
require_once __DIR__ . '/../auth/session.php';

function renderRolePortal(array $user, string $title, string $description, array $cards = []): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
        <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Plus Jakarta Sans', sans-serif;
                background: linear-gradient(160deg, #08111f 0%, #10223d 60%, #173a6c 100%);
                color: #eff6ff;
                padding: 28px;
            }
            .shell {
                max-width: 1100px;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                gap: 18px;
            }
            .topbar, .card {
                background: rgba(9, 18, 32, 0.62);
                border: 1px solid rgba(255,255,255,0.08);
                border-radius: 22px;
                backdrop-filter: blur(14px);
            }
            .topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 18px;
                padding: 22px 26px;
            }
            .brand-line {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 18px;
            }
            .brand-line img {
                width: 64px;
                height: 64px;
                object-fit: contain;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.95);
                padding: 6px;
            }
            .brand-line span {
                display: block;
                color: rgba(239, 246, 255, 0.72);
                font-size: 0.82rem;
                font-weight: 700;
            }
            h1 {
                margin: 0 0 8px;
                font-size: clamp(2rem, 4vw, 3rem);
            }
            p {
                margin: 0;
                color: rgba(239, 246, 255, 0.78);
                line-height: 1.6;
            }
            .user-meta {
                text-align: right;
            }
            .user-meta strong {
                display: block;
                font-size: 1rem;
            }
            .logout {
                display: inline-flex;
                margin-top: 10px;
                padding: 10px 14px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                text-decoration: none;
                font-weight: 700;
            }
            .grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
            }
            .card {
                padding: 22px;
            }
            .card strong {
                display: block;
                margin-bottom: 8px;
                font-size: 1rem;
            }
            @media (max-width: 700px) {
                body { padding: 14px; }
                .topbar { flex-direction: column; align-items: flex-start; }
                .user-meta { text-align: left; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <section class="topbar">
                <div>
                    <div class="brand-line">
                        <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>" alt="" aria-hidden="true">
                        <span><?= htmlspecialchars(APP_NAME) ?></span>
                    </div>
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <p><?= htmlspecialchars($description) ?></p>
                </div>
                <div class="user-meta">
                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                    <span><?= htmlspecialchars(roleLabel($user['role'])) ?></span><br>
                    <a class="logout" href="<?= htmlspecialchars(appUrl('/auth/logout.php')) ?>">Logout</a>
                </div>
            </section>
            <section class="grid">
                <?php foreach ($cards as $card): ?>
                    <article class="card">
                        <strong><?= htmlspecialchars($card['title']) ?></strong>
                        <p><?= htmlspecialchars($card['body']) ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </body>
    </html>
    <?php
}
