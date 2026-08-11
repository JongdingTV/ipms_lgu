/* ============================================================
   assets/js/task-center.js — Smart Task & Assignment Center, shared
   across all 6 staff portals (admin, super_admin, bac, engineer,
   contractor, hope). Portal-agnostic: it never calls a portal's own
   modal/router/escape/toast functions by name — each portal instead sets
   four globals once, near the top of its own JS file:

     window.TASK_CENTER_NAVIGATE   = navigate;          // (page, params) -> void
     window.TASK_CENTER_OPEN_MODAL = openModal;          // (title, html) -> void
     window.TASK_CENTER_CLOSE_MODAL= closeModal;         // () -> void
     window.TASK_CENTER_ESCAPE     = escapeHtml;         // (value) -> string
     window.TASK_CENTER_TOAST      = toast;              // (msg, type) -> void

   This mirrors the existing window.GLOBAL_SEARCH_NAVIGATE precedent
   (assets/js/script.js) rather than inventing a new cross-portal pattern.
   ============================================================ */
const TASK_CENTER_API = (window.BASE_PATH || '/') + 'api/task-center.php';
const TASK_CENTER_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

let taskCenterState = { filter: '', search: '', module: '' };
let taskCenterCache = [];
let taskCenterPanelCache = [];
const TASK_CENTER_PANEL_LIMIT = 6;

function tcEsc(v) {
  return (window.TASK_CENTER_ESCAPE || (x => String(x))).call(null, v == null ? '' : String(v));
}
function tcToast(msg, type) {
  if (window.TASK_CENTER_TOAST) window.TASK_CENTER_TOAST(msg, type);
}

async function taskCenterGet(params) {
  const qs = new URLSearchParams(params).toString();
  const res = await fetch(`${TASK_CENTER_API}?${qs}`);
  const data = await res.json();
  if (!res.ok || data.success === false) throw new Error(data.message || 'Failed to load tasks.');
  return data;
}

