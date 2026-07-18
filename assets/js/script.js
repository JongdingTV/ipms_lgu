/* ============================================================
   LGU Infrastructure Dashboard — assets/js/script.js
   Full live-data frontend
   ============================================================ */

// BASE_PATH is set by PHP in header.php
// If not set, calculate fallback
if (typeof window.BASE_PATH === 'undefined') {
  const pathname = window.location.pathname;
  const parts = pathname.split('/').filter(Boolean);
  window.BASE_PATH = parts.length > 0 ? '/' + parts[0] + '/' : '/';
  console.log('[JS] PHP BASE_PATH not set, calculated:', window.BASE_PATH);
} else {
  console.log('[JS] Using PHP-provided BASE_PATH:', window.BASE_PATH);
}

const API = {
  dashboard:   window.BASE_PATH + 'api/dashboard.php',
  projects:    window.BASE_PATH + 'api/projects.php',
  expenses:    window.BASE_PATH + 'api/expenses.php',
  contractors: window.BASE_PATH + 'api/contractors.php',
  feedback:    window.BASE_PATH + 'api/feedback.php',
  user:        window.BASE_PATH + 'api/user.php',
  users:       window.BASE_PATH + 'api/users.php',
  workflow:    window.BASE_PATH + 'api/workflow.php',
  staffAccounts: window.BASE_PATH + 'superadmin/api/accounts.php',
};

const CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

console.log('[API] Using endpoints:', API);

