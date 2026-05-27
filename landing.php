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
    <meta name="theme-color" content="#1d4ed8">
    <style>
        :root {
            --primary: #1d4ed8;
            --primary-mid: #2563eb;
            --primary-light: #0ea5e9;
            --primary-dark: #1e40af;
            --text: #1e293b;
            --muted: #475569;
            --subtle: #64748b;
            --line: #e2e8f0;
            --bg-page: #f0f6ff;
            --bg-white: #ffffff;
            --bg-surface: #f8faff;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-page);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }

        /* ── NAV ── */
        nav {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(29, 78, 216, 0.08);
        }

        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: .9rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-mark {
            display: flex;
            align-items: center;
            gap: .55rem;
            font-weight: 800;
            font-size: 1.05rem;
            color: #0c1a2e;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            overflow: hidden;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-links a {
            font-size: .88rem;
            font-weight: 600;
            color: var(--muted);
            transition: color .2s;
        }

        .nav-links a:hover { color: var(--primary); }

        .nav-cta {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff !important;
            padding: .55rem 1.2rem;
            border-radius: 999px;
            font-size: .87rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: opacity .2s;
        }

        .nav-cta:hover { opacity: .88; }

        /* ── HERO ── */
        .hero-section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 5rem 1.5rem 4rem;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 3rem;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: var(--primary);
            font-size: .75rem;
            font-weight: 700;
            padding: .35rem .85rem;
            border-radius: 999px;
            margin-bottom: 1.2rem;
            letter-spacing: .02em;
        }

        .hero-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .22);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(34, 197, 94, .22); }
            50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, .08); }
        }

        .hero-title {
            font-size: 2.9rem;
            font-weight: 800;
            line-height: 1.04;
            color: #0c1a2e;
            margin-bottom: 1rem;
        }

        .hero-title-accent {
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 480px;
            margin-bottom: 1.75rem;
        }

        .hero-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
            padding: .75rem 1.5rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .93rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: opacity .2s, transform .2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover { opacity: .88; transform: translateY(-1px); }

        .btn-ghost {
            background: #fff;
            border: 1.5px solid var(--line);
            color: var(--text);
            padding: .73rem 1.4rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: .93rem;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: border-color .2s, transform .2s;
        }

        .btn-ghost:hover { border-color: #94a3b8; transform: translateY(-1px); }

        .hero-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
        }

        .stat-item .stat-num {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0c1a2e;
        }

        .stat-item .stat-lbl {
            font-size: .73rem;
            color: var(--subtle);
            margin-top: .15rem;
        }

        /* ── HERO VISUAL ── */
        .hero-visual {
            background: var(--bg-white);
            border-radius: 20px;
            border: 1px solid rgba(30, 41, 59, .07);
            padding: 1.5rem;
            box-shadow: 0 32px 80px rgba(29, 78, 216, .1);
        }

        .vis-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .vis-title {
            font-size: .85rem;
            font-weight: 700;
            color: #0c1a2e;
        }

        .vis-live-badge {
            background: #dcfce7;
            color: #166534;
            font-size: .7rem;
            font-weight: 700;
            padding: .25rem .65rem;
            border-radius: 999px;
        }

        .proj-row {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            padding: .75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .proj-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .proj-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex: 0 0 auto;
        }

        .proj-name {
            font-size: .83rem;
            font-weight: 700;
            color: #0f172a;
        }

        .proj-loc {
            font-size: .72rem;
            color: var(--subtle);
            margin-top: .1rem;
        }

        .prog-bar {
            margin-top: .55rem;
            height: 5px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            width: 160px;
        }

        .prog-fill {
            height: 100%;
            border-radius: 999px;
        }

        .prog-meta {
            display: flex;
            justify-content: space-between;
            width: 160px;
            margin-top: .3rem;
        }

        .prog-meta span {
            font-size: .67rem;
            color: #94a3b8;
        }

        /* ── FEATURES ── */
        .features-section {
            background: var(--bg-white);
            padding: 5rem 1.5rem;
            margin-top: 1rem;
        }

        .section-inner { max-width: 1100px; margin: 0 auto; }

        .section-label {
            font-size: .72rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: .6rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 800;
            color: #0c1a2e;
            max-width: 520px;
            line-height: 1.15;
            margin-bottom: .75rem;
        }

        .section-sub {
            color: var(--muted);
            font-size: .97rem;
            line-height: 1.7;
            max-width: 520px;
            margin-bottom: 2.5rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        .feat-card {
            background: var(--bg-surface);
            border: 1px solid #e2eeff;
            border-radius: 16px;
            padding: 1.4rem;
            transition: transform .2s, box-shadow .2s;
        }

        .feat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(29, 78, 216, .09);
        }

        .feat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 1rem;
        }

        .feat-card h3 {
            font-size: .92rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .4rem;
        }

        .feat-card p {
            font-size: .82rem;
            color: var(--muted);
            line-height: 1.65;
        }

        /* ── HOW IT WORKS ── */
        .how-section {
            background: var(--bg-page);
            padding: 5rem 1.5rem;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2.5rem;
        }

        .step-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--line);
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }

        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(29, 78, 216, .07);
        }

        .step-bg-num {
            font-size: 4.5rem;
            font-weight: 800;
            color: #eff6ff;
            position: absolute;
            right: .75rem;
            top: .1rem;
            line-height: 1;
            user-select: none;
            pointer-events: none;
        }

        .step-dot {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            font-weight: 700;
            margin-bottom: 1rem;
            flex: 0 0 auto;
            position: relative;
        }

        .step-card h3 {
            font-size: .9rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .4rem;
            position: relative;
        }

        .step-card p {
            font-size: .8rem;
            color: var(--subtle);
            line-height: 1.65;
            position: relative;
        }

        /* ── CTA BAND ── */
        .cta-band {
            background: linear-gradient(135deg, var(--primary) 0%, #0369a1 100%);
            padding: 5rem 1.5rem;
            text-align: center;
        }

        .cta-band h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .75rem;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.15;
        }

        .cta-band p {
            color: rgba(255, 255, 255, .8);
            font-size: 1rem;
            line-height: 1.7;
            max-width: 460px;
            margin: 0 auto 2rem;
        }

        .btn-white {
            background: #fff;
            color: var(--primary);
            padding: .85rem 1.9rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .95rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: opacity .2s, transform .2s;
        }

        .btn-white:hover { opacity: .9; transform: translateY(-1px); }

        /* ── FOOTER ── */
        footer {
            background: #0c1a2e;
            color: #94a3b8;
            padding: 2.5rem 1.5rem;
        }

        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            font-weight: 700;
            font-size: .9rem;
            color: #cbd5e1;
        }

        .footer-icon {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .footer-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 3px;
        }

        .footer-copy {
            font-size: .8rem;
            line-height: 1.6;
            text-align: right;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .hero-section {
                grid-template-columns: 1fr;
                padding: 3rem 1.25rem 2.5rem;
            }

            .hero-visual { display: none; }
            .hero-title { font-size: 2.2rem; }
            .hero-sub { font-size: 1rem; }

            .nav-links .hide-mobile { display: none; }

            .footer-inner { flex-direction: column; align-items: flex-start; }
            .footer-copy { text-align: left; }
        }

        @media (max-width: 540px) {
            .hero-title { font-size: 1.9rem; }
            .hero-stats { gap: 1rem; flex-wrap: wrap; }
            .btn-primary, .btn-ghost { width: 100%; justify-content: center; }
            .section-title { font-size: 1.65rem; }
            .cta-band h2 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <!-- NAV -->
    <nav>
        <div class="nav-inner">
            <a href="<?= htmlspecialchars(appUrl('/')) ?>" class="logo-mark">
                <div class="logo-icon">
                    <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="">
                </div>
                IPMS
            </a>
            <div class="nav-links">
                <a href="#features" class="hide-mobile">
                    <i class="fa fa-star fa-xs"></i> Features
                </a>
                <a href="#how" class="hide-mobile">
                    <i class="fa fa-circle-info fa-xs"></i> How it works
                </a>
                <a href="#about" class="hide-mobile">
                    <i class="fa fa-building fa-xs"></i> About
                </a>
                <a href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>" class="nav-cta">
                    <i class="fa fa-right-to-bracket"></i> Login
                </a>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section>
        <div class="hero-section">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="hero-badge-dot"></span>
                    Live project tracking
                </div>
                <h1 class="hero-title">
                    Infrastructure<br>
                    <span class="hero-title-accent">Transparency</span><br>
                    for Every Citizen
                </h1>
                <p class="hero-sub">
                    Track local infrastructure projects, view real-time progress, and submit feedback — from your barangay to the municipality.
                </p>
                <div class="hero-actions">
                    <a class="btn-primary" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>">
                        <i class="fa fa-right-to-bracket"></i>
                        Login / Register
                    </a>
                    <a class="btn-ghost" href="#features">
                        Learn more <i class="fa fa-arrow-down fa-xs"></i>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-num">240+</div>
                        <div class="stat-lbl">Active projects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">18</div>
                        <div class="stat-lbl">Barangays covered</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">&#8369;2.1B</div>
                        <div class="stat-lbl">Funds tracked</div>
                    </div>
                </div>
            </div>

            <!-- Hero visual card -->
            <div class="hero-visual" aria-hidden="true">
                <div class="vis-header">
                    <span class="vis-title">Active Projects</span>
                    <span class="vis-live-badge">&#9679; Live</span>
                </div>

                <div class="proj-row">
                    <div class="proj-icon" style="background:#eff6ff">🏗️</div>
                    <div>
                        <div class="proj-name">Barangay Road Widening</div>
                        <div class="proj-loc">Brgy. San Isidro &middot; &#8369;4.2M</div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:72%;background:linear-gradient(90deg,#1d4ed8,#0ea5e9)"></div>
                        </div>
                        <div class="prog-meta"><span>In progress</span><span>72%</span></div>
                    </div>
                </div>

                <div class="proj-row">
                    <div class="proj-icon" style="background:#f0fdf4">🌉</div>
                    <div>
                        <div class="proj-name">Footbridge Rehabilitation</div>
                        <div class="proj-loc">Brgy. Sta. Cruz &middot; &#8369;1.8M</div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:100%;background:linear-gradient(90deg,#16a34a,#22c55e)"></div>
                        </div>
                        <div class="prog-meta"><span>Completed</span><span>100%</span></div>
                    </div>
                </div>

                <div class="proj-row">
                    <div class="proj-icon" style="background:#fff7ed">💧</div>
                    <div>
                        <div class="proj-name">Water System Upgrade</div>
                        <div class="proj-loc">Brgy. Poblacion &middot; &#8369;6.5M</div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:38%;background:linear-gradient(90deg,#d97706,#f59e0b)"></div>
                        </div>
                        <div class="prog-meta"><span>Ongoing</span><span>38%</span></div>
                    </div>
                </div>

                <div class="proj-row">
                    <div class="proj-icon" style="background:#fdf4ff">🏫</div>
                    <div>
                        <div class="proj-name">School Building Repair</div>
                        <div class="proj-loc">Brgy. Malaya &middot; &#8369;2.9M</div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:55%;background:linear-gradient(90deg,#7c3aed,#a78bfa)"></div>
                        </div>
                        <div class="prog-meta"><span>In progress</span><span>55%</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="features-section">
        <div class="section-inner">
            <div class="section-label">Citizen portal features</div>
            <h2 class="section-title">Everything you need to stay informed</h2>
            <p class="section-sub">A transparent, accessible window into how public funds are being used in your community.</p>
            <div class="features-grid">
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#1d4ed8,#0ea5e9)">
                        <i class="fa fa-list-ul"></i>
                    </div>
                    <h3>Project Transparency</h3>
                    <p>View public projects with full budget breakdowns, timelines, and official status updates.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#059669,#34d399)">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <h3>Real-time Progress</h3>
                    <p>See milestones and completion percentages reported live by field engineers.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)">
                        <i class="fa fa-comments"></i>
                    </div>
                    <h3>Feedback &amp; Complaints</h3>
                    <p>Submit issues about any project and track resolution status from end to end.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">
                        <i class="fa fa-map-marker-alt"></i>
                    </div>
                    <h3>Location Filtering</h3>
                    <p>Browse projects by barangay, city, or province with an interactive map view.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#0f766e,#2dd4bf)">
                        <i class="fa fa-file-alt"></i>
                    </div>
                    <h3>Document Access</h3>
                    <p>Download contracts, budgets, and inspection reports directly from the portal.</p>
                </div>
                <div class="feat-card">
                    <div class="feat-icon" style="background:linear-gradient(135deg,#be185d,#f472b6)">
                        <i class="fa fa-bell"></i>
                    </div>
                    <h3>Project Alerts</h3>
                    <p>Get notified when projects near you reach new milestones or encounter issues.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how" class="how-section">
        <div class="section-inner">
            <div class="section-label">How it works</div>
            <h2 class="section-title">Simple steps to get started</h2>
            <p class="section-sub">Joining the citizen portal takes under two minutes.</p>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-bg-num">01</div>
                    <div class="step-dot">1</div>
                    <h3>Create an account</h3>
                    <p>Register with your name, email, and barangay to get full access to the portal.</p>
                </div>
                <div class="step-card">
                    <div class="step-bg-num">02</div>
                    <div class="step-dot">2</div>
                    <h3>Browse projects</h3>
                    <p>Search and filter infrastructure projects in your area with real-time data.</p>
                </div>
                <div class="step-card">
                    <div class="step-bg-num">03</div>
                    <div class="step-dot">3</div>
                    <h3>Submit feedback</h3>
                    <p>Flag issues, leave comments, or commend completed work directly in the portal.</p>
                </div>
                <div class="step-card">
                    <div class="step-bg-num">04</div>
                    <div class="step-dot">4</div>
                    <h3>Track resolution</h3>
                    <p>Follow your submitted reports and get notified when they are addressed.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA BAND -->
    <section id="about" class="cta-band">
        <h2>Ready to see what's happening in your barangay?</h2>
        <p>Join thousands of citizens already using IPMS to hold local government accountable.</p>
        <a class="btn-white" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>">
            <i class="fa fa-right-to-bracket"></i>
            Get started &mdash; it&rsquo;s free
        </a>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-icon">
                    <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="">
                </div>
                IPMS &mdash; Infrastructure Project Management System
            </div>
            <div class="footer-copy">
                &copy; <?= date('Y') ?> LGU Infrastructure Office<br>
                Promoting transparency and accountability in local government.
            </div>
        </div>
    </footer>

</body>
</html>