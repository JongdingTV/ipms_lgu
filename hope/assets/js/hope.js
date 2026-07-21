/* HOPE (Head of Procuring Entity) portal frontend */
const HOPE_API = window.BASE_PATH + 'hope/api/portal.php';
const PROJECTS_API = window.BASE_PATH + 'api/projects.php';
const HOPE_USER_API = window.BASE_PATH + 'api/user.php';
const HOPE_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

let hopeCurrentPage = 'dashboard';
let hopeApprovalState = { page: 1, search: '', status: 'endorsed' };
let hopeProjectsById = {};

function hopeEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function hopeMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function hopeDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

function hopeBadge(value) {
  return `<span class="badge status-${hopeEscape(value)}">${hopeEscape(String(value || '').replaceAll('_', ' '))}</span>`;
}

function hopeToast(message, type = 'success') {
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

function hopeOpenModal(title, html) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function hopeCloseModal() {
  document.getElementById('modalOverlay')?.classList.remove('open');
}

function hopeRow(title, meta, side) {
  return `
    <div class="hope-row">
      <div class="hope-row-main">
        <strong>${hopeEscape(title)}</strong>
        <span>${hopeEscape(meta)}</span>
      </div>
      <div>${side}</div>
    </div>
  `;
}

/* ---- Networking ------------------------------------------------------- */

function hopeErrorFrom(result, response) {
  const err = new Error(result?.error || `HTTP ${response.status}`);
  err.fieldErrors = result?.errors || null;
  return err;
}

async function hopeGet(action, params = {}) {
  const query = new URLSearchParams({ action });
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) query.set(key, value);
  });
  const response = await fetch(`${HOPE_API}?${query.toString()}`);
  const result = await response.json();
  if (!response.ok || result.error) throw hopeErrorFrom(result, response);
  return result;
}

async function hopeFetchProjects(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) query.set(key, value);
  });
  const response = await fetch(`${PROJECTS_API}?${query.toString()}`);
  const result = await response.json();
  if (!response.ok || result.error) throw hopeErrorFrom(result, response);
  return result;
}

async function hopeFetchProject(id) {
  const response = await fetch(`${PROJECTS_API}?id=${encodeURIComponent(id)}`);
  const result = await response.json();
  if (!response.ok || result.error) throw hopeErrorFrom(result, response);
  return result;
}

async function hopeDecide(projectId, decision, reason) {
  const response = await fetch(`${PROJECTS_API}?action=decide`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...HOPE_CSRF_HEADERS },
    body: JSON.stringify({ project_id: projectId, decision, reason }),
  });
  const result = await response.json();
  if (!response.ok || result.error) throw hopeErrorFrom(result, response);
  return result;
}

/* ---- Refresh dispatch --------------------------------------------------- */

async function hopeRefresh(page = hopeCurrentPage) {
  try {
    await hopeRenderers[page]?.();
  } catch (error) {
    hopeToast(error.message || 'Failed to load HOPE data.', 'error');
  }
}

/* ---- Dashboard ----------------------------------------------------------- */

async function hopeRenderDashboard() {
  let data;
  try {
    data = await hopeGet('summary');
  } catch (error) {
    hopeToast(error.message || 'Failed to load dashboard data.', 'error');
    return;
  }

  const stats = data.stats || {};
  document.getElementById('hopePendingCount').textContent = stats.pending_project_approvals || 0;
  document.getElementById('hopePendingAwardsCount').textContent = stats.pending_award_approvals || 0;
  document.getElementById('hopePendingDeletionsCount').textContent = stats.pending_deletion_requests || 0;
  document.getElementById('hopeApprovedCount').textContent = stats.approved_this_month || 0;
  document.getElementById('hopeReturnedCount').textContent = stats.returned || 0;
  document.getElementById('hopeRejectedCount').textContent = stats.rejected || 0;
  document.getElementById('hopeTotalBudget').textContent = hopeMoney(stats.total_budget);
  document.getElementById('hopeDelayedCount').textContent = stats.delayed || 0;
  document.getElementById('hopeNearCompletionCount').textContent = stats.near_completion || 0;

  const pending = data.pending_preview || [];
  document.getElementById('hopePendingPreview').innerHTML = pending.length
    ? pending.map(p => hopeRow(
        p.name,
        `${p.project_code} — ${p.location || 'No location'} — submitted by ${p.created_by_name || 'Unknown'}`,
        hopeMoney(p.budget)
      )).join('')
    : '<p class="empty-state">No projects are currently awaiting approval.</p>';

  const highRisk = data.high_risk_projects || [];
  document.getElementById('hopeHighRiskList').innerHTML = highRisk.length
    ? highRisk.map(p => hopeRow(p.name, `${p.project_code} — ${hopeEscape(p.status).replaceAll('_', ' ')}`, hopeBadge('high_risk'))).join('')
    : '<p class="empty-state">No high-risk projects detected right now.</p>';

  try {
    hopeRenderDecisionChart(data.monthly_decisions || []);
    hopeRenderStageChart(data.status_mix || []);
  } catch (error) {
    console.error('Failed to render dashboard charts:', error);
  }
}

/* ---- Dashboard charts ----------------------------------------------------- */

let hopeDecisionChartInst = null;
let hopeStageChartInst = null;

const HOPE_CHART_GRID = () => document.documentElement.getAttribute('data-theme') === 'dark'
  ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)';

