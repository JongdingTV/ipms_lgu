/* Contractor portal frontend */
const CONTRACTOR_API = window.BASE_PATH + 'contractor/api/portal.php';
const USER_API = window.BASE_PATH + 'api/user.php';
const CONTRACTOR_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

let contractorState = {
  summary: null,
  projects: [],
  payments: [],
};

/* reports/documents accumulate over time, so they're fetched paginated,
   per-page, rather than bulk-loaded like the small/bounded lists above. */
let contractorListState = {
  reports: { page: 1, perPage: 10, search: '' },
  documents: { page: 1, perPage: 10, search: '' },
};

/* Bidding, accreditation, and performance data is fetched lazily the first
   time its own page is opened — see contractorShowPage() — rather than
   eagerly in contractorRefreshData(), since most visits to the portal won't
   touch these pages. */
let contractorBiddingState = { openBiddings: null, myBids: null };
let contractorAccreditationState = { documents: null };
let contractorPerformanceState = { data: null };

function contractorEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function contractorMoney(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function contractorFullMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function contractorDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

function contractorStatus(value) {
  return String(value || '').replaceAll('_', ' ');
}

function contractorBadge(value, label = null) {
  return `<span class="badge status-${contractorEscape(value)}">${contractorEscape(label || contractorStatus(value))}</span>`;
}

function contractorToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function contractorErrorFrom(data, response) {
  const err = new Error(data?.error || `HTTP ${response.status}`);
  err.fieldErrors = data?.errors || null;
  return err;
}

async function contractorGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const response = await fetch(`${CONTRACTOR_API}?${qs}`);
  const data = await response.json();
  if (!response.ok || data.error) throw contractorErrorFrom(data, response);
  return data;
}

async function contractorPostJson(action, body) {
  const response = await fetch(`${CONTRACTOR_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...CONTRACTOR_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) throw contractorErrorFrom(data, response);
  return data;
}

async function contractorPostForm(action, formData) {
  const response = await fetch(`${CONTRACTOR_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...CONTRACTOR_CSRF_HEADERS },
    body: formData,
  });
  const data = await response.json();
  if (!response.ok || data.error) throw contractorErrorFrom(data, response);
  return data;
}

/* ---- Inline field-error rendering -------------------------------------- */

function contractorClearFieldErrors(form) {
  form.querySelectorAll('.field-error-msg').forEach(el => el.remove());
  form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
}

function contractorShowFieldErrors(form, fieldErrors) {
  if (!fieldErrors) return;
  Object.entries(fieldErrors).forEach(([field, message]) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) return;
    input.classList.add('has-error');
    const msg = document.createElement('div');
    msg.className = 'field-error-msg';
    msg.textContent = message;
    input.insertAdjacentElement('afterend', msg);
  });
}

/* ---- Dynamic document rows (repeatable fields) ------------------------- */

const CONTRACTOR_DOCUMENT_TYPES = ['Accomplishment Photo', 'Delivery Receipt', 'Inspection Record', 'Billing Attachment', 'Other'];

function contractorDocRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <select class="form-input" name="documents[${index}][document_type]">
        ${CONTRACTOR_DOCUMENT_TYPES.map(type => `<option value="${contractorEscape(type)}">${contractorEscape(type)}</option>`).join('')}
      </select>
      <input class="form-input" type="text" name="documents[${index}][title]" placeholder="Document title">
      <input class="form-input" type="file" name="document_files[${index}]">
      <button type="button" class="doc-row-remove" aria-label="Remove document row">&times;</button>
    </div>
  `;
}

function contractorWireDocRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', contractorDocRowHtml(nextIndex));
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

function contractorOpenModal(title, html) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function contractorCloseModal() {
  document.getElementById('modalOverlay')?.classList.remove('open');
}

function contractorProjectOptions(selected = '') {
  if (!contractorState.projects.length) {
    return '<option value="">No assigned projects</option>';
  }

  return contractorState.projects.map(project => `
    <option value="${project.id}" ${String(selected) === String(project.id) ? 'selected' : ''}>
      ${contractorEscape(project.project_code)} - ${contractorEscape(project.name)}
    </option>
  `).join('');
}

function contractorProjectCard(project, compact = false) {
  const progress = Number(project.progress || 0);
  return `
    <article class="contractor-project-card" data-project-search="${contractorEscape(project.project_code + ' ' + project.name + ' ' + (project.location || ''))}">
      <div class="contractor-project-card-head">
        <div>
          <span class="contractor-project-code">${contractorEscape(project.project_code)}</span>
          <h3 class="contractor-project-title">${contractorEscape(project.name)}</h3>
          <p class="contractor-project-location">${contractorEscape(project.location || 'No location set')}</p>
        </div>
        ${contractorBadge(project.status)}
      </div>
      <div class="contractor-progress">
        <div class="contractor-progress-top">
          <span>Progress</span>
          <strong>${progress}%</strong>
        </div>
        <div class="contractor-progress-track">
          <div class="contractor-progress-fill" style="width:${Math.max(0, Math.min(100, progress))}%"></div>
        </div>
      </div>
      <div class="contractor-project-meta">
        <div class="contractor-meta-item"><span>Contract Value</span><strong>${contractorFullMoney(project.budget)}</strong></div>
        <div class="contractor-meta-item"><span>Schedule</span><strong>${contractorDate(project.start_date)} to ${contractorDate(project.end_date)}</strong></div>
        <div class="contractor-meta-item"><span>Latest Report</span><strong>${contractorDate(project.latest_report_date)}</strong></div>
      </div>
      ${compact ? '' : `
        <div class="contractor-card-actions">
          <button class="btn-secondary btn-compact" type="button" onclick="contractorOpenProject(${project.id})">Details</button>
          <button class="btn-primary btn-compact" type="button" onclick="contractorGoToReport(${project.id})">Submit Report</button>
        </div>
      `}
    </article>
  `;
}

let contractorStatusChartInst = null;

function contractorRenderStatusChart(stats) {
  const ctx = document.getElementById('contractorStatusChart')?.getContext('2d');
  if (!ctx) return;
  if (contractorStatusChartInst) contractorStatusChartInst.destroy();

  const assigned = Number(stats.assigned_projects || 0);
  const active = Number(stats.active_projects || 0);
  const delayed = Number(stats.delayed_projects || 0);
  const other = Math.max(0, assigned - active - delayed);

  const segments = [
    { label: 'Active', value: active, color: '#22c55e' },
    { label: 'Delayed', value: delayed, color: '#ef4444' },
    { label: 'Other', value: other, color: '#94a3b8' },
  ];

  contractorStatusChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: segments.map(s => s.label),
      datasets: [{
        data: segments.map(s => s.value),
        backgroundColor: segments.map(s => s.color),
        borderColor: segments.map(() => '#fff'), borderWidth: 3, hoverOffset: 6,
      }],
    },
    options: {
      responsive: false, cutout: '70%',
      animation: { duration: 900 },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` ${c.label}: ${c.raw}` } },
      },
    },
  });

  document.getElementById('contractorStatusChartTotal').textContent = assigned;
  document.getElementById('contractorStatusChartLegend').innerHTML = segments.map(s => `
    <div class="budget-legend-item">
      <span class="legend-dot" style="background:${s.color};"></span>
      <span>${s.label} <strong>${s.value}</strong></span>
    </div>
  `).join('');
}

function contractorRenderDashboard() {
  const stats = contractorState.summary?.stats || {};
  document.getElementById('contractorAssignedCount').textContent = stats.assigned_projects || 0;
  document.getElementById('contractorAverageProgress').textContent = `${stats.average_progress || 0}%`;
  document.getElementById('contractorReportsCount').textContent = stats.reports_submitted || 0;
  document.getElementById('contractorPendingPayment').textContent = contractorMoney(stats.pending_payment_amount || 0);
  document.getElementById('contractorPerformanceScore').textContent = contractorState.summary?.contractor?.performance_score ?? 0;

  // "Submit Report" only makes sense once a project has actually been won.
  const submitReportBtn = document.getElementById('contractorSubmitReportBtn');
  if (submitReportBtn) {
    submitReportBtn.style.display = contractorState.summary?.has_awarded_projects ? '' : 'none';
  }

  try {
    contractorRenderStatusChart(stats);
  } catch (error) {
    console.error('Failed to render status chart:', error);
  }

  contractorLoadProgressChart();

  contractorRenderStageWidgets();
}

let contractorProgressChartInst = null;

/* Line chart of the progress % reported in accomplishment reports, oldest
   first. Fetched separately from the summary since reports are paginated. */
async function contractorLoadProgressChart() {
  const canvas = document.getElementById('contractorProgressChart');
  const emptyNote = document.getElementById('contractorProgressChartEmpty');
  if (!canvas) return;

  let reports = [];
  try {
    const result = await contractorGet('reports', { page: 1, per_page: 50 });
    reports = (result.data || []).slice().reverse();
  } catch (error) {
    console.error('Failed to load reports for progress chart:', error);
  }

  if (!reports.length) {
    canvas.style.display = 'none';
    if (emptyNote) emptyNote.style.display = '';
    return;
  }
  canvas.style.display = '';
  if (emptyNote) emptyNote.style.display = 'none';

  if (contractorProgressChartInst) contractorProgressChartInst.destroy();
  const gridColor = document.documentElement.getAttribute('data-theme') === 'dark'
    ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)';

  contractorProgressChartInst = new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: {
      labels: reports.map(r => contractorDate(r.report_date)),
      datasets: [{
        label: 'Reported progress',
        data: reports.map(r => Number(r.progress_percent) || 0),
        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.08)',
        borderWidth: 2.5, tension: 0.35, fill: true,
        pointBackgroundColor: '#3b82f6', pointBorderColor: '#fff',
        pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          callbacks: {
            title: items => `${reports[items[0].dataIndex]?.project_code || ''} — ${items[0].label}`,
            label: c => ` ${c.raw}% complete`,
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 }, maxTicksLimit: 8 }, border: { display: false } },
        y: { min: 0, max: 100, ticks: { stepSize: 25, color: '#94a3b8', font: { size: 11 }, callback: v => v + '%' }, grid: { color: gridColor }, border: { display: false } },
      },
    },
  });
}

/**
 * The dashboard shows a different widget set depending on whether the
 * contractor has ever won a project (summary.has_awarded_projects) —
 * pre-award, the priority is accreditation/bidding; post-award, it's
 * execution/payment. See contractor/api/portal.php's contractorPortalPreAwardStage()/
 * contractorPortalPostAwardStage() for where stage_data comes from.
 */
function contractorRenderStageWidgets() {
  const container = document.getElementById('contractorStageWidgets');
  if (!container) return;

  const hasAwarded = Boolean(contractorState.summary?.has_awarded_projects);
  const stageData = contractorState.summary?.stage_data || {};

  if (!hasAwarded) {
    container.innerHTML = `
      <article class="contractor-panel">
        <div class="contractor-panel-head">
          <h2>Accreditation Status</h2>
          <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('accreditation-status')">View</button>
        </div>
        <div class="contractor-mini-list">
          <div class="contractor-mini-row"><span>Status</span><strong>${contractorEscape(contractorStatus(contractorState.summary?.contractor?.application_status))}</strong></div>
          <div class="contractor-mini-row"><span>PCAB Classification</span><strong>${contractorEscape(contractorState.summary?.contractor?.pcab_classification || 'Not on file')}</strong></div>
        </div>
      </article>
      <article class="contractor-panel contractor-panel-wide">
        <div class="contractor-panel-head">
          <h2>Available Bidding Projects</h2>
          <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('open-biddings')">Browse</button>
        </div>
        <div class="contractor-mini-list">
          <div class="contractor-mini-row"><span>Open for bidding</span><strong>${Number(stageData.open_biddings_count || 0)}</strong></div>
          <div class="contractor-mini-row"><span>Your submitted bids</span><strong>${Number(stageData.submitted_bids_count || 0)}</strong></div>
        </div>
      </article>
      <article class="contractor-panel">
        <div class="contractor-panel-head">
          <h2>Recent Bid Results</h2>
          <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('bid-results')">View All</button>
        </div>
        <div class="contractor-mini-list">
          ${(stageData.recent_results || []).length ? stageData.recent_results.map(bid => `
            <div class="contractor-mini-row">
              <span>${contractorEscape(bid.project_code)} - ${contractorEscape(bid.project_name)}</span>
              ${contractorBadge(bid.status)}
            </div>
          `).join('') : '<p class="empty-state">No bid decisions yet.</p>'}
        </div>
      </article>
    `;
    return;
  }

  container.innerHTML = `
    <article class="contractor-panel contractor-panel-wide">
      <div class="contractor-panel-head">
        <h2>Assigned Projects</h2>
        <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('assigned-projects')">View All</button>
      </div>
      <div id="contractorProjectPreview" class="contractor-project-list">
        ${contractorState.projects.length ? contractorState.projects.slice(0, 3).map(project => contractorProjectCard(project, true)).join('') : '<p class="empty-state">No assigned projects yet.</p>'}
      </div>
    </article>
    <article class="contractor-panel">
      <div class="contractor-panel-head">
        <h2>Upcoming &amp; Pending</h2>
      </div>
      <div class="contractor-mini-list">
        <div class="contractor-mini-row"><span>Upcoming deadline</span><strong>${stageData.upcoming_deadline ? contractorDate(stageData.upcoming_deadline) : 'None scheduled'}</strong></div>
        <div class="contractor-mini-row"><span>Pending inspections</span><strong>${Number(stageData.pending_inspections_count || 0)}</strong></div>
        <div class="contractor-mini-row"><span>Pending payment requests</span><strong>${Number(stageData.pending_payment_requests_count || 0)}</strong></div>
      </div>
    </article>
    <article class="contractor-panel">
      <div class="contractor-panel-head">
        <h2>Payment Status</h2>
        <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('payment-status')">Review</button>
      </div>
      <div id="contractorPaymentPreview" class="contractor-mini-list">
        ${contractorState.payments.length ? contractorState.payments.slice(0, 5).map(payment => `
          <div class="contractor-mini-row">
            <span>${contractorEscape(payment.project_code)} - ${contractorEscape(payment.name)}</span>
            <strong>${contractorFullMoney(payment.balance_amount)}</strong>
          </div>
        `).join('') : '<p class="empty-state">No payment records yet.</p>'}
      </div>
    </article>
  `;
}

function contractorRenderAssignedProjects() {
  const page = document.getElementById('page-assigned-projects');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Assigned Projects</h1>
        <p class="contractor-scope-note">Only projects assigned to your contractor profile are shown.</p>
      </div>
      <button class="btn-primary" type="button" onclick="contractorGoToReport()">Submit Report</button>
    </div>
    <div id="contractorAssignedGrid" class="contractor-stack">
      ${contractorState.projects.length ? contractorState.projects.map(project => contractorProjectCard(project)).join('') : '<p class="empty-state">No assigned projects yet.</p>'}
    </div>
  `;
}

