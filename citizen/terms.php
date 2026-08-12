<?php
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - IPMS</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <meta name="theme-color" content="#1e3a8a">
    <style>
        :root {
            --ink: #0f1c2e;
            --muted: #51617a;
            --deep: #1e3a8a;
            --green: #2563eb;
            --mint: #dbeafe;
            --paper: #f2f7fd;
            --white: #ffffff;
            --line: #d8e3f2;
            --shadow: 0 24px 60px rgba(15, 23, 42, .18);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .doc-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            overflow: hidden;
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
        }
        .doc-header {
            background: linear-gradient(150deg, var(--deep), var(--green) 65%, #3b82f6 100%);
            color: var(--white);
            padding: 2rem;
        }
        .doc-header h1 { font-size: 1.55rem; margin-bottom: 0.35rem; }
        .doc-header p { color: rgba(255,255,255,0.85); font-size: 0.88rem; }
        .doc-body { padding: 2rem; line-height: 1.7; font-size: 0.95rem; }
        .doc-body h2 { font-size: 1.1rem; color: var(--deep); margin: 1.75rem 0 0.6rem; }
        .doc-body h2:first-child { margin-top: 0; }
        .doc-body p, .doc-body li { color: #33415c; margin-bottom: 0.6rem; }
        .doc-body ul { padding-left: 1.4rem; }
        .doc-body .updated { color: var(--muted); font-size: 0.82rem; margin-bottom: 1.5rem; }
        .back-link { padding: 1.25rem 2rem 0; }
        .back-link a {
            color: var(--green);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .back-link a:hover { color: var(--deep); }
    </style>
</head>
<body>
    <div class="doc-container">
        <div class="doc-header">
            <h1>Terms and Conditions</h1>
            <p>Infrastructure Project Monitoring System (IPMS) &mdash; Quezon City</p>
        </div>
        <div class="back-link">
            <a href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="doc-body">
            <p class="updated">Last updated: <?= date('F j, Y') ?></p>

            <h2>1. Acceptance of Terms</h2>
            <p>By creating a citizen account and using the Infrastructure Project Monitoring System (IPMS), you agree to be bound by these Terms and Conditions. If you do not agree, please do not register for or use this portal.</p>

            <h2>2. Purpose of the Portal</h2>
            <p>IPMS is provided by the local government to give citizens visibility into ongoing and completed infrastructure projects, and to allow citizens to submit feedback, complaints, and reviews related to those projects.</p>

            <h2>3. Account Registration and Identity Verification</h2>
            <p>To submit feedback tied to your identity, you must register using accurate personal information and a valid government-issued ID. Impersonation, providing false information, or submitting an ID that does not belong to you is prohibited and may result in account suspension and referral to the appropriate authorities.</p>

            <h2>4. Acceptable Use</h2>
            <ul>
                <li>Do not submit false, defamatory, or malicious reports.</li>
                <li>Do not use the portal to harass staff, contractors, or other citizens.</li>
                <li>Do not attempt to access accounts, data, or system functions that are not intended for citizen users.</li>
                <li>Feedback, complaints, and reviews should relate to actual infrastructure projects and public services covered by this system.</li>
            </ul>

            <h2>5. Content You Submit</h2>
            <p>Feedback, complaint reports, photos, and project reviews you submit may be visible to LGU staff and, where applicable, displayed publicly (for example, on a project's public review list) to promote transparency. You are responsible for the accuracy and appropriateness of what you submit.</p>

            <h2>6. Availability and Changes</h2>
            <p>The portal is provided on an as-available basis. Features may be added, changed, or removed over time, and these Terms may be updated to reflect those changes. Continued use of the portal after an update constitutes acceptance of the revised Terms.</p>

            <h2>7. Limitation of Liability</h2>
            <p>The LGU makes reasonable efforts to keep information on this portal accurate and up to date, but does not guarantee that all data (such as project status, timelines, or budgets) is free of error or delay.</p>

            <h2>8. Contact</h2>
            <p>Questions about these Terms may be directed to your local government unit's designated support channel for this system.</p>
        </div>
    </div>
</body>
</html>
