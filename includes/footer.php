
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

  <script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
  <script src="<?= htmlspecialchars(assetUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>