function contractorRenderReportPage(selectedProjectId = '') {
  const page = document.getElementById('page-accomplishment-report');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Submit Accomplishment Report</h1>
        <p class="contractor-scope-note">Report progress for one assigned project at a time.</p>
      </div>
    </div>
    <section class="contractor-layout">
      <article class="contractor-form-card">
        <h2>Progress Update</h2>
        <form id="contractorReportForm">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${contractorProjectOptions(selectedProjectId)}</select>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>Report Date</label>
              <input class="form-input" type="date" name="report_date" required value="${new Date().toISOString().slice(0, 10)}">
            </div>
            <div class="form-group">
              <label>Progress Percent</label>
              <input class="form-input" type="number" min="0" max="100" name="progress_percent" required placeholder="0">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Accomplishments</label>
            <textarea class="form-input" name="accomplishments" rows="5" required placeholder="Completed work, quantities, and site updates"></textarea>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Issues or Delays</label>
            <textarea class="form-input" name="issues" rows="3" placeholder="Optional"></textarea>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Next Steps</label>
            <textarea class="form-input" name="next_steps" rows="3" placeholder="Optional"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Submit Report</button>
          </div>
        </form>
      </article>
      <article class="contractor-history-card">
        <h2>Recent Reports</h2>
        <label class="list-search" style="max-width:none;margin-bottom:10px;">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="contractorReportsSearch" placeholder="Search project or accomplishments...">
        </label>
        <div id="contractorReportsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="contractorReportsPager"></div>
      </article>
    </section>
  `;

  document.getElementById('contractorReportForm').addEventListener('submit', contractorSubmitReport);
  document.getElementById('contractorReportsSearch').addEventListener('input', debounce(() => {
    contractorListState.reports.search = document.getElementById('contractorReportsSearch').value.trim();
    contractorListState.reports.page = 1;
    contractorLoadReportsList();
  }, 300));
  contractorLoadReportsList();
}

async function contractorLoadReportsList() {
  const container = document.getElementById('contractorReportsList');
  const pager = document.getElementById('contractorReportsPager');
  if (!container) return;
  const state = contractorListState.reports;

  try {
    const result = await contractorGet('reports', { page: state.page, per_page: state.perPage, search: state.search });

    container.innerHTML = result.data.length ? result.data.map(report => `
      <div class="contractor-mini-row">
        <span>${contractorEscape(report.project_code)} - ${contractorDate(report.report_date)}</span>
        <strong>${Number(report.progress_percent || 0)}%</strong>
      </div>
    `).join('') : '<p class="empty-state">No reports submitted yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { contractorListState.reports.page = nextPage; contractorLoadReportsList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load reports.</p>';
  }
}

function contractorRenderDocumentsPage() {
  const page = document.getElementById('page-supporting-documents');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Upload Supporting Documents</h1>
        <p class="contractor-scope-note">Attach documents only to your assigned projects.</p>
      </div>
    </div>
    <section class="contractor-layout">
      <article class="contractor-form-card">
        <h2>New Documents</h2>
        <form id="contractorDocumentForm" enctype="multipart/form-data">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${contractorProjectOptions()}</select>
          </div>
          <div class="doc-section">
            <label>Documents</label>
            <div class="doc-rows" id="docRows">${contractorDocRowHtml(0)}</div>
            <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another document</button>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Remarks</label>
            <textarea class="form-input" name="remarks" rows="3" placeholder="Optional, applies to this whole batch"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Upload Document(s)</button>
          </div>
        </form>
      </article>
      <article class="contractor-history-card">
        <h2>Uploaded Documents</h2>
        <label class="list-search" style="max-width:none;margin-bottom:10px;">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="contractorDocumentsSearch" placeholder="Search project or document title...">
        </label>
        <div id="contractorDocumentsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="contractorDocumentsPager"></div>
      </article>
    </section>
  `;

  contractorWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));
  document.getElementById('contractorDocumentForm').addEventListener('submit', contractorSubmitDocument);
  document.getElementById('contractorDocumentsSearch').addEventListener('input', debounce(() => {
    contractorListState.documents.search = document.getElementById('contractorDocumentsSearch').value.trim();
    contractorListState.documents.page = 1;
    contractorLoadDocumentsList();
  }, 300));
  contractorLoadDocumentsList();
}

async function contractorLoadDocumentsList() {
  const container = document.getElementById('contractorDocumentsList');
  const pager = document.getElementById('contractorDocumentsPager');
  if (!container) return;
  const state = contractorListState.documents;

  try {
    const result = await contractorGet('documents', { page: state.page, per_page: state.perPage, search: state.search });

    container.innerHTML = result.data.length ? result.data.map(doc => `
      <div class="contractor-mini-row">
        <span>${contractorEscape(doc.project_code)} - ${contractorEscape(doc.title)}</span>
        <a class="document-link" href="${window.BASE_PATH}${contractorEscape(doc.file_path)}" target="_blank" rel="noopener">Open</a>
      </div>
    `).join('') : '<p class="empty-state">No documents uploaded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { contractorListState.documents.page = nextPage; contractorLoadDocumentsList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load documents.</p>';
  }
}