/* ── Tiny fetch helpers ── */
async function get(url, params = {}) {
  const qs = new URLSearchParams(params).toString();
  const fullUrl = qs ? `${url}?${qs}` : url;
  console.log('[FETCH] GET', fullUrl);
  try {
    const res = await fetch(fullUrl);
    if (!res.ok) {
      console.error('[FETCH] HTTP Error', res.status, res.statusText, 'URL:', fullUrl);
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  } catch (e) {
    console.error('[FETCH] Error:', e.message, 'URL:', fullUrl);
    throw e;
  }
}
async function post(url, body) {
  const res = await fetch(url, {
    method:'POST',
    headers:{ 'Content-Type':'application/json', ...CSRF_HEADERS },
    body: JSON.stringify(body)
  });
  return res.json();
}
async function postForm(url, formData) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { ...CSRF_HEADERS },
    body: formData,
  });
  return res.json();
}
async function postAction(url, action, body) {
  const res = await fetch(`${url}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  return res.json();
}
async function put(url, id, body) {
  const res = await fetch(`${url}?id=${id}`, {
    method:'PUT',
    headers:{ 'Content-Type':'application/json', ...CSRF_HEADERS },
    body: JSON.stringify(body)
  });
  return res.json();
}
async function del(url, id) {
  const res = await fetch(`${url}?id=${id}`, {
    method:'DELETE',
    headers: { ...CSRF_HEADERS }
  });
  return res.json();
}

/* ── Toast notifications ── */
function toast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
}

/* ── HTML-escape any server-sourced text before it reaches innerHTML ── */
function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

/* ── Loading overlay on cards ── */
function setLoading(el, on) {
  if (on) el.classList.add('loading'); else el.classList.remove('loading');
}

function formatMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString();
}

function formatStatus(value) {
  return String(value || '').replace(/_/g, ' ');
}

const PROJECT_STATUSES = [
  'draft',
  'returned',
  'planning',
  'approved',
  'bidding',
  'awarded',
  'assigned',
  'active',
  'delayed',
  'on_hold',
  'completed',
  'cancelled',
];

const PROJECT_STATUS_LABELS = {
  draft: 'Draft',
  returned: 'Returned',
  planning: 'Planning',
  approved: 'Approved',
  bidding: 'Bidding',
  awarded: 'Awarded',
  assigned: 'Assigned',
  active: 'Active',
  delayed: 'Delayed',
  on_hold: 'On Hold',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

function projectStatusOptions(selected = 'draft') {
  return PROJECT_STATUSES.map(status =>
    `<option value="${status}" ${selected === status ? 'selected' : ''}>${PROJECT_STATUS_LABELS[status] || formatStatus(status)}</option>`
  ).join('');
}

function formatRole(value) {
  const labels = {
    super_admin: 'Super Admin',
    admin: 'LGU Admin / Engineering Head',
    bac: 'BAC (Bids & Awards Committee)',
    engineer: 'Engineer',
    contractor: 'Contractor',
    citizen: 'Citizen / Public User',
  };
  return labels[value] || formatStatus(value);
}

function statusBadge(value) {
  return `<span class="badge status-${value}">${PROJECT_STATUS_LABELS[value] || formatStatus(value)}</span>`;
}

function progressColor(value) {
  return value >= 70 ? '#22c55e' : value >= 40 ? '#f97316' : '#ef4444';
}

function formatDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

/* ── Animated counter ── */
function animateCounter(el, target, isBudget = false) {
  const duration = 1200;
  const start = performance.now();
  function tick(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = isBudget ? (ease * target).toFixed(1) : Math.round(ease * target);
    if (p < 1) requestAnimationFrame(tick);
    else el.textContent = isBudget ? target.toFixed(1) : target;
  }
  requestAnimationFrame(tick);
}

/* ── Chart instances ── */
let progressChartInst = null;
let budgetChartInst   = null;

/* ============================================================
   PAGE ROUTER
   ============================================================ */
let currentPage = 'dashboard';

function navigate(page) {
  currentPage = page;

  // Update nav active state
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });

  // Show/hide sections
  document.querySelectorAll('.page-section').forEach(s => {
    s.style.display = s.id === `page-${page}` ? 'block' : 'none';
  });

  // Load page data
  const loaders = {
    dashboard: loadDashboard,
    'project-registration': () => loadProjectsPage('page-project-registration', 'Project Registration'),
    'project-approval': loadProjectApprovalPage,
    'contractor-assignment': loadContractorAssignmentPage,
    'workflow-management': loadWorkflowManagementPage,
    'budget-monitoring': () => loadBudgetPage('page-budget-monitoring', 'Budget Monitoring'),
    'milestone-overview': loadMilestoneOverviewPage,
    'gis-map': loadGisMapPage,
    reports: loadReportsPage,
    'ai-risk-insights': loadAIRiskInsightsPage,
    'citizen-feedback': () => loadFeedbackPage('page-citizen-feedback', 'Citizen Feedback Review', false),
    'staff-requests': loadStaffRequestsPage,
  };
  if (loaders[page]) loaders[page]();
}

window.GLOBAL_SEARCH_NAVIGATE = navigate;
window.GLOBAL_SEARCH_SOURCES = [
  {
    label: 'Projects',
    url: API.projects,
    mapItem: (row) => ({
      title: row.name,
      meta: `${row.project_code || ''} · ${row.status || ''}`.replace(/^ · /, ''),
      page: 'project-registration',
    }),
  },
  {
    label: 'Contractors',
    url: API.contractors,
    mapItem: (row) => ({
      title: row.name,
      meta: row.contact_person || row.email || '',
      page: 'contractor-assignment',
    }),
  },
];

/* ============================================================
   DASHBOARD
   ============================================================ */
async function loadDashboard() {
  const content = document.querySelector('.content');
  setLoading(content, true);
  try {
    const d = await get(API.dashboard);

    // KPI counters
    const kpiMap = {
      'kpi-active':  { target: d.active_projects,  budget: false },
      'kpi-delayed': { target: d.delayed_projects, budget: false },
      'kpi-budget':  { target: d.total_spent / 1_000_000, budget: true },
      'kpi-alerts':  { target: d.high_risk_alerts, budget: false },
    };
    Object.entries(kpiMap).forEach(([id, cfg]) => {
      const el = document.getElementById(id);
      if (el) animateCounter(el, cfg.target, cfg.budget);
    });

    // Budget total label
    const totalEl = document.getElementById('kpi-budget-total');
    if (totalEl) totalEl.textContent = `/ ₱${(d.total_budget / 1_000_000).toFixed(1)}M`;

    // Progress chart
    renderProgressChart(d.progress_chart);

    // Budget donut
    renderBudgetDonut(d.budget_donut, d.budget_pct);

    // Top delayed
    renderTopDelayed(d.top_delayed);

    // Anomalies
    renderAnomalies(d.budget_anomalies);

    // Feedback
    renderFeedbackWidget(d.recent_feedback);

    // AI insights
    renderAIInsights(d.ai_insights);

    renderWorkflowConnections(d.workflow_connections, d.recent_workflow);

  } catch (e) {
    toast('Failed to load dashboard data', 'error');
    console.error(e);
  } finally {
    setLoading(content, false);
  }
}

function renderProgressChart(data) {
  const ctx = document.getElementById('progressChart')?.getContext('2d');
  if (!ctx) return;
  if (progressChartInst) progressChartInst.destroy();

  progressChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.map(d => d.month),
      datasets: [
        {
          label: 'Planned',
          data: data.map(d => d.planned),
          borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)',
          borderWidth: 2.5, tension: 0.4, fill: true,
          pointBackgroundColor: '#3b82f6', pointBorderColor: '#fff',
          pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
        },
        {
          label: 'Actual',
          data: data.map(d => d.actual),
          borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.06)',
          borderWidth: 2.5, tension: 0.4, fill: true,
          pointBackgroundColor: '#22c55e', pointBorderColor: '#fff',
          pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          callbacks: { label: c => ` ${c.dataset.label}: ${c.raw}%` }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } }, border: { display: false } },
        y: { min: 0, max: 100, ticks: { stepSize: 25, color: '#94a3b8', font: { size: 11 }, callback: v => v + '%' },
             grid: { color: '#f1f5f9' }, border: { display: false } }
      }
    }
  });
}

function renderBudgetDonut(donut, pct) {
  const ctx = document.getElementById('budgetChart')?.getContext('2d');
  if (!ctx) return;
  if (budgetChartInst) budgetChartInst.destroy();

  const total = donut.spent + donut.remaining + donut.anomaly || 1;
  budgetChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Spent', 'Remaining', 'Anomaly'],
      datasets: [{
        data: [donut.spent, donut.remaining, donut.anomaly],
        backgroundColor: ['#3b82f6','#22c55e','#ef4444'],
        borderColor: ['#fff','#fff','#fff'], borderWidth: 3, hoverOffset: 6
      }]
    },
    options: {
      responsive: false, cutout: '70%',
      animation: { duration: 900 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          callbacks: { label: c => ` ${c.label}: ₱${Number(c.raw).toLocaleString()}` }
        }
      }
    }
  });

  const pctEl = document.querySelector('.donut-pct');
  if (pctEl) pctEl.textContent = pct + '%';

  // Update legend
  const items = document.querySelectorAll('.budget-legend-item strong');
  if (items[0]) items[0].textContent = pct + '%';
  if (items[1]) items[1].textContent = (100 - pct) + '%';
}

function renderTopDelayed(projects) {
  const list = document.querySelector('.delayed-list');
  if (!list) return;
  list.innerHTML = projects.length ? projects.map(p => `
    <div class="delayed-item">
      <span class="proj-id">#${p.id}</span>
      <span class="proj-name">${escapeHtml(p.name)}</span>
      <button class="btn-view" onclick="openProjectModal(${p.id})">View</button>
    </div>
  `).join('') : '<p class="empty-state">No delayed projects 🎉</p>';
}

function renderAnomalies(anomalies) {
  const list = document.querySelector('.anomaly-list');
  if (!list) return;
  list.innerHTML = anomalies.length ? anomalies.map(a => {
    const pct = a.budget > 0 ? Math.round(((a.total_spent - a.budget) / a.budget) * 100) : 0;
    const label = pct > 0 ? `Over Budget +${pct}%` : 'High Expense Spike';
    const cls   = pct > 0 ? 'badge-overbudget' : 'badge-spike';
    return `
      <div class="anomaly-item">
        <span class="anomaly-name">${a.project_name}</span>
        <span class="badge ${cls}">${label}</span>
      </div>`;
  }).join('') : '<p class="empty-state">No anomalies detected ✓</p>';
}

function renderFeedbackWidget(items) {
  const list = document.querySelector('.feedback-list');
  if (!list) return;
  const priorityClass = { urgent:'badge-urgent', high:'badge-highprio', medium:'badge-resolved', low:'badge-resolved' };
  const priorityLabel = { urgent:'Urgent', high:'High Priority', medium:'Medium', low:'Low' };
  list.innerHTML = items.map(f => `
    <div class="feedback-item">
      <div class="feedback-icon feedback-red">
        <span aria-hidden="true">!</span>
      </div>
      <span class="feedback-text">${escapeHtml(f.message.slice(0,50))}${f.message.length>50?'…':''}</span>
      <span class="badge ${priorityClass[f.priority] || 'badge-resolved'}">${priorityLabel[f.priority] || 'Normal'}</span>
    </div>
  `).join('') || '<p class="empty-state">No feedback yet</p>';
}

function renderAIInsights(ai) {
  const riskEl = document.querySelector('.ai-card.ai-red .ai-card-title');
  if (riskEl) riskEl.innerHTML = `Delay Risk: <strong>${ai.delay_risk}</strong>`;

  const alertEl = document.querySelector('.ai-card.ai-orange .ai-card-title');
  if (alertEl) alertEl.innerHTML = ai.budget_alert
    ? '<strong>Budget Alert:</strong> Overspending Detected'
    : '<strong>Budget:</strong> Within Normal Range';

  const cEl = document.querySelector('.ai-card.ai-green .ai-card-title');
  if (cEl && ai.top_contractor)
    cEl.innerHTML = `<strong>Top Contractor:</strong> ${ai.top_contractor.name} (Score: ${ai.top_contractor.performance_score})`;
}

function renderWorkflowConnections(connections, recent) {
  const summary = document.getElementById('workflowConnectionList');
  if (summary && connections) {
    summary.innerHTML = `
      <div class="anomaly-item"><span class="anomaly-name">Contracts</span><span class="badge badge-resolved">${connections.contracts || 0}</span></div>
      <div class="anomaly-item"><span class="anomaly-name">Active Contracts</span><span class="badge badge-highprio">${connections.active_contracts || 0}</span></div>
      <div class="anomaly-item"><span class="anomaly-name">Engineer Inspections</span><span class="badge badge-spike">${connections.inspections || 0}</span></div>
      <div class="anomaly-item"><span class="anomaly-name">Pending Payment Requests</span><span class="badge badge-urgent">${connections.pending_payment_requests || 0}</span></div>
    `;
  }

  const activity = document.getElementById('workflowActivityList');
  if (activity) {
    activity.innerHTML = (recent || []).length ? recent.map(row => `
      <div class="feedback-item">
        <div class="feedback-icon feedback-red"><span aria-hidden="true">${row.record_type.slice(0, 1)}</span></div>
        <span class="feedback-text">${row.project_code} - ${row.record_type}: ${row.details}</span>
        <span class="badge badge-resolved">${formatStatus(row.status)}</span>
      </div>
    `).join('') : '<p class="empty-state">No connected workflow records yet.</p>';
  }
}

async function workflowGet(action = 'summary') {
  return get(API.workflow, { action });
}

async function workflowPost(action, body) {
  const res = await fetch(`${API.workflow}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (!res.ok || data.error) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

async function loadWorkflowManagementPage() {
  const container = document.getElementById('page-workflow-management');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Contract & Payment Review</h2>
      <button class="btn-secondary" onclick="loadWorkflowManagementPage()">Refresh</button>
    </div>
    <section class="admin-summary-grid" id="workflowSummaryCards"></section>
    <div class="table-card" id="workflowPaymentsTable" style="margin-top:12px;"></div>
    <div class="table-card" id="workflowContractsTable" style="margin-top:12px;"></div>
    <div class="table-card" id="workflowInspectionsTable" style="margin-top:12px;"></div>
  `;

  try {
    const data = await workflowGet('summary');
    renderWorkflowManagement(data);
  } catch (error) {
    toast(error.message || 'Failed to load workflow records.', 'error');
  }
}

function renderWorkflowManagement(data) {
  const payments = data.payment_requests || [];
  const contracts = data.contracts || [];
  const inspections = data.inspections || [];

  const summary = document.getElementById('workflowSummaryCards');
  if (summary) {
    summary.innerHTML = `
      <article class="admin-summary-card"><span>Contracts</span><strong>${contracts.length}</strong></article>
      <article class="admin-summary-card"><span>Payment Requests</span><strong>${payments.length}</strong></article>
      <article class="admin-summary-card"><span>Pending Review</span><strong>${payments.filter(p => ['submitted','under_review'].includes(p.status)).length}</strong></article>
      <article class="admin-summary-card"><span>Inspections</span><strong>${inspections.length}</strong></article>
    `;
  }

  const paymentWrap = document.getElementById('workflowPaymentsTable');
  if (paymentWrap) {
    paymentWrap.innerHTML = `
      <h3 style="padding:14px 16px 0;margin:0;">Payment Requests</h3>
      <table class="data-table">
        <thead><tr><th>Billing</th><th>Project</th><th>Contractor</th><th>Report</th><th>Amount</th><th>Status</th><th>Latest Review</th><th>Action</th></tr></thead>
        <tbody>
          ${payments.length ? payments.map(row => `
            <tr>
              <td><span class="proj-id">${row.billing_no}</span><br><small>${formatDate(row.submitted_at)}</small></td>
              <td><strong>${row.project_code}</strong><br><small>${row.project_name}</small></td>
              <td>${row.contractor_name}</td>
              <td>${formatDate(row.report_date)}<br><small>${Number(row.progress_percent || 0)}%</small></td>
              <td>${formatMoney(row.requested_amount)}</td>
              <td>${statusBadgeForWorkflow(row.status)}</td>
              <td>${row.latest_review || 'No review yet'}</td>
              <td>
                ${row.status === 'approved' ? `
                  <button class="btn-primary btn-compact" onclick="markPaymentPaid(${row.id})">Mark as Paid</button>
                ` : ['paid', 'rejected'].includes(row.status) ? `
                  <small>No further action</small>
                ` : `
                  <button class="btn-primary btn-compact" onclick="openPaymentReviewModal(${row.id}, 'approve')">Approve</button>
                  <button class="btn-secondary btn-compact" onclick="openPaymentReviewModal(${row.id}, 'return')">Return</button>
                  <button class="btn-secondary btn-compact" onclick="openPaymentReviewModal(${row.id}, 'reject')">Reject</button>
                `}
              </td>
            </tr>
          `).join('') : '<tr><td colspan="8"><p class="empty-state">No payment requests yet.</p></td></tr>'}
        </tbody>
      </table>
    `;
  }

  const contractWrap = document.getElementById('workflowContractsTable');
  if (contractWrap) {
    contractWrap.innerHTML = `
      <h3 style="padding:14px 16px 0;margin:0;">Contracts</h3>
      <table class="data-table">
        <thead><tr><th>Contract No.</th><th>Project</th><th>Contractor</th><th>Amount</th><th>Schedule</th><th>Status</th></tr></thead>
        <tbody>
          ${contracts.length ? contracts.map(row => `
            <tr>
              <td><span class="proj-id">${row.contract_no}</span></td>
              <td><strong>${row.project_code}</strong><br><small>${row.project_name}</small></td>
              <td>${row.contractor_name}</td>
              <td>${formatMoney(row.contract_amount)}</td>
              <td>${formatDate(row.contract_start_date)} to ${formatDate(row.contract_end_date)}</td>
              <td>${statusBadgeForWorkflow(row.status)}</td>
            </tr>
          `).join('') : '<tr><td colspan="6"><p class="empty-state">No contracts yet. BAC recommendations will create contracts.</p></td></tr>'}
        </tbody>
      </table>
    `;
  }

  const inspectionWrap = document.getElementById('workflowInspectionsTable');
  if (inspectionWrap) {
    inspectionWrap.innerHTML = `
      <h3 style="padding:14px 16px 0;margin:0;">Engineer Inspections</h3>
      <table class="data-table">
        <thead><tr><th>Date</th><th>Project</th><th>Engineer</th><th>Reported</th><th>Actual</th><th>Recommendation</th></tr></thead>
        <tbody>
          ${inspections.length ? inspections.map(row => `
            <tr>
              <td>${formatDate(row.inspection_date)}</td>
              <td><strong>${row.project_code}</strong><br><small>${row.project_name}</small></td>
              <td>${row.engineer_name}</td>
              <td>${Number(row.reported_progress || 0)}%</td>
              <td>${Number(row.actual_progress_percent || 0)}%</td>
              <td>${statusBadgeForWorkflow(row.recommendation)}</td>
            </tr>
          `).join('') : '<tr><td colspan="6"><p class="empty-state">No inspections yet.</p></td></tr>'}
        </tbody>
      </table>
    `;
  }
}

function statusBadgeForWorkflow(value) {
  return `<span class="badge status-${String(value || '').replace(/[^a-z0-9_-]/gi, '')}">${formatStatus(value)}</span>`;
}

function openPaymentReviewModal(paymentId, recommendation) {
  openModal(`${formatStatus(recommendation)} Payment Request`, `
    <form id="paymentReviewForm" onsubmit="submitPaymentReview(event, ${paymentId}, '${recommendation}')">
      <div class="form-group">
        <label>Recommendation</label>
        <input class="form-input" disabled value="${formatStatus(recommendation)}">
      </div>
      <div class="form-group">
        <label>Remarks</label>
        <textarea class="form-input" name="remarks" rows="4" placeholder="Review notes, reason, or approval details"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Save Review</button>
      </div>
    </form>
  `);
}

async function markPaymentPaid(paymentId) {
  if (!confirm('Mark this payment request as paid? This finalizes the payment lifecycle.')) return;
  try {
    await workflowPost('mark_paid', { payment_request_id: paymentId });
    toast('Payment marked as paid.');
    loadWorkflowManagementPage();
    loadDashboard();
  } catch (error) {
    toast(error.message || 'Unable to mark payment as paid.', 'error');
  }
}

async function submitPaymentReview(event, paymentId, recommendation) {
  event.preventDefault();
  const form = new FormData(event.target);
  try {
    await workflowPost('payment_review', {
      payment_request_id: paymentId,
      recommendation,
      remarks: form.get('remarks'),
    });
    toast('Payment review saved.');
    closeModal();
    loadWorkflowManagementPage();
    loadDashboard();
  } catch (error) {
    toast(error.message || 'Unable to save payment review.', 'error');
  }
}

/* ============================================================
   PROJECT MODAL (from dashboard)
   ============================================================ */
async function openProjectModal(id) {
  try {
    const p = await get(API.projects, { id });
    const color = p.progress >= 70 ? '#22c55e' : p.progress >= 40 ? '#f97316' : '#ef4444';
    openModal(`Project #${p.id} — ${escapeHtml(p.name)}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${p.location || '—'}</p></div>
          <div><p class="modal-label">CONTRACTOR</p><p class="modal-val">${p.contractor_name || '—'}</p></div>
          <div><p class="modal-label">BUDGET</p><p class="modal-val">₱${Number(p.budget).toLocaleString()}</p></div>
          <div><p class="modal-label">SPENT</p><p class="modal-val">₱${Number(p.total_spent).toLocaleString()}</p></div>
          <div><p class="modal-label">STATUS</p><p class="modal-val">${statusBadge(p.status)}</p></div>
          <div><p class="modal-label">END DATE</p><p class="modal-val">${p.end_date}</p></div>
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
                <span style="color:${m.completed?'#22c55e':'#94a3b8'};">${m.completed?'✓':'○'}</span>
                <span style="color:${m.completed?'#1e293b':'#64748b'};text-decoration:${m.completed?'line-through':'none'}">${m.title}</span>
                <span style="margin-left:auto;color:#94a3b8;">${m.due_date}</span>
              </div>
            `).join('')}
          </div>
        </div>` : ''}
        <div>
          <p class="modal-label">SUPPORTING DOCUMENTS</p>
          <div id="projectDocList" style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
            ${p.documents?.length ? p.documents.map(d => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;padding:8px 10px;background:#f8fafc;border-radius:6px;">
                <a href="${window.BASE_PATH || ''}${d.file_path}" target="_blank" rel="noopener" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(d.title)}</a>
                <span style="color:#94a3b8;">${escapeHtml(d.document_type)}</span>
                <span style="font-family:monospace;color:#64748b;">v${d.version}</span>
                <button type="button" class="btn-secondary btn-compact" onclick="openDocumentVersions(${d.id}, '${escapeHtml(d.title)}')">History</button>
                <button type="button" class="btn-secondary btn-compact" onclick="openUploadDocumentVersion(${d.id}, '${escapeHtml(d.title)}')">Upload New Version</button>
              </div>
            `).join('') : '<p class="empty-state">No documents attached.</p>'}
          </div>
        </div>
      </div>
    `);
  } catch (e) {
    toast('Failed to load project details', 'error');
  }
}

