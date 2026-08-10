<?php
// ============================================================
// transparency.php — public, unauthenticated project transparency page.
// This is what every project's QR code (assets/js/qrcode-widget.js) encodes:
// {appUrl}/transparency.php?code={project_code}. No login required — the
// whole point of a QR code on a project's public signage is that a citizen
// can scan it and see the project immediately, not be routed through a
// login wall (that's citizen/api/project-details.php's job, for the richer
// authenticated dashboard view).
//
// Deliberately opens its own PDO connection rather than includes/db.php's
// getDB() — same reasoning as landing.php: getDB() prints a JSON error body
// and exit()s on failure, which would corrupt this HTML page.
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/workflow.php';

// Same publicly-visible status gate citizen/api/project-details.php already
// uses — a project still in internal draft/review has no business being
// reachable here even if someone guesses/enumerates a project_code.
const TRANSPARENCY_VISIBLE_STATUSES = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];

$code = trim((string) ($_GET['code'] ?? ''));
$project = null;
$photos = [];
$engineerName = null;
$dbError = false;
$ratingSummary = ['count' => 0, 'average' => 0];
$ratingDistribution = [];
$recentReviews = [];
$ratingEligible = false;

if ($code !== '') {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2,
        ]);

        $placeholders = implode(',', array_fill(0, count(TRANSPARENCY_VISIBLE_STATUSES), '?'));
        $stmt = $db->prepare("
            SELECT p.id, p.project_code, p.name, p.description, p.location, p.budget, p.status,
                   p.start_date, p.end_date, p.progress, p.latitude, p.longitude,
                   c.name AS contractor_name
            FROM projects p
            LEFT JOIN contractors c ON c.id = p.contractor_id
            WHERE p.project_code = ? AND p.status IN ($placeholders)
            LIMIT 1
        ");
        $stmt->execute(array_merge([$code], TRANSPARENCY_VISIBLE_STATUSES));
        $project = $stmt->fetch() ?: null;

        if ($project) {
            $engStmt = $db->prepare("
                SELECT u.full_name FROM engineer_project_assignments epa
                INNER JOIN users u ON u.id = epa.engineer_id
                WHERE epa.project_id = ? AND epa.status = 'active'
                ORDER BY epa.assigned_at DESC LIMIT 1
            ");
            $engStmt->execute([$project['id']]);
            $engineerName = $engStmt->fetchColumn() ?: null;

            $photoStmt = $db->prepare("
                SELECT title, caption, file_path, created_at
                FROM engineer_progress_photos
                WHERE project_id = ?
                ORDER BY created_at DESC LIMIT 6
            ");
            $photoStmt->execute([$project['id']]);
            $photos = $photoStmt->fetchAll();

            projectRatingsEnsureSchema($db);
            $ratingEligible = in_array($project['status'], projectRatingEligibleStatuses(), true);

            $ratingStmt = $db->prepare("SELECT COUNT(*) AS count, COALESCE(AVG(rating), 0) AS average FROM project_ratings WHERE project_id = ? AND status = 'approved'");
            $ratingStmt->execute([$project['id']]);
            $rr = $ratingStmt->fetch();
            $ratingSummary = ['count' => (int) $rr['count'], 'average' => round((float) $rr['average'], 1)];

            $distStmt = $db->prepare("SELECT rating, COUNT(*) AS c FROM project_ratings WHERE project_id = ? AND status = 'approved' GROUP BY rating");
            $distStmt->execute([$project['id']]);
            $distRaw = $distStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            for ($star = 5; $star >= 1; $star--) {
                $c = (int) ($distRaw[$star] ?? 0);
                $ratingDistribution[] = [
                    'star' => $star,
                    'count' => $c,
                    'percent' => $ratingSummary['count'] > 0 ? (int) round($c / $ratingSummary['count'] * 100) : 0,
                ];
            }

            $reviewStmt = $db->prepare("
                SELECT r.rating, r.comment, r.created_at, CONCAT(c.first_name, ' ', LEFT(c.last_name, 1), '.') AS citizen_name
                FROM project_ratings r INNER JOIN citizens c ON c.id = r.citizen_id
                WHERE r.project_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC LIMIT 10
            ");
            $reviewStmt->execute([$project['id']]);
            $recentReviews = $reviewStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $dbError = true;
    }
}

function transparencyMoney($value): string
{
    return '₱' . number_format((float) $value, 2);
}

function transparencyStatusLabel(string $status): string
{
    $labels = [
        'approved' => 'Approved', 'bidding' => 'Bidding', 'awarded' => 'Awarded',
        'assigned' => 'Contractor Assigned', 'active' => 'Under Construction',
        'delayed' => 'Delayed', 'on_hold' => 'On Hold',
        'completion_inspection' => 'Completion Inspection', 'completed' => 'Completed',
        'turnover' => 'Completed & Turned Over',
    ];
    return $labels[$status] ?? ucfirst($status);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $project ? htmlspecialchars($project['name']) . ' — ' : '' ?>Project Transparency — Quezon City IPMS</title>
<link rel="icon" href="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php if ($project && $project['latitude'] !== null && $project['longitude'] !== null): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<!-- Shared pin design (assets/js/project-map.js's .proj-map-pin classes) —
     just the CSS here; the marker itself is hand-built below to match it,
     since this single-marker public page doesn't need the whole JS module. -->
<link rel="stylesheet" href="<?= htmlspecialchars(appUrl('/assets/css/project-map.css')) ?>">
<?php endif; ?>
<style>
  :root {
    --blue: #2563eb; --green: #16a34a; --orange: #f97316; --red: #dc2626;
    --text: #1e293b; --muted: #64748b; --border: #e2e8f0; --bg: #f8fafc;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); }
  .t-topbar { background: #0f172a; color: #fff; padding: 14px 20px; display: flex; align-items: center; gap: 10px; }
  .t-topbar img { height: 28px; }
  .t-topbar strong { font-size: .95rem; }
  .t-topbar span { font-size: .75rem; color: #94a3b8; margin-left: auto; }
  .t-wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 60px; }
  .t-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 22px; margin-bottom: 18px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
  .t-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
  h1 { font-size: 1.5rem; margin: 10px 0 4px; }
  .t-code { font-family: monospace; color: var(--muted); font-size: .8rem; }
  .t-desc { color: var(--muted); font-size: .9rem; line-height: 1.6; margin-top: 10px; }
  .t-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 16px; }
  .t-stat span { display: block; font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
  .t-stat strong { font-size: 1rem; }
  .t-progress-bar { background: #f1f5f9; border-radius: 20px; height: 12px; overflow: hidden; margin-top: 8px; }
  .t-progress-fill { height: 100%; border-radius: 20px; }
  .t-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 12px; }
  .t-photos img { width: 100%; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }
  .t-empty { color: var(--muted); font-size: .85rem; text-align: center; padding: 20px 0; }
  h2 { font-size: 1rem; margin: 0 0 4px; }
  .t-footer { text-align: center; color: var(--muted); font-size: .75rem; margin-top: 24px; }
  #tMap { height: 240px; border-radius: 10px; overflow: hidden; margin-top: 10px; border: 1px solid var(--border); }
  .t-stars { color: #f59e0b; font-size: 1.1rem; letter-spacing: 1px; }
  .t-rating-head { display: flex; align-items: center; gap: 14px; }
  .t-rating-score { font-size: 2.2rem; font-weight: 800; }
  .t-rating-bars { margin-top: 14px; }
  .t-rating-bar-row { display: flex; align-items: center; gap: 8px; font-size: .75rem; color: var(--muted); margin: 4px 0; }
  .t-rating-bar-track { flex: 1; background: #f1f5f9; border-radius: 20px; height: 8px; overflow: hidden; }
  .t-rating-bar-fill { height: 100%; background: #f59e0b; border-radius: 20px; }
  .t-review { padding: 12px 0; border-top: 1px solid var(--border); }
  .t-review:first-child { border-top: none; }
  .t-review-head { display: flex; align-items: center; gap: 8px; font-size: .82rem; }
  .t-review-comment { font-size: .85rem; color: var(--muted); margin-top: 4px; line-height: 1.5; }
  .t-rating-cta { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); text-align: center; }
  .t-cta-btn { display: inline-block; background: var(--blue); color: #fff; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: .85rem; text-decoration: none; margin-top: 8px; }
</style>
</head>
<body>
  <div class="t-topbar">
    <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="">
    <strong>Quezon City IPMS</strong>
    <span>Public Project Transparency</span>
  </div>

  <div class="t-wrap">
    <?php if ($dbError): ?>
      <div class="t-card"><p class="t-empty">This page is temporarily unavailable. Please try again shortly.</p></div>
    <?php elseif (!$project): ?>
      <div class="t-card">
        <p class="t-empty">This project isn't publicly available yet — it may still be in internal review, or the code scanned doesn't match any project.</p>
      </div>
    <?php else: ?>
      <?php
        $progress = (int) $project['progress'];
        $barColor = $progress >= 70 ? 'var(--green)' : ($progress >= 40 ? 'var(--orange)' : 'var(--red)');
      ?>
      <div class="t-card">
        <span class="t-badge" style="background:<?= $barColor ?>22;color:<?= $barColor ?>;"><?= htmlspecialchars(transparencyStatusLabel($project['status'])) ?></span>
        <h1><?= htmlspecialchars($project['name']) ?></h1>
        <p class="t-code"><?= htmlspecialchars($project['project_code']) ?><?= $project['location'] ? ' · ' . htmlspecialchars($project['location']) : '' ?></p>
        <?php if ($project['description']): ?><p class="t-desc"><?= nl2br(htmlspecialchars($project['description'])) ?></p><?php endif; ?>

        <div class="t-grid">
          <div class="t-stat"><span>Budget</span><strong><?= transparencyMoney($project['budget']) ?></strong></div>
          <div class="t-stat"><span>Contractor</span><strong><?= $project['contractor_name'] ? htmlspecialchars($project['contractor_name']) : 'Not yet awarded' ?></strong></div>
          <div class="t-stat"><span>Engineer</span><strong><?= $engineerName ? htmlspecialchars($engineerName) : 'Not yet assigned' ?></strong></div>
          <div class="t-stat"><span>Timeline</span><strong><?= htmlspecialchars($project['start_date']) ?> – <?= htmlspecialchars($project['end_date']) ?></strong></div>
        </div>

        <div style="margin-top:16px;">
          <span class="t-stat" style="display:block;"><span>Completion Percentage</span></span>
          <div class="t-progress-bar"><div class="t-progress-fill" style="width:<?= $progress ?>%;background:<?= $barColor ?>;"></div></div>
          <p style="font-size:.8rem;color:var(--muted);margin-top:4px;"><?= $progress ?>% complete</p>
        </div>
      </div>

      <div class="t-card">
        <h2>Latest Photos</h2>
        <?php if ($photos): ?>
          <div class="t-photos">
            <?php foreach ($photos as $ph): ?>
              <a href="<?= htmlspecialchars(appUrl('/' . ltrim($ph['file_path'], '/'))) ?>" target="_blank" rel="noopener">
                <img src="<?= htmlspecialchars(appUrl('/' . ltrim($ph['file_path'], '/'))) ?>" alt="<?= htmlspecialchars($ph['title'] ?? 'Progress photo') ?>" loading="lazy">
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="t-empty">No photos posted yet.</p>
        <?php endif; ?>
      </div>

      <div class="t-card">
        <h2>Citizen Rating &amp; Reviews</h2>
        <?php if ($ratingSummary['count'] > 0): ?>
          <div class="t-rating-head">
            <span class="t-rating-score"><?= htmlspecialchars((string) $ratingSummary['average']) ?></span>
            <div>
              <div class="t-stars"><?= str_repeat('★', (int) round($ratingSummary['average'])) . str_repeat('☆', 5 - (int) round($ratingSummary['average'])) ?></div>
              <span style="font-size:.8rem;color:var(--muted);"><?= $ratingSummary['average'] ?> out of 5 based on <?= $ratingSummary['count'] ?> citizen review<?= $ratingSummary['count'] === 1 ? '' : 's' ?>.</span>
            </div>
          </div>
          <div class="t-rating-bars">
            <?php foreach ($ratingDistribution as $d): ?>
              <div class="t-rating-bar-row">
                <span style="width:34px;"><?= $d['star'] ?>★</span>
                <div class="t-rating-bar-track"><div class="t-rating-bar-fill" style="width:<?= $d['percent'] ?>%;"></div></div>
                <span style="width:32px;text-align:right;"><?= $d['percent'] ?>%</span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($recentReviews): ?>
            <div style="margin-top:16px;">
              <?php foreach ($recentReviews as $r): ?>
                <div class="t-review">
                  <div class="t-review-head">
                    <span class="t-stars" style="font-size:.85rem;"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
                    <strong><?= htmlspecialchars($r['citizen_name']) ?></strong>
                    <span style="color:var(--muted);font-size:.75rem;"><?= htmlspecialchars(date('F j, Y', strtotime($r['created_at']))) ?></span>
                  </div>
                  <?php if ($r['comment']): ?><p class="t-review-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></p><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="t-empty">No citizen reviews yet.</p>
        <?php endif; ?>

        <?php if ($ratingEligible): ?>
          <div class="t-rating-cta">
            <p style="margin:0;font-size:.85rem;color:var(--muted);">Have you seen this project up close?</p>
            <a class="t-cta-btn" href="<?= htmlspecialchars(appUrl('/citizen/login.php')) ?>">Sign in to rate this project</a>
          </div>
        <?php else: ?>
          <p class="t-empty" style="padding-top:14px;border-top:1px solid var(--border);margin-top:14px;">Ratings open once this project is actively underway or completed.</p>
        <?php endif; ?>
      </div>

      <?php if ($project['latitude'] !== null && $project['longitude'] !== null): ?>
      <div class="t-card">
        <h2>Project Location</h2>
        <div id="tMap"></div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <p class="t-footer">This page is generated automatically from the Quezon City Infrastructure Project Management System and updates as the project progresses.</p>
  </div>

  <?php if ($project && $project['latitude'] !== null && $project['longitude'] !== null):
    // Same status→{color,icon} pairing as window.ProjectMap.STATUS
    // (assets/js/project-map.js) — duplicated here in PHP since this
    // single-marker public page doesn't load the shared JS module.
    $tPinIcons = [
        'completed' => ['#22c55e', '<path d="M5 12.5l4.5 4.5L19 7"/>'],
        'turnover' => ['#16a34a', '<path d="M5 12.5l4.5 4.5L19 7"/>'],
        'delayed' => ['#ef4444', '<path d="M12 4l9 16H3l9-16z"/><path d="M12 10v4"/>'],
        'cancelled' => ['#94a3b8', '<path d="M6 6l12 12M18 6L6 18"/>'],
    ];
    [$tPinColor, $tPinPath] = $tPinIcons[$project['status']] ?? ['#3b82f6', '<path d="M12 3l5 15H7l5-15z"/><path d="M5 21h14"/><path d="M9 13h6"/>'];
  ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script>
    var map = L.map('tMap', { scrollWheelZoom: false }).setView([<?= (float) $project['latitude'] ?>, <?= (float) $project['longitude'] ?>], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    var tPinIcon = L.divIcon({
      className: 'proj-map-pin',
      html: '<span class="pin-pulse" style="background:<?= $tPinColor ?>"></span>' +
        '<span class="pin-body" style="background:<?= $tPinColor ?>"><span class="pin-icon">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $tPinPath ?></svg>' +
        '</span></span>',
      iconSize: [34, 44], iconAnchor: [17, 40],
    });
    L.marker([<?= (float) $project['latitude'] ?>, <?= (float) $project['longitude'] ?>], { icon: tPinIcon }).addTo(map);
  </script>
  <?php endif; ?>
</body>
</html>