function contractorRenderContractDetails() {
  const page = document.getElementById('page-contract-details');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">View Contract Details</h1>
        <p class="contractor-scope-note">Contract information is limited to assigned projects.</p>
      </div>
    </div>
    <div class="contractor-stack">
      ${contractorState.projects.length ? contractorState.projects.map(project => `
        <article class="contractor-panel">
          <div class="contractor-panel-head">
            <h2>${contractorEscape(project.project_code)} - ${contractorEscape(project.name)}</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="contractorOpenProject(${project.id})">View Details</button>
          </div>
          <div class="contractor-detail-grid">
            <div class="contractor-detail-box"><span>Location</span><strong>${contractorEscape(project.location || '-')}</strong></div>
            <div class="contractor-detail-box"><span>Contract No.</span><strong>${contractorEscape(project.contract_no || 'Pending')}</strong></div>
            <div class="contractor-detail-box"><span>Contract Value</span><strong>${contractorFullMoney(project.contract_amount || project.budget)}</strong></div>
            <div class="contractor-detail-box"><span>Schedule</span><strong>${contractorDate(project.start_date)} to ${contractorDate(project.end_date)}</strong></div>
            <div class="contractor-detail-box"><span>Contract Status</span><strong>${contractorStatus(project.contract_status || project.status)}</strong></div>
            <div class="contractor-detail-box"><span>Progress</span><strong>${Number(project.progress || 0)}%</strong></div>
            <div class="contractor-detail-box"><span>Milestones</span><strong>${Number(project.milestone_count || 0)}</strong></div>
          </div>
        </article>
      `).join('') : '<p class="empty-state">No contract details available yet.</p>'}
    </div>
  `;
}

function contractorRenderPaymentStatus() {
  const page = document.getElementById('page-payment-status');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">View Payment Status</h1>
        <p class="contractor-scope-note">Payment requests are linked to your latest submitted progress report.</p>
      </div>
      <button class="btn-primary" type="button" onclick="contractorOpenPaymentRequestForm()">Request Payment</button>
    </div>
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Project</th>
            <th>Progress</th>
            <th>Contract Value</th>
            <th>Eligible</th>
            <th>Released</th>
            <th>Amount / Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          ${contractorState.payments.length ? contractorState.payments.map(payment => `
            <tr>
              <td><strong>${contractorEscape(payment.project_code)}</strong><br><span class="contractor-scope-note">${contractorEscape(payment.name)}</span></td>
              <td>${Number(payment.progress || 0)}%</td>
              <td>${contractorFullMoney(payment.budget)}</td>
              <td>${contractorFullMoney(payment.eligible_amount)}</td>
              <td>${contractorFullMoney(payment.released_amount)}</td>
              <td><strong>${contractorFullMoney(payment.requested_amount || payment.balance_amount)}</strong><br><span class="contractor-scope-note">${contractorEscape(payment.billing_no || payment.source || '')}</span></td>
              <td>${contractorBadge(payment.status, payment.label)}</td>
            </tr>
          `).join('') : '<tr><td colspan="7"><p class="empty-state">No payment records yet.</p></td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

/* ---- Accreditation ------------------------------------------------------ */

function contractorRenderCompanyProfile() {
  const page = document.getElementById('page-company-profile');
  const c = contractorState.summary?.contractor || {};
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Company Profile</h1>
        <p class="contractor-scope-note">Business information on file with BAC. Name, PCAB details, and accreditation status are controlled by BAC — only your contact details can be updated here.</p>
      </div>
    </div>
    <section class="contractor-layout">
      <article class="contractor-form-card">
        <h2>Business Information</h2>
        <div class="contractor-detail-grid">
          <div class="contractor-detail-box"><span>Company Name</span><strong>${contractorEscape(c.name)}</strong></div>
          <div class="contractor-detail-box"><span>PCAB License No.</span><strong>${contractorEscape(c.pcab_license_no || 'Not on file')}</strong></div>
          <div class="contractor-detail-box"><span>PCAB Classification</span><strong>${contractorEscape(c.pcab_classification || 'Not on file')}</strong></div>
        </div>
        <h2 style="margin-top:20px;">Contact Details</h2>
        <form id="contractorCompanyProfileForm">
          <div class="form-grid">
            <div class="form-group">
              <label>Contact Person</label>
              <input class="form-input" name="contact_person" value="${contractorEscape(c.contact_person)}">
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input class="form-input" name="phone" value="${contractorEscape(c.phone)}">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Address</label>
            <textarea class="form-input" name="address" rows="2">${contractorEscape(c.address)}</textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Contact Details</button>
          </div>
        </form>
      </article>
    </section>
  `;

  document.getElementById('contractorCompanyProfileForm').addEventListener('submit', contractorSubmitCompanyProfile);
}

async function contractorSubmitCompanyProfile(event) {
  event.preventDefault();
  const formEl = event.target;
  contractorClearFieldErrors(formEl);
  const form = new FormData(formEl);
  try {
    await contractorPostJson('update_profile', {
      contact_person: form.get('contact_person'),
      phone: form.get('phone'),
      address: form.get('address'),
    });
    contractorToast('Contact details updated.');
    await contractorRefreshData();
    contractorRenderCompanyProfile();
  } catch (error) {
    contractorShowFieldErrors(formEl, error.fieldErrors);
    contractorToast(error.message, 'error');
  }
}

function contractorRenderAccreditationStatus() {
  const page = document.getElementById('page-accreditation-status');
  const c = contractorState.summary?.contractor || {};
  const isBlacklisted = Number(c.is_blacklisted || 0) === 1;

  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Accreditation Status</h1>
        <p class="contractor-scope-note">Your standing with the Bids and Awards Committee.</p>
      </div>
    </div>
    ${isBlacklisted ? `
      <div class="empty-state" style="background:#fef2f2;color:#991b1b;border-radius:8px;padding:16px;margin-bottom:16px;">
        <strong>Blacklisted</strong> — ${contractorEscape(c.blacklist_reason || 'No reason on file.')}
        ${c.blacklist_date ? `<br><small>Since ${contractorDate(c.blacklist_date)}</small>` : ''}
      </div>
    ` : ''}
    <div class="contractor-detail-grid">
      <div class="contractor-detail-box"><span>Application Status</span><strong>${contractorEscape(contractorStatus(c.application_status))}</strong></div>
      <div class="contractor-detail-box"><span>Account Status</span><strong>${contractorEscape(contractorStatus(c.status))}</strong></div>
      <div class="contractor-detail-box"><span>PCAB License No.</span><strong>${contractorEscape(c.pcab_license_no || 'Not on file')}</strong></div>
      <div class="contractor-detail-box"><span>PCAB Classification</span><strong>${contractorEscape(c.pcab_classification || 'Not on file')}</strong></div>
      <div class="contractor-detail-box"><span>Accredited Since</span><strong>${contractorDate(c.created_at)}</strong></div>
    </div>
  `;
}

const ACCREDITATION_DOCUMENT_TYPES = ['Business Permit', 'DTI/SEC Registration', 'Tax Clearance', 'PCAB License', 'Audited Financial Statement', 'Other'];

function contractorAccreditationDocRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <select class="form-input" name="documents[${index}][document_type]">
        ${ACCREDITATION_DOCUMENT_TYPES.map(type => `<option value="${contractorEscape(type)}">${contractorEscape(type)}</option>`).join('')}
      </select>
      <input class="form-input" type="text" name="documents[${index}][title]" placeholder="Document title">
      <input class="form-input" type="file" name="document_files[${index}]">
      <button type="button" class="doc-row-remove" aria-label="Remove document row">&times;</button>
    </div>
  `;
}

