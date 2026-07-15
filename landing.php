<?php
require_once __DIR__ . '/includes/config.php';

$landingImages = [
    'building' => appUrl('/assets/img/landing_pic/building-qc.jpeg'),
    'flood' => appUrl('/assets/img/landing_pic/flood-control-qc.jpg'),
    'road' => appUrl('/assets/img/landing_pic/infrastructure-road-repair-28April2025.jpg'),
    'promenade' => appUrl('/assets/img/landing_pic/qc-elevated-promenade-1-1712648532.jpeg'),
    'cycling' => appUrl('/assets/img/landing_pic/Quezon-City-improves-its-cycling-infrastructure-1.jpg'),
];

/**
 * Live portal stats for the public landing page.
 *
 * Deliberately opens its own PDO connection (rather than including
 * includes/db.php) because getDB() prints a JSON error body and calls
 * exit() on failure — correct for an API endpoint, but it would corrupt
 * this HTML page. Any failure here just leaves $stats values null, and
 * the template falls back to the static quick-strip copy.
 */
$stats = [
    'projects' => null,
    'budget' => null,
    'feedback' => null,
    'avgProgress' => null,
];
$recentProjects = [];

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $statsDb = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2,
    ]);

    $row = $statsDb->query(
        "SELECT COUNT(*) AS total, COALESCE(SUM(budget), 0) AS budget, COALESCE(AVG(progress), 0) AS avg_progress
         FROM projects WHERE status NOT IN ('draft', 'returned')"
    )->fetch();

    $stats['projects'] = (int) $row['total'];
    $stats['budget'] = (float) $row['budget'];
    $stats['avgProgress'] = (int) round((float) $row['avg_progress']);
    $stats['feedback'] = (int) $statsDb->query('SELECT COUNT(*) FROM feedback')->fetchColumn();

    $recentProjects = $statsDb->query(
        "SELECT project_code, name, location, status, updated_at
         FROM projects WHERE status NOT IN ('draft', 'returned')
         ORDER BY updated_at DESC LIMIT 3"
    )->fetchAll();
} catch (Throwable $e) {
    // Public page must never break on a DB hiccup; keep the null/empty fallbacks.
}

/** Maps a project status to a display label, accent color, and whether it's "live". */
function projectStatusBadge(string $status): array
{
    $map = [
        'planning' => ['label' => 'Planning', 'var' => 'var(--blue)', 'pulse' => false],
        'approved' => ['label' => 'Approved', 'var' => 'var(--blue)', 'pulse' => false],
        'bidding' => ['label' => 'Bidding', 'var' => 'var(--blue)', 'pulse' => false],
        'awarded' => ['label' => 'Awarded', 'var' => 'var(--blue)', 'pulse' => false],
        'assigned' => ['label' => 'Assigned', 'var' => 'var(--blue)', 'pulse' => false],
        'active' => ['label' => 'Active', 'var' => 'var(--green)', 'pulse' => true],
        'delayed' => ['label' => 'Delayed', 'var' => 'var(--red)', 'pulse' => true],
        'on_hold' => ['label' => 'On hold', 'var' => 'var(--gold)', 'pulse' => false],
        'completed' => ['label' => 'Completed', 'var' => 'var(--deep)', 'pulse' => false],
        'cancelled' => ['label' => 'Cancelled', 'var' => 'var(--muted)', 'pulse' => false],
    ];

    return $map[$status] ?? ['label' => ucfirst($status), 'var' => 'var(--muted)', 'pulse' => false];
}

/** Picks a representative icon by keyword-matching the project name/location. */
function projectIcon(string $name, ?string $location): string
{
    $haystack = mb_strtolower($name . ' ' . ($location ?? ''));
    if (preg_match('/road|street|highway|lane|bridge/', $haystack)) return 'fa-road';
    if (preg_match('/drain|flood|river|dike|water/', $haystack)) return 'fa-water';
    if (preg_match('/school|hall|market|centre|center|building|hospital/', $haystack)) return 'fa-building';
    if (preg_match('/light|electric|power/', $haystack)) return 'fa-lightbulb';
    return 'fa-diagram-project';
}

function timeAgo(string $datetime): string
{
    $diff = max(0, time() - strtotime($datetime));
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return floor($diff / 2592000) . 'mo ago';
}