async function openDocumentVersions(documentId, title) {
  try {
    const res = await get(API.projects, { action: 'document_versions', document_id: documentId });
    const rows = res.data || [];
    openModal(`Version History — ${title}`, `
      <div style="display:flex;flex-direction:column;gap:6px;">
        ${rows.map(v => `
          <div style="display:flex;align-items:center;gap:10px;font-size:.82rem;padding:8px 10px;background:#f8fafc;border-radius:6px;">
            <span style="font-family:monospace;font-weight:700;color:${v.is_current ? '#16a34a' : '#64748b'};">v${v.version}${v.is_current ? ' (current)' : ''}</span>
            <a href="${window.BASE_PATH || ''}${v.file_path}" target="_blank" rel="noopener" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(v.original_name)}</a>
            <span style="color:#94a3b8;">${formatDate(v.created_at)}</span>
          </div>
        `).join('') || '<p class="empty-state">No version history.</p>'}
      </div>
    `);
  } catch (e) {
    toast('Failed to load version history', 'error');
  }
}

function openUploadDocumentVersion(documentId, title) {
  openModal(`Upload New Version — ${title}`, `
    <form id="docVersionForm" onsubmit="submitDocumentVersion(event, ${documentId})">
      <div class="form-group">
        <label>New File *</label>
        <input class="form-input" type="file" name="file" required>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Upload</button>
      </div>
    </form>
  `);
}

async function submitDocumentVersion(e, documentId) {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.set('document_id', documentId);
  try {
    const res = await fetch(`${API.projects}?action=upload_document_version`, {
      method: 'POST',
      headers: { ...CSRF_HEADERS },
      body: formData,
    });
    const data = await res.json();
    if (data.error) { toast(data.error, 'error'); return; }
    toast('New version uploaded.');
    closeModal();
  } catch {
    toast('Failed to upload new version', 'error');
  }
}

/* ============================================================
   PROJECTS PAGE
   ============================================================ */
let projectsState = { page: 1, search: '', status: '' };

const PROJECT_DOC_TYPES = [
  'Feasibility Study',
  'Site Assessment',
  'Budget Justification',
  'Environmental Compliance Certificate',
  'Other',
];

function projectDocRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <select class="form-input" name="documents[${index}][document_type]">
        ${PROJECT_DOC_TYPES.map(type => `<option value="${escapeHtml(type)}">${escapeHtml(type)}</option>`).join('')}
      </select>
      <input class="form-input" type="text" name="documents[${index}][title]" placeholder="Document title">
      <input class="form-input" type="file" name="document_files[${index}]">
      <button type="button" class="doc-row-remove" aria-label="Remove document row">&times;</button>
    </div>
  `;
}

function wireProjectDocRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', projectDocRowHtml(nextIndex));
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

async function loadProjectsPage(containerId = 'page-project-registration', title = 'Project Registration') {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
      <button class="btn-primary" onclick="showProjectForm()">+ New Project</button>
    </div>
    <div class="filter-bar">
      <input class="filter-input" id="projSearch" placeholder="Search projects…" oninput="projectsState.search=this.value;projectsState.page=1;fetchProjects()" />
      <select class="filter-select" onchange="projectsState.status=this.value;projectsState.page=1;fetchProjects()">
        <option value="">All Statuses</option>
        ${PROJECT_STATUSES.map(status => `<option value="${status}">${PROJECT_STATUS_LABELS[status]}</option>`).join('')}
      </select>
    </div>
    <div id="projectsTable" class="table-card"></div>
    <div id="projectsPager" class="pager"></div>
  `;
  fetchProjects();
}

