/* BAC portal frontend - live procurement workflow */
const BAC_API = window.BASE_PATH + 'bac/api/portal.php';
const BAC_DOCS_API = window.BASE_PATH + 'bac/api/documents.php';
const BAC_USER_API = window.BASE_PATH + 'api/user.php';
const BAC_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};
const BAC_DOCUMENT_TYPES = [
  'Invitation to Bid', 'Approved Budget for the Contract', 'Bid Bulletin',
  'Abstract of Bids', 'Notice of Award', 'Board Resolution', 'Performance Bond', 'Other',
];

let bacCurrentPage = 'dashboard';
let bacDashboardData = { stats: {} };

/* Per-section pagination/filter state */
let bacListState = {
  announcements: { page: 1, perPage: 10, search: '' },
  bids: { page: 1, perPage: 10, search: '' },
  recommendations: { page: 1, perPage: 10, search: '' },
  logs: { page: 1, perPage: 10, search: '' },
  documents: { page: 1, perPage: 10, ownerType: '', status: '' },
  contractorApplications: { page: 1, perPage: 10 },
};

/* Row caches (by id) so click handlers can look up full record details
   without a second round-trip, regardless of which paginated list last
   fetched that row. */
let bacApprovedProjectsById = {};
let bacBidsById = {};
let bacDocumentsCache = [];
let bacContractorApplicationsCache = [];

function bacEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function bacMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function bacDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

function bacLabel(value) {
  return String(value || '').replaceAll('_', ' ');
}

function bacBadge(value) {
  return `<span class="badge status-${bacEscape(value)}">${bacEscape(bacLabel(value))}</span>`;
}

function bacToast(message, type = 'success') {
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

function bacOpenModal(title, html) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function bacCloseModal() {
  document.getElementById('modalOverlay')?.classList.remove('open');
}

function bacRow(title, meta, side) {
  return `
    <div class="bac-row">
      <div class="bac-row-main">
        <strong>${bacEscape(title)}</strong>
        <span>${bacEscape(meta)}</span>
      </div>
      <div>${side}</div>
    </div>
  `;
}

/* ---- Networking ------------------------------------------------------- */

function bacErrorFrom(result, response) {
  const err = new Error(result?.error || `HTTP ${response.status}`);
  err.fieldErrors = result?.errors || null;
  return err;
}

async function bacFetchJson(baseUrl, action, params = {}) {
  const query = new URLSearchParams({ action });
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      query.set(key, value);
    }
  });
  const response = await fetch(`${baseUrl}?${query.toString()}`);
  const result = await response.json();
  if (!response.ok || result.error) throw bacErrorFrom(result, response);
  return result;
}

async function bacGet(action, params = {}) {
  return bacFetchJson(BAC_API, action, params);
}

async function bacGetDocs(action, params = {}) {
  return bacFetchJson(BAC_DOCS_API, action, params);
}

async function bacRequest(action = 'summary', options = {}) {
  const response = await fetch(`${BAC_API}?action=${encodeURIComponent(action)}`, options);
  const result = await response.json();
  if (!response.ok || result.error) throw bacErrorFrom(result, response);
  return result;
}

