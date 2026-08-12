/* Engineer Live Status widget — floating launcher + right-side slide-in panel
   for Admin/Head Office. Expects window.ENGINEER_STATUS_ENDPOINT (set by
   includes/topbar.php). Builds its own DOM like assets/js/chatbot-widget.js —
   the host page only needs the CSS link + this script tag. Polls in the
   background every 45s (same convention as assets/js/sidebar-badges.js) so
   the launcher's presence/alert dots stay fresh even while the panel is closed. */
(function () {
  var POLL_INTERVAL_MS = 45000;
  var CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

  var PRESENCE_LABELS = { online: 'Online', away: 'Away', offline: 'Offline' };
  var WORK_LABELS = {
    available: 'Available',
    on_site: 'On Site',
    on_inspection: 'On Inspection',
    field_assignment: 'Field Assignment',
    busy: 'Busy',
    off_duty: 'Off Duty',
  };
  var SUMMARY_CHIPS = [
    { key: 'online', label: 'Online', color: '#4ade80' },
    { key: 'on_site', label: 'On Site', color: '#60a5fa' },
    { key: 'on_inspection', label: 'Inspection', color: '#fb923c' },
  ];

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function formatTime(value) {
    if (!value) return null;
    var d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return null;
    return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }

  var MINIMIZED_KEY = 'engStatusWidgetMinimized';

  function buildWidget() {
    var launcher = document.createElement('button');
    launcher.type = 'button';
    launcher.className = 'eng-status-launcher';
    launcher.setAttribute('aria-label', 'Open Engineering Team status');
    launcher.innerHTML =
      '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>' +
      '<span class="eng-status-presence-dot"></span>' +
      '<span class="eng-status-alert-dot"></span>' +
      '<span class="eng-status-hide-btn" role="button" aria-label="Tuck away to the corner" title="Tuck away to the corner">&times;</span>';

    var panel = document.createElement('div');
    panel.className = 'eng-status-panel';
    panel.innerHTML =
      '<div class="eng-status-head">' +
      '  <div class="eng-status-head-row">' +
      '    <div class="eng-status-head-title">Engineering Team</div>' +
      '    <button type="button" class="eng-status-head-close" aria-label="Close">&times;</button>' +
      '  </div>' +
      '  <div class="eng-status-summary" id="engStatusSummary"></div>' +
      '</div>' +
      '<div class="eng-status-body" id="engStatusBody"><p class="eng-status-empty">Loading engineers&hellip;</p></div>';

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    var body = panel.querySelector('#engStatusBody');
    var summaryEl = panel.querySelector('#engStatusSummary');
    var closeBtn = panel.querySelector('.eng-status-head-close');
    var presenceDot = launcher.querySelector('.eng-status-presence-dot');
    var hideBtn = launcher.querySelector('.eng-status-hide-btn');

    var lastData = null;
    var expandedId = null;

    function togglePanel() {
      var open = panel.classList.toggle('is-open');
      if (open) render();
    }
    function closePanel() { panel.classList.remove('is-open'); }

    function isMinimized() { return launcher.classList.contains('is-minimized'); }
    function minimize() {
      launcher.classList.add('is-minimized');
      closePanel();
      try { localStorage.setItem(MINIMIZED_KEY, '1'); } catch (e) { /* private mode, etc — just won't persist */ }
    }
    function restore() {
      launcher.classList.remove('is-minimized');
      try { localStorage.removeItem(MINIMIZED_KEY); } catch (e) { /* ignore */ }
    }
    try {
      if (localStorage.getItem(MINIMIZED_KEY) === '1') minimize();
    } catch (e) { /* ignore */ }

    launcher.addEventListener('click', function (e) {
      if (e.target.closest('.eng-status-hide-btn')) return; // own handler below
      if (isMinimized()) { restore(); return; } // first click just brings it back
      togglePanel();
    });
    hideBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      minimize();
    });
    closeBtn.addEventListener('click', closePanel);

    function renderSummary(summary) {
      summaryEl.innerHTML = SUMMARY_CHIPS.map(function (chip) {
        return '<span><i style="width:8px;height:8px;border-radius:50%;background:' + chip.color + ';display:inline-block;"></i>' +
          (summary[chip.key] || 0) + ' ' + chip.label + '</span>';
      }).join('');
    }

    function projectDetailRows(engineer) {
      if (!engineer.project) return '';
      var p = engineer.project;
      return (
        '<div class="eng-status-detail-row"><span>Project</span><span>' + escapeHtml(p.name) + '</span></div>' +
        (p.code ? '<div class="eng-status-detail-row"><span>Project ID</span><span>' + escapeHtml(p.code) + '</span></div>' : '') +
        (p.district ? '<div class="eng-status-detail-row"><span>District</span><span>' + escapeHtml(p.district) + '</span></div>' : '') +
        (p.location ? '<div class="eng-status-detail-row"><span>Site Location</span><span>' + escapeHtml(p.location) + '</span></div>' : '')
      );
    }

    function cardHtml(engineer) {
      var expanded = expandedId === engineer.id;
      var startedLabel = formatTime(engineer.started_at);
      return (
        '<div class="eng-status-card' + (expanded ? ' is-expanded' : '') + (engineer.needs_attention ? ' needs-attention' : '') + '" data-id="' + engineer.id + '">' +
        '  <div class="eng-status-card-top">' +
        '    <div class="eng-status-avatar">' + escapeHtml(engineer.initial) +
        '      <span class="eng-status-dot presence-' + engineer.presence + '"></span>' +
        '    </div>' +
        '    <div class="eng-status-card-info">' +
        '      <div class="eng-status-name">' + escapeHtml(engineer.name) + '</div>' +
        '      <div class="eng-status-work-line"><span class="eng-status-work-dot work-' + engineer.work_status + '"></span>' +
               (WORK_LABELS[engineer.work_status] || engineer.work_status) +
               (engineer.project ? ' &middot; ' + escapeHtml(engineer.project.name) : '') +
        '      </div>' +
        '    </div>' +
        '    <svg class="eng-status-card-caret" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>' +
        '  </div>' +
        '  <div class="eng-status-detail">' +
        '    <div class="eng-status-detail-row"><span>Presence</span><span>' + PRESENCE_LABELS[engineer.presence] + '</span></div>' +
             (engineer.activity ? '<div class="eng-status-detail-row"><span>Activity</span><span>' + escapeHtml(engineer.activity) + '</span></div>' : '') +
             (startedLabel ? '<div class="eng-status-detail-row"><span>Started</span><span>' + startedLabel + '</span></div>' : '') +
             projectDetailRows(engineer) +
             (engineer.project ? '<button type="button" class="eng-status-view-btn" data-view-project="' + engineer.project.id + '">View Project</button>' : '') +
        '  </div>' +
        '</div>'
      );
    }

    function render() {
      if (!lastData) return;
      renderSummary(lastData.summary);

      // The whole list is rebuilt below on every render — including the
      // background poll every 45s while the panel sits open — so without
      // this, reading down a long list yanks the scroll back to the top
      // mid-read. expandedId already persists a card's open/closed state
      // across re-renders the same way; this just does the same for scroll.
      var scrollTop = body.scrollTop;

      if (!lastData.engineers.length) {
        body.innerHTML = '<p class="eng-status-empty">No engineers on record yet.</p>';
        return;
      }

      body.innerHTML = lastData.engineers.map(cardHtml).join('');
      body.scrollTop = scrollTop;

      body.querySelectorAll('.eng-status-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
          if (e.target.closest('[data-view-project]')) return;
          var id = Number(card.dataset.id);
          expandedId = expandedId === id ? null : id;
          render();
        });
      });
      body.querySelectorAll('[data-view-project]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var id = Number(btn.dataset.viewProject);
          closePanel();
          if (typeof window.openProjectModal === 'function') {
            window.openProjectModal(id);
          } else if (typeof window.hopeOpenProjectModal === 'function') {
            window.hopeOpenProjectModal(id);
          }
        });
      });
    }

    function updateLauncher(data) {
      presenceDot.classList.toggle('has-online', (data.summary.online || 0) > 0);
      launcher.classList.toggle('has-alerts', !!data.has_alerts);
    }

    function fetchList() {
      fetch(window.ENGINEER_STATUS_ENDPOINT + '?action=list', { headers: CSRF_HEADERS })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.error) return;
          lastData = data;
          updateLauncher(data);
          if (panel.classList.contains('is-open')) render();
        })
        .catch(function () { /* badges are convenience — fail silently, same as sidebar-badges.js */ });
    }

    fetchList();
    setInterval(fetchList, POLL_INTERVAL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildWidget);
  } else {
    buildWidget();
  }
})();
