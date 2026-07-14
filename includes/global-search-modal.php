<!-- Shared Ctrl+K / Cmd+K global search overlay — populated by assets/js/global-search.js.
     Each portal's own JS declares window.GLOBAL_SEARCH_SOURCES (which existing,
     already role-scoped endpoints to query) and window.GLOBAL_SEARCH_NAVIGATE
     (how to switch to a result's page) before this script needs them. -->
<div class="gsearch-overlay" id="globalSearchOverlay">
  <div class="gsearch-box">
    <div class="gsearch-input-row">
      <svg viewBox="0 0 20 20" fill="currentColor" class="gsearch-icon">
        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
      </svg>
      <input type="text" id="globalSearchInput" placeholder="Search everything..." autocomplete="off">
      <kbd class="gsearch-kbd">Esc</kbd>
    </div>
    <div class="gsearch-results" id="globalSearchResults">
      <p class="empty-state">Type to search...</p>
    </div>
  </div>
</div>
