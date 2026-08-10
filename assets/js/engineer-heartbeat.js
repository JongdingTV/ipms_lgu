/* Presence heartbeat — engineer portal only. Pings users.last_seen_at every
   45s (see api/engineer-status.php action=heartbeat) so the Admin/Head
   Office Engineer Live Status widget can show Online/Away/Offline without
   any GPS or continuous location tracking. Separate from
   engineer-status-widget.js so engineers never download the panel code. */
(function () {
  var POLL_INTERVAL_MS = 45000;
  var CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

  function ping() {
    fetch(window.ENGINEER_STATUS_ENDPOINT + '?action=heartbeat', {
      method: 'POST',
      headers: CSRF_HEADERS,
    }).catch(function () { /* best-effort — a missed beat just lets presence age out */ });
  }

  ping();
  setInterval(ping, POLL_INTERVAL_MS);
})();
