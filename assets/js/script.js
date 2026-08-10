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
  publicFacilities: window.BASE_PATH + 'api/public-facilities.php',
  calendar:    window.BASE_PATH + 'api/calendar.php',
  contractorPerformance: window.BASE_PATH + 'api/contractor-performance.php',
  engineerPerformance: window.BASE_PATH + 'api/engineer-performance.php',
  aiAssistant: window.BASE_PATH + 'api/ai-assistant.php',
  announcements: window.BASE_PATH + 'api/announcements.php',
  projectGallery: window.BASE_PATH + 'api/project-gallery.php',
  projectRatings: window.BASE_PATH + 'api/project-ratings.php',
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

// Must match PROJECT_CATEGORIES/PROJECT_FUNDING_SOURCES in api/projects.php.
const PROJECT_CATEGORIES = ['Roads and Bridges', 'Drainage and Flood Control', 'Water Supply', 'Public Buildings and Facilities', 'Street Lighting', 'Parks and Recreation', 'Other'];
const PROJECT_FUNDING_SOURCES = ['LGU General Fund', '20% Development Fund', 'National Government Fund', 'Grant/Donor Fund', 'Special Education Fund', 'Other'];

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

/* Project Health Score — same 0-100 score + healthy/warning/critical status
   everywhere (includes/ProjectHealth.php is the single source of truth;
   this just renders whatever score/status the API already computed). */
const HEALTH_COLORS = { healthy: '#22c55e', warning: '#f97316', critical: '#ef4444' };
const HEALTH_LABELS = { healthy: 'Healthy', warning: 'Warning', critical: 'Critical' };
function healthBadge(score, status) {
  const color = HEALTH_COLORS[status] || HEALTH_COLORS.critical;
  const label = HEALTH_LABELS[status] || formatStatus(status);
  return `<span class="badge" style="background:${color}22;color:${color};">${Number(score)}% · ${label}</span>`;
}

function formatDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

function formatDateTime(value) {
  return value ? String(value).slice(0, 16).replace('T', ' ') : '-';
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

  // Sidebar badge clearing/refresh is handled independently by
  // assets/js/sidebar-badges.js's own click listener on .nav-item.

  // Load page data
  const loaders = {
    dashboard: loadDashboard,
    'project-registration': () => loadProjectsPage('page-project-registration', 'Project Registration'),
    'project-approval': loadProjectApprovalPage,
    'contractor-assignment': loadContractorAssignmentPage,
    'workflow-management': loadWorkflowManagementPage,
    'budget-monitoring': () => loadBudgetPage('page-budget-monitoring', 'Budget Monitoring'),
    'milestone-overview': loadMilestoneOverviewPage,
    'calendar-schedule': loadCalendarSchedulePage,
    'gis-map': loadGisMapPage,
    reports: loadReportsPage,
    'ai-risk-insights': loadAiAssistantPage,
    announcements: loadAnnouncementsPage,
    'citizen-feedback': () => loadFeedbackPage('page-citizen-feedback', 'Citizen Feedback Review', false),
    'project-gallery': loadProjectGalleryPage,
    'citizen-ratings': loadCitizenRatingsPage,
    'staff-requests': loadStaffRequestsPage,
    'contractor-performance': loadContractorPerformancePage,
    'engineer-performance': loadEngineerPerformancePage,
    'completed-projects': () => loadStatusFilteredProjectsPage('page-completed-projects', 'Completed Projects', 'completed,turnover'),
    'cancelled-projects': () => loadStatusFilteredProjectsPage('page-cancelled-projects', 'Cancelled Projects', 'cancelled'),
    'public-facilities-integration': loadPublicFacilitiesPage,
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
      'kpi-total':               { target: d.total_projects,       budget: false },
      'kpi-active':              { target: d.active_projects,      budget: false },
      'kpi-delayed':             { target: d.delayed_projects,     budget: false },
      'kpi-completed':           { target: d.completed_projects,   budget: false },
      'kpi-pending-approvals':   { target: d.pending_approvals,    budget: false },
      'kpi-budget-risk':         { target: d.budget_risk_projects, budget: false },
      'kpi-active-engineers':    { target: d.active_engineers,     budget: false },
      'kpi-active-contractors':  { target: d.active_contractors,   budget: false },
      'kpi-citizen-complaints':  { target: d.citizen_complaints,   budget: false },
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

    // Projects by status bar
    renderStatusMixChart(d.status_mix);

    // Top delayed
    renderTopDelayed(d.top_delayed);

    // Anomalies
    renderAnomalies(d.budget_anomalies);

    // Feedback
    renderFeedbackWidget(d.recent_feedback);

    // AI insights
    renderAIInsights(d.ai_insights);

    renderWorkflowConnections(d.workflow_connections, d.recent_workflow);

    // Command Center widgets
    renderRecentActivities(d.recent_activities);
    renderLatestNotifications(d.latest_notifications);
    renderUpcomingDeadlines(d.upcoming_deadlines);
    renderTodaysInspections(d.todays_inspections);
    renderAIRecommendations(d.ai_recommendations);
    renderDashboardGisMap(d.gis_projects);

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
             // Theme-aware gridlines: the old #f1f5f9 glared on dark cards.
             grid: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)' }, border: { display: false } }
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

let statusMixChartInst = null;
function renderStatusMixChart(rows) {
  const ctx = document.getElementById('statusMixChart')?.getContext('2d');
  if (!ctx) return;
  if (statusMixChartInst) statusMixChartInst.destroy();

  const data = (rows || []).filter(r => Number(r.total) > 0);
  statusMixChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(r => PROJECT_STATUS_LABELS[r.status] || r.status.replaceAll('_', ' ')),
      datasets: [{
        data: data.map(r => Number(r.total)),
        backgroundColor: 'rgba(59,130,246,.75)',
        hoverBackgroundColor: '#3b82f6',
        borderRadius: 6,
        maxBarThickness: 42,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` ${c.raw} project${c.raw === 1 ? '' : 's'}` } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } }, border: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } },
             grid: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)' }, border: { display: false } },
      },
    },
  });
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
  `).join('') : '<p class="empty-state">No delayed projects</p>';
}

function renderAnomalies(anomalies) {
  const list = document.getElementById('budgetAnomaliesList');
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
  const list = document.getElementById('recentFeedbackList');
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

/* ============================================================
   COMMAND CENTER — Recent Activities / Notifications / Deadlines /
   Today's Inspections / AI Recommendations / health-colored GIS map.
   ============================================================ */
function renderRecentActivities(items) {
  const list = document.getElementById('recentActivitiesList');
  if (!list) return;
  list.innerHTML = (items || []).length ? items.map(a => `
    <div class="feedback-item">
      <div class="feedback-icon ${a.status === 'failed' ? 'feedback-red' : 'feedback-green'}"><span aria-hidden="true">${a.status === 'failed' ? '!' : '✓'}</span></div>
      <span class="feedback-text"><strong>${escapeHtml(a.actor_name)}</strong> — ${escapeHtml(formatStatus(a.action))}${a.module ? ' · ' + escapeHtml(a.module) : ''}</span>
      <span class="badge badge-resolved">${formatDateTime(a.created_at)}</span>
    </div>
  `).join('') : '<p class="empty-state">No activity recorded yet.</p>';
}

function renderLatestNotifications(items) {
  const list = document.getElementById('latestNotificationsList');
  if (!list) return;
  list.innerHTML = (items || []).length ? items.map(n => `
    <div class="feedback-item">
      <div class="feedback-icon ${Number(n.is_read) ? 'feedback-blue' : 'feedback-red'}"><span aria-hidden="true">${Number(n.is_read) ? 'i' : '!'}</span></div>
      <span class="feedback-text"><strong>${escapeHtml(n.title)}</strong> — ${escapeHtml(n.message)}</span>
      <span class="badge badge-resolved">${formatDateTime(n.created_at)}</span>
    </div>
  `).join('') : '<p class="empty-state">No notifications yet.</p>';
}

function renderUpcomingDeadlines(items) {
  const list = document.getElementById('upcomingDeadlinesList');
  if (!list) return;
  list.innerHTML = (items || []).length ? items.map(m => `
    <div class="anomaly-item">
      <span class="anomaly-name">${escapeHtml(m.project_code)} — ${escapeHtml(m.title)}</span>
      <span class="badge badge-highprio">Due ${formatDate(m.due_date)}</span>
    </div>
  `).join('') : '<p class="empty-state">No milestones due in the next 14 days.</p>';
}

function renderTodaysInspections(items) {
  const list = document.getElementById('todaysInspectionsList');
  if (!list) return;
  list.innerHTML = (items || []).length ? items.map(i => `
    <div class="anomaly-item">
      <span class="anomaly-name">${escapeHtml(i.project_code)} — ${escapeHtml(i.engineer_name)}</span>
      <span class="badge badge-resolved">${escapeHtml(formatStatus(i.recommendation))}</span>
    </div>
  `).join('') : '<p class="empty-state">No inspections recorded for today.</p>';
}

function renderAIRecommendations(items) {
  const list = document.getElementById('aiRecommendationsList');
  if (!list) return;
  list.innerHTML = (items || []).length ? items.map(text => `
    <div class="feedback-item">
      <div class="feedback-icon feedback-orange"><span aria-hidden="true">AI</span></div>
      <span class="feedback-text">${escapeHtml(text)}</span>
    </div>
  `).join('') : '<p class="empty-state">No recommendations right now.</p>';
}

/* Health-colored project map — separate Leaflet instance from the
   standalone GIS Map page's (status-colored) gisMapInstance/renderGisMap()
   in this same file; this one reads api/dashboard.php's gis_projects
   (health: healthy/warning/critical, the same rule-based formula
   hope/api/portal.php's hopeProjectRiskSummary() uses per-project). */
const DASHBOARD_HEALTH_COLORS = { healthy: '#22c55e', warning: '#f97316', critical: '#ef4444' };
let dashboardGisMapInstance = null;
let dashboardGisMarkers = [];

async function renderDashboardGisMap(projects) {
  const container = document.getElementById('dashboardGisMap');
  const empty = document.getElementById('dashboardGisEmpty');
  if (!container) return;

  const withCoords = (projects || []).filter(p =>
    p.latitude !== null && p.longitude !== null && isWithinQc(Number(p.latitude), Number(p.longitude)));

  if (empty) empty.style.display = withCoords.length ? 'none' : 'block';
  if (!withCoords.length && !dashboardGisMapInstance) return;

  if (!dashboardGisMapInstance) {
    dashboardGisMapInstance = L.map('dashboardGisMap', {
      maxBounds: QC_BOUNDS,
      maxBoundsViscosity: 1.0,
      minZoom: 11,
    }).setView([14.6760, 121.0437], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(dashboardGisMapInstance);

    try {
      const geojson = await loadQcBoundaryGeoJson();
      L.geoJSON(geojson, {
        style: { color: '#2563eb', weight: 1.5, fill: false },
        interactive: false,
      }).addTo(dashboardGisMapInstance);
    } catch { /* decorative outline only */ }
  }

  dashboardGisMarkers.forEach(m => dashboardGisMapInstance.removeLayer(m));
  dashboardGisMarkers = [];

  withCoords.forEach(p => {
    const color = DASHBOARD_HEALTH_COLORS[p.health] || DASHBOARD_HEALTH_COLORS.healthy;
    const marker = L.circleMarker([Number(p.latitude), Number(p.longitude)], {
      radius: 9,
      color: '#fff',
      weight: 2,
      fillColor: color,
      fillOpacity: 0.9,
    }).addTo(dashboardGisMapInstance);

    marker.bindPopup(`
      <strong>${escapeHtml(p.name)}</strong><br>
      <small>${escapeHtml(p.project_code)} · ${escapeHtml(formatStatus(p.status))} · ${p.progress}%</small><br>
      <small>${healthBadge(p.health_score, p.health)}${p.contractor_name ? ' · ' + escapeHtml(p.contractor_name) : ''}</small><br>
      <button style="margin-top:6px;padding:4px 10px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;" onclick="openProjectModal(${p.id})">View Details</button>
    `);
    dashboardGisMarkers.push(marker);
  });

  if (dashboardGisMarkers.length) {
    const group = L.featureGroup(dashboardGisMarkers);
    dashboardGisMapInstance.fitBounds(group.getBounds().pad(0.2));
  }

  setTimeout(() => dashboardGisMapInstance.invalidateSize(), 100);
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
    <div class="table-card" id="workflowPaymentsTable"></div>
    <div class="table-card" id="workflowContractsTable"></div>
    <div class="table-card" id="workflowInspectionsTable"></div>
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
          <p class="modal-label">HEALTH SCORE <small style="font-weight:400;color:#94a3b8;">— advisory only</small></p>
          <p class="modal-val" style="margin-top:4px;">${healthBadge(p.health_score, p.health_status)}</p>
          ${p.health_breakdown ? `
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:8px;font-size:.72rem;color:#64748b;">
            <span>Timeline: ${p.health_breakdown.timeline_progress}/25</span>
            <span>Budget: ${p.health_breakdown.budget_consumption}/20</span>
            <span>Delay: ${p.health_breakdown.delay_status}/20</span>
            <span>Inspection: ${p.health_breakdown.inspection_results}/15</span>
            <span>Complaints: ${p.health_breakdown.citizen_complaints}/10</span>
            <span>AI Risk: ${p.health_breakdown.ai_risk_analysis}/10</span>
          </div>` : ''}
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
          <div id="projectDocList" class="doc-list-scroll" style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
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
        <div>
          <p class="modal-label">QR CODE <small style="font-weight:400;color:#94a3b8;">— public transparency page, no login required</small></p>
          <div style="display:flex;align-items:center;gap:14px;margin-top:6px;">
            <div id="projectQRTarget" style="padding:8px;background:#fff;border-radius:8px;border:1px solid #e2e8f0;"></div>
            <div style="font-size:.78rem;color:#64748b;">
              <p style="margin:0 0 6px;">Citizens can scan this to view the project's name, description, budget, contractor, engineer, progress, timeline, latest photos, and GIS location — no account needed.</p>
              <button class="btn-secondary btn-compact" type="button" onclick="openProjectQRModal('${escapeHtml(p.project_code)}', '${escapeHtml(p.name)}')">Enlarge / Copy Link</button>
            </div>
          </div>
        </div>
        <div>
          <p class="modal-label">ACTIVITY TIMELINE</p>
          <div id="projectTimelineSection" style="margin-top:6px;"></div>
        </div>
      </div>
    `);
    renderProjectTimeline('projectTimelineSection', p.id);
    renderProjectQR('projectQRTarget', p.project_code, 96);
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

const PROJECT_DOC_ROW_LIMIT = 3;

function wireProjectDocRows(container, addBtn) {
  let nextIndex = 1;

  const syncAddBtn = () => {
    const atLimit = container.querySelectorAll('.doc-row').length >= PROJECT_DOC_ROW_LIMIT;
    addBtn.disabled = atLimit;
    addBtn.textContent = atLimit ? `Limit reached (${PROJECT_DOC_ROW_LIMIT} documents max)` : '+ Add another document';
  };

  addBtn.addEventListener('click', () => {
    if (container.querySelectorAll('.doc-row').length >= PROJECT_DOC_ROW_LIMIT) return;
    container.insertAdjacentHTML('beforeend', projectDocRowHtml(nextIndex));
    nextIndex += 1;
    syncAddBtn();
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      // Always keep at least one row so the form never submits with none.
      if (container.querySelectorAll('.doc-row').length <= 1) return;
      event.target.closest('.doc-row')?.remove();
      syncAddBtn();
    }
  });

  syncAddBtn();
}

