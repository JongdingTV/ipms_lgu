/* ============================================================
   assets/js/scroll-reveal.js — shared scroll-reveal for dashboard
   cards, ported from landing.php's existing IntersectionObserver
   pattern so portals get the same subtle entrance motion.
   Elements need class="reveal" (see assets/css/style.css for the
   opacity/transform transition); this script just toggles
   "is-visible" once each element scrolls into view.
   ============================================================ */
(function () {
  function init() {
    const targets = document.querySelectorAll('.reveal');
    if (!targets.length) return;

    if (!('IntersectionObserver' in window)) {
      targets.forEach((el) => el.classList.add('is-visible'));
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

    targets.forEach((el) => observer.observe(el));

    // Safety net: async content above a reveal target (Chart.js canvases,
    // dashboard data replacing skeleton loaders) can shift its position
    // right as the observer takes its first reading, sometimes missing the
    // intersection crossing entirely and leaving genuinely on-screen content
    // stuck at opacity:0 forever. Force-reveal anything still unrevealed
    // after a short grace period so nothing can stay permanently invisible.
    setTimeout(() => {
      targets.forEach((el) => {
        if (!el.classList.contains('is-visible')) {
          el.classList.add('is-visible');
          observer.unobserve(el);
        }
      });
    }, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Re-scan whenever a portal's SPA navigation swaps in new page content
  // (e.g. bacShowPage/saShowPage/etc. toggle .page-section visibility) —
  // exposed globally so each portal's own router can call it after render.
  window.rescanScrollReveal = init;
})();
