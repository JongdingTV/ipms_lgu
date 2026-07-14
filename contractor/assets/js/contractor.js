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
  reports: { page: 1, perPage: 10 },
  documents: { page: 1, perPage: 10 },
};

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

  try {
    contractorRenderStatusChart(stats);
  } catch (error) {
    console.error('Failed to render status chart:', error);
  }

  const projectPreview = document.getElementById('contractorProjectPreview');
  projectPreview.innerHTML = contractorState.projects.length
    ? contractorState.projects.slice(0, 3).map(project => contractorProjectCard(project, true)).join('')
    : '<p class="empty-state">No assigned projects yet.</p>';

  const paymentPreview = document.getElementById('contractorPaymentPreview');
  paymentPreview.innerHTML = contractorState.payments.length
    ? contractorState.payments.slice(0, 5).map(payment => `
      <div class="contractor-mini-row">
        <span>${contractorEscape(payment.project_code)} - ${contractorEscape(payment.name)}</span>
        <strong>${contractorFullMoney(payment.balance_amount)}</strong>
      </div>
    `).join('')
    : '<p class="empty-state">No payment records yet.</p>';
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
        <div id="contractorReportsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="contractorReportsPager"></div>
      </article>
    </section>
  `;

  document.getElementById('contractorReportForm').addEventListener('submit', contractorSubmitReport);
  contractorLoadReportsList();
}

async function contractorLoadReportsList() {
  const container = document.getElementById('contractorReportsList');
  const pager = document.getElementById('contractorReportsPager');
  if (!container) return;
  const state = contractorListState.reports;

  try {
    const result = await contractorGet('reports', { page: state.page, per_page: state.perPage });

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
        <div id="contractorDocumentsList" class="contractor-mini-list"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="contractorDocumentsPager"></div>
      </article>
    </section>
  `;

  contractorWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));
  document.getElementById('contractorDocumentForm').addEventListener('submit', contractorSubmitDocument);
  contractorLoadDocumentsList();
}

async function contractorLoadDocumentsList() {
  const container = document.getElementById('contractorDocumentsList');
  const pager = document.getElementById('contractorDocumentsPager');
  if (!container) return;
  const state = contractorListState.documents;

  try {
    const result = await contractorGet('documents', { page: state.page, per_page: state.perPage });

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
  if (page === 'assigned-projects') contractorRenderAssignedProjects();
  if (page === 'accomplishment-report') contractorRenderReportPage();
  if (page === 'supporting-documents') contractorRenderDocumentsPage();
  if (page === 'contract-details') contractorRenderContractDetails();
  if (page === 'payment-status') contractorRenderPaymentStatus();
}

function contractorGoToReport(projectId = '') {
  contractorShowPage('accomplishment-report');
  if (projectId) {
    contractorRenderReportPage(projectId);
  }
}

function contractorWireShell() {
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
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
