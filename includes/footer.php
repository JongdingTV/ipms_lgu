
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

  <!-- NOTIFICATION PANEL -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
      <span>Notifications</span>
      <button class="notif-clear" id="notifClear">Clear all</button>
    </div>
    <div class="notif-item notif-high">
      <div class="notif-dot"></div>
      <div><p class="notif-msg">Brgy. Health Center is over budget</p><span class="notif-time">Loading live data…</span></div>
    </div>
    <div class="notif-item notif-mid">
      <div class="notif-dot"></div>
      <div><p class="notif-msg">River Dike expense spike detected</p><span class="notif-time">Loading live data…</span></div>
    </div>
    <div class="notif-item notif-low">
      <div class="notif-dot"></div>
      <div><p class="notif-msg">Delayed projects need attention</p><span class="notif-time">Loading live data…</span></div>
    </div>
  </div>

  <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>assets/js/script.js"></script>
</body>
</html>
