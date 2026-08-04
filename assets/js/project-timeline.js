/* ============================================================
   Project Activity Timeline — shared, read-only, drop-in widget
   used by Admin/Engineer/HOPE/Contractor project detail modals.

   Self-contained on purpose: each portal has its own JS globals
   (escape/date helpers, role labels) under different names, so this
   file brings its own rather than depending on any of them. Data
   comes from api/project-timeline.php, which reads bac_procurement_logs
   — already populated by projectWorkflowLog() across the whole
   project lifecycle. Nothing here writes back; there is no edit or
   delete affordance anywhere in this file.
   ============================================================ */
(function () {
  'use strict';

  var PT_TONES = {
    success:     { bg: '#dcfce7', fg: '#15803d' },
    warning:     { bg: '#ffedd5', fg: '#c2410c' },
    danger:      { bg: '#fee2e2', fg: '#b91c1c' },
    info:        { bg: '#dbeafe', fg: '#1d4ed8' },
    procurement: { bg: '#ede9fe', fg: '#6d28d9' },
    teal:        { bg: '#ccfbf1', fg: '#0f766e' },
    amber:       { bg: '#fef3c7', fg: '#92400e' },
    dark:        { bg: '#f0fdf4', fg: '#166534' },
    gray:        { bg: '#f1f5f9', fg: '#475569' },
  };

  var PT_ICONS = {
    success: '✓', warning: '↺', danger: '✕', info: 'ℹ', procurement: '📋',
    teal: '🚧', amber: '₱', dark: '📦', gray: '•',
  };

  // Keyed on the exact action strings projectWorkflowLog() writes today
  // (includes/workflow.php, api/projects.php, api/workflow.php,
  // engineer/api/portal.php, contractor/api/portal.php, bac/api/portal.php,
  // hope/api/portal.php). Anything not listed here falls back to a
  // Title-Cased render of the raw action instead of rendering blank.
  var PT_EVENTS = {
    'Project registered':              { title: 'Project Registered', tone: 'info' },
    'Project updated':                 { title: 'Project Updated', tone: 'gray' },
    'Project approved':                { title: 'Project Approved', tone: 'success' },
    'Project returned':                { title: 'Project Returned', tone: 'warning' },
    'Project rejected':                { title: 'Project Rejected', tone: 'danger' },
    'Engineering review: endorsed':    { title: 'Engineering Review: Endorsed', tone: 'success' },
    'Engineering review: returned':    { title: 'Engineering Review: Returned', tone: 'warning' },
    'Engineering review: rejected':    { title: 'Engineering Review: Rejected', tone: 'danger' },
    'Bidding notice posted':           { title: 'Bidding Opened', tone: 'procurement' },
    'Contractor bid recorded':         { title: 'Bid Submitted', tone: 'procurement' },
    'Bid technical score set':         { title: 'Bid Scored', tone: 'procurement' },
    'Award recommendation sent':       { title: 'Contractor Recommended', tone: 'teal' },
    'Contract award approved':        { title: 'Contractor Assigned', tone: 'success' },
    'Contract award returned':         { title: 'Contract Award Returned', tone: 'warning' },
    'Contract award rejected':         { title: 'Contract Award Rejected', tone: 'danger' },
    'Notice to Proceed issued':        { title: 'Construction Started', tone: 'teal' },
    'Progress report submitted':       { title: 'Progress Report Submitted', tone: 'info' },
    'Inspection recorded':             { title: 'Inspection Completed', tone: 'procurement' },
    'Completion inspection requested': { title: 'Completion Inspection Scheduled', tone: 'procurement' },
    'Completion inspection: accepted': { title: 'Completion Inspection Passed', tone: 'success' },
    'Completion inspection: returned': { title: 'Completion Inspection Returned', tone: 'warning' },
    'Payment requested':               { title: 'Payment Requested', tone: 'amber' },
    'Payment review: under_review':    { title: 'Payment Under Review', tone: 'amber' },
    'Payment review: approved':        { title: 'Payment Approved', tone: 'success' },
    'Payment review: rejected':        { title: 'Payment Rejected', tone: 'danger' },
    'Payment marked as paid':          { title: 'Payment Released', tone: 'success' },
    'Contractor assignment updated':   { title: 'Contractor Assignment Updated', tone: 'info' },
    'Engineer assignment updated':     { title: 'Engineer Assignment Updated', tone: 'info' },
    'Project turned over':             { title: 'Project Archived (Turned Over)', tone: 'dark' },
    'Project deletion requested':      { title: 'Deletion Requested', tone: 'danger' },
    'Project deletion approved':       { title: 'Project Deleted', tone: 'danger' },
    'Project deletion rejected':       { title: 'Deletion Request Rejected', tone: 'gray' },
    // Stub only: no workflow in this codebase produces this action today.
    // Kept so the display is ready the moment a variation-order feature
    // (its own table/status/approval steps) is built on top of this log.
    'Variation order approved':        { title: 'Variation Order Approved', tone: 'amber' },
  };

  // 'Project status updated' is the one generic action that covers many
  // different transitions; its details text carries "... changed from X to
  // Y.", so the friendlier title comes from the destination status instead
  // of the action string. Status keys mirror projectWorkflowStatuses()
  // (includes/workflow.php).
  var PT_STATUS_LABELS = {
    draft:                 { title: 'Saved as Draft', tone: 'gray' },
    endorsed:              { title: 'Endorsed for Approval', tone: 'info' },
    returned:              { title: 'Returned', tone: 'warning' },
    planning:              { title: 'Moved to Planning', tone: 'info' },
    approved:              { title: 'Approved', tone: 'success' },
    bidding:               { title: 'Moved to Bidding', tone: 'procurement' },
    awarded:               { title: 'Contract Awarded', tone: 'teal' },
    assigned:              { title: 'Contractor Assigned', tone: 'teal' },
    active:                { title: 'Construction Started', tone: 'teal' },
    delayed:               { title: 'Project Delayed', tone: 'danger' },
    on_hold:               { title: 'Project Put On Hold', tone: 'amber' },
    completion_inspection: { title: 'Completion Inspection Started', tone: 'procurement' },
    completed:             { title: 'Project Completed', tone: 'success' },
    turnover:              { title: 'Project Turned Over', tone: 'dark' },
    cancelled:             { title: 'Project Cancelled', tone: 'gray' },
  };

  var PT_ROLE_LABELS = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    bac: 'BAC',
    engineer: 'Engineer',
    contractor: 'Contractor',
    hope: 'HOPE',
    citizen: 'Citizen',
  };

  function ptEscape(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function ptTitleCase(text) {
    return String(text || '').replace(/[_-]+/g, ' ').replace(/\S+/g, function (w) {
      return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
    });
  }

  function ptResolveEvent(action, details) {
    if (action === 'Project status updated') {
      var match = /to (\w+)\.?\s*$/.exec(details || '');
      var status = match ? match[1] : null;
      if (status && PT_STATUS_LABELS[status]) return PT_STATUS_LABELS[status];
      return { title: 'Project Status Updated', tone: 'gray' };
    }
    if (PT_EVENTS[action]) return PT_EVENTS[action];
    return { title: ptTitleCase(action), tone: 'gray' };
  }

  function ptParseDate(mysqlDatetime) {
    return new Date(String(mysqlDatetime).replace(' ', 'T'));
  }

  function ptFormatDate(d) {
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  function ptFormatTime(d) {
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }

  var ptState = {};

  function ptApplyFilters(containerId) {
    var state = ptState[containerId];
    var container = document.getElementById(containerId);
    if (!state || !container) return;

    var listEl = container.querySelector('.pt-list');
    if (!listEl) return;

    var filtered = state.events.filter(function (ev) {
      if (state.filters.user && ev.actor_name !== state.filters.user) return false;
      if (state.filters.type && ev.resolved.title !== state.filters.type) return false;
      if (state.filters.from && ev.date < state.filters.from) return false;
      if (state.filters.to && ev.date > state.filters.to + ' 23:59:59') return false;
      return true;
    });

    listEl.innerHTML = filtered.length ? filtered.map(function (ev) {
      var tone = PT_TONES[ev.resolved.tone] || PT_TONES.gray;
      var icon = PT_ICONS[ev.resolved.tone] || '•';
      var roleLabel = ev.actor_role ? (PT_ROLE_LABELS[ev.actor_role] || ptTitleCase(ev.actor_role)) : '';
      // Citizen mode never got an actor_name from the API to begin with
      // (see citizen/api/project-timeline.php) — the portal-only branch is
      // just belt-and-suspenders in case a future caller forgets to set the
      // option.
      var metaLine = state.showActorName
        ? ptEscape(ev.actor_name) + (roleLabel ? ' · ' + ptEscape(roleLabel) : '')
        : ptEscape(roleLabel || 'System');
      return (
        '<article class="pt-item">' +
          '<span class="pt-dot" style="background:' + tone.bg + ';color:' + tone.fg + '">' + icon + '</span>' +
          '<div class="pt-card">' +
            '<div class="pt-card-head">' +
              '<strong style="color:' + tone.fg + '">' + ptEscape(ev.resolved.title) + '</strong>' +
              '<span class="pt-when">' + ptEscape(ptFormatDate(ev.dateObj)) + ' · ' + ptEscape(ptFormatTime(ev.dateObj)) + '</span>' +
            '</div>' +
            (ev.details ? '<p class="pt-detail">' + ptEscape(ev.details) + '</p>' : '') +
            '<div class="pt-meta">' + metaLine + '</div>' +
          '</div>' +
        '</article>'
      );
    }).join('') : '<p class="empty-state">No matching activity.</p>';
  }

  function ptBuildFilterBar(containerId) {
    var state = ptState[containerId];
    var types = Array.prototype.slice.call(new Set(state.events.map(function (e) { return e.resolved.title; }))).sort();

    // The user filter only makes sense where actor names are shown — citizen
    // mode never receives them (see ptApplyFilters), so there'd be nothing
    // meaningful to filter by.
    var userFilterHtml = '';
    if (state.showActorName) {
      var users = Array.prototype.slice.call(new Set(state.events.map(function (e) { return e.actor_name; }))).sort();
      userFilterHtml =
        '<select class="form-input pt-filter-user"><option value="">All users</option>' +
          users.map(function (u) { return '<option value="' + ptEscape(u) + '">' + ptEscape(u) + '</option>'; }).join('') +
        '</select>';
    }

    return (
      '<div class="pt-filters">' +
        userFilterHtml +
        '<select class="form-input pt-filter-type"><option value="">All event types</option>' +
          types.map(function (t) { return '<option value="' + ptEscape(t) + '">' + ptEscape(t) + '</option>'; }).join('') +
        '</select>' +
        '<input type="date" class="form-input pt-filter-from" title="From date">' +
        '<input type="date" class="form-input pt-filter-to" title="To date">' +
      '</div>'
    );
  }

  function ptWireFilterBar(containerId) {
    var container = document.getElementById(containerId);
    var state = ptState[containerId];

    var userFilter = container.querySelector('.pt-filter-user');
    if (userFilter) {
      userFilter.addEventListener('change', function (e) {
        state.filters.user = e.target.value;
        ptApplyFilters(containerId);
      });
    }
    container.querySelector('.pt-filter-type').addEventListener('change', function (e) {
      state.filters.type = e.target.value;
      ptApplyFilters(containerId);
    });
    container.querySelector('.pt-filter-from').addEventListener('change', function (e) {
      state.filters.from = e.target.value;
      ptApplyFilters(containerId);
    });
    container.querySelector('.pt-filter-to').addEventListener('change', function (e) {
      state.filters.to = e.target.value;
      ptApplyFilters(containerId);
    });
  }

  // Public entry point: renderProjectTimeline('containerElementId', projectId, options)
  // options.endpoint   — API path relative to BASE_PATH (default 'api/project-timeline.php')
  // options.showActorName — false hides individual staff names, showing only
  //   their role (used by the citizen portal, which never names staff
  //   anywhere else on that page — see citizen/api/project-timeline.php,
  //   which doesn't return actor_name at all, matching this default-safe).
  window.renderProjectTimeline = function (containerId, projectId, options) {
    var container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '<p class="empty-state">Loading activity timeline...</p>';

    options = options || {};
    var showActorName = options.showActorName !== false;
    var endpoint = options.endpoint || 'api/project-timeline.php';

    var base = window.BASE_PATH || '/';
    var url = base.replace(/\/?$/, '/') + endpoint + '?project_id=' + encodeURIComponent(projectId);

    fetch(url)
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var events = (data.events || []).map(function (ev) {
          return {
            actor_name: ev.actor_name,
            actor_role: ev.actor_role,
            details: ev.details,
            date: ev.created_at,
            dateObj: ptParseDate(ev.created_at),
            resolved: ptResolveEvent(ev.action, ev.details),
          };
        });

        ptState[containerId] = { events: events, filters: {}, showActorName: showActorName };

        if (!events.length) {
          container.innerHTML = '<p class="empty-state">No activity recorded yet.</p>';
          return;
        }

        container.innerHTML =
          '<div class="pt-timeline">' +
            ptBuildFilterBar(containerId) +
            '<div class="pt-list"></div>' +
          '</div>';

        ptWireFilterBar(containerId);
        ptApplyFilters(containerId);
      })
      .catch(function () {
        container.innerHTML = '<p class="empty-state">Unable to load activity timeline.</p>';
      });
  };

  // Shared with other portal pages that render their own list of the same
  // bac_procurement_logs actions (e.g. bac/assets/js/bac.js's Procurement
  // Logs page) so the action->title/tone/icon mapping has one source of
  // truth instead of a second copy that can drift out of sync with this one.
  window.ptResolveEvent = ptResolveEvent;
  window.PT_TONES = PT_TONES;
  window.PT_ICONS = PT_ICONS;
  window.PT_ROLE_LABELS = PT_ROLE_LABELS;
})();