async function taskCenterPost(action, body) {
  const res = await fetch(`${TASK_CENTER_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...TASK_CENTER_CSRF_HEADERS },
    body: JSON.stringify(body || {}),
  });
  const data = await res.json();
  if (!res.ok || data.success === false) throw new Error(data.message || 'Request failed.');
  return data;
}

const TASK_CENTER_PRIORITY_META = {
  urgent: { icon: '🔴', label: 'URGENT', class: 'tc-urgent' },
  due_today: { icon: '🟠', label: 'DUE TODAY', class: 'tc-due-today' },
  upcoming: { icon: '🟢', label: 'UPCOMING', class: 'tc-upcoming' },
  info: { icon: '🔵', label: 'INFORMATION', class: 'tc-info' },
};

function taskCenterFormatDate(d) {
  if (!d) return null;
  const dt = new Date(d.length <= 10 ? d + 'T00:00:00' : d);
  if (isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

/* ---- "My Tasks" page ---------------------------------------------------- */

function taskCenterInitPage(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">My Tasks</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Everything that needs your action, pulled live from the existing project, procurement, inspection, and payment workflows — this list is a view, not a separate to-do system.
        </p>
      </div>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search tasks…" oninput="taskCenterState.search=this.value;taskCenterLoadList()" />
      <select class="filter-select" onchange="taskCenterState.filter=this.value;taskCenterLoadList()">
        <option value="">All</option>
        <option value="urgent">Urgent</option>
        <option value="due_today">Due Today</option>
        <option value="upcoming">Upcoming</option>
        <option value="overdue">Overdue</option>
        <option value="completed">Completed</option>
        <option value="dismissed">Dismissed</option>
      </select>
    </div>
    <div id="taskCenterList"></div>
  `;
  taskCenterLoadList();
}

async function taskCenterLoadList() {
  const wrap = document.getElementById('taskCenterList');
  if (!wrap) return;
  wrap.innerHTML = '<p class="empty-state">Loading tasks…</p>';

  try {
    const data = await taskCenterGet({
      action: 'list', filter: taskCenterState.filter, search: taskCenterState.search, module: taskCenterState.module,
    });

    if (taskCenterState.filter === 'completed') {
      taskCenterRenderCompleted(wrap, data.completed || []);
      return;
    }

    taskCenterCache = data.data || [];
    taskCenterRenderList(wrap, taskCenterCache, taskCenterState.filter === 'dismissed');
  } catch (e) {
    wrap.innerHTML = '<p class="empty-state">Failed to load tasks.</p>';
    console.error(e);
  }
}

function taskCenterRenderList(wrap, tasks, isDismissedView) {
  if (!tasks.length) {
    wrap.innerHTML = `
      <div class="empty-state" style="padding:40px 20px;text-align:center;">
        <p style="font-weight:700;font-size:1rem;margin-bottom:4px;">You're all caught up!</p>
        <p style="color:var(--text-muted);margin:0;">No pending tasks require your attention.</p>
      </div>
    `;
    return;
  }
  wrap.innerHTML = `<div class="tc-list">${tasks.map(t => taskCenterCard(t, isDismissedView)).join('')}</div>`;
}

function taskCenterRenderCompleted(wrap, rows) {
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No recently completed actions yet.</p>';
    return;
  }
  wrap.innerHTML = `
    <div class="tc-list">
      ${rows.map(r => `
        <div class="tc-card tc-completed">
          <div class="tc-card-body">
            <strong>${tcEsc(r.action.replace(/_/g, ' '))}</strong>
            <p class="tc-card-desc">${tcEsc(r.details)}</p>
            <span class="tc-card-meta">${tcEsc(r.module)} · ${tcEsc(r.created_at)}</span>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

function taskCenterCard(t, isDismissedView) {
  const meta = TASK_CENTER_PRIORITY_META[t.priority] || TASK_CENTER_PRIORITY_META.upcoming;
  const dueLabel = t.due_date ? `Due: ${taskCenterFormatDate(t.due_date)}` : null;
  const statusLabel = t.status === 'overdue' ? 'Overdue' : (t.status === 'in_progress' ? 'In Progress' : 'Pending');

  return `
    <div class="tc-card ${meta.class}" data-key="${tcEsc(t.key)}">
      <div class="tc-card-priority">${meta.icon} <span>${meta.label}</span></div>
      <div class="tc-card-body" onclick="taskCenterOpenDetail('${tcEsc(t.key)}')" style="cursor:pointer;">
        <strong>${tcEsc(t.title)}</strong>
        ${t.project_name ? `<p class="tc-card-project">${tcEsc(t.project_name)}</p>` : ''}
        <p class="tc-card-desc">${tcEsc(t.description)}</p>
        <div class="tc-card-meta">
          <span class="tc-status-badge tc-status-${tcEsc(t.status)}">${statusLabel}</span>
          ${dueLabel ? `<span>${tcEsc(dueLabel)}</span>` : ''}
          <span>${tcEsc(t.module)}</span>
        </div>
      </div>
      <div class="tc-card-actions">
        ${t.priority !== 'info' ? `<button class="btn-primary btn-compact" onclick="taskCenterPerformActionByKey('${tcEsc(t.key)}')">Open</button>` : ''}
        ${isDismissedView
          ? `<button class="btn-secondary btn-compact" onclick="taskCenterUndismiss('${tcEsc(t.key)}')">Restore</button>`
          : `<button class="tc-dismiss-btn" title="Dismiss" onclick="taskCenterDismiss('${tcEsc(t.key)}')">&times;</button>`}
      </div>
    </div>
  `;
}

/* ---- Detail modal --------------------------------------------------------- */

function taskCenterOpenDetail(key) {
  const t = taskCenterCache.find(x => x.key === key) || taskCenterPanelCache.find(x => x.key === key);
  if (!t || !window.TASK_CENTER_OPEN_MODAL) return;

  const meta = TASK_CENTER_PRIORITY_META[t.priority] || TASK_CENTER_PRIORITY_META.upcoming;
  const statusLabel = t.status === 'overdue' ? 'Overdue' : (t.status === 'in_progress' ? 'In Progress' : 'Pending');

  window.TASK_CENTER_OPEN_MODAL(t.title, `
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div>${meta.icon} <strong>${meta.label}</strong> · <span class="tc-status-badge tc-status-${tcEsc(t.status)}">${statusLabel}</span></div>
      <p>${tcEsc(t.description)}</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.85rem;">
        ${t.project_name ? `<div><strong>Project</strong><br>${tcEsc(t.project_name)}</div>` : ''}
        <div><strong>Module</strong><br>${tcEsc(t.module)}</div>
        ${t.due_date ? `<div><strong>Due Date</strong><br>${tcEsc(taskCenterFormatDate(t.due_date))}</div>` : ''}
        <div><strong>Created</strong><br>${tcEsc(taskCenterFormatDate(t.created_date))}</div>
      </div>
      <div class="form-actions">
        ${t.priority !== 'info' ? `<button class="btn-primary" onclick="taskCenterPerformActionByKey('${tcEsc(t.key)}')">Perform Action</button>` : ''}
        <button class="btn-secondary" onclick="(window.TASK_CENTER_CLOSE_MODAL||function(){})()">Close</button>
      </div>
    </div>
  `);
}

async function taskCenterPerformActionByKey(key) {
  const t = taskCenterCache.find(x => x.key === key) || taskCenterPanelCache.find(x => x.key === key);
  if (!t) return;
  try { await taskCenterPost('opened', { task_key: t.key }); } catch (e) { /* non-fatal */ }
  if (window.TASK_CENTER_CLOSE_MODAL) window.TASK_CENTER_CLOSE_MODAL();
  if (window.TASK_CENTER_NAVIGATE) window.TASK_CENTER_NAVIGATE(t.link_page, t.link_params || {});
}

async function taskCenterDismiss(key, event) {
  if (event) event.stopPropagation();
  try {
    await taskCenterPost('dismiss', { task_key: key });
    tcToast('Task dismissed.');
    taskCenterRefreshAllViews();
  } catch (e) {
    tcToast(e.message || 'Failed to dismiss task.', 'error');
  }
}

async function taskCenterUndismiss(key) {
  try {
    await taskCenterPost('undismiss', { task_key: key });
    tcToast('Task restored.');
    taskCenterRefreshAllViews();
  } catch (e) {
    tcToast(e.message || 'Failed to restore task.', 'error');
  }
}

function taskCenterRefreshAllViews() {
  if (document.getElementById('taskCenterList')) taskCenterLoadList();
  if (document.getElementById('taskCenterModalBody')) {
    taskCenterGet({ action: 'list' }).then(data => {
      taskCenterPanelCache = data.data || [];
      taskCenterRenderPanelModalBody();
    }).catch(() => {});
  }
  taskCenterRefreshSummary();
}

/* ---- Dashboard summary widget --------------------------------------------- */

async function taskCenterInitDashboardWidget() {
  const kpiEl = document.getElementById('taskCenterKpiValue');
  const bodyEl = document.getElementById('taskCenterSummaryBody');
  if (!kpiEl && !bodyEl) return;

  try {
    const data = await taskCenterGet({ action: 'summary' });
    const c = data.counts || { urgent: 0, due_today: 0, upcoming: 0, total: 0 };
    if (kpiEl) kpiEl.textContent = c.total;
    if (bodyEl) {
      bodyEl.innerHTML = c.total === 0
        ? '<p class="empty-state empty-state-compact">You\'re all caught up! No pending tasks.</p>'
        : `
          <div class="tc-summary-row">🔴 ${c.urgent} Urgent</div>
          <div class="tc-summary-row">🟠 ${c.due_today} Due Today</div>
          <div class="tc-summary-row">🟢 ${c.upcoming} Upcoming</div>
        `;
    }
  } catch (e) {
    console.error(e);
  }
}

function taskCenterRefreshSummary() {
  taskCenterInitDashboardWidget();
}

/* ---- Topbar popup modal -------------------------------------------------
   Clicking the My Tasks icon (includes/topbar.php) opens the same centered
   modal-overlay every other detail view in this app uses (openModal/
   saOpenModal/etc., bridged via window.TASK_CENTER_OPEN_MODAL) — not a page
   navigation, not a dropdown. The #taskCenterModalBody wrapper is how
   taskCenterRefreshAllViews() knows to re-render this modal in place after
   a dismiss/undismiss instead of blindly popping it open elsewhere. */

async function taskCenterOpenPanelModal() {
  if (!window.TASK_CENTER_OPEN_MODAL) return;

  window.TASK_CENTER_OPEN_MODAL('My Tasks', '<div id="taskCenterModalBody"><p class="empty-state">Loading…</p></div>');

  try {
    const data = await taskCenterGet({ action: 'list' });
    taskCenterPanelCache = data.data || [];
    taskCenterRenderPanelModalBody();
  } catch (e) {
    const body = document.getElementById('taskCenterModalBody');
    if (body) body.innerHTML = '<p class="empty-state">Failed to load tasks.</p>';
    console.error(e);
  }
}

function taskCenterRenderPanelModalBody() {
  const body = document.getElementById('taskCenterModalBody');
  if (!body) return;

  const shown = taskCenterPanelCache.slice(0, TASK_CENTER_PANEL_LIMIT);

  if (!shown.length) {
    body.innerHTML = `
      <div class="empty-state" style="padding:24px 16px;text-align:center;">
        <p style="font-weight:700;margin-bottom:2px;">You're all caught up!</p>
        <p style="color:var(--text-muted);margin:0;font-size:.85rem;">No pending tasks require your attention.</p>
      </div>
    `;
    return;
  }

  const remaining = taskCenterPanelCache.length - shown.length;
  body.innerHTML = `
    <div class="tc-modal-list"><div class="tc-list">${shown.map(t => taskCenterCard(t, false)).join('')}</div></div>
    <div class="tc-modal-footer">
      ${remaining > 0 ? `<p style="font-size:.8rem;color:var(--text-muted);margin:0 0 8px;">+ ${remaining} more task${remaining === 1 ? '' : 's'} not shown</p>` : ''}
      <button class="btn-primary btn-compact" type="button" onclick="taskCenterViewAllFromModal()">View All Tasks</button>
    </div>
  `;
}

function taskCenterViewAllFromModal() {
  if (window.TASK_CENTER_CLOSE_MODAL) window.TASK_CENTER_CLOSE_MODAL();
  if (window.TASK_CENTER_NAVIGATE) window.TASK_CENTER_NAVIGATE('my-tasks');
}
