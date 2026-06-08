<?php
require_once __DIR__ . '/includes/config.php';

$landingImages = [
    'building' => appUrl('/assets/img/landing_pic/building-qc.jpeg'),
    'flood' => appUrl('/assets/img/landing_pic/flood-control-qc.jpg'),
    'road' => appUrl('/assets/img/landing_pic/infrastructure-road-repair-28April2025.jpg'),
    'promenade' => appUrl('/assets/img/landing_pic/qc-elevated-promenade-1-1712648532.jpeg'),
    'cycling' => appUrl('/assets/img/landing_pic/Quezon-City-improves-its-cycling-infrastructure-1.jpg'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quezon City IPMS - Public Infrastructure Portal</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#063b33">
    <style>
        :root {
            --ink: #10201d;
            --muted: #52615d;
            --deep: #063b33;
            --green: #0f7a5f;
            --mint: #d9f3e7;
            --gold: #f6b83f;
            --red: #d64a3a;
            --blue: #1f66b2;
            --paper: #fbfaf5;
            --white: #ffffff;
            --line: #dce4dd;
            --shadow: 0 24px 60px rgba(16, 32, 29, .14);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--ink);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }

        .site-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background: rgba(251, 250, 245, .9);
            border-bottom: 1px solid rgba(16, 32, 29, .1);
            backdrop-filter: blur(14px);
        }

        .nav-inner {
            width: min(1180px, calc(100% - 32px));
            min-height: 76px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            background: var(--white);
            border-radius: 8px;
            padding: 4px;
            box-shadow: 0 12px 28px rgba(6, 59, 51, .12);
        }

        .brand span {
            display: block;
            font-size: .78rem;
            font-weight: 800;
            color: var(--deep);
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .brand strong {
            display: block;
            font-size: 1rem;
            line-height: 1.15;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 0 14px;
            font-size: .86rem;
            font-weight: 800;
            color: #34443f;
        }

        .nav-links a:hover { background: rgba(15, 122, 95, .1); color: var(--deep); }
        .nav-links .portal-btn { background: var(--deep); color: var(--white); }
        .nav-links .portal-btn:hover { background: #0b4d43; color: var(--white); }

        .hero {
            position: relative;
            min-height: 88vh;
            padding: 112px 0 44px;
            display: flex;
            align-items: end;
            overflow: hidden;
            background: #17302b;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(6, 35, 31, .9) 0%, rgba(6, 35, 31, .62) 48%, rgba(6, 35, 31, .2) 100%),
                linear-gradient(0deg, rgba(6, 35, 31, .68) 0%, rgba(6, 35, 31, .08) 50%);
            z-index: 1;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-inner {
            position: relative;
            z-index: 2;
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: clamp(28px, 5vw, 80px);
            align-items: end;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: #fff4d8;
            font-size: .76rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 4px;
            background: var(--gold);
            border-radius: 999px;
        }

        .hero h1 {
            max-width: 780px;
            color: var(--white);
            font-size: clamp(2.45rem, 6vw, 5.8rem);
            line-height: .95;
            font-weight: 900;
            letter-spacing: 0;
        }

        .hero p {
            max-width: 620px;
            margin-top: 22px;
            color: rgba(255, 255, 255, .86);
            font-size: clamp(1rem, 1.8vw, 1.2rem);
            line-height: 1.75;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 8px;
            padding: 0 20px;
            border: 1px solid transparent;
            font-size: .9rem;
            font-weight: 900;
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }

        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--gold); color: #221900; }
        .btn-primary:hover { background: #ffca5e; }
        .btn-light { background: rgba(255, 255, 255, .12); color: var(--white); border-color: rgba(255, 255, 255, .35); }
        .btn-light:hover { background: rgba(255, 255, 255, .2); }
        .btn-dark { background: var(--deep); color: var(--white); }
        .btn-dark:hover { background: #0b4d43; }

        .status-panel {
            background: rgba(251, 250, 245, .96);
            border: 1px solid rgba(255, 255, 255, .5);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .status-panel header {
            padding: 18px 18px 14px;
            border-bottom: 1px solid rgba(16, 32, 29, .1);
        }

        .status-panel header span {
            display: block;
            color: var(--green);
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .status-panel header strong {
            display: block;
            margin-top: 4px;
            font-size: 1.1rem;
        }

        .status-row {
            display: grid;
            grid-template-columns: 42px 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(16, 32, 29, .08);
        }

        .status-row:last-child { border-bottom: 0; }
        .status-row i {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: var(--white);
        }
        .status-row strong { display: block; font-size: .9rem; }
        .status-row span { display: block; margin-top: 3px; color: var(--muted); font-size: .75rem; }
        .status-row b { font-size: 1.1rem; color: var(--deep); }

        .quick-strip {
            position: relative;
            z-index: 3;
            width: min(1180px, calc(100% - 32px));
            margin: -30px auto 0;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            overflow: hidden;
        }

        .quick-item {
            min-height: 112px;
            padding: 22px;
            border-right: 1px solid var(--line);
        }

        .quick-item:last-child { border-right: 0; }
        .quick-item i { color: var(--green); font-size: 1.2rem; margin-bottom: 14px; }
        .quick-item strong { display: block; font-size: .95rem; }
        .quick-item span { display: block; margin-top: 7px; color: var(--muted); font-size: .8rem; line-height: 1.5; }

        .section {
            padding: clamp(56px, 8vw, 92px) 0;
        }

        .section-inner {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            gap: 28px;
            align-items: end;
            margin-bottom: 34px;
        }

        .label {
            color: var(--green);
            font-size: .76rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .section h2 {
            max-width: 700px;
            font-size: clamp(1.8rem, 3.6vw, 3.35rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
        }

        .section-head p {
            max-width: 390px;
            color: var(--muted);
            line-height: 1.7;
            font-size: .96rem;
        }

        .showcase {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 18px;
        }

        .feature-project,
        .project-card {
            position: relative;
            min-height: 320px;
            border-radius: 8px;
            overflow: hidden;
            background: #17201d;
        }

        .feature-project { min-height: 620px; }
        .feature-project img,
        .project-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .45s ease;
        }

        .feature-project:hover img,
        .project-card:hover img { transform: scale(1.04); }

        .feature-project::after,
        .project-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(7, 28, 25, .86) 0%, rgba(7, 28, 25, .2) 64%);
        }

        .project-copy {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            padding: clamp(20px, 4vw, 34px);
            color: var(--white);
        }

        .project-copy .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 28px;
            padding: 0 10px;
            margin-bottom: 12px;
            border-radius: 6px;
            background: rgba(246, 184, 63, .95);
            color: #241a00;
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .project-copy h3 {
            font-size: clamp(1.25rem, 2.7vw, 2.35rem);
            line-height: 1.05;
            letter-spacing: 0;
        }

        .project-copy p {
            max-width: 540px;
            margin-top: 10px;
            color: rgba(255, 255, 255, .84);
            line-height: 1.65;
            font-size: .92rem;
        }

        .project-stack {
            display: grid;
            gap: 18px;
        }

        .project-card { min-height: 301px; }

        .services {
            background: #eef7f1;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .service-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 24px;
            min-height: 210px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 18px 36px rgba(16, 32, 29, .06);
        }

        .service-card i {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: var(--white);
            font-size: 1.1rem;
        }

        .service-card h3 {
            margin-top: 18px;
            font-size: 1.05rem;
        }

        .service-card p {
            margin-top: 9px;
            color: var(--muted);
            line-height: 1.65;
            font-size: .88rem;
        }

        .steps {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 36px;
            align-items: center;
        }

        .steps-media {
            border-radius: 8px;
            overflow: hidden;
            min-height: 500px;
            background: #17201d;
            box-shadow: var(--shadow);
        }

        .steps-media img {
            width: 100%;
            height: 100%;
            min-height: 500px;
            object-fit: cover;
        }

        .timeline {
            display: grid;
            gap: 14px;
        }

        .timeline-item {
            display: grid;
            grid-template-columns: 54px 1fr;
            gap: 16px;
            align-items: start;
            padding: 20px;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .timeline-item b {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--deep);
            color: var(--white);
            font-size: 1rem;
        }

        .timeline-item h3 { font-size: 1rem; }
        .timeline-item p {
            margin-top: 7px;
            color: var(--muted);
            line-height: 1.6;
            font-size: .88rem;
        }

        .cta {
            position: relative;
            overflow: hidden;
            color: var(--white);
            background: var(--deep);
        }

        .cta::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(6, 59, 51, .95), rgba(6, 59, 51, .6)),
                url("<?= htmlspecialchars($landingImages['flood']) ?>") center/cover;
        }

        .cta .section-inner {
            position: relative;
            z-index: 1;
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .cta h2 { color: var(--white); max-width: 650px; }
        .cta p {
            max-width: 560px;
            margin-top: 16px;
            color: rgba(255, 255, 255, .82);
            line-height: 1.7;
        }

        .cta-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .site-footer {
            background: #101a18;
            color: #b9c8c2;
            padding: 30px 0;
        }

        .footer-inner {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            font-size: .82rem;
            line-height: 1.6;
        }

        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--white);
            font-weight: 900;
        }

        .footer-brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
            background: var(--white);
            border-radius: 8px;
            padding: 3px;
        }

        @media (max-width: 980px) {
            .nav-inner { min-height: 68px; }
            .nav-links .hide-tablet { display: none; }
            .hero { min-height: auto; padding-top: 104px; }
            .hero-inner,
            .showcase,
            .steps,
            .cta .section-inner {
                grid-template-columns: 1fr;
            }
            .hero-inner { align-items: start; }
            .status-panel { max-width: 520px; }
            .quick-strip,
            .service-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .quick-item:nth-child(2) { border-right: 0; }
            .quick-item:nth-child(1),
            .quick-item:nth-child(2) { border-bottom: 1px solid var(--line); }
            .feature-project { min-height: 480px; }
            .steps-media,
            .steps-media img { min-height: 360px; }
        }

        @media (max-width: 640px) {
            .brand strong { font-size: .88rem; }
            .brand span { font-size: .66rem; }
            .brand img { width: 38px; height: 38px; }
            .nav-links a:not(.portal-btn) { display: none; }
            .nav-links a { padding: 0 12px; }
            .hero { padding: 94px 0 34px; }
            .hero-actions .btn,
            .cta-actions .btn { width: 100%; }
            .status-row { grid-template-columns: 38px 1fr; }
            .status-row b { grid-column: 2; }
            .quick-strip,
            .service-grid { grid-template-columns: 1fr; }
            .quick-item { border-right: 0; border-bottom: 1px solid var(--line); }
            .quick-item:last-child { border-bottom: 0; }
            .section-head { display: block; }
            .section-head p { margin-top: 14px; }
            .feature-project,
            .project-card { min-height: 360px; }
            .timeline-item { grid-template-columns: 44px 1fr; padding: 16px; }
            .timeline-item b { width: 44px; height: 44px; }
            .footer-inner { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <nav class="site-nav" aria-label="Primary navigation">
        <div class="nav-inner">
            <a class="brand" href="<?= htmlspecialchars(appUrl('/')) ?>">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="IPMS logo">
                <span>
                    Quezon City
                    <strong>Infrastructure Portal</strong>
                </span>
            </a>
            <div class="nav-links">
                <a href="#projects" class="hide-tablet"><i class="fa-solid fa-road"></i> Projects</a>
                <a href="#services" class="hide-tablet"><i class="fa-solid fa-building-columns"></i> Services</a>
                <a href="#process" class="hide-tablet"><i class="fa-solid fa-list-check"></i> Process</a>
                <a class="portal-btn" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>"><i class="fa-solid fa-right-to-bracket"></i> Portal</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="hero">
            <img class="hero-bg" src="<?= htmlspecialchars($landingImages['cycling']) ?>" alt="Quezon City cycling infrastructure and road works">
            <div class="hero-inner">
                <div>
                    <div class="eyebrow">Public works, visible to the public</div>
                    <h1>Quezon City IPMS</h1>
                    <p>
                        A civic infrastructure portal for monitoring roads, flood control, public buildings, walkways, and mobility projects from planning to completion.
                    </p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('/citizen/register.php')) ?>">
                            <i class="fa-solid fa-user-plus"></i>
                            Create citizen account
                        </a>
                        <a class="btn btn-light" href="#projects">
                            <i class="fa-solid fa-map-location-dot"></i>
                            View highlighted works
                        </a>
                    </div>
                </div>

                <aside class="status-panel" aria-label="Infrastructure monitoring summary">
                    <header>
                        <span>Operations lens</span>
                        <strong>Track the work that shapes the city</strong>
                    </header>
                    <div class="status-row">
                        <i class="fa-solid fa-road" style="background:var(--blue)"></i>
                        <div>
                            <strong>Road and mobility works</strong>
                            <span>Repairs, lanes, access routes</span>
                        </div>
                        <b>Live</b>
                    </div>
                    <div class="status-row">
                        <i class="fa-solid fa-water" style="background:var(--green)"></i>
                        <div>
                            <strong>Drainage and flood control</strong>
                            <span>Risk reduction and clearing works</span>
                        </div>
                        <b>Open</b>
                    </div>
                    <div class="status-row">
                        <i class="fa-solid fa-helmet-safety" style="background:var(--red)"></i>
                        <div>
                            <strong>Field inspection reports</strong>
                            <span>Engineer updates and milestones</span>
                        </div>
                        <b>Daily</b>
                    </div>
                </aside>
            </div>
        </section>

        <section class="quick-strip" aria-label="Portal highlights">
            <div class="quick-item">
                <i class="fa-solid fa-chart-line"></i>
                <strong>Progress tracking</strong>
                <span>Follow milestones, status changes, and project timelines in one place.</span>
            </div>
            <div class="quick-item">
                <i class="fa-solid fa-peso-sign"></i>
                <strong>Budget visibility</strong>
                <span>Review funded works, spending updates, and procurement movement.</span>
            </div>
            <div class="quick-item">
                <i class="fa-solid fa-comments"></i>
                <strong>Citizen feedback</strong>
                <span>Send reports, questions, and observations connected to public works.</span>
            </div>
            <div class="quick-item">
                <i class="fa-solid fa-shield-halved"></i>
                <strong>Accountable workflow</strong>
                <span>Connect LGU staff, BAC, engineers, contractors, and citizens.</span>
            </div>
        </section>

        <section id="projects" class="section">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <div class="label">City project gallery</div>
                        <h2>Infrastructure updates with the worksite in view.</h2>
                    </div>
                    <p>
                        Use the portal as a public window into ongoing works across city facilities, roads, drainage corridors, pedestrian routes, and active transport networks.
                    </p>
                </div>

                <div class="showcase">
                    <article class="feature-project">
                        <img src="<?= htmlspecialchars($landingImages['building']) ?>" alt="Public building construction site in Quezon City">
                        <div class="project-copy">
                            <span class="tag"><i class="fa-solid fa-building"></i> Public facilities</span>
                            <h3>Building works monitored from inspection to turnover</h3>
                            <p>Keep construction progress, contractor updates, and site observations accessible to departments and the public.</p>
                        </div>
                    </article>
                    <div class="project-stack">
                        <article class="project-card">
                            <img src="<?= htmlspecialchars($landingImages['road']) ?>" alt="Road repair and excavation works">
                            <div class="project-copy">
                                <span class="tag"><i class="fa-solid fa-road"></i> Road repair</span>
                                <h3>Repair works tracked by lane, area, and status</h3>
                            </div>
                        </article>
                        <article class="project-card">
                            <img src="<?= htmlspecialchars($landingImages['promenade']) ?>" alt="Elevated promenade construction">
                            <div class="project-copy">
                                <span class="tag"><i class="fa-solid fa-person-walking"></i> Pedestrian access</span>
                                <h3>Walkable connections for safer movement</h3>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="services" class="section services">
            <div class="section-inner">
                <div class="section-head">
                    <div>
                        <div class="label">LGU digital service</div>
                        <h2>Built for public works teams and residents.</h2>
                    </div>
                    <p>
                        IPMS organizes the project lifecycle so updates can move from offices and worksites into a clean, citizen-facing record.
                    </p>
                </div>

                <div class="service-grid">
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-file-signature" style="background:var(--green)"></i>
                            <h3>Project registration</h3>
                            <p>Capture scope, funding, location, schedule, and responsibility before work begins.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-handshake" style="background:var(--gold);color:#211800"></i>
                            <h3>Bidding and assignment</h3>
                            <p>Support BAC review, contractor assignment, and connected workflow records.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-clipboard-check" style="background:var(--blue)"></i>
                            <h3>Engineering updates</h3>
                            <p>Let field teams report milestones, inspection notes, delays, and progress evidence.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-money-check-dollar" style="background:var(--red)"></i>
                            <h3>Budget monitoring</h3>
                            <p>Review allocation, expenses, anomalies, and payment requests across active works.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-bullhorn" style="background:#7257b5"></i>
                            <h3>Citizen reports</h3>
                            <p>Receive complaints, suggestions, and inquiries tied to the right project record.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <div>
                            <i class="fa-solid fa-chart-pie" style="background:#2a908d"></i>
                            <h3>Dashboards and reports</h3>
                            <p>See project health, delays, completion rates, and documented activity by office.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="process" class="section">
            <div class="section-inner steps">
                <div class="steps-media">
                    <img src="<?= htmlspecialchars($landingImages['flood']) ?>" alt="Flood control inspection in Quezon City">
                </div>
                <div>
                    <div class="label">How the portal works</div>
                    <h2>From LGU records to citizen confidence.</h2>
                    <div class="timeline" style="margin-top:28px;">
                        <article class="timeline-item">
                            <b>01</b>
                            <div>
                                <h3>LGU staff encode the project</h3>
                                <p>Project details, funding, location, deadlines, and office responsibilities are organized at the start.</p>
                            </div>
                        </article>
                        <article class="timeline-item">
                            <b>02</b>
                            <div>
                                <h3>BAC and engineers move the workflow</h3>
                                <p>Approvals, contractor records, field inspections, and progress reports stay connected.</p>
                            </div>
                        </article>
                        <article class="timeline-item">
                            <b>03</b>
                            <div>
                                <h3>Citizens follow and respond</h3>
                                <p>Residents can browse project information, see updates, and submit feedback for review.</p>
                            </div>
                        </article>
                        <article class="timeline-item">
                            <b>04</b>
                            <div>
                                <h3>Leaders see risks early</h3>
                                <p>Dashboards surface delay risk, budget alerts, and feedback trends before they become bigger problems.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="section-inner">
                <div>
                    <div class="label" style="color:#ffdb83;">Public participation</div>
                    <h2>See what is being built, repaired, and improved near you.</h2>
                    <p>Access the citizen portal to track infrastructure work and send feedback directly to the project record.</p>
                </div>
                <div class="cta-actions">
                    <a class="btn btn-primary" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Citizen login
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="">
                IPMS - Infrastructure Project Management System
            </div>
            <div>
                &copy; <?= date('Y') ?> LGU Infrastructure Office. Transparency, accountability, and better public works.
            </div>
        </div>
    </footer>
</body>
</html>