async function fetchProjects() {
  const wrap = document.getElementById('projectsTable');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.projects, {
      page: projectsState.page,
      search: projectsState.search,
      status: projectsState.status,
    });
    renderProjectsTable(d.data);
    renderPager('projectsPager', d.page, d.last_page, p => { projectsState.page = p; fetchProjects(); });
  } catch (e) {
    wrap.innerHTML = '<p class="empty-state">Failed to load projects.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderProjectsTable(rows) {
  const wrap = document.getElementById('projectsTable');
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No projects found.</p>'; return; }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th><th>Name</th><th>Location</th><th>Contractor</th>
          <th>Budget</th><th>Spent</th><th>Progress</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${rows.map(p => {
          const pct  = p.budget > 0 ? Math.round((p.total_spent / p.budget) * 100) : 0;
          const bar  = p.progress >= 70 ? '#22c55e' : p.progress >= 40 ? '#f97316' : '#ef4444';
          return `
          <tr>
            <td><span class="proj-id">${escapeHtml(p.project_code)}</span></td>
            <td><strong>${escapeHtml(p.name)}</strong><br><small style="color:#94a3b8">${escapeHtml(p.location||'')}</small></td>
            <td>${escapeHtml(p.location || '—')}</td>
            <td>${p.contractor_name || '—'}</td>
            <td>₱${Number(p.budget).toLocaleString()}</td>
            <td>₱${Number(p.total_spent).toLocaleString()} <small style="color:${pct>100?'#ef4444':'#94a3b8'}">(${pct}%)</small></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <div style="flex:1;background:#f1f5f9;border-radius:20px;height:6px;overflow:hidden;">
                  <div style="width:${p.progress}%;background:${bar};height:100%;border-radius:20px;"></div>
                </div>
                <span style="font-size:.75rem;color:#64748b;min-width:28px;">${p.progress}%</span>
              </div>
            </td>
            <td>${statusBadge(p.status)}</td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" title="View" onclick="openProjectModal(${p.id})">👁</button>
                <button class="btn-icon" title="Edit" onclick="showProjectForm(${p.id})">✏️</button>
                <button class="btn-icon btn-danger" title="Delete" onclick="deleteProject(${p.id})">🗑</button>
              </div>
            </td>
          </tr>`;
        }).join('')}
      </tbody>
    </table>
  `;
}

async function showProjectForm(id = null) {
  let p = null;
  try {
    if (id) p = await get(API.projects, { id });
  } catch {}

  const title = id ? `Edit Project #${id}` : 'New Project';
  openModal(title, `
    <form id="projectForm" onsubmit="submitProjectForm(event, ${id})">
      <div class="form-grid">
        <div class="form-group">
          <label>Project Name *</label>
          <input name="name" class="form-input" required value="${p?.name||''}" />
        </div>
        <div class="form-group">
          <label>Location *</label>
          <input name="location" class="form-input" required value="${p?.location||''}" />
        </div>
        <div class="form-group">
          <label>Latitude <small>(for GIS map, optional)</small></label>
          <input name="latitude" type="number" step="0.0000001" min="-90" max="90" class="form-input" placeholder="e.g. 14.6760" value="${p?.latitude ?? ''}" />
        </div>
        <div class="form-group">
          <label>Longitude <small>(for GIS map, optional)</small></label>
          <input name="longitude" type="number" step="0.0000001" min="-180" max="180" class="form-input" placeholder="e.g. 121.0437" value="${p?.longitude ?? ''}" />
        </div>
        <div class="form-group">
          <label>Budget (₱) *</label>
          <input name="budget" type="number" step="0.01" class="form-input" required value="${p?.budget||''}" />
        </div>
        <div class="form-group">
          <label>Start Date *</label>
          <input name="start_date" type="date" class="form-input" required value="${p?.start_date||''}" />
        </div>
        <div class="form-group">
          <label>End Date *</label>
          <input name="end_date" type="date" class="form-input" required value="${p?.end_date||''}" />
        </div>
        <div class="form-group">
          <label>Progress (%)</label>
          <input name="progress" type="number" min="0" max="100" class="form-input" value="${p?.progress||0}" />
        </div>
        ${id ? `
          <div class="form-group">
            <label>Current Status</label>
            <input class="form-input" disabled value="${PROJECT_STATUS_LABELS[p?.status] || formatStatus(p?.status)}" />
          </div>
        ` : '<input type="hidden" name="status" value="draft" />'}
      </div>
      <div class="form-group" style="margin-top:8px;">
        <label>Description *</label>
        <textarea name="description" class="form-input" rows="3" required>${p?.description||''}</textarea>
      </div>
      ${!id ? `
        <div class="form-group" style="margin-top:8px;">
          <label>Supporting Documents * <small>(at least one required — feasibility study, site assessment, budget justification, etc.)</small></label>
          <div class="doc-rows" id="projectDocRows">${projectDocRowHtml(0)}</div>
          <button type="button" class="doc-add-btn" id="projectDocAddBtn">+ Add another document</button>
        </div>
      ` : ''}
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">${id ? 'Update' : 'Submit for Registration'}</button>
      </div>
    </form>
  `);

  if (!id) {
    wireProjectDocRows(document.getElementById('projectDocRows'), document.getElementById('projectDocAddBtn'));
  }
}

async function submitProjectForm(e, id) {
  e.preventDefault();
  try {
    let res;
    if (id) {
      const fd = new FormData(e.target);
      const body = Object.fromEntries(fd.entries());
      res = await put(API.projects, id, body);
    } else {
      res = await postForm(API.projects, new FormData(e.target));
    }
    if (res.error) { toast(res.error, 'error'); return; }
    toast(id ? 'Project updated!' : 'Project registered — awaiting Engineering Review.');
    closeModal();
    fetchProjects();
  } catch { toast('Something went wrong', 'error'); }
}

async function deleteProject(id) {
  if (!confirm('Delete this project? This will also delete related expenses and milestones.')) return;
  try {
    const res = await del(API.projects, id);
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Project deleted');
    fetchProjects();
  } catch { toast('Delete failed', 'error'); }
}

/* ============================================================
   PROJECT APPROVAL
   ============================================================ */
let approvalState = { page: 1, search: '', status: '' };

async function loadProjectApprovalPage() {
  const container = document.getElementById('page-project-approval');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Project Approval</h2>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search projects..."
        oninput="approvalState.search=this.value;approvalState.page=1;fetchApprovalProjects()" />
      <select class="filter-select" onchange="approvalState.status=this.value;approvalState.page=1;fetchApprovalProjects()">
        <option value="">All Projects</option>
        <option value="draft">Draft / Awaiting Engineering Review</option>
        <option value="endorsed">Endorsed / Awaiting HOPE Review</option>
        <option value="returned">Returned for Revision</option>
        <option value="approved">Approved</option>
        <option value="bidding">In BAC Bidding</option>
        <option value="awarded">Awarded</option>
        <option value="assigned">Assigned / Awaiting Notice to Proceed</option>
        <option value="active">Active Implementation</option>
        <option value="delayed">Delayed</option>
        <option value="completion_inspection">Awaiting Completion Inspection</option>
        <option value="completed">Completed / Awaiting Turnover</option>
        <option value="turnover">Turned Over</option>
        <option value="cancelled">Rejected / Cancelled</option>
      </select>
    </div>
    <div id="approvalTable" class="table-card" style="margin-top:12px;"></div>
    <div id="approvalPager" class="pager"></div>
  `;

  fetchApprovalProjects();
}

async function fetchApprovalProjects() {
  const wrap = document.getElementById('approvalTable');
  if (!wrap) return;
  setLoading(wrap, true);

  try {
    const d = await get(API.projects, {
      page: approvalState.page,
      search: approvalState.search,
      status: approvalState.status,
    });
    renderApprovalTable(d.data);
    renderPager('approvalPager', d.page, d.last_page, p => { approvalState.page = p; fetchApprovalProjects(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load projects for approval.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderApprovalTable(rows) {
  const wrap = document.getElementById('approvalTable');
  if (!wrap) return;
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No projects found for this approval filter.</p>';
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
            <td><span class="proj-id">${p.project_code}</span></td>
            <td><strong>${escapeHtml(p.name)}</strong><br><small style="color:#94a3b8">${escapeHtml(p.location || '-')}</small></td>
            <td>${formatMoney(p.budget)}</td>
            <td>${formatDate(p.start_date)} to ${formatDate(p.end_date)}</td>
            <td>${p.contractor_name || 'Unassigned'}</td>
            <td>${statusBadge(p.status)}</td>
            <td>
              <div class="inline-actions">
                ${p.status === 'draft' ? '<small>Awaiting Engineering Review</small>' : ''}
                ${p.status === 'endorsed' ? '<small>Awaiting HOPE review</small>' : ''}
                ${p.status === 'assigned' ? '<small>Awaiting Notice to Proceed</small>' : ''}
                ${p.status === 'completion_inspection' ? '<small>Awaiting completion inspection</small>' : ''}
                ${p.status === 'completed' ? `<button class="btn-primary btn-compact" onclick="openTurnoverModal(${p.id}, '${escapeHtml(p.name)}')">Record Turnover</button>` : ''}
                <button class="btn-secondary btn-compact" onclick="openProjectModal(${p.id})">View</button>
              </div>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function openTurnoverModal(id, projectName) {
  openModal('Record Turnover', `
    <form id="turnoverForm" onsubmit="submitTurnover(event, ${id})">
      <p style="font-size:.85rem; color:#64748b; margin-bottom:12px;">Turning over <strong>${projectName}</strong> to the receiving office closes out the project record.</p>
      <div class="form-group">
        <label>Receiving Office / Barangay *</label>
        <input class="form-input" name="turnover_office" required placeholder="e.g. Barangay 7 / DPWH District Office">
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea class="form-input" name="notes" rows="3" placeholder="Turnover remarks"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm Turnover</button>
      </div>
    </form>
  `);
}

async function submitTurnover(e, id) {
  e.preventDefault();
  const form = new FormData(e.target);
  try {
    const res = await postAction(API.projects, 'turnover', {
      project_id: id,
      turnover_office: form.get('turnover_office'),
      notes: form.get('notes'),
    });
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Turnover recorded.');
    closeModal();
    fetchApprovalProjects();
  } catch {
    toast('Failed to record turnover', 'error');
  }
}

/* ============================================================
   CONTRACTOR ASSIGNMENT
   ============================================================ */
let assignmentState = { page: 1, search: '', contractor_id: '' };
let assignmentContractors = [];
let assignmentEngineers = [];

async function loadContractorAssignmentPage() {
  const container = document.getElementById('page-contractor-assignment');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Contractor Assignment</h2>
      <button class="btn-primary" onclick="showContractorForm()">+ Add Contractor</button>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search projects..."
        oninput="assignmentState.search=this.value;assignmentState.page=1;fetchAssignmentProjects()" />
      <select id="assignmentContractorFilter" class="filter-select"
        onchange="assignmentState.contractor_id=this.value;assignmentState.page=1;fetchAssignmentProjects()">
        <option value="">All Contractors</option>
      </select>
    </div>
    <div id="assignmentSummary" class="admin-summary-grid"></div>
    <div id="assignmentTable" class="table-card" style="margin-top:12px;"></div>
    <div id="assignmentPager" class="pager"></div>
  `;

  await loadAssignmentContractors();
  await loadAssignmentEngineers();
  fetchAssignmentProjects();
}

