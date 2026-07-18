
  <!-- MODAL -->
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal" id="modal">
      <div class="modal-header">
        <h3 id="modalTitle">Details</h3>
        <button class="modal-close" id="modalClose">&times;</button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <?php include __DIR__ . '/notifications-panel.php'; ?>

  <!-- Leaflet: only the GIS Map page (admin-only) uses this; kept out of the
       shared header.php so no other portal pays for it. -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

  <script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
  <script>window.SIDEBAR_BADGES_PORTAL = 'admin';</script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>
