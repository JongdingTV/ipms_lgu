<?php
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - IPMS</title>
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
            <h1>Privacy Policy</h1>
            <p>Infrastructure Project Monitoring System (IPMS) &mdash; Quezon City</p>
        </div>
        <div class="back-link">
            <a href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="doc-body">
            <p class="updated">Last updated: <?= date('F j, Y') ?></p>

            <h2>1. Information We Collect</h2>
            <p>When you register as a citizen, we collect personal information necessary to verify your identity and residency, including your name, date of birth, contact details, address, and a photo of a valid government-issued ID. When you submit feedback, complaints, or project reviews, we also collect the content you provide, such as text, photos, and ratings.</p>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To verify that you are a resident eligible to use this portal.</li>
                <li>To create and secure your account, including sending one-time verification codes to your email.</li>
                <li>To route your feedback and complaints to the appropriate LGU office and follow up with you.</li>
                <li>To display project reviews publicly, in line with the transparency purpose of this portal (with your name shown only as you choose &mdash; see Section 4).</li>
            </ul>

            <h2>3. ID Verification</h2>
            <p>Your uploaded ID photo is used to automatically check that it shows a Quezon City address and that the name matches what you entered, and is subsequently reviewed by authorized LGU staff as part of manual verification. ID images are stored securely and are only accessible to authorized personnel for verification purposes.</p>

            <h2>4. Review Anonymity</h2>
            <p>When you submit a review for a project, you may choose to display your name (shown as first name and last-initial, e.g. "Juan D.") or to post anonymously. If you choose to post anonymously, your name will not be shown alongside your review to the public; LGU staff may still be able to see the account associated with a review for moderation and accountability purposes.</p>

            <h2>5. Who Can See Your Information</h2>
            <p>Your registration details are visible only to authorized LGU staff involved in verifying accounts and handling feedback. Project reviews and complaint content you choose to submit may be visible more broadly as described above and in these policies.</p>

            <h2>6. Data Retention</h2>
            <p>We retain your account and submission data for as long as your account is active and as needed to comply with applicable recordkeeping requirements. You may request account deactivation through LGU support channels.</p>

            <h2>7. Your Choices</h2>
            <p>You may choose whether to display your name on individual reviews. You may also request correction of inaccurate personal information by contacting LGU support.</p>

            <h2>8. Contact</h2>
            <p>Questions about this Privacy Policy or how your data is handled may be directed to your local government unit's designated support channel for this system.</p>
        </div>
    </div>
</body>
</html>