$fallbackStatusRows = [
    ['icon' => 'fa-road', 'color' => 'var(--blue)', 'title' => 'Road and mobility works', 'subtitle' => 'Repairs, lanes, access routes', 'badge' => 'Live', 'pulse' => true],
    ['icon' => 'fa-water', 'color' => 'var(--green)', 'title' => 'Drainage and flood control', 'subtitle' => 'Risk reduction and clearing works', 'badge' => 'Open', 'pulse' => true],
    ['icon' => 'fa-helmet-safety', 'color' => 'var(--red)', 'title' => 'Field inspection reports', 'subtitle' => 'Engineer updates and milestones', 'badge' => 'Daily', 'pulse' => true],
];

$statusRows = $fallbackStatusRows;
if (!empty($recentProjects)) {
    $statusRows = array_map(function ($p) {
        $badge = projectStatusBadge($p['status']);
        return [
            'icon' => projectIcon($p['name'], $p['location']),
            'color' => $badge['var'],
            'title' => $p['name'],
            'subtitle' => ($p['location'] ?: 'Quezon City') . ' · updated ' . timeAgo($p['updated_at']),
            'badge' => $badge['label'],
            'pulse' => $badge['pulse'],
        ];
    }, $recentProjects);
}

$metaScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$metaHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $metaScheme . '://' . $metaHost . appUrl('/landing.php');
$metaDescription = 'Track Quezon City public infrastructure projects from planning to completion — roads, flood control, public buildings, and mobility works, with live budget and progress transparency for residents.';

/**
 * Splits a raw number into an animatable {value, decimals, suffix} triplet,
 * e.g. 17450000 -> ['value' => 17.5, 'decimals' => 1, 'suffix' => 'M'].
 */
function compactStat(?float $n): ?array
{
    if ($n === null) {
        return null;
    }

    $suffix = '';
    $value = $n;
    if ($n >= 1_000_000_000) {
        $value = $n / 1_000_000_000;
        $suffix = 'B';
    } elseif ($n >= 1_000_000) {
        $value = $n / 1_000_000;
        $suffix = 'M';
    } elseif ($n >= 1_000) {
        $value = $n / 1_000;
        $suffix = 'K';
    }

    $decimals = ($suffix !== '' && $value < 100) ? 1 : 0;
    return ['value' => round($value, $decimals), 'decimals' => $decimals, 'suffix' => $suffix];
}

