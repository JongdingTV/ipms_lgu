/* ============================================================
   assets/js/theme-toggle.js — light/dark theme switch, shared by
   all 6 portals. Client-side only (localStorage), no backend/schema
   changes. The actual dark palette lives in assets/css/style.css
   (:root[data-theme="dark"]) and citizen/assets/css/citizen.css.
   A tiny inline snippet in includes/header.php already applies the
   saved theme before first paint — this file only wires the toggle
   button and keeps localStorage in sync afterward.
   ============================================================ */
(function () {
  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    try {
      localStorage.setItem('theme', theme);
    } catch (e) {}
    updateToggleIcon(theme);
  }

  function updateToggleIcon(theme) {
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    btn.classList.toggle('is-dark', theme === 'dark');
  }

  function init() {
    updateToggleIcon(currentTheme());
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
      applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
