
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

  <!-- CONFIRMATION DIALOG — on-brand, Promise-based replacement for the
       native confirm() popup. See assets/js/script.js's showConfirm().
       Classes are prefixed "confirmdlg-", not "confirm-" — the latter
       already belongs to includes/topbar.php's logout/idle-timeout modals,
       which use a different display:none/flex toggle (assets/js/session-guard.js).
       Do not rename these back to "confirm-*". -->
  <div class="confirmdlg-overlay" id="confirmOverlay">
    <div class="confirmdlg-card" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
      <div class="confirmdlg-icon" id="confirmIcon"></div>
      <h3 class="confirmdlg-title" id="confirmTitle"></h3>
      <p class="confirmdlg-message" id="confirmMessage"></p>
      <div class="confirmdlg-actions">
        <button type="button" class="btn-secondary" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn-primary" id="confirmOkBtn">Confirm</button>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/notifications-panel.php'; ?>

  <!-- Leaflet: only the GIS Map page (admin-only) uses this; kept out of the
       shared header.php so no other portal pays for it. -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

  <!-- Shared pin/popup/district-tint design for every project map — see
       assets/js/project-map.js. Loaded after Leaflet itself. -->
  <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('/assets/css/project-map.css')) ?>">
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/project-map.js')) ?>"></script>

  <!-- QR code generation (Project QR Transparency feature) — client-side
       only, same CDN-vendoring pattern as Leaflet above. -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/qrcode-widget.js')) ?>"></script>

  <script src="<?= htmlspecialchars(assetUrl('/assets/js/pagination.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
  <script>window.SIDEBAR_BADGES_PORTAL = 'admin';</script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-toggle.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/project-timeline.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>
