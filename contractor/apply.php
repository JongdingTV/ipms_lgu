<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../auth/session.php';

if (isLoggedIn()) {
    redirectToRoleDashboard();
}

$success = isset($_GET['submitted']);
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial;
            background: linear-gradient(135deg, #e6f0ff 0%, #eef7ff 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .apply-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }

        .apply-header {
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .apply-header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .apply-header p { color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; }
        .apply-body { padding: 2rem; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
        label { font-weight: 600; color: #333; margin-bottom: 0.4rem; font-size: 0.9rem; }
        .required { color: #e74c3c; }

        input[type="text"], input[type="email"], input[type="file"], textarea, select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2563eb;
        }

        .scope-note { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .doc-checklist { list-style: none; margin: 0 0 1.5rem; padding: 0; display: flex; flex-direction: column; gap: 6px; }
        .doc-checklist li { font-size: 0.88rem; color: #444; line-height: 1.5; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .doc-checklist li strong { color: #1e40af; }

        .doc-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: 0.75rem; }
        .doc-row {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1.4fr auto;
            gap: 8px;
            align-items: center;
            padding: 10px;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
        }
        .doc-row-remove {
            width: 28px; height: 28px;
            border: 1px solid #ddd; border-radius: 6px;
            background: white; color: #666;
            font-size: 1rem; line-height: 1; cursor: pointer;
        }
        .doc-row-remove:hover { background: #fee2e2; border-color: #e74c3c; color: #e74c3c; }
        .doc-add-btn {
            align-self: flex-start;
            padding: 7px 14px;
            border: 1px dashed #2563eb;
            border-radius: 6px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }
        .doc-add-btn:hover { background: #dbe8ff; }
        @media (max-width: 640px) { .doc-row { grid-template-columns: 1fr; } }

        button[type="submit"] {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(90deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
        }
        button[type="submit"]:hover { box-shadow: 0 10px 20px rgba(37,99,235,0.25); }

        .back-link { margin-bottom: 1.5rem; }
        .back-link a { color: #2563eb; text-decoration: none; font-size: 0.9rem; }
        .back-link a:hover { color: #1e40af; }
    </style>
</head>
<body>
    <div class="apply-container">
        <div class="apply-header">
            <h1>Become a Contractor</h1>
            <p>Apply to join the LGU's roster of accredited infrastructure contractors</p>
        </div>

        <div class="apply-body">
            <div class="back-link">
                <a href="<?= htmlspecialchars(appUrl('/landing.php')) ?>">&larr; Back to Home</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    Your application has been submitted. The Bids and Awards Committee (BAC) will review your
                    documents — you'll receive an email once a decision is made.
                </div>
            <?php endif; ?>

            <p class="scope-note">
                Fill out your company details below, then attach each of these as a separate supporting document.
                Your application will be reviewed by BAC before an account is created for you.
            </p>
            <ul class="doc-checklist">
                <li><strong>DTI or SEC Registration Certificate</strong> — proof your business is legally registered (DTI for sole proprietorship, SEC for corporation/partnership)</li>
                <li><strong>Mayor's / Business Permit</strong> — current year</li>
                <li><strong>Tax Clearance Certificate</strong> — from the BIR, for bidding purposes</li>
                <li><strong>PCAB License</strong> — must match the license number and classification you enter below</li>
                <li><strong>Audited Financial Statement</strong> — most recent fiscal year, BIR-stamped</li>
            </ul>
            <p class="scope-note">Additional documents (project portfolio, etc.) are optional but strengthen your application.</p>

            <form id="applyForm" enctype="multipart/form-data"
                  data-api-url="<?= htmlspecialchars(appUrl('/contractor/api/apply.php')) ?>"
                  data-redirect-url="<?= htmlspecialchars(appUrl('/contractor/apply.php')) ?>?submitted=1">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Company Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Business Address</label>
                    <textarea id="address" name="address" rows="2"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="pcab_license_no">PCAB License Number <span class="required">*</span></label>
                        <input type="text" id="pcab_license_no" name="pcab_license_no" required>
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
                    </div>
                </div>

                <div class="section-title">Supporting Documents <span class="required">*</span></div>
                <div class="doc-rows" id="docRows"></div>
                <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another document</button>

                <div id="formError" class="alert alert-error" style="display:none;margin-top:1rem;"></div>

                <button type="submit">Submit Application</button>
            </form>
        </div>
    </div>

    <script src="<?= htmlspecialchars(assetUrl('/contractor/assets/js/apply.js')) ?>"></script>
</body>
</html>