async function loadProjectsPage(containerId = 'page-project-registration', title = 'Project Registration') {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
    </div>
    <div class="filter-bar">
      <input class="filter-input" id="projSearch" placeholder="Search projects…" oninput="projectsState.search=this.value;projectsState.page=1;fetchProjects()" />
      <select class="filter-select" onchange="projectsState.status=this.value;projectsState.page=1;fetchProjects()">
        <option value="">All Statuses</option>
        ${PROJECT_STATUSES.map(status => `<option value="${status}">${PROJECT_STATUS_LABELS[status]}</option>`).join('')}
      </select>
      <button class="btn-primary" style="margin-left:auto;" onclick="showProjectForm()">+ New Project</button>
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
          <th>Budget</th><th>Spent</th><th>Progress</th><th>Status</th><th>Health</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${rows.map(p => {
          const pct  = p.budget > 0 ? Math.round((p.total_spent / p.budget) * 100) : 0;
          const bar  = p.progress >= 70 ? '#22c55e' : p.progress >= 40 ? '#f97316' : '#ef4444';
          return `
          <tr>
            <td><span class="proj-id">${escapeHtml(p.project_code)}</span></td>
            <td>
              <strong>${escapeHtml(p.name)}</strong><br><small style="color:#94a3b8">${escapeHtml(p.location||'')}</small>
              ${p.has_pending_edit_request ? '<br><span class="badge badge-highprio" style="margin-top:4px;display:inline-block;">Edit Pending City Mayor Review</span>' : ''}
            </td>
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
            <td>${healthBadge(p.health_score, p.health_status)}</td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" title="View" onclick="openProjectModal(${p.id})"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg></button>
                <button class="btn-icon" title="${p.has_pending_edit_request ? 'An edit request for this project is already pending City Mayor review' : 'Edit (requires City Mayor approval)'}" ${p.has_pending_edit_request ? 'disabled' : ''} onclick="showProjectForm(${p.id})"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793z"/><path d="M11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg></button>
                <button class="btn-icon btn-danger" title="Request Deletion (requires City Mayor approval)" onclick="deleteProject(${p.id})"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button>
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

  const districts = Object.keys(window.QC_DISTRICTS || {});
  const title = id ? `Edit Project #${id}` : 'New Project';
  openModal(title, `
    <form id="projectForm" onsubmit="submitProjectForm(event, ${id})">
      ${id ? '<p class="empty-state" style="margin-bottom:10px;">Editing a registered project now requires the City Mayor\'s approval. Your proposed changes are only applied once the City Mayor reviews and approves this request.</p>' : ''}
      <div class="form-grid">
        <div class="form-group" style="grid-column: span 2;">
          <label>Project Name *</label>
          <input name="name" class="form-input" required value="${p?.name||''}" />
        </div>
      </div>

      <div class="proj-location-fieldset" style="border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-top:8px;">
        <p style="font-weight:700;font-size:.85rem;margin-bottom:10px;">Location in Quezon City *</p>
        <div class="form-grid">
          <div class="form-group">
            <label for="projDistrict">District</label>
            <select id="projDistrict" class="form-input">
              <option value="">Select district</option>
              ${districts.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label for="projBarangay">Barangay</label>
            <select id="projBarangay" class="form-input" disabled>
              <option value="">Select a district first</option>
            </select>
          </div>
        </div>
        <p style="font-size:.75rem;color:var(--text-muted);margin:8px 0;">Tap the exact spot on the map to drop a pin — drag it to fine-tune. The location below fills in automatically; you can still edit it for more specific detail (e.g. a street or landmark).</p>
        <div id="projQcMap" style="height:280px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);"></div>
        <div class="form-group" style="margin-top:10px;">
          <label>Location *</label>
          <input name="location" id="projLocationText" class="form-input" required value="${escapeHtml(p?.location||'')}" />
        </div>
        <input type="hidden" name="latitude" id="projLat" value="${p?.latitude ?? ''}" />
        <input type="hidden" name="longitude" id="projLng" value="${p?.longitude ?? ''}" />
      </div>

      <div class="form-grid" style="margin-top:8px;">
        <div class="form-group">
          <label>Project Category</label>
          <select name="category" class="form-input">
            <option value="">Select category</option>
            ${PROJECT_CATEGORIES.map(c => `<option value="${c}" ${p?.category===c?'selected':''}>${c}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Funding Source</label>
          <select name="funding_source" class="form-input">
            <option value="">Select funding source</option>
            ${PROJECT_FUNDING_SOURCES.map(f => `<option value="${f}" ${p?.funding_source===f?'selected':''}>${f}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Implementing Office</label>
          <input name="implementing_office" class="form-input" placeholder="e.g. City Engineering Office" value="${escapeHtml(p?.implementing_office||'')}" />
        </div>
        <div class="form-group">
          <label>Physical Target / Scope</label>
          <input name="physical_target" class="form-input" placeholder="e.g. 2.5 km road rehabilitation" value="${escapeHtml(p?.physical_target||'')}" />
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

      ${roadGeometrySectionHtml(p)}

      <div class="form-group" style="margin-top:8px;">
        <label>Description *</label>
        <textarea name="description" class="form-input" rows="3" required>${p?.description||''}</textarea>
      </div>
      ${!id ? `
        <div class="form-group" style="margin-top:8px;">
          <label>Supporting Documents * <small>(1–3 documents — feasibility study, site assessment, budget justification, etc. Max 10MB each.)</small></label>
          <div class="doc-rows" id="projectDocRows">${projectDocRowHtml(0)}</div>
          <button type="button" class="doc-add-btn" id="projectDocAddBtn">+ Add another document</button>
        </div>
      ` : ''}
      ${id ? `
        <div class="form-group" style="margin-top:8px;">
          <label>Reason for This Edit *</label>
          <textarea name="reason" class="form-input" rows="2" required placeholder="Explain why this change is needed — the City Mayor will review this before it takes effect."></textarea>
        </div>
      ` : ''}
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">${id ? 'Submit Edit Request' : 'Submit for Registration'}</button>
      </div>
    </form>
  `);

  setupProjectLocationPicker(p);
  setupRoadGeometryModule(p);

  if (!id) {
    wireProjectDocRows(document.getElementById('projectDocRows'), document.getElementById('projectDocAddBtn'));
  }
}

/* ============================================================
   PROJECT LOCATION PICKER — District -> Barangay -> map pin.
   Same QC boundary geojson + interaction model as Citizen Feedback's own
   picker (click a barangay or the map to drop a pin, dropdowns cascade),
   adapted for this modal, which is destroyed/rebuilt each time the form
   opens rather than being a persistent page like citizen's.
   ============================================================ */
let projectQcMap = null;
let projectQcGeoLayer = null;
const projectQcLayersByGeo = {};
let projectQcPinMarker = null;

function projectBarangayIndex() {
  const index = {};
  Object.keys(window.QC_DISTRICTS || {}).forEach(district => {
    (window.QC_DISTRICTS[district] || []).forEach(entry => {
      index[entry.geo || entry.name] = { district, name: entry.name };
    });
  });
  return index;
}

async function setupProjectLocationPicker(existingProject) {
  // Tear down any previous instance — the modal DOM is rebuilt each time,
  // so a leftover map bound to a now-detached container would leak/error.
  if (projectQcMap) {
    projectQcMap.remove();
    projectQcMap = null;
    projectQcGeoLayer = null;
    projectQcPinMarker = null;
    Object.keys(projectQcLayersByGeo).forEach(k => delete projectQcLayersByGeo[k]);
  }

  const districtSel = document.getElementById('projDistrict');
  const barangaySel = document.getElementById('projBarangay');
  if (!districtSel || !barangaySel) return;

  districtSel.addEventListener('change', () => {
    populateProjectBarangayOptions(districtSel.value);
    clearProjectPin();
    updateProjectLocationText();
    if (projectQcMap) focusProjectDistrictOnMap(districtSel.value);
  });

  barangaySel.addEventListener('change', () => {
    updateProjectLocationText();
    if (projectQcMap) focusProjectBarangayOnMap(districtSel.value, barangaySel.value);
  });

  try {
    const geojson = await loadQcBoundaryGeoJson();
    const container = document.getElementById('projQcMap');
    if (!container) return; // modal was closed while the geojson was loading

    projectQcMap = L.map('projQcMap', { minZoom: 11, maxZoom: 17 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(projectQcMap);

    const barangayIndex = projectBarangayIndex();
    projectQcGeoLayer = L.geoJSON(geojson, {
      style: { color: '#fff', weight: 1, fillColor: '#94a3b8', fillOpacity: 0.35 },
      onEachFeature: (feature, layer) => {
        const geoName = feature.properties.adm4_en;
        const info = barangayIndex[geoName];
        projectQcLayersByGeo[geoName] = layer;
        if (!info) return;

        layer.on('click', (e) => {
          districtSel.value = info.district;
          populateProjectBarangayOptions(info.district);
          barangaySel.value = info.name;
          updateProjectLocationText();
          focusProjectBarangayOnMap(info.district, info.name, false);
          placeProjectPin(e.latlng);
        });
      },
    }).addTo(projectQcMap);

    projectQcMap.setMaxBounds(projectQcGeoLayer.getBounds().pad(0.3));

    if (existingProject?.latitude && existingProject?.longitude) {
      const latlng = L.latLng(Number(existingProject.latitude), Number(existingProject.longitude));
      projectQcMap.setView(latlng, 15);
      placeProjectPin(latlng);
    } else {
      projectQcMap.fitBounds(projectQcGeoLayer.getBounds());
    }

    setTimeout(() => projectQcMap.invalidateSize(), 100);
  } catch {
    // Map is a convenience layer on top of the dropdowns — the dropdowns
    // and typed location text still work fine without it.
  }
}

function populateProjectBarangayOptions(district) {
  const barangaySel = document.getElementById('projBarangay');
  if (!barangaySel) return;

  if (!district || !(window.QC_DISTRICTS || {})[district]) {
    barangaySel.innerHTML = '<option value="">Select a district first</option>';
    barangaySel.disabled = true;
    return;
  }

  barangaySel.innerHTML = '<option value="">Select barangay</option>' +
    window.QC_DISTRICTS[district].map(entry => `<option value="${escapeHtml(entry.name)}">${escapeHtml(entry.name)}</option>`).join('');
  barangaySel.disabled = false;
}

function focusProjectDistrictOnMap(district, zoom = true) {
  if (!projectQcGeoLayer) return;
  projectQcGeoLayer.eachLayer(layer => projectQcGeoLayer.resetStyle(layer));
  if (!district) {
    if (zoom) projectQcMap.fitBounds(projectQcGeoLayer.getBounds());
    return;
  }
  const barangayIndex = projectBarangayIndex();
  const districtLayers = Object.keys(barangayIndex)
    .filter(geo => barangayIndex[geo].district === district)
    .map(geo => projectQcLayersByGeo[geo])
    .filter(Boolean);
  if (zoom && districtLayers.length) {
    projectQcMap.fitBounds(L.featureGroup(districtLayers).getBounds().pad(0.1));
  }
}

function focusProjectBarangayOnMap(district, barangayName, zoom = true) {
  if (!projectQcGeoLayer || !district || !barangayName) return;
  focusProjectDistrictOnMap(district, false);

  const entry = (window.QC_DISTRICTS[district] || []).find(e => e.name === barangayName);
  if (!entry) return;
  const geoName = entry.geo || entry.name;
  const layer = projectQcLayersByGeo[geoName];
  if (!layer) return;

  layer.setStyle({ fillOpacity: 0.75, weight: 3, color: '#1e293b' });
  if (layer.bringToFront) layer.bringToFront();
  if (zoom) projectQcMap.fitBounds(layer.getBounds().pad(0.4), { maxZoom: 15 });
}

function placeProjectPin(latlng) {
  if (!projectQcMap) return;
  if (!projectQcPinMarker) {
    projectQcPinMarker = L.marker(latlng, { draggable: true, title: 'Exact spot (drag to adjust)' }).addTo(projectQcMap);
    projectQcPinMarker.on('dragend', () => setProjectPinInputs(projectQcPinMarker.getLatLng()));
  } else {
    projectQcPinMarker.setLatLng(latlng);
  }
  setProjectPinInputs(latlng);
}

function clearProjectPin() {
  if (projectQcPinMarker && projectQcMap) {
    projectQcMap.removeLayer(projectQcPinMarker);
  }
  projectQcPinMarker = null;
  setProjectPinInputs(null);
}

function setProjectPinInputs(latlng) {
  const latInput = document.getElementById('projLat');
  const lngInput = document.getElementById('projLng');
  if (latInput) latInput.value = latlng ? latlng.lat.toFixed(7) : '';
  if (lngInput) lngInput.value = latlng ? latlng.lng.toFixed(7) : '';
}

function updateProjectLocationText() {
  const districtSel = document.getElementById('projDistrict');
  const barangaySel = document.getElementById('projBarangay');
  const locationInput = document.getElementById('projLocationText');
  if (!districtSel || !barangaySel || !locationInput) return;
  if (districtSel.value && barangaySel.value) {
    locationInput.value = `Barangay ${barangaySel.value}, ${districtSel.value}, Quezon City`;
  }
}

/* ============================================================
   ROAD GEOMETRY — conditional module on Project Registration/Edit, visible
   only when Project Category = 'Roads and Bridges'. Every other category's
   form and submission flow is completely untouched by this module.
   Shared with the Urban Planning System via integrations/urban-planning/
   road-geometry-feed.php (read-only on their side — IPMS remains the owner
   of the project and its geometry).
   ============================================================ */
const ROAD_TYPES = ['National Road', 'City Road', 'Barangay Road', 'Secondary Road', 'Bridge', 'Intersection'];
const ROAD_STATUSES = ['Existing Road', 'Road Widening', 'New Road', 'Rehabilitation', 'Bridge Construction'];
const ROAD_SURFACES = ['Concrete', 'Asphalt', 'Gravel', 'Mixed'];

function roadToggleHtml(id, label, checked) {
  return `
    <label class="form-checkbox-label road-toggle">
      <span class="toggle-switch">
        <input type="checkbox" id="${id}" ${checked ? 'checked' : ''}>
        <span class="toggle-slider"></span>
      </span>
      ${label}
    </label>
  `;
}

function roadGeometrySectionHtml(p) {
  const g = p?.road_geometry || null;
  return `
    <div class="road-geometry-card" id="roadGeometrySection" style="display:none;">
      <div class="road-geometry-header">
        <h3>Road Geometry</h3>
        <small>Visible only for Roads and Bridges — this data is shared with the Urban Planning System integration.</small>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Road Name *</label>
          <input id="roadName" class="form-input" placeholder="e.g. Commonwealth Avenue Extension" value="${escapeHtml(g?.road_name || '')}" />
        </div>
        <div class="form-group">
          <label>Road Type</label>
          <select id="roadType" class="form-input">
            <option value="">Select road type</option>
            ${ROAD_TYPES.map(t => `<option value="${t}" ${g?.road_type === t ? 'selected' : ''}>${t}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Road Status</label>
          <select id="roadStatus" class="form-input">
            <option value="">Select road status</option>
            ${ROAD_STATUSES.map(s => `<option value="${s}" ${g?.road_status === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>

      <div class="road-map-toolbar">
        <input class="filter-input" id="roadMapSearch" placeholder="Search a location..." autocomplete="off">
        <div id="roadMapSuggestions" class="road-map-suggestions"></div>
        <button type="button" class="btn-secondary btn-compact" id="roadMapUndo">Undo Last Point</button>
        <button type="button" class="btn-secondary btn-compact" id="roadMapClear">Clear Road</button>
        <span class="road-point-count" id="roadPointCount">0 points</span>
      </div>
      <p style="font-size:.75rem;color:var(--text-muted);margin:6px 0;">Click the map to place points along the road, in order from start to end — they connect automatically into one continuous line.</p>
      <div id="roadGeometryMap" style="height:340px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);"></div>

      <div class="road-geometry-readouts">
        <div><span class="modal-label">START</span><p class="modal-val" id="roadStartReadout">Not set</p></div>
        <div><span class="modal-label">END</span><p class="modal-val" id="roadEndReadout">Not set</p></div>
        <div><span class="modal-label">LENGTH</span><p class="modal-val" id="roadLengthReadout">—</p></div>
        <div><span class="modal-label">SEGMENTS</span><p class="modal-val" id="roadSegmentsReadout">0</p></div>
      </div>

      <div id="roadGeometrySummary" class="road-summary-card" style="display:none;"></div>

      <p style="font-weight:700;font-size:.85rem;margin:14px 0 8px;">Optional Details</p>
      <div class="form-grid">
        <div class="form-group"><label>Road Width (meters)</label><input id="roadWidth" type="number" step="0.1" min="0" class="form-input" value="${g?.road_width ?? ''}" /></div>
        <div class="form-group"><label>Number of Lanes</label><input id="roadLanes" type="number" min="0" class="form-input" value="${g?.num_lanes ?? ''}" /></div>
        <div class="form-group">
          <label>Road Surface</label>
          <select id="roadSurface" class="form-input">
            <option value="">Select surface</option>
            ${ROAD_SURFACES.map(s => `<option value="${s}" ${g?.road_surface === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="road-toggle-grid">
        ${roadToggleHtml('roadBridgeIncluded', 'Bridge Included', g?.bridge_included == 1)}
        ${roadToggleHtml('roadDrainageIncluded', 'Drainage Included', g?.drainage_included == 1)}
        ${roadToggleHtml('roadBikeLane', 'Bike Lane', g?.bike_lane == 1)}
        ${roadToggleHtml('roadSidewalk', 'Sidewalk', g?.sidewalk == 1)}
        ${roadToggleHtml('roadStreetlights', 'Streetlights', g?.streetlights == 1)}
      </div>

      <input type="hidden" name="road_geometry" id="roadGeometryJson" />
    </div>
  `;
}

// Mutable drawing state — reset each time the map (re)initializes.
let roadGeoMap = null;
let roadGeoGeoLayer = null;
const roadGeoLayersByGeo = {};
let roadGeoPoints = []; // [[lat,lng], ...] in click order
let roadGeoPointMarkers = [];
let roadGeoPolyline = null;
let roadGeoStartMarker = null;
let roadGeoEndMarker = null;
let roadGeoStartInfo = null; // {lat,lng,address,barangay,district}
let roadGeoEndInfo = null;

function roadGeoBarangayIndex() {
  const index = {};
  Object.keys(window.QC_DISTRICTS || {}).forEach(district => {
    (window.QC_DISTRICTS[district] || []).forEach(entry => {
      index[entry.geo || entry.name] = { district, name: entry.name };
    });
  });
  return index;
}

/** Standard ray-casting point-in-polygon test against a single [lng,lat] ring. */
function roadGeoPointInRing(lat, lng, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i][0], yi = ring[i][1];
    const xj = ring[j][0], yj = ring[j][1];
    const intersect = ((yi > lat) !== (yj > lat)) &&
      (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
    if (intersect) inside = !inside;
  }
  return inside;
}

function roadGeoPointInGeometry(lat, lng, geometry) {
  if (!geometry) return false;
  const polygons = geometry.type === 'Polygon' ? [geometry.coordinates]
    : geometry.type === 'MultiPolygon' ? geometry.coordinates : [];
  return polygons.some(rings => rings.length && roadGeoPointInRing(lat, lng, rings[0]));
}

/** Finds which QC barangay/district a lat/lng falls in, using our own boundary
    data — more reliable than a third-party geocoder's approximate guess,
    and it's already loaded for the map anyway. */
function roadGeoLocateBarangay(lat, lng) {
  const index = roadGeoBarangayIndex();
  for (const geoName of Object.keys(roadGeoLayersByGeo)) {
    const feature = roadGeoLayersByGeo[geoName]?.feature;
    if (feature && roadGeoPointInGeometry(lat, lng, feature.geometry)) {
      const info = index[geoName];
      if (info) return { barangay: info.name, district: info.district };
    }
  }
  return { barangay: null, district: null };
}

function roadGeoHaversineMeters(a, b) {
  const R = 6371000;
  const dLat = (b[0] - a[0]) * Math.PI / 180;
  const dLng = (b[1] - a[1]) * Math.PI / 180;
  const lat1 = a[0] * Math.PI / 180, lat2 = b[0] * Math.PI / 180;
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function roadGeoTotalLengthMeters(points) {
  let total = 0;
  for (let i = 1; i < points.length; i++) total += roadGeoHaversineMeters(points[i - 1], points[i]);
  return total;
}

function roadGeoFormatLength(meters) {
  return meters >= 1000 ? (meters / 1000).toFixed(2) + ' km' : Math.round(meters) + ' m';
}

async function roadGeoReverseGeocode(lat, lng) {
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`);
    const data = await res.json();
    return data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  } catch {
    return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  }
}

function roadGeoUpdatePointCount() {
  const el = document.getElementById('roadPointCount');
  if (el) el.textContent = `${roadGeoPoints.length} point${roadGeoPoints.length === 1 ? '' : 's'}`;
}

function roadGeoRenderPolyline() {
  if (!roadGeoMap) return;
  if (roadGeoPolyline) { roadGeoMap.removeLayer(roadGeoPolyline); roadGeoPolyline = null; }
  if (roadGeoPoints.length >= 2) {
    roadGeoPolyline = L.polyline(roadGeoPoints, { color: '#2563eb', weight: 4 }).addTo(roadGeoMap);
  }
}

function roadGeoRenderStartEndMarkers() {
  if (!roadGeoMap) return;
  if (roadGeoStartMarker) { roadGeoMap.removeLayer(roadGeoStartMarker); roadGeoStartMarker = null; }
  if (roadGeoEndMarker) { roadGeoMap.removeLayer(roadGeoEndMarker); roadGeoEndMarker = null; }

  if (roadGeoPoints.length >= 1) {
    roadGeoStartMarker = L.circleMarker(roadGeoPoints[0], { radius: 8, color: '#1e40af', fillColor: '#3b82f6', fillOpacity: 1, weight: 2 })
      .bindTooltip('Start', { permanent: false }).addTo(roadGeoMap);
  }
  if (roadGeoPoints.length >= 2) {
    roadGeoEndMarker = L.circleMarker(roadGeoPoints[roadGeoPoints.length - 1], { radius: 8, color: '#991b1b', fillColor: '#ef4444', fillOpacity: 1, weight: 2 })
      .bindTooltip('End', { permanent: false }).addTo(roadGeoMap);
  }
}

function roadGeoBarangaysCovered() {
  const set = new Set();
  roadGeoPoints.forEach(pt => {
    const loc = roadGeoLocateBarangay(pt[0], pt[1]);
    if (loc.barangay) set.add(loc.barangay);
  });
  return Array.from(set);
}

function roadGeoDistrictsCovered() {
  const set = new Set();
  roadGeoPoints.forEach(pt => {
    const loc = roadGeoLocateBarangay(pt[0], pt[1]);
    if (loc.district) set.add(loc.district);
  });
  return Array.from(set);
}

/** Recomputes start (first point, set once) and end (last point, changes as
    points are added/removed) — avoids re-geocoding points that haven't moved. */
async function roadGeoRecomputeEndpoints() {
  if (roadGeoPoints.length === 0) {
    roadGeoStartInfo = null;
    roadGeoEndInfo = null;
    return;
  }
  const startPt = roadGeoPoints[0];
  const endPt = roadGeoPoints[roadGeoPoints.length - 1];

  if (!roadGeoStartInfo || roadGeoStartInfo.lat !== startPt[0] || roadGeoStartInfo.lng !== startPt[1]) {
    const loc = roadGeoLocateBarangay(startPt[0], startPt[1]);
    roadGeoStartInfo = { lat: startPt[0], lng: startPt[1], address: `${startPt[0].toFixed(5)}, ${startPt[1].toFixed(5)}`, ...loc };
    roadGeoReverseGeocode(startPt[0], startPt[1]).then(addr => {
      if (roadGeoStartInfo && roadGeoStartInfo.lat === startPt[0] && roadGeoStartInfo.lng === startPt[1]) {
        roadGeoStartInfo.address = addr;
        roadGeoRefresh();
      }
    });
  }

  if (roadGeoPoints.length >= 2) {
    if (!roadGeoEndInfo || roadGeoEndInfo.lat !== endPt[0] || roadGeoEndInfo.lng !== endPt[1]) {
      const loc = roadGeoLocateBarangay(endPt[0], endPt[1]);
      roadGeoEndInfo = { lat: endPt[0], lng: endPt[1], address: `${endPt[0].toFixed(5)}, ${endPt[1].toFixed(5)}`, ...loc };
      roadGeoReverseGeocode(endPt[0], endPt[1]).then(addr => {
        if (roadGeoEndInfo && roadGeoEndInfo.lat === endPt[0] && roadGeoEndInfo.lng === endPt[1]) {
          roadGeoEndInfo.address = addr;
          roadGeoRefresh();
        }
      });
    }
  } else {
    roadGeoEndInfo = null;
  }
}

function roadGeoRenderReadouts() {
  const startEl = document.getElementById('roadStartReadout');
  const endEl = document.getElementById('roadEndReadout');
  const lengthEl = document.getElementById('roadLengthReadout');
  const segEl = document.getElementById('roadSegmentsReadout');
  const summaryEl = document.getElementById('roadGeometrySummary');

  if (startEl) startEl.textContent = roadGeoStartInfo ? roadGeoStartInfo.address : 'Not set';
  if (endEl) endEl.textContent = roadGeoEndInfo ? roadGeoEndInfo.address : 'Not set';

  const length = roadGeoTotalLengthMeters(roadGeoPoints);
  if (lengthEl) lengthEl.textContent = roadGeoPoints.length >= 2 ? roadGeoFormatLength(length) : '—';
  if (segEl) segEl.textContent = String(Math.max(0, roadGeoPoints.length - 1));

  if (!summaryEl) return;
  if (roadGeoPoints.length >= 2 && roadGeoStartInfo && roadGeoEndInfo) {
    const barangays = roadGeoBarangaysCovered();
    const districts = roadGeoDistrictsCovered();
    const roadNameVal = document.getElementById('roadName')?.value || '(unnamed road)';
    summaryEl.style.display = 'block';
    summaryEl.innerHTML = `
      <p class="modal-label">ROAD SUMMARY</p>
      <div class="road-summary-grid">
        <div><span class="modal-label">ROAD NAME</span><p class="modal-val">${escapeHtml(roadNameVal)}</p></div>
        <div><span class="modal-label">LENGTH</span><p class="modal-val">${roadGeoFormatLength(length)}</p></div>
        <div><span class="modal-label">START</span><p class="modal-val">${escapeHtml(roadGeoStartInfo.address)}</p></div>
        <div><span class="modal-label">END</span><p class="modal-val">${escapeHtml(roadGeoEndInfo.address)}</p></div>
        <div><span class="modal-label">BARANGAYS COVERED</span><p class="modal-val">${barangays.length ? barangays.map(escapeHtml).join(', ') : '-'}</p></div>
        <div><span class="modal-label">DISTRICTS COVERED</span><p class="modal-val">${districts.length ? districts.map(escapeHtml).join(', ') : '-'}</p></div>
      </div>
    `;
  } else {
    summaryEl.style.display = 'none';
  }
}

function roadGeoSyncHiddenInput() {
  const input = document.getElementById('roadGeometryJson');
  if (!input) return;
  if (roadGeoPoints.length < 2 || !roadGeoStartInfo || !roadGeoEndInfo) {
    input.value = '';
    return;
  }
  const payload = {
    road_name: document.getElementById('roadName')?.value.trim() || '',
    road_type: document.getElementById('roadType')?.value || '',
    road_status: document.getElementById('roadStatus')?.value || '',
    points: roadGeoPoints,
    start: roadGeoStartInfo,
    end: roadGeoEndInfo,
    barangays_covered: roadGeoBarangaysCovered(),
    districts_covered: roadGeoDistrictsCovered(),
    estimated_length_meters: roadGeoTotalLengthMeters(roadGeoPoints),
    num_segments: roadGeoPoints.length - 1,
    road_width: document.getElementById('roadWidth')?.value || null,
    num_lanes: document.getElementById('roadLanes')?.value || null,
    road_surface: document.getElementById('roadSurface')?.value || '',
    bridge_included: document.getElementById('roadBridgeIncluded')?.checked || false,
    drainage_included: document.getElementById('roadDrainageIncluded')?.checked || false,
    bike_lane: document.getElementById('roadBikeLane')?.checked || false,
    sidewalk: document.getElementById('roadSidewalk')?.checked || false,
    streetlights: document.getElementById('roadStreetlights')?.checked || false,
  };
  input.value = JSON.stringify(payload);
}

function roadGeoRefresh() {
  roadGeoRenderReadouts();
  roadGeoSyncHiddenInput();
}

async function roadGeoAddPoint(latlng) {
  roadGeoPoints.push([latlng.lat, latlng.lng]);
  const marker = L.circleMarker(latlng, { radius: 5, color: '#1e293b', fillColor: '#fff', fillOpacity: 1, weight: 2 }).addTo(roadGeoMap);
  roadGeoPointMarkers.push(marker);

  roadGeoRenderPolyline();
  roadGeoRenderStartEndMarkers();
  roadGeoUpdatePointCount();
  await roadGeoRecomputeEndpoints();
  roadGeoRefresh();
}

async function roadGeoUndoLastPoint() {
  if (!roadGeoPoints.length) return;
  roadGeoPoints.pop();
  const marker = roadGeoPointMarkers.pop();
  if (marker && roadGeoMap) roadGeoMap.removeLayer(marker);

  roadGeoRenderPolyline();
  roadGeoRenderStartEndMarkers();
  roadGeoUpdatePointCount();
  roadGeoEndInfo = null; // the "last point" just changed — force a fresh lookup
  await roadGeoRecomputeEndpoints();
  roadGeoRefresh();
}

function roadGeoClearAll() {
  roadGeoPoints = [];
  roadGeoPointMarkers.forEach(m => roadGeoMap?.removeLayer(m));
  roadGeoPointMarkers = [];
  if (roadGeoPolyline) { roadGeoMap?.removeLayer(roadGeoPolyline); roadGeoPolyline = null; }
  if (roadGeoStartMarker) { roadGeoMap?.removeLayer(roadGeoStartMarker); roadGeoStartMarker = null; }
  if (roadGeoEndMarker) { roadGeoMap?.removeLayer(roadGeoEndMarker); roadGeoEndMarker = null; }
  roadGeoStartInfo = null;
  roadGeoEndInfo = null;
  roadGeoUpdatePointCount();
  roadGeoRefresh();
}

async function initRoadGeometryMap(existingGeometry) {
  if (roadGeoMap) {
    roadGeoMap.remove();
    roadGeoMap = null;
    roadGeoGeoLayer = null;
    Object.keys(roadGeoLayersByGeo).forEach(k => delete roadGeoLayersByGeo[k]);
  }
  roadGeoPoints = [];
  roadGeoPointMarkers = [];
  roadGeoPolyline = null;
  roadGeoStartMarker = null;
  roadGeoEndMarker = null;
  roadGeoStartInfo = null;
  roadGeoEndInfo = null;

  if (!document.getElementById('roadGeometryMap')) return;

  try {
    const geojson = await loadQcBoundaryGeoJson();
    if (!document.getElementById('roadGeometryMap')) return; // modal closed while loading

    roadGeoMap = L.map('roadGeometryMap', { minZoom: 11, maxZoom: 18 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(roadGeoMap);

    roadGeoGeoLayer = L.geoJSON(geojson, {
      style: { color: '#94a3b8', weight: 1, fillColor: '#94a3b8', fillOpacity: 0.06 },
      onEachFeature: (feature, layer) => {
        roadGeoLayersByGeo[feature.properties.adm4_en] = layer;
      },
    }).addTo(roadGeoMap);
    roadGeoMap.setMaxBounds(roadGeoGeoLayer.getBounds().pad(0.3));
    roadGeoMap.fitBounds(roadGeoGeoLayer.getBounds());

    roadGeoMap.on('click', (e) => { roadGeoAddPoint(e.latlng); });

    setTimeout(() => roadGeoMap.invalidateSize(), 100);

    if (existingGeometry?.polyline_coordinates?.length) {
      existingGeometry.polyline_coordinates.forEach(pt => {
        const marker = L.circleMarker(pt, { radius: 5, color: '#1e293b', fillColor: '#fff', fillOpacity: 1, weight: 2 }).addTo(roadGeoMap);
        roadGeoPointMarkers.push(marker);
        roadGeoPoints.push(pt);
      });
      roadGeoRenderPolyline();
      roadGeoRenderStartEndMarkers();
      roadGeoUpdatePointCount();
      roadGeoStartInfo = {
        lat: existingGeometry.start_latitude, lng: existingGeometry.start_longitude,
        address: existingGeometry.start_address, barangay: existingGeometry.start_barangay, district: existingGeometry.start_district,
      };
      roadGeoEndInfo = {
        lat: existingGeometry.end_latitude, lng: existingGeometry.end_longitude,
        address: existingGeometry.end_address, barangay: existingGeometry.end_barangay, district: existingGeometry.end_district,
      };
      roadGeoRefresh();
      roadGeoMap.fitBounds(L.polyline(roadGeoPoints).getBounds().pad(0.3));
    }
  } catch {
    // The map is a convenience layer for drawing; if it fails to load there's
    // nothing meaningful to draw with, but this must not crash the whole form.
  }
}

function setupRoadGeometryModule(p) {
  const form = document.getElementById('projectForm');
  const categorySelect = form?.querySelector('select[name="category"]');
  const section = document.getElementById('roadGeometrySection');
  if (!categorySelect || !section) return;

  const applyVisibility = async () => {
    const isRoads = categorySelect.value === 'Roads and Bridges';
    section.style.display = isRoads ? 'block' : 'none';
    if (isRoads && !roadGeoMap) {
      await initRoadGeometryMap(p?.road_geometry || null);
    } else if (isRoads && roadGeoMap) {
      setTimeout(() => roadGeoMap.invalidateSize(), 50);
    }
  };

  categorySelect.addEventListener('change', applyVisibility);
  applyVisibility(); // pre-fill case: editing an existing Roads and Bridges project

  ['roadName', 'roadType', 'roadStatus', 'roadWidth', 'roadLanes', 'roadSurface',
    'roadBridgeIncluded', 'roadDrainageIncluded', 'roadBikeLane', 'roadSidewalk', 'roadStreetlights']
    .forEach(fieldId => {
      const el = document.getElementById(fieldId);
      el?.addEventListener('input', roadGeoRefresh);
      el?.addEventListener('change', roadGeoRefresh);
    });

  document.getElementById('roadMapUndo')?.addEventListener('click', roadGeoUndoLastPoint);
  document.getElementById('roadMapClear')?.addEventListener('click', roadGeoClearAll);

  // Search box — jumps the map to a place, same Nominatim pattern as the
  // CIMMS map picker. Navigation only; clicking the map is still what places
  // a road point, so a search never accidentally adds one.
  const searchInput = document.getElementById('roadMapSearch');
  const suggestionBox = document.getElementById('roadMapSuggestions');
  let roadSearchDebounce = null;
  searchInput?.addEventListener('input', () => {
    const query = searchInput.value.trim();
    clearTimeout(roadSearchDebounce);
    if (query.length < 3 || !suggestionBox) {
      if (suggestionBox) suggestionBox.style.display = 'none';
      return;
    }
    roadSearchDebounce = setTimeout(() => {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Quezon City')}&limit=6`)
        .then(res => res.json())
        .then(results => {
          suggestionBox.innerHTML = '';
          if (!results.length) { suggestionBox.style.display = 'none'; return; }
          results.forEach(place => {
            const div = document.createElement('div');
            div.textContent = place.display_name;
            div.onclick = () => {
              suggestionBox.style.display = 'none';
              searchInput.value = place.display_name;
              if (roadGeoMap) roadGeoMap.setView([parseFloat(place.lat), parseFloat(place.lon)], 16);
            };
            suggestionBox.appendChild(div);
          });
          suggestionBox.style.display = 'block';
        })
        .catch(() => { suggestionBox.style.display = 'none'; });
    }, 350);
  });
}

async function submitProjectForm(e, id) {
  e.preventDefault();

  const categoryValue = e.target.querySelector('select[name="category"]')?.value;
  if (categoryValue === 'Roads and Bridges') {
    const roadName = document.getElementById('roadName')?.value.trim();
    if (!roadName) { toast('Road Name is required for Roads and Bridges projects.', 'error'); return; }
    if (roadGeoPoints.length < 2) { toast('Draw at least two points (a start and an end) to define the road.', 'error'); return; }
    if (!roadGeoStartInfo || !roadGeoEndInfo) { toast('Please wait a moment for the road\'s start/end location to finish loading before submitting.', 'error'); return; }
    roadGeoSyncHiddenInput();
  }

  try {
    let res;
    if (id) {
      // Edits to an already-registered project are no longer applied
      // instantly — they're submitted as a request_edit for HOPE to review
      // and approve/reject (see api/projects.php + hope/api/portal.php's
      // decide_edit). Direct PUT stays in place for contractor/engineer
      // assignment and status-transition actions elsewhere, which this form
      // never touches.
      const fd = new FormData(e.target);
      const body = Object.fromEntries(fd.entries());
      res = await fetch(`${API.projects}?action=request_edit&id=${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: JSON.stringify(body),
      }).then(r => r.json());
    } else {
      res = await postForm(API.projects, new FormData(e.target));
    }
    if (res.error) { toast(res.error, 'error'); return; }
    toast(id ? 'Edit request submitted — awaiting City Mayor review.' : 'Project registered — awaiting Engineering Review.');
    closeModal();
    fetchProjects();
  } catch { toast('Something went wrong', 'error'); }
}

function deleteProject(id) {
  openModal('Request Project Deletion', `
    <form id="deleteProjectForm">
      <p class="empty-state" style="margin-bottom:10px;">Deleting a project now requires the City Mayor's approval. Submit a reason below — the project (and its related expenses/milestones) is only removed once the City Mayor approves the request.</p>
      <div class="form-group">
        <label>Reason for Deletion *</label>
        <textarea name="reason" class="form-input" rows="4" required placeholder="Explain why this project should be permanently deleted"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Submit Deletion Request</button>
      </div>
    </form>
  `);

  document.getElementById('deleteProjectForm').addEventListener('submit', async event => {
    event.preventDefault();
    const reason = new FormData(event.target).get('reason') || '';
    try {
      const res = await fetch(`${API.projects}?action=request_deletion&id=${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...CSRF_HEADERS },
        body: JSON.stringify({ reason }),
      });
      const result = await res.json();
      if (!res.ok || result.error) { toast(result.error || 'Failed to submit deletion request', 'error'); return; }
      toast('Deletion request submitted for City Mayor review.');
      closeModal();
      fetchProjects();
    } catch {
      toast('Failed to submit deletion request', 'error');
    }
  });
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
        <option value="endorsed">Endorsed / Awaiting City Mayor Review</option>
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
    <div id="approvalTable" class="table-card"></div>
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
                ${p.status === 'endorsed' ? '<small>Awaiting City Mayor review</small>' : ''}
                ${p.status === 'assigned' ? '<small>Awaiting Notice to Proceed</small>' : ''}
                ${p.status === 'completion_inspection' ? '<small>Awaiting completion inspection</small>' : ''}
                ${p.status === 'completed' ? `<button class="btn-primary btn-compact" onclick="openTurnoverModal(${p.id}, '${escapeHtml(p.name)}', '${escapeHtml(p.project_code)}')">Record Turnover</button>` : ''}
                <button class="btn-secondary btn-compact" onclick="openProjectModal(${p.id})">View</button>
              </div>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function openTurnoverModal(id, projectName, projectCode) {
  openModal('Record Turnover', `
    <form id="turnoverForm" onsubmit="submitTurnover(event, ${id}, '${escapeHtml(projectName)}', '${escapeHtml(projectCode)}')">
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

async function submitTurnover(e, id, projectName, projectCode) {
  e.preventDefault();
  const form = new FormData(e.target);
  const turnoverOffice = form.get('turnover_office');
  const notes = form.get('notes');
  try {
    const res = await postAction(API.projects, 'turnover', {
      project_id: id,
      turnover_office: turnoverOffice,
      notes,
    });
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Turnover recorded.');
    fetchApprovalProjects();
    openCompletionRecordModal(projectName, projectCode, turnoverOffice, notes);
  } catch {
    toast('Failed to record turnover', 'error');
  }
}

/* Completion Document — a printable record of the turnover just recorded,
   stamped with the project's QR code so a citizen holding the printout (or
   viewing it later) can scan straight through to the live transparency page. */
function openCompletionRecordModal(projectName, projectCode, turnoverOffice, notes) {
  openModal('Completion Record', `
    <div id="completionRecordPrint">
      <p style="font-size:.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin:0 0 4px;">Project Completion &amp; Turnover Record</p>
      <h3 style="margin:0 0 10px;">${escapeHtml(projectName)}</h3>
      <p style="font-size:.85rem;"><strong>Project Code:</strong> ${escapeHtml(projectCode)}</p>
      <p style="font-size:.85rem;"><strong>Turned Over To:</strong> ${escapeHtml(turnoverOffice)}</p>
      <p style="font-size:.85rem;"><strong>Date:</strong> ${formatDate(new Date().toISOString())}</p>
      ${notes ? `<p style="font-size:.85rem;"><strong>Notes:</strong> ${escapeHtml(notes)}</p>` : ''}
      <div style="text-align:center;margin-top:16px;">
        <div id="completionQRTarget" style="display:inline-block;padding:10px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;"></div>
        <p style="font-size:.72rem;color:#94a3b8;margin-top:6px;">Scan for the live public transparency page</p>
      </div>
    </div>
    <div class="form-actions">
      <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
      <button type="button" class="btn-primary" onclick="window.print()">Print</button>
    </div>
  `);
  setTimeout(() => renderProjectQR('completionQRTarget', projectCode, 140), 0);
}

/* ============================================================
   ARCHIVE
   Completed Projects (status=turnover) and Cancelled Projects (status=
   cancelled) are just api/projects.php filtered to a different status,
   reusing the same read-only table. Archived Documents / Historical
   Records have no backing data model yet, so they're placeholders.
   ============================================================ */
const statusListState = {};

async function loadStatusFilteredProjectsPage(containerId, title, statusParam) {
  const container = document.getElementById(containerId);
  if (!container) return;

  statusListState[containerId] = statusListState[containerId] || { page: 1, search: '' };

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search projects..." id="${containerId}Search" />
    </div>
    <div id="${containerId}Table" class="table-card"></div>
    <div id="${containerId}Pager" class="pager"></div>
  `;

  document.getElementById(`${containerId}Search`)?.addEventListener('input', e => {
    statusListState[containerId].search = e.target.value;
    statusListState[containerId].page = 1;
    fetchStatusFilteredProjects(containerId, statusParam);
  });

  fetchStatusFilteredProjects(containerId, statusParam);
}

async function fetchStatusFilteredProjects(containerId, statusParam) {
  const wrap = document.getElementById(`${containerId}Table`);
  if (!wrap) return;
  setLoading(wrap, true);
  const state = statusListState[containerId] || { page: 1, search: '' };

  try {
    const d = await get(API.projects, { page: state.page, search: state.search, status_in: statusParam });
    renderStatusFilteredTable(containerId, d.data || []);
    renderPager(`${containerId}Pager`, d.page, d.last_page, p => {
      statusListState[containerId].page = p;
      fetchStatusFilteredProjects(containerId, statusParam);
    });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load projects.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderStatusFilteredTable(containerId, rows) {
  const wrap = document.getElementById(`${containerId}Table`);
  if (!wrap) return;
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No projects found.</p>';
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
            <td><button class="btn-secondary btn-compact" onclick="openProjectModal(${p.id})">View</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

/* ============================================================
   PUBLIC FACILITIES INTEGRATION — read-only, Barangay Culiat only.
   Not a core IPMS module: this is a filtered lens over IPMS's own projects,
   standing in for what would be synchronized to the separate "Public
   Facilities Management System" capstone project. Everything here is
   GET-only against api/public-facilities.php, which has no write actions
   at all — there is nothing on this page that can create, edit, delete,
   approve, assign, or otherwise mutate a project.
   ============================================================ */
const PUBLIC_FACILITIES_VIEWS = [
  { key: 'planned', label: 'Planned Projects', badge: 'pf-badge-planned', badgeLabel: 'PLANNED' },
  { key: 'ongoing', label: 'Ongoing Projects', badge: 'pf-badge-ongoing', badgeLabel: 'ONGOING' },
  { key: 'completed', label: 'Completed Projects', badge: 'pf-badge-completed', badgeLabel: 'COMPLETED' },
  { key: 'cancelled', label: 'Cancelled Projects', badge: 'pf-badge-cancelled', badgeLabel: 'CANCELLED' },
];
let publicFacilitiesState = { view: 'planned', page: 1, search: '', engineer: '', contractor: '', year: '', min_budget: '', max_budget: '' };

function publicFacilitiesViewBadge(viewKey) {
  const v = PUBLIC_FACILITIES_VIEWS.find(x => x.key === viewKey);
  return v ? `<span class="pf-badge ${v.badge}">${v.badgeLabel}</span>` : '';
}

function publicFacilitiesSyncBadge() {
  return `<span class="pf-sync-badge" title="This is a read-only view of IPMS's own project data.">Synced from IPMS</span>`;
}

async function loadPublicFacilitiesPage() {
  const container = document.getElementById('page-public-facilities-integration');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Public Facilities Integration</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Read-only view for the Public Facilities Management System capstone integration — Barangay Culiat only.
          IPMS is the source of truth; nothing here can create, edit, delete, approve, assign, or otherwise change a project.
        </p>
      </div>
      ${publicFacilitiesSyncBadge()}
    </div>

    <div class="pf-tabs" id="pfTabs">
      ${PUBLIC_FACILITIES_VIEWS.map(v => `
        <button type="button" class="pf-tab${v.key === publicFacilitiesState.view ? ' active' : ''}" data-view="${v.key}">${v.label}</button>
      `).join('')}
    </div>

    <div class="filter-bar" style="flex-wrap:wrap;">
      <input class="filter-input" id="pfSearch" placeholder="Search project name, ID, or category..." value="${escapeHtml(publicFacilitiesState.search)}">
      <input class="filter-input" id="pfEngineer" placeholder="Engineer..." value="${escapeHtml(publicFacilitiesState.engineer)}" style="max-width:160px;">
      <input class="filter-input" id="pfContractor" placeholder="Contractor..." value="${escapeHtml(publicFacilitiesState.contractor)}" style="max-width:160px;">
      <input class="filter-input" id="pfYear" placeholder="Year..." value="${escapeHtml(publicFacilitiesState.year)}" style="max-width:100px;">
      <input class="filter-input" id="pfMinBudget" type="number" placeholder="Min budget" value="${escapeHtml(publicFacilitiesState.min_budget)}" style="max-width:130px;">
      <input class="filter-input" id="pfMaxBudget" type="number" placeholder="Max budget" value="${escapeHtml(publicFacilitiesState.max_budget)}" style="max-width:130px;">
      <input class="filter-input" disabled value="Barangay: Culiat" title="Locked — Future Ready: additional barangays can be enabled later" style="max-width:160px;opacity:.7;">
    </div>

    <div id="pfTable" class="table-card"></div>
    <div id="pfPager" class="pager"></div>
  `;

  document.getElementById('pfTabs').addEventListener('click', e => {
    const btn = e.target.closest('.pf-tab');
    if (!btn) return;
    publicFacilitiesState.view = btn.dataset.view;
    publicFacilitiesState.page = 1;
    document.querySelectorAll('#pfTabs .pf-tab').forEach(t => t.classList.toggle('active', t === btn));
    fetchPublicFacilities();
  });

  const bindFilter = (id, key, transform = v => v) => {
    document.getElementById(id)?.addEventListener('input', e => {
      publicFacilitiesState[key] = transform(e.target.value);
      publicFacilitiesState.page = 1;
      fetchPublicFacilities();
    });
  };
  bindFilter('pfSearch', 'search');
  bindFilter('pfEngineer', 'engineer');
  bindFilter('pfContractor', 'contractor');
  bindFilter('pfYear', 'year');
  bindFilter('pfMinBudget', 'min_budget');
  bindFilter('pfMaxBudget', 'max_budget');

  await fetchPublicFacilities();
}

async function fetchPublicFacilities() {
  const wrap = document.getElementById('pfTable');
  if (!wrap) return;
  setLoading(wrap, true);
  const s = publicFacilitiesState;

  try {
    const result = await get(API.publicFacilities, {
      action: 'list', view: s.view, page: s.page, search: s.search,
      engineer: s.engineer, contractor: s.contractor, year: s.year,
      min_budget: s.min_budget, max_budget: s.max_budget,
    });
    renderPublicFacilitiesTable(s.view, result.data || []);
    renderPager('pfPager', result.page, result.last_page, p => {
      publicFacilitiesState.page = p;
      fetchPublicFacilities();
    });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load Public Facilities Integration data.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderPublicFacilitiesTable(view, rows) {
  const wrap = document.getElementById('pfTable');
  if (!wrap) return;
  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No Barangay Culiat projects found for this view.</p>';
    return;
  }

  const columnsByView = {
    planned: {
      head: ['Project', 'Category', 'District / Barangay', 'Budget', 'Start Date', 'Expected Completion', 'Status', ''],
      row: p => `
        <td><span class="proj-id">${p.project_code}</span><br><strong>${escapeHtml(p.name)}</strong></td>
        <td>${escapeHtml(p.category || '-')}</td>
        <td>${escapeHtml(p.barangay)}</td>
        <td>${formatMoney(p.budget)}</td>
        <td>${formatDate(p.start_date)}</td>
        <td>${formatDate(p.end_date)}</td>
        <td>${statusBadge(p.status)}</td>
      `,
    },
    ongoing: {
      head: ['Project', 'Progress', 'Engineer', 'Contractor', 'Budget Utilization', 'Inspection Status', 'Expected Completion', ''],
      row: p => {
        const util = p.budget > 0 ? Math.round((p.total_spent / p.budget) * 100) : 0;
        return `
          <td><span class="proj-id">${p.project_code}</span><br><strong>${escapeHtml(p.name)}</strong></td>
          <td>${p.progress}%</td>
          <td>${escapeHtml(p.engineer_name || 'Unassigned')}</td>
          <td>${escapeHtml(p.contractor_name || 'Unassigned')}</td>
          <td>${util}%</td>
          <td>${p.inspection_status ? formatStatus(p.inspection_status.recommendation) : 'No inspection yet'}</td>
          <td>${formatDate(p.end_date)}</td>
        `;
      },
    },
    completed: {
      head: ['Project', 'Completion Date', 'Final Budget', 'Duration', 'Status', ''],
      row: p => `
        <td><span class="proj-id">${p.project_code}</span><br><strong>${escapeHtml(p.name)}</strong></td>
        <td>${formatDate(p.end_date)}</td>
        <td>${formatMoney(p.budget)}</td>
        <td>${formatDate(p.start_date)} — ${formatDate(p.end_date)}</td>
        <td>${statusBadge(p.status)}</td>
      `,
    },
    cancelled: {
      head: ['Project', 'Reason', 'Cancelled Date', 'Previous Status', ''],
      row: p => `
        <td><span class="proj-id">${p.project_code}</span><br><strong>${escapeHtml(p.name)}</strong></td>
        <td>${escapeHtml(p.rejection_reason || '-')}</td>
        <td>${formatDate(p.cancelled_date)}</td>
        <td>${p.previous_status ? formatStatus(p.previous_status) : '-'}</td>
      `,
    },
  };
  const cfg = columnsByView[view];

  wrap.innerHTML = `
    <table class="data-table">
      <thead><tr>${cfg.head.map(h => `<th>${h}</th>`).join('')}</tr></thead>
      <tbody>
        ${rows.map(p => `
          <tr>
            ${cfg.row(p)}
            <td><button class="btn-secondary btn-compact" onclick="openPublicFacilitiesDetailModal(${p.id})">View Details</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function openPublicFacilitiesDetailModal(id) {
  try {
    const p = await get(API.publicFacilities, { action: 'detail', id });

    const photos = p.photos || [];
    const docs = p.documents || [];
    const fb = p.feedback_summary || {};

    openModal(p.name, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div>${publicFacilitiesSyncBadge()}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">PROJECT ID</p><p class="modal-val">${p.project_code}</p></div>
          <div><p class="modal-label">STATUS</p><p class="modal-val">${statusBadge(p.status)}</p></div>
          <div><p class="modal-label">CATEGORY</p><p class="modal-val">${escapeHtml(p.category || '-')}</p></div>
          <div><p class="modal-label">BARANGAY</p><p class="modal-val">${escapeHtml(p.barangay)}</p></div>
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${escapeHtml(p.location || '-')}</p></div>
          <div><p class="modal-label">BUDGET</p><p class="modal-val">${formatMoney(p.budget)}</p></div>
          <div><p class="modal-label">START DATE</p><p class="modal-val">${formatDate(p.start_date)}</p></div>
          <div><p class="modal-label">EXPECTED COMPLETION</p><p class="modal-val">${formatDate(p.end_date)}</p></div>
          <div><p class="modal-label">PROGRESS</p><p class="modal-val">${p.progress}%</p></div>
          <div><p class="modal-label">BUDGET UTILIZATION</p><p class="modal-val">${p.budget > 0 ? Math.round((p.total_spent / p.budget) * 100) : 0}%</p></div>
        </div>

        <div><p class="modal-label">DESCRIPTION</p><p class="modal-val" style="font-weight:400;">${escapeHtml(p.description || 'No description on file.')}</p></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">ENGINEER ASSIGNED</p><p class="modal-val">${p.engineer ? escapeHtml(p.engineer.full_name) : 'Unassigned'}</p></div>
          <div><p class="modal-label">CONTRACTOR</p><p class="modal-val">${escapeHtml(p.contractor_name || 'Unassigned')}${p.contractor_name ? ` (score ${p.performance_score}/100)` : ''}</p></div>
        </div>

        ${p.status === 'cancelled' ? `
          <div class="pf-cancel-box">
            <p class="modal-label">CANCELLATION</p>
            <p class="modal-val" style="font-weight:400;">
              Reason: ${escapeHtml(p.rejection_reason || '-')}<br>
              Cancelled: ${formatDate(p.approved_at)}${p.approved_by_name ? ' by ' + escapeHtml(p.approved_by_name) : ''}<br>
              ${p.previous_status ? 'Previous status: ' + formatStatus(p.previous_status) : ''}
            </p>
          </div>
        ` : ''}

        <div>
          <p class="modal-label">PROJECT TIMELINE / MILESTONES</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${(p.milestones || []).length ? p.milestones.map(m => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;">
                <span style="color:${m.completed ? '#22c55e' : '#94a3b8'};">${m.completed ? '✓' : '○'}</span>
                <span>${escapeHtml(m.title)}</span>
                <span style="margin-left:auto;color:#94a3b8;">${formatDate(m.due_date)}</span>
              </div>
            `).join('') : '<p class="empty-state">No milestones on file.</p>'}
          </div>
        </div>

        <div>
          <p class="modal-label">INSPECTION HISTORY</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${(p.inspection_history || []).length ? p.inspection_history.map(i => `
              <div style="font-size:.8rem;display:flex;justify-content:space-between;gap:8px;">
                <span>${formatDate(i.inspection_date)} — ${formatStatus(i.recommendation)} (${i.actual_progress_percent}%)</span>
              </div>
            `).join('') : '<p class="empty-state">No inspections on file.</p>'}
          </div>
        </div>

        <div>
          <p class="modal-label">PROGRESS HISTORY</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${(p.progress_history || []).length ? p.progress_history.map(r => `
              <div style="font-size:.8rem;">${formatDate(r.report_date)} — ${r.progress_percent}%: ${escapeHtml(r.accomplishments || '')}</div>
            `).join('') : '<p class="empty-state">No progress reports on file.</p>'}
          </div>
        </div>

        <div>
          <p class="modal-label">SUPPORTING DOCUMENTS</p>
          <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
            ${docs.length ? docs.map(d => `
              <div style="display:flex;align-items:center;gap:8px;font-size:.8rem;">
                <a href="${window.BASE_PATH || ''}${d.file_path}" target="_blank" rel="noopener">${escapeHtml(d.title)}</a>
                <span style="color:#94a3b8;">${escapeHtml(d.document_type)}</span>
              </div>
            `).join('') : '<p class="empty-state">No documents attached.</p>'}
          </div>
        </div>

        <div>
          <p class="modal-label">PROJECT PHOTOS</p>
          ${photos.length ? `
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:6px;margin-top:6px;">
              ${photos.map(ph => `<a href="${window.BASE_PATH || ''}${ph.file_path}" target="_blank" rel="noopener"><img src="${window.BASE_PATH || ''}${ph.file_path}" alt="${escapeHtml(ph.title || '')}" style="width:100%;height:70px;object-fit:cover;border-radius:6px;"></a>`).join('')}
            </div>
          ` : '<p class="empty-state">No photos on file.</p>'}
        </div>

        <div>
          <p class="modal-label">GIS LOCATION</p>
          <p class="modal-val" style="font-weight:400;">${p.latitude && p.longitude ? `${p.latitude}, ${p.longitude}` : 'No pinned location on file.'}</p>
        </div>

        <div>
          <p class="modal-label">CITIZEN FEEDBACK SUMMARY</p>
          <p class="modal-val" style="font-weight:400;">${fb.total > 0 ? `${fb.total} total (${fb.open_count || 0} open, ${fb.resolved_count || 0} resolved)` : 'No citizen feedback on file for this project.'}</p>
        </div>

        <div>
          <p class="modal-label">LATEST UPDATE</p>
          <p class="modal-val" style="font-weight:400;">${p.latest_update ? `${p.latest_update.action}${p.latest_update.details ? ' — ' + escapeHtml(p.latest_update.details) : ''} (${formatDate(p.latest_update.created_at)})` : 'No workflow history on file.'}</p>
        </div>
      </div>
    `);
  } catch {
    toast('Failed to load project details', 'error');
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
    <div id="assignmentTable" class="table-card"></div>
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
   CALENDAR & SCHEDULE
   Reads from api/calendar.php (frozen contract: action=events for the
   list, action=event_detail for a single event's extra fields,
   action=create_meeting / action=delete_meeting for the only two
   writeable operations admins get here — everything else is read-only,
   sourced from other modules' own tables).
   ============================================================ */
let calendarState = {
  view: 'month',
  currentDate: new Date(),
  events: [],
  visibleTypes: new Set(['milestone', 'inspection', 'payment', 'deadline', 'meeting', 'hope_review', 'bac_activity']),
};

// Legend order requested by the spec: Inspections, Milestones, Payment Due
// Dates, Project Deadlines, Meetings, HOPE Reviews, BAC Activities.
const CAL_TYPE_ORDER = ['inspection', 'milestone', 'payment', 'deadline', 'meeting', 'hope_review', 'bac_activity'];
const CAL_TYPE_LABELS = {
  inspection: 'Inspections',
  milestone: 'Milestones',
  payment: 'Payment Due Dates',
  deadline: 'Project Deadlines',
  meeting: 'Meetings',
  hope_review: 'City Mayor Reviews',
  bac_activity: 'BAC Activities',
};
const CAL_MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const CAL_DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function calPad2(n) { return String(n).padStart(2, '0'); }
function calToISODate(d) { return `${d.getFullYear()}-${calPad2(d.getMonth() + 1)}-${calPad2(d.getDate())}`; }
function calStartOfWeek(d) {
  const r = new Date(d);
  r.setHours(0, 0, 0, 0);
  r.setDate(r.getDate() - r.getDay());
  return r;
}
function calEndOfWeek(d) {
  const r = calStartOfWeek(d);
  r.setDate(r.getDate() + 6);
  return r;
}
// Event ids from the API are always "{type}-{source_row_id}" — recover the
// bare row id for the event_detail/delete_meeting calls, which take it alone.
function calSourceId(ev) { return String(ev.id).slice(ev.type.length + 1); }

function calendarRangeForView() {
  const cur = calendarState.currentDate;
  if (calendarState.view === 'day') {
    return { start: calToISODate(cur), end: calToISODate(cur) };
  }
  if (calendarState.view === 'week') {
    return { start: calToISODate(calStartOfWeek(cur)), end: calToISODate(calEndOfWeek(cur)) };
  }
  // Month view renders a full 7xN grid including leading/trailing days from
  // neighboring months, so fetch that whole span rather than just the 1st-to-last.
  const firstOfMonth = new Date(cur.getFullYear(), cur.getMonth(), 1);
  const lastOfMonth = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
  return { start: calToISODate(calStartOfWeek(firstOfMonth)), end: calToISODate(calEndOfWeek(lastOfMonth)) };
}

function calendarStep(direction) {
  const d = new Date(calendarState.currentDate);
  if (calendarState.view === 'month') d.setMonth(d.getMonth() + direction);
  else if (calendarState.view === 'week') d.setDate(d.getDate() + direction * 7);
  else d.setDate(d.getDate() + direction);
  calendarState.currentDate = d;
  refreshCalendar();
}

function calendarEventsByDate() {
  const map = {};
  (calendarState.events || []).forEach(ev => { (map[ev.date] ||= []).push(ev); });
  return map;
}

function calendarEventSort(a, b) {
  if (a.time && b.time) return a.time.localeCompare(b.time);
  if (a.time && !b.time) return -1;
  if (!a.time && b.time) return 1;
  return String(a.title || '').localeCompare(String(b.title || ''));
}

function calendarPillHtml(ev, block = false) {
  const title = escapeHtml(ev.title);
  const timePrefix = block && ev.time ? `${escapeHtml(ev.time)} &middot; ` : '';
  return `
    <button type="button" class="cal-pill ${block ? 'cal-pill-block' : ''} cal-badge-${ev.type} ${ev.is_flagged ? 'cal-flagged' : ''}"
      data-type="${ev.type}" data-id="${calSourceId(ev)}" title="${title}">
      ${ev.is_flagged ? '<span class="cal-flag-icon">&#9888;</span> ' : ''}${timePrefix}${title}
    </button>
  `;
}

async function loadCalendarSchedulePage() {
  const container = document.getElementById('page-calendar-schedule');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Calendar &amp; Schedule</h2>
      <button class="btn-primary" onclick="openNewMeetingModal()">+ New Meeting</button>
    </div>

    <div class="cal-toolbar">
      <div class="cal-view-switch" id="calViewSwitch">
        ${['month', 'week', 'day'].map(v => `
          <button type="button" class="cal-view-btn ${calendarState.view === v ? 'active' : ''}" data-view="${v}">${v.charAt(0).toUpperCase() + v.slice(1)}</button>
        `).join('')}
      </div>
      <div class="cal-nav">
        <button type="button" class="btn-secondary btn-compact" id="calPrevBtn">&laquo; Prev</button>
        <button type="button" class="btn-secondary btn-compact" id="calTodayBtn">Today</button>
        <button type="button" class="btn-secondary btn-compact" id="calNextBtn">Next &raquo;</button>
        <span class="cal-range-label" id="calRangeLabel"></span>
      </div>
    </div>

    <div class="cal-legend" id="calLegend">
      ${CAL_TYPE_ORDER.map(type => `
        <button type="button" class="cal-legend-chip ${calendarState.visibleTypes.has(type) ? '' : 'off'}" data-type="${type}">
          <span class="cal-legend-dot cal-badge-${type}"></span>
          <span>${CAL_TYPE_LABELS[type]}</span>
        </button>
      `).join('')}
    </div>

    <div class="table-card cal-grid-card">
      <div id="calendarGrid" class="cal-grid-container"></div>
    </div>
  `;

  container.querySelectorAll('#calViewSwitch .cal-view-btn').forEach(btn => {
    btn.addEventListener('click', () => { calendarState.view = btn.dataset.view; refreshCalendar(); });
  });
  document.getElementById('calPrevBtn').addEventListener('click', () => calendarStep(-1));
  document.getElementById('calNextBtn').addEventListener('click', () => calendarStep(1));
  document.getElementById('calTodayBtn').addEventListener('click', () => { calendarState.currentDate = new Date(); refreshCalendar(); });
  container.querySelectorAll('#calLegend .cal-legend-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const type = chip.dataset.type;
      if (calendarState.visibleTypes.has(type)) calendarState.visibleTypes.delete(type);
      else calendarState.visibleTypes.add(type);
      chip.classList.toggle('off');
      renderCalendarCurrentView();
    });
  });

  refreshCalendar();
}

function updateCalRangeLabel() {
  const el = document.getElementById('calRangeLabel');
  if (!el) return;
  const cur = calendarState.currentDate;
  if (calendarState.view === 'month') {
    el.textContent = `${CAL_MONTH_NAMES[cur.getMonth()]} ${cur.getFullYear()}`;
  } else if (calendarState.view === 'week') {
    const s = calStartOfWeek(cur), e = calEndOfWeek(cur);
    el.textContent = `${CAL_MONTH_NAMES[s.getMonth()]} ${s.getDate()} – ${CAL_MONTH_NAMES[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;
  } else {
    el.textContent = `${CAL_MONTH_NAMES[cur.getMonth()]} ${cur.getDate()}, ${cur.getFullYear()}`;
  }
}

function renderCalendarCurrentView() {
  if (calendarState.view === 'week') renderWeekView();
  else if (calendarState.view === 'day') renderDayView();
  else renderMonthView();
}

async function refreshCalendar() {
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;

  updateCalRangeLabel();
  document.querySelectorAll('#calViewSwitch .cal-view-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.view === calendarState.view);
  });

  const { start, end } = calendarRangeForView();
  setLoading(grid, true);
  try {
    const res = await get(API.calendar, { action: 'events', start, end });
    calendarState.events = (res && res.events) || [];
    renderCalendarCurrentView();
  } catch (e) {
    grid.innerHTML = '<p class="empty-state">Failed to load calendar events.</p>';
    toast('Failed to load calendar events', 'error');
    console.error(e);
  } finally {
    setLoading(grid, false);
  }
}

function renderMonthView() {
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;

  const cur = calendarState.currentDate;
  const firstOfMonth = new Date(cur.getFullYear(), cur.getMonth(), 1);
  const lastOfMonth = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
  const gridStart = calStartOfWeek(firstOfMonth);
  const gridEnd = calEndOfWeek(lastOfMonth);
  const eventsByDate = calendarEventsByDate();
  const todayIso = calToISODate(new Date());

  let html = '<div class="cal-month-grid">';
  CAL_DAY_NAMES.forEach(n => { html += `<div class="cal-month-headcell">${n}</div>`; });

  const d = new Date(gridStart);
  while (d <= gridEnd) {
    const iso = calToISODate(d);
    const inMonth = d.getMonth() === cur.getMonth();
    const dayEvents = (eventsByDate[iso] || []).filter(ev => calendarState.visibleTypes.has(ev.type));
    const visible = dayEvents.slice(0, 3);
    const extra = dayEvents.length - visible.length;

    html += `
      <div class="cal-month-cell ${inMonth ? '' : 'cal-dim'} ${iso === todayIso ? 'cal-today' : ''}">
        <div class="cal-month-daynum">${d.getDate()}</div>
        <div class="cal-month-pills">
          ${visible.map(ev => calendarPillHtml(ev)).join('')}
          ${extra > 0 ? `<button type="button" class="cal-more-link" data-date="${iso}">+${extra} more</button>` : ''}
        </div>
      </div>
    `;
    d.setDate(d.getDate() + 1);
  }
  html += '</div>';
  grid.innerHTML = html;

  grid.querySelectorAll('.cal-pill').forEach(pill => {
    pill.addEventListener('click', () => openCalendarEventModal(pill.dataset.type, pill.dataset.id));
  });
  grid.querySelectorAll('.cal-more-link').forEach(link => {
    link.addEventListener('click', () => openCalendarDayPopover(link.dataset.date));
  });
}

// "+N more" opens a simple day-list popover — reuses the existing modal
// engine rather than a bespoke floating panel, so positioning/scroll/escape
// handling all come for free and stay consistent with the rest of the app.
function openCalendarDayPopover(dateIso) {
  const dayEvents = (calendarEventsByDate()[dateIso] || [])
    .filter(ev => calendarState.visibleTypes.has(ev.type))
    .sort(calendarEventSort);

  openModal(`Events on ${formatDate(dateIso)}`, `
    <div class="cal-day-popover-list">
      ${dayEvents.length ? dayEvents.map(ev => `
        <button type="button" class="cal-day-popover-row" onclick="closeModal();openCalendarEventModal('${ev.type}','${calSourceId(ev)}')">
          <span class="cal-legend-dot cal-badge-${ev.type}"></span>
          <span class="cal-day-popover-title">${escapeHtml(ev.title)}</span>
          ${ev.time ? `<span class="cal-day-popover-time">${escapeHtml(ev.time)}</span>` : ''}
          ${ev.is_flagged ? '<span class="cal-flag-icon">&#9888;</span>' : ''}
        </button>
      `).join('') : '<p class="empty-state">No events.</p>'}
    </div>
  `);
}

function renderWeekView() {
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;

  const start = calStartOfWeek(calendarState.currentDate);
  const eventsByDate = calendarEventsByDate();
  const todayIso = calToISODate(new Date());

  let html = '<div class="cal-week-grid">';
  for (let i = 0; i < 7; i++) {
    const d = new Date(start);
    d.setDate(d.getDate() + i);
    const iso = calToISODate(d);
    const dayEvents = (eventsByDate[iso] || []).filter(ev => calendarState.visibleTypes.has(ev.type)).sort(calendarEventSort);

    html += `
      <div class="cal-week-col ${iso === todayIso ? 'cal-today' : ''}">
        <div class="cal-week-colhead">
          <span>${CAL_DAY_NAMES[i]}</span>
          <strong>${d.getDate()}</strong>
        </div>
        <div class="cal-week-pills">
          ${dayEvents.length ? dayEvents.map(ev => calendarPillHtml(ev, true)).join('') : '<p class="cal-week-empty">No events</p>'}
        </div>
      </div>
    `;
  }
  html += '</div>';
  grid.innerHTML = html;

  grid.querySelectorAll('.cal-pill').forEach(pill => {
    pill.addEventListener('click', () => openCalendarEventModal(pill.dataset.type, pill.dataset.id));
  });
}

function renderDayView() {
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;

  const iso = calToISODate(calendarState.currentDate);
  const dayEvents = (calendarEventsByDate()[iso] || [])
    .filter(ev => calendarState.visibleTypes.has(ev.type))
    .sort(calendarEventSort);

  grid.innerHTML = `
    <div class="cal-day-list">
      ${dayEvents.length ? dayEvents.map(ev => `
        <div class="cal-day-row ${ev.is_flagged ? 'cal-flagged' : ''}" data-type="${ev.type}" data-id="${calSourceId(ev)}">
          <span class="cal-day-row-swatch cal-badge-${ev.type}"></span>
          <span class="cal-day-row-time">${ev.time ? escapeHtml(ev.time) : 'All day'}</span>
          <span class="cal-day-row-title">${escapeHtml(ev.title)}</span>
          <span class="cal-day-row-project">${ev.project_name ? escapeHtml(ev.project_name) : '—'}</span>
          <span class="cal-day-row-status">${escapeHtml(ev.status_label || '')}</span>
          <span class="cal-day-row-flag">${ev.is_flagged ? '<span class="cal-flag-icon">&#9888; Flagged</span>' : ''}</span>
        </div>
      `).join('') : '<p class="empty-state">No events scheduled for this day.</p>'}
    </div>
  `;

  grid.querySelectorAll('.cal-day-row').forEach(row => {
    row.addEventListener('click', () => openCalendarEventModal(row.dataset.type, row.dataset.id));
  });
}

function calendarEventDetailHtml(ev) {
  const common = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
      <div><p class="modal-label">TYPE</p><p class="modal-val">${escapeHtml(CAL_TYPE_LABELS[ev.type] || formatStatus(ev.type))}</p></div>
      <div><p class="modal-label">STATUS</p><p class="modal-val">${ev.is_flagged ? '<span class="cal-flag-badge">&#9888; ' + escapeHtml(ev.status_label || '') + '</span>' : escapeHtml(ev.status_label || '-')}</p></div>
      <div><p class="modal-label">DATE</p><p class="modal-val">${formatDate(ev.date)}</p></div>
      <div><p class="modal-label">TIME</p><p class="modal-val">${ev.time ? escapeHtml(ev.time) : 'All day'}</p></div>
      <div><p class="modal-label">PROJECT</p><p class="modal-val">${ev.project_name ? escapeHtml(ev.project_name) : '—'}</p></div>
    </div>
  `;

  let extra = '';
  if (ev.type === 'milestone') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">COMPLETED</p><p class="modal-val">${ev.completed ? 'Yes' : 'No'}</p></div>
        <div><p class="modal-label">DUE DATE</p><p class="modal-val">${formatDate(ev.due_date)}</p></div>
      </div>
    `;
  } else if (ev.type === 'inspection') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">ENGINEER</p><p class="modal-val">${escapeHtml(ev.engineer_name || '-')}</p></div>
        <div><p class="modal-label">RECOMMENDATION</p><p class="modal-val">${escapeHtml(formatStatus(ev.recommendation || '-'))}</p></div>
        <div><p class="modal-label">ACTUAL PROGRESS</p><p class="modal-val">${Number(ev.actual_progress_percent || 0)}%</p></div>
      </div>
      <div><p class="modal-label">FINDINGS</p><p class="modal-val" style="font-weight:400;">${escapeHtml(ev.findings || 'No findings recorded.')}</p></div>
    `;
  } else if (ev.type === 'payment') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">BILLING NO.</p><p class="modal-val">${escapeHtml(ev.billing_no || '-')}</p></div>
        <div><p class="modal-label">AMOUNT REQUESTED</p><p class="modal-val">${formatMoney(ev.requested_amount)}</p></div>
        <div><p class="modal-label">STATUS</p><p class="modal-val">${escapeHtml(formatStatus(ev.status || '-'))}</p></div>
        <div><p class="modal-label">SUBMITTED</p><p class="modal-val">${formatDateTime(ev.submitted_at)}</p></div>
        <div><p class="modal-label">DAYS PENDING</p><p class="modal-val">${Number(ev.days_pending || 0)}</p></div>
      </div>
    `;
  } else if (ev.type === 'deadline') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">START DATE</p><p class="modal-val">${formatDate(ev.start_date)}</p></div>
        <div><p class="modal-label">END DATE</p><p class="modal-val">${formatDate(ev.end_date)}</p></div>
        <div><p class="modal-label">PROGRESS</p><p class="modal-val">${Number(ev.progress || 0)}%</p></div>
        <div><p class="modal-label">STATUS</p><p class="modal-val">${escapeHtml(formatStatus(ev.status || '-'))}</p></div>
      </div>
    `;
  } else if (ev.type === 'meeting') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">MEETING DATE</p><p class="modal-val">${formatDate(ev.meeting_date)}</p></div>
        <div><p class="modal-label">MEETING TIME</p><p class="modal-val">${ev.meeting_time ? escapeHtml(ev.meeting_time) : 'Not set'}</p></div>
        <div><p class="modal-label">LOCATION</p><p class="modal-val">${escapeHtml(ev.location || '-')}</p></div>
        <div><p class="modal-label">CREATED BY</p><p class="modal-val">${escapeHtml(ev.created_by_name || '-')}</p></div>
      </div>
      <div><p class="modal-label">DESCRIPTION</p><p class="modal-val" style="font-weight:400;">${escapeHtml(ev.description || 'No description.')}</p></div>
      <div class="form-actions" style="border-top:none;padding-top:0;justify-content:flex-start;">
        <button type="button" class="btn-secondary" onclick="deleteCalendarMeeting('${calSourceId(ev)}')">Delete Meeting</button>
      </div>
    `;
  } else if (ev.type === 'hope_review') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">SUBTYPE</p><p class="modal-val">${escapeHtml(formatStatus(ev.subtype || '-'))}</p></div>
        <div><p class="modal-label">DECISION STATUS</p><p class="modal-val">${escapeHtml(formatStatus(ev.decision_status || '-'))}</p></div>
        <div><p class="modal-label">DECIDED BY</p><p class="modal-val">${escapeHtml(ev.decided_by_name || '-')}</p></div>
      </div>
      <div><p class="modal-label">REMARKS</p><p class="modal-val" style="font-weight:400;">${escapeHtml(ev.remarks || 'No remarks.')}</p></div>
    `;
  } else if (ev.type === 'bac_activity') {
    extra = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div><p class="modal-label">SUBTYPE</p><p class="modal-val">${escapeHtml(formatStatus(ev.subtype || '-'))}</p></div>
        <div><p class="modal-label">REFERENCE NO.</p><p class="modal-val">${escapeHtml(ev.reference_no || '-')}</p></div>
      </div>
      <div><p class="modal-label">NOTES</p><p class="modal-val" style="font-weight:400;">${escapeHtml(ev.extra_notes || 'No additional notes.')}</p></div>
    `;
  }

  return `<div style="display:flex;flex-direction:column;gap:14px;">${common}${extra}</div>`;
}

async function openCalendarEventModal(type, id) {
  try {
    const res = await get(API.calendar, { action: 'event_detail', type, id });
    const ev = res && res.event;
    if (!ev) { toast('Event not found', 'error'); return; }
    openModal(ev.title, calendarEventDetailHtml(ev));
  } catch (e) {
    toast('Failed to load event details', 'error');
    console.error(e);
  }
}

async function openNewMeetingModal() {
  let projects = [];
  try {
    const d = await get(API.projects, {});
    projects = d.data || [];
  } catch { /* project dropdown just stays empty */ }

  openModal('New Meeting', `
    <form id="newMeetingForm" onsubmit="submitNewMeetingForm(event)">
      <div class="form-grid">
        <div class="form-group">
          <label>Title *</label>
          <input name="title" class="form-input" required />
        </div>
        <div class="form-group">
          <label>Project</label>
          <select name="project_id" class="form-input">
            <option value="">— None —</option>
            ${projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Meeting Date *</label>
          <input name="meeting_date" type="date" class="form-input" required value="${calToISODate(new Date())}" />
        </div>
        <div class="form-group">
          <label>Meeting Time</label>
          <input name="meeting_time" type="time" class="form-input" />
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Location</label>
          <input name="location" class="form-input" placeholder="e.g. City Engineering Office, 3F Conference Room" />
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-input" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Create Meeting</button>
      </div>
    </form>
  `);
}

async function submitNewMeetingForm(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = {
    title: fd.get('title') || '',
    description: fd.get('description') || '',
    meeting_date: fd.get('meeting_date') || '',
    meeting_time: fd.get('meeting_time') || '',
    location: fd.get('location') || '',
    project_id: fd.get('project_id') ? Number(fd.get('project_id')) : null,
  };
  try {
    const res = await postAction(API.calendar, 'create_meeting', body);
    if (!res || !res.success) { toast((res && res.message) || 'Failed to create meeting', 'error'); return; }
    toast('Meeting created!');
    closeModal();
    refreshCalendar();
  } catch (err) {
    toast('Failed to create meeting', 'error');
    console.error(err);
  }
}

async function deleteCalendarMeeting(id) {
  if (!confirm('Delete this meeting?')) return;
  try {
    const res = await postAction(API.calendar, 'delete_meeting', { id: Number(id) });
    if (!res || !res.success) { toast((res && res.message) || 'Failed to delete meeting', 'error'); return; }
    toast('Meeting deleted');
    closeModal();
    refreshCalendar();
  } catch (err) {
    toast('Failed to delete meeting', 'error');
    console.error(err);
  }
}

/* ============================================================
   CONTRACTOR PERFORMANCE DASHBOARD
   Reads from api/contractor-performance.php (frozen contract:
   action=leaderboard for the ranked table + chart, action=detail for one
   contractor's full score breakdown + 12-month trend). Reuses the same
   85/70 risk-label thresholds BAC's own evaluation view already uses.
   ============================================================ */
let contractorPerfChartInst = null;
let contractorPerfTrendChartInst = null;

const PERF_RISK_COLORS = { low: '#22c55e', medium: '#f97316', high: '#ef4444' };
const PERF_RISK_LABELS = { low: 'Low Risk', medium: 'Medium Risk', high: 'High Risk' };
function perfRiskBadge(label) {
  const color = PERF_RISK_COLORS[label] || PERF_RISK_COLORS.high;
  return `<span class="badge" style="background:${color}22;color:${color};">${PERF_RISK_LABELS[label] || label}</span>`;
}
function perfPct(value, suffix = '%') {
  return value === null || value === undefined ? '—' : `${value}${suffix}`;
}

async function loadContractorPerformancePage() {
  const container = document.getElementById('page-contractor-performance');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Contractor Performance Dashboard</h2>
    </div>
    <div class="chart-card chart-main">
      <div class="chart-header"><h2 class="chart-title">Performance Score Ranking</h2></div>
      <div class="chart-body"><canvas id="contractorPerfChart"></canvas></div>
    </div>
    <div id="contractorPerfTable" class="table-card"></div>
  `;

  setLoading(container, true);
  try {
    const res = await get(API.contractorPerformance, { action: 'leaderboard' });
    const rows = (res && res.contractors) || [];
    renderContractorPerfChart(rows);
    renderContractorPerfTable(rows);
  } catch (e) {
    document.getElementById('contractorPerfTable').innerHTML = '<p class="empty-state">Failed to load contractor performance data.</p>';
    toast('Failed to load contractor performance data', 'error');
    console.error(e);
  } finally {
    setLoading(container, false);
  }
}

function renderContractorPerfChart(rows) {
  const ctx = document.getElementById('contractorPerfChart')?.getContext('2d');
  if (!ctx) return;
  if (contractorPerfChartInst) contractorPerfChartInst.destroy();

  contractorPerfChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.name),
      datasets: [{
        label: 'Score',
        data: rows.map(r => r.score),
        backgroundColor: rows.map(r => PERF_RISK_COLORS[r.risk_label] || PERF_RISK_COLORS.high),
        borderRadius: 6,
        maxBarThickness: 42,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` Score: ${c.raw}/100` } },
      },
      scales: { y: { min: 0, max: 100, ticks: { stepSize: 25 } } },
    },
  });
}

function renderContractorPerfTable(rows) {
  const wrap = document.getElementById('contractorPerfTable');
  if (!wrap) return;

  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No contractors on file yet.</p>';
    return;
  }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr>
          <th>Rank</th><th>Contractor</th><th>Score</th><th>Risk</th>
          <th>Completed</th><th>Delayed</th><th>Avg Completion</th>
          <th>Inspections Approved</th><th>Feedback Positive</th><th>Budget Perf.</th><th></th>
        </tr>
      </thead>
      <tbody>
        ${rows.map(r => `
          <tr class="clickable-row" onclick="openContractorPerformanceModal(${r.contractor_id})">
            <td>#${r.rank}</td>
            <td><strong>${escapeHtml(r.name)}</strong>${r.is_blacklisted ? ' <span class="badge status-cancelled">Blacklisted</span>' : ''}</td>
            <td>${r.score}/100</td>
            <td>${perfRiskBadge(r.risk_label)}</td>
            <td>${r.projects_completed}</td>
            <td>${r.projects_delayed}</td>
            <td>${r.avg_completion_days !== null ? r.avg_completion_days + ' days' : '—'}</td>
            <td>${perfPct(r.inspection_pct_approved)}</td>
            <td>${perfPct(r.feedback_positive_pct)}</td>
            <td>${perfPct(r.budget_performance_pct)}</td>
            <td><button type="button" class="btn-secondary btn-compact" onclick="event.stopPropagation();openContractorPerformanceModal(${r.contractor_id})">View</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function openContractorPerformanceModal(id) {
  try {
    const res = await get(API.contractorPerformance, { action: 'detail', contractor_id: id });
    if (!res || !res.success) { toast((res && res.message) || 'Failed to load contractor detail', 'error'); return; }
    const c = res.contractor;

    openModal(`#${c.rank} ${escapeHtml(c.name)} — Performance`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span class="badge" style="background:#3b82f622;color:#3b82f6;font-size:.95rem;">${c.score}/100</span>
          ${perfRiskBadge(c.risk_label)}
          ${c.is_blacklisted ? '<span class="badge status-cancelled">Blacklisted</span>' : ''}
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
          <div><p class="modal-label">PROJECTS COMPLETED</p><p class="modal-val">${c.projects_completed}</p></div>
          <div><p class="modal-label">PROJECTS DELAYED</p><p class="modal-val">${c.projects_delayed}</p></div>
          <div><p class="modal-label">AVG COMPLETION</p><p class="modal-val">${c.avg_completion_days !== null ? c.avg_completion_days + ' days' : '—'}</p></div>
          <div><p class="modal-label">INSPECTIONS</p><p class="modal-val">${c.total_inspections} <small style="font-weight:400;color:#94a3b8;">(${perfPct(c.inspection_pct_approved)} approved)</small></p></div>
          <div><p class="modal-label">CITIZEN FEEDBACK</p><p class="modal-val">${perfPct(c.feedback_positive_pct)} positive <small style="font-weight:400;color:#94a3b8;">(${c.feedback_commendation_count} commendations, ${c.feedback_complaint_count} complaints)</small></p></div>
          <div><p class="modal-label">BUDGET PERFORMANCE</p><p class="modal-val">${perfPct(c.budget_performance_pct)} of budget spent</p></div>
          <div><p class="modal-label">CREDIBILITY SCORE</p><p class="modal-val">${Number(c.credibility_score).toFixed(2)}/5.00</p></div>
          <div><p class="modal-label">OPEN ISSUES</p><p class="modal-val">${c.open_issue_count}</p></div>
          <div><p class="modal-label">DELAY REPORTS</p><p class="modal-val">${c.delay_report_count}</p></div>
        </div>

        <div>
          <p class="modal-label">SCORE BREAKDOWN <small style="font-weight:400;color:#94a3b8;">— advisory only</small></p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px;font-size:.8rem;color:#64748b;">
            <span>Completion: ${c.components.completion.earned ?? '—'}/${c.components.completion.weight}</span>
            <span>Delay: ${c.components.delay.earned ?? '—'}/${c.components.delay.weight}</span>
            <span>Issues: ${c.components.issues.earned ?? '—'}/${c.components.issues.weight}</span>
            <span>Financial: ${c.components.financial.earned ?? '—'}/${c.components.financial.weight}</span>
          </div>
        </div>

        <div class="chart-card chart-main" style="box-shadow:none;">
          <div class="chart-header"><h2 class="chart-title">12-Month Trend</h2></div>
          <div class="chart-body"><canvas id="contractorPerfTrendChart"></canvas></div>
        </div>
      </div>
    `);

    renderContractorPerfTrendChart(c.trend);
  } catch (err) {
    toast('Failed to load contractor detail', 'error');
    console.error(err);
  }
}

function renderContractorPerfTrendChart(trend) {
  const ctx = document.getElementById('contractorPerfTrendChart')?.getContext('2d');
  if (!ctx) return;
  if (contractorPerfTrendChartInst) contractorPerfTrendChartInst.destroy();

  contractorPerfTrendChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels: trend.months,
      datasets: [
        { label: 'Completions', data: trend.completions, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
        { label: 'Delay Reports', data: trend.delay_reports, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.06)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
        { label: 'Flagged Expenses', data: trend.flagged_expenses, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.06)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 700 },
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
}

/* ============================================================
   ENGINEER PERFORMANCE DASHBOARD
   Reads from api/engineer-performance.php (frozen contract:
   action=leaderboard for the ranked table + chart, action=detail for one
   engineer's full score breakdown + 12-month trend). Reuses the same
   85/70 risk-label thresholds as the Contractor Performance Dashboard.
   ============================================================ */
let engineerPerfChartInst = null;
let engineerPerfTrendChartInst = null;

async function loadEngineerPerformancePage() {
  const container = document.getElementById('page-engineer-performance');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Engineer Performance Dashboard</h2>
    </div>
    <div class="chart-card chart-main">
      <div class="chart-header"><h2 class="chart-title">Performance Score Ranking</h2></div>
      <div class="chart-body"><canvas id="engineerPerfChart"></canvas></div>
    </div>
    <div id="engineerPerfTable" class="table-card"></div>
  `;

  setLoading(container, true);
  try {
    const res = await get(API.engineerPerformance, { action: 'leaderboard' });
    const rows = (res && res.engineers) || [];
    renderEngineerPerfChart(rows);
    renderEngineerPerfTable(rows);
  } catch (e) {
    document.getElementById('engineerPerfTable').innerHTML = '<p class="empty-state">Failed to load engineer performance data.</p>';
    toast('Failed to load engineer performance data', 'error');
    console.error(e);
  } finally {
    setLoading(container, false);
  }
}

function renderEngineerPerfChart(rows) {
  const ctx = document.getElementById('engineerPerfChart')?.getContext('2d');
  if (!ctx) return;
  if (engineerPerfChartInst) engineerPerfChartInst.destroy();

  engineerPerfChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.name),
      datasets: [{
        label: 'Score',
        data: rows.map(r => r.score),
        backgroundColor: rows.map(r => PERF_RISK_COLORS[r.risk_label] || PERF_RISK_COLORS.high),
        borderRadius: 6,
        maxBarThickness: 42,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` Score: ${c.raw}/100` } },
      },
      scales: { y: { min: 0, max: 100, ticks: { stepSize: 25 } } },
    },
  });
}

function renderEngineerPerfTable(rows) {
  const wrap = document.getElementById('engineerPerfTable');
  if (!wrap) return;

  if (!rows.length) {
    wrap.innerHTML = '<p class="empty-state">No engineers on file yet.</p>';
    return;
  }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr>
          <th>Rank</th><th>Engineer</th><th>Score</th><th>Risk</th>
          <th>Total Inspections</th><th>Completed</th><th>Pending</th>
          <th>Avg Inspection Time</th><th>Projects Assigned</th><th>Reports Submitted</th><th></th>
        </tr>
      </thead>
      <tbody>
        ${rows.map(r => `
          <tr class="clickable-row" onclick="openEngineerPerformanceModal(${r.engineer_id})">
            <td>#${r.rank}</td>
            <td><strong>${escapeHtml(r.name)}</strong>${!r.is_active ? ' <span class="badge status-cancelled">Inactive</span>' : ''}</td>
            <td>${r.score}/100</td>
            <td>${perfRiskBadge(r.risk_label)}</td>
            <td>${r.total_inspections}</td>
            <td>${r.completed_inspections}</td>
            <td>${r.pending_inspections}</td>
            <td>${r.avg_inspection_days !== null ? r.avg_inspection_days + ' days' : '—'}</td>
            <td>${r.projects_assigned}</td>
            <td>${r.reports_submitted}</td>
            <td><button type="button" class="btn-secondary btn-compact" onclick="event.stopPropagation();openEngineerPerformanceModal(${r.engineer_id})">View</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function openEngineerPerformanceModal(id) {
  try {
    const res = await get(API.engineerPerformance, { action: 'detail', engineer_id: id });
    if (!res || !res.success) { toast((res && res.message) || 'Failed to load engineer detail', 'error'); return; }
    const e = res.engineer;

    openModal(`#${e.rank} ${escapeHtml(e.name)} — Performance`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span class="badge" style="background:#3b82f622;color:#3b82f6;font-size:.95rem;">${e.score}/100</span>
          ${perfRiskBadge(e.risk_label)}
          ${!e.is_active ? '<span class="badge status-cancelled">Inactive</span>' : ''}
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
          <div><p class="modal-label">TOTAL INSPECTIONS</p><p class="modal-val">${e.total_inspections}</p></div>
          <div><p class="modal-label">COMPLETED</p><p class="modal-val">${e.completed_inspections}</p></div>
          <div><p class="modal-label">PENDING</p><p class="modal-val">${e.pending_inspections}</p></div>
          <div><p class="modal-label">AVG INSPECTION TIME</p><p class="modal-val">${e.avg_inspection_days !== null ? e.avg_inspection_days + ' days' : '—'}</p></div>
          <div><p class="modal-label">PROJECTS ASSIGNED</p><p class="modal-val">${e.projects_assigned}</p></div>
          <div><p class="modal-label">REPORTS SUBMITTED</p><p class="modal-val">${e.reports_submitted}</p></div>
        </div>

        <div>
          <p class="modal-label">SCORE BREAKDOWN <small style="font-weight:400;color:#94a3b8;">— advisory only</small></p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px;font-size:.8rem;color:#64748b;">
            <span>Quality: ${e.components.quality.earned ?? '—'}/${e.components.quality.weight}</span>
            <span>Responsiveness: ${e.components.responsiveness.earned ?? '—'}/${e.components.responsiveness.weight}</span>
            <span>Activity: ${e.components.activity.earned ?? '—'}/${e.components.activity.weight}</span>
            <span>Delay Impact: ${e.components.delay_impact.earned ?? '—'}/${e.components.delay_impact.weight}</span>
          </div>
        </div>

        <div class="chart-card chart-main" style="box-shadow:none;">
          <div class="chart-header"><h2 class="chart-title">12-Month Trend</h2></div>
          <div class="chart-body"><canvas id="engineerPerfTrendChart"></canvas></div>
        </div>
      </div>
    `);

    renderEngineerPerfTrendChart(e.trend);
  } catch (err) {
    toast('Failed to load engineer detail', 'error');
    console.error(err);
  }
}

function renderEngineerPerfTrendChart(trend) {
  const ctx = document.getElementById('engineerPerfTrendChart')?.getContext('2d');
  if (!ctx) return;
  if (engineerPerfTrendChartInst) engineerPerfTrendChartInst.destroy();

  engineerPerfTrendChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels: trend.months,
      datasets: [
        { label: 'Inspections Completed', data: trend.inspections_completed, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
        { label: 'Reports Submitted', data: trend.reports_submitted, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.06)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
        { label: 'Delay Reports', data: trend.delay_reports, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.06)', borderWidth: 2, tension: 0.35, fill: true, pointRadius: 3 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 700 },
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
}

/* ============================================================
   ANNOUNCEMENTS — Citizen-facing bulletin board (events, new-project
   spotlights, official notices/posters, general updates). Admin creates
   here via api/announcements.php; citizen/api/announcements.php serves
   only status='published' rows, read-only, on the citizen portal's own
   Announcements page.
   ============================================================ */
let announcementsState = { page: 1, search: '', category: '', status: '' };

const ANNOUNCEMENT_CATEGORY_LABELS = { event: 'Event', new_project: 'New Project', notice: 'Notice', general: 'General' };
const ANNOUNCEMENT_CATEGORY_BADGE = { event: 'badge-highprio', new_project: 'badge-resolved', notice: 'badge-urgent', general: 'badge-spike' };
const ANNOUNCEMENT_STATUS_BADGE = { draft: 'badge-spike', published: 'badge-resolved', archived: 'badge-urgent' };

async function loadAnnouncementsPage() {
  const container = document.getElementById('page-announcements');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Announcements</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Post events, new-project spotlights, official notices, or posters to the citizen portal.
        </p>
      </div>
      <button class="btn-primary" onclick="showAnnouncementForm()">+ New Announcement</button>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search announcements…" oninput="announcementsState.search=this.value;announcementsState.page=1;fetchAnnouncements()" />
      <select class="filter-select" onchange="announcementsState.category=this.value;announcementsState.page=1;fetchAnnouncements()">
        <option value="">All Categories</option>
        <option value="event">Event</option>
        <option value="new_project">New Project</option>
        <option value="notice">Notice</option>
        <option value="general">General</option>
      </select>
      <select class="filter-select" onchange="announcementsState.status=this.value;announcementsState.page=1;fetchAnnouncements()">
        <option value="">All Statuses</option>
        <option value="published">Published</option>
        <option value="draft">Draft</option>
        <option value="archived">Archived</option>
      </select>
    </div>
    <div id="announcementsTable" class="table-card"></div>
    <div id="announcementsPager" class="pager"></div>
  `;
  fetchAnnouncements();
}

async function fetchAnnouncements() {
  const wrap = document.getElementById('announcementsTable');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.announcements, { action: 'list', ...announcementsState });
    renderAnnouncementsTable(d.data || []);
    renderPager('announcementsPager', d.page, d.last_page, p => { announcementsState.page = p; fetchAnnouncements(); });
  } catch (e) {
    wrap.innerHTML = '<p class="empty-state">Failed to load announcements.</p>';
    console.error(e);
  } finally {
    setLoading(wrap, false);
  }
}

function renderAnnouncementsTable(rows) {
  const wrap = document.getElementById('announcementsTable');
  if (!wrap) return;
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No announcements yet.</p>'; return; }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th></th><th>Title</th><th>Category</th><th>Event</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(a => `
          <tr>
            <td>${a.poster_path
              ? `<img src="${(window.BASE_PATH || '') + a.poster_path}" alt="" class="announcement-thumb" />`
              : '<span class="announcement-thumb announcement-thumb-empty">—</span>'}
            </td>
            <td>
              ${a.is_pinned == 1 ? '<span title="Pinned">📌</span> ' : ''}<strong>${escapeHtml(a.title)}</strong>
              ${a.project_name ? `<br><small style="color:#94a3b8;">${escapeHtml(a.project_name)}</small>` : ''}
            </td>
            <td><span class="badge ${ANNOUNCEMENT_CATEGORY_BADGE[a.category] || 'badge-spike'}">${ANNOUNCEMENT_CATEGORY_LABELS[a.category] || a.category}</span></td>
            <td style="font-size:.78rem;">${a.category === 'event' && a.event_date ? formatDate(a.event_date) + (a.event_time ? ' ' + a.event_time.slice(0, 5) : '') : '—'}</td>
            <td><span class="badge ${ANNOUNCEMENT_STATUS_BADGE[a.status] || 'badge-spike'}">${formatStatus(a.status)}</span></td>
            <td style="font-size:.75rem;color:#94a3b8;">${formatDate(a.created_at)}</td>
            <td>
              <div class="action-btns">
                <button class="btn-secondary btn-compact" onclick="showAnnouncementForm(${a.id})">Edit</button>
                <button class="btn-icon btn-danger" title="Delete" onclick="deleteAnnouncement(${a.id})"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button>
              </div>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function showAnnouncementForm(id = null) {
  let a = null;
  if (id) {
    try { const res = await get(API.announcements, { action: 'detail', id }); a = res.announcement; } catch {}
  }
  let projects = [];
  try { const d = await get(API.projects, {}); projects = d.data || []; } catch {}

  openModal(id ? `Edit Announcement #${id}` : 'New Announcement', `
    <form id="announcementForm" onsubmit="submitAnnouncementForm(event, ${id})">
      <div class="form-group">
        <label>Title *</label>
        <input name="title" class="form-input" required value="${escapeHtml(a?.title || '')}" />
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Category *</label>
          <select name="category" id="annCategory" class="form-input" onchange="toggleAnnouncementEventFields(this.value)">
            <option value="general" ${a?.category === 'general' ? 'selected' : ''}>General</option>
            <option value="event" ${a?.category === 'event' ? 'selected' : ''}>Event</option>
            <option value="new_project" ${a?.category === 'new_project' ? 'selected' : ''}>New Project</option>
            <option value="notice" ${a?.category === 'notice' ? 'selected' : ''}>Notice</option>
          </select>
        </div>
        <div class="form-group">
          <label>Related Project</label>
          <select name="project_id" class="form-input">
            <option value="">— None —</option>
            ${projects.map(p => `<option value="${p.id}" ${String(a?.project_id || '') === String(p.id) ? 'selected' : ''}>${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Status *</label>
          <select name="status" class="form-input">
            <option value="published" ${!a || a.status === 'published' ? 'selected' : ''}>Published</option>
            <option value="draft" ${a?.status === 'draft' ? 'selected' : ''}>Draft</option>
            <option value="archived" ${a?.status === 'archived' ? 'selected' : ''}>Archived</option>
          </select>
        </div>
        <div class="form-group" style="display:flex;align-items:center;padding-top:22px;">
          <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
            <input type="checkbox" name="is_pinned" value="1" ${a?.is_pinned == 1 ? 'checked' : ''} />
            Pin to top of feed
          </label>
        </div>
      </div>
      <div id="annEventFields" class="form-grid" style="display:${a?.category === 'event' ? 'grid' : 'none'};">
        <div class="form-group">
          <label>Event Date</label>
          <input name="event_date" type="date" class="form-input" value="${a?.event_date || ''}" />
        </div>
        <div class="form-group">
          <label>Event Time</label>
          <input name="event_time" type="time" class="form-input" value="${a?.event_time ? a.event_time.slice(0, 5) : ''}" />
        </div>
        <div class="form-group" style="grid-column: span 2;">
          <label>Event Location</label>
          <input name="event_location" class="form-input" value="${escapeHtml(a?.event_location || '')}" />
        </div>
      </div>
      <div class="form-group" style="margin-top:8px;">
        <label>Body *</label>
        <textarea name="body" class="form-input" rows="5" required>${escapeHtml(a?.body || '')}</textarea>
      </div>
      <div class="form-group" style="margin-top:8px;">
        <label>Poster Image <small>(PNG/JPG, max 5MB, optional)</small></label>
        ${a?.poster_path ? `
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <img src="${(window.BASE_PATH || '') + a.poster_path}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);" />
            <label style="display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:500;cursor:pointer;">
              <input type="checkbox" name="remove_poster" value="1" /> Remove current poster
            </label>
          </div>
        ` : ''}
        <input type="file" name="poster" accept=".png,.jpg,.jpeg" class="form-input" />
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">${id ? 'Save Changes' : 'Post Announcement'}</button>
      </div>
    </form>
  `);
}

function toggleAnnouncementEventFields(category) {
  const el = document.getElementById('annEventFields');
  if (el) el.style.display = category === 'event' ? 'grid' : 'none';
}

async function submitAnnouncementForm(e, id) {
  e.preventDefault();
  try {
    const fd = new FormData(e.target);
    const url = id ? `${API.announcements}?action=update&id=${id}` : `${API.announcements}?action=create`;
    const res = await postForm(url, fd);
    if (!res.success) { toast(res.message || 'Failed to save announcement', 'error'); return; }
    toast(id ? 'Announcement updated!' : 'Announcement posted!');
    closeModal();
    fetchAnnouncements();
  } catch {
    toast('Something went wrong', 'error');
  }
}

async function deleteAnnouncement(id) {
  const confirmed = await showConfirm({
    title: 'Delete this announcement?',
    message: 'This removes it from the citizen portal immediately and cannot be undone.',
    confirmLabel: 'Delete',
    tone: 'danger',
  });
  if (!confirmed) return;

  try {
    const res = await fetch(`${API.announcements}?action=delete&id=${id}`, {
      method: 'POST',
      headers: { ...CSRF_HEADERS },
    }).then(r => r.json());
    if (!res.success) { toast(res.message || 'Delete failed', 'error'); return; }
    toast('Announcement deleted');
    fetchAnnouncements();
  } catch {
    toast('Delete failed', 'error');
  }
}

/* ============================================================
   PROJECT GALLERY (Admin — blueprint/gallery photo uploads)
   ============================================================ */
let galleryState = { page: 1, projectId: '' };
const GALLERY_TYPE_LABELS = { blueprint: 'Blueprint', render: 'Render', photo: 'Photo' };

async function loadProjectGalleryPage() {
  const container = document.getElementById('page-project-gallery');
  if (!container) return;

  let projects = [];
  try { const d = await get(API.projects, {}); projects = d.data || []; } catch {}
  window.galleryProjectsCache = projects;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Project Gallery</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Upload blueprints, renders, and reference photos citizens see on the dashboard. One photo per project can be set as the cover shown in the slideshow.
        </p>
      </div>
      <button class="btn-primary" onclick="showGalleryUploadForm()">+ Upload Photo</button>
    </div>
    <div class="filter-bar">
      <select class="filter-select" onchange="galleryState.projectId=this.value;galleryState.page=1;fetchGalleryPhotos()">
        <option value="">All Projects</option>
        ${projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
      </select>
    </div>
    <div id="galleryGrid"></div>
    <div id="galleryPager" class="pager"></div>
  `;
  fetchGalleryPhotos();
}

async function fetchGalleryPhotos() {
  const wrap = document.getElementById('galleryGrid');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.projectGallery, { action: 'list', project_id: galleryState.projectId, page: galleryState.page });
    renderGalleryGrid(d.data || []);
    renderPager('galleryPager', d.page, d.last_page, p => { galleryState.page = p; fetchGalleryPhotos(); });
  } catch (e) {
    wrap.innerHTML = '<p class="empty-state">Failed to load gallery photos.</p>';
    console.error(e);
  } finally {
    setLoading(wrap, false);
  }
}

function renderGalleryGrid(rows) {
  const wrap = document.getElementById('galleryGrid');
  if (!wrap) return;
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No gallery photos yet.</p>'; return; }

  wrap.innerHTML = `
    <div class="gallery-admin-grid">
      ${rows.map(g => `
        <div class="gallery-admin-card">
          <div class="gallery-admin-thumb">
            <img src="${(window.BASE_PATH || '') + g.file_path}" alt="" />
            ${Number(g.is_cover) === 1 ? '<span class="gallery-cover-badge">Cover</span>' : ''}
          </div>
          <div class="gallery-admin-body">
            <strong>${escapeHtml(g.title)}</strong>
            <span class="badge badge-spike">${GALLERY_TYPE_LABELS[g.photo_type] || g.photo_type}</span>
            <p style="font-size:.75rem;color:#94a3b8;margin:4px 0 0;">${escapeHtml(g.project_code)} — ${escapeHtml(g.project_name)}</p>
          </div>
          <div class="action-btns">
            ${Number(g.is_cover) !== 1 ? `<button class="btn-secondary btn-compact" onclick="setGalleryCover(${g.id})">Set as Cover</button>` : ''}
            <button class="btn-secondary btn-compact" onclick="showGalleryEditForm(${g.id})">Edit</button>
            <button class="btn-icon btn-danger" title="Delete" onclick="deleteGalleryPhoto(${g.id})"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

async function showGalleryUploadForm() {
  const projects = window.galleryProjectsCache || [];
  openModal('Upload Gallery Photo', `
    <form id="galleryUploadForm" onsubmit="submitGalleryUpload(event)" enctype="multipart/form-data">
      <div class="form-group">
        <label>Project *</label>
        <select name="project_id" class="form-input" required>
          <option value="">— Select —</option>
          ${projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_code)} — ${escapeHtml(p.name)}</option>`).join('')}
        </select>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Title *</label>
          <input name="title" class="form-input" required />
        </div>
        <div class="form-group">
          <label>Type *</label>
          <select name="photo_type" class="form-input">
            <option value="blueprint">Blueprint</option>
            <option value="render">Render</option>
            <option value="photo">Photo</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Caption</label>
        <textarea name="caption" class="form-input" rows="3"></textarea>
      </div>
      <div class="form-group" style="display:flex;align-items:center;">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
          <input type="checkbox" name="is_cover" value="1" />
          Set as cover photo (shown in citizen dashboard slideshow)
        </label>
      </div>
      <div class="form-group">
        <label>Photo * <small>(PNG/JPG, max 8MB)</small></label>
        <input type="file" name="photo" accept=".png,.jpg,.jpeg" class="form-input" required />
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Upload</button>
      </div>
    </form>
  `);
}

async function submitGalleryUpload(e) {
  e.preventDefault();
  try {
    const fd = new FormData(e.target);
    const res = await postForm(`${API.projectGallery}?action=upload`, fd);
    if (!res.success) { toast(res.message || 'Failed to upload photo', 'error'); return; }
    toast('Photo uploaded!');
    closeModal();
    fetchGalleryPhotos();
  } catch {
    toast('Something went wrong', 'error');
  }
}

async function showGalleryEditForm(id) {
  let g = null;
  try { const res = await get(API.projectGallery, { action: 'detail', id }); g = res.photo; } catch {}
  if (!g) { toast('Photo not found', 'error'); return; }

  openModal('Edit Gallery Photo', `
    <form id="galleryEditForm" onsubmit="submitGalleryEdit(event, ${id})">
      <div class="form-group">
        <label>Title *</label>
        <input name="title" class="form-input" required value="${escapeHtml(g.title || '')}" />
      </div>
      <div class="form-group">
        <label>Type *</label>
        <select name="photo_type" class="form-input">
          <option value="blueprint" ${g.photo_type === 'blueprint' ? 'selected' : ''}>Blueprint</option>
          <option value="render" ${g.photo_type === 'render' ? 'selected' : ''}>Render</option>
          <option value="photo" ${g.photo_type === 'photo' ? 'selected' : ''}>Photo</option>
        </select>
      </div>
      <div class="form-group">
        <label>Caption</label>
        <textarea name="caption" class="form-input" rows="3">${escapeHtml(g.caption || '')}</textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Save Changes</button>
      </div>
    </form>
  `);
}

async function submitGalleryEdit(e, id) {
  e.preventDefault();
  try {
    const fd = new FormData(e.target);
    const res = await postForm(`${API.projectGallery}?action=update&id=${id}`, fd);
    if (!res.success) { toast(res.message || 'Failed to save changes', 'error'); return; }
    toast('Photo updated!');
    closeModal();
    fetchGalleryPhotos();
  } catch {
    toast('Something went wrong', 'error');
  }
}

async function setGalleryCover(id) {
  const confirmed = await showConfirm({
    title: 'Set as cover photo?',
    message: 'This replaces the current cover photo shown in the citizen dashboard slideshow for this project.',
    confirmLabel: 'Set as Cover',
    tone: 'info',
  });
  if (!confirmed) return;

  try {
    const res = await fetch(`${API.projectGallery}?action=set_cover&id=${id}`, {
      method: 'POST',
      headers: { ...CSRF_HEADERS },
    }).then(r => r.json());
    if (!res.success) { toast(res.message || 'Failed to set cover', 'error'); return; }
    toast('Cover photo updated');
    fetchGalleryPhotos();
  } catch {
    toast('Failed to set cover', 'error');
  }
}

async function deleteGalleryPhoto(id) {
  const confirmed = await showConfirm({
    title: 'Delete this photo?',
    message: 'This removes it from the citizen portal immediately and cannot be undone.',
    confirmLabel: 'Delete',
    tone: 'danger',
  });
  if (!confirmed) return;

  try {
    const res = await fetch(`${API.projectGallery}?action=delete&id=${id}`, {
      method: 'POST',
      headers: { ...CSRF_HEADERS },
    }).then(r => r.json());
    if (!res.success) { toast(res.message || 'Delete failed', 'error'); return; }
    toast('Photo deleted');
    fetchGalleryPhotos();
  } catch {
    toast('Delete failed', 'error');
  }
}

/* ============================================================
   CITIZEN RATINGS (Admin — moderation queue. Approve/Reject/Flag/Archive
   only ever touch the row's status — api/project-ratings.php's UPDATE
   structurally excludes rating/comment from its SET list, so staff can
   never alter what a citizen actually said.)
   ============================================================ */
let ratingsState = { page: 1, search: '', project_id: '', status: 'pending' };

const RATING_STATUS_BADGE = { pending: 'badge-urgent', approved: 'badge-resolved', rejected: 'badge-overbudget', flagged: 'badge-flagged', archived: 'badge-archived' };
const RATING_STATUS_LABELS = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected', flagged: 'Flagged', archived: 'Archived' };

const REVIEW_STATUS_CONFIRM = {
  approved: { title: 'Approve this review?', message: "This makes the review publicly visible on the project's public page and counts it toward the public rating. The citizen will be notified.", confirmLabel: 'Approve', tone: 'success' },
  rejected: { title: 'Reject this review?', message: 'This keeps the review hidden from public display and out of the rating average. The citizen will be notified.', confirmLabel: 'Reject', tone: 'danger' },
  flagged: { title: 'Flag this review for follow-up?', message: 'This keeps it out of public display pending further staff review. The citizen is not notified.', confirmLabel: 'Flag', tone: 'warning' },
  archived: { title: 'Archive this review?', message: 'This removes it from the moderation queue and public display without deleting it. The citizen is not notified.', confirmLabel: 'Archive', tone: 'warning' },
};

async function loadCitizenRatingsPage() {
  const container = document.getElementById('page-citizen-ratings');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Citizen Ratings</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Star ratings and reviews citizens leave on projects. Approve, reject, flag, or archive — the star rating and review text are never editable here.
        </p>
      </div>
      <div id="ratingsSummary" style="font-size:.82rem;font-weight:600;color:var(--text-muted);"></div>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search citizen or project…" oninput="ratingsState.search=this.value;ratingsState.page=1;fetchCitizenRatings()" />
      <select class="filter-select" onchange="ratingsState.status=this.value;ratingsState.page=1;fetchCitizenRatings()">
        <option value="pending" selected>Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="flagged">Flagged</option>
        <option value="archived">Archived</option>
        <option value="">All</option>
      </select>
    </div>
    <div id="ratingsTable" class="table-card"></div>
    <div id="ratingsPager" class="pager"></div>
  `;
  fetchCitizenRatings();
}

async function fetchCitizenRatings() {
  const wrap = document.getElementById('ratingsTable');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.projectRatings, ratingsState);
    renderCitizenRatingsTable(d.data || []);
    renderPager('ratingsPager', d.page, d.last_page, p => { ratingsState.page = p; fetchCitizenRatings(); });
    const summaryEl = document.getElementById('ratingsSummary');
    if (summaryEl) summaryEl.textContent = `${d.total} rating${d.total === 1 ? '' : 's'} · avg ${d.average || 0}★`;
  } catch (e) {
    wrap.innerHTML = '<p class="empty-state">Failed to load ratings.</p>';
    console.error(e);
  } finally {
    setLoading(wrap, false);
  }
}

