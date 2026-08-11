/* ============================================================
   assets/js/document-checklist.js — shared Document Checklist renderer,
   used by the 3 portals with a real project-detail modal today (admin,
   hope, engineer). Paired with api/document-checklist.php and
   includes/DocumentChecklist.php.

   Portal-agnostic: takes a container element id and a project id, fetches,
   renders. No actions to perform here (this pass is read-only — see the
   plan), so unlike task-center.js there's no need for per-portal
   toast/modal glue variables.
   ============================================================ */
const DOCUMENT_CHECKLIST_API = (window.BASE_PATH || '/') + 'api/document-checklist.php';
const DOCUMENT_CHECKLIST_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

const DOCUMENT_CHECKLIST_STATUS_META = {
  complete:     { icon: '✅', label: 'Complete',        class: 'dc-complete' },
  pending:      { icon: '🟠', label: 'Pending',         class: 'dc-pending' },
  missing:      { icon: '❌', label: 'Missing',         class: 'dc-missing' },
  under_review: { icon: '⏳', label: 'Under Review',    class: 'dc-under-review' },
  returned:     { icon: '⚠️', label: 'Returned',        class: 'dc-returned' },
  not_required: { icon: '🔵', label: 'Not Yet Required', class: 'dc-not-required' },
};

function dcEsc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[ch]));
}

async function documentChecklistInit(containerId, projectId) {
  const container = document.getElementById(containerId);
  if (!container || !projectId) return;

  container.innerHTML = '<p class="empty-state">Loading document checklist…</p>';

  try {
    const res = await fetch(`${DOCUMENT_CHECKLIST_API}?action=get&project_id=${encodeURIComponent(projectId)}`, {
      headers: DOCUMENT_CHECKLIST_CSRF_HEADERS,
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      container.innerHTML = `<p class="empty-state">${dcEsc(data.message || 'Failed to load document checklist.')}</p>`;
      return;
    }
    documentChecklistRender(container, data.items, data.summary, data.missing_required);
  } catch (e) {
    container.innerHTML = '<p class="empty-state">Failed to load document checklist.</p>';
    console.error(e);
  }
}

function documentChecklistRender(container, items, summary, missingRequired) {
  const categories = [];
  const byCategory = {};
  items.forEach((item) => {
    if (!byCategory[item.category]) {
      byCategory[item.category] = [];
      categories.push(item.category);
    }
    byCategory[item.category].push(item);
  });

  const alertHtml = missingRequired.length ? `
    <div class="dc-alert">
      <strong>⚠️ Documentation Issue</strong>
      <p>${missingRequired.length} required item${missingRequired.length === 1 ? '' : 's'} missing: ${missingRequired.map(i => dcEsc(i.label)).join(', ')}</p>
    </div>
  ` : '';

  container.innerHTML = `
    <div class="dc-summary">
      <div class="dc-progress-bar"><div class="dc-progress-fill" style="width:${summary.percent}%;"></div></div>
      <div class="dc-summary-line">
        <strong>${summary.percent}% Complete</strong>
        <span>${summary.complete} Complete · ${summary.pending} Pending · ${summary.under_review} Under Review · ${summary.missing} Missing · ${summary.returned} Returned · ${summary.not_required} Not Yet Required</span>
      </div>
    </div>
    ${alertHtml}
    <div class="dc-categories">
      ${categories.map(cat => `
        <div class="dc-category">
          <p class="dc-category-title">${dcEsc(cat)}</p>
          ${byCategory[cat].map(item => {
            const meta = DOCUMENT_CHECKLIST_STATUS_META[item.status] || DOCUMENT_CHECKLIST_STATUS_META.pending;
            return `
              <div class="dc-item ${meta.class}${item.info_only ? ' dc-info-only' : ''}">
                <span class="dc-item-icon">${meta.icon}</span>
                <span class="dc-item-label">${dcEsc(item.label)}</span>
                <span class="dc-item-status">${meta.label}</span>
                <span class="dc-item-detail">${dcEsc(item.detail)}</span>
              </div>
            `;
          }).join('')}
        </div>
      `).join('')}
    </div>
  `;
}