async function contractorRenderAccreditationDocuments() {
  const page = document.getElementById('page-accreditation-documents');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Accreditation Documents</h1>
        <p class="contractor-scope-note">Documents submitted with your original application, plus any renewals (e.g. an updated business permit) you upload here for BAC's review.</p>
      </div>
    </div>
    <section class="contractor-layout">
      <article class="contractor-form-card">
        <h2>Upload a Renewal or Update</h2>
        <form id="contractorAccreditationDocForm" enctype="multipart/form-data">
          <div class="doc-section">
            <label>Documents</label>
            <div class="doc-rows" id="accreditationDocRows">${contractorAccreditationDocRowHtml(0)}</div>
            <button type="button" class="doc-add-btn" id="accreditationDocAddBtn">+ Add another document</button>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Submit for Review</button>
          </div>
        </form>
      </article>
      <article class="contractor-history-card">
        <h2>Documents on File</h2>
        <div id="contractorAccreditationDocsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
      </article>
    </section>
  `;

  contractorWireDocRows(document.getElementById('accreditationDocRows'), document.getElementById('accreditationDocAddBtn'));
  document.getElementById('contractorAccreditationDocForm').addEventListener('submit', contractorSubmitAccreditationDocument);
  await contractorLoadAccreditationDocuments();
}

async function contractorLoadAccreditationDocuments() {
  const container = document.getElementById('contractorAccreditationDocsList');
  if (!container) return;
  try {
    const result = await contractorGet('accreditation_documents');
    contractorAccreditationState.documents = result.data || [];
    container.innerHTML = contractorAccreditationState.documents.length ? contractorAccreditationState.documents.map(doc => `
      <div class="contractor-mini-row">
        <span>${contractorEscape(doc.document_type)} - ${contractorEscape(doc.title)}</span>
        <span>${contractorBadge(doc.status)} <a class="document-link" href="${window.BASE_PATH}${contractorEscape(doc.file_path)}" target="_blank" rel="noopener">Open</a></span>
      </div>
    `).join('') : '<p class="empty-state">No accreditation documents on file.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load documents.</p>';
  }
}

async function contractorSubmitAccreditationDocument(event) {
  event.preventDefault();
  const formEl = event.target;
  contractorClearFieldErrors(formEl);
  const form = new FormData(formEl);
  try {
    await contractorPostForm('upload_accreditation_document', form);
    contractorToast('Document(s) submitted for BAC review.');
    formEl.reset();
    await contractorLoadAccreditationDocuments();
  } catch (error) {
    contractorShowFieldErrors(formEl, error.fieldErrors);
    contractorToast(error.message, 'error');
  }
}

/* ---- Procurement / Bidding ----------------------------------------------- */

async function contractorRenderOpenBiddings() {
  const page = document.getElementById('page-open-biddings');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Available Bidding Projects</h1>
        <p class="contractor-scope-note">Projects currently open for bidding, with their submission deadline.</p>
      </div>
    </div>
    <div id="contractorOpenBiddingsList" class="contractor-stack"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorOpenBiddingsList');
  try {
    const result = await contractorGet('list_open_biddings');
    contractorBiddingState.openBiddings = result.data || [];

    container.innerHTML = contractorBiddingState.openBiddings.length ? contractorBiddingState.openBiddings.map(item => `
      <article class="contractor-panel">
        <div class="contractor-panel-head">
          <h2>${contractorEscape(item.project_code)} - ${contractorEscape(item.name)}</h2>
          ${item.my_bid_id ? contractorBadge(item.my_bid_status, 'Bid submitted') : ''}
        </div>
        <div class="contractor-detail-grid">
          <div class="contractor-detail-box"><span>Location</span><strong>${contractorEscape(item.location || '-')}</strong></div>
          <div class="contractor-detail-box"><span>Approved Budget</span><strong>${contractorFullMoney(item.budget)}</strong></div>
          <div class="contractor-detail-box"><span>Reference No.</span><strong>${contractorEscape(item.reference_no)}</strong></div>
          <div class="contractor-detail-box"><span>Bid Deadline</span><strong>${item.deadline ? contractorDate(item.deadline) : 'No deadline set'}</strong></div>
        </div>
        <div class="contractor-card-actions">
          ${item.my_bid_id
            ? `<span class="contractor-scope-note">Your bid: ${contractorFullMoney(item.my_bid_amount)}</span>`
            : `<button class="btn-primary btn-compact" type="button" onclick="contractorOpenBidForm(${item.project_id})">Submit Bid</button>`}
        </div>
      </article>
    `).join('') : '<p class="empty-state">No projects are currently open for bidding.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load open biddings.</p>';
  }
}

function contractorOpenBidForm(projectId) {
  const item = (contractorBiddingState.openBiddings || []).find(p => p.project_id === projectId);
  if (!item) {
    contractorToast('Project not found.', 'error');
    return;
  }

  contractorOpenModal(`Submit Bid — ${item.project_code}`, `
    <form id="contractorBidForm">
      <div class="contractor-decision-list" style="margin-bottom:12px;">
        <div class="contractor-detail-box"><span>Project</span><strong>${contractorEscape(item.name)}</strong></div>
        <div class="contractor-detail-box"><span>Approved Budget</span><strong>${contractorFullMoney(item.budget)}</strong></div>
      </div>
      <div class="form-group">
        <label>Bid Amount</label>
        <input class="form-input" type="number" min="1" step="0.01" name="bid_amount" required>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Proposed Delivery Days</label>
        <input class="form-input" type="number" min="1" name="delivery_days" placeholder="Optional">
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Remarks</label>
        <textarea class="form-input" name="remarks" rows="3" placeholder="Optional"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="contractorCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Submit Bid</button>
      </div>
    </form>
  `);

  document.getElementById('contractorBidForm').addEventListener('submit', async event => {
    event.preventDefault();
    const formEl = event.target;
    contractorClearFieldErrors(formEl);
    const form = new FormData(formEl);
    try {
      await contractorPostJson('submit_bid', {
        project_id: projectId,
        bid_amount: form.get('bid_amount'),
        delivery_days: form.get('delivery_days'),
        remarks: form.get('remarks'),
      });
      contractorCloseModal();
      contractorToast('Bid submitted.');
      await contractorRenderOpenBiddings();
    } catch (error) {
      contractorShowFieldErrors(formEl, error.fieldErrors);
      contractorToast(error.message, 'error');
    }
  });
}

async function contractorEnsureMyBidsLoaded() {
  if (contractorBiddingState.myBids === null) {
    const result = await contractorGet('my_bids');
    contractorBiddingState.myBids = result.data || [];
  }
  return contractorBiddingState.myBids;
}

function contractorBidRow(bid) {
  return `
    <div class="contractor-mini-row">
      <span>${contractorEscape(bid.project_code)} - ${contractorEscape(bid.project_name)} (${contractorFullMoney(bid.bid_amount)})</span>
      ${contractorBadge(bid.status)}
    </div>
  `;
}

async function contractorRenderMyBids() {
  const page = document.getElementById('page-my-bids');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">My Submitted Bids</h1>
        <p class="contractor-scope-note">Bids you've submitted that BAC hasn't decided on yet.</p>
      </div>
    </div>
    <div id="contractorMyBidsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorMyBidsList');
  try {
    const bids = await contractorEnsureMyBidsLoaded();
    const pending = bids.filter(b => ['submitted', 'for_review'].includes(b.status));
    container.innerHTML = pending.length ? pending.map(contractorBidRow).join('') : '<p class="empty-state">No pending bids.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load bids.</p>';
  }
}

async function contractorRenderBidResults() {
  const page = document.getElementById('page-bid-results');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Bid Results</h1>
        <p class="contractor-scope-note">Bids BAC has already decided on.</p>
      </div>
    </div>
    <div id="contractorBidResultsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorBidResultsList');
  try {
    const bids = await contractorEnsureMyBidsLoaded();
    const decided = bids.filter(b => ['recommended', 'rejected'].includes(b.status));
    container.innerHTML = decided.length ? decided.map(contractorBidRow).join('') : '<p class="empty-state">No bid decisions yet.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load bid results.</p>';
  }
}

/* ---- Projects ------------------------------------------------------------ */

async function contractorRenderProjectTimeline(selectedProjectId = '') {
  const page = document.getElementById('page-project-timeline');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Project Timeline</h1>
        <p class="contractor-scope-note">Milestone schedule for one assigned project at a time.</p>
      </div>
    </div>
    <div class="form-group" style="max-width:420px;">
      <label>Project</label>
      <select class="form-input" id="contractorTimelineProjectSelect">${contractorProjectOptions(selectedProjectId)}</select>
    </div>
    <div id="contractorTimelineBody" class="contractor-stack"></div>
  `;

  const select = document.getElementById('contractorTimelineProjectSelect');
  const loadTimeline = async projectId => {
    const body = document.getElementById('contractorTimelineBody');
    if (!projectId) {
      body.innerHTML = '<p class="empty-state">No assigned projects yet.</p>';
      return;
    }
    body.innerHTML = '<p class="empty-state">Loading...</p>';
    try {
      const response = await contractorGet('project', { id: projectId });
      const project = response.data;
      body.innerHTML = `
        <article class="contractor-panel">
          <div class="contractor-detail-grid">
            <div class="contractor-detail-box"><span>Start Date</span><strong>${contractorDate(project.start_date)}</strong></div>
            <div class="contractor-detail-box"><span>Target End Date</span><strong>${contractorDate(project.end_date)}</strong></div>
            <div class="contractor-detail-box"><span>Status</span><strong>${contractorStatus(project.status)}</strong></div>
          </div>
          <h4 style="margin:16px 0 8px;color:#1e293b;">Milestones</h4>
          <div class="contractor-mini-list">
            ${project.milestones.length ? project.milestones.map(m => `
              <div class="contractor-mini-row">
                <span>${contractorEscape(m.title)} — due ${contractorDate(m.due_date)}</span>
                <strong>${Number(m.completed) === 1 ? 'Done' : 'Open'}</strong>
              </div>
            `).join('') : '<p class="empty-state">No milestones recorded for this project.</p>'}
          </div>
        </article>
      `;
    } catch (error) {
      body.innerHTML = '<p class="empty-state">Unable to load project timeline.</p>';
    }
  };

  select.addEventListener('change', () => loadTimeline(select.value));
  await loadTimeline(select.value);
}