async function bacPost(action, body) {
  return bacRequest(action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...BAC_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
}

async function bacPostDocsForm(action, formData) {
  const response = await fetch(`${BAC_DOCS_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...BAC_CSRF_HEADERS },
    body: formData,
  });
  const result = await response.json();
  if (!response.ok || result.error) throw bacErrorFrom(result, response);
  return result;
}

async function bacPostDocsJson(action, body) {
  const response = await fetch(`${BAC_DOCS_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...BAC_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const result = await response.json();
  if (!response.ok || result.error) throw bacErrorFrom(result, response);
  return result;
}

/* ---- Inline field-error rendering -------------------------------------- */

function bacClearFieldErrors(form) {
  form.querySelectorAll('.field-error-msg').forEach(el => el.remove());
  form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
}

function bacShowFieldErrors(form, fieldErrors) {
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

/* ---- Dynamic document rows (repeatable fields), optional attachments
   embedded directly in the Publish and Recommend forms ------------------ */

function bacDocRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <select class="form-input" name="documents[${index}][document_type]">
        ${BAC_DOCUMENT_TYPES.map(type => `<option value="${bacEscape(type)}">${bacEscape(type)}</option>`).join('')}
      </select>
      <input class="form-input" type="text" name="documents[${index}][title]" placeholder="Document title">
      <input class="form-input" type="file" name="document_files[${index}]">
      <button type="button" class="doc-row-remove" aria-label="Remove document row">&times;</button>
    </div>
  `;
}

function bacWireDocRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', bacDocRowHtml(nextIndex));
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

function bacDocSectionHtml(label) {
  return `
    <div class="doc-section">
      <label>${bacEscape(label)}</label>
      <div class="doc-rows" id="docRows"></div>
      <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another document</button>
    </div>
  `;
}

/** True if at least one document row actually has a file selected (attachments are optional). */
function bacFormHasDocuments(form) {
  return Array.from(form.querySelectorAll('input[type="file"][name^="document_files"]'))
    .some(input => input.files && input.files.length > 0);
}

/** Builds a FormData for documents.php's 'upload' action from the doc rows already present in `form`. */
function bacBuildDocumentFormData(form, ownerType, ownerId) {
  const data = new FormData();
  data.set('owner_type', ownerType);
  data.set('owner_id', ownerId);
  new FormData(form).forEach((value, key) => {
    if (key.startsWith('documents[') || key.startsWith('document_files[')) {
      data.append(key, value);
    }
  });
  return data;
}

/* ---- Refresh dispatch --------------------------------------------------- */

async function bacRefresh(page = bacCurrentPage) {
  try {
    await bacRenderers[page]?.();
  } catch (error) {
    bacToast(error.message || 'Failed to load BAC data.', 'error');
  }
}

/* ---- Dashboard ----------------------------------------------------------- */

let bacPipelineChartInst = null;

function bacRenderPipelineChart(stats) {
  const ctx = document.getElementById('bacPipelineChart')?.getContext('2d');
  if (!ctx) return;
  if (bacPipelineChartInst) bacPipelineChartInst.destroy();

  const segments = [
    { label: 'Open Bids', value: Number(stats.open_bids || 0), color: '#3b82f6' },
    { label: 'For Evaluation', value: Number(stats.for_evaluation || 0), color: '#f97316' },
    { label: 'Recommendations', value: Number(stats.recommendations || 0), color: '#22c55e' },
    { label: 'Approved Waiting', value: Number(stats.approved_waiting || 0), color: '#ef4444' },
  ];
  const total = segments.reduce((sum, s) => sum + s.value, 0);

  bacPipelineChartInst = new Chart(ctx, {
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

  document.getElementById('bacPipelineChartTotal').textContent = total;
  document.getElementById('bacPipelineChartLegend').innerHTML = segments.map(s => `
    <div class="budget-legend-item">
      <span class="legend-dot" style="background:${s.color};"></span>
      <span>${bacEscape(s.label)} <strong>${s.value}</strong></span>
    </div>
  `).join('');
}

async function bacRenderDashboard() {
  try {
    bacDashboardData = await bacGet('summary');
  } catch (error) {
    bacToast(error.message || 'Failed to load dashboard data.', 'error');
    return;
  }

  const stats = bacDashboardData.stats || {};
  document.getElementById('bacOpenBids').textContent = stats.open_bids || 0;
  document.getElementById('bacEvaluationCount').textContent = stats.for_evaluation || 0;
  document.getElementById('bacRecommendationCount').textContent = stats.recommendations || 0;
  document.getElementById('bacLogCount').textContent = stats.logs || 0;

  try {
    bacRenderPipelineChart(stats);
  } catch (error) {
    console.error('Failed to render pipeline chart:', error);
  }

  const announcements = bacDashboardData.announcements_preview || [];
  document.getElementById('bacAnnouncementPreview').innerHTML = announcements.length
    ? announcements.map(item => bacRow(
      item.project_name,
      `${item.reference_no} - Deadline ${bacDate(item.deadline)}`,
      bacBadge(item.status)
    )).join('')
    : '<p class="empty-state">No bidding announcements posted yet.</p>';

  const recommendations = bacDashboardData.recommendations_preview || [];
  document.getElementById('bacRecommendationPreview').innerHTML = recommendations.length
    ? recommendations.map(item => bacRow(
      item.awardee,
      item.project,
      `<strong class="bac-money">${bacMoney(item.amount)}</strong>`
    )).join('')
    : '<p class="empty-state">No award recommendations sent yet.</p>';

  const evaluations = bacDashboardData.evaluations_preview || [];
  document.getElementById('bacEvaluationPreview').innerHTML = evaluations.length
    ? evaluations.map(item => bacRow(
      item.contractor,
      `Compliance: ${bacLabel(item.compliance)}`,
      `<strong class="bac-score">${item.performance}</strong>`
    )).join('')
    : '<p class="empty-state">No active contractors found.</p>';

  const bids = bacDashboardData.bids_preview || [];
  document.getElementById('bacBidPreview').innerHTML = bids.length
    ? bids.map(item => bacRow(
      item.contractor,
      item.project,
      `<strong class="bac-money">${bacMoney(item.bid)}</strong>`
    )).join('')
    : '<p class="empty-state">No submitted bids yet.</p>';

  const logs = bacDashboardData.logs_preview || [];
  document.getElementById('bacLogPreview').innerHTML = logs.length
    ? logs.map(item => bacRow(
      item.title,
      item.detail || item.project || 'Procurement activity',
      `<span class="bac-log-date">${bacDate(item.date)}</span>`
    )).join('')
    : '<p class="empty-state">No procurement logs yet.</p>';
}

/* ---- Bidding Announcements ------------------------------------------------ */

async function bacRenderAnnouncements() {
  const container = document.getElementById('page-bidding-announcements');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Bidding Announcements</h2>
        <p class="bac-scope-note">Approved projects are posted here before bids can be recorded.</p>
      </div>
      <button class="btn-secondary" type="button" onclick="bacRefresh('bidding-announcements')">Refresh</button>
    </div>

    <section class="bac-dashboard-grid">
      <article class="bac-panel bac-panel-wide">
        <div class="bac-panel-head"><h2>Approved Projects Ready for Posting</h2></div>
        <div class="bac-list" id="bacApprovedProjects"><p class="empty-state">Loading...</p></div>
      </article>
    </section>

    <div class="bac-panel" style="margin-top:16px;">
      <div class="bac-panel-head">
        <h2>Bidding Notices</h2>
        <input type="text" id="bacAnnouncementSearch" placeholder="Search reference, project...">
      </div>
      <div class="table-card" id="bacAnnouncementsTable"></div>
      <div class="pagination-wrap" id="bacAnnouncementsPager"></div>
    </div>
  `;

  document.getElementById('bacAnnouncementSearch').addEventListener('input', debounce(event => {
    bacListState.announcements.search = event.target.value.trim();
    bacListState.announcements.page = 1;
    bacLoadAnnouncementsTable();
  }, 350));

  await Promise.all([bacLoadApprovedProjects(), bacLoadAnnouncementsTable()]);
}

async function bacLoadApprovedProjects() {
  const container = document.getElementById('bacApprovedProjects');
  try {
    const result = await bacGet('list_approved_projects');
    bacApprovedProjectsById = {};
    (result.data || []).forEach(project => { bacApprovedProjectsById[project.id] = project; });

    container.innerHTML = result.data.length ? result.data.map(project => `
      <div class="bac-row">
        <div class="bac-row-main">
          <strong>${bacEscape(project.name)}</strong>
          <span>${bacEscape(project.project_code)} - ${bacEscape(project.location || 'No location')}</span>
        </div>
        <button class="btn-primary btn-compact" type="button" onclick="bacOpenPublishForm(${project.id})">Post</button>
      </div>
    `).join('') : '<p class="empty-state">No approved projects waiting for BAC posting.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load approved projects.</p>';
  }
}

async function bacLoadAnnouncementsTable() {
  const table = document.getElementById('bacAnnouncementsTable');
  const pager = document.getElementById('bacAnnouncementsPager');
  const state = bacListState.announcements;

  try {
    const result = await bacGet('list_announcements', { page: state.page, per_page: state.perPage, search: state.search });

    table.innerHTML = result.data.length ? `
      <table class="data-table">
        <thead><tr><th>Reference</th><th>Project</th><th>Approved Budget</th><th>Published</th><th>Deadline</th><th>Status</th></tr></thead>
        <tbody>
          ${result.data.map(item => `
            <tr>
              <td><span class="proj-id">${bacEscape(item.reference_no)}</span></td>
              <td><strong>${bacEscape(item.project_name)}</strong><br><small>${bacEscape(item.project_code || '')}</small></td>
              <td>${bacMoney(item.budget)}</td>
              <td>${bacDate(item.published_at)}</td>
              <td>${bacDate(item.deadline)}</td>
              <td>${bacBadge(item.status)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    ` : '<p class="empty-state">No bidding announcements posted yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.announcements.page = nextPage; bacLoadAnnouncementsTable(); },
    });
  } catch (error) {
    table.innerHTML = '<p class="empty-state">Unable to load bidding announcements.</p>';
  }
}

/* ---- Contractor Evaluation ------------------------------------------------ */

async function bacRenderEvaluation() {
  const container = document.getElementById('page-contractor-evaluation');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Contractor Evaluation Page</h2>
        <p class="bac-scope-note">Eligibility, document compliance, performance score, and risk review for active contractors.</p>
      </div>
    </div>
    <div class="bac-page-grid" id="bacEvaluationGrid"><p class="empty-state">Loading...</p></div>
  `;

  try {
    const result = await bacGet('list_evaluations');
    document.getElementById('bacEvaluationGrid').innerHTML = result.data.length ? result.data.map(item => `
      <article class="bac-panel">
        <div class="bac-panel-head">
          <h2>${bacEscape(item.contractor)}</h2>
          ${bacBadge(item.eligibility)}
        </div>
        <div class="bac-decision-list">
          <div class="bac-decision-item"><span>Performance Score</span><strong class="bac-score">${item.performance}</strong></div>
          <div class="bac-decision-item"><span>Document Compliance</span>${bacBadge(item.compliance)}</div>
          <div class="bac-decision-item"><span>Risk Rating</span><strong>${bacEscape(bacLabel(item.risk))}</strong></div>
        </div>
      </article>
    `).join('') : '<p class="empty-state">No contractors available for evaluation.</p>';
  } catch (error) {
    document.getElementById('bacEvaluationGrid').innerHTML = '<p class="empty-state">Unable to load contractor evaluations.</p>';
  }
}

/* ---- Bid Comparison -------------------------------------------------------- */

async function bacRenderBidComparison() {
  const container = document.getElementById('page-bid-comparison');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Bid Comparison Table</h2>
        <p class="bac-scope-note">Record submitted amounts, variance from approved budget, technical score, and delivery commitment.</p>
      </div>
      <button class="btn-primary" type="button" onclick="bacOpenBidForm()">Record Bid</button>
    </div>
    <div class="bac-panel">
      <div class="bac-panel-head">
        <h2>Submitted Bids</h2>
        <input type="text" id="bacBidSearch" placeholder="Search project, contractor...">
      </div>
      <div class="table-card" id="bacBidsTable"></div>
      <div class="pagination-wrap" id="bacBidsPager"></div>
    </div>
  `;

  document.getElementById('bacBidSearch').addEventListener('input', debounce(event => {
    bacListState.bids.search = event.target.value.trim();
    bacListState.bids.page = 1;
    bacLoadBidsTable();
  }, 350));

  await bacLoadBidsTable();
}

async function bacLoadBidsTable() {
  const table = document.getElementById('bacBidsTable');
  const pager = document.getElementById('bacBidsPager');
  const state = bacListState.bids;

  try {
    const result = await bacGet('list_bids', { page: state.page, per_page: state.perPage, search: state.search });
    result.data.forEach(bid => { bacBidsById[bid.id] = bid; });

    table.innerHTML = result.data.length ? `
      <table class="data-table">
        <thead><tr><th>Project</th><th>Contractor</th><th>Source</th><th>Bid Amount</th><th>Variance</th><th>Technical</th><th>Delivery</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          ${result.data.map(item => `
            <tr>
              <td><strong>${bacEscape(item.project)}</strong><br><small>${bacEscape(item.project_code || '')}</small></td>
              <td>${bacEscape(item.contractor)}</td>
              <td><span class="badge bac-source-${bacEscape(item.source || 'bac_recorded')}">${item.source === 'contractor' ? 'Contractor-submitted' : 'BAC-recorded'}</span></td>
              <td>${bacMoney(item.bid)}</td>
              <td>${item.variance > 0 ? '+' : ''}${item.variance}%</td>
              <td><strong class="bac-score">${item.technical}</strong></td>
              <td>${item.deliveryDays || '-'} days</td>
              <td>${bacBadge(item.status)}</td>
              <td>
                <button class="btn-secondary btn-compact" type="button" onclick="bacOpenScoreForm(${item.id})" ${item.status === 'recommended' || item.status === 'rejected' ? 'disabled' : ''}>Score</button>
                <button class="btn-primary btn-compact" type="button" onclick="bacOpenRecommendationForm(${item.id})" ${item.status === 'recommended' ? 'disabled' : ''}>Recommend</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    ` : '<p class="empty-state">No bid submissions recorded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.bids.page = nextPage; bacLoadBidsTable(); },
    });
  } catch (error) {
    table.innerHTML = '<p class="empty-state">Unable to load bid submissions.</p>';
  }
}

/* ---- Award Recommendation -------------------------------------------------- */

async function bacRenderRecommendation() {
  const container = document.getElementById('page-award-recommendation');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Award Recommendation</h2>
        <p class="bac-scope-note">Recommended awardees are sent to HOPE for the official contract award decision.</p>
      </div>
      <div class="bac-action-strip">
        <button class="btn-secondary" type="button" onclick="bacOpenResolutionPacket()">Resolution Packet</button>
        <button class="btn-primary" type="button" onclick="bacOpenBidForm()">Record Bid</button>
      </div>
    </div>
    <div class="bac-recommendation">
      <article class="bac-panel bac-panel-wide">
        <div class="bac-panel-head"><h2>Committee Recommendations</h2></div>
        <div class="bac-stack" id="bacRecommendationsList"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="bacRecommendationsPager"></div>
      </article>
      <article class="bac-panel">
        <div class="bac-panel-head"><h2>Recommendation Queue</h2></div>
        <div class="bac-decision-list" id="bacCandidateQueue"><p class="empty-state">Loading...</p></div>
      </article>
    </div>
  `;

  await Promise.all([bacLoadRecommendationsList(), bacLoadCandidateQueue()]);
}

async function bacLoadRecommendationsList() {
  const container = document.getElementById('bacRecommendationsList');
  const pager = document.getElementById('bacRecommendationsPager');
  const state = bacListState.recommendations;

  try {
    const result = await bacGet('list_recommendations', { page: state.page, per_page: state.perPage, search: state.search });

    container.innerHTML = result.data.length ? result.data.map(item => `
      <div class="bac-row">
        <div class="bac-row-main">
          <strong>${bacEscape(item.project)}</strong>
          <span>${bacEscape(item.contract_no || 'Contract pending')} - ${bacEscape(item.basis || 'Award recommendation sent to admin.')}</span>
        </div>
        <div>
          <strong class="bac-money">${bacMoney(item.amount)}</strong><br>
          ${bacBadge(item.status)}
        </div>
      </div>
    `).join('') : '<p class="empty-state">No award recommendations sent yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.recommendations.page = nextPage; bacLoadRecommendationsList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load award recommendations.</p>';
  }
}

async function bacLoadCandidateQueue() {
  const container = document.getElementById('bacCandidateQueue');
  try {
    const result = await bacGet('list_candidate_bids');
    result.data.forEach(bid => { bacBidsById[bid.id] = bid; });

    container.innerHTML = result.data.length ? result.data.map(item => `
      <div class="bac-decision-item">
        <span>${bacEscape(item.contractor)}<br><small>${bacEscape(item.project)}</small></span>
        <button class="btn-primary btn-compact" type="button" onclick="bacOpenRecommendationForm(${item.id})">Select</button>
      </div>
    `).join('') : '<p class="empty-state">Record bids to build the recommendation queue.</p>';
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load the recommendation queue.</p>';
  }
}

async function bacOpenResolutionPacket() {
  bacOpenModal('Resolution Packet', '<p class="empty-state">Loading packet summary...</p>');
  try {
    const [announcements, bids, recommendations] = await Promise.all([
      bacGet('list_announcements', { per_page: 1 }),
      bacGet('list_bids', { per_page: 1 }),
      bacGet('list_recommendations', { page: 1, per_page: 100 }),
    ]);
    const pendingAdmin = recommendations.data.filter(item => item.status === 'sent_to_admin').length;

    document.getElementById('modalBody').innerHTML = `
      <div class="bac-decision-list">
        <div class="bac-decision-item"><span>Posted Announcements</span><strong>${announcements.total}</strong></div>
        <div class="bac-decision-item"><span>Bid Submissions</span><strong>${bids.total}</strong></div>
        <div class="bac-decision-item"><span>Award Recommendations</span><strong>${recommendations.total}</strong></div>
        <div class="bac-decision-item"><span>Admin Assignment Queue</span><strong>${pendingAdmin}</strong></div>
      </div>
    `;
  } catch (error) {
    document.getElementById('modalBody').innerHTML = '<p class="empty-state">Unable to load packet summary.</p>';
  }
}

/* ---- Procurement Logs ------------------------------------------------------ */

async function bacRenderLogs() {
  const container = document.getElementById('page-procurement-logs');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Procurement Logs</h2>
        <p class="bac-scope-note">Chronological audit trail for BAC procurement activities and committee actions.</p>
      </div>
      <input type="text" id="bacLogSearch" placeholder="Search action or detail...">
    </div>
    <div class="bac-timeline" id="bacLogsTimeline"><p class="empty-state">Loading...</p></div>
    <div class="pagination-wrap" id="bacLogsPager"></div>
  `;

  document.getElementById('bacLogSearch').addEventListener('input', debounce(event => {
    bacListState.logs.search = event.target.value.trim();
    bacListState.logs.page = 1;
    bacLoadLogsTimeline();
  }, 350));

  await bacLoadLogsTimeline();
}

async function bacLoadLogsTimeline() {
  const container = document.getElementById('bacLogsTimeline');
  const pager = document.getElementById('bacLogsPager');
  const state = bacListState.logs;

  try {
    const result = await bacGet('list_logs', { page: state.page, per_page: state.perPage, search: state.search });

    container.innerHTML = result.data.length ? result.data.map(item => `
      <article class="bac-log-item">
        <span class="bac-log-date">${bacDate(item.date)}</span>
        <div>
          <strong>${bacEscape(item.title)}</strong>
          <p>${bacEscape(item.detail || item.project || 'Procurement activity')}</p>
          <div style="margin-top:8px;">${bacBadge(item.status)}</div>
        </div>
      </article>
    `).join('') : '<p class="empty-state">No procurement logs yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.logs.page = nextPage; bacLoadLogsTimeline(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load procurement logs.</p>';
  }
}

/* ---- Procurement Documents (new) ------------------------------------------ */

async function bacRenderDocuments() {
  const container = document.getElementById('page-procurement-documents');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Procurement Documents</h2>
        <p class="bac-scope-note">Invitation to Bid, Approved Budget, Abstract of Bids, Notice of Award, and Board Resolution attachments, attached from the Bidding Announcements and Award Recommendation forms.</p>
      </div>
      <div class="bac-filters">
        <select id="bacDocOwnerFilter">
          <option value="">All types</option>
          <option value="project">Project (announcement stage)</option>
          <option value="bac_bid">Bid (award stage)</option>
        </select>
        <select id="bacDocStatusFilter">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
    </div>
    <div class="bac-list" id="bacDocumentsList"><p class="empty-state">Loading...</p></div>
    <div class="pagination-wrap" id="bacDocumentsPager"></div>
  `;

  document.getElementById('bacDocOwnerFilter').addEventListener('change', event => {
    bacListState.documents.ownerType = event.target.value;
    bacListState.documents.page = 1;
    bacLoadDocumentsList();
  });
  document.getElementById('bacDocStatusFilter').addEventListener('change', event => {
    bacListState.documents.status = event.target.value;
    bacListState.documents.page = 1;
    bacLoadDocumentsList();
  });

  await bacLoadDocumentsList();
}

async function bacLoadDocumentsList() {
  const container = document.getElementById('bacDocumentsList');
  const pager = document.getElementById('bacDocumentsPager');
  const state = bacListState.documents;

  try {
    const result = await bacGetDocs('list', {
      page: state.page, per_page: state.perPage, owner_type: state.ownerType, status: state.status,
    });
    bacDocumentsCache = result.data;

    container.innerHTML = result.data.length ? result.data.map((doc, idx) => `
      <div class="bac-row">
        <div class="bac-row-main">
          <strong>${bacEscape(doc.title)}</strong>
          <span>${bacEscape(doc.document_type)} - ${bacEscape(doc.project_name || (doc.owner_type + ' #' + doc.owner_id))} - uploaded by ${bacEscape(doc.uploaded_by_name || 'Unknown')} - ${bacDate(doc.created_at)}</span>
        </div>
        <div>
          ${bacBadge(doc.status)}
          ${doc.status === 'pending' ? `<button class="btn-primary btn-compact" type="button" onclick="bacOpenDocumentReview(${idx})">Review</button>` : ''}
        </div>
      </div>
    `).join('') : '<p class="empty-state">No procurement documents uploaded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.documents.page = nextPage; bacLoadDocumentsList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load procurement documents.</p>';
  }
}

function bacOpenDocumentReview(index) {
  const doc = bacDocumentsCache[index];
  if (!doc) {
    bacToast('Document not found.', 'error');
    return;
  }

  bacOpenModal('Review Document', `
    <p><strong>${bacEscape(doc.title)}</strong> (${bacEscape(doc.document_type)})</p>
    <p><small>${bacEscape(doc.owner_type)} #${doc.owner_id} - ${bacEscape(doc.original_name)} - uploaded by ${bacEscape(doc.uploaded_by_name || 'Unknown')}</small></p>
    <p><a href="${bacEscape(window.BASE_PATH + doc.file_path)}" target="_blank" rel="noopener">Open document in a new tab</a></p>
    <form id="bacDocumentReviewForm">
      <div class="form-group" style="margin-top:12px;">
        <label>Remarks (optional)</label>
        <textarea class="form-input" name="remarks" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-secondary" type="submit" data-decision="rejected">Reject</button>
        <button class="btn-primary" type="submit" data-decision="verified">Verify</button>
      </div>
    </form>
  `);

  document.getElementById('bacDocumentReviewForm').addEventListener('submit', async event => {
    event.preventDefault();
    const decision = event.submitter?.dataset.decision || 'verified';
    const remarks = new FormData(event.target).get('remarks') || '';
    try {
      await bacPostDocsJson('review', { document_id: doc.id, decision, remarks });
      bacCloseModal();
      bacToast(`Document ${decision}.`);
      await bacLoadDocumentsList();
    } catch (error) {
      bacToast(error.message, 'error');
    }
  });
}

async function bacRenderContractorApplications() {
  const container = document.getElementById('page-contractor-applications');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Contractor Applications</h2>
        <p class="bac-scope-note">Public applications submitted at /contractor/apply.php, awaiting BAC review. Approving creates their portal account and emails them; rejecting requires a reason.</p>
      </div>
    </div>
    <div class="bac-list" id="bacContractorApplicationsList"><p class="empty-state">Loading...</p></div>
    <div class="pagination-wrap" id="bacContractorApplicationsPager"></div>
  `;

  await bacLoadContractorApplications();
}

async function bacLoadContractorApplications() {
  const container = document.getElementById('bacContractorApplicationsList');
  const pager = document.getElementById('bacContractorApplicationsPager');
  const state = bacListState.contractorApplications;

  try {
    const result = await bacGet('list_contractor_applications', { page: state.page, per_page: state.perPage });
    bacContractorApplicationsCache = result.data;

    container.innerHTML = result.data.length ? result.data.map((app, idx) => `
      <div class="bac-row">
        <div class="bac-row-main">
          <strong>${bacEscape(app.name)}</strong>
          <span>${bacEscape(app.contact_person || 'No contact person listed')} - ${bacEscape(app.email)} - ${bacEscape(app.phone || 'No phone')} - submitted ${bacDate(app.created_at)}</span>
          <span>PCAB ${bacEscape(app.pcab_classification || 'N/A')} - License #${bacEscape(app.pcab_license_no || 'N/A')}</span>
          <span>${app.documents.length} document(s): ${app.documents.map(d => bacEscape(d.title)).join(', ') || 'none'}</span>
        </div>
        <div>
          <button class="btn-primary btn-compact" type="button" onclick="bacOpenContractorApplicationReview(${idx})">Review</button>
        </div>
      </div>
    `).join('') : '<p class="empty-state">No pending contractor applications.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { bacListState.contractorApplications.page = nextPage; bacLoadContractorApplications(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load contractor applications.</p>';
  }
}

function bacOpenContractorApplicationReview(index) {
  const app = bacContractorApplicationsCache[index];
  if (!app) {
    bacToast('Application not found.', 'error');
    return;
  }

  bacOpenModal('Review Contractor Application', `
    <p><strong>${bacEscape(app.name)}</strong></p>
    <p><small>${bacEscape(app.email)} - ${bacEscape(app.phone || 'No phone')} - ${bacEscape(app.address || 'No address')}</small></p>
    <p><small><strong>PCAB Classification:</strong> ${bacEscape(app.pcab_classification || 'N/A')} &nbsp; <strong>License #:</strong> ${bacEscape(app.pcab_license_no || 'N/A')}</small></p>
    <div style="margin:12px 0;">
      <strong>Documents:</strong>
      <ul>
        ${app.documents.map(d => `<li><a href="${bacEscape(window.BASE_PATH + d.file_path)}" target="_blank" rel="noopener">${bacEscape(d.title)}</a> (${bacEscape(d.document_type)})</li>`).join('') || '<li>No documents attached</li>'}
      </ul>
    </div>
    <form id="bacContractorApplicationForm">
      <div class="form-group">
        <label>Remarks (required to reject)</label>
        <textarea class="form-input" name="remarks" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-secondary" type="submit" data-decision="reject">Reject</button>
        <button class="btn-primary" type="submit" data-decision="approve">Approve</button>
      </div>
    </form>
  `);

  document.getElementById('bacContractorApplicationForm').addEventListener('submit', async event => {
    event.preventDefault();
    const decision = event.submitter?.dataset.decision || 'approve';
    const remarks = new FormData(event.target).get('remarks') || '';
    try {
      const result = await bacPost('review_contractor_application', { contractor_id: app.id, decision, remarks });
      bacCloseModal();
      if (decision === 'approve') {
        bacOpenModal('Application Approved', `
          <p><strong>${bacEscape(app.name)}</strong>'s account has been created.</p>
          <div class="form-group" style="margin-top:12px;">
            <label>Temporary password (shown once — the applicant was also emailed a link to set their own password)</label>
            <input class="form-input" readonly value="${bacEscape(result.temp_password)}" onclick="this.select()">
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="button" onclick="bacCloseModal()">Done</button>
          </div>
        `);
      } else {
        bacToast('Application rejected.');
      }
      await bacLoadContractorApplications();
    } catch (error) {
      bacToast(error.message, 'error');
    }
  });
}

/* ---- Page shell ----------------------------------------------------------- */

const bacRenderers = {
  dashboard: bacRenderDashboard,
  'bidding-announcements': bacRenderAnnouncements,
  'contractor-evaluation': bacRenderEvaluation,
  'bid-comparison': bacRenderBidComparison,
  'award-recommendation': bacRenderRecommendation,
  'procurement-logs': bacRenderLogs,
  'procurement-documents': bacRenderDocuments,
  'contractor-applications': bacRenderContractorApplications,
};

function bacShowPage(page) {
  bacCurrentPage = page;

  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });

  document.querySelectorAll('.page-section').forEach(section => {
    section.style.display = section.id === `page-${page}` ? 'block' : 'none';
  });

  bacRenderers[page]?.();
}

window.GLOBAL_SEARCH_NAVIGATE = bacShowPage;
window.GLOBAL_SEARCH_SOURCES = [
  {
    label: 'Bidding Announcements',
    url: BAC_API,
    extraParams: { action: 'list_announcements' },
    mapItem: (row) => ({
      title: row.project_name,
      meta: `${row.reference_no || ''} · ${row.project_code || ''}`.replace(/^ · /, ''),
      page: 'bidding-announcements',
    }),
  },
  {
    label: 'Bids',
    url: BAC_API,
    extraParams: { action: 'list_bids' },
    mapItem: (row) => ({
      title: row.project_name,
      meta: `${row.contractor_name || ''} · ${row.project_code || ''}`.replace(/^ · /, ''),
      page: 'bid-comparison',
    }),
  },
];

/* ---- Forms: Publish / Bid / Recommend (with optional document attachments) */

function bacOpenPublishForm(projectId) {
  const project = bacApprovedProjectsById[projectId];
  if (!project) {
    bacToast('Approved project not found.', 'error');
    return;
  }

  bacOpenModal('Post Bidding Notice', `
    <form id="bacPublishForm">
      <div class="form-grid">
        <div class="form-group">
          <label>Project</label>
          <input class="form-input" disabled value="${bacEscape(project.project_code)} - ${bacEscape(project.name)}">
        </div>
        <div class="form-group">
          <label>Deadline</label>
          <input class="form-input" type="date" name="deadline" required>
        </div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Notes</label>
        <textarea class="form-input" name="notes" rows="3" placeholder="Bid instructions, pre-bid schedule, or eligibility notes"></textarea>
      </div>
      ${bacDocSectionHtml('Attach documents (Invitation to Bid, Approved Budget for the Contract) — optional, can be added later too')}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Post Notice</button>
      </div>
    </form>
  `);

  bacWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));

  document.getElementById('bacPublishForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    bacClearFieldErrors(form);
    const data = new FormData(form);

    try {
      await bacPost('publish', {
        project_id: projectId,
        deadline: data.get('deadline'),
        notes: data.get('notes'),
      });

      if (bacFormHasDocuments(form)) {
        try {
          await bacPostDocsForm('upload', bacBuildDocumentFormData(form, 'project', projectId));
        } catch (docError) {
          bacToast('Notice posted, but attaching documents failed: ' + docError.message, 'error');
        }
      }

      bacCloseModal();
      bacToast('Bidding notice posted.');
      await bacRefresh('bidding-announcements');
    } catch (error) {
      bacShowFieldErrors(form, error.fieldErrors);
      bacToast(error.message, 'error');
    }
  });
}

