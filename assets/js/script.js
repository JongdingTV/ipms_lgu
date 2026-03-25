/* ============================================================
   LGU Infrastructure Dashboard — assets/js/script.js
   Full live-data frontend
   ============================================================ */

const API = {
  dashboard:   'api/dashboard.php',
  projects:    'api/projects.php',
  expenses:    'api/expenses.php',
  contractors: 'api/contractors.php',
  feedback:    'api/feedback.php',
};

/* ── Tiny fetch helpers ── */
async function get(url, params = {}) {
  const qs = new URLSearchParams(params).toString();
  const res = await fetch(qs ? `${url}?${qs}` : url);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
async function post(url, body) {
  const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
  return res.json();
}
async function put(url, id, body) {
  const res = await fetch(`${url}?id=${id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
  return res.json();
}
async function del(url, id) {
  const res = await fetch(`${url}?id=${id}`, { method:'DELETE' });
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

/* ── Loading overlay on cards ── */
function setLoading(el, on) {
  if (on) el.classList.add('loading'); else el.classList.remove('loading');
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
    dashboard:   loadDashboard,
    projects:    loadProjectsPage,
    budget:      loadBudgetPage,
    contractors: loadContractorsPage,
    feedback:    loadFeedbackPage,
  };
  if (loaders[page]) loaders[page]();
}

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

    // Notif badge
    const badge = document.querySelector('.notif-badge');
    if (badge) badge.textContent = d.high_risk_alerts + d.delayed_projects;

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
      <span class="proj-name">${p.name}</span>
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
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>
      </div>
      <span class="feedback-text">${f.message.slice(0,50)}${f.message.length>50?'…':''}</span>
      <span class="badge ${priorityClass[f.priority] || 'badge-resolved'}">${priorityLabel[f.priority]}</span>
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

/* ============================================================
   PROJECT MODAL (from dashboard)
   ============================================================ */
async function openProjectModal(id) {
  try {
    const p = await get(API.projects, { id });
    const color = p.progress >= 70 ? '#22c55e' : p.progress >= 40 ? '#f97316' : '#ef4444';
    openModal(`Project #${p.id} — ${p.name}`, `
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div><p class="modal-label">LOCATION</p><p class="modal-val">${p.location || '—'}</p></div>
          <div><p class="modal-label">CONTRACTOR</p><p class="modal-val">${p.contractor_name || '—'}</p></div>
          <div><p class="modal-label">BUDGET</p><p class="modal-val">₱${Number(p.budget).toLocaleString()}</p></div>
          <div><p class="modal-label">SPENT</p><p class="modal-val">₱${Number(p.total_spent).toLocaleString()}</p></div>
          <div><p class="modal-label">STATUS</p><p class="modal-val"><span class="badge status-${p.status}">${p.status.toUpperCase()}</span></p></div>
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
      </div>
    `);
  } catch (e) {
    toast('Failed to load project details', 'error');
  }
}

/* ============================================================
   PROJECTS PAGE
   ============================================================ */
let projectsState = { page: 1, search: '', status: '' };

async function loadProjectsPage() {
  const container = document.getElementById('page-projects');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Projects</h2>
      <button class="btn-primary" onclick="showProjectForm()">+ New Project</button>
    </div>
    <div class="filter-bar">
      <input class="filter-input" id="projSearch" placeholder="Search projects…" oninput="projectsState.search=this.value;projectsState.page=1;fetchProjects()" />
      <select class="filter-select" onchange="projectsState.status=this.value;projectsState.page=1;fetchProjects()">
        <option value="">All Statuses</option>
        <option value="planning">Planning</option>
        <option value="active">Active</option>
        <option value="delayed">Delayed</option>
        <option value="on_hold">On Hold</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
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
            <td><span class="proj-id">${p.project_code}</span></td>
            <td><strong>${p.name}</strong><br><small style="color:#94a3b8">${p.location||''}</small></td>
            <td>${p.location || '—'}</td>
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
            <td><span class="badge status-${p.status}">${p.status}</span></td>
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
  let contractors = [];
  try {
    const cd = await get(API.contractors, { page: 1, _limit: 100 });
    contractors = cd.data || [];
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
          <label>Contractor</label>
          <select name="contractor_id" class="form-input">
            <option value="">— Select —</option>
            ${contractors.map(c => `<option value="${c.id}" ${p?.contractor_id==c.id?'selected':''}>${c.name}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Location</label>
          <input name="location" class="form-input" value="${p?.location||''}" />
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
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-input">
            ${['planning','active','delayed','on_hold','completed','cancelled'].map(s =>
              `<option value="${s}" ${p?.status===s?'selected':''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-top:8px;">
        <label>Description</label>
        <textarea name="description" class="form-input" rows="3">${p?.description||''}</textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">${id ? 'Update' : 'Create'} Project</button>
      </div>
    </form>
  `);
}

async function submitProjectForm(e, id) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  try {
    const res = id ? await put(API.projects, id, body) : await post(API.projects, body);
    if (res.error) { toast(res.error, 'error'); return; }
    toast(id ? 'Project updated!' : 'Project created!');
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
   BUDGET PAGE
   ============================================================ */
let budgetState = { page: 1, search: '', flagged: false, project_id: '' };

async function loadBudgetPage() {
  const container = document.getElementById('page-budget');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Budget &amp; Expenses</h2>
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
            ${projects.map(p => `<option value="${p.id}">${p.project_code} — ${p.name}</option>`).join('')}
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
   CONTRACTORS PAGE
   ============================================================ */
let contractorsState = { page: 1, search: '' };

async function loadContractorsPage() {
  const container = document.getElementById('page-contractors');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Contractors</h2>
      <button class="btn-primary" onclick="showContractorForm()">+ Add Contractor</button>
    </div>
    <div class="filter-bar">
      <input class="filter-input" placeholder="Search contractors…"
        oninput="contractorsState.search=this.value;contractorsState.page=1;fetchContractors()" />
    </div>
    <div id="contractorsGrid" class="contractors-grid"></div>
    <div id="contractorsPager" class="pager"></div>
  `;
  fetchContractors();
}

async function fetchContractors() {
  const wrap = document.getElementById('contractorsGrid');
  if (!wrap) return;
  setLoading(wrap, true);
  try {
    const d = await get(API.contractors, { page: contractorsState.page, search: contractorsState.search });
    renderContractors(d.data);
    renderPager('contractorsPager', d.page, d.last_page, p => { contractorsState.page = p; fetchContractors(); });
  } catch {
    wrap.innerHTML = '<p class="empty-state">Failed to load contractors.</p>';
  } finally {
    setLoading(wrap, false);
  }
}

function renderContractors(rows) {
  const wrap = document.getElementById('contractorsGrid');
  if (!rows.length) { wrap.innerHTML = '<p class="empty-state">No contractors found.</p>'; return; }
  wrap.innerHTML = rows.map(c => {
    const scoreColor = c.performance_score >= 80 ? '#22c55e' : c.performance_score >= 60 ? '#f97316' : '#ef4444';
    return `
      <div class="contractor-card">
        <div class="contractor-header">
          <div class="contractor-avatar">${c.name.charAt(0)}</div>
          <div>
            <p class="contractor-name">${c.name}</p>
            <p class="contractor-contact">${c.contact_person || '—'}</p>
          </div>
          <div style="margin-left:auto;text-align:center;">
            <div style="font-size:1.4rem;font-weight:800;color:${scoreColor};font-family:monospace">${c.performance_score}</div>
            <div style="font-size:.65rem;color:#94a3b8;">SCORE</div>
          </div>
        </div>
        <div class="contractor-info">
          <span>📧 ${c.email || '—'}</span>
          <span>📞 ${c.phone || '—'}</span>
        </div>
        <div class="contractor-stats">
          <div class="cstat"><span class="cstat-val">${c.total_projects||0}</span><span class="cstat-lbl">Total</span></div>
          <div class="cstat"><span class="cstat-val" style="color:#22c55e">${c.active_projects||0}</span><span class="cstat-lbl">Active</span></div>
          <div class="cstat"><span class="cstat-val" style="color:#ef4444">${c.delayed_projects||0}</span><span class="cstat-lbl">Delayed</span></div>
          <div class="cstat"><span class="cstat-val" style="color:#3b82f6">${c.completed_projects||0}</span><span class="cstat-lbl">Done</span></div>
        </div>
        <div class="contractor-footer">
          <span class="badge ${c.status==='active'?'badge-highprio':'badge-resolved'}">${c.status}</span>
          <div class="action-btns">
            <button class="btn-icon" onclick="showContractorForm(${c.id})" title="Edit">✏️</button>
            <button class="btn-icon btn-danger" onclick="deleteContractor(${c.id})" title="Delete">🗑</button>
          </div>
        </div>
      </div>`;
  }).join('');
}

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
          <label>Performance Score (0–100)</label>
          <input name="performance_score" type="number" min="0" max="100" class="form-input" value="${c?.performance_score||0}" />
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
    fetchContractors();
  } catch { toast('Something went wrong', 'error'); }
}

async function deleteContractor(id) {
  if (!confirm('Delete this contractor?')) return;
  try {
    const res = await del(API.contractors, id);
    if (res.error) { toast(res.error, 'error'); return; }
    toast('Contractor deleted');
    fetchContractors();
  } catch { toast('Delete failed', 'error'); }
}

/* ============================================================
   FEEDBACK PAGE
   ============================================================ */
let feedbackState = { page: 1, search: '', status: '', priority: '' };

async function loadFeedbackPage() {
  const container = document.getElementById('page-feedback');
  if (!container) return;

  container.innerHTML = `
    <div class="page-header">
      <h2 class="page-title">Feedback &amp; Complaints</h2>
      <button class="btn-primary" onclick="showFeedbackForm()">+ New Entry</button>
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
        <tr><th>Citizen</th><th>Project</th><th>Message</th><th>Category</th><th>Priority</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        ${rows.map(f => `
          <tr>
            <td>${f.citizen_name || '<em style="color:#94a3b8">Anonymous</em>'}</td>
            <td>${f.project_name || '—'}</td>
            <td style="max-width:200px;">${f.message}</td>
            <td>${f.category}</td>
            <td><span class="badge ${pBadge[f.priority]||'badge-resolved'}">${f.priority}</span></td>
            <td><span class="badge ${sBadge[f.status]||'badge-resolved'}">${f.status}</span></td>
            <td style="font-size:.75rem;color:#94a3b8;">${f.created_at?.slice(0,10)}</td>
            <td>
              <div class="action-btns">
                <button class="btn-icon" onclick="updateFeedbackStatus(${f.id},'resolved')" title="Mark Resolved" ${f.status==='resolved'?'disabled':''}>✓</button>
                <button class="btn-icon btn-danger" onclick="deleteFeedback(${f.id})" title="Delete">🗑</button>
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
            ${projects.map(p => `<option value="${p.id}">${p.project_code} — ${p.name}</option>`).join('')}
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

// ── Notification ──
const notifBtn   = document.getElementById('notifBtn');
const notifPanel = document.getElementById('notifPanel');
notifBtn?.addEventListener('click', e => {
  e.stopPropagation();
  notifPanel?.classList.toggle('open');
});
document.addEventListener('click', e => {
  if (!notifPanel?.contains(e.target) && e.target !== notifBtn) {
    notifPanel?.classList.remove('open');
  }
});
document.getElementById('notifClear')?.addEventListener('click', () => {
  notifPanel?.querySelectorAll('.notif-item').forEach(el => el.remove());
  document.querySelector('.notif-badge').style.display = 'none';
});

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
  const contentEl = document.querySelector('.content');
  if (!contentEl) return;

  // Wrap existing dashboard HTML in page-dashboard div,
  // and create hidden containers for other pages
  const dashHTML = contentEl.innerHTML;
  contentEl.innerHTML = `
    <div id="page-dashboard" class="page-section">${dashHTML}</div>
    <div id="page-projects"    class="page-section" style="display:none;"></div>
    <div id="page-milestones"  class="page-section" style="display:none;"><div class="page-header"><h2 class="page-title">Milestones &amp; Tasks</h2></div><p class="empty-state" style="padding:2rem;">Coming soon</p></div>
    <div id="page-budget"      class="page-section" style="display:none;"></div>
    <div id="page-contractors" class="page-section" style="display:none;"></div>
    <div id="page-feedback"    class="page-section" style="display:none;"></div>
    <div id="page-reports"     class="page-section" style="display:none;"><div class="page-header"><h2 class="page-title">Reports</h2></div><p class="empty-state" style="padding:2rem;">Coming soon</p></div>
    <div id="page-users"       class="page-section" style="display:none;"><div class="page-header"><h2 class="page-title">User Management</h2></div><p class="empty-state" style="padding:2rem;">Coming soon</p></div>
    <div id="page-audit"       class="page-section" style="display:none;"><div class="page-header"><h2 class="page-title">Audit Logs</h2></div><p class="empty-state" style="padding:2rem;">Coming soon</p></div>
  `;

  loadDashboard();
});
