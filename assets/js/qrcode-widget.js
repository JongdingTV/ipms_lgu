/* ============================================================
   Project QR Code — Transparency link generator, shared across Project
   Details, Reports, and the Turnover/Completion flow (assets/js/script.js).
   Encodes a stable URL (transparency.php?code=<project_code>) using the
   vendored QRCode library (davidshimjs/qrcodejs, loaded via CDN in
   includes/footer.php) — the same project_code always renders the same
   QR, so "remains active throughout the project's lifecycle" just falls
   out of the URL being permanent rather than anything needing to be
   regenerated or stored.
   ============================================================ */
function projectTransparencyUrl(projectCode) {
  var base = (window.BASE_PATH || '/').replace(/\/?$/, '/');
  // window.APP_PUBLIC_URL is server-configured (includes/config.php's
  // APP_PUBLIC_URL, injected in includes/header.php) — deliberately NOT
  // window.location.origin, which would bake in whatever hostname happened
  // to be in the browser's address bar when this ran (localhost during
  // local dev, a LAN IP, etc.) instead of the real public domain citizens
  // need to reach.
  var origin = window.APP_PUBLIC_URL || window.location.origin;
  return origin + base + 'transparency.php?code=' + encodeURIComponent(projectCode);
}

/* Renders a QR into an existing empty container element. */
function renderProjectQR(containerId, projectCode, size) {
  var el = document.getElementById(containerId);
  if (!el || !projectCode || typeof QRCode === 'undefined') return;
  el.innerHTML = '';
  new QRCode(el, {
    text: projectTransparencyUrl(projectCode),
    width: size || 140,
    height: size || 140,
    correctLevel: QRCode.CorrectLevel.M,
  });
}

/* Standalone "View QR" modal — used by the Reports page and anywhere else
   that only has a project code/name handy, not a full project detail view. */
function openProjectQRModal(projectCode, projectName) {
  if (typeof openModal !== 'function' || typeof escapeHtml !== 'function') return;
  var url = projectTransparencyUrl(projectCode);

  openModal('Project QR Code — ' + escapeHtml(projectName), `
    <div style="text-align:center;">
      <div id="qrModalTarget" style="display:inline-block;padding:12px;background:#fff;border-radius:10px;"></div>
      <p style="font-size:.8rem;color:#64748b;margin-top:10px;word-break:break-all;">${escapeHtml(url)}</p>
      <p style="font-size:.75rem;color:#94a3b8;">Scan to open this project's public transparency page — no login required.</p>
      <button class="btn-secondary btn-compact" type="button" id="qrCopyLinkBtn">Copy Link</button>
    </div>
  `);

  setTimeout(function () {
    renderProjectQR('qrModalTarget', projectCode, 180);
    var copyBtn = document.getElementById('qrCopyLinkBtn');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        navigator.clipboard.writeText(url).then(function () {
          if (typeof toast === 'function') toast('Link copied.');
        });
      });
    }
  }, 0);
}