async function bacOpenBidForm() {
  bacOpenModal('Record Bid Submission', '<p class="empty-state">Loading form...</p>');

  let announcements = [];
  let contractors = [];
  try {
    [announcements, contractors] = await Promise.all([
      bacGet('list_announcements', { per_page: 100 }).then(r => r.data),
      bacGet('list_contractors').then(r => r.data),
    ]);
  } catch (error) {
    document.getElementById('modalBody').innerHTML = '<p class="empty-state">Unable to load form data.</p>';
    return;
  }

  document.getElementById('modalBody').innerHTML = `
    <form id="bacBidForm">
      <div class="form-grid">
        <div class="form-group">
          <label>Project</label>
          <select class="form-input" name="project_id" required>
            <option value="">Select posted project</option>
            ${announcements.map(item => `<option value="${item.project_id}">${bacEscape(item.project_code)} - ${bacEscape(item.project_name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Contractor</label>
          <select class="form-input" name="contractor_id" required>
            <option value="">Select contractor</option>
            ${contractors.map(item => `<option value="${item.id}">${bacEscape(item.name)} (${Number(item.performance_score || 0)})</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Bid Amount</label>
          <input class="form-input" type="number" min="1" step="0.01" name="bid_amount" required>
        </div>
        <div class="form-group">
          <label>Technical Score</label>
          <input class="form-input" type="number" min="0" max="100" name="technical_score" value="80" required>
        </div>
        <div class="form-group">
          <label>Delivery Days</label>
          <input class="form-input" type="number" min="1" name="delivery_days" value="60">
        </div>
        <div class="form-group">
          <label>Submitted Date</label>
          <input class="form-input" type="date" name="submitted_at" value="${new Date().toISOString().slice(0, 10)}">
        </div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Remarks</label>
        <textarea class="form-input" name="remarks" rows="2"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Save Bid</button>
      </div>
    </form>
  `;

  document.getElementById('bacBidForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    bacClearFieldErrors(form);
    const body = Object.fromEntries(new FormData(form).entries());
    try {
      await bacPost('bid', body);
      bacCloseModal();
      bacToast('Bid submission recorded.');
      await bacRefresh('bid-comparison');
    } catch (error) {
      bacShowFieldErrors(form, error.fieldErrors);
      bacToast(error.message, 'error');
    }
  });
}

/**
 * Sets a bid's technical score after the fact. Needed because contractor-
 * submitted bids (source='contractor') arrive with no technical evaluation —
 * unlike BAC's own manual "Record Bid" form, which asks for one up front —
 * so BAC needs a way to score them before recommending.
 */
function bacOpenScoreForm(bidId) {
  const bid = bacBidsById[bidId];
  if (!bid) {
    bacToast('Bid not found.', 'error');
    return;
  }

  bacOpenModal('Set Technical Score', `
    <form id="bacScoreForm">
      <div class="bac-decision-list">
        <div class="bac-decision-item"><span>Project</span><strong>${bacEscape(bid.project)}</strong></div>
        <div class="bac-decision-item"><span>Contractor</span><strong>${bacEscape(bid.contractor)}</strong></div>
        <div class="bac-decision-item"><span>Bid Amount</span><strong>${bacMoney(bid.bid)}</strong></div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Technical Score (0-100)</label>
        <input class="form-input" type="number" name="technical_score" min="0" max="100" value="${bid.technical || 0}" required>
      </div>
      <div class="form-group">
        <label>Remarks (optional)</label>
        <textarea class="form-input" name="remarks" rows="2"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Save Score</button>
      </div>
    </form>
  `);

  document.getElementById('bacScoreForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    bacClearFieldErrors(form);
    const data = new FormData(form);

    try {
      await bacPost('score_bid', {
        bid_id: bidId,
        technical_score: data.get('technical_score'),
        remarks: data.get('remarks'),
      });

      bacCloseModal();
      bacToast('Technical score saved.');
      await bacLoadBidsTable();
    } catch (error) {
      bacShowFieldErrors(form, error.fieldErrors);
      bacToast(error.message, 'error');
    }
  });
}

function bacOpenRecommendationForm(bidId) {
  const bid = bacBidsById[bidId];
  if (!bid) {
    bacToast('Bid not found.', 'error');
    return;
  }

  bacOpenModal('Send Award Recommendation', `
    <form id="bacRecommendationForm">
      <div class="bac-decision-list">
        <div class="bac-decision-item"><span>Project</span><strong>${bacEscape(bid.project)}</strong></div>
        <div class="bac-decision-item"><span>Contractor</span><strong>${bacEscape(bid.contractor)}</strong></div>
        <div class="bac-decision-item"><span>Bid Amount</span><strong>${bacMoney(bid.bid)}</strong></div>
        <div class="bac-decision-item"><span>Technical Score</span><strong>${bid.technical}</strong></div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Basis</label>
        <textarea class="form-input" name="basis" rows="3">Lowest calculated responsive bid with acceptable technical score.</textarea>
      </div>
      ${bacDocSectionHtml('Attach award documents (Abstract of Bids, Notice of Award, Board Resolution) — optional, can be added later too')}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Send to HOPE</button>
      </div>
    </form>
  `);

  bacWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));

  document.getElementById('bacRecommendationForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    bacClearFieldErrors(form);
    const data = new FormData(form);

    try {
      await bacPost('recommend', {
        bid_id: bidId,
        basis: data.get('basis'),
      });

      if (bacFormHasDocuments(form)) {
        try {
          await bacPostDocsForm('upload', bacBuildDocumentFormData(form, 'bac_bid', bidId));
        } catch (docError) {
          bacToast('Recommendation sent, but attaching documents failed: ' + docError.message, 'error');
        }
      }

      bacCloseModal();
      bacToast('Award recommendation sent to HOPE for approval.');
      await bacRefresh('award-recommendation');
    } catch (error) {
      bacShowFieldErrors(form, error.fieldErrors);
      bacToast(error.message, 'error');
    }
  });
}

