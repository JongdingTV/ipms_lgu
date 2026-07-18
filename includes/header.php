<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Settings.php';

$BASE_PATH = appUrl('/');
$extraStylesheets = $extraStylesheets ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(getSetting('site_name', APP_NAME)) ?></title>
  <!-- Applied before any stylesheet loads, so the correct theme paints first
       try — no flash of the wrong theme while localStorage is read later. -->
  <script>
    (function () {
      try {
        if (localStorage.getItem('theme') === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
    })();
  </script>
  <!-- style.css declares --font: 'Plus Jakarta Sans' and --mono: 'DM Mono';
       without this link they silently fall back to plain system fonts.
       Sora is the citizen portal's display face for headings. -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Poppins: face of the CIMMS maintenance-request replica on the citizen dashboard. -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&family=DM+Mono:wght@400;500&family=Poppins:wght@300;400;500;600;700&display=swap">
  <link rel="icon" href="<?= htmlspecialchars($BASE_PATH) ?>assets/img/ipms-icon.png" type="image/png">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($BASE_PATH) ?>assets/img/ipms-icon.png">
  <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('/assets/css/style.css')) ?>">
  <?php foreach ($extraStylesheets as $stylesheet): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('/' . ltrim($stylesheet, '/'))) ?>">
  <?php endforeach; ?>
  <!-- Only the UMD build works as a plain classic script — dist/chart.js (and its
       chart.min.js minified alias) is an ES module and throws a SyntaxError here. -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/scroll-reveal.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/theme-toggle.js')) ?>"></script>
  <script>
    window.BASE_PATH = '<?= $BASE_PATH ?>';
    window.CSRF_TOKEN = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>';
    window.CURRENT_USER_ROLE = '<?= htmlspecialchars((string) (currentUser()['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?>';
  </script>
</head>
<body>
<?php include __DIR__ . '/global-search-modal.php'; ?>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/global-search.js')) ?>"></script>