/** Renders a <b class="stat-number"> that JS animates on scroll into view. */
function renderStat(?float $n, string $prefix = '', string $suffix = ''): string
{
    $c = compactStat($n);
    if ($c === null) {
        return '';
    }
    return sprintf(
        '<b class="stat-number" data-target="%s" data-decimals="%d" data-prefix="%s" data-suffix="%s">0</b>',
        htmlspecialchars((string) $c['value']),
        $c['decimals'],
        htmlspecialchars($prefix),
        htmlspecialchars($c['suffix'] . $suffix)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Quezon City IPMS - Public Infrastructure Portal</title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Quezon City IPMS">
    <meta property="og:title" content="Quezon City IPMS - Public Infrastructure Portal">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($landingImages['building']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Quezon City IPMS - Public Infrastructure Portal">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($landingImages['building']) ?>">
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#1e3a8a">
    <style>
        /* Blue palette to match the QC City Hall photo used across the citizen
           portal. Variable names kept from the original green theme so layout
           CSS (and the PHP that references var(--green) etc.) is untouched:
           --deep = navy, --green = primary blue, --mint = light blue. */
        :root {
            --ink: #0f1c2e;
            --muted: #51617a;
            --deep: #1e3a8a;
            --green: #2563eb;
            --mint: #dbeafe;
            --gold: #f6b83f;
            --red: #d64a3a;
            --blue: #1f66b2;
            --paper: #f2f7fd;
            --white: #ffffff;
            --line: #d8e3f2;
            --shadow: 0 24px 60px rgba(15, 23, 42, .18);
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

        .skip-link {
            position: fixed;
            top: -60px;
            left: 12px;
            z-index: 100;
            background: var(--deep);
            color: var(--white);
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 800;
            font-size: .88rem;
            transition: top .2s ease;
        }
        .skip-link:focus { top: 12px; }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 2px;
            border-radius: 6px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
                scroll-behavior: auto !important;
            }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(26px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes floatY {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, .5); }
            70% { box-shadow: 0 0 0 8px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }
        @keyframes shimmerSweep {
            0% { transform: translateX(-120%) skewX(-12deg); }
            100% { transform: translateX(220%) skewX(-12deg); }
        }
        @keyframes bounceChevron {
            0%, 100% { transform: translateY(0); opacity: .55; }
            50% { transform: translateY(8px); opacity: 1; }
        }

        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .7s cubic-bezier(.16,.8,.3,1), transform .7s cubic-bezier(.16,.8,.3,1);
        }
        .reveal.is-visible { opacity: 1; transform: translateY(0); }

        .site-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background: rgba(251, 250, 245, .9);
            border-bottom: 1px solid rgba(16, 32, 29, .1);
            backdrop-filter: blur(14px);
            transition: box-shadow .3s ease, background .3s ease;
        }

        .site-nav.is-scrolled {
            background: rgba(251, 250, 245, .98);
            box-shadow: 0 8px 30px rgba(16, 32, 29, .08);
        }

        .nav-inner {
            width: min(1180px, calc(100% - 32px));
            min-height: 76px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
            transition: min-height .3s ease;
        }

        .site-nav.is-scrolled .nav-inner { min-height: 64px; }

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
            box-shadow: 0 12px 28px rgba(30, 58, 138, .12);
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
            position: relative;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 0 14px;
            font-size: .86rem;
            font-weight: 800;
            color: #3c4a63;
            transition: background .2s ease, color .2s ease;
        }

        .nav-links a:not(.portal-btn)::after {
            content: "";
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 6px;
            height: 2px;
            border-radius: 999px;
            background: var(--green);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .25s ease;
        }

        .nav-links a:not(.portal-btn):hover::after,
        .nav-links a:not(.portal-btn).is-active::after { transform: scaleX(1); }
        .nav-links a:not(.portal-btn).is-active { color: var(--deep); }

        .nav-links a:hover { background: rgba(37, 99, 235, .1); color: var(--deep); }
        .nav-links .portal-btn { background: var(--deep); color: var(--white); transition: background .2s ease, transform .2s ease; }
        .nav-links .portal-btn:hover { background: #16307a; color: var(--white); transform: translateY(-1px); }

        .hero {
            position: relative;
            min-height: 88vh;
            min-height: 88svh;
            padding: 112px 0 44px;
            display: flex;
            align-items: end;
            overflow: hidden;
            background: #16255c;
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

        .hero-bg-wrap {
            position: absolute;
            inset: -6% 0 0 0;
            width: 100%;
            height: 112%;
            overflow: hidden;
            will-change: transform;
        }

        .hero-bg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: heroZoom 16s ease-out forwards;
        }

        @keyframes heroZoom {
            from { transform: scale(1.12); }
            to { transform: scale(1.02); }
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

        .hero-inner > div > * ,
        .hero-inner > aside {
            opacity: 0;
            animation: fadeInUp .8s cubic-bezier(.16,.8,.3,1) forwards;
        }
        .hero-inner .eyebrow { animation-delay: .05s; }
        .hero-inner h1 { animation-delay: .16s; }
        .hero-inner p { animation-delay: .28s; }
        .hero-inner .hero-actions { animation-delay: .4s; }
        .hero-inner aside.status-panel { animation-delay: .52s; }

        .scroll-cue {
            position: absolute;
            left: 50%;
            bottom: 18px;
            z-index: 2;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, .75);
            font-size: 1.1rem;
            animation: bounceChevron 2.2s ease-in-out infinite;
            pointer-events: none;
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
            position: relative;
            overflow: hidden;
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
            transition: transform .18s ease, background .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 40%;
            height: 100%;
            background: linear-gradient(115deg, transparent, rgba(255, 255, 255, .35), transparent);
            transform: translateX(-120%) skewX(-12deg);
            transition: transform .55s ease;
        }

        .btn:hover::before { transform: translateX(220%) skewX(-12deg); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(30, 58, 138, .18); }
        .btn:active { transform: translateY(0); }
        .btn-primary { background: var(--gold); color: #221900; }
        .btn-primary:hover { background: #ffca5e; }
        .btn-light { background: rgba(255, 255, 255, .12); color: var(--white); border-color: rgba(255, 255, 255, .35); }
        .btn-light:hover { background: rgba(255, 255, 255, .2); }
        .btn-dark { background: var(--deep); color: var(--white); }
        .btn-dark:hover { background: #16307a; }

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
        .status-row { transition: background .2s ease; }
        .status-row:hover { background: rgba(37, 99, 235, .05); }
        .status-row i {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: var(--white);
            transition: transform .25s ease;
        }
        .status-row:hover i { transform: scale(1.08) rotate(-4deg); }
        .status-row strong { display: block; font-size: .9rem; }
        .status-row span { display: block; margin-top: 3px; color: var(--muted); font-size: .75rem; }
        .status-row b {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 1.1rem;
            color: var(--deep);
        }
        .status-row b::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            animation: pulseDot 2s ease-in-out infinite;
        }
        .status-row b.static::before { animation: none; background: var(--muted); }

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
            transition: background .25s ease, transform .25s ease;
        }

        .quick-item:hover { background: rgba(37, 99, 235, .05); transform: translateY(-3px); }
        .quick-item:last-child { border-right: 0; }
        .quick-item i {
            color: var(--green);
            font-size: 1.2rem;
            margin-bottom: 14px;
            display: inline-block;
            transition: transform .3s ease;
        }
        .quick-item:hover i { transform: scale(1.15) translateY(-2px); }
        .quick-item strong { display: block; font-size: .95rem; }
        .quick-item span { display: block; margin-top: 7px; color: var(--muted); font-size: .8rem; line-height: 1.5; }

        .stat-number {
            display: block;
            margin: 2px 0 6px;
            font-size: 1.9rem;
            font-weight: 900;
            letter-spacing: -.02em;
            color: var(--deep);
            font-variant-numeric: tabular-nums;
        }

        .project-search { padding-bottom: clamp(28px, 4vw, 46px); }

        .search-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: clamp(24px, 4vw, 40px);
            box-shadow: var(--shadow);
        }

        .search-card-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 28px;
            margin-bottom: 22px;
        }

        .search-card-head h2 {
            margin-top: 8px;
            font-size: clamp(1.4rem, 2.6vw, 2rem);
            line-height: 1.08;
            font-weight: 900;
        }

        .search-card-head p { max-width: 360px; color: var(--muted); line-height: 1.65; font-size: .92rem; }

        .search-form {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 58px;
            padding: 0 18px;
            border: 2px solid var(--line);
            border-radius: 8px;
            background: var(--paper);
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .search-form:focus-within {
            border-color: var(--green);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }

        .search-form i.fa-magnifying-glass { color: var(--muted); font-size: .95rem; }

        .search-form input {
            flex: 1;
            min-width: 0;
            height: 56px;
            border: 0;
            outline: 0;
            background: transparent;
            font: inherit;
            font-size: .95rem;
            color: var(--ink);
        }

        .search-form input::placeholder { color: #8b97ab; }

        .search-spinner {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid rgba(37, 99, 235, .25);
            border-top-color: var(--green);
            opacity: 0;
            animation: spin .7s linear infinite;
        }

        .search-spinner.is-active { opacity: 1; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .search-results:not(:empty) {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            animation: fadeInUp .35s ease;
        }

        .search-results-count {
            margin-bottom: 12px;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
        }

        .search-result-list { display: grid; gap: 8px; }

        .search-result-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--paper);
            animation: fadeInUp .4s ease both;
        }

        .search-result-row i {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--mint);
            color: var(--green);
            font-size: .85rem;
            flex-shrink: 0;
        }

        .search-result-row strong { display: block; font-size: .9rem; }
        .search-result-row span { display: block; margin-top: 2px; color: var(--muted); font-size: .76rem; letter-spacing: .04em; text-transform: uppercase; }

        .search-empty {
            padding: 18px;
            border: 1px dashed var(--line);
            border-radius: 8px;
            color: var(--muted);
            font-size: .88rem;
            text-align: center;
        }

        .search-more-cta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--line);
        }

        .search-more-cta span { color: var(--muted); font-size: .84rem; }

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
            background: #131c2e;
            transition: box-shadow .35s ease;
        }

        .feature-project:hover,
        .project-card:hover { box-shadow: 0 26px 54px rgba(6, 20, 18, .35); }

        .feature-project { min-height: 620px; }
        .feature-project img,
        .project-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .55s cubic-bezier(.16,.8,.3,1);
        }

        .feature-project:hover img,
        .project-card:hover img { transform: scale(1.06); }

        .feature-project::after,
        .project-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(7, 28, 25, .86) 0%, rgba(7, 28, 25, .2) 64%);
        }

        .project-copy .tag { transition: transform .3s ease; }
        .feature-project:hover .tag,
        .project-card:hover .tag { transform: translateY(-2px); }

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
            background: #eaf2fc;
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
            transition: transform .3s cubic-bezier(.16,.8,.3,1), box-shadow .3s ease, border-color .3s ease;
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 26px 48px rgba(16, 32, 29, .12);
            border-color: rgba(37, 99, 235, .35);
        }

        .service-card i {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: var(--white);
            font-size: 1.1rem;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1);
        }

        .service-card:hover i { transform: scale(1.12) rotate(-6deg); }

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
            background: #131c2e;
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
            transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
        }

        .timeline-item:hover {
            transform: translateX(6px);
            box-shadow: 0 16px 32px rgba(16, 32, 29, .08);
            border-color: rgba(37, 99, 235, .3);
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
            transition: transform .3s cubic-bezier(.34,1.56,.64,1), background .3s ease;
        }

        .timeline-item:hover b { transform: scale(1.08); background: var(--green); }

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
                linear-gradient(90deg, rgba(30, 58, 138, .95), rgba(30, 58, 138, .6)),
                url("<?= htmlspecialchars($landingImages['flood']) ?>") center/cover;
            transform: scale(1.08);
            transition: transform 6s ease;
        }

        .cta.is-visible::before { transform: scale(1); }

        .cta::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 85% 20%, rgba(246, 184, 63, .16), transparent 55%);
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
            background: #0e1626;
            color: #b8c4d6;
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
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
            padding-bottom: env(safe-area-inset-bottom);
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

        .back-to-top {
            position: fixed;
            right: max(22px, calc(env(safe-area-inset-right) + 12px));
            bottom: max(22px, calc(env(safe-area-inset-bottom) + 12px));
            z-index: 25;
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--deep);
            color: var(--white);
            box-shadow: 0 14px 30px rgba(30, 58, 138, .3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: opacity .3s ease, transform .3s ease, visibility .3s ease, background .2s ease;
        }

        .back-to-top.is-visible { opacity: 1; visibility: visible; transform: translateY(0); }
        .back-to-top:hover { background: var(--green); }

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

        @media (max-width: 380px) {
            .nav-inner { gap: 10px; }
            .brand span { font-size: .62rem; }
            .brand strong { font-size: .82rem; }
            .brand img { width: 34px; height: 34px; }
            .hero h1 { font-size: clamp(2.05rem, 9vw, 5.8rem); }
            .quick-item { padding: 18px; }
            .service-card { padding: 20px; }
            .cta .section-inner { padding: 8px 0; }
        }

        /*
         * Touch devices simulate :hover on tap and it can stay applied until
         * the user taps elsewhere, leaving cards visually "stuck" mid-animation.
         * Neutralize the decorative transform/shine effects for touch-only input
         * while keeping simple color/background hover feedback intact.
         */
        @media (hover: none), (pointer: coarse) {
            .quick-item:hover,
            .quick-item:hover i,
            .service-card:hover,
            .service-card:hover i,
            .timeline-item:hover,
            .timeline-item:hover b,
            .status-row:hover i,
            .feature-project:hover img,
            .project-card:hover img,
            .feature-project:hover .tag,
            .project-card:hover .tag,
            .btn:hover,
            .nav-links .portal-btn:hover {
                transform: none;
            }
            .btn:hover::before { transform: translateX(-120%) skewX(-12deg); }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
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
                <a href="#search" class="hide-tablet" data-nav-link><i class="fa-solid fa-magnifying-glass"></i> Search</a>
                <a href="#projects" class="hide-tablet" data-nav-link><i class="fa-solid fa-road"></i> Projects</a>
                <a href="#services" class="hide-tablet" data-nav-link><i class="fa-solid fa-building-columns"></i> Services</a>
                <a href="#process" class="hide-tablet" data-nav-link><i class="fa-solid fa-list-check"></i> Process</a>
                <a href="<?= htmlspecialchars(appUrl('/contractor/apply.php')) ?>" class="hide-tablet" data-nav-link><i class="fa-solid fa-building-user"></i> Become a Contractor</a>
                <a class="portal-btn" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>"><i class="fa-solid fa-right-to-bracket"></i> Portal</a>
            </div>
        </div>
    </nav>

    <main id="main-content">
        <section class="hero">
            <div class="hero-bg-wrap">
                <img class="hero-bg" src="<?= htmlspecialchars($landingImages['cycling']) ?>" alt="Quezon City cycling infrastructure and road works">
            </div>
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

                <aside class="status-panel" aria-label="Recently updated projects">
                    <header>
                        <span>Operations lens</span>
                        <strong>See what changed most recently</strong>
                    </header>
                    <?php foreach ($statusRows as $row): ?>
                        <div class="status-row">
                            <i class="fa-solid <?= htmlspecialchars($row['icon']) ?>" style="background:<?= htmlspecialchars($row['color']) ?>"></i>
                            <div>
                                <strong><?= htmlspecialchars($row['title']) ?></strong>
                                <span><?= htmlspecialchars($row['subtitle']) ?></span>
                            </div>
                            <b class="<?= $row['pulse'] ? '' : 'static' ?>"><?= htmlspecialchars($row['badge']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </aside>
            </div>
            <i class="fa-solid fa-chevron-down scroll-cue" aria-hidden="true"></i>
        </section>

        <section class="quick-strip" aria-label="Portal highlights">
            <div class="quick-item reveal">
                <i class="fa-solid fa-chart-line"></i>
                <?= renderStat($stats['projects']) ?>
                <strong>Projects tracked live</strong>
                <span>Follow milestones, status changes, and project timelines in one place.</span>
            </div>
            <div class="quick-item reveal" style="transition-delay:.08s">
                <i class="fa-solid fa-peso-sign"></i>
                <?= renderStat($stats['budget'], '₱') ?>
                <strong>Budget tracked</strong>
                <span>Review funded works, spending updates, and procurement movement.</span>
            </div>
            <div class="quick-item reveal" style="transition-delay:.16s">
                <i class="fa-solid fa-comments"></i>
                <?= renderStat($stats['feedback']) ?>
                <strong>Citizen reports received</strong>
                <span>Send reports, questions, and observations connected to public works.</span>
            </div>
            <div class="quick-item reveal" style="transition-delay:.24s">
                <i class="fa-solid fa-shield-halved"></i>
                <?= renderStat($stats['avgProgress'], '', '%') ?>
                <strong>Average completion</strong>
                <span>Connect LGU staff, BAC, engineers, contractors, and citizens.</span>
            </div>
        </section>

        <section id="search" class="section project-search">
            <div class="section-inner">
                <div class="search-card reveal">
                    <div class="search-card-head">
                        <div>
                            <div class="label">Look up a project</div>
                            <h2>Search the city's tracked infrastructure works.</h2>
                        </div>
                        <p>Search by project name, reference number, or location. Sign in to see full progress, budget, and updates.</p>
                    </div>
                    <div class="search-form">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="projectSearchInput" placeholder="e.g. Road Rehabilitation, PRJ-004, Barangay 7" aria-label="Search public projects" autocomplete="off">
                        <div class="search-spinner" id="searchSpinner" aria-hidden="true"></div>
                    </div>
                    <div class="search-results" id="searchResults" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <section id="projects" class="section">
            <div class="section-inner">
                <div class="section-head reveal">
                    <div>
                        <div class="label">City project gallery</div>
                        <h2>Infrastructure updates with the worksite in view.</h2>
                    </div>
                    <p>
                        Use the portal as a public window into ongoing works across city facilities, roads, drainage corridors, pedestrian routes, and active transport networks.
                    </p>
                </div>

                <div class="showcase">
                    <article class="feature-project reveal">
                        <img src="<?= htmlspecialchars($landingImages['building']) ?>" alt="Public building construction site in Quezon City">
                        <div class="project-copy">
                            <span class="tag"><i class="fa-solid fa-building"></i> Public facilities</span>
                            <h3>Building works monitored from inspection to turnover</h3>
                            <p>Keep construction progress, contractor updates, and site observations accessible to departments and the public.</p>
                        </div>
                    </article>
                    <div class="project-stack">
                        <article class="project-card reveal" style="transition-delay:.1s">
                            <img src="<?= htmlspecialchars($landingImages['road']) ?>" alt="Road repair and excavation works">
                            <div class="project-copy">
                                <span class="tag"><i class="fa-solid fa-road"></i> Road repair</span>
                                <h3>Repair works tracked by lane, area, and status</h3>
                            </div>
                        </article>
                        <article class="project-card reveal" style="transition-delay:.2s">
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
                <div class="section-head reveal">
                    <div>
                        <div class="label">LGU digital service</div>
                        <h2>Built for public works teams and residents.</h2>
                    </div>
                    <p>
                        IPMS organizes the project lifecycle so updates can move from offices and worksites into a clean, citizen-facing record.
                    </p>
                </div>

                <div class="service-grid">
                    <article class="service-card reveal">
                        <div>
                            <i class="fa-solid fa-file-signature" style="background:var(--green)"></i>
                            <h3>Project registration</h3>
                            <p>Capture scope, funding, location, schedule, and responsibility before work begins.</p>
                        </div>
                    </article>
                    <article class="service-card reveal" style="transition-delay:.06s">
                        <div>
                            <i class="fa-solid fa-handshake" style="background:var(--gold);color:#211800"></i>
                            <h3>Bidding and assignment</h3>
                            <p>Support BAC review, contractor assignment, and connected workflow records.</p>
                        </div>
                    </article>
                    <article class="service-card reveal" style="transition-delay:.12s">
                        <div>
                            <i class="fa-solid fa-clipboard-check" style="background:var(--blue)"></i>
                            <h3>Engineering updates</h3>
                            <p>Let field teams report milestones, inspection notes, delays, and progress evidence.</p>
                        </div>
                    </article>
                    <article class="service-card reveal" style="transition-delay:.18s">
                        <div>
                            <i class="fa-solid fa-money-check-dollar" style="background:var(--red)"></i>
                            <h3>Budget monitoring</h3>
                            <p>Review allocation, expenses, anomalies, and payment requests across active works.</p>
                        </div>
                    </article>
                    <article class="service-card reveal" style="transition-delay:.24s">
                        <div>
                            <i class="fa-solid fa-bullhorn" style="background:#7257b5"></i>
                            <h3>Citizen reports</h3>
                            <p>Receive complaints, suggestions, and inquiries tied to the right project record.</p>
                        </div>
                    </article>
                    <article class="service-card reveal" style="transition-delay:.3s">
                        <div>
                            <i class="fa-solid fa-chart-pie" style="background:#2a6f90"></i>
                            <h3>Dashboards and reports</h3>
                            <p>See project health, delays, completion rates, and documented activity by office.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="process" class="section">
            <div class="section-inner steps">
                <div class="steps-media reveal">
                    <img src="<?= htmlspecialchars($landingImages['flood']) ?>" alt="Flood control inspection in Quezon City">
                </div>
                <div>
                    <div class="label reveal">How the portal works</div>
                    <h2 class="reveal">From LGU records to citizen confidence.</h2>
                    <div class="timeline" style="margin-top:28px;">
                        <article class="timeline-item reveal">
                            <b>01</b>
                            <div>
                                <h3>LGU staff encode the project</h3>
                                <p>Project details, funding, location, deadlines, and office responsibilities are organized at the start.</p>
                            </div>
                        </article>
                        <article class="timeline-item reveal" style="transition-delay:.08s">
                            <b>02</b>
                            <div>
                                <h3>BAC and engineers move the workflow</h3>
                                <p>Approvals, contractor records, field inspections, and progress reports stay connected.</p>
                            </div>
                        </article>
                        <article class="timeline-item reveal" style="transition-delay:.16s">
                            <b>03</b>
                            <div>
                                <h3>Citizens follow and respond</h3>
                                <p>Residents can browse project information, see updates, and submit feedback for review.</p>
                            </div>
                        </article>
                        <article class="timeline-item reveal" style="transition-delay:.24s">
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

        <section class="cta reveal">
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

    <button type="button" class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script>
        (function () {
            var nav = document.querySelector('.site-nav');
            var backToTop = document.getElementById('backToTop');
            var navLinks = Array.prototype.slice.call(document.querySelectorAll('[data-nav-link]'));
            var sections = navLinks
                .map(function (link) {
                    var id = link.getAttribute('href').slice(1);
                    return document.getElementById(id);
                })
                .filter(Boolean);
            var heroBg = document.querySelector('.hero-bg-wrap');

            function onScroll() {
                var y = window.scrollY || window.pageYOffset;

                if (nav) nav.classList.toggle('is-scrolled', y > 12);
                if (backToTop) backToTop.classList.toggle('is-visible', y > 600);

                if (heroBg) {
                    heroBg.style.transform = 'translateY(' + Math.min(y * 0.25, 120) + 'px)';
                }

                var current = null;
                sections.forEach(function (section) {
                    if (y >= section.offsetTop - 140) current = section.id;
                });
                navLinks.forEach(function (link) {
                    link.classList.toggle('is-active', link.getAttribute('href') === '#' + current);
                });
            }

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            if (backToTop) {
                backToTop.addEventListener('click', function () {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            var revealTargets = document.querySelectorAll('.reveal');
            if ('IntersectionObserver' in window && revealTargets.length) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

                revealTargets.forEach(function (el) { observer.observe(el); });
            } else {
                revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
            }

            // Animated stat counters (projects tracked, budget, feedback, completion).
            function paintCounter(el, value) {
                var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
                el.textContent = (el.getAttribute('data-prefix') || '') + value.toFixed(decimals) + (el.getAttribute('data-suffix') || '');
            }

            function animateCounter(el) {
                var target = parseFloat(el.getAttribute('data-target'));
                if (isNaN(target)) return;
                var duration = 1100;
                var start = null;

                function step(ts) {
                    if (!start) start = ts;
                    var progress = Math.min((ts - start) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    paintCounter(el, target * eased);
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            var counters = document.querySelectorAll('.stat-number');
            if (counters.length) {
                if ('IntersectionObserver' in window) {
                    var counterObserver = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                animateCounter(entry.target);
                                counterObserver.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.4 });
                    counters.forEach(function (el) { counterObserver.observe(el); });
                } else {
                    counters.forEach(function (el) {
                        paintCounter(el, parseFloat(el.getAttribute('data-target')) || 0);
                    });
                }
            }

            // Public project search (teaser only — full details require citizen login).
            var searchInput = document.getElementById('projectSearchInput');
            var searchResults = document.getElementById('searchResults');
            var searchSpinner = document.getElementById('searchSpinner');
            var SEARCH_ENDPOINT = <?= json_encode(appUrl('/api/public_project_search.php')) ?>;
            var LOGIN_URL = <?= json_encode(appUrl('/citizen/login.php')) ?>;

            if (searchInput && searchResults) {
                var debounceTimer = null;
                var activeController = null;

                function escapeHtml(str) {
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                }

                function renderResults(data, query) {
                    if (!data || !data.results || data.results.length === 0) {
                        searchResults.innerHTML = '<div class="search-empty">No projects match &ldquo;' + escapeHtml(query) + '&rdquo;. Try a different keyword or barangay.</div>';
                        return;
                    }

                    var rows = data.results.map(function (r) {
                        return '<div class="search-result-row">' +
                            '<i class="fa-solid fa-diagram-project"></i>' +
                            '<div><strong>' + escapeHtml(r.name) + '</strong><span>' + escapeHtml(r.code) + '</span></div>' +
                            '</div>';
                    }).join('');

                    var remaining = data.total - data.results.length;
                    var countLabel = data.total + (data.total === 1 ? ' project matches' : ' projects match') + ' your search';

                    searchResults.innerHTML =
                        '<div class="search-results-count">' + countLabel + '</div>' +
                        '<div class="search-result-list">' + rows + '</div>' +
                        '<div class="search-more-cta">' +
                        '<span>' + (remaining > 0 ? remaining + ' more not shown. ' : '') + 'Sign in to view full progress, budget, and updates.</span>' +
                        '<a class="btn btn-dark" href="' + LOGIN_URL + '"><i class="fa-solid fa-right-to-bracket"></i> Sign in</a>' +
                        '</div>';
                }

                function runSearch(query) {
                    if (activeController) activeController.abort();
                    activeController = ('AbortController' in window) ? new AbortController() : null;
                    if (searchSpinner) searchSpinner.classList.add('is-active');

                    fetch(SEARCH_ENDPOINT + '?q=' + encodeURIComponent(query), {
                        signal: activeController ? activeController.signal : undefined
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            renderResults(data, query);
                        })
                        .catch(function (err) {
                            if (err && err.name === 'AbortError') return;
                            searchResults.innerHTML = '<div class="search-empty">Search is temporarily unavailable. Please try again shortly.</div>';
                        })
                        .finally(function () {
                            if (searchSpinner) searchSpinner.classList.remove('is-active');
                        });
                }

                searchInput.addEventListener('input', function () {
                    var query = searchInput.value.trim();
                    clearTimeout(debounceTimer);

                    if (query.length < 2) {
                        searchResults.innerHTML = '';
                        if (searchSpinner) searchSpinner.classList.remove('is-active');
                        return;
                    }

                    debounceTimer = setTimeout(function () { runSearch(query); }, 350);
                });
            }
        })();
    </script>
</body>
</html>