function renderCitizenRatingsTable(rows) {
  const wrap = document.getElementById('ratingsTable');
  if (!wrap) return;
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No ratings in this view.</p>'; return; }

  wrap.innerHTML = `
    <table class="data-table">
      <thead>
        <tr><th>Project</th><th>Rating</th><th>Review</th><th>Citizen</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(r => `
          <tr>
            <td>${escapeHtml(r.project_code)} — ${escapeHtml(r.project_name)}</td>
            <td>${'★'.repeat(Number(r.rating))}${'☆'.repeat(5 - Number(r.rating))}</td>
            <td style="max-width:280px;">${r.comment ? escapeHtml(r.comment) : '<span style="color:#94a3b8;">—</span>'}</td>
            <td>${escapeHtml(r.citizen_name)}</td>
            <td style="font-size:.75rem;color:#94a3b8;">${formatDate(r.created_at)}</td>
            <td><span class="badge ${RATING_STATUS_BADGE[r.status] || 'badge-spike'}">${RATING_STATUS_LABELS[r.status] || r.status}</span></td>
            <td><button class="btn-secondary btn-compact" onclick="openRatingDetailModal(${r.id})">View Details</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function openRatingDetailModal(id) {
  try {
    const res = await get(API.projectRatings, { id });
    if (!res.success) { toast(res.message || 'Failed to load review', 'error'); return; }
    const r = res.data;

    openModal(`Review #${r.id}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <span class="badge ${RATING_STATUS_BADGE[r.status] || 'badge-spike'}">${RATING_STATUS_LABELS[r.status] || r.status}</span>
          <span style="font-size:1.1rem;">${'★'.repeat(Number(r.rating))}${'☆'.repeat(5 - Number(r.rating))}</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">PROJECT</p><p class="modal-val">${escapeHtml(r.project_code)} — ${escapeHtml(r.project_name)}</p></div>
          <div><p class="modal-label">CITIZEN</p><p class="modal-val">${escapeHtml(r.citizen_name)}</p></div>
          <div><p class="modal-label">SUBMITTED</p><p class="modal-val">${formatDate(r.created_at)}</p></div>
          ${r.moderated_at ? `<div><p class="modal-label">MODERATED</p><p class="modal-val">${escapeHtml(r.moderated_by_name || 'Staff')} on ${formatDate(r.moderated_at)}</p></div>` : ''}
        </div>
        <div>
          <p class="modal-label">REVIEW</p>
          <p class="modal-val" style="font-weight:400;white-space:pre-wrap;">${r.comment ? escapeHtml(r.comment) : '<span style="color:#94a3b8;">No written review.</span>'}</p>
        </div>
        ${r.decision_remarks ? `<div><p class="modal-label">MODERATION REMARKS</p><p class="modal-val" style="font-weight:400;">${escapeHtml(r.decision_remarks)}</p></div>` : ''}
        <div class="form-actions">
          <button class="btn-secondary" onclick="moderateReview(${r.id}, 'approved')" ${r.status === 'approved' ? 'disabled' : ''}>Approve</button>
          <button class="btn-secondary" onclick="moderateReview(${r.id}, 'rejected')" ${r.status === 'rejected' ? 'disabled' : ''}>Reject</button>
          <button class="btn-secondary" onclick="moderateReview(${r.id}, 'flagged')" ${r.status === 'flagged' ? 'disabled' : ''}>Flag</button>
          <button class="btn-secondary" onclick="moderateReview(${r.id}, 'archived')" ${r.status === 'archived' ? 'disabled' : ''}>Archive</button>
        </div>
      </div>
    `);
  } catch {
    toast('Failed to load review', 'error');
  }
}

async function moderateReview(id, status) {
  let decision_remarks = '';
  if (status === 'rejected' || status === 'flagged') {
    decision_remarks = window.prompt('Optional note (shown to the citizen if rejecting; internal-only if flagging):', '') || '';
  }
  const confirmed = await showConfirm(REVIEW_STATUS_CONFIRM[status]);
  if (!confirmed) return;
  try {
    const res = await postAction(API.projectRatings, 'moderate', { id, status, decision_remarks });
    if (!res.success) { toast(res.message || 'Update failed', 'error'); return; }
    toast(`Review marked as ${RATING_STATUS_LABELS[status]}`);
    closeModal();
    fetchCitizenRatings();
  } catch {
    toast('Update failed', 'error');
  }
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
// Same QC bounding box (with a little slack) as api/projects.php's
// projectQcCoordinatesValid() and citizen/api/submit-feedback.php — this map
// is Quezon-City-only, so it neither pans/zooms elsewhere nor plots a pin
// that's outside the city (old bad data included, since nothing here is
// re-validated on the way out of the database).
const QC_BOUNDS = [[14.55, 120.96], [14.82, 121.16]];
function isWithinQc(lat, lng) {
  return lat >= 14.55 && lat <= 14.82 && lng >= 120.96 && lng <= 121.16;
}
// Same boundary file the Citizen portal's Submit Feedback map uses (its own
// Leaflet instance draws it per-barangay with district colors/interactivity
// for picking a barangay; here it's just a plain outline for orientation).
const QC_GEOJSON_URL = (window.BASE_PATH || '/') + 'citizen/assets/data/qc-barangays.geojson';
let qcBoundaryGeoJsonCache = null;
async function loadQcBoundaryGeoJson() {
  if (qcBoundaryGeoJsonCache) return qcBoundaryGeoJsonCache;
  const res = await fetch(QC_GEOJSON_URL);
  qcBoundaryGeoJsonCache = await res.json();
  return qcBoundaryGeoJsonCache;
}
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
    <div class="gis-legend">
      <span class="gis-legend-item"><span class="gis-legend-dot" style="background:${GIS_DEFAULT_COLOR};"></span>Active / In Progress</span>
      <span class="gis-legend-item"><span class="gis-legend-dot" style="background:${GIS_STATUS_COLORS.delayed};"></span>Delayed</span>
      <span class="gis-legend-item"><span class="gis-legend-dot" style="background:${GIS_STATUS_COLORS.completed};"></span>Completed / Turned Over</span>
      <span class="gis-legend-item"><span class="gis-legend-dot" style="background:${GIS_STATUS_COLORS.cancelled};"></span>Cancelled</span>
    </div>
    <div id="gisMapContainer" style="height:520px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);"></div>
    <p id="gisEmptyState" class="empty-state" style="display:none;">No projects with map coordinates match this filter. Add latitude/longitude when registering or editing a project to place it here.</p>
    <p id="gisOutOfBoundsWarning" class="empty-state" style="display:none;color:var(--orange);"></p>
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
    await renderGisMap(result.data || []);
    emptyState.style.display = (result.data || []).length ? 'none' : 'block';
  } catch {
    toast('Failed to load projects for the map', 'error');
  }
}

