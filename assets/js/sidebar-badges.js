/* ============================================================
   assets/js/sidebar-badges.js — shared sidebar notification badges,
   used by all 7 portals (paired with api/sidebar-badges.php).

   Each portal's dashboard page sets `window.SIDEBAR_BADGES_PORTAL`
   before this script loads (e.g. 'admin', 'bac', 'engineer', ...) so
   the shared endpoint knows which sidebar's badges to compute — a role
   like super_admin can reach more than one portal, so the session role
   alone isn't enough to know which sidebar is on screen.

   This script owns its own click listeners on `.nav-item[data-page]`
   and is deliberately independent of each portal's own page router
   (script.js's navigate(), bac.js's, engineer.js's, etc.) — clicking a
   badge-bearing item clears it instantly (optimistic) while the real
   mark_viewed write and a reconciling refetch happen in the background,
   regardless of how that portal switches pages internally.
   ============================================================ */
(function () {
  const PORTAL = window.SIDEBAR_BADGES_PORTAL;
  if (!PORTAL) return; // a page that forgot to set this just gets no badges, not an error

  const POLL_INTERVAL_MS = 45000;
  const API_URL = (window.BASE_PATH || '/') + 'api/sidebar-badges.php';
  const CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

  function readNavBadgeCount(page) {
    const el = document.querySelector(`.nav-badge[data-badge="${page}"] .nav-badge-dot`);
    return el ? (parseInt(el.textContent, 10) || 0) : 0;
  }

  function renderNavBadge(page, data) {
    const el = document.querySelector(`.nav-badge[data-badge="${page}"]`);
    if (!el || !data) return;

    const parts = [];
    if (data.count > 0 && data.type) {
      parts.push(`<span class="nav-badge-dot nav-badge-${data.type}${data.type === 'red' ? ' nav-badge-pulse' : ''}">${data.count > 99 ? '99+' : data.count}</span>`);
    }
    if (data.urgent) {
      parts.push('<span class="nav-badge-pill nav-badge-pill-urgent">URGENT</span>');
    }
    if (data.label) {
      const pillClass = data.label === 'AI' ? 'nav-badge-pill-ai'
        : data.label === 'NEW' ? 'nav-badge-pill-new'
        : data.label === 'UPDATED' ? 'nav-badge-pill-updated'
        : 'nav-badge-pill-default';
      parts.push(`<span class="nav-badge-pill ${pillClass}">${data.label}</span>`);
    }

    el.innerHTML = parts.join('');
  }

  async function fetchSidebarBadges() {
    try {
      const res = await fetch(`${API_URL}?portal=${encodeURIComponent(PORTAL)}`, { headers: CSRF_HEADERS });
      if (!res.ok) return;
      const data = await res.json();
      const badges = data.badges || {};
      Object.keys(badges).forEach(page => renderNavBadge(page, badges[page]));
    } catch (e) {
      // Badges are a convenience layer — never let a failed fetch break navigation.
    }
  }

  // Instantly clears the clicked item's badge (and shrinks Dashboard's total
  // by the same amount) before any network round-trip, then persists the
  // view server-side and reconciles with a real refetch shortly after.
  async function markSidebarBadgeViewed(page) {
    const clearedCount = readNavBadgeCount(page);
    if (clearedCount === 0 && !document.querySelector(`.nav-badge[data-badge="${page}"]`)?.innerHTML) return;

    renderNavBadge(page, { count: 0, label: null });
    if (clearedCount > 0 && page !== 'dashboard') {
      const dashCount = readNavBadgeCount('dashboard');
      if (dashCount > 0) renderNavBadge('dashboard', { type: 'red', count: Math.max(0, dashCount - clearedCount) });
    }

    try {
      await fetch(`${API_URL}?action=mark_viewed`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: JSON.stringify({ badge_key: page }),
      });
    } catch (e) {
      // The optimistic clear already happened; the next poll reconciles either way.
    }

    fetchSidebarBadges();
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nav-item[data-page]').forEach(item => {
      item.addEventListener('click', () => markSidebarBadgeViewed(item.dataset.page));
    });

    fetchSidebarBadges();
    setInterval(fetchSidebarBadges, POLL_INTERVAL_MS);
  });
})();
