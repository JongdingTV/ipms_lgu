/* ============================================================
   assets/js/notifications.js — shared notification bell/panel logic,
   used by all 6 portals (paired with includes/notifications-panel.php
   for the markup and includes/topbar.php for the bell button).
   Polls api/notifications.php on an interval since there is no
   push/websocket infrastructure in this app.

   Automated Reminders tab: reminders are delivered as normal notification
   rows (type='reminder', see includes/Reminders.php), so the "All" tab
   already shows them with zero changes here. The "Reminders" tab is a
   richer, bucketed view (Upcoming/Today/Overdue) sourced from
   api/reminders.php — same panel, same list container, just a different
   fetch+render pair, not a separate component.
   ============================================================ */
(function () {
  const POLL_INTERVAL_MS = 45000;
  const API_URL = (window.BASE_PATH || '/') + 'api/notifications.php';
  const REMINDERS_API_URL = (window.BASE_PATH || '/') + 'api/reminders.php';
  const CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

  let activeTab = 'all';

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
  }

  function timeAgo(dateStr) {
    const diffMs = Date.now() - new Date(String(dateStr).replace(' ', 'T')).getTime();
    const diffSec = Math.max(0, Math.floor(diffMs / 1000));
    if (diffSec < 60) return 'just now';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr}h ago`;
    return `${Math.floor(diffHr / 24)}d ago`;
  }

  function severityClass(type) {
    if (type === 'alert') return 'notif-high';
    if (type === 'warning' || type === 'reminder') return 'notif-mid';
    return 'notif-low';
  }

  function updateBadge(count) {
    document.querySelectorAll('.notif-badge').forEach((badge) => {
      badge.textContent = String(count);
      badge.style.display = count > 0 ? 'flex' : 'none';
    });
  }

  function renderPanel(items) {
    const list = document.getElementById('notifList');
    if (!list) return;

    if (!items.length) {
      list.innerHTML = '<p class="empty-state" style="padding:16px;">No notifications yet.</p>';
      return;
    }

    list.innerHTML = items.map((n) => `
      <div class="notif-item ${severityClass(n.type)}" data-id="${n.id}" data-link="${escapeHtml(n.link || '')}"
           style="cursor:pointer;${n.is_read ? 'opacity:.55' : ''}">
        <div class="notif-dot"></div>
        <div>
          <p class="notif-msg">${escapeHtml(n.title)}</p>
          <p class="notif-msg" style="font-weight:400;">${escapeHtml(n.message)}</p>
          <span class="notif-time">${timeAgo(n.created_at)}</span>
        </div>
      </div>
    `).join('');

    list.querySelectorAll('.notif-item').forEach((el) => {
      el.addEventListener('click', () => handleItemClick(el));
    });
  }

  async function fetchNotifications() {
    try {
      const res = await fetch(`${API_URL}?per_page=15`, { headers: CSRF_HEADERS });
      if (!res.ok) return;
      const data = await res.json();
      updateBadge(data.unread_count || 0);
      if (activeTab === 'all') renderPanel(data.data || []);
    } catch (e) {
      // Fail silently — notifications should never break the rest of the dashboard.
    }
  }

  async function handleItemClick(el) {
    const id = Number(el.dataset.id);
    const link = el.dataset.link;
    try {
      await fetch(`${API_URL}?action=mark_read`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: JSON.stringify({ id }),
      });
    } catch (e) {
      // ignore
    }
    if (link) {
      window.location.href = link;
    } else {
      fetchNotifications();
    }
  }

  async function markAllRead() {
    try {
      await fetch(`${API_URL}?action=mark_all_read`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: '{}',
      });
    } catch (e) {
      // ignore
    }
    fetchNotifications();
  }

  /* ---- Reminders tab ------------------------------------------------- */

  const BUCKET_LABEL = { '7d': 'In 7 days', '3d': 'In 3 days', '1d': 'Tomorrow', today: 'Today', overdue: 'Overdue', urgent: 'Needs attention' };

  function renderReminders(items) {
    const list = document.getElementById('notifList');
    if (!list) return;

    if (!items.length) {
      list.innerHTML = '<p class="empty-state" style="padding:16px;">No reminders right now.</p>';
      return;
    }

    list.innerHTML = items.map((r) => {
      const sevClass = (r.bucket === 'overdue' || r.bucket === 'urgent') ? 'notif-high' : (r.bucket === 'today' ? 'notif-mid' : 'notif-low');
      return `
        <div class="notif-item ${sevClass}" data-link="${escapeHtml(r.link || '')}" style="cursor:pointer;align-items:center;">
          <div class="notif-dot"></div>
          <div style="flex:1;min-width:0;">
            <p class="notif-msg">${escapeHtml(r.title)}</p>
            <p class="notif-msg" style="font-weight:400;">${escapeHtml(r.message)}</p>
            <span class="notif-time">${escapeHtml(BUCKET_LABEL[r.bucket] || r.module || '')}</span>
          </div>
          ${r.dismissible ? `<button class="tc-dismiss-btn" title="Dismiss" onclick="event.stopPropagation();window.__notifDismissReminder('${escapeHtml(r.reminder_key)}')">&times;</button>` : ''}
        </div>
      `;
    }).join('');

    list.querySelectorAll('.notif-item[data-link]').forEach((el) => {
      el.addEventListener('click', () => {
        const link = el.dataset.link;
        if (link) window.location.href = link;
      });
    });
  }

  async function fetchReminders() {
    try {
      const res = await fetch(`${REMINDERS_API_URL}?action=list`, { headers: CSRF_HEADERS });
      if (!res.ok) return;
      const data = await res.json();
      if (activeTab === 'reminders') renderReminders(data.data || []);
    } catch (e) {
      // Fail silently, same as fetchNotifications().
    }
  }

  async function dismissReminder(reminderKey) {
    try {
      const res = await fetch(`${REMINDERS_API_URL}?action=dismiss`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: JSON.stringify({ reminder_key: reminderKey }),
      });
      const data = await res.json();
      if (!data.success && window.TASK_CENTER_TOAST) {
        window.TASK_CENTER_TOAST(data.message || "Couldn't dismiss this reminder.", 'error');
      }
    } catch (e) {
      // ignore
    }
    fetchReminders();
  }
  window.__notifDismissReminder = dismissReminder;

  function initTabs() {
    const tabs = document.querySelectorAll('.notif-tab');
    if (!tabs.length) return;
    tabs.forEach((tab) => {
      tab.addEventListener('click', (e) => {
        e.stopPropagation();
        tabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        activeTab = tab.dataset.tab;
        if (activeTab === 'reminders') fetchReminders(); else fetchNotifications();
      });
    });
  }

  function initPanelToggle() {
    const btn = document.getElementById('notifBtn');
    const panel = document.getElementById('notifPanel');
    const clearBtn = document.getElementById('notifClear');
    if (!btn || !panel) return;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      panel.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!panel.contains(e.target) && e.target !== btn) {
        panel.classList.remove('open');
      }
    });
    if (clearBtn) {
      clearBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        markAllRead();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    initPanelToggle();
    initTabs();
    fetchNotifications();
    setInterval(() => {
      fetchNotifications();
      if (activeTab === 'reminders') fetchReminders();
    }, POLL_INTERVAL_MS);
  });
})();