async function renderGisMap(projects) {
  let skippedOutOfBounds = 0;

  if (!gisMapInstance) {
    gisMapInstance = L.map('gisMapContainer', {
      maxBounds: QC_BOUNDS,
      maxBoundsViscosity: 1.0, // hard stop — panning cannot drag the map past the city limits
      minZoom: 11,             // roughly "all of QC visible" — zooming out further would just show ocean/other cities
    }).setView([14.6760, 121.0437], 12); // Quezon City
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(gisMapInstance);

    try {
      const geojson = await loadQcBoundaryGeoJson();
      L.geoJSON(geojson, {
        style: { color: '#2563eb', weight: 1.5, fill: false },
        interactive: false, // outline only — clicks/hover should pass through to project markers underneath
      }).addTo(gisMapInstance);
    } catch {
      // Purely decorative — the maxBounds restriction above already keeps the map QC-only either way.
    }
  }

  gisMarkers.forEach(m => gisMapInstance.removeLayer(m));
  gisMarkers = [];

  projects.forEach(p => {
    if (p.latitude === null || p.longitude === null || p.latitude === undefined || p.longitude === undefined) return;
    const lat = Number(p.latitude);
    const lng = Number(p.longitude);
    // Older rows saved before coordinate validation existed can still have
    // stray non-QC values — skip them here rather than plot a wrong pin or
    // force the map to zoom out past the city to fit an obviously bad point.
    if (!isWithinQc(lat, lng)) { skippedOutOfBounds++; return; }

    const color = GIS_STATUS_COLORS[p.status] || GIS_DEFAULT_COLOR;
    const marker = L.circleMarker([lat, lng], {
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
    gisMapInstance.fitBounds(group.getBounds().pad(0.2)); // clamped to QC_BOUNDS automatically since maxBounds is already set
  }

  const warning = document.getElementById('gisOutOfBoundsWarning');
  if (warning) {
    warning.style.display = skippedOutOfBounds > 0 ? 'block' : 'none';
    warning.textContent = skippedOutOfBounds === 1
      ? '1 project has a pinned location outside Quezon City and is not shown. Edit its coordinates to fix this.'
      : `${skippedOutOfBounds} projects have pinned locations outside Quezon City and are not shown. Edit their coordinates to fix this.`;
  }

  // Leaflet paints into a container that was just made visible; without this
  // its internal size calculation runs while the pane is still display:none
  // and the tiles render into a collapsed 0x0 box.
  setTimeout(() => gisMapInstance.invalidateSize(), 100);
}

/* ============================================================
   REPORTS
   ============================================================ */
let reportsPageData = null; // cached last-loaded payload, reused by exportReportsCsv()
let reportsDelayedChartInst = null;
let reportsContractorsChartInst = null;
let reportsUsageChartInst = null;
let reportsFundingChartInst = null;

async function loadReportsPage() {
  const container = document.getElementById('page-reports');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Reports</h2>
      <div style="display:flex;gap:10px;">
        <button class="btn-secondary" onclick="exportReportsCsv()">Export CSV</button>
        <button class="btn-secondary" onclick="window.print()">Print</button>
      </div>
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
    reportsPageData = { dashboard, contractors: contractors.data || [], openFeedback, expenseSummary: expenseSummary.data || [] };
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
  const fundingSources = dashboard.funding_source_breakdown || [];
  const recentActivity = (dashboard.recent_workflow || []).slice(0, 5);
  const healthSummary = dashboard.project_health_summary || [];

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
        ${delayed.length ? '<div class="chart-body report-chart-body"><canvas id="reportsDelayedChart"></canvas></div>' : '<p class="empty-state">No delayed projects.</p>'}
      </article>
      <article class="report-panel">
        <h3>Top Contractors</h3>
        ${topContractors.length ? '<div class="chart-body report-chart-body"><canvas id="reportsContractorsChart"></canvas></div>' : '<p class="empty-state">No contractor data.</p>'}
      </article>
      <article class="report-panel">
        <h3>Highest Budget Usage</h3>
        ${highestSpend.length ? '<div class="chart-body report-chart-body"><canvas id="reportsUsageChart"></canvas></div>' : '<p class="empty-state">No budget data.</p>'}
      </article>
    </section>

    <section class="report-columns-2">
      <article class="report-panel">
        <h3>Funding Source Breakdown</h3>
        ${fundingSources.length ? '<div class="chart-body report-chart-body"><canvas id="reportsFundingChart"></canvas></div>' : '<p class="empty-state">No funding source data.</p>'}
      </article>
      <article class="report-panel">
        <h3>Recent Workflow Activity</h3>
        ${recentActivity.length ? recentActivity.map(r => `
          <div class="report-row">
            <span>${escapeHtml(r.record_type)} · ${escapeHtml(r.project_name)}</span>
            <strong>${formatStatus(r.status)}</strong>
          </div>
        `).join('') : '<p class="empty-state">No recent activity.</p>'}
      </article>
    </section>

    <section class="report-columns-2">
      <article class="report-panel" style="grid-column: span 2;">
        <h3>Project Health Overview <small style="font-weight:400;color:var(--text-muted);">— worst first, advisory only</small></h3>
        ${healthSummary.length ? healthSummary.map(h => `
          <div class="report-row">
            <span>${escapeHtml(h.project_code)} — ${escapeHtml(h.name)}</span>
            <span style="display:flex;align-items:center;gap:8px;">
              ${healthBadge(h.health_score, h.health_status)}
              <button class="btn-secondary btn-compact" type="button" onclick="openProjectQRModal('${escapeHtml(h.project_code)}', '${escapeHtml(h.name)}')" title="View QR code">QR</button>
            </span>
          </div>
        `).join('') : '<p class="empty-state">No project health data yet.</p>'}
      </article>
    </section>
  `;

  renderReportsCharts(dashboard, topContractors, highestSpend);
}

function renderReportsCharts(dashboard, topContractors, highestSpend) {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = isDark ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)';

  const delayedCtx = document.getElementById('reportsDelayedChart')?.getContext('2d');
  if (delayedCtx) {
    if (reportsDelayedChartInst) reportsDelayedChartInst.destroy();
    const rows = dashboard.top_delayed || [];
    reportsDelayedChartInst = new Chart(delayedCtx, {
      type: 'bar',
      data: {
        labels: rows.map(p => p.name.length > 22 ? p.name.slice(0, 20) + '…' : p.name),
        datasets: [{ data: rows.map(p => Number(p.days_overdue) || 0), backgroundColor: 'rgba(239,68,68,.75)', hoverBackgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 26 }],
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` ${c.raw} days overdue` } } },
        scales: {
          x: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: gridColor }, border: { display: false } },
          y: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10.5 } }, border: { display: false } },
        },
      },
    });
  }

  const contractorsCtx = document.getElementById('reportsContractorsChart')?.getContext('2d');
  if (contractorsCtx) {
    if (reportsContractorsChartInst) reportsContractorsChartInst.destroy();
    const rows = topContractors || [];
    reportsContractorsChartInst = new Chart(contractorsCtx, {
      type: 'bar',
      data: {
        labels: rows.map(c => c.name.length > 22 ? c.name.slice(0, 20) + '…' : c.name),
        datasets: [{ data: rows.map(c => Number(c.performance_score) || 0), backgroundColor: 'rgba(34,197,94,.75)', hoverBackgroundColor: '#22c55e', borderRadius: 6, maxBarThickness: 26 }],
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` Score: ${c.raw}/100` } } },
        scales: {
          x: { beginAtZero: true, max: 100, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: gridColor }, border: { display: false } },
          y: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10.5 } }, border: { display: false } },
        },
      },
    });
  }

  const usageCtx = document.getElementById('reportsUsageChart')?.getContext('2d');
  if (usageCtx) {
    if (reportsUsageChartInst) reportsUsageChartInst.destroy();
    const rows = highestSpend || [];
    reportsUsageChartInst = new Chart(usageCtx, {
      type: 'bar',
      data: {
        labels: rows.map(r => String(r.project_name).length > 22 ? String(r.project_name).slice(0, 20) + '…' : r.project_name),
        datasets: [{
          data: rows.map(r => r.budget > 0 ? Math.round((r.total_spent / r.budget) * 100) : 0),
          backgroundColor: 'rgba(249,115,22,.75)', hoverBackgroundColor: '#f97316', borderRadius: 6, maxBarThickness: 26,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` ${c.raw}% of budget used` } } },
        scales: {
          x: { beginAtZero: true, ticks: { color: '#94a3b8', callback: v => v + '%' }, grid: { color: gridColor }, border: { display: false } },
          y: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10.5 } }, border: { display: false } },
        },
      },
    });
  }

  const fundingCtx = document.getElementById('reportsFundingChart')?.getContext('2d');
  if (fundingCtx) {
    if (reportsFundingChartInst) reportsFundingChartInst.destroy();
    const rows = (dashboard.funding_source_breakdown || []).filter(r => Number(r.total) > 0);
    reportsFundingChartInst = new Chart(fundingCtx, {
      type: 'doughnut',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{
          data: rows.map(r => Number(r.total_budget)),
          backgroundColor: ['#14b8a6', '#3b82f6', '#a855f7', '#f97316', '#22c55e', '#ef4444', '#94a3b8'],
          borderColor: '#fff', borderWidth: 3, hoverOffset: 6,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: { duration: 900 },
        plugins: {
          legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 10, boxHeight: 10, font: { size: 11 } } },
          tooltip: { backgroundColor: '#1e2a3b', callbacks: { label: c => ` ${c.label}: ${formatMoney(c.raw)}` } },
        },
      },
    });
  }
}

function exportReportsCsv() {
  if (!reportsPageData) {
    toast('Reports data is still loading.', 'error');
    return;
  }
  const { dashboard, contractors, expenseSummary } = reportsPageData;
  const lines = [];
  const addSection = (title, header, rows) => {
    lines.push([title]);
    lines.push(header);
    rows.forEach(r => lines.push(r));
    lines.push([]);
  };

  addSection('Summary', ['Metric', 'Value'], [
    ['Active Projects', dashboard.active_projects],
    ['Delayed Projects', dashboard.delayed_projects],
    ['Budget Used %', dashboard.budget_pct],
    ['Total Budget', dashboard.total_budget],
    ['Total Spent', dashboard.total_spent],
  ]);

  addSection('Projects by Status', ['Status', 'Total'],
    (dashboard.status_mix || []).map(r => [PROJECT_STATUS_LABELS[r.status] || r.status, r.total]));

  addSection('Projects by Category', ['Category', 'Total Projects', 'Total Budget'],
    (dashboard.category_breakdown || []).map(r => [r.label, r.total, r.total_budget]));

  addSection('Funding Source Breakdown', ['Funding Source', 'Total Projects', 'Total Budget'],
    (dashboard.funding_source_breakdown || []).map(r => [r.label, r.total, r.total_budget]));

  addSection('Monthly Spending', ['Month', 'Total'],
    (dashboard.monthly_spending || []).map(r => [r.month, r.total]));

  addSection('Delayed Projects', ['Project', 'Days Overdue', 'Contractor'],
    (dashboard.top_delayed || []).map(p => [p.name, p.days_overdue || 0, p.contractor_name || '']));

  addSection('Top Contractors', ['Contractor', 'Performance Score'],
    contractors.slice(0, 10).map(c => [c.name, c.performance_score]));

  addSection('Highest Budget Usage', ['Project', 'Budget', 'Spent'],
    expenseSummary.slice(0, 10).map(r => [r.project_name, r.budget, r.total_spent]));

  addSection('Project Health Overview', ['Project', 'Health Score', 'Health Status'],
    (dashboard.project_health_summary || []).map(h => [`${h.project_code} — ${h.name}`, h.health_score, HEALTH_LABELS[h.health_status] || h.health_status]));

  const csv = lines.map(row => row.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `ipms-reports-${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

/* ============================================================
   AI PROJECT ASSISTANT (formerly "AI Risk Insights")
   Reads/writes api/ai-assistant.php (frozen contract: action=ask for a
   chat turn, action=history to restore the session's prior transcript,
   action=reset to start over). The assistant is grounded entirely in a
   live data snapshot the backend queries fresh on every turn — it is
   read-only decision support and can never approve, reject, or modify a
   record; see includes/AiAssistantContext.php's persona for the full
   contract enforced server-side.
   ============================================================ */
const AI_ASST_SUGGESTIONS = [
  'Which projects are delayed?',
  'Which projects have budget risks?',
  'Which contractors have the highest performance?',
  'Which inspections are overdue?',
  'Summarize citizen feedback.',
  'Recommend projects requiring immediate attention.',
];

let aiAsstSending = false;
// Suggested-question chips stay available for the whole conversation (not
// just the first message) — the user can collapse/reopen them instead of
// losing them permanently after the first send.
let aiAsstSuggestionsOpen = true;

async function loadAiAssistantPage() {
  const container = document.getElementById('page-ai-risk-insights');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">AI Project Assistant</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Ask about delays, budget risk, contractor and engineer performance, inspections, or
          citizen feedback — answered from your live project data.
        </p>
      </div>
      <button type="button" class="btn-secondary btn-compact" onclick="aiAsstClearChat()">New Chat</button>
    </div>

    <div class="ai-asst-shell">
      <div class="ai-asst-disclaimer">
        <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.596c.75 1.334-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.742-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-6a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <span>Advisory only — this assistant answers questions and surfaces insights, but it never approves, rejects, or modifies any record itself.</span>
      </div>
      <div class="ai-asst-messages" id="aiAsstMessages"></div>

      <div class="ai-asst-suggestions-bar">
        <button type="button" class="ai-asst-suggestions-toggle" id="aiAsstSuggestionsToggle">
          <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 01.967.744L11.98 6.9a3 3 0 002.12 2.12l4.156 1.013a1 1 0 010 1.934l-4.156 1.013a3 3 0 00-2.12 2.12l-1.013 4.156a1 1 0 01-1.934 0l-1.013-4.156a3 3 0 00-2.12-2.12L1.744 11.9a1 1 0 010-1.934L5.9 8.953a3 3 0 002.12-2.12L9.033 2.744A1 1 0 0110 2z"/></svg>
          <span id="aiAsstSuggestionsToggleLabel">Hide suggestions</span>
          <svg class="ai-asst-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
        <div class="ai-asst-suggestions" id="aiAsstSuggestions">
          ${AI_ASST_SUGGESTIONS.map(q => `<button type="button" class="ai-asst-chip" data-q="${escapeHtml(q)}">${escapeHtml(q)}</button>`).join('')}
        </div>
      </div>

      <form class="ai-asst-composer" id="aiAsstForm">
        <textarea id="aiAsstInput" class="ai-asst-input" rows="1" placeholder="Ask about your projects…" maxlength="1000"></textarea>
        <button type="submit" class="ai-asst-send" id="aiAsstSend" aria-label="Send">
          <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.4 20l17.6-8.6a1 1 0 000-1.8L3.4 1a1 1 0 00-1.4 1.1L4.6 10l-2.6 7.9A1 1 0 003.4 20z"/></svg>
        </button>
      </form>
    </div>
  `;

  container.querySelectorAll('.ai-asst-chip').forEach(chip => {
    chip.addEventListener('click', () => aiAsstSend(chip.dataset.q));
  });
  document.getElementById('aiAsstSuggestionsToggle').addEventListener('click', aiAsstToggleSuggestions);
  aiAsstApplySuggestionsState();

  const input = document.getElementById('aiAsstInput');
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('aiAsstForm').requestSubmit();
    }
  });
  document.getElementById('aiAsstForm').addEventListener('submit', e => {
    e.preventDefault();
    aiAsstSend(input.value);
  });

  aiAsstRestoreHistory();
}

function aiAsstToggleSuggestions() {
  aiAsstSuggestionsOpen = !aiAsstSuggestionsOpen;
  aiAsstApplySuggestionsState();
}

function aiAsstApplySuggestionsState() {
  const panel = document.getElementById('aiAsstSuggestions');
  const toggle = document.getElementById('aiAsstSuggestionsToggle');
  const label = document.getElementById('aiAsstSuggestionsToggleLabel');
  if (!panel || !toggle || !label) return;
  panel.classList.toggle('open', aiAsstSuggestionsOpen);
  toggle.classList.toggle('open', aiAsstSuggestionsOpen);
  label.textContent = aiAsstSuggestionsOpen ? 'Hide suggestions' : 'Show suggestions';
}

async function aiAsstRestoreHistory() {
  const messagesEl = document.getElementById('aiAsstMessages');
  if (!messagesEl) return;
  try {
    const res = await get(API.aiAssistant, { action: 'history' });
    const history = (res && res.history) || [];
    if (!history.length) {
      aiAsstAddMessage('assistant', "Hi! I'm your AI Project Assistant. Ask me about delays, budget risk, contractor performance, inspections, or citizen feedback — or try one of the suggested questions.");
      return;
    }
    history.forEach(turn => aiAsstAddMessage(turn.role === 'assistant' ? 'assistant' : 'user', turn.content));
  } catch {
    aiAsstAddMessage('assistant', "Hi! I'm your AI Project Assistant. Ask me about delays, budget risk, contractor performance, inspections, or citizen feedback — or try one of the suggested questions.");
  }
}

function aiAsstAddMessage(role, text) {
  const messagesEl = document.getElementById('aiAsstMessages');
  if (!messagesEl) return;
  const row = document.createElement('div');
  row.className = `ai-asst-msg ${role}`;
  const bubble = document.createElement('div');
  bubble.className = 'ai-asst-bubble';
  bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
  row.appendChild(bubble);
  messagesEl.appendChild(row);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function aiAsstShowTyping() {
  const messagesEl = document.getElementById('aiAsstMessages');
  if (!messagesEl) return;
  const row = document.createElement('div');
  row.className = 'ai-asst-msg assistant';
  row.id = 'aiAsstTyping';
  row.innerHTML = '<div class="ai-asst-bubble"><div class="ai-asst-typing"><span></span><span></span><span></span></div></div>';
  messagesEl.appendChild(row);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function aiAsstHideTyping() {
  document.getElementById('aiAsstTyping')?.remove();
}

async function aiAsstSend(text) {
  text = (text || '').trim();
  if (!text || aiAsstSending) return;

  aiAsstAddMessage('user', text);

  const input = document.getElementById('aiAsstInput');
  const sendBtn = document.getElementById('aiAsstSend');
  input.value = '';
  input.style.height = 'auto';
  aiAsstSending = true;
  input.disabled = true;
  sendBtn.disabled = true;
  aiAsstShowTyping();

  try {
    const res = await postAction(API.aiAssistant, 'ask', { message: text });
    aiAsstHideTyping();
    if (res && res.success) {
      aiAsstAddMessage('assistant', res.reply);
    } else {
      aiAsstAddMessage('error', (res && res.message) || 'Something went wrong. Please try again.');
    }
  } catch (err) {
    aiAsstHideTyping();
    aiAsstAddMessage('error', 'Could not reach the assistant. Please check your connection and try again.');
    console.error(err);
  } finally {
    aiAsstSending = false;
    input.disabled = false;
    sendBtn.disabled = false;
    input.focus();
  }
}

async function aiAsstClearChat() {
  try {
    await postAction(API.aiAssistant, 'reset', {});
  } catch {}
  loadAiAssistantPage();
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
    </div>
    <div id="budgetSummary" class="budget-summary-grid"></div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search expenses…" oninput="budgetState.search=this.value;budgetState.page=1;fetchExpenses()" />
      <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;cursor:pointer;">
        <input type="checkbox" onchange="budgetState.flagged=this.checked;budgetState.page=1;fetchExpenses()" />
        Anomalies only
      </label>
      <button class="btn-primary" style="margin-left:auto;" onclick="showExpenseForm()">+ Log Expense</button>
    </div>
    <div id="expensesTable" class="table-card"></div>
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
          <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:4px;font-size:.75rem;color:#64748b;margin:4px 0;">
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
                <button class="btn-icon" onclick="toggleFlag(${e.id}, ${e.flagged})" title="${e.flagged?'Unflag':'Flag'}" style="color:${e.flagged?'#ef4444':'#94a3b8'};"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M3 2.75A.75.75 0 013.75 2h.5a.75.75 0 01.75.75V3h9.19c.727 0 1.15.826.723 1.415L12.86 7.25l2.053 2.835c.427.589.004 1.415-.723 1.415H5v5.75a.75.75 0 01-.75.75h-.5a.75.75 0 01-.75-.75V2.75z" clip-rule="evenodd"/></svg></button>
                <button class="btn-icon btn-danger" onclick="deleteExpense(${e.id})" title="Delete"><svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button>
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
      <div>
        <h2 class="page-title">Staff Requests</h2>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;max-width:640px;">
          Request a new Engineer or BAC account. A Super Admin must review and approve the
          request before the login is created — you cannot create staff accounts directly.
        </p>
      </div>
    </div>
    <div class="staff-request-layout">
      <div class="staff-request-info">
        <div class="staff-request-info-icon">
          <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
        </div>
        <h3>How it works</h3>
        <ol class="staff-request-steps">
          <li><span class="step-num">1</span><span>Fill in the new hire's role and details</span></li>
          <li><span class="step-num">2</span><span>A Super Admin reviews the request</span></li>
          <li><span class="step-num">3</span><span>Once approved, their login is created and emailed to them</span></li>
        </ol>
      </div>
      <div class="table-card staff-request-form-card">
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
   FEEDBACK PAGE
   ============================================================ */
let feedbackState = { page: 1, search: '', status: '', priority: '', category: '' };

const FEEDBACK_CATEGORY_LABELS = {
  complaint: 'General Complaint',
  road_damage: 'Road Damage',
  drainage_flooding: 'Drainage / Flooding',
  streetlight: 'Streetlight',
  sidewalk_accessibility: 'Sidewalk / Accessibility',
  safety_hazard: 'Safety Hazard',
  project_delay: 'Project Delay',
  suggestion: 'Suggestion',
  inquiry: 'Inquiry',
  commendation: 'Commendation',
};

async function loadFeedbackPage(containerId = 'page-citizen-feedback', title = 'Citizen Feedback Review', allowNewEntry = false) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">${title}</h2>
    </div>
    <div id="feedbackNeedsAttention"></div>
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
      <select class="filter-select" onchange="feedbackState.category=this.value;feedbackState.page=1;fetchFeedback()">
        <option value="">All Categories</option>
        ${Object.entries(FEEDBACK_CATEGORY_LABELS).map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}
      </select>
      ${allowNewEntry ? '<button class="btn-primary" style="margin-left:auto;" onclick="showFeedbackForm()">+ New Entry</button>' : ''}
    </div>
    <div id="feedbackTable" class="table-card"></div>
    <div id="feedbackPager" class="pager"></div>
  `;
  fetchFeedback();
  fetchFeedbackNeedsAttention();
}

async function fetchFeedbackNeedsAttention() {
  const wrap = document.getElementById('feedbackNeedsAttention');
  if (!wrap) return;
  try {
    const d = await get(API.feedback, { action: 'needs_attention' });
    renderFeedbackNeedsAttention(d.data || []);
  } catch {
    wrap.innerHTML = '';
  }
}

function renderFeedbackNeedsAttention(rows) {
  const wrap = document.getElementById('feedbackNeedsAttention');
  if (!wrap) return;
  if (!rows.length) { wrap.innerHTML = ''; return; }

  wrap.innerHTML = `
    <div class="table-card" style="padding:16px 18px;margin-bottom:14px;">
      <h3 style="font-size:.88rem;margin:0 0 10px;">Needs Attention <small style="font-weight:400;color:#94a3b8;">— rule-based priority score, advisory only</small></h3>
      <div style="display:flex;flex-direction:column;gap:8px;">
        ${rows.map(f => `
          <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;background:var(--bg-body);cursor:pointer;" onclick="openFeedbackDetailModal(${f.id})">
            <span class="badge badge-urgent" style="flex-shrink:0;">${escapeHtml(f.reason)}</span>
            <span style="flex:1;font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(f.message)}</span>
            <span style="font-size:.72rem;color:#94a3b8;flex-shrink:0;">${f.project_name ? escapeHtml(f.project_name) : 'General'}</span>
          </div>
        `).join('')}
      </div>
    </div>
  `;
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
            <td>${escapeHtml(FEEDBACK_CATEGORY_LABELS[f.category] || f.category)}</td>
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
                <button class="btn-secondary btn-compact" onclick="openFeedbackDetailModal(${f.id})">View Details</button>
              </div>
            </td>
          </tr>`).join('')}
      </tbody>
    </table>`;
}

// Row actions open the full detail view first (citizen/contact info, full
// message, photos, project/location context) — status changes only ever
// happen from inside that modal, each gated by an on-brand confirm dialog
// (showConfirm(), not the native confirm()), so staff never act on a
// truncated table row without seeing the full picture first.
const FEEDBACK_STATUS_LABELS = { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved', closed: 'Closed' };
const FEEDBACK_STATUS_CONFIRM = {
  in_progress: { title: 'Mark as In Progress?', message: 'This flags the concern as actively being reviewed. The citizen will be notified of the change.', confirmLabel: 'Mark In Progress', tone: 'info' },
  resolved: { title: 'Mark as Resolved?', message: 'This confirms the concern has been addressed. The citizen will be notified that it is resolved.', confirmLabel: 'Mark Resolved', tone: 'success' },
  closed: { title: 'Close this feedback?', message: 'This closes the entry with no further action expected. The citizen will be notified.', confirmLabel: 'Close Feedback', tone: 'warning' },
};

async function openFeedbackDetailModal(id) {
  try {
    const f = await get(API.feedback, { id });
    if (f.error) { toast(f.error, 'error'); return; }

    const pBadge = { urgent: 'badge-urgent', high: 'badge-overbudget', medium: 'badge-spike', low: 'badge-resolved' };
    const sBadge = { open: 'badge-urgent', in_progress: 'badge-highprio', resolved: 'badge-resolved', closed: 'badge-resolved' };
    const photos = f.photos || [];
    const hasContact = f.contact_name || f.contact_phone || f.contact_email;

    openModal(`Feedback #${f.id}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <span class="badge ${pBadge[f.priority] || 'badge-resolved'}">${escapeHtml(f.priority)} priority</span>
          <span class="badge ${sBadge[f.status] || 'badge-resolved'}">${FEEDBACK_STATUS_LABELS[f.status] || escapeHtml(f.status)}</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">SUBMITTED BY</p><p class="modal-val">${f.citizen_name ? escapeHtml(f.citizen_name) : 'Anonymous'}</p></div>
          <div><p class="modal-label">DATE SUBMITTED</p><p class="modal-val">${formatDateTime(f.created_at)}</p></div>
          <div><p class="modal-label">TYPE</p><p class="modal-val">${f.concern_type === 'maintenance' ? 'Maintenance' : 'Project'}</p></div>
          <div><p class="modal-label">CATEGORY</p><p class="modal-val">${escapeHtml(FEEDBACK_CATEGORY_LABELS[f.category] || formatStatus(f.category))}</p></div>
          <div><p class="modal-label">RELATED PROJECT</p><p class="modal-val">${f.project_name ? escapeHtml(f.project_name) : '—'}</p></div>
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${[f.barangay, f.district].filter(Boolean).map(v => escapeHtml(v)).join(', ') || '—'}</p></div>
        </div>

        ${hasContact ? `
        <div>
          <p class="modal-label">CONTACT INFO</p>
          <p class="modal-val" style="font-weight:400;">
            ${f.contact_name ? escapeHtml(f.contact_name) + '<br>' : ''}${f.contact_phone ? escapeHtml(f.contact_phone) + '<br>' : ''}${f.contact_email ? escapeHtml(f.contact_email) : ''}
          </p>
        </div>` : ''}

        <div>
          <p class="modal-label">MESSAGE</p>
          <p class="modal-val" style="font-weight:400;white-space:pre-wrap;">${escapeHtml(f.message)}</p>
        </div>

        ${photos.length ? `
        <div>
          <p class="modal-label">PHOTOS</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
            ${photos.map(p => `
              <a href="${(window.BASE_PATH || '') + p}" target="_blank" rel="noopener">
                <img src="${(window.BASE_PATH || '') + p}" style="width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--border);" />
              </a>
            `).join('')}
          </div>
        </div>` : ''}

        ${f.concern_type === 'maintenance' ? `
        <div><p class="modal-label">CIMMS SYNC</p><p class="modal-val">${f.cimm_reference ? escapeHtml(f.cimm_reference) : escapeHtml(f.cimm_sync_status || '—')}</p></div>
        ` : ''}

        <div>
          <p class="modal-label">ACTIONS</p>
          <div class="form-actions" style="justify-content:flex-start;margin-top:8px;">
            <button type="button" class="btn-secondary btn-compact" onclick="confirmFeedbackStatus(${f.id},'in_progress')" ${f.status === 'in_progress' ? 'disabled' : ''}>Mark In Progress</button>
            <button type="button" class="btn-primary btn-compact" onclick="confirmFeedbackStatus(${f.id},'resolved')" ${f.status === 'resolved' ? 'disabled' : ''}>Mark Resolved</button>
            <button type="button" class="btn-secondary btn-compact" onclick="confirmFeedbackStatus(${f.id},'closed')" ${f.status === 'closed' ? 'disabled' : ''}>Close</button>
          </div>
        </div>
      </div>
    `);
  } catch (err) {
    toast('Failed to load feedback details', 'error');
    console.error(err);
  }
}

async function confirmFeedbackStatus(id, status) {
  const cfg = FEEDBACK_STATUS_CONFIRM[status] || { title: 'Change status?', message: `Change status to "${FEEDBACK_STATUS_LABELS[status] || status}"?`, confirmLabel: 'Confirm', tone: 'info' };
  const confirmed = await showConfirm(cfg);
  if (!confirmed) return;
  try {
    await put(API.feedback, id, { status });
    toast(`Marked as ${FEEDBACK_STATUS_LABELS[status] || status}`);
    closeModal();
    fetchFeedback();
  } catch {
    toast('Update failed', 'error');
  }
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
// Guarded so Escape only dismisses the confirm dialog (not the detail modal
// underneath it too) when a confirm prompt is open — see showConfirm() below.
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && !document.getElementById('confirmOverlay')?.classList.contains('open')) closeModal();
});

// ── Confirm dialog — on-brand replacement for native confirm(). Stacks
// above .modal-overlay (its own overlay lives in includes/footer.php), so it
// can gate an action button that lives inside an already-open detail modal.
// Usage: if (!(await showConfirm({ title, message, tone }))) return;
const CONFIRM_ICONS = {
  info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
  success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-3.293-4.707a1 1 0 00-1.414-1.414L9 8.586 7.207 6.793a1 1 0 00-1.414 1.414l2.5 2.5a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
  warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.596c.75 1.334-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.742-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-6a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
  danger: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>',
};
const CONFIRM_BTN_CLASS = { info: 'btn-primary', success: 'btn-primary', warning: 'btn-primary', danger: 'btn-danger' };

function showConfirm({ title = 'Are you sure?', message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel', tone = 'info' } = {}) {
  const overlay = document.getElementById('confirmOverlay');
  const card = overlay.querySelector('.confirmdlg-card');
  const iconEl = document.getElementById('confirmIcon');
  const titleEl = document.getElementById('confirmTitle');
  const msgEl = document.getElementById('confirmMessage');
  const cancelBtn = document.getElementById('confirmCancelBtn');
  const okBtn = document.getElementById('confirmOkBtn');

  card.dataset.tone = tone;
  iconEl.innerHTML = CONFIRM_ICONS[tone] || CONFIRM_ICONS.info;
  titleEl.textContent = title;
  msgEl.textContent = message;
  cancelBtn.textContent = cancelLabel;
  okBtn.textContent = confirmLabel;
  okBtn.className = CONFIRM_BTN_CLASS[tone] || 'btn-primary';

  return new Promise(resolve => {
    function finish(result) {
      overlay.classList.remove('open');
      cancelBtn.removeEventListener('click', onCancel);
      okBtn.removeEventListener('click', onOk);
      overlay.removeEventListener('click', onBackdrop);
      document.removeEventListener('keydown', onKeydown);
      resolve(result);
    }
    function onCancel() { finish(false); }
    function onOk() { finish(true); }
    function onBackdrop(e) { if (e.target === overlay) finish(false); }
    function onKeydown(e) {
      if (e.key === 'Escape') finish(false);
      else if (e.key === 'Enter') finish(true);
    }

    cancelBtn.addEventListener('click', onCancel);
    okBtn.addEventListener('click', onOk);
    overlay.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onKeydown);
    overlay.classList.add('open');
    okBtn.focus();
  });
}

// ── Pager — same Gmail-style pill as the citizen portal's list-pager ──
function renderPager(containerId, page, lastPage, onPage) {
  const el = document.getElementById(containerId);
  if (!el || lastPage <= 1) { if (el) el.innerHTML = ''; return; }
  const prevSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
  const nextSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>';
  el.innerHTML = `
    <div class="list-pager">
      <span class="list-pager-info">Page ${page} of ${lastPage}</span>
      <button type="button" class="list-pager-btn" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''} aria-label="Previous page">${prevSvg}</button>
      <button type="button" class="list-pager-btn" data-page="${page + 1}" ${page >= lastPage ? 'disabled' : ''} aria-label="Next page">${nextSvg}</button>
    </div>
  `;
  el.querySelectorAll('.list-pager-btn:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => onPage(parseInt(btn.dataset.page, 10)));
  });
}

// ── Sidebar toggle (open/close + backdrop) is handled by assets/js/sidebar-toggle.js. ──

// ── Nav ──
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', e => {
    e.preventDefault();
    navigate(item.dataset.page || 'dashboard');
  });
});