/* ---- Shell / navigation ----------------------------------------------------- */

function bacWireShell() {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      bacShowPage(item.dataset.page || 'dashboard');
    });
  });

  const sidebarToggle = document.getElementById('sidebarToggle');
  sidebarToggle?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('open');
  });

  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.getElementById('userMenu');
  userMenuBtn?.addEventListener('click', event => {
    event.stopPropagation();
    userMenu?.classList.toggle('open');
  });

  document.addEventListener('click', event => {
    if (window.innerWidth <= 768) {
      const sidebar = document.getElementById('sidebar');
      if (sidebar?.classList.contains('open') && !sidebar.contains(event.target) && event.target !== sidebarToggle) {
        sidebar.classList.remove('open');
      }
    }

    if (userMenu && !userMenu.contains(event.target) && event.target !== userMenuBtn) {
      userMenu.classList.remove('open');
    }
  });

  // Notification bell/panel toggle + polling is handled by assets/js/notifications.js.

  document.getElementById('modalClose')?.addEventListener('click', bacCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') bacCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') bacCloseModal();
  });

  // Topbar search now proxies into whichever single page-specific (server-backed)
  // search box is visible, since list search moved server-side with pagination.
  // No-ops when the current page has none or more than one search box (ambiguous).
  document.getElementById('searchInput')?.addEventListener('input', event => {
    const pageEl = document.getElementById(`page-${bacCurrentPage}`);
    const candidates = pageEl ? pageEl.querySelectorAll('input[type="text"][id$="Search"]') : [];
    if (candidates.length === 1) {
      candidates[0].value = event.target.value;
      candidates[0].dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
}

/* ---- Profile / password (unrelated to this phase, unchanged) ------------ */

async function showProfileSettings() {
  try {
    const response = await fetch(BAC_USER_API);
    const result = await response.json();
    const user = result.data || {};
    bacOpenModal('Profile Settings', `
      <form id="bacProfileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-input" name="full_name" required value="${bacEscape(user.full_name)}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-input" type="email" name="email" required value="${bacEscape(user.email)}">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-input" disabled value="${bacEscape(user.username)}">
          </div>
          <div class="form-group">
            <label>Role</label>
            <input class="form-input" disabled value="BAC (Bids & Awards Committee)">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
          <button class="btn-primary" type="submit">Update Profile</button>
        </div>
      </form>
    `);
    document.getElementById('bacProfileForm').addEventListener('submit', submitProfileForm);
  } catch {
    bacToast('Failed to load profile.', 'error');
  }
}

function showChangePassword() {
  bacOpenModal('Change Password', `
    <form id="bacPasswordForm">
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
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Change Password</button>
      </div>
    </form>
  `);
  document.getElementById('bacPasswordForm').addEventListener('submit', submitPasswordForm);
}

async function submitProfileForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const body = new URLSearchParams({
    full_name: form.get('full_name'),
    email: form.get('email'),
  });

  try {
    const response = await fetch(BAC_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...BAC_CSRF_HEADERS },
      body,
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    document.querySelector('.user-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-email').textContent = form.get('email');
    bacCloseModal();
    bacToast('Profile updated.');
  } catch (error) {
    bacToast(error.message, 'error');
  }
}

async function submitPasswordForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  if (form.get('new_password') !== form.get('confirm_password')) {
    bacToast('New passwords do not match.', 'error');
    return;
  }

  try {
    const response = await fetch(BAC_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...BAC_CSRF_HEADERS },
      body: new URLSearchParams({
        current_password: form.get('current_password'),
        new_password: form.get('new_password'),
      }),
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    bacCloseModal();
    bacToast('Password changed.');
  } catch (error) {
    bacToast(error.message, 'error');
  }
}

window.bacShowPage = bacShowPage;
window.bacCloseModal = bacCloseModal;
window.bacRefresh = bacRefresh;
window.bacOpenPublishForm = bacOpenPublishForm;
window.bacOpenBidForm = bacOpenBidForm;
window.bacOpenRecommendationForm = bacOpenRecommendationForm;
window.bacOpenScoreForm = bacOpenScoreForm;
window.bacOpenResolutionPacket = bacOpenResolutionPacket;
window.bacOpenDocumentReview = bacOpenDocumentReview;
window.bacToast = bacToast;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  bacWireShell();
  await bacRenderDashboard();
});
