<?php
require_once __DIR__ . '/auth.php';

$BASE_PATH = appUrl('/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= $BASE_PATH ?>assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
  <script>
    window.BASE_PATH = '<?= $BASE_PATH ?>';
    window.CSRF_TOKEN = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>';
  </script>
</head>
<body>