// ── Notification bell/panel toggle + polling is handled by assets/js/notifications.js. ──
// ── Sidebar notification badges are handled by assets/js/sidebar-badges.js. ──

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
    <div id="page-calendar-schedule" class="page-section" style="display:none;"></div>
    <div id="page-gis-map" class="page-section" style="display:none;"></div>
    <div id="page-reports" class="page-section" style="display:none;"></div>
    <div id="page-ai-risk-insights" class="page-section" style="display:none;"></div>
    <div id="page-announcements" class="page-section" style="display:none;"></div>
    <div id="page-citizen-feedback" class="page-section" style="display:none;"></div>
    <div id="page-project-gallery" class="page-section" style="display:none;"></div>
    <div id="page-citizen-ratings" class="page-section" style="display:none;"></div>
    <div id="page-staff-requests" class="page-section" style="display:none;"></div>
    <div id="page-contractor-performance" class="page-section" style="display:none;"></div>
    <div id="page-engineer-performance" class="page-section" style="display:none;"></div>
    <div id="page-completed-projects" class="page-section" style="display:none;"></div>
    <div id="page-cancelled-projects" class="page-section" style="display:none;"></div>
    <div id="page-public-facilities-integration" class="page-section" style="display:none;"></div>
  `;

  // The re-wrap above just replaced every node scroll-reveal.js observed at
  // DOMContentLoaded, so the fresh .reveal sections would stay at opacity:0
  // forever (all the dashboard's lower cards). Re-scan the new DOM.
  window.rescanScrollReveal?.();

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
 
