<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../auth/session.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$success = isset($_GET['submitted']);
$heroImage = appUrl('/assets/img/landing_pic/building-qc.jpeg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Contractor - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --ink: #0f1c2e;
            --deep: #1e3a8a;
            --accent: #2563eb;
            --gold: #f6b83f;
            --paper: #f2f7fd;
            --line: #d8e3f2;
            --muted: #51617a;
            --good: #16a34a;
            --bad: #dc2626;
        }

        html, body { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial;
            color: var(--ink);
            background: var(--paper);
        }

        .apply-shell { display: flex; min-height: 100vh; }

        /* ── Left: brand / info panel ── */
        .apply-brand {
            position: relative;
            flex: 0 0 40%;
            min-height: 100vh;
            overflow-y: auto;
            padding: 2.5rem 2.75rem;
            color: #fff;
            display: flex;
            flex-direction: column;
            gap: 1.6rem;
        }
        .apply-brand::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 40%; height: 100%;
            background:
                linear-gradient(165deg, rgba(15,28,46,.94), rgba(30,58,138,.90)),
                url("<?= htmlspecialchars($heroImage) ?>") center/cover;
            z-index: -2;
        }
        .apply-brand::after {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 40%; height: 100%;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 34px 34px;
            z-index: -1;
        }

        .brand-back { color: rgba(255,255,255,.8); text-decoration: none; font-size: .85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; width: fit-content; transition: color .2s; }
        .brand-back:hover { color: #fff; }

        .brand-logo { display: flex; align-items: center; gap: 10px; }
        .brand-logo img { width: 38px; height: 38px; border-radius: 8px; background: #fff; padding: 3px; }
        .brand-logo span { font-weight: 800; font-size: 1.05rem; letter-spacing: .01em; }

        .brand-heading h1 { font-size: 1.85rem; font-weight: 800; line-height: 1.2; margin-bottom: .6rem; }
        .brand-heading p { color: rgba(255,255,255,.82); font-size: .95rem; line-height: 1.6; max-width: 40ch; }

        .brand-perks { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .brand-perks li { display: flex; align-items: flex-start; gap: 12px; font-size: .88rem; color: rgba(255,255,255,.9); line-height: 1.5; }
        .brand-perk-icon {
            flex-shrink: 0; width: 30px; height: 30px; border-radius: 9px;
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
            display: flex; align-items: center; justify-content: center;
        }
        .brand-perk-icon svg { width: 16px; height: 16px; color: var(--gold); }

        .brand-checklist {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            backdrop-filter: blur(6px);
        }
        .brand-checklist h3 { font-size: .82rem; text-transform: uppercase; letter-spacing: .06em; color: var(--gold); margin-bottom: .85rem; }
        .brand-checklist ul { list-style: none; display: flex; flex-direction: column; gap: 9px; }
        .brand-checklist li { display: flex; align-items: flex-start; gap: 9px; font-size: .82rem; line-height: 1.5; color: rgba(255,255,255,.88); }
        .brand-checklist li svg { width: 14px; height: 14px; margin-top: 2px; flex-shrink: 0; color: #93c5fd; }
        .brand-checklist strong { color: #fff; }

        /* ── Right: form panel ── */
        .apply-main { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 2.5rem 2rem 4rem; overflow-y: auto; }
        .apply-main-inner { width: 100%; max-width: 620px; }

        .stepper { display: flex; align-items: center; margin-bottom: 2.2rem; }
        .stepper-item { display: flex; align-items: center; flex: 1; }
        .stepper-item:last-child { flex: 0; }
        .stepper-dot {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700; color: var(--muted);
            background: #fff; border: 2px solid var(--line);
            transition: all .25s ease;
        }
        .stepper-label { font-size: .74rem; font-weight: 600; color: var(--muted); margin-left: 8px; white-space: nowrap; transition: color .25s ease; }
        .stepper-line { flex: 1; height: 2px; background: var(--line); margin: 0 12px; transition: background .25s ease; }
        .stepper-item.is-active .stepper-dot { background: var(--accent); border-color: var(--accent); color: #fff; }
        .stepper-item.is-active .stepper-label { color: var(--ink); }
        .stepper-item.is-done .stepper-dot { background: var(--good); border-color: var(--good); color: #fff; }
        .stepper-item.is-done .stepper-line { background: var(--good); }
        @media (max-width: 640px) { .stepper-label { display: none; } }

        .step { display: none; animation: stepIn .35s ease; }
        .step.is-active { display: block; }
        @keyframes stepIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .step-title { font-size: 1.3rem; font-weight: 800; margin-bottom: .3rem; }
        .step-subtitle { color: var(--muted); font-size: .88rem; margin-bottom: 1.6rem; line-height: 1.5; }

        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; position: relative; }
        label { font-weight: 600; color: #26344a; margin-bottom: .4rem; font-size: .87rem; }
        .required { color: var(--bad); }
        .field-hint { font-size: .74rem; color: var(--muted); margin-top: 4px; }
        .field-error { font-size: .76rem; color: var(--bad); margin-top: 4px; display: none; }

        input[type="text"], input[type="email"], textarea, select {
            padding: .75rem .85rem; border: 1.5px solid var(--line); border-radius: 8px;
            font-size: .92rem; font-family: inherit; background: #fff; color: var(--ink);
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }
        .form-group.is-invalid input, .form-group.is-invalid select, .form-group.is-invalid textarea { border-color: var(--bad); }
        .form-group.is-invalid .field-error { display: block; }
        .form-group.is-valid input, .form-group.is-valid select { border-color: var(--good); }

        /* ── Document dropzones ── */
        .doc-required-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 1.6rem; }
        .dropzone {
            display: flex; align-items: center; gap: 14px;
            border: 1.5px dashed var(--line); border-radius: 10px;
            padding: 14px 16px; background: #fff; cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .dropzone:hover, .dropzone.is-dragover { border-color: var(--accent); background: #eef4ff; }
        .dropzone.has-file { border-style: solid; border-color: var(--good); background: #f0fdf4; }
        .dropzone.is-invalid { border-color: var(--bad); }
        .dropzone-icon {
            flex-shrink: 0; width: 38px; height: 38px; border-radius: 9px;
            background: var(--paper); display: flex; align-items: center; justify-content: center; color: var(--accent);
        }
        .dropzone.has-file .dropzone-icon { background: #dcfce7; color: var(--good); }
        .dropzone-icon svg { width: 18px; height: 18px; }
        .dropzone-body { flex: 1; min-width: 0; }
        .dropzone-title { font-size: .87rem; font-weight: 700; color: var(--ink); }
        .dropzone-sub { font-size: .76rem; color: var(--muted); margin-top: 2px; }
        .dropzone-filename { font-size: .76rem; color: var(--good); margin-top: 2px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dropzone-remove {
            flex-shrink: 0; width: 26px; height: 26px; border-radius: 6px; border: 1px solid var(--line);
            background: #fff; color: var(--muted); font-size: .95rem; cursor: pointer; display: none;
        }
        .dropzone.has-file .dropzone-remove { display: block; }
        .dropzone-remove:hover { background: #fee2e2; border-color: var(--bad); color: var(--bad); }
        .dropzone input[type="file"] { display: none; }

        .doc-optional-title { font-size: .82rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; margin: 1.4rem 0 .75rem; }
        .doc-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: .75rem; }
        .doc-row {
            display: grid; grid-template-columns: 1fr 1.4fr 1.4fr auto; gap: 8px; align-items: center;
            padding: 10px; border: 1px dashed var(--line); border-radius: 8px; background: #fff;
        }
        .doc-row input[type="file"] { padding: .5rem; font-size: .82rem; }
        .doc-row-remove { width: 28px; height: 28px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--muted); font-size: 1rem; line-height: 1; cursor: pointer; }
        .doc-row-remove:hover { background: #fee2e2; border-color: var(--bad); color: var(--bad); }
        .doc-add-btn { align-self: flex-start; padding: 7px 14px; border: 1px dashed var(--accent); border-radius: 6px; background: #eef4ff; color: var(--accent); font-size: .83rem; font-weight: 700; cursor: pointer; }
        .doc-add-btn:hover { background: #dbe8ff; }
        @media (max-width: 640px) { .doc-row { grid-template-columns: 1fr; } }

        /* ── Review step ── */
        .review-group { margin-bottom: 1.4rem; }
        .review-group h3 { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: .6rem; display: flex; align-items: center; justify-content: space-between; }
        .review-edit { font-size: .74rem; color: var(--accent); cursor: pointer; font-weight: 700; text-transform: none; letter-spacing: 0; background: none; border: none; }
        .review-edit:hover { text-decoration: underline; }
        .review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem 1rem; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; }
        .review-grid dt { font-size: .72rem; color: var(--muted); }
        .review-grid dd { font-size: .87rem; font-weight: 600; color: var(--ink); word-break: break-word; }
        .review-docs { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 4px 16px; }
        .review-docs li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; font-size: .85rem; border-bottom: 1px solid var(--line); }
        .review-docs li:last-child { border-bottom: none; }
        .review-docs .doc-name { color: var(--muted); }
        .review-docs .doc-file { font-weight: 600; }

        /* ── Step nav / alerts ── */
        .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; gap: 12px; }
        .btn { padding: .8rem 1.5rem; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; border: none; transition: all .2s; }
        .btn-primary { background: linear-gradient(90deg, var(--accent), var(--deep)); color: #fff; margin-left: auto; }
        .btn-primary:hover { box-shadow: 0 10px 24px rgba(37,99,235,.28); transform: translateY(-1px); }
        .btn-ghost { background: none; color: var(--muted); border: 1.5px solid var(--line); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }

        .alert { padding: .9rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: .88rem; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .draft-banner { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: .7rem 1rem; border-radius: 8px; font-size: .82rem; margin-bottom: 1.4rem; }
        .draft-banner button { background: none; border: none; color: #92400e; font-weight: 700; text-decoration: underline; cursor: pointer; font-size: .82rem; }

        /* ── Success screen ── */
        .success-screen { text-align: center; padding: 2rem 1rem; }
        .success-icon { width: 68px; height: 68px; border-radius: 50%; background: #dcfce7; color: var(--good); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.4rem; }
        .success-icon svg { width: 34px; height: 34px; }
        .success-screen h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: .6rem; }
        .success-screen p { color: var(--muted); font-size: .92rem; line-height: 1.6; max-width: 46ch; margin: 0 auto 1.6rem; }
        .success-steps { text-align: left; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 1.2rem 1.4rem; max-width: 420px; margin: 0 auto 1.8rem; display: flex; flex-direction: column; gap: 12px; }
        .success-steps li { display: flex; gap: 10px; font-size: .84rem; color: #26344a; line-height: 1.5; list-style: none; }
        .success-steps b { flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--paper); color: var(--accent); font-size: .75rem; display: flex; align-items: center; justify-content: center; }

        @media (max-width: 900px) {
            .apply-shell { flex-direction: column; }
            .apply-brand { position: static; flex: none; min-height: auto; padding: 2rem 1.5rem; }
            .apply-brand::before, .apply-brand::after { position: absolute; width: 100%; }
            .apply-main { padding: 2rem 1.25rem 3rem; }
        }
    </style>
</head>
<body>
    <div class="apply-shell">
        <aside class="apply-brand">
            <a class="brand-back" href="<?= htmlspecialchars(appUrl('/landing.php')) ?>">&larr; Back to Home</a>

            <div class="brand-logo">
                <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="">
                <span><?= htmlspecialchars(APP_NAME) ?></span>
            </div>

            <div class="brand-heading">
                <h1>Build Quezon City's Infrastructure With Us</h1>
                <p>Join the LGU's roster of accredited infrastructure contractors and bid on public works projects.</p>
            </div>

            <ul class="brand-perks">
                <li>
                    <span class="brand-perk-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a1 1 0 01.894.553l1.382 2.76 3.05.443a1 1 0 01.554 1.706l-2.207 2.15.521 3.036a1 1 0 01-1.451 1.054L10 11.347l-2.723 1.355a1 1 0 01-1.451-1.054l.52-3.036-2.206-2.15a1 1 0 01.554-1.706l3.05-.443L9.106 1.553A1 1 0 0110 1z" clip-rule="evenodd"/></svg></span>
                    <span>Transparent, BAC-reviewed bidding process for every project</span>
                </li>
                <li>
                    <span class="brand-perk-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg></span>
                    <span>Milestone-based payments tracked and released on schedule</span>
                </li>
                <li>
                    <span class="brand-perk-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg></span>
                    <span>Steady pipeline of roads, drainage, and public building projects</span>
                </li>
            </ul>

            <div class="brand-checklist">
                <h3>What you'll need to apply</h3>
                <ul>
                    <li><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><span><strong>DTI or SEC Registration</strong> — proof of legal business registration</span></li>
                    <li><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><span><strong>Mayor's / Business Permit</strong> — current year</span></li>
                    <li><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><span><strong>Tax Clearance Certificate</strong> — from the BIR</span></li>
                    <li><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><span><strong>PCAB License</strong> — matching your license number below</span></li>
                    <li><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg><span><strong>Audited Financial Statement</strong> — most recent fiscal year</span></li>
                </ul>
            </div>
        </aside>

        <main class="apply-main">
            <div class="apply-main-inner">
            <?php if ($success): ?>
                <div class="success-screen">
                    <div class="success-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    </div>
                    <h2>Application Submitted</h2>
                    <p>Thanks for applying. The Bids and Awards Committee (BAC) will review your company details and documents.</p>
                    <ul class="success-steps">
                        <li><b>1</b> BAC reviews your registration, PCAB license, and supporting documents.</li>
                        <li><b>2</b> You'll receive an email with the decision — approval or a request for changes.</li>
                        <li><b>3</b> Once approved, follow the emailed link to set your password and access the Contractor Portal.</li>
                    </ul>
                    <a class="btn btn-ghost" href="<?= htmlspecialchars(appUrl('/landing.php')) ?>">Back to Home</a>
                </div>
            <?php else: ?>
                <div class="stepper" id="stepper">
                    <div class="stepper-item is-active" data-step="1"><div class="stepper-dot">1</div><div class="stepper-label">Company</div></div>
                    <div class="stepper-line"></div>
                    <div class="stepper-item" data-step="2"><div class="stepper-dot">2</div><div class="stepper-label">Legal &amp; PCAB</div></div>
                    <div class="stepper-line"></div>
                    <div class="stepper-item" data-step="3"><div class="stepper-dot">3</div><div class="stepper-label">Documents</div></div>
                    <div class="stepper-line"></div>
                    <div class="stepper-item" data-step="4"><div class="stepper-dot">4</div><div class="stepper-label">Review</div></div>
                </div>

                <div class="draft-banner" id="draftBanner" style="display:none;">
                    <span>We restored your saved progress from a previous visit.</span>
                    <button type="button" id="draftClear">Clear &amp; start over</button>
                </div>

                <form id="applyForm" enctype="multipart/form-data" novalidate
                      data-api-url="<?= htmlspecialchars(appUrl('/contractor/api/apply.php')) ?>"
                      data-redirect-url="<?= htmlspecialchars(appUrl('/contractor/apply.php')) ?>?submitted=1">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                    <!-- Step 1: Company Details -->
                    <section class="step is-active" data-step="1">
                        <h2 class="step-title">Company Details</h2>
                        <p class="step-subtitle">Tell us about your business and how BAC can reach you.</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Company Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required maxlength="150">
                                <span class="field-error">Company name is required.</span>
                            </div>
                            <div class="form-group">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" maxlength="120">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required maxlength="180">
                                <span class="field-error">A valid email address is required.</span>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" maxlength="30">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Business Address</label>
                            <textarea id="address" name="address" rows="2" maxlength="1000"></textarea>
                        </div>
                    </section>

                    <!-- Step 2: Legal & PCAB -->
                    <section class="step" data-step="2">
                        <h2 class="step-title">Legal &amp; PCAB Details</h2>
                        <p class="step-subtitle">This is what BAC checks against a project's budget and scope.</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pcab_license_no">PCAB License Number <span class="required">*</span></label>
                                <input type="text" id="pcab_license_no" name="pcab_license_no" required maxlength="50">
                                <span class="field-error">PCAB license number is required.</span>
                            </div>
                            <div class="form-group">
                                <label for="pcab_classification">PCAB Classification <span class="required">*</span></label>
                                <select id="pcab_classification" name="pcab_classification" required>
                                    <option value="">Select classification</option>
                                    <option value="Small B">Small B</option>
                                    <option value="Small A">Small A</option>
                                    <option value="Medium B">Medium B</option>
                                    <option value="Medium A">Medium A</option>
                                    <option value="Large B">Large B</option>
                                    <option value="Large A">Large A</option>
                                </select>
                                <span class="field-error">Please select your classification.</span>
                                <span class="field-hint">Caps the size of project (ABC) you're eligible to bid on.</span>
                            </div>
                        </div>
                    </section>

                    <!-- Step 3: Documents -->
                    <section class="step" data-step="3">
                        <h2 class="step-title">Supporting Documents</h2>
                        <p class="step-subtitle">Drag a file onto each box below, or click to browse. All five are required.</p>

                        <div class="doc-required-grid" id="requiredDropzones"></div>

                        <div class="doc-optional-title">Additional documents (optional)</div>
                        <div class="doc-rows" id="docRows"></div>
                        <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another document</button>
                    </section>

                    <!-- Step 4: Review -->
                    <section class="step" data-step="4">
                        <h2 class="step-title">Review &amp; Submit</h2>
                        <p class="step-subtitle">Double-check everything below before sending it to BAC.</p>
                        <div id="reviewContent"></div>
                    </section>

                    <div id="formError" class="alert alert-error" style="display:none;"></div>

                    <div class="step-nav">
                        <button type="button" class="btn btn-ghost" id="stepBackBtn" style="visibility:hidden;">Back</button>
                        <button type="button" class="btn btn-primary" id="stepNextBtn">Next</button>
                        <button type="submit" class="btn btn-primary" id="stepSubmitBtn" style="display:none;">Submit Application</button>
                    </div>
                </form>
            <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="<?= htmlspecialchars(assetUrl('/contractor/assets/js/apply.js')) ?>"></script>
</body>
</html>
