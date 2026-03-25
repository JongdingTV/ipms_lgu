<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LGU Infrastructure Project Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>assets/css/style.css" />
  
  <!-- Set global BASE_PATH from PHP -->
  <script>
    <?php
      // Detect the project root from REQUEST_URI
      // REQUEST_URI might be: /ipms.lgu/admin/index.php
      $uri = $_SERVER['REQUEST_URI'];
      $parts = array_filter(explode('/', $uri));
      $parts_arr = array_values($parts); // Re-index after filter
      
      // Assume first non-empty part is project folder
      $projectFolder = isset($parts_arr[0]) ? $parts_arr[0] : '';
      $basePath = '/' . $projectFolder . '/';
      
      echo "window.BASE_PATH = '" . addslashes($basePath) . "';";
      echo "console.log('[PHP] BASE_PATH set to:', window.BASE_PATH);";
    ?>
  </script>
</head>
<body>