async function loadAssignmentContractors() {
  try {
    const d = await get(API.contractors, { page: 1, _limit: 100 });
    assignmentContractors = d.data || [];
    const filter = document.getElementById('assignmentContractorFilter');
    if (filter) {
      filter.innerHTML = '<option value="">All Contractors</option>' + assignmentContractors
        .map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`)
        .join('');
    }
    renderAssignmentSummary();
  } catch {
    assignmentContractors = [];
  }
}

function renderAssignmentSummary() {
  const wrap = document.getElementById('assignmentSummary');
  if (!wrap) return;
  const active = assignmentContractors.filter(c => c.status === 'active').length;
  const average = assignmentContractors.length
    ? Math.round(assignmentContractors.reduce((sum, c) => sum + Number(c.performance_score || 0), 0) / assignmentContractors.length)
    : 0;

  wrap.innerHTML = `
    <article class="admin-summary-card"><span>Accredited Contractors</span><strong>${assignmentContractors.length}</strong></article>
    <article class="admin-summary-card"><span>Active Contractors</span><strong>${active}</strong></article>
    <article class="admin-summary-card"><span>Field Engineers</span><strong>${assignmentEngineers.length}</strong></article>
    <article class="admin-summary-card"><span>Average Score</span><strong>${average}</strong></article>
  `;
}

async function loadAssignmentEngineers() {
  try {
    const d = await get(API.users, { role: 'engineer' });
    assignmentEngineers = d.data || [];
    renderAssignmentSummary();
  } catch {
    assignmentEngineers = [];
  }
}

async function fetchAssignmentProjects() {
  const wrap = document.getElementById('assignmentTable');
  if (!wrap) return;
  setLoading(wrap, true);

  try {
    const d = await get(API.projects, {
      page: assignmentState.page,
      search: assignmentState.search,
      contractor_id: assignmentState.contractor_id,
      status_in: 'awarded,assigned',
    });
    renderAssignmentTable(d.data);
    renderPager('assignmentPager', d.page, d.last_page, p => { assignmentState.page = p; fetchAssignmentProjects(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load project assignments.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderAssignmentTable(rows) {
  const wrap = document.getElementById('assignmentTable');
  if (!wrap) return;
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No BAC-awarded projects found for assignment.</p>';
    return;
  }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th>Code</th><th>Project</th><th>Current Contractor</th><th>Status</th><th>Assign Contractor</th><th>Field Engineer</th><th>Action</th></tr>
      </thead>
      <tbody>
        ${rows.map(p => `
          <tr>
            <td><span class="proj-id">${p.project_code}</span></td>
            <td><strong>${escapeHtml(p.name)}</strong><br><small style="color:#94a3b8">${escapeHtml(p.location || '-')}</small></td>
            <td>${p.contractor_name || '<span style="color:#ef4444;">Unassigned</span>'}</td>
            <td>${statusBadge(p.status)}</td>
            <td>
              <select class="form-input assignment-select" id="assignContractor-${p.id}">
                <option value="">Unassigned</option>
                ${assignmentContractors.map(c => `
                  <option value="${c.id}" ${String(p.contractor_id || '') === String(c.id) ? 'selected' : ''}>
                    ${escapeHtml(c.name)} (${c.performance_score})
                  </option>
                `).join('')}
              </select>
            </td>
            <td>
              <select class="form-input assignment-select" id="assignEngineer-${p.id}">
                <option value="">Select engineer</option>
                ${assignmentEngineers.map(engineer => `
                  <option value="${engineer.id}" ${String(p.assigned_engineer_id || '') === String(engineer.id) ? 'selected' : ''}>
                    ${engineer.full_name}
                  </option>
                `).join('')}
              </select>
              ${p.assigned_engineer_name ? `<small style="display:block;color:#64748b;margin-top:4px;">Current: ${p.assigned_engineer_name}</small>` : ''}
            </td>
            <td><button class="btn-primary btn-compact" onclick="saveContractorAssignment(${p.id})">Save</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function saveContractorAssignment(projectId) {
  const select = document.getElementById(`assignContractor-${projectId}`);
  const engineerSelect = document.getElementById(`assignEngineer-${projectId}`);
  if (!select) return;
  if (select.value && !engineerSelect?.value) {
    toast('Select a field engineer before marking the project assigned.', 'error');
    return;
  }

  try {
    const body = {
      contractor_id: select.value,
      ...(engineerSelect?.value ? { engineer_id: engineerSelect.value } : {}),
      ...(select.value ? { status: 'assigned' } : {}),
    };
    const res = await put(API.projects, projectId, body);
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Contractor assignment saved.');
    fetchAssignmentProjects();
  } catch {
    toast('Failed to save assignment', 'error');
  }
}

/* ============================================================
   MILESTONE OVERVIEW
   ============================================================ */
let milestoneState = { page: 1, search: '', status: '' };

async function loadMilestoneOverviewPage() {
  const container = document.getElementById('page-milestone-overview');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Milestone Overview</h2>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search projects..."
        oninput="milestoneState.search=this.value;milestoneState.page=1;fetchMilestoneProjects()" />
      <select class="filter-select" onchange="milestoneState.status=this.value;milestoneState.page=1;fetchMilestoneProjects()">
        <option value="">All Statuses</option>
        ${PROJECT_STATUSES.map(status => `<option value="${status}">${PROJECT_STATUS_LABELS[status]}</option>`).join('')}
      </select>
    </div>
    <div id="milestoneGrid" class="milestone-grid"></div>
    <div id="milestonePager" class="pager"></div>
  `;

  fetchMilestoneProjects();
}

async function fetchMilestoneProjects() {
  const wrap = document.getElementById('milestoneGrid');
  if (!wrap) return;
  setLoading(wrap, true);

  try {
    const d = await get(API.projects, {
      page: milestoneState.page,
      search: milestoneState.search,
      status: milestoneState.status,
    });
    const detailRows = await Promise.all((d.data || []).map(p => get(API.projects, { id: p.id }).catch(() => p)));
    renderMilestoneCards(detailRows);
    renderPager('milestonePager', d.page, d.last_page, p => { milestoneState.page = p; fetchMilestoneProjects(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load milestone overview.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderMilestoneCards(projects) {
  const wrap = document.getElementById('milestoneGrid');
  if (!wrap) return;
  if (!projects.length) {
    wrap.innerHTML = '<p class="empty-state">No milestone records found.</p>';
    return;
  }

  wrap.innerHTML = projects.map(p => {
    const milestones = p.milestones || [];
    const completed = milestones.filter(m => Number(m.completed) === 1).length;
    const next = milestones
      .filter(m => Number(m.completed) !== 1)
      .sort((a, b) => String(a.due_date || '').localeCompare(String(b.due_date || '')))[0];
    const pct = milestones.length ? Math.round((completed / milestones.length) * 100) : Number(p.progress || 0);

    return `
      <article class="milestone-card">
        <div class="milestone-card-head">
          <div>
            <span class="proj-id">${escapeHtml(p.project_code)}</span>
            <h3>${escapeHtml(p.name)}</h3>
          </div>
          ${statusBadge(p.status)}
        </div>
        <div class="milestone-progress">
          <div style="width:${pct}%;background:${progressColor(pct)};"></div>
        </div>
        <div class="milestone-meta">
          <span>${completed}/${milestones.length || 0} milestones done</span>
          <strong>${pct}%</strong>
        </div>
        <div class="milestone-next">
          <span>Next milestone</span>
          <strong>${next ? next.title : 'No pending milestone'}</strong>
          <small>${next ? formatDate(next.due_date) : 'Ready for review'}</small>
        </div>
        <button class="btn-secondary" onclick="openProjectModal(${p.id})">View Details</button>
      </article>
    `;
  }).join('');
}

/* ============================================================
   GIS MAP
   ============================================================ */
const GIS_STATUS_COLORS = {
  completed: '#22c55e',
  delayed: '#ef4444',
  cancelled: '#94a3b8',
  turnover: '#16a34a',
};
const GIS_DEFAULT_COLOR = '#3b82f6'; // everything still in progress (active, assigned, bidding, etc.)
let gisMapInstance = null;
let gisMarkers = [];
let gisFilterState = { status: '', contractor_id: '', search: '', min_budget: '', max_budget: '' };

async function loadGisMapPage() {
  const container = document.getElementById('page-gis-map');
  if (!container) return;

  let contractors = [];
  try {
    const c = await get(API.contractors, { _limit: 100 });
    contractors = c.data || [];
  } catch { /* filter dropdown just stays empty */ }

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">GIS Map</h2>
    </div>
    <div class="filter-bar" style="flex-wrap:wrap;">
      <input class="filter-input" id="gisSearchInput" placeholder="Search location or project name...">
      <select class="filter-select" id="gisStatusInput">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="delayed">Delayed</option>
        <option value="completed">Completed</option>
        <option value="turnover">Turned Over</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select class="filter-select" id="gisContractorInput">
        <option value="">All Contractors</option>
        ${contractors.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('')}
      </select>
      <input class="filter-input" id="gisMinBudget" type="number" placeholder="Min budget (₱)" style="max-width:150px;">
      <input class="filter-input" id="gisMaxBudget" type="number" placeholder="Max budget (₱)" style="max-width:150px;">
      <button class="btn-secondary btn-compact" id="gisApplyFilters" type="button">Apply</button>
    </div>
    <div class="gis-legend" style="display:flex;gap:16px;margin:12px 0;font-size:.78rem;color:var(--text-muted);">
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${GIS_DEFAULT_COLOR};margin-right:5px;"></span>Active / In Progress</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${GIS_STATUS_COLORS.delayed};margin-right:5px;"></span>Delayed</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${GIS_STATUS_COLORS.completed};margin-right:5px;"></span>Completed / Turned Over</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${GIS_STATUS_COLORS.cancelled};margin-right:5px;"></span>Cancelled</span>
    </div>
    <div id="gisMapContainer" style="height:520px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);"></div>
    <p id="gisEmptyState" class="empty-state" style="display:none;">No projects with map coordinates match this filter. Add latitude/longitude when registering or editing a project to place it here.</p>
  `;

  document.getElementById('gisApplyFilters').addEventListener('click', () => {
    gisFilterState = {
      status: document.getElementById('gisStatusInput').value,
      contractor_id: document.getElementById('gisContractorInput').value,
      search: document.getElementById('gisSearchInput').value,
      min_budget: document.getElementById('gisMinBudget').value,
      max_budget: document.getElementById('gisMaxBudget').value,
    };
    fetchGisProjects();
  });

  await fetchGisProjects();
}

async function fetchGisProjects() {
  const emptyState = document.getElementById('gisEmptyState');
  try {
    const result = await get(API.projects, { ...gisFilterState, has_coordinates: 1, _limit: 100 });
    renderGisMap(result.data || []);
    emptyState.style.display = (result.data || []).length ? 'none' : 'block';
  } catch {
    toast('Failed to load projects for the map', 'error');
  }
}

function renderGisMap(projects) {
  if (!gisMapInstance) {
    gisMapInstance = L.map('gisMapContainer').setView([14.6760, 121.0437], 12); // Quezon City
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(gisMapInstance);
  }

  gisMarkers.forEach(m => gisMapInstance.removeLayer(m));
  gisMarkers = [];

  projects.forEach(p => {
    if (p.latitude === null || p.longitude === null || p.latitude === undefined || p.longitude === undefined) return;
    const color = GIS_STATUS_COLORS[p.status] || GIS_DEFAULT_COLOR;
    const marker = L.circleMarker([Number(p.latitude), Number(p.longitude)], {
      radius: 9,
      color: '#fff',
      weight: 2,
      fillColor: color,
      fillOpacity: 0.9,
    }).addTo(gisMapInstance);

    marker.bindPopup(`
      <strong>${escapeHtml(p.name)}</strong><br>
      <small>${escapeHtml(p.project_code)} — ${escapeHtml(p.location || '')}</small><br>
      <small>${formatMoney(p.budget)} · ${formatStatus(p.status)}</small><br>
      <button style="margin-top:6px;padding:4px 10px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;" onclick="openProjectModal(${p.id})">View Details</button>
    `);
    gisMarkers.push(marker);
  });

  if (gisMarkers.length) {
    const group = L.featureGroup(gisMarkers);
    gisMapInstance.fitBounds(group.getBounds().pad(0.2));
  }

  // Leaflet paints into a container that was just made visible; without this
  // its internal size calculation runs while the pane is still display:none
  // and the tiles render into a collapsed 0x0 box.
  setTimeout(() => gisMapInstance.invalidateSize(), 100);
}

/* ============================================================
   REPORTS
   ============================================================ */
async function loadReportsPage() {
  const container = document.getElementById('page-reports');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Reports</h2>
      <button class="btn-secondary" onclick="window.print()">Print</button>
    </div>
    <div id="reportsContent" class="reports-layout"></div>
  `;

  const wrap = document.getElementById('reportsContent');
  setLoading(wrap, true);

  try {
    const [dashboard, contractors, openFeedback, expenseSummary] = await Promise.all([
      get(API.dashboard),
      get(API.contractors, { page: 1, _limit: 100 }),
      get(API.feedback, { status: 'open' }),
      get(API.expenses, { summary: 1 }),
    ]);
    renderReports(dashboard, contractors.data || [], openFeedback, expenseSummary.data || []);
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to build reports.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderReports(dashboard, contractors, openFeedback, expenseSummary) {
  const wrap = document.getElementById('reportsContent');
  if (!wrap) return;
  const budgetPct = Number(dashboard.budget_pct || 0);
  const delayed = dashboard.top_delayed || [];
  const topContractors = contractors.slice(0, 5);
  const highestSpend = expenseSummary.slice(0, 5);

  wrap.innerHTML = `
    <section class="admin-summary-grid">
      <article class="admin-summary-card"><span>Active Projects</span><strong>${dashboard.active_projects}</strong></article>
      <article class="admin-summary-card"><span>Delayed Projects</span><strong>${dashboard.delayed_projects}</strong></article>
      <article class="admin-summary-card"><span>Budget Used</span><strong>${budgetPct}%</strong></article>
      <article class="admin-summary-card"><span>Open Feedback</span><strong>${openFeedback.total || 0}</strong></article>
    </section>

    <section class="report-columns">
      <article class="report-panel">
        <h3>Delayed Projects</h3>
        ${delayed.length ? delayed.map(p => `
          <div class="report-row">
            <span>${escapeHtml(p.name)}</span>
            <strong>${p.days_overdue || 0} days</strong>
          </div>
        `).join('') : '<p class="empty-state">No delayed projects.</p>'}
      </article>
      <article class="report-panel">
        <h3>Top Contractors</h3>
        ${topContractors.length ? topContractors.map(c => `
          <div class="report-row">
            <span>${escapeHtml(c.name)}</span>
            <strong>${c.performance_score}</strong>
          </div>
        `).join('') : '<p class="empty-state">No contractor data.</p>'}
      </article>
      <article class="report-panel">
        <h3>Highest Budget Usage</h3>
        ${highestSpend.length ? highestSpend.map(r => {
          const pct = r.budget > 0 ? Math.round((r.total_spent / r.budget) * 100) : 0;
          return `
            <div class="report-row">
              <span>${r.project_name}</span>
              <strong>${pct}%</strong>
            </div>
          `;
        }).join('') : '<p class="empty-state">No budget data.</p>'}
      </article>
    </section>
  `;
}

/* ============================================================
   AI RISK INSIGHTS
   ============================================================ */
async function loadAIRiskInsightsPage() {
  const container = document.getElementById('page-ai-risk-insights');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">AI Risk Insights</h2>
    </div>
    <div id="riskInsightsContent" class="reports-layout"></div>
  `;

  const wrap = document.getElementById('riskInsightsContent');
  setLoading(wrap, true);

  try {
    const dashboard = await get(API.dashboard);
    renderAIRiskInsights(dashboard);
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load risk insights.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderAIRiskInsights(dashboard) {
  const wrap = document.getElementById('riskInsightsContent');
  if (!wrap) return;
  const ai = dashboard.ai_insights || {};
  const anomalies = dashboard.budget_anomalies || [];
  const delayed = dashboard.top_delayed || [];
  const riskClass = ai.delay_risk === 'High' ? 'risk-high' : ai.delay_risk === 'Medium' ? 'risk-medium' : 'risk-low';

  wrap.innerHTML = `
    <section class="risk-grid">
      <article class="risk-card ${riskClass}">
        <span>Delay Risk</span>
        <strong>${ai.delay_risk || 'Low'}</strong>
        <p>${dashboard.delayed_projects || 0} delayed project(s) currently need management attention.</p>
      </article>
      <article class="risk-card ${dashboard.high_risk_alerts > 0 ? 'risk-high' : 'risk-low'}">
        <span>Budget Risk</span>
        <strong>${dashboard.high_risk_alerts || 0} alerts</strong>
        <p>${ai.budget_alert ? 'Flagged expenses are present in the budget ledger.' : 'No flagged expenses in the current ledger.'}</p>
      </article>
      <article class="risk-card risk-low">
        <span>Best Contractor Signal</span>
        <strong>${ai.top_contractor ? ai.top_contractor.name : 'No data'}</strong>
        <p>${ai.top_contractor ? `Performance score ${ai.top_contractor.performance_score}.` : 'Contractor performance data is not available yet.'}</p>
      </article>
    </section>

    <section class="report-columns">
      <article class="report-panel">
        <h3>Priority Delays</h3>
        ${delayed.length ? delayed.map(p => `
          <div class="report-row">
            <span>${escapeHtml(p.name)}</span>
            <button class="btn-secondary btn-compact" onclick="openProjectModal(${p.id})">Review</button>
          </div>
        `).join('') : '<p class="empty-state">No delayed projects.</p>'}
      </article>
      <article class="report-panel">
        <h3>Budget Anomalies</h3>
        ${anomalies.length ? anomalies.map(a => `
          <div class="report-row">
            <span>${a.project_name}</span>
            <strong>${formatMoney(a.amount)}</strong>
          </div>
        `).join('') : '<p class="empty-state">No flagged budget items.</p>'}
      </article>
    </section>
  `;
}

/* ============================================================
   BUDGET PAGE
   ============================================================ */
let budgetState = { page: 1, search: '', flagged: false, project_id: '' };

async function loadBudgetPage(containerId = 'page-budget-monitoring', title = 'Budget Monitoring') {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
      <button class="btn-primary" onclick="showExpenseForm()">+ Log Expense</button>
    </div>
    <div id="budgetSummary" class="budget-summary-grid"></div>
    <div class="filter-bar" style="margin-top:16px;">
      <input class="filter-input" placeholder="Search expenses…" oninput="budgetState.search=this.value;budgetState.page=1;fetchExpenses()" />
      <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;cursor:pointer;">
        <input type="checkbox" onchange="budgetState.flagged=this.checked;budgetState.page=1;fetchExpenses()" />
        Anomalies only
      </label>
    </div>
    <div id="expensesTable" class="table-card" style="margin-top:12px;"></div>
    <div id="expensesPager" class="pager"></div>
  `;

  fetchBudgetSummary();
  fetchExpenses();
}

async function fetchBudgetSummary() {
  try {
    const d = await get(API.expenses, { summary: 1 });
    const wrap = document.getElementById('budgetSummary');
    if (!wrap) return;
    wrap.innerHTML = d.data.slice(0, 4).map(r => {
      const pct = r.budget > 0 ? Math.min(100, Math.round((r.total_spent / r.budget) * 100)) : 0;
      const color = pct >= 90 ? '#ef4444' : pct >= 70 ? '#f97316' : '#22c55e';
      return `
        <div class="budget-summary-card">
          <p class="budget-proj-name">${r.project_name}</p>
          <div style="display:flex;justify-content:space-between;font-size:.75rem;color:#64748b;margin:4px 0;">
            <span>₱${Number(r.total_spent).toLocaleString()} spent</span>
            <span>₱${Number(r.budget).toLocaleString()} budget</span>
          </div>
          <div style="background:#f1f5f9;border-radius:20px;height:8px;overflow:hidden;">
            <div style="width:${pct}%;background:${color};height:100%;border-radius:20px;"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.72rem;margin-top:4px;">
            <span style="color:${color};font-weight:700;">${pct}%</span>
            ${r.flag_count > 0 ? `<span style="color:#ef4444;">⚠ ${r.flag_count} anomaly</span>` : ''}
          </div>
        </div>`;
    }).join('');
  } catch {}
}

async function fetchExpenses() {
  const wrap = document.getElementById('expensesTable');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const params = {
      page: budgetState.page,
      search: budgetState.search,
      ...(budgetState.flagged ? { flagged: 1 } : {}),
    };
    const d = await get(API.expenses, params);
    renderExpensesTable(d.data);
    renderPager('expensesPager', d.page, d.last_page, p => { budgetState.page = p; fetchExpenses(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load expenses.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderExpensesTable(rows) {
  const wrap = document.getElementById('expensesTable');
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No expenses found.</p>'; return; }
  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th>Project</th><th>Category</th><th>Description</th><th>Amount</th><th>Date</th><th>Flag</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(e => `
          <tr ${e.flagged == 1 ? 'style="background:#fff5f5"' : ''}>
            <td>${e.project_name}</td>
            <td>${e.category}</td>
            <td>${e.description || '—'}</td>
            <td style="font-weight:600;">₱${Number(e.amount).toLocaleString()}</td>
            <td>${e.expense_date}</td>
            <td>${e.flagged == 1 ? '<span style="color:#ef4444;">⚠ Flagged</span>' : '<span style="color:#22c55e;">✓ OK</span>'}</td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" onclick="toggleFlag(${e.id}, ${e.flagged})" title="${e.flagged?'Unflag':'Flag'}">${e.flagged?'🚩':'⬜'}</button>
                <button class="btn-icon btn-danger" onclick="deleteExpense(${e.id})" title="Delete">🗑</button>
              </div>
            </td>
          </tr>`).join('')}
      </tbody>
    </table>`;
}

async function showExpenseForm() {
  let projects = [];
  try { const d = await get(API.projects, {}); projects = d.data || []; } catch {}

  openModal('Log New Expense', `
    <form id="expenseForm" onsubmit="submitExpenseForm(event)">
      <div class="form-grid">
        <div class="form-group">
          <label>Project *</label>
          <select name="project_id" class="form-input" required>
            <option value="">— Select —</option>
            ${projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-input">
            ${['Materials','Labor','Equipment','Consultancy','Misc','General'].map(c =>
              `<option value="${c}">${c}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Amount (₱) *</label>
          <input name="amount" type="number" step="0.01" class="form-input" required />
        </div>
        <div class="form-group">
          <label>Date *</label>
          <input name="expense_date" type="date" class="form-input" required value="${new Date().toISOString().slice(0,10)}" />
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-input" rows="2"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Log Expense</button>
      </div>
    </form>
  `);
}

async function submitExpenseForm(e) {
  e.preventDefault();
  const body = Object.fromEntries(new FormData(e.target).entries());
  try {
    const res = await post(API.expenses, body);
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Expense logged!');
    closeModal();
    fetchExpenses();
    fetchBudgetSummary();
  } catch { toast('Failed to log expense', 'error'); }
}

async function toggleFlag(id, current) {
  try {
    await put(API.expenses, id, { flagged: current ? 0 : 1 });
    fetchExpenses();
  } catch { toast('Update failed', 'error'); }
}

async function deleteExpense(id) {
  if (!confirm('Delete this expense?')) return;
  try {
    await del(API.expenses, id);
    toast('Expense deleted');
    fetchExpenses();
    fetchBudgetSummary();
  } catch { toast('Delete failed', 'error'); }
}

/* ============================================================
   STAFF REQUESTS (Engineer/BAC accounts) — admin submits a request,
   only Super Admin can approve it and actually create the login
   (maker-checker; admin has no direct account-creation path).
   ============================================================ */
async function loadStaffRequestsPage() {
  const container = document.getElementById('page-staff-requests');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Staff Requests</h2>
    </div>
    <p style="color:#64748b;margin-bottom:16px;max-width:640px;">
      Request a new Engineer or BAC account. A Super Admin must review and approve the
      request before the login is created — you cannot create staff accounts directly.
    </p>
    <div class="table-card" style="padding:20px;max-width:520px;">
      <form id="staffRequestForm">
        <div class="form-group">
          <label>Role *</label>
          <select name="requested_role" class="form-input" required>
            <option value="engineer">Engineer</option>
            <option value="bac">BAC</option>
          </select>
        </div>
        <div class="form-group">
          <label>Full Name *</label>
          <input name="full_name" class="form-input" required />
        </div>
        <div class="form-group">
          <label>Username *</label>
          <input name="username" class="form-input" required />
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input name="email" type="email" class="form-input" required />
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  `;

  document.getElementById('staffRequestForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.target).entries());
    try {
      const res = await postAction(API.staffAccounts, 'request_staff_account', body);
      if (res.error) { toast(res.error, 'error'); return; }
      toast('Request submitted — awaiting Super Admin approval.');
      e.target.reset();
    } catch { toast('Something went wrong', 'error'); }
  });
}

/* ============================================================
   CONTRACTOR FORM (used by the "+ Add Contractor" button on the
   Contractor Assignment page — there is no standalone contractors
   list page; that was removed as dead/unreachable code)
   ============================================================ */

async function showContractorForm(id = null) {
  let c = null;
  if (id) try { c = await get(API.contractors, { id }); } catch {}

  openModal(id ? `Edit Contractor` : 'New Contractor', `
    <form id="contractorForm" onsubmit="submitContractorForm(event, ${id})">
      <div class="form-grid">
        <div class="form-group">
          <label>Company Name *</label>
          <input name="name" class="form-input" required value="${c?.name||''}" />
        </div>
        <div class="form-group">
          <label>Contact Person</label>
          <input name="contact_person" class="form-input" value="${c?.contact_person||''}" />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input name="email" type="email" class="form-input" value="${c?.email||''}" />
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input name="phone" class="form-input" value="${c?.phone||''}" />
        </div>
        <div class="form-group">
          <label>Performance Score</label>
          <input class="form-input" disabled value="${id ? `${c?.performance_score||0} / 100 (computed from project history)` : 'Computed after first project assignment'}" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-input">
            ${['active','inactive','blacklisted'].map(s =>
              `<option value="${s}" ${c?.status===s?'selected':''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address" class="form-input" rows="2">${c?.address||''}</textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">${id ? 'Update' : 'Add'} Contractor</button>
      </div>
    </form>
  `);
}

async function submitContractorForm(e, id) {
  e.preventDefault();
  const body = Object.fromEntries(new FormData(e.target).entries());
  try {
    const res = id ? await put(API.contractors, id, body) : await post(API.contractors, body);
    if (res.error) { toast(res.error, 'error'); return; }
    toast(id ? 'Contractor updated!' : 'Contractor added!');
    closeModal();
    if (document.getElementById('assignmentTable')) {
      await loadAssignmentContractors();
      fetchAssignmentProjects();
    }
  } catch { toast('Something went wrong', 'error'); }
}

/* ============================================================
   FEEDBACK PAGE
   ============================================================ */
let feedbackState = { page: 1, search: '', status: '', priority: '' };

async function loadFeedbackPage(containerId = 'page-citizen-feedback', title = 'Citizen Feedback Review', allowNewEntry = false) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
      ${allowNewEntry ? '<button class="btn-primary" onclick="showFeedbackForm()">+ New Entry</button>' : ''}
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search feedback…"
        oninput="feedbackState.search=this.value;feedbackState.page=1;fetchFeedback()" />
      <select class="filter-select" onchange="feedbackState.priority=this.value;feedbackState.page=1;fetchFeedback()">
        <option value="">All Priorities</option>
        <option value="urgent">Urgent</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <select class="filter-select" onchange="feedbackState.status=this.value;feedbackState.page=1;fetchFeedback()">
        <option value="">All Statuses</option>
        <option value="open">Open</option>
        <option value="in_progress">In Progress</option>
        <option value="resolved">Resolved</option>
        <option value="closed">Closed</option>
      </select>
    </div>
    <div id="feedbackTable" class="table-card" style="margin-top:12px;"></div>
    <div id="feedbackPager" class="pager"></div>
  `;
  fetchFeedback();
}

async function fetchFeedback() {
  const wrap = document.getElementById('feedbackTable');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.feedback, feedbackState);
    renderFeedbackTable(d.data);
    renderPager('feedbackPager', d.page, d.last_page, p => { feedbackState.page = p; fetchFeedback(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load feedback.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderFeedbackTable(rows) {
  const wrap = document.getElementById('feedbackTable');
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No feedback entries.</p>'; return; }

  const pBadge = { urgent:'badge-urgent', high:'badge-overbudget', medium:'badge-spike', low:'badge-resolved' };
  const sBadge = { open:'badge-urgent', in_progress:'badge-highprio', resolved:'badge-resolved', closed:'badge-resolved' };

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th>Citizen</th><th>Type</th><th>Message</th><th>Category</th><th>Priority</th><th>Status</th><th>CIMMS</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(f => `
          <tr>
            <td>${f.citizen_name ? escapeHtml(f.citizen_name) : '<em style="color:#94a3b8">Anonymous</em>'}</td>
            <td>${f.concern_type === 'maintenance' ? 'Maintenance' : 'Project'}</td>
            <td style="max-width:200px;">${escapeHtml(f.message)}</td>
            <td>${escapeHtml(f.category)}</td>
            <td><span class="badge ${pBadge[f.priority]||'badge-resolved'}">${f.priority}</span></td>
            <td><span class="badge ${sBadge[f.status]||'badge-resolved'}">${f.status}</span></td>
            <td style="font-size:.75rem;">${
              f.concern_type !== 'maintenance'
                ? '—'
                : (f.cimm_reference
                    ? escapeHtml(f.cimm_reference)
                    : escapeHtml(f.cimm_sync_status || '—'))
            }</td>
            <td style="font-size:.75rem;color:#94a3b8;">${f.created_at?.slice(0,10)}</td>
            <td>
              <div class="action-btns">
                <button class="btn-secondary btn-compact" onclick="updateFeedbackStatus(${f.id},'in_progress')" ${f.status==='in_progress'?'disabled':''}>Review</button>
                <button class="btn-primary btn-compact" onclick="updateFeedbackStatus(${f.id},'resolved')" ${f.status==='resolved'?'disabled':''}>Resolve</button>
                <button class="btn-secondary btn-compact" onclick="updateFeedbackStatus(${f.id},'closed')" ${f.status==='closed'?'disabled':''}>Close</button>
              </div>
            </td>
          </tr>`).join('')}
      </tbody>
    </table>`;
}

async function showFeedbackForm() {
  let projects = [];
  try { const d = await get(API.projects, {}); projects = d.data || []; } catch {}

  openModal('New Feedback / Complaint', `
    <form id="feedbackForm" onsubmit="submitFeedbackForm(event)">
      <div class="form-grid">
        <div class="form-group">
          <label>Citizen Name</label>
          <input name="citizen_name" class="form-input" placeholder="Optional" />
        </div>
        <div class="form-group">
          <label>Related Project</label>
          <select name="project_id" class="form-input">
            <option value="">— None —</option>
            ${projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-input">
            <option value="complaint">Complaint</option>
            <option value="suggestion">Suggestion</option>
            <option value="inquiry">Inquiry</option>
          </select>
        </div>
        <div class="form-group">
          <label>Priority</label>
          <select name="priority" class="form-input">
            <option value="medium">Medium</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Message *</label>
        <textarea name="message" class="form-input" rows="3" required></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Submit</button>
      </div>
    </form>
  `);
}

async function submitFeedbackForm(e) {
  e.preventDefault();
  const body = Object.fromEntries(new FormData(e.target).entries());
  try {
    const res = await post(API.feedback, body);
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Feedback submitted!');
    closeModal();
    fetchFeedback();
  } catch { toast('Failed to submit', 'error'); }
}

async function updateFeedbackStatus(id, status) {
  try {
    await put(API.feedback, id, { status });
    toast('Status updated');
    fetchFeedback();
  } catch { toast('Update failed', 'error'); }
}

async function deleteFeedback(id) {
  if (!confirm('Delete this entry?')) return;
  try {
    await del(API.feedback, id);
    toast('Entry deleted');
    fetchFeedback();
  } catch { toast('Delete failed', 'error'); }
}

/* ============================================================
   SHARED HELPERS
   ============================================================ */

// ── Modal ──
const modalOverlay = document.getElementById('modalOverlay');
const modalTitle   = document.getElementById('modalTitle');
const modalBody    = document.getElementById('modalBody');

function openModal(title, html) {
  modalTitle.textContent = title;
  modalBody.innerHTML = html;
  modalOverlay.classList.add('open');
}

function closeModal() {
  modalOverlay.classList.remove('open');
}

document.getElementById('modalClose')?.addEventListener('click', closeModal);
modalOverlay?.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── Pager ──
function renderPager(containerId, page, lastPage, onPage) {
  const el = document.getElementById(containerId);
  if (!el || lastPage <= 1) { if (el) el.innerHTML = ''; return; }
  el.innerHTML = `
    <button class="pager-btn" ${page<=1?'disabled':''} onclick="(${onPage.toString()})(${page-1})">‹ Prev</button>
    <span class="pager-info">Page ${page} of ${lastPage}</span>
    <button class="pager-btn" ${page>=lastPage?'disabled':''} onclick="(${onPage.toString()})(${page+1})">Next ›</button>
  `;
}

// ── Sidebar toggle ──
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sidebar')?.classList.toggle('open');
});
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  const toggle  = document.getElementById('sidebarToggle');
  if (window.innerWidth <= 768 && sidebar?.classList.contains('open')
      && !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
    sidebar.classList.remove('open');
  }
});

// ── Nav ──
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', e => {
    e.preventDefault();
    navigate(item.dataset.page || 'dashboard');
  });
});

// ── Notification bell/panel toggle + polling is handled by assets/js/notifications.js. ──

// ── Search ──
document.getElementById('searchInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && e.target.value.trim()) {
    toast(`Searching for: "${e.target.value.trim()}"`, 'info');
  }
});

/* ============================================================
   INIT — build page sections and load dashboard
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  // User menu toggle
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.getElementById('userMenu');
  
  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      userMenu.classList.toggle('open');
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target) && e.target !== userMenuBtn) {
        userMenu.classList.remove('open');
      }
    });
    
    // Prevent menu from closing when clicking inside
    userMenu.addEventListener('click', (e) => {
      e.stopPropagation();
    });
  }

  const contentEl = document.querySelector('.content');
  if (!contentEl) return;

  // Wrap existing dashboard HTML in page-dashboard div,
  // and create hidden containers for other pages
  const dashHTML = contentEl.innerHTML;
  contentEl.innerHTML = `
    <div id="page-dashboard" class="page-section">${dashHTML}</div>
    <div id="page-project-registration" class="page-section" style="display:none;"></div>
    <div id="page-project-approval" class="page-section" style="display:none;"></div>
    <div id="page-contractor-assignment" class="page-section" style="display:none;"></div>
    <div id="page-workflow-management" class="page-section" style="display:none;"></div>
    <div id="page-budget-monitoring" class="page-section" style="display:none;"></div>
    <div id="page-milestone-overview" class="page-section" style="display:none;"></div>
    <div id="page-gis-map" class="page-section" style="display:none;"></div>
    <div id="page-reports" class="page-section" style="display:none;"></div>
    <div id="page-ai-risk-insights" class="page-section" style="display:none;"></div>
    <div id="page-citizen-feedback" class="page-section" style="display:none;"></div>
    <div id="page-staff-requests" class="page-section" style="display:none;"></div>
  `;

  loadDashboard();
});
// Profile settings modal (already in topbar.php but with working implementation)
async function showProfileSettings() {
  try {
    const user = await get(API.user);
    openModal('Profile Settings', `
      <form id="profileForm" onsubmit="submitProfileForm(event)">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name *</label>
            <input name="full_name" class="form-input" required value="${user.data.full_name}" />
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input name="email" type="email" class="form-input" required value="${user.data.email}" />
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-input" value="${user.data.username}" disabled />
            <small style="color: #64748b; font-size: 0.8rem;">Username cannot be changed</small>
          </div>
          <div class="form-group">
            <label>Role</label>
            <input class="form-input" value="${formatRole(user.data.role)}" disabled />
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-primary">Update Profile</button>
        </div>
      </form>
    `);
  } catch (e) {
    toast('Failed to load profile', 'error');
  }
}
 
// Change password modal
function showChangePassword() {
  openModal('Change Password', `
    <form id="passwordForm" onsubmit="submitPasswordForm(event)">
      <div class="form-group">
        <label>Current Password *</label>
        <input name="current_password" type="password" class="form-input" required autocomplete="current-password" />
      </div>
      <div class="form-group">
        <label>New Password *</label>
        <input name="new_password" type="password" class="form-input" required minlength="6" autocomplete="new-password" />
        <small style="color: #64748b; font-size: 0.8rem;">Minimum 6 characters</small>
      </div>
      <div class="form-group">
        <label>Confirm New Password *</label>
        <input name="confirm_password" type="password" class="form-input" required autocomplete="new-password" />
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Change Password</button>
      </div>
    </form>
  `);
}
 
// Submit profile form
async function submitProfileForm(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  const body = {
    full_name: formData.get('full_name'),
    email: formData.get('email')
  };
  
  try {
    const res = await fetch(API.user, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...CSRF_HEADERS },
      body: new URLSearchParams(body)
    });
    const data = await res.json();
    
    if (data.error) {
      toast(data.error, 'error');
    } else {
      toast('Profile updated successfully!');
      closeModal();
      // Update the displayed name in topbar
      const userName = document.querySelector('.user-name');
      const menuName = document.querySelector('.user-menu-name');
      const menuEmail = document.querySelector('.user-menu-email');
      if (userName) userName.textContent = body.full_name;
      if (menuName) menuName.textContent = body.full_name;
      if (menuEmail) menuEmail.textContent = body.email;
    }
  } catch (e) {
    toast('Failed to update profile', 'error');
  }
}
 
// Submit password form
async function submitPasswordForm(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const current = formData.get('current_password');
  const newPass = formData.get('new_password');
  const confirm = formData.get('confirm_password');
  
  if (newPass !== confirm) {
    toast('New passwords do not match', 'error');
    return;
  }
  
  if (newPass.length < 6) {
    toast('Password must be at least 6 characters', 'error');
    return;
  }
  
  const body = {
    current_password: current,
    new_password: newPass
  };
  
  try {
    const res = await fetch(API.user, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...CSRF_HEADERS },
      body: new URLSearchParams(body)
    });
    const data = await res.json();
    
    if (data.error) {
      toast(data.error, 'error');
    } else {
      toast('Password changed successfully!');
      closeModal();
    }
  } catch (e) {
    toast('Failed to change password', 'error');
  }
}
 