function hopeRenderDecisionChart(rows) {
  const ctx = document.getElementById('hopeDecisionChart')?.getContext('2d');
  if (!ctx) return;
  if (hopeDecisionChartInst) hopeDecisionChartInst.destroy();

  // Last six calendar months, oldest first, zero-filled from the log rows.
  const months = [];
  const now = new Date();
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    months.push({
      ym: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleString('en', { month: 'short' }),
    });
  }
  const series = {
    'Project approved': months.map(() => 0),
    'Project returned': months.map(() => 0),
    'Project rejected': months.map(() => 0),
  };
  rows.forEach(row => {
    const index = months.findIndex(m => m.ym === row.ym);
    if (index !== -1 && series[row.action]) series[row.action][index] = Number(row.total);
  });

  hopeDecisionChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: months.map(m => m.label),
      datasets: [
        { label: 'Approved', data: series['Project approved'], backgroundColor: 'rgba(34,197,94,.8)', borderRadius: 5, maxBarThickness: 26 },
        { label: 'Returned', data: series['Project returned'], backgroundColor: 'rgba(249,115,22,.8)', borderRadius: 5, maxBarThickness: 26 },
        { label: 'Rejected', data: series['Project rejected'], backgroundColor: 'rgba(239,68,68,.8)', borderRadius: 5, maxBarThickness: 26 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e2a3b' },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } }, border: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } }, grid: { color: HOPE_CHART_GRID() }, border: { display: false } },
      },
    },
  });
}

