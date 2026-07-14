/* ============================================================
   assets/js/global-search.js — shared Ctrl+K / Cmd+K search overlay.
   Purely additive: does not touch each portal's existing per-page
   #searchInput proxy-search. Fans out to whichever existing,
   already role-scoped endpoints the current portal declares via
   window.GLOBAL_SEARCH_SOURCES (array of {label, url, mapItem(row)}),
   then hands off navigation to window.GLOBAL_SEARCH_NAVIGATE(page).
   Never introduces a new backend endpoint — only recombines data the
   user could already reach through normal navigation.
   ============================================================ */
(function () {
  const CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};
  let debounceTimer = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
  }

  function openGlobalSearch() {
    const overlay = document.getElementById('globalSearchOverlay');
    const input = document.getElementById('globalSearchInput');
    const results = document.getElementById('globalSearchResults');
    if (!overlay || !input || !results) return;
    overlay.classList.add('open');
    input.value = '';
    results.innerHTML = '<p class="empty-state">Type to search...</p>';
    setTimeout(() => input.focus(), 50);
  }

  function closeGlobalSearch() {
    document.getElementById('globalSearchOverlay')?.classList.remove('open');
  }

  async function runSearch(term) {
    const resultsEl = document.getElementById('globalSearchResults');
    if (!resultsEl) return;

    const sources = window.GLOBAL_SEARCH_SOURCES || [];
    if (!sources.length) {
      resultsEl.innerHTML = '<p class="empty-state">Search is not available in this portal.</p>';
      return;
    }
    if (!term || term.length < 2) {
      resultsEl.innerHTML = '<p class="empty-state">Type at least 2 characters...</p>';
      return;
    }

    resultsEl.innerHTML = '<p class="empty-state">Searching...</p>';

    try {
      const groups = await Promise.all(sources.map(async (source) => {
        try {
          const url = new URL(source.url, window.location.origin);
          url.searchParams.set('search', term);
          if (source.extraParams) {
            Object.entries(source.extraParams).forEach(([k, v]) => url.searchParams.set(k, v));
          }
          const res = await fetch(url.toString(), { headers: CSRF_HEADERS });
          if (!res.ok) return { label: source.label, items: [] };
          const data = await res.json();
          const rows = (data[source.dataKey || 'data'] || []).slice(0, 5);
          return { label: source.label, items: rows.map(source.mapItem) };
        } catch (e) {
          return { label: source.label, items: [] };
        }
      }));

      const nonEmpty = groups.filter((g) => g.items.length);
      if (!nonEmpty.length) {
        resultsEl.innerHTML = '<p class="empty-state">No matches found.</p>';
        return;
      }

      resultsEl.innerHTML = nonEmpty.map((group) => `
        <div class="gsearch-group">
          <div class="gsearch-group-label">${escapeHtml(group.label)}</div>
          ${group.items.map((item) => `
            <button type="button" class="gsearch-result" data-page="${escapeHtml(item.page || '')}">
              <span class="gsearch-result-title">${escapeHtml(item.title)}</span>
              <span class="gsearch-result-meta">${escapeHtml(item.meta || '')}</span>
            </button>
          `).join('')}
        </div>
      `).join('');

      resultsEl.querySelectorAll('.gsearch-result').forEach((btn) => {
        btn.addEventListener('click', () => {
          const page = btn.dataset.page;
          closeGlobalSearch();
          if (page && typeof window.GLOBAL_SEARCH_NAVIGATE === 'function') {
            window.GLOBAL_SEARCH_NAVIGATE(page);
          }
        });
      });
    } catch (e) {
      resultsEl.innerHTML = '<p class="empty-state">Search failed. Please try again.</p>';
    }
  }

  function init() {
    document.addEventListener('keydown', (event) => {
      const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
      if (isShortcut) {
        event.preventDefault();
        const overlay = document.getElementById('globalSearchOverlay');
        if (overlay?.classList.contains('open')) {
          closeGlobalSearch();
        } else {
          openGlobalSearch();
        }
      } else if (event.key === 'Escape') {
        closeGlobalSearch();
      }
    });

    document.getElementById('globalSearchBtn')?.addEventListener('click', openGlobalSearch);
    document.getElementById('globalSearchOverlay')?.addEventListener('click', (event) => {
      if (event.target.id === 'globalSearchOverlay') closeGlobalSearch();
    });

    const input = document.getElementById('globalSearchInput');
    input?.addEventListener('input', (event) => {
      clearTimeout(debounceTimer);
      const term = event.target.value.trim();
      debounceTimer = setTimeout(() => runSearch(term), 250);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
