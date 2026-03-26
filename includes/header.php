<?php
// Include authentication check
require_once __DIR__ . '/auth.php';

// Determine base path more reliably
$current_dir = dirname($_SERVER['PHP_SELF']);
$base_path = '/ipms.lgu/'; // Direct path to project root

// Override if needed based on actual directory structure
if (strpos($current_dir, '/admin') !== false || strpos($current_dir, '/admin/') !== false) {
    $BASE_PATH = '/ipms.lgu/';
} else {
    $BASE_PATH = '/ipms.lgu/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LGU Infrastructure Dashboard</title>
  <link rel="stylesheet" href="<?= $BASE_PATH ?>assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
  <script>
    // Make BASE_PATH available to JS
    window.BASE_PATH = '<?= $BASE_PATH ?>';
  </script>
</head>
<body>