function hopeRenderStageChart(statusMix) {
  const ctx = document.getElementById('hopeStageChart')?.getContext('2d');
  if (!ctx) return;
  if (hopeStageChartInst) hopeStageChartInst.destroy();

  const stageOf = status => {
    if (['draft', 'endorsed', 'returned', 'planning'].includes(status)) return 'In Review';
    if (['approved', 'bidding', 'awarded'].includes(status)) return 'Procurement';
    if (['assigned', 'active', 'delayed', 'on_hold', 'completion_inspection'].includes(status)) return 'Execution';
    if (['completed', 'turnover'].includes(status)) return 'Completed';
    return 'Stopped';
  };
  const stages = [
    { label: 'In Review', color: '#94a3b8' },
    { label: 'Procurement', color: '#f97316' },
    { label: 'Execution', color: '#3b82f6' },
    { label: 'Completed', color: '#22c55e' },
    { label: 'Stopped', color: '#ef4444' },
  ].map(stage => ({ ...stage, value: 0 }));
  statusMix.forEach(row => {
    const stage = stages.find(s => s.label === stageOf(row.status));
    if (stage) stage.value += Number(row.total);
  });
  const total = stages.reduce((sum, s) => sum + s.value, 0);

  hopeStageChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: stages.map(s => s.label),
      datasets: [{
        data: stages.map(s => s.value),
        backgroundColor: stages.map(s => s.color),
        borderColor: stages.map(() => '#fff'), borderWidth: 3, hoverOffset: 6,
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

  document.getElementById('hopeStageChartTotal').textContent = total;
  document.getElementById('hopeStageChartLegend').innerHTML = stages.map(s => `
    <div class="budget-legend-item">
      <span class="legend-dot" style="background:${s.color};"></span>
      <span>${hopeEscape(s.label)} <strong>${s.value}</strong></span>
    </div>
  `).join('');
}

/* ---- Project Approvals ---------------------------------------------------- */

async function hopeRenderProjectApprovals() {
  const container = document.getElementById('page-project-approvals');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Project Approvals</h2>
        <p class="hope-decision-note">Approve, return for revision, or reject infrastructure projects that Engineering Review has already endorsed. A reason is required for Return and Reject decisions.</p>
      </div>
    </div>
    <div class="hope-filters" style="margin-bottom:12px;">
      <input class="filter-input" id="hopeApprovalSearch" placeholder="Search projects...">
      <select class="filter-select" id="hopeApprovalStatus">
        <option value="endorsed">Pending Review</option>
        <option value="returned">Returned for Revision</option>
        <option value="approved">Approved</option>
        <option value="cancelled">Rejected</option>
        <option value="">All Statuses</option>
      </select>
    </div>
    <div id="hopeApprovalTable" class="table-card"></div>
    <div id="hopeApprovalPager" class="pager"></div>
  `;

  document.getElementById('hopeApprovalSearch').value = hopeApprovalState.search;
  document.getElementById('hopeApprovalStatus').value = hopeApprovalState.status;
  document.getElementById('hopeApprovalSearch').addEventListener('input', event => {
    hopeApprovalState.search = event.target.value;
    hopeApprovalState.page = 1;
    hopeFetchApprovalProjects();
  });
  document.getElementById('hopeApprovalStatus').addEventListener('change', event => {
    hopeApprovalState.status = event.target.value;
    hopeApprovalState.page = 1;
    hopeFetchApprovalProjects();
  });

  await hopeFetchApprovalProjects();
}

async function hopeFetchApprovalProjects() {
  const wrap = document.getElementById('hopeApprovalTable');
  if (!wrap) return;
  wrap.innerHTML = '<div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div><div class="skeleton-row"></div></div>';

  try {
    const result = await hopeFetchProjects({
      page: hopeApprovalState.page,
      search: hopeApprovalState.search,
      status: hopeApprovalState.status,
    });
    result.data.forEach(p => { hopeProjectsById[p.id] = p; });
    hopeRenderApprovalTable(result.data);
    renderPagination(document.getElementById('hopeApprovalPager'), {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: 10,
      onPageChange: nextPage => { hopeApprovalState.page = nextPage; hopeFetchApprovalProjects(); },
    });
  } catch (error) {
    wrap.innerHTML = '<p class="empty-state">Failed to load projects for approval.</p>';
  }
}

function hopeRenderApprovalTable(rows) {
  const wrap = document.getElementById('hopeApprovalTable');
  if (!wrap) return;
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No projects found for this filter.</p>';
    return;
  }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Project</th><th>Budget</th><th>Schedule</th><th>Contractor</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(p => `
          <tr>
            <td><span class="proj-id">${hopeEscape(p.project_code)}</span></td>
            <td><strong>${hopeEscape(p.name)}</strong><br><small style="color:#94a3b8">${hopeEscape(p.location || '-')}</small></td>
            <td>${hopeMoney(p.budget)}</td>
            <td>${hopeDate(p.start_date)} to ${hopeDate(p.end_date)}</td>
            <td>${hopeEscape(p.contractor_name || 'Unassigned')}</td>
            <td>${hopeBadge(p.status)}</td>
            <td>
              <div class="inline-actions">
                <button class="btn-primary btn-compact" onclick="hopeOpenDecisionModal(${p.id}, 'approve')" ${p.status === 'approved' ? 'disabled' : ''}>Approve</button>
                <button class="btn-secondary btn-compact" onclick="hopeOpenDecisionModal(${p.id}, 'return')" ${p.status === 'returned' ? 'disabled' : ''}>Return</button>
                <button class="btn-secondary btn-compact" onclick="hopeOpenDecisionModal(${p.id}, 'reject')" ${p.status === 'cancelled' ? 'disabled' : ''}>Reject</button>
                <button class="btn-secondary btn-compact" onclick="hopeOpenProjectModal(${p.id})">View</button>
              </div>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function hopeOpenDecisionModal(id, decision) {
  const labels = { approve: 'Approve', return: 'Return for Revision', reject: 'Reject' };
  const reasonRequired = decision !== 'approve';

  hopeOpenModal(`${labels[decision]} Project`, `
    <form id="hopeDecisionForm">
      <div class="form-group">
        <label>Reason${reasonRequired ? ' *' : ' (optional)'}</label>
        <textarea name="reason" class="form-input" rows="4" placeholder="Explain the decision" ${reasonRequired ? 'required' : ''}></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="hopeCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm ${labels[decision]}</button>
      </div>
    </form>
  `);

  document.getElementById('hopeDecisionForm').addEventListener('submit', async event => {
    event.preventDefault();
    const reason = new FormData(event.target).get('reason') || '';
    try {
      const result = await hopeDecide(id, decision, reason);
      hopeToast(`Project ${result.status}.`);
      hopeCloseModal();
      hopeFetchApprovalProjects();
      hopeRenderDashboard();
    } catch (error) {
      hopeToast(error.message || 'Failed to record decision.', 'error');
    }
  });
}

async function hopeOpenProjectModal(id) {
  try {
    const [p, risk] = await Promise.all([
      hopeFetchProject(id),
      hopeGet('project_risk', { id }).catch(() => ({ risk: 'unknown', summary: 'Risk summary unavailable.' })),
    ]);
    const color = p.progress >= 70 ? '#22c55e' : p.progress >= 40 ? '#f97316' : '#ef4444';
    hopeOpenModal(`Project #${p.id} — ${hopeEscape(p.name)}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${hopeEscape(p.location || '—')}</p></div>
          <div><p class="modal-label">CONTRACTOR</p><p class="modal-val">${hopeEscape(p.contractor_name || '—')}</p></div>
          <div><p class="modal-label">BUDGET</p><p class="modal-val">${hopeMoney(p.budget)}</p></div>
          <div><p class="modal-label">SPENT</p><p class="modal-val">${hopeMoney(p.total_spent)}</p></div>
          <div><p class="modal-label">STATUS</p><p class="modal-val">${hopeBadge(p.status)}</p></div>
          <div><p class="modal-label">END DATE</p><p class="modal-val">${hopeDate(p.end_date)}</p></div>
        </div>
        <div>
          <p class="modal-label">DESCRIPTION</p>
          <p class="modal-val" style="font-weight:400;">${hopeEscape(p.description || '—')}</p>
        </div>
        <div>
          <p class="modal-label">PROGRESS</p>
          <div style="background:#f1f5f9;border-radius:20px;height:10px;overflow:hidden;margin-top:6px;">
            <div style="width:${p.progress}%;background:${color};height:100%;border-radius:20px;transition:width 0.8s;"></div>
          </div>
          <p style="font-size:.75rem;color:#64748b;margin-top:4px;">${p.progress}% complete</p>
        </div>
        ${p.milestones?.length ? `
        <div>
          <p class="modal-label">MILESTONES</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${p.milestones.map(m => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;">
                <span style="color:${m.completed ? '#22c55e' : '#94a3b8'};">${m.completed ? '✓' : '○'}</span>
                <span style="color:${m.completed ? '#1e293b' : '#64748b'};text-decoration:${m.completed ? 'line-through' : 'none'}">${hopeEscape(m.title)}</span>
                <span style="margin-left:auto;color:#94a3b8;">${hopeDate(m.due_date)}</span>
              </div>
            `).join('')}
          </div>
        </div>` : ''}
        <div>
          <p class="modal-label">SUPPORTING DOCUMENTS</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${p.documents?.length ? p.documents.map(d => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;">
                <span>${hopeEscape(d.document_type)} — ${hopeEscape(d.title)}</span>
                <a style="margin-left:auto;" href="${window.BASE_PATH}${hopeEscape(d.file_path)}" target="_blank" rel="noopener">Open</a>
              </div>
            `).join('') : '<p class="empty-state">No supporting documents on file.</p>'}
          </div>
        </div>
        <div class="hope-risk-box hope-risk-${hopeEscape(risk.risk)}">
          <p class="modal-label">RISK SUMMARY — AI SUMMARY, ADVISORY ONLY</p>
          <p class="modal-val" style="font-weight:400;">${hopeEscape(risk.summary)}</p>
        </div>
      </div>
    `);
  } catch (error) {
    hopeToast('Failed to load project details.', 'error');
  }
}

/* ---- Contract Award Approvals ---------------------------------------------- */

let hopeAwardRecsById = {};

async function hopeRenderAwardApprovals() {
  const container = document.getElementById('page-award-approvals');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Contract Award Approvals</h2>
        <p class="hope-decision-note">Review BAC's recommended contractor and bid evaluation, then approve the award, return the recommendation for reconsideration, or reject it. A remark is required for Return and Reject decisions.</p>
      </div>
    </div>
    ${listToolbarHtml('hopeAwardSearch', 'Search project, contractor...', 'hopeAwardPager')}
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>Project</th><th>Contractor</th><th>Award Amount</th><th>Recommended</th><th>Actions</th></tr></thead>
        <tbody id="hopeAwardBody"><tr><td colspan="5" class="table-empty">Loading...</td></tr></tbody>
      </table>
    </div>
  `;

  initClientList('hopeAwards', {
    bodyId: 'hopeAwardBody', searchId: 'hopeAwardSearch', pagerId: 'hopeAwardPager',
    columns: 5, emptyText: 'No contract award recommendations are awaiting your decision.',
    searchText: r => `${r.project_code} ${r.project_name} ${r.contractor_name}`,
    rowHtml: r => `
      <tr>
        <td><span class="proj-id">${hopeEscape(r.project_code)}</span><br><strong>${hopeEscape(r.project_name)}</strong></td>
        <td>${hopeEscape(r.contractor_name)}<br><small style="color:#94a3b8">Performance ${r.performance_score}/100</small></td>
        <td class="cell-money">${hopeMoney(r.award_amount)}</td>
        <td class="cell-nowrap">${hopeDate(r.created_at)}</td>
        <td><button class="btn-primary btn-compact" onclick="hopeOpenAwardDetailModal(${r.id})">Review</button></td>
      </tr>`,
  });

  await hopeLoadAwardApprovals();
}

async function hopeLoadAwardApprovals() {
  const body = document.getElementById('hopeAwardBody');
  if (!body) return;

  try {
    const result = await hopeGet('list_award_recommendations');
    result.data.forEach(r => { hopeAwardRecsById[r.id] = r; });
    setClientListData('hopeAwards', result.data);
  } catch (error) {
    body.innerHTML = '<tr><td colspan="5" class="table-empty">Failed to load award recommendations.</td></tr>';
  }
}

async function hopeOpenAwardDetailModal(recId) {
  try {
    const detail = await hopeGet('award_recommendation_detail', { id: recId });
    const rec = detail.recommendation;
    const risk = detail.risk;

    hopeOpenModal(`Contract Award — ${hopeEscape(rec.project_code)}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">PROJECT</p><p class="modal-val">${hopeEscape(rec.project_name)}</p></div>
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${hopeEscape(rec.location || '-')}</p></div>
          <div><p class="modal-label">APPROVED BUDGET</p><p class="modal-val">${hopeMoney(rec.budget)}</p></div>
          <div><p class="modal-label">AWARD AMOUNT</p><p class="modal-val">${hopeMoney(rec.award_amount)}</p></div>
        </div>
        <div>
          <p class="modal-label">CONTRACTOR PROFILE</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
            <div><p class="modal-label">NAME</p><p class="modal-val">${hopeEscape(rec.contractor_name)}</p></div>
            <div><p class="modal-label">PCAB</p><p class="modal-val">${hopeEscape(rec.pcab_classification || 'Not on file')}${rec.pcab_license_no ? ' (' + hopeEscape(rec.pcab_license_no) + ')' : ''}</p></div>
            <div><p class="modal-label">PERFORMANCE SCORE</p><p class="modal-val">${rec.performance_score}/100</p></div>
            <div><p class="modal-label">CREDIBILITY</p><p class="modal-val">${rec.credibility_score} / 5.00</p></div>
          </div>
          ${Number(rec.is_blacklisted) === 1 ? '<p style="color:#ef4444;font-weight:600;margin-top:6px;">This contractor is currently blacklisted.</p>' : ''}
        </div>
        <div>
          <p class="modal-label">BID EVALUATION SUMMARY</p>
          <table class="data-table" style="margin-top:6px;">
            <thead><tr><th>Contractor</th><th>Bid Amount</th><th>Technical</th><th>Status</th></tr></thead>
            <tbody>
              ${detail.bids.map(b => `
                <tr style="${b.contractor_id === rec.contractor_id ? 'font-weight:600;' : ''}">
                  <td>${hopeEscape(b.contractor_name)}</td>
                  <td>${hopeMoney(b.bid_amount)}</td>
                  <td>${b.technical_score}</td>
                  <td>${hopeBadge(b.status)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        <div>
          <p class="modal-label">PROCUREMENT DOCUMENTS</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${detail.documents.length ? detail.documents.map(d => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;">
                <span>${hopeEscape(d.document_type)} — ${hopeEscape(d.title)}</span>
                <a style="margin-left:auto;" href="${window.BASE_PATH}${hopeEscape(d.file_path)}" target="_blank" rel="noopener">Open</a>
              </div>
            `).join('') : '<p class="empty-state">No procurement documents attached.</p>'}
          </div>
        </div>
        <div class="hope-risk-box hope-risk-${hopeEscape(risk.risk)}">
          <p class="modal-label">RISK SUMMARY — AI SUMMARY, ADVISORY ONLY</p>
          <p class="modal-val" style="font-weight:400;">${hopeEscape(risk.summary)}</p>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="hopeOpenAwardDecisionModal(${recId}, 'return')">Return Recommendation</button>
          <button class="btn-secondary" type="button" onclick="hopeOpenAwardDecisionModal(${recId}, 'reject')">Reject Recommendation</button>
          <button class="btn-primary" type="button" onclick="hopeOpenAwardDecisionModal(${recId}, 'approve')">Approve Award</button>
        </div>
      </div>
    `);
  } catch (error) {
    hopeToast(error.message || 'Failed to load award details.', 'error');
  }
}

function hopeOpenAwardDecisionModal(recId, decision) {
  const labels = { approve: 'Approve Award', return: 'Return Recommendation', reject: 'Reject Recommendation' };
  const remarksRequired = decision !== 'approve';

  hopeOpenModal(labels[decision], `
    <form id="hopeAwardDecisionForm">
      <div class="form-group">
        <label>Remarks${remarksRequired ? ' *' : ' (optional)'}</label>
        <textarea name="remarks" class="form-input" rows="4" placeholder="Explain the decision" ${remarksRequired ? 'required' : ''}></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="hopeCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm ${labels[decision]}</button>
      </div>
    </form>
  `);

  document.getElementById('hopeAwardDecisionForm').addEventListener('submit', async event => {
    event.preventDefault();
    const remarks = new FormData(event.target).get('remarks') || '';
    try {
      const response = await fetch(`${HOPE_API}?action=decide_award`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...HOPE_CSRF_HEADERS },
        body: JSON.stringify({ recommendation_id: recId, decision, remarks }),
      });
      const result = await response.json();
      if (!response.ok || result.error) throw hopeErrorFrom(result, response);
      hopeToast(`Contract award ${result.status}.`);
      hopeCloseModal();
      await hopeLoadAwardApprovals();
      await hopeRenderDashboard();
    } catch (error) {
      hopeToast(error.message || 'Failed to record decision.', 'error');
    }
  });
}

/* ---- Deletion Requests ---------------------------------------------------- */

let hopeDeletionRequestsById = {};

async function hopeRenderDeletionRequests() {
  const container = document.getElementById('page-deletion-requests');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Deletion Requests</h2>
        <p class="hope-decision-note">Review Admin's request to permanently delete a project, including the stated reason, then approve or reject it. A remark is required to reject.</p>
      </div>
    </div>
    ${listToolbarHtml('hopeDeletionSearch', 'Search project...', 'hopeDeletionPager')}
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>Project</th><th>Requested By</th><th>Reason</th><th>Requested</th><th>Actions</th></tr></thead>
        <tbody id="hopeDeletionBody"><tr><td colspan="5" class="table-empty">Loading...</td></tr></tbody>
      </table>
    </div>
  `;

  initClientList('hopeDeletions', {
    bodyId: 'hopeDeletionBody', searchId: 'hopeDeletionSearch', pagerId: 'hopeDeletionPager',
    columns: 5, emptyText: 'No project deletion requests are awaiting your review.',
    searchText: r => `${r.project_code} ${r.project_name} ${r.requested_by_name || ''}`,
    rowHtml: r => `
      <tr>
        <td><span class="proj-id">${hopeEscape(r.project_code)}</span><br><strong>${hopeEscape(r.project_name)}</strong></td>
        <td>${hopeEscape(r.requested_by_name || 'Unknown')}</td>
        <td style="max-width:260px;">${hopeEscape(r.reason)}</td>
        <td class="cell-nowrap">${hopeDate(r.created_at)}</td>
        <td><button class="btn-primary btn-compact" onclick="hopeOpenDeletionDetailModal(${r.id})">Review</button></td>
      </tr>`,
  });

  await hopeLoadDeletionRequests();
}

async function hopeLoadDeletionRequests() {
  const body = document.getElementById('hopeDeletionBody');
  if (!body) return;

  try {
    const result = await hopeGet('list_deletion_requests');
    result.data.forEach(r => { hopeDeletionRequestsById[r.id] = r; });
    setClientListData('hopeDeletions', result.data);
  } catch (error) {
    body.innerHTML = '<tr><td colspan="5" class="table-empty">Failed to load deletion requests.</td></tr>';
  }
}

function hopeOpenDeletionDetailModal(requestId) {
  const r = hopeDeletionRequestsById[requestId];
  if (!r) return;

  hopeOpenModal(`Deletion Request — ${hopeEscape(r.project_code)}`, `
    <div style="display:flex;flex-direction:column;gap:14px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">PROJECT</p><p class="modal-val">${hopeEscape(r.project_name)}</p></div>
        <div><p class="modal-label">LOCATION</p><p class="modal-val">${hopeEscape(r.location || '-')}</p></div>
        <div><p class="modal-label">CURRENT STATUS</p><p class="modal-val">${r.project_status ? hopeBadge(r.project_status) : 'Project already removed'}</p></div>
        <div><p class="modal-label">BUDGET</p><p class="modal-val">${r.budget != null ? hopeMoney(r.budget) : '-'}</p></div>
        <div><p class="modal-label">REQUESTED BY</p><p class="modal-val">${hopeEscape(r.requested_by_name || 'Unknown')}</p></div>
        <div><p class="modal-label">REQUESTED</p><p class="modal-val">${hopeDate(r.created_at)}</p></div>
      </div>
      <div>
        <p class="modal-label">REASON FOR DELETION</p>
        <p class="modal-val" style="font-weight:400;">${hopeEscape(r.reason)}</p>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="hopeOpenDeletionDecisionModal(${requestId}, 'reject')">Reject Request</button>
        <button class="btn-primary" type="button" onclick="hopeOpenDeletionDecisionModal(${requestId}, 'approve')">Approve — Permanently Delete</button>
      </div>
    </div>
  `);
}

function hopeOpenDeletionDecisionModal(requestId, decision) {
  const labels = { approve: 'Approve Deletion', reject: 'Reject Request' };
  const remarksRequired = decision !== 'approve';

  hopeOpenModal(labels[decision], `
    <form id="hopeDeletionDecisionForm">
      ${decision === 'approve' ? '<p class="empty-state" style="color:#ef4444;">This permanently deletes the project and all its related records. This cannot be undone.</p>' : ''}
      <div class="form-group">
        <label>Remarks${remarksRequired ? ' *' : ' (optional)'}</label>
        <textarea name="remarks" class="form-input" rows="4" placeholder="Explain the decision" ${remarksRequired ? 'required' : ''}></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="hopeCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm ${labels[decision]}</button>
      </div>
    </form>
  `);

  document.getElementById('hopeDeletionDecisionForm').addEventListener('submit', async event => {
    event.preventDefault();
    const remarks = new FormData(event.target).get('remarks') || '';
    try {
      const response = await fetch(`${HOPE_API}?action=decide_deletion`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...HOPE_CSRF_HEADERS },
        body: JSON.stringify({ request_id: requestId, decision, remarks }),
      });
      const result = await response.json();
      if (!response.ok || result.error) throw hopeErrorFrom(result, response);
      hopeToast(`Deletion request ${result.status}.`);
      hopeCloseModal();
      await hopeLoadDeletionRequests();
      await hopeRenderDashboard();
    } catch (error) {
      hopeToast(error.message || 'Failed to record decision.', 'error');
    }
  });
}

/* ---- Returned Projects / Decision History ----------------------------------- */

async function hopeRenderReturnedProjects() {
  const container = document.getElementById('page-returned-projects');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Returned Projects</h2>
        <p class="hope-decision-note">Projects sent back for revision, awaiting Admin's resubmission.</p>
      </div>
    </div>
    ${listToolbarHtml('hopeReturnedSearch', 'Search code, project, reason...', 'hopeReturnedPager')}
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>Code</th><th>Project</th><th>Budget</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody id="hopeReturnedBody"><tr><td colspan="5" class="table-empty">Loading...</td></tr></tbody>
      </table>
    </div>
  `;

  initClientList('hopeReturned', {
    bodyId: 'hopeReturnedBody', searchId: 'hopeReturnedSearch', pagerId: 'hopeReturnedPager',
    columns: 5, emptyText: 'No returned projects.',
    searchText: p => `${p.project_code} ${p.name} ${p.rejection_reason || ''}`,
    rowHtml: p => `
      <tr>
        <td class="cell-nowrap">${hopeEscape(p.project_code)}</td>
        <td><span class="cell-title">${hopeEscape(p.name)}</span></td>
        <td class="cell-money">${hopeMoney(p.budget)}</td>
        <td>${hopeEscape(p.rejection_reason || '-')}</td>
        <td><button class="btn-secondary btn-compact" onclick="hopeOpenProjectModal(${p.id})">View</button></td>
      </tr>`,
  });

  try {
    const result = await hopeFetchProjects({ status: 'returned', per_page: 50 });
    setClientListData('hopeReturned', result.data);
  } catch (error) {
    const body = document.getElementById('hopeReturnedBody');
    if (body) body.innerHTML = '<tr><td colspan="5" class="table-empty">Failed to load returned projects.</td></tr>';
  }
}

let hopeHistoryState = { page: 1, search: '' };

async function hopeRenderDecisionHistory() {
  const container = document.getElementById('page-decision-history');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Decision History</h2>
        <p class="hope-decision-note">Every project approval and contract award decision you've made.</p>
      </div>
    </div>
    ${listToolbarHtml('hopeHistorySearch', 'Search action, project, details...', 'hopeHistoryPager')}
    <div id="hopeHistoryTable" class="table-card"></div>
  `;

  hopeHistoryState = { page: 1, search: '' };
  document.getElementById('hopeHistorySearch').addEventListener('input', debounce(() => {
    hopeHistoryState.search = document.getElementById('hopeHistorySearch').value.trim();
    hopeHistoryState.page = 1;
    hopeLoadDecisionHistory();
  }, 300));
  await hopeLoadDecisionHistory();
}

async function hopeLoadDecisionHistory() {
  const wrap = document.getElementById('hopeHistoryTable');
  if (!wrap) return;
  wrap.innerHTML = '<div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>';

  try {
    const result = await hopeGet('decision_history', { page: hopeHistoryState.page, per_page: 15, search: hopeHistoryState.search });
    wrap.innerHTML = result.data.length ? `
      <table class="data-table">
        <thead><tr><th>Date</th><th>Action</th><th>Project</th><th>Details</th></tr></thead>
        <tbody>
          ${result.data.map(row => `
            <tr>
              <td>${hopeDate(row.created_at)}</td>
              <td>${hopeEscape(row.action)}</td>
              <td>${hopeEscape(row.project_code || '-')}</td>
              <td>${hopeEscape(row.details || '-')}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    ` : '<p class="empty-state">No decisions recorded yet.</p>';

    renderPagination(document.getElementById('hopeHistoryPager'), {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: 15,
      onPageChange: nextPage => { hopeHistoryState.page = nextPage; hopeLoadDecisionHistory(); },
    });
  } catch (error) {
    wrap.innerHTML = '<p class="empty-state">Failed to load decision history.</p>';
  }
}

/* ---- Projects by lifecycle stage (read-only oversight) ---------------------- */

async function hopeRenderProjectsByStage(pageId, title, statusIn) {
  const container = document.getElementById(`page-${pageId}`);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header"><div><h2 class="page-title">${hopeEscape(title)}</h2></div></div>
    ${listToolbarHtml(`${pageId}Search`, 'Search code, project, contractor...', `${pageId}Pager`)}
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>Code</th><th>Project</th><th>Budget</th><th>Contractor</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="${pageId}Body"><tr><td colspan="7" class="table-empty">Loading...</td></tr></tbody>
      </table>
    </div>
  `;

  initClientList(`stage-${pageId}`, {
    bodyId: `${pageId}Body`, searchId: `${pageId}Search`, pagerId: `${pageId}Pager`,
    columns: 7, emptyText: 'No projects in this category.',
    searchText: p => `${p.project_code} ${p.name} ${p.contractor_name || ''} ${p.status}`,
    rowHtml: p => `
      <tr>
        <td class="cell-nowrap">${hopeEscape(p.project_code)}</td>
        <td><span class="cell-title">${hopeEscape(p.name)}</span></td>
        <td class="cell-money">${hopeMoney(p.budget)}</td>
        <td>${hopeEscape(p.contractor_name || 'Unassigned')}</td>
        <td>
          <div class="cell-progress">
            <div class="mini-progress"><div style="width:${Number(p.progress) || 0}%"></div></div>
            <span>${Number(p.progress || 0)}%</span>
          </div>
        </td>
        <td>${hopeBadge(p.status)}</td>
        <td><button class="btn-secondary btn-compact" onclick="hopeOpenProjectModal(${p.id})">View</button></td>
      </tr>`,
  });

  try {
    const result = await hopeFetchProjects({ status_in: statusIn, per_page: 100 });
    setClientListData(`stage-${pageId}`, result.data);
  } catch (error) {
    const body = document.getElementById(`${pageId}Body`);
    if (body) body.innerHTML = '<tr><td colspan="7" class="table-empty">Failed to load projects.</td></tr>';
  }
}

function hopeRenderApprovedProjects() {
  return hopeRenderProjectsByStage('approved-projects', 'Approved Projects', 'approved,bidding,awarded,assigned');
}
function hopeRenderOngoingProjects() {
  return hopeRenderProjectsByStage('ongoing-projects', 'Ongoing Projects', 'active,delayed,on_hold,completion_inspection');
}
function hopeRenderCompletedProjects() {
  return hopeRenderProjectsByStage('completed-projects', 'Completed Projects', 'completed,turnover');
}

/* ---- Reports ----------------------------------------------------------------- */

function hopeHighRiskSummaryText(list) {
  if (!list || !list.length) return 'No high-risk projects detected — the infrastructure portfolio is currently on track.';
  return `${list.length} project(s) currently flagged high risk: ${list.map(p => hopeEscape(p.name)).join(', ')}.`;
}

async function hopeRenderExecutiveReports() {
  const container = document.getElementById('page-executive-reports');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Executive Reports</h2>
        <p class="hope-decision-note">A consolidated snapshot of procurement and project performance.</p>
      </div>
    </div>
    <div id="hopeExecReportBody"><div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div></div>
  `;

  const body = document.getElementById('hopeExecReportBody');
  try {
    const [summary, budget, procurement] = await Promise.all([
      hopeGet('summary'), hopeGet('budget_summary'), hopeGet('procurement_summary'),
    ]);
    const s = summary.stats;
    body.innerHTML = `
      <div class="hope-stat-grid">
        <div class="hope-stat-box"><span>Pending Project Approvals</span><strong>${s.pending_project_approvals}</strong></div>
        <div class="hope-stat-box"><span>Pending Award Approvals</span><strong>${s.pending_award_approvals}</strong></div>
        <div class="hope-stat-box"><span>Approved This Month</span><strong>${s.approved_this_month}</strong></div>
        <div class="hope-stat-box"><span>Delayed Projects</span><strong>${s.delayed}</strong></div>
        <div class="hope-stat-box"><span>Total Infrastructure Budget</span><strong>${hopeMoney(s.total_budget)}</strong></div>
        <div class="hope-stat-box"><span>Budget Utilization</span><strong>${budget.utilization_pct}%</strong></div>
        <div class="hope-stat-box"><span>Open Bids</span><strong>${procurement.open_bids}</strong></div>
        <div class="hope-stat-box"><span>Awards This Month</span><strong>${procurement.awarded_this_month}</strong></div>
      </div>
      <article class="info-card" style="margin-top:16px;">
        <p class="hope-decision-note" style="margin-bottom:0;">${hopeHighRiskSummaryText(summary.high_risk_projects)}</p>
      </article>
    `;
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Failed to load executive report.</p>';
  }
}

async function hopeRenderBudgetSummary() {
  const container = document.getElementById('page-budget-summary');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header"><div><h2 class="page-title">Budget Summary</h2></div></div>
    <div id="hopeBudgetBody"><div class="skeleton-group"><div class="skeleton-row"></div></div></div>
  `;

  const body = document.getElementById('hopeBudgetBody');
  try {
    const data = await hopeGet('budget_summary');
    body.innerHTML = `
      <div class="hope-stat-grid" style="margin-bottom:16px;">
        <div class="hope-stat-box"><span>Total Infrastructure Budget</span><strong>${hopeMoney(data.total_budget)}</strong></div>
        <div class="hope-stat-box"><span>Total Utilized</span><strong>${hopeMoney(data.total_spent)}</strong></div>
        <div class="hope-stat-box"><span>Utilization</span><strong>${data.utilization_pct}%</strong></div>
      </div>
      ${listToolbarHtml('hopeBudgetSearch', 'Search code, project, status...', 'hopeBudgetPager')}
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>Code</th><th>Project</th><th>Status</th><th>Budget</th><th>Spent</th></tr></thead>
          <tbody id="hopeBudgetTableBody"></tbody>
        </table>
      </div>
    `;

    initClientList('hopeBudget', {
      bodyId: 'hopeBudgetTableBody', searchId: 'hopeBudgetSearch', pagerId: 'hopeBudgetPager',
      columns: 5, emptyText: 'No projects with budget data.',
      searchText: p => `${p.project_code} ${p.name} ${p.status}`,
      rowHtml: p => `
        <tr>
          <td class="cell-nowrap">${hopeEscape(p.project_code)}</td>
          <td><span class="cell-title">${hopeEscape(p.name)}</span></td>
          <td>${hopeBadge(p.status)}</td>
          <td class="cell-money">${hopeMoney(p.budget)}</td>
          <td class="cell-money">${hopeMoney(p.spent)}</td>
        </tr>`,
    });
    setClientListData('hopeBudget', data.projects);
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Failed to load budget summary.</p>';
  }
}

async function hopeRenderProcurementSummary() {
  const container = document.getElementById('page-procurement-summary');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header"><div><h2 class="page-title">Procurement Summary</h2></div></div>
    <div id="hopeProcurementBody"><div class="skeleton-group"><div class="skeleton-row"></div></div></div>
  `;

  const body = document.getElementById('hopeProcurementBody');
  try {
    const data = await hopeGet('procurement_summary');
    body.innerHTML = `
      <div class="hope-stat-grid">
        <div class="hope-stat-box"><span>Open Bids</span><strong>${data.open_bids}</strong></div>
        <div class="hope-stat-box"><span>Bids for Evaluation</span><strong>${data.for_evaluation}</strong></div>
        <div class="hope-stat-box"><span>Pending Award Approvals</span><strong>${data.pending_award_approvals}</strong></div>
        <div class="hope-stat-box"><span>Awarded This Month</span><strong>${data.awarded_this_month}</strong></div>
        <div class="hope-stat-box"><span>Returned Recommendations</span><strong>${data.returned_recommendations}</strong></div>
        <div class="hope-stat-box"><span>Rejected Recommendations</span><strong>${data.rejected_recommendations}</strong></div>
      </div>
    `;
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Failed to load procurement summary.</p>';
  }
}

/* ---- Profile ------------------------------------------------------------------- */

async function hopeRenderProfilePage() {
  const container = document.getElementById('page-profile');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header"><div><h2 class="page-title">Profile</h2></div></div>
    <section class="hope-two-col">
      <article class="info-card">
        <h2 class="info-card-title">Account Details</h2>
        <form id="hopeAccountProfileForm">
          <div class="form-grid" id="hopeAccountProfileFields"><p class="empty-state">Loading...</p></div>
          <div class="form-actions"><button class="btn-primary" type="submit">Update Profile</button></div>
        </form>
      </article>
      <article class="info-card">
        <h2 class="info-card-title">Change Password</h2>
        <form id="hopeAccountPasswordForm">
          <div class="form-group"><label>Current Password</label><input class="form-input" type="password" name="current_password" required></div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group"><label>New Password</label><input class="form-input" type="password" name="new_password" required minlength="6"></div>
            <div class="form-group"><label>Confirm Password</label><input class="form-input" type="password" name="confirm_password" required minlength="6"></div>
          </div>
          <div class="form-actions"><button class="btn-primary" type="submit">Change Password</button></div>
        </form>
      </article>
    </section>
  `;

  document.getElementById('hopeAccountPasswordForm').addEventListener('submit', submitPasswordForm);
  document.getElementById('hopeAccountProfileForm').addEventListener('submit', submitProfileForm);

  try {
    const response = await fetch(HOPE_USER_API);
    const result = await response.json();
    const user = result.data || {};
    document.getElementById('hopeAccountProfileFields').innerHTML = `
      <div class="form-group"><label>Full Name</label><input class="form-input" name="full_name" required value="${hopeEscape(user.full_name)}"></div>
      <div class="form-group"><label>Email</label><input class="form-input" type="email" name="email" required value="${hopeEscape(user.email)}"></div>
    `;
  } catch {
    document.getElementById('hopeAccountProfileFields').innerHTML = '<p class="empty-state">Unable to load profile.</p>';
  }
}

const hopeRenderers = {
  dashboard: hopeRenderDashboard,
  'project-approvals': hopeRenderProjectApprovals,
  'award-approvals': hopeRenderAwardApprovals,
  'returned-projects': hopeRenderReturnedProjects,
  'deletion-requests': hopeRenderDeletionRequests,
  'decision-history': hopeRenderDecisionHistory,
  'approved-projects': hopeRenderApprovedProjects,
  'ongoing-projects': hopeRenderOngoingProjects,
  'completed-projects': hopeRenderCompletedProjects,
  'executive-reports': hopeRenderExecutiveReports,
  'budget-summary': hopeRenderBudgetSummary,
  'procurement-summary': hopeRenderProcurementSummary,
  profile: hopeRenderProfilePage,
};

function hopeShowPage(page) {
  hopeCurrentPage = page;

  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });
  document.querySelectorAll('.page-section').forEach(section => {
    section.style.display = section.id === `page-${page}` ? 'block' : 'none';
  });

  hopeRenderers[page]?.();
}

window.GLOBAL_SEARCH_NAVIGATE = hopeShowPage;
window.GLOBAL_SEARCH_SOURCES = [
  {
    label: 'Projects',
    url: PROJECTS_API,
    mapItem: (row) => ({
      title: row.name,
      meta: `${row.project_code || ''} · ${row.status || ''}`.replace(/^ · /, ''),
      page: 'project-approvals',
    }),
  },
];

/* ---- Shell / navigation ----------------------------------------------------- */

function hopeWireShell() {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      hopeShowPage(item.dataset.page || 'dashboard');
    });
  });

  // Sidebar toggle (open/close + backdrop) is handled by assets/js/sidebar-toggle.js.

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

  document.getElementById('modalClose')?.addEventListener('click', hopeCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') hopeCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') hopeCloseModal();
  });

  document.getElementById('searchInput')?.addEventListener('input', event => {
    const pageEl = document.getElementById(`page-${hopeCurrentPage}`);
    const candidates = pageEl ? pageEl.querySelectorAll('input[type="text"][id$="Search"]') : [];
    if (candidates.length === 1) {
      candidates[0].value = event.target.value;
      candidates[0].dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
}

/* ---- Profile / password ------------------------------------------------- */

async function showProfileSettings() {
  try {
    const response = await fetch(HOPE_USER_API);
    const result = await response.json();
    const user = result.data || {};
    hopeOpenModal('Profile Settings', `
      <form id="hopeProfileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-input" name="full_name" required value="${hopeEscape(user.full_name)}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-input" name="email" type="email" required value="${hopeEscape(user.email)}">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="hopeCloseModal()">Cancel</button>
          <button class="btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    `);
    document.getElementById('hopeProfileForm').addEventListener('submit', submitProfileForm);
  } catch (error) {
    hopeToast('Failed to load profile.', 'error');
  }
}

function showChangePassword() {
  hopeOpenModal('Change Password', `
    <form id="hopePasswordForm">
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
        <button class="btn-secondary" type="button" onclick="hopeCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Change Password</button>
      </div>
    </form>
  `);
  document.getElementById('hopePasswordForm').addEventListener('submit', submitPasswordForm);
}

async function submitProfileForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const body = new URLSearchParams({
    full_name: form.get('full_name'),
    email: form.get('email'),
  });

  try {
    const response = await fetch(HOPE_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...HOPE_CSRF_HEADERS },
      body,
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    document.querySelector('.user-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-email').textContent = form.get('email');
    hopeCloseModal();
    hopeToast('Profile updated.');
  } catch (error) {
    hopeToast(error.message, 'error');
  }
}

async function submitPasswordForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  if (form.get('new_password') !== form.get('confirm_password')) {
    hopeToast('New passwords do not match.', 'error');
    return;
  }

  try {
    const response = await fetch(HOPE_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...HOPE_CSRF_HEADERS },
      body: new URLSearchParams({
        current_password: form.get('current_password'),
        new_password: form.get('new_password'),
      }),
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    hopeCloseModal();
    hopeToast('Password changed.');
  } catch (error) {
    hopeToast(error.message, 'error');
  }
}

window.hopeShowPage = hopeShowPage;
window.hopeCloseModal = hopeCloseModal;
window.hopeRefresh = hopeRefresh;
window.hopeOpenDecisionModal = hopeOpenDecisionModal;
window.hopeOpenProjectModal = hopeOpenProjectModal;
window.hopeOpenAwardDetailModal = hopeOpenAwardDetailModal;
window.hopeOpenAwardDecisionModal = hopeOpenAwardDecisionModal;
window.hopeToast = hopeToast;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  hopeWireShell();
  await hopeRenderDashboard();
});
