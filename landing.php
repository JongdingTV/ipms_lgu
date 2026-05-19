<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>IPMS — Public Portal</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
    <meta name="theme-color" content="#2563eb">
    <style>
        :root{--primary:#2563eb;--primary-dark:#1e40af;--bg1:#e6f0ff;--bg2:#eef7ff;--muted:#334155;--panel:#ffffff}
        *{box-sizing:border-box;margin:0;padding:0}
        html,body{height:100%}
        body{font-family:'Plus Jakarta Sans',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:linear-gradient(135deg,var(--bg1),var(--bg2));color:var(--muted);-webkit-font-smoothing:antialiased}
        header{background:rgba(255,255,255,0.9);backdrop-filter:blur(6px);position:sticky;top:0;z-index:10;border-bottom:1px solid rgba(15,23,42,0.04)}
        .nav-container{max-width:1200px;margin:0 auto;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between}
        .logo{display:flex;gap:.6rem;align-items:center;text-decoration:none;color:var(--primary);font-weight:800}
        .logo img{width:38px;height:38px;object-fit:contain;border-radius:8px;background:#fff;padding:3px;border:1px solid rgba(15,23,42,.08)}
        .nav-links{display:flex;gap:1rem;align-items:center}
        .nav-links a{color:var(--muted);text-decoration:none;font-weight:600;display:inline-flex;gap:.5rem;align-items:center}
        .nav-links a:hover{color:var(--primary)}
        .btn-primary{background:linear-gradient(90deg,var(--primary),var(--primary-dark));color:#fff;padding:.65rem 1rem;border-radius:999px;text-decoration:none;font-weight:700;display:inline-flex;gap:.5rem;align-items:center}
        main{display:flex;align-items:center;justify-content:center;padding:3.25rem 1.25rem}
        .hero{max-width:1200px;width:100%;display:grid;grid-template-columns:1fr 480px;gap:2.5rem;align-items:center}
        .hero h1{font-size:2.6rem;color:#083344;line-height:1.02}
        .hero p{color:#334155;font-size:1.05rem;margin-top:.75rem}
        .cta{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
        .cta .btn-ghost{background:transparent;border:2px solid rgba(8,51,68,.06);padding:.6rem 1rem;border-radius:999px;color:var(--muted)}
        .hero-illustration{display:flex;justify-content:center}
        .hero-mark{width:min(360px,80vw);height:auto;object-fit:contain;border-radius:12px;background:#fff;padding:1.5rem;border:1px solid rgba(8,51,68,.08);box-shadow:0 24px 60px rgba(37,99,235,.14)}
        .features{background:var(--panel);padding:3rem 1.25rem;border-top-left-radius:16px;border-top-right-radius:16px;margin-top:2rem}
        .features .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;max-width:1200px;margin:0 auto}
        .feature-card{background:#f8faf9;padding:1.25rem;border-radius:12px;display:flex;flex-direction:column;gap:.6rem}
        .feature-card .icon{width:48px;height:48px;border-radius:10px;display:grid;place-items:center;color:#fff;background:linear-gradient(90deg,var(--primary),var(--primary-dark))}
        footer{padding:1.25rem;text-align:center;color:#94a3b8}
        @media(max-width:900px){
            .hero{grid-template-columns:1fr;}
            .nav-links{gap:.6rem}
            .hero h1{font-size:2rem}
            .hero p{font-size:1rem}
            .cta .btn-primary, .cta .btn-ghost { width:100%; justify-content:center }
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <a class="logo" href="<?= htmlspecialchars(appUrl('/')) ?>">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
                IPMS
            </a>
            <nav class="nav-links">
                <a href="#features"><i class="fa fa-star"></i> Features</a>
                <a href="#about"><i class="fa fa-circle-info"></i> About</a>
                <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>" class="btn-primary"><i class="fa fa-right-to-bracket"></i> Login</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="hero">
            <div class="hero-content">
                <h1>Infrastructure Project Transparency</h1>
                <p>Track local infrastructure projects, submit feedback, and view transparent budget and progress information — from your barangay to the municipality.</p>
                <div class="cta">
                    <a class="btn-primary" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>"><i class="fa fa-right-to-bracket"></i> Login / Register</a>
                    <a class="btn-ghost" href="#features">Learn More</a>
                </div>
            </div>
            <div class="hero-illustration">
                <img class="hero-mark" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
            </div>
        </div>
    </main>

    <section id="features" class="features">
        <div style="max-width:1200px;margin:0 auto;">
            <h2 style="font-size:1.6rem;margin-bottom:1rem;color:#102a43">Citizen Portal Features</h2>
            <div class="grid">
                <div class="feature-card"><div class="icon"><i class="fa fa-list"></i></div><h3>Project Transparency</h3><p>View public projects with budgets, timelines, and updates.</p></div>
                <div class="feature-card"><div class="icon"><i class="fa fa-chart-line"></i></div><h3>Real-time Updates</h3><p>See progress and milestones as they are reported.</p></div>
                <div class="feature-card"><div class="icon"><i class="fa fa-comments"></i></div><h3>Feedback & Complaints</h3><p>Submit issues and track their resolution.</p></div>
                <div class="feature-card"><div class="icon"><i class="fa fa-map-marker-alt"></i></div><h3>Location View</h3><p>Filter projects by barangay, city, and province.</p></div>
            </div>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> Infrastructure Project Management System — Promoting transparency and accountability.
    </footer>
</body>
</html>
