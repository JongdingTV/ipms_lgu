<!-- Shared notification panel markup — populated live by assets/js/notifications.js.
     The bell button itself lives in includes/topbar.php and is unchanged.
     Set $notifPanelTitle before including this file for a portal-specific
     header (e.g. "BAC Updates"); defaults to "Notifications". -->
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-header">
    <span><?= htmlspecialchars($notifPanelTitle ?? 'Notifications') ?></span>
    <button class="notif-clear" id="notifClear" type="button">Clear all</button>
  </div>
  <div id="notifList">
    <p class="empty-state" style="padding:16px;">Loading…</p>
  </div>
</div>