/* ---- Project Execution ---------------------------------------------------- */

async function contractorRenderProgressUpdates() {
  const page = document.getElementById('page-progress-updates');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Progress Updates</h1>
        <p class="contractor-scope-note">A quick chronological view of your logged progress. Use Accomplishment Reports to submit a new one.</p>
      </div>
      <button class="btn-primary" type="button" onclick="contractorShowPage('accomplishment-report')">Submit New Report</button>
    </div>
    <div id="contractorProgressUpdatesList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorProgressUpdatesList');
  try {
    const result = await contractorGet('reports', { page: 1, per_page: 25 });
    container.innerHTML = result.data.length ? result.data.map(report => `
      <div class="contractor-mini-row">
        <span>${contractorDate(report.report_date)} — ${contractorEscape(report.project_code)}</span>
        <strong>${Number(report.progress_percent || 0)}%</strong>
      </div>
    `).join('') : '<p class="empty-state">No progress logged yet.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load progress updates.</p>';
  }
}

async function contractorRenderSitePhotos() {
  const page = document.getElementById('page-site-photos');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Site Photos</h1>
        <p class="contractor-scope-note">Accomplishment photos uploaded through Supporting Documents.</p>
      </div>
      <button class="btn-primary" type="button" onclick="contractorShowPage('supporting-documents')">Upload Photos</button>
    </div>
    <div id="contractorSitePhotosGrid" class="contractor-photo-grid"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorSitePhotosGrid');
  try {
    const result = await contractorGet('documents', { page: 1, per_page: 50, type: 'Accomplishment Photo' });
    container.innerHTML = result.data.length ? result.data.map(doc => `
      <a class="contractor-photo-card" href="${window.BASE_PATH}${contractorEscape(doc.file_path)}" target="_blank" rel="noopener">
        <img src="${window.BASE_PATH}${contractorEscape(doc.file_path)}" alt="${contractorEscape(doc.title)}" loading="lazy">
        <span>${contractorEscape(doc.project_code)} — ${contractorEscape(doc.title)}</span>
      </a>
    `).join('') : '<p class="empty-state">No site photos uploaded yet.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load site photos.</p>';
  }
}

/* ---- Payments -------------------------------------------------------------- */

function contractorRenderPaymentRequests() {
  const page = document.getElementById('page-payment-requests');
  const pending = contractorState.payments.filter(p => p.source === 'request' && ['submitted', 'under_review'].includes(p.status));

  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Payment Requests</h1>
        <p class="contractor-scope-note">Requests you've made that are still awaiting review.</p>
      </div>
      <button class="btn-primary" type="button" onclick="contractorOpenPaymentRequestForm()">Request Payment</button>
    </div>
    <div class="contractor-mini-list">
      ${pending.length ? pending.map(payment => `
        <div class="contractor-mini-row">
          <span>${contractorEscape(payment.project_code)} - ${contractorEscape(payment.billing_no || '')}</span>
          <span>${contractorFullMoney(payment.requested_amount)} ${contractorBadge(payment.status, payment.label)}</span>
        </div>
      `).join('') : '<p class="empty-state">No pending payment requests.</p>'}
    </div>
  `;
}

function contractorRenderPaymentHistory() {
  const page = document.getElementById('page-payment-history');
  const history = contractorState.payments.filter(p => p.source === 'request' && ['paid', 'rejected'].includes(p.status));

  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Payment History</h1>
        <p class="contractor-scope-note">Payment requests that have already been decided.</p>
      </div>
    </div>
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>Project</th><th>Billing No.</th><th>Amount</th><th>Submitted</th><th>Status</th></tr></thead>
        <tbody>
          ${history.length ? history.map(payment => `
            <tr>
              <td>${contractorEscape(payment.project_code)}</td>
              <td>${contractorEscape(payment.billing_no || '-')}</td>
              <td>${contractorFullMoney(payment.requested_amount)}</td>
              <td>${contractorDate(payment.submitted_at)}</td>
              <td>${contractorBadge(payment.status, payment.label)}</td>
            </tr>
          `).join('') : '<tr><td colspan="5"><p class="empty-state">No decided payment requests yet.</p></td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

/* ---- Performance ------------------------------------------------------------ */

async function contractorEnsurePerformanceLoaded() {
  if (contractorPerformanceState.data === null) {
    contractorPerformanceState.data = await contractorGet('performance');
  }
  return contractorPerformanceState.data;
}

async function contractorRenderPerformanceRating() {
  const page = document.getElementById('page-performance-rating');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Performance Rating</h1>
        <p class="contractor-scope-note">Modeled on DPWH's Constructors' Performance Evaluation System.</p>
      </div>
    </div>
    <div id="contractorPerformanceBody"><p class="empty-state">Loading...</p></div>
  `;

  const body = document.getElementById('contractorPerformanceBody');
  try {
    const data = await contractorEnsurePerformanceLoaded();
    const componentLabels = { completion: 'Completion Rate', delay: 'Delay Record', issues: 'Issue Reports', financial: 'Financial Discipline' };
    body.innerHTML = `
      <div class="contractor-detail-grid">
        <div class="contractor-detail-box"><span>Overall Score</span><strong>${data.score}/100</strong></div>
      </div>
      <h4 style="margin:16px 0 8px;color:#1e293b;">Score Breakdown</h4>
      <div class="contractor-mini-list">
        ${Object.entries(data.components).map(([key, value]) => `
          <div class="contractor-mini-row">
            <span>${componentLabels[key] || key}</span>
            <strong>${value.earned === null ? 'Not yet rated' : `${value.earned} / ${value.weight}`}</strong>
          </div>
        `).join('')}
      </div>
    `;
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Unable to load performance rating.</p>';
  }
}

