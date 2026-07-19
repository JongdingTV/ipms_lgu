/* ============================================================
   assets/js/sidebar-toggle.js — shared hamburger/sidebar behavior for
   admin, super_admin, bac, engineer, contractor, and hope (every portal
   except citizen, which already has this exact behavior built directly
   into citizen.js/citizen.css and doesn't need it duplicated here).

   Two modes, matched by the existing CSS in assets/css/style.css:
   - Desktop (>=769px): burger toggles body.sidebar-collapsed, sliding the
     fixed sidebar out of view and letting the content take full width.
   - Mobile (<769px): burger toggles an off-canvas .open drawer, with a
     dimmed backdrop behind it (click or Escape to close, same as
     opening any other overlay in this app).
   ============================================================ */
(function () {
  function setupSidebarToggle() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    let backdrop = document.getElementById('sidebarBackdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'sidebarBackdrop';
      backdrop.className = 'sidebar-backdrop';
      document.body.appendChild(backdrop);
    }

    const closeSidebar = () => {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
    };

    toggle.addEventListener('click', () => {
      if (window.matchMedia('(min-width: 769px)').matches) {
        document.body.classList.toggle('sidebar-collapsed');
        return;
      }
      const isOpen = sidebar.classList.toggle('open');
      backdrop.classList.toggle('show', isOpen);
    });

    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeSidebar();
    });
    // Navigating closes the drawer so the chosen page is immediately visible.
    sidebar.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', closeSidebar);
    });
  }

  document.addEventListener('DOMContentLoaded', setupSidebarToggle);
})();
