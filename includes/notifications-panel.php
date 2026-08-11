<!-- Shared notification panel markup — populated live by assets/js/notifications.js.
     The bell button itself lives in includes/topbar.php and is unchanged.
     Set $notifPanelTitle before including this file for a portal-specific
     header (e.g. "BAC Updates"); defaults to "Notifications". -->
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-header">
    <span><?= htmlspecialchars($notifPanelTitle ?? 'Notifications') ?></span>
    <button class="notif-clear" id="notifClear" type="button">Clear all</button>
  </div>
  <!-- Automated Reminders — same panel, same list, just a filtered view
       (assets/js/notifications.js) — not a separate portal/component. -->
  <div class="notif-tabs" id="notifTabs">
    <button class="notif-tab active" data-tab="all" type="button">All</button>
    <button class="notif-tab" data-tab="reminders" type="button">Reminders</button>
  </div>
  <div id="notifList">
    <p class="empty-state" style="padding:16px;">Loading…</p>
  </div>
</div>