async function contractorRenderComplianceRecords() {
  const page = document.getElementById('page-compliance-records');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Compliance Records</h1>
        <p class="contractor-scope-note">Standing indicators BAC and Admin consider during procurement.</p>
      </div>
    </div>
    <div id="contractorComplianceBody"><p class="empty-state">Loading...</p></div>
  `;

  const body = document.getElementById('contractorComplianceBody');
  try {
    const data = await contractorEnsurePerformanceLoaded();
    body.innerHTML = `
      ${data.is_blacklisted ? `
        <div class="empty-state" style="background:#fef2f2;color:#991b1b;border-radius:8px;padding:16px;margin-bottom:16px;">
          <strong>Blacklisted</strong> — ${contractorEscape(data.blacklist_reason || 'No reason on file.')}
        </div>
      ` : ''}
      <div class="contractor-detail-grid">
        <div class="contractor-detail-box"><span>Credibility Score</span><strong>${data.credibility_score} / 5.00</strong></div>
        <div class="contractor-detail-box"><span>Delay Reports Logged</span><strong>${data.delay_report_count}</strong></div>
        <div class="contractor-detail-box"><span>Open Issue Reports</span><strong>${data.open_issue_count}</strong></div>
      </div>
    `;
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Unable to load compliance records.</p>';
  }
}

/* ---- Notifications & Profile ------------------------------------------------ */

async function contractorRenderNotificationsPage() {
  const page = document.getElementById('page-notifications');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Notifications</h1>
        <p class="contractor-scope-note">Every alert sent to your account.</p>
      </div>
    </div>
    <div id="contractorNotificationsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
  `;

  const container = document.getElementById('contractorNotificationsList');
  try {
    const response = await fetch(`${window.BASE_PATH}api/notifications.php?per_page=30`, { headers: CONTRACTOR_CSRF_HEADERS });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Unable to load notifications.');

    container.innerHTML = (result.data || []).length ? result.data.map(notif => `
      <div class="contractor-mini-row">
        <span>${notif.is_read ? '' : '<strong>&bull;</strong> '}${contractorEscape(notif.title)} — ${contractorEscape(notif.message)}</span>
        <span class="contractor-scope-note">${contractorDate(notif.created_at)}</span>
      </div>
    `).join('') : '<p class="empty-state">No notifications yet.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load notifications.</p>';
  }
}

async function contractorRenderProfilePage() {
  const page = document.getElementById('page-profile');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Profile</h1>
        <p class="contractor-scope-note">Your personal login account — separate from your company's business profile.</p>
      </div>
    </div>
    <section class="contractor-layout">
      <article class="contractor-form-card">
        <h2>Account Details</h2>
        <form id="contractorAccountProfileForm">
          <div class="form-grid" id="contractorAccountProfileFields"><p class="empty-state">Loading...</p></div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Update Profile</button>
          </div>
        </form>
      </article>
      <article class="contractor-form-card">
        <h2>Change Password</h2>
        <form id="contractorAccountPasswordForm">
          <div class="form-group">
            <label>Current Password</label>
            <input class="form-input" type="password" name="current_password" required>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>New Password</label>
              <input class="form-input" type="password" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input class="form-input" type="password" name="confirm_password" required minlength="6">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Change Password</button>
          </div>
        </form>
      </article>
    </section>
  `;

  document.getElementById('contractorAccountPasswordForm').addEventListener('submit', submitPasswordForm);
  document.getElementById('contractorAccountProfileForm').addEventListener('submit', submitProfileForm);

  try {
    const response = await fetch(USER_API);
    const result = await response.json();
    const user = result.data || {};
    document.getElementById('contractorAccountProfileFields').innerHTML = `
      <div class="form-group">
        <label>Full Name</label>
        <input class="form-input" name="full_name" required value="${contractorEscape(user.full_name)}">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input class="form-input" type="email" name="email" required value="${contractorEscape(user.email)}">
      </div>
      <div class="form-group">
        <label>Username</label>
        <input class="form-input" disabled value="${contractorEscape(user.username)}">
      </div>
    `;
  } catch {
    document.getElementById('contractorAccountProfileFields').innerHTML = '<p class="empty-state">Unable to load profile.</p>';
  }
}

function contractorOpenPaymentRequestForm() {
  contractorOpenModal('Submit Payment Request', `
    <form id="contractorPaymentRequestForm">
      <div class="form-group">
        <label>Assigned Project</label>
        <select class="form-input" name="project_id" required>${contractorProjectOptions()}</select>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Requested Amount</label>
        <input class="form-input" type="number" min="1" step="0.01" name="requested_amount" required>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Remarks</label>
        <textarea class="form-input" name="remarks" rows="3" placeholder="Billing period, accomplishment reference, or notes"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="contractorCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Submit Request</button>
      </div>
    </form>
  `);

  document.getElementById('contractorPaymentRequestForm').addEventListener('submit', contractorSubmitPaymentRequest);
}

async function contractorSubmitReport(event) {
  event.preventDefault();
  const formEl = event.target;
  contractorClearFieldErrors(formEl);
  const form = new FormData(formEl);
  try {
    await contractorPostJson('report', {
      project_id: form.get('project_id'),
      report_date: form.get('report_date'),
      progress_percent: form.get('progress_percent'),
      accomplishments: form.get('accomplishments'),
      issues: form.get('issues'),
      next_steps: form.get('next_steps'),
    });
    contractorToast('Accomplishment report submitted.');
    formEl.reset();
    await contractorRefreshData();
    contractorListState.reports.page = 1;
    await contractorLoadReportsList();
  } catch (error) {
    contractorShowFieldErrors(formEl, error.fieldErrors);
    contractorToast(error.message, 'error');
  }
}

async function contractorSubmitDocument(event) {
  event.preventDefault();
  const formEl = event.target;
  contractorClearFieldErrors(formEl);
  const form = new FormData(formEl);
  try {
    await contractorPostForm('document', form);
    contractorToast('Supporting document(s) uploaded.');
    formEl.reset();
    contractorListState.documents.page = 1;
    await contractorLoadDocumentsList();
  } catch (error) {
    contractorShowFieldErrors(formEl, error.fieldErrors);
    contractorToast(error.message, 'error');
  }
}

async function contractorSubmitPaymentRequest(event) {
  event.preventDefault();
  const formEl = event.target;
  contractorClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await contractorPostJson('payment_request', {
      project_id: form.get('project_id'),
      requested_amount: form.get('requested_amount'),
      remarks: form.get('remarks'),
    });
    contractorCloseModal();
    contractorToast('Payment request submitted.');
    await contractorRefreshData();
    contractorRenderPaymentStatus();
  } catch (error) {
    contractorShowFieldErrors(formEl, error.fieldErrors);
    contractorToast(error.message, 'error');
  }
}

async function contractorOpenProject(projectId) {
  try {
    const response = await contractorGet('project', { id: projectId });
    const project = response.data;
    contractorOpenModal(`${project.project_code} Details`, `
      <div class="contractor-detail-grid">
        <div class="contractor-detail-box"><span>Project</span><strong>${contractorEscape(project.name)}</strong></div>
        <div class="contractor-detail-box"><span>Location</span><strong>${contractorEscape(project.location || '-')}</strong></div>
        <div class="contractor-detail-box"><span>Status</span><strong>${contractorStatus(project.status)}</strong></div>
        <div class="contractor-detail-box"><span>Progress</span><strong>${Number(project.progress || 0)}%</strong></div>
        <div class="contractor-detail-box"><span>Contract Value</span><strong>${contractorFullMoney(project.budget)}</strong></div>
        <div class="contractor-detail-box"><span>Released</span><strong>${contractorFullMoney(project.total_spent)}</strong></div>
      </div>
      <h4 style="margin: 12px 0 8px; color:#1e293b;">Milestones</h4>
      <div class="contractor-mini-list">
        ${project.milestones.length ? project.milestones.map(milestone => `
          <div class="contractor-mini-row">
            <span>${contractorEscape(milestone.title)} (${contractorDate(milestone.due_date)})</span>
            <strong>${Number(milestone.completed) === 1 ? 'Done' : 'Open'}</strong>
          </div>
        `).join('') : '<p class="empty-state">No milestones recorded.</p>'}
      </div>
      <h4 style="margin: 16px 0 8px; color:#1e293b;">Recent Reports</h4>
      <div class="contractor-mini-list">
        ${project.reports.length ? project.reports.map(report => `
          <div class="contractor-mini-row">
            <span>${contractorDate(report.report_date)} - ${contractorEscape(report.accomplishments).slice(0, 70)}</span>
            <strong>${Number(report.progress_percent || 0)}%</strong>
          </div>
        `).join('') : '<p class="empty-state">No reports submitted.</p>'}
      </div>
    `);
  } catch (error) {
    contractorToast(error.message, 'error');
  }
}

async function contractorRefreshData() {
  const [summary, projects, payments] = await Promise.all([
    contractorGet('summary'),
    contractorGet('projects'),
    contractorGet('payments'),
  ]);

  contractorState.summary = summary;
  contractorState.projects = projects.data || [];
  contractorState.payments = payments.data || [];
  contractorRenderDashboard();
}

function contractorShowPage(page) {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });
  document.querySelectorAll('.page-section').forEach(section => {
    section.style.display = section.id === `page-${page}` ? 'block' : 'none';
  });

  if (page === 'dashboard') contractorRenderDashboard();
  if (page === 'company-profile') contractorRenderCompanyProfile();
  if (page === 'accreditation-status') contractorRenderAccreditationStatus();
  if (page === 'accreditation-documents') contractorRenderAccreditationDocuments();
  if (page === 'open-biddings') contractorRenderOpenBiddings();
  if (page === 'my-bids') contractorRenderMyBids();
  if (page === 'bid-results') contractorRenderBidResults();
  if (page === 'assigned-projects') contractorRenderAssignedProjects();
  if (page === 'project-timeline') contractorRenderProjectTimeline();
  if (page === 'contract-details') contractorRenderContractDetails();
  if (page === 'progress-updates') contractorRenderProgressUpdates();
  if (page === 'accomplishment-report') contractorRenderReportPage();
  if (page === 'site-photos') contractorRenderSitePhotos();
  if (page === 'supporting-documents') contractorRenderDocumentsPage();
  if (page === 'payment-requests') contractorRenderPaymentRequests();
  if (page === 'payment-status') contractorRenderPaymentStatus();
  if (page === 'payment-history') contractorRenderPaymentHistory();
  if (page === 'performance-rating') contractorRenderPerformanceRating();
  if (page === 'compliance-records') contractorRenderComplianceRecords();
  if (page === 'notifications') contractorRenderNotificationsPage();
  if (page === 'profile') contractorRenderProfilePage();
}

function contractorGoToReport(projectId = '') {
  contractorShowPage('accomplishment-report');
  if (projectId) {
    contractorRenderReportPage(projectId);
  }
}

function contractorWireShell() {
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    if (window.matchMedia('(min-width: 769px)').matches) {
      document.body.classList.toggle('sidebar-collapsed');
      return;
    }
    document.getElementById('sidebar')?.classList.toggle('open');
  });

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      contractorShowPage(item.dataset.page || 'dashboard');
    });
  });

  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.getElementById('userMenu');
  userMenuBtn?.addEventListener('click', event => {
    event.stopPropagation();
    userMenu?.classList.toggle('open');
  });

  document.addEventListener('click', event => {
    if (userMenu && !userMenu.contains(event.target) && event.target !== userMenuBtn) {
      userMenu.classList.remove('open');
    }
  });

  // Notification bell/panel toggle + polling is handled by assets/js/notifications.js.

  document.getElementById('modalClose')?.addEventListener('click', contractorCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') contractorCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') contractorCloseModal();
  });

  document.getElementById('searchInput')?.addEventListener('input', event => {
    const term = event.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-project-search]').forEach(card => {
      card.style.display = card.dataset.projectSearch.toLowerCase().includes(term) ? '' : 'none';
    });
  });
}

async function showProfileSettings() {
  try {
    const response = await fetch(USER_API);
    const result = await response.json();
    const user = result.data || {};
    contractorOpenModal('Profile Settings', `
      <form id="contractorProfileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-input" name="full_name" required value="${contractorEscape(user.full_name)}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-input" type="email" name="email" required value="${contractorEscape(user.email)}">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-input" disabled value="${contractorEscape(user.username)}">
          </div>
          <div class="form-group">
            <label>Role</label>
            <input class="form-input" disabled value="Contractor">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="contractorCloseModal()">Cancel</button>
          <button class="btn-primary" type="submit">Update Profile</button>
        </div>
      </form>
    `);
    document.getElementById('contractorProfileForm').addEventListener('submit', submitProfileForm);
  } catch {
    contractorToast('Failed to load profile.', 'error');
  }
}

function showChangePassword() {
  contractorOpenModal('Change Password', `
    <form id="contractorPasswordForm">
      <div class="form-group">
        <label>Current Password</label>
        <input class="form-input" type="password" name="current_password" required>
      </div>
      <div class="form-grid" style="margin-top:12px;">
        <div class="form-group">
          <label>New Password</label>
          <input class="form-input" type="password" name="new_password" required minlength="6">
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input class="form-input" type="password" name="confirm_password" required minlength="6">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="contractorCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Change Password</button>
      </div>
    </form>
  `);
  document.getElementById('contractorPasswordForm').addEventListener('submit', submitPasswordForm);
}

async function submitProfileForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const body = new URLSearchParams({
    full_name: form.get('full_name'),
    email: form.get('email'),
  });

  try {
    const response = await fetch(USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...CONTRACTOR_CSRF_HEADERS },
      body,
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    document.querySelector('.user-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-email').textContent = form.get('email');
    contractorCloseModal();
    contractorToast('Profile updated.');
  } catch (error) {
    contractorToast(error.message, 'error');
  }
}

async function submitPasswordForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  if (form.get('new_password') !== form.get('confirm_password')) {
    contractorToast('New passwords do not match.', 'error');
    return;
  }

  try {
    const response = await fetch(USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...CONTRACTOR_CSRF_HEADERS },
      body: new URLSearchParams({
        current_password: form.get('current_password'),
        new_password: form.get('new_password'),
      }),
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    contractorCloseModal();
    contractorToast('Password changed.');
  } catch (error) {
    contractorToast(error.message, 'error');
  }
}

window.contractorShowPage = contractorShowPage;
window.contractorGoToReport = contractorGoToReport;
window.contractorOpenProject = contractorOpenProject;
window.contractorCloseModal = contractorCloseModal;
window.contractorOpenPaymentRequestForm = contractorOpenPaymentRequestForm;
window.contractorOpenBidForm = contractorOpenBidForm;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  contractorWireShell();
  try {
    await contractorRefreshData();
  } catch (error) {
    contractorToast(error.message, 'error');
  }
});
