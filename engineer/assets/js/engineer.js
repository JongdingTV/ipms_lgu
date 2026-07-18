/* Engineer portal frontend */
const ENGINEER_API = window.BASE_PATH + 'engineer/api/portal.php';
const ENGINEER_WORKFLOW_API = window.BASE_PATH + 'api/workflow.php';
const ENGINEER_USER_API = window.BASE_PATH + 'api/user.php';
const ENGINEER_PROJECTS_API = window.BASE_PATH + 'api/projects.php';
const ENGINEER_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

let engineerCurrentPage = 'dashboard';
let engineerState = {
  summary: null,
  projects: [],
  milestones: [],
  pendingInspections: [],
  paymentRequests: [],
  budgetWatch: [],
};

/* photos/delays/issues/inspections accumulate over time, so they're fetched
   paginated, per-page, rather than bulk-loaded like the small/bounded lists above. */
let engineerListState = {
  photos: { page: 1, perPage: 12 },
  delays: { page: 1, perPage: 10 },
  issues: { page: 1, perPage: 10 },
  inspections: { page: 1, perPage: 10 },
};

function engineerEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function engineerMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function engineerShortMoney(value) {
  return 'PHP ' + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function engineerDate(value) {
  return value ? String(value).slice(0, 10) : '-';
}

function engineerStatus(value) {
  return String(value || '').replaceAll('_', ' ');
}

function engineerBadge(value, label = null) {
  return `<span class="badge status-${engineerEscape(value)}">${engineerEscape(label || engineerStatus(value))}</span>`;
}

function engineerToast(message, type = 'success') {
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

function engineerErrorFrom(data, response) {
  const err = new Error(data?.error || `HTTP ${response.status}`);
  err.fieldErrors = data?.errors || null;
  return err;
}

async function engineerGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const response = await fetch(`${ENGINEER_API}?${qs}`);
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerPostJson(action, body) {
  const response = await fetch(`${ENGINEER_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...ENGINEER_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerPostForm(action, formData) {
  const response = await fetch(`${ENGINEER_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...ENGINEER_CSRF_HEADERS },
    body: formData,
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

/* ---- Shared api/projects.php (Engineering Review, NTP, Completion Inspection,
   Turnover, document versions — the lifecycle-gate actions the shared endpoint
   owns, so project data stays one source of truth instead of duplicated here) --- */
async function engineerGetProjects(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const response = await fetch(`${ENGINEER_PROJECTS_API}?${qs}`);
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerPostProjects(action, body) {
  const response = await fetch(`${ENGINEER_PROJECTS_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...ENGINEER_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

/* ---- Inline field-error rendering -------------------------------------- */

function engineerClearFieldErrors(form) {
  form.querySelectorAll('.field-error-msg').forEach(el => el.remove());
  form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
}

function engineerShowFieldErrors(form, fieldErrors) {
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

/* ---- Dynamic photo rows (repeatable fields) ---------------------------- */

function engineerPhotoRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <input class="form-input" type="text" name="photos[${index}][title]" placeholder="Photo title">
      <input class="form-input" type="text" name="photos[${index}][caption]" placeholder="Caption (optional)">
      <input class="form-input" type="file" name="photo_files[${index}]" accept=".png,.jpg,.jpeg,.webp">
      <button type="button" class="doc-row-remove" aria-label="Remove photo row">&times;</button>
    </div>
  `;
}

function engineerWirePhotoRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', engineerPhotoRowHtml(nextIndex));
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

function engineerOpenModal(title, html) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function engineerCloseModal() {
  document.getElementById('modalOverlay')?.classList.remove('open');
}

function engineerProjectOptions(selected = '') {
  if (!engineerState.projects.length) {
    return '<option value="">No assigned projects</option>';
  }

  return engineerState.projects.map(project => `
    <option value="${project.id}" ${String(selected) === String(project.id) ? 'selected' : ''}>
      ${engineerEscape(project.project_code)} - ${engineerEscape(project.name)}
    </option>
  `).join('');
}

function engineerMilestoneOptions() {
  const rows = engineerState.milestones.filter(milestone => Number(milestone.completed) !== 1);
  if (!rows.length) {
    return '<option value="">No open milestones</option>';
  }

  return rows.map(milestone => `
    <option value="${milestone.project_id}|${milestone.id}">
      ${engineerEscape(milestone.project_code)} - ${engineerEscape(milestone.title)}
    </option>
  `).join('');
}

function engineerProjectCard(project, compact = false) {
  const progress = Number(project.progress || 0);
  const spentPercent = Number(project.budget || 0) > 0
    ? Math.round((Number(project.total_spent || 0) / Number(project.budget || 0)) * 100)
    : 0;

  return `
    <article class="engineer-project-card" data-project-search="${engineerEscape(project.project_code + ' ' + project.name + ' ' + (project.location || ''))}">
      <div class="engineer-project-card-head">
        <div>
          <span class="engineer-project-code">${engineerEscape(project.project_code)}</span>
          <h3 class="engineer-project-title">${engineerEscape(project.name)}</h3>
          <p class="engineer-project-location">${engineerEscape(project.location || 'No location set')}</p>
        </div>
        ${engineerBadge(project.status)}
      </div>
      <div class="engineer-progress">
        <div class="engineer-progress-top">
          <span>Progress</span>
          <strong>${progress}%</strong>
        </div>
        <div class="engineer-progress-track">
          <div class="engineer-progress-fill" style="width:${Math.max(0, Math.min(100, progress))}%"></div>
        </div>
      </div>
      <div class="engineer-project-meta">
        <div class="engineer-meta-item"><span>Contractor</span><strong>${engineerEscape(project.contractor_name || '-')}</strong></div>
        <div class="engineer-meta-item"><span>Milestones</span><strong>${Number(project.completed_milestones || 0)}/${Number(project.milestone_count || 0)}</strong></div>
        <div class="engineer-meta-item"><span>Budget Use</span><strong>${spentPercent}%</strong></div>
        <div class="engineer-meta-item"><span>Open Issues</span><strong>${Number(project.open_issues || 0)}</strong></div>
      </div>
      ${compact ? '' : `
        <div class="engineer-card-actions">
          <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenProject(${project.id})">Details</button>
          <button class="btn-secondary btn-compact" type="button" onclick="engineerShowPage('progress-photos', ${project.id})">Photo</button>
          <button class="btn-primary btn-compact" type="button" onclick="engineerShowPage('status-tracker', ${project.id})">Update Status</button>
          ${project.status === 'assigned' ? `<button class="btn-primary btn-compact" type="button" onclick="engineerOpenIssueNtp(${project.id})">Issue Notice to Proceed</button>` : ''}
          ${['active', 'delayed', 'on_hold'].includes(project.status) ? `<button class="btn-secondary btn-compact" type="button" onclick="engineerRequestCompletionInspection(${project.id})">Request Completion Inspection</button>` : ''}
          ${project.status === 'completion_inspection' ? `
            <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenCompletionDecision(${project.id}, 'return')">Return (Punch-List)</button>
            <button class="btn-primary btn-compact" type="button" onclick="engineerOpenCompletionDecision(${project.id}, 'accept')">Accept as Complete</button>
          ` : ''}
        </div>
      `}
    </article>
  `;
}

let engineerStatusChartInst = null;

function engineerRenderStatusChart(stats) {
  const ctx = document.getElementById('engineerStatusChart')?.getContext('2d');
  if (!ctx) return;
  if (engineerStatusChartInst) engineerStatusChartInst.destroy();

  const assigned = Number(stats.assigned_projects || 0);
  const active = Number(stats.active_projects || 0);
  const delayed = Number(stats.delayed_projects || 0);
  const other = Math.max(0, assigned - active - delayed);

  const segments = [
    { label: 'Active', value: active, color: '#22c55e' },
    { label: 'Delayed', value: delayed, color: '#ef4444' },
    { label: 'Other', value: other, color: '#94a3b8' },
  ];

  engineerStatusChartInst = new Chart(ctx, {
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

  document.getElementById('engineerStatusChartTotal').textContent = assigned;
  document.getElementById('engineerStatusChartLegend').innerHTML = segments.map(s => `
    <div class="budget-legend-item">
      <span class="legend-dot" style="background:${s.color};"></span>
      <span>${s.label} <strong>${s.value}</strong></span>
    </div>
  `).join('');
}

let engineerProgressChartInst = null;

function engineerRenderProgressChart(projects) {
  const ctx = document.getElementById('engineerProgressChart')?.getContext('2d');
  if (!ctx) return;
  if (engineerProgressChartInst) engineerProgressChartInst.destroy();

  const rows = (projects || []).slice(0, 8);
  const barColor = progress => progress >= 90 ? 'rgba(34,197,94,.8)' : progress >= 40 ? 'rgba(59,130,246,.8)' : 'rgba(249,115,22,.8)';
  const gridColor = document.documentElement.getAttribute('data-theme') === 'dark'
    ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)';

  engineerProgressChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: rows.map(p => p.project_code),
      datasets: [{
        data: rows.map(p => Number(p.progress) || 0),
        backgroundColor: rows.map(p => barColor(Number(p.progress) || 0)),
        borderRadius: 5,
        maxBarThickness: 22,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          callbacks: {
            title: items => rows[items[0].dataIndex]?.name || items[0].label,
            label: c => ` ${c.raw}% complete`,
          },
        },
      },
      scales: {
        x: { min: 0, max: 100, ticks: { color: '#94a3b8', font: { size: 11 }, callback: v => v + '%' }, grid: { color: gridColor }, border: { display: false } },
        y: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } }, border: { display: false } },
      },
    },
  });
}

function engineerRenderDashboard() {
  const stats = engineerState.summary?.stats || {};
  document.getElementById('engineerAssignedCount').textContent = stats.assigned_projects || 0;
  document.getElementById('engineerAverageProgress').textContent = `${stats.average_progress || 0}%`;
  document.getElementById('engineerDelayedCount').textContent = stats.delayed_projects || 0;
  document.getElementById('engineerOpenIssues').textContent = stats.open_issues || 0;

  try {
    engineerRenderStatusChart(stats);
    engineerRenderProgressChart(engineerState.projects);
  } catch (error) {
    console.error('Failed to render dashboard charts:', error);
  }

  const projectPreview = document.getElementById('engineerProjectPreview');
  if (projectPreview) {
    projectPreview.innerHTML = engineerState.projects.length
      ? engineerState.projects.slice(0, 3).map(project => engineerProjectCard(project, true)).join('')
      : '<p class="empty-state">No assigned projects yet.</p>';
  }

  const budgetPreview = document.getElementById('engineerBudgetPreview');
  if (budgetPreview) {
    budgetPreview.innerHTML = engineerState.budgetWatch.length
      ? engineerState.budgetWatch.slice(0, 5).map(row => `
        <div class="engineer-mini-row">
          <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.name)}</span>
          <strong>${Number(row.spent_percent || 0)}%</strong>
        </div>
      `).join('')
      : '<p class="empty-state">No budget data yet.</p>';
  }

  const milestonePreview = document.getElementById('engineerMilestonePreview');
  if (milestonePreview) {
    const rows = engineerState.summary?.recent_milestones || [];
    milestonePreview.innerHTML = rows.length
      ? rows.map(row => `
        <div class="engineer-mini-row">
          <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.title)}</span>
          <strong>${Number(row.completed) === 1 ? 'Done' : engineerDate(row.due_date)}</strong>
        </div>
      `).join('')
      : '<p class="empty-state">No milestones yet.</p>';
  }

  const issuePreview = document.getElementById('engineerIssuePreview');
  if (issuePreview) {
    const rows = engineerState.summary?.recent_issues || [];
    issuePreview.innerHTML = rows.length
      ? rows.map(row => `
        <div class="engineer-mini-row">
          <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.issue_type)}</span>
          <strong>${engineerBadge(row.priority)}</strong>
        </div>
      `).join('')
      : '<p class="empty-state">No issue reports yet.</p>';
  }

  const photoPreview = document.getElementById('engineerPhotoPreview');
  if (photoPreview) {
    const rows = engineerState.summary?.recent_photos || [];
    photoPreview.innerHTML = rows.length
      ? rows.map(row => `
        <div class="engineer-photo-row">
          <img class="engineer-photo-thumb" src="${window.BASE_PATH}${engineerEscape(row.file_path)}" alt="">
          <div>
            <strong>${engineerEscape(row.title)}</strong>
            <span>${engineerEscape(row.project_code)} - ${engineerDate(row.created_at)}</span>
          </div>
        </div>
      `).join('')
      : '<p class="empty-state">No photos uploaded yet.</p>';
  }
}

function engineerRenderAssignedProjects() {
  const page = document.getElementById('page-assigned-projects');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">My Assigned Projects</h1>
        <p class="engineer-scope-note">Projects assigned for field monitoring and inspection.</p>
      </div>
      <button class="btn-primary" type="button" onclick="engineerShowPage('status-tracker')">Open Tracker</button>
    </div>
    <div class="engineer-stack">
      ${engineerState.projects.length ? engineerState.projects.map(project => engineerProjectCard(project)).join('') : '<p class="empty-state">No assigned projects yet.</p>'}
    </div>
  `;
}

async function engineerRenderEngineeringReviewPage() {
  const page = document.getElementById('page-engineering-review');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Engineering Review</h1>
        <p class="engineer-scope-note">Newly registered projects awaiting a technical feasibility endorsement, before Budget/HOPE review.</p>
      </div>
    </div>
    <div class="engineer-stack" id="engineeringReviewList"><p class="empty-state">Loading...</p></div>
  `;

  try {
    const result = await engineerGetProjects('list', { status: 'draft' });
    const rows = result.data || [];
    document.getElementById('engineeringReviewList').innerHTML = rows.length
      ? rows.map(p => `
        <article class="engineer-project-card">
          <div class="engineer-project-card-head">
            <div>
              <span class="engineer-project-code">${engineerEscape(p.project_code)}</span>
              <h3 class="engineer-project-title">${engineerEscape(p.name)}</h3>
              <p class="engineer-project-location">${engineerEscape(p.location || 'No location set')}</p>
            </div>
            ${engineerBadge(p.status)}
          </div>
          <div class="engineer-project-meta">
            <div class="engineer-meta-item"><span>Budget</span><strong>${engineerMoney(p.budget)}</strong></div>
            <div class="engineer-meta-item"><span>Schedule</span><strong>${engineerDate(p.start_date)} - ${engineerDate(p.end_date)}</strong></div>
          </div>
          <div class="engineer-card-actions">
            <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenProjectPreview(${p.id})">View Details</button>
            <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenEngineeringDecision(${p.id}, 'return')">Return</button>
            <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenEngineeringDecision(${p.id}, 'reject')">Reject</button>
            <button class="btn-primary btn-compact" type="button" onclick="engineerOpenEngineeringDecision(${p.id}, 'endorse')">Endorse</button>
          </div>
        </article>
      `).join('')
      : '<p class="empty-state">No projects are currently awaiting engineering review.</p>';
  } catch (error) {
    document.getElementById('engineeringReviewList').innerHTML = '<p class="empty-state">Failed to load projects.</p>';
  }
}

async function engineerOpenProjectPreview(projectId) {
  try {
    const project = await engineerGetProjects('single', { id: projectId });
    engineerOpenModal(`${engineerEscape(project.project_code)} — ${engineerEscape(project.name)}`, `
      <div class="engineer-detail-grid">
        <div class="engineer-detail-box"><span>Location</span><strong>${engineerEscape(project.location || '-')}</strong></div>
        <div class="engineer-detail-box"><span>Budget</span><strong>${engineerMoney(project.budget)}</strong></div>
        <div class="engineer-detail-box"><span>Schedule</span><strong>${engineerDate(project.start_date)} - ${engineerDate(project.end_date)}</strong></div>
        <div class="engineer-detail-box"><span>Status</span><strong>${engineerStatus(project.status)}</strong></div>
      </div>
      <h4 style="margin: 12px 0 8px; color:#1e293b;">Description</h4>
      <p style="font-size:.85rem; color:#475569;">${engineerEscape(project.description || 'No description provided.')}</p>
      <h4 style="margin: 16px 0 8px; color:#1e293b;">Supporting Documents</h4>
      <div class="engineer-mini-list">
        ${(project.documents || []).length ? project.documents.map(doc => `
          <div class="engineer-mini-row">
            <span><a href="${window.BASE_PATH}${engineerEscape(doc.file_path)}" target="_blank" rel="noopener">${engineerEscape(doc.title)}</a> (${engineerEscape(doc.document_type)})</span>
            <strong>v${Number(doc.version || 1)}</strong>
          </div>
        `).join('') : '<p class="empty-state">No documents attached.</p>'}
      </div>
    `);
  } catch (error) {
    engineerToast(error.message || 'Failed to load project details.', 'error');
  }
}

function engineerOpenEngineeringDecision(id, decision) {
  const labels = { endorse: 'Endorse', return: 'Return for Revision', reject: 'Reject' };
  const reasonRequired = decision !== 'endorse';

  engineerOpenModal(`${labels[decision]} Project`, `
    <form id="engineeringDecisionForm">
      <div class="form-group">
        <label>Reason${reasonRequired ? ' *' : ' (optional)'}</label>
        <textarea name="reason" class="form-input" rows="4" placeholder="Explain the decision" ${reasonRequired ? 'required' : ''}></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="engineerCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm ${labels[decision]}</button>
      </div>
    </form>
  `);

  document.getElementById('engineeringDecisionForm').addEventListener('submit', async event => {
    event.preventDefault();
    const reason = new FormData(event.target).get('reason') || '';
    try {
      const result = await engineerPostProjects('engineering_review', { project_id: id, decision, reason });
      engineerToast(`Project ${result.status}.`);
      engineerCloseModal();
      engineerRenderEngineeringReviewPage();
    } catch (error) {
      engineerToast(error.message || 'Failed to record decision.', 'error');
    }
  });
}

/* ---- Notice to Proceed / Completion Inspection (contextual actions on an
   already-assigned project — see engineerProjectCard's action buttons) ---- */

function engineerOpenIssueNtp(id) {
  engineerOpenModal('Issue Notice to Proceed', `
    <form id="engineerNtpForm">
      <p style="font-size:.85rem; color:#475569; margin-bottom:12px;">Confirms the site is ready for the contractor to begin. The contract's implementation period starts from today's date, not the award date.</p>
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" class="form-input" rows="3" placeholder="Site readiness notes"></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="engineerCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm &amp; Issue NTP</button>
      </div>
    </form>
  `);

  document.getElementById('engineerNtpForm').addEventListener('submit', async event => {
    event.preventDefault();
    const notes = new FormData(event.target).get('notes') || '';
    try {
      await engineerPostProjects('issue_ntp', { project_id: id, notes });
      engineerToast('Notice to Proceed issued.');
      engineerCloseModal();
      await engineerRefreshData();
      engineerShowPage(engineerCurrentPage);
    } catch (error) {
      engineerToast(error.message || 'Failed to issue Notice to Proceed.', 'error');
    }
  });
}

async function engineerRequestCompletionInspection(id) {
  if (!confirm('Submit this project for completion inspection? This should only be done once the final milestone is reached.')) return;
  try {
    await engineerPostProjects('request_completion_inspection', { project_id: id });
    engineerToast('Project submitted for completion inspection.');
    await engineerRefreshData();
    engineerShowPage(engineerCurrentPage);
  } catch (error) {
    engineerToast(error.message || 'Failed to submit for completion inspection.', 'error');
  }
}

function engineerOpenCompletionDecision(id, decision) {
  const labels = { accept: 'Accept as Complete', return: 'Return with Punch-List' };
  const reasonRequired = decision === 'return';

  engineerOpenModal(labels[decision], `
    <form id="engineerCompletionForm">
      <div class="form-group">
        <label>${decision === 'return' ? 'Punch-list items *' : 'Notes (optional)'}</label>
        <textarea name="reason" class="form-input" rows="4" placeholder="${decision === 'return' ? 'List what still needs correction' : 'Final inspection notes'}" ${reasonRequired ? 'required' : ''}></textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="engineerCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Confirm</button>
      </div>
    </form>
  `);

  document.getElementById('engineerCompletionForm').addEventListener('submit', async event => {
    event.preventDefault();
    const reason = new FormData(event.target).get('reason') || '';
    try {
      const result = await engineerPostProjects('completion_decide', { project_id: id, decision, reason });
      engineerToast(`Project ${result.status}.`);
      engineerCloseModal();
      await engineerRefreshData();
      engineerShowPage(engineerCurrentPage);
    } catch (error) {
      engineerToast(error.message || 'Failed to record completion decision.', 'error');
    }
  });
}

function engineerRenderMilestonePage() {
  const page = document.getElementById('page-milestone-update');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Milestone Update Page</h1>
        <p class="engineer-scope-note">Update assigned project milestones from field inspection.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>Milestone Update</h2>
        <form id="engineerMilestoneForm">
          <div class="form-group">
            <label>Open Milestone</label>
            <select class="form-input" name="milestone_ref" required>${engineerMilestoneOptions()}</select>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Status</label>
            <select class="form-input" name="completed">
              <option value="1">Completed</option>
              <option value="0">Reopened / Not Completed</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Inspection Remarks</label>
            <textarea class="form-input" name="remarks" rows="4" placeholder="Field validation notes"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Milestone Update</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Assigned Milestones</h2>
        <div class="engineer-mini-list">
          ${engineerRenderMilestoneRows()}
        </div>
      </article>
    </section>
  `;

  document.getElementById('engineerMilestoneForm').addEventListener('submit', engineerSubmitMilestone);
}

function engineerRenderMilestoneRows() {
  if (!engineerState.milestones.length) {
    return '<p class="empty-state">No milestones available.</p>';
  }

  return engineerState.milestones.map(row => `
    <div class="engineer-mini-row">
      <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.title)}</span>
      <strong>${Number(row.completed) === 1 ? 'Done' : engineerDate(row.due_date)}</strong>
    </div>
  `).join('');
}

async function engineerWorkflowGet(action = 'summary') {
  const response = await fetch(`${ENGINEER_WORKFLOW_API}?action=${encodeURIComponent(action)}`);
  const data = await response.json();
  if (!response.ok || data.error) {
    throw new Error(data.error || `HTTP ${response.status}`);
  }
  return data;
}

async function engineerWorkflowPost(action, body) {
  const response = await fetch(`${ENGINEER_WORKFLOW_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...ENGINEER_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) {
    throw new Error(data.error || `HTTP ${response.status}`);
  }
  return data;
}

function engineerInspectionOptions() {
  const rows = engineerState.pendingInspections;
  if (!rows.length) {
    return '<option value="">No contractor reports pending inspection</option>';
  }

  return rows.map(row => `
    <option value="${row.report_id}">
      ${engineerEscape(row.project_code)} - ${engineerDate(row.report_date)} - ${Number(row.progress_percent || 0)}%
    </option>
  `).join('');
}

function engineerRenderInspectionPage() {
  const page = document.getElementById('page-inspection-review');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Inspection Review</h1>
        <p class="engineer-scope-note">Validate contractor progress reports for assigned projects.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>New Inspection</h2>
        <form id="engineerInspectionForm">
          <div class="form-group">
            <label>Contractor Report</label>
            <select class="form-input" name="progress_report_id" required>${engineerInspectionOptions()}</select>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>Inspection Date</label>
              <input class="form-input" type="date" name="inspection_date" required value="${new Date().toISOString().slice(0, 10)}">
            </div>
            <div class="form-group">
              <label>Actual Progress Percent</label>
              <input class="form-input" type="number" min="0" max="100" name="actual_progress_percent" required placeholder="0">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Recommendation</label>
            <select class="form-input" name="recommendation">
              <option value="approved">Approved</option>
              <option value="needs_correction">Needs Correction</option>
              <option value="for_reinspection">For Reinspection</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Findings</label>
            <textarea class="form-input" name="findings" rows="4" required placeholder="Inspection findings and validation notes"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Inspection</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Contractor Reports</h2>
        <div class="engineer-mini-list" id="engineerInspectionsList"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="engineerInspectionsPager"></div>
      </article>
    </section>
  `;

  document.getElementById('engineerInspectionForm').addEventListener('submit', engineerSubmitInspection);
  engineerLoadInspectionsList();
}

async function engineerLoadInspectionsList() {
  const container = document.getElementById('engineerInspectionsList');
  const pager = document.getElementById('engineerInspectionsPager');
  if (!container) return;
  const state = engineerListState.inspections;

  try {
    const result = await engineerGet('inspections', { page: state.page, per_page: state.perPage });

    container.innerHTML = result.data.length ? result.data.map(row => `
      <div class="engineer-mini-row">
        <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.contractor_name)} - ${engineerDate(row.report_date)}</span>
        <strong>${engineerBadge(row.recommendation || row.report_status, row.recommendation ? engineerStatus(row.recommendation) : engineerStatus(row.report_status))}</strong>
      </div>
    `).join('') : '<p class="empty-state">No contractor reports submitted yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerListState.inspections.page = nextPage; engineerLoadInspectionsList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load contractor reports.</p>';
  }
}

function engineerRenderPaymentReviewPage() {
  const page = document.getElementById('page-payment-review');
  const rows = engineerState.paymentRequests || [];

  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Payment Review</h1>
        <p class="engineer-scope-note">Technical review of contractor payment requests for assigned projects.</p>
      </div>
      <button class="btn-secondary" type="button" onclick="engineerRefreshData().then(() => engineerShowPage('payment-review'))">Refresh</button>
    </div>
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Billing</th>
            <th>Project</th>
            <th>Contractor</th>
            <th>Progress Report</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          ${rows.length ? rows.map(row => `
            <tr>
              <td><span class="proj-id">${engineerEscape(row.billing_no)}</span><br><small>${engineerDate(row.submitted_at)}</small></td>
              <td><strong>${engineerEscape(row.project_code)}</strong><br><small>${engineerEscape(row.project_name)}</small></td>
              <td>${engineerEscape(row.contractor_name)}</td>
              <td>${engineerDate(row.report_date)}<br><small>${Number(row.progress_percent || 0)}%</small></td>
              <td>${engineerMoney(row.requested_amount)}</td>
              <td>${engineerBadge(row.status)}</td>
              <td>
                <button class="btn-primary btn-compact" type="button" onclick="engineerOpenPaymentReview(${row.id}, 'approve')">Approve</button>
                <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenPaymentReview(${row.id}, 'return')">Return</button>
                <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenPaymentReview(${row.id}, 'reject')">Reject</button>
              </td>
            </tr>
          `).join('') : '<tr><td colspan="7"><p class="empty-state">No payment requests for assigned projects yet.</p></td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

function engineerOpenPaymentReview(paymentId, recommendation) {
  engineerOpenModal(`${engineerStatus(recommendation)} Payment Request`, `
    <form id="engineerPaymentReviewForm">
      <div class="form-group">
        <label>Recommendation</label>
        <input class="form-input" disabled value="${engineerStatus(recommendation)}">
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Technical Review Remarks</label>
        <textarea class="form-input" name="remarks" rows="4" placeholder="Progress validation, inspection notes, or reason"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="engineerCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Save Review</button>
      </div>
    </form>
  `);

  document.getElementById('engineerPaymentReviewForm').addEventListener('submit', event => engineerSubmitPaymentReview(event, paymentId, recommendation));
}

function engineerRenderPhotosPage(selectedProjectId = '') {
  const page = document.getElementById('page-progress-photos');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Upload Progress Photos</h1>
        <p class="engineer-scope-note">Attach field photos to assigned projects only.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>New Progress Photos</h2>
        <form id="engineerPhotoForm" enctype="multipart/form-data">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${engineerProjectOptions(selectedProjectId)}</select>
          </div>
          <div class="doc-section">
            <label>Photos</label>
            <div class="doc-rows" id="docRows">${engineerPhotoRowHtml(0)}</div>
            <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another photo</button>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Upload Photo(s)</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Uploaded Photos</h2>
        <div class="engineer-mini-list" id="engineerPhotosList"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="engineerPhotosPager"></div>
      </article>
    </section>
  `;

  engineerWirePhotoRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));
  document.getElementById('engineerPhotoForm').addEventListener('submit', engineerSubmitPhoto);
  engineerLoadPhotosList();
}

async function engineerLoadPhotosList() {
  const container = document.getElementById('engineerPhotosList');
  const pager = document.getElementById('engineerPhotosPager');
  if (!container) return;
  const state = engineerListState.photos;

  try {
    const result = await engineerGet('photos', { page: state.page, per_page: state.perPage });

    container.innerHTML = result.data.length ? result.data.map(row => `
      <a class="engineer-photo-row" href="${window.BASE_PATH}${engineerEscape(row.file_path)}" target="_blank" rel="noopener">
        <img class="engineer-photo-thumb" src="${window.BASE_PATH}${engineerEscape(row.file_path)}" alt="">
        <div>
          <strong>${engineerEscape(row.title)}</strong>
          <span>${engineerEscape(row.project_code)} - ${engineerDate(row.created_at)}</span>
        </div>
      </a>
    `).join('') : '<p class="empty-state">No photos uploaded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerListState.photos.page = nextPage; engineerLoadPhotosList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load photos.</p>';
  }
}

function engineerRenderDelayPage() {
  const page = document.getElementById('page-delay-report');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Delay Report Form</h1>
        <p class="engineer-scope-note">Flag schedule risk and mitigation for assigned projects.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>New Delay Report</h2>
        <form id="engineerDelayForm">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${engineerProjectOptions()}</select>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>Severity</label>
              <select class="form-input" name="severity">
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="form-group">
              <label>Estimated Impact Days</label>
              <input class="form-input" type="number" min="0" name="impact_days" value="0">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Cause</label>
            <textarea class="form-input" name="cause" rows="4" required placeholder="Cause of delay"></textarea>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Mitigation Plan</label>
            <textarea class="form-input" name="mitigation_plan" rows="3" placeholder="Recommended catch-up action"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Submit Delay Report</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Delay Reports</h2>
        <div class="engineer-mini-list" id="engineerDelaysList"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="engineerDelaysPager"></div>
      </article>
    </section>
  `;

  document.getElementById('engineerDelayForm').addEventListener('submit', engineerSubmitDelay);
  engineerLoadDelaysList();
}

async function engineerLoadDelaysList() {
  const container = document.getElementById('engineerDelaysList');
  const pager = document.getElementById('engineerDelaysPager');
  if (!container) return;
  const state = engineerListState.delays;

  try {
    const result = await engineerGet('delays', { page: state.page, per_page: state.perPage });

    container.innerHTML = result.data.length ? result.data.map(row => `
      <div class="engineer-mini-row">
        <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.cause).slice(0, 68)}</span>
        <strong>${engineerBadge(row.severity)}</strong>
      </div>
    `).join('') : '<p class="empty-state">No delay reports yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerListState.delays.page = nextPage; engineerLoadDelaysList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load delay reports.</p>';
  }
}

function engineerRenderIssuePage() {
  const page = document.getElementById('page-issue-reporting');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Issue Reporting</h1>
        <p class="engineer-scope-note">Submit field issues, blockers, and recommended action.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>New Issue Report</h2>
        <form id="engineerIssueForm">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${engineerProjectOptions()}</select>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>Issue Type</label>
              <select class="form-input" name="issue_type">
                <option>Site Issue</option>
                <option>Quality Concern</option>
                <option>Safety Concern</option>
                <option>Material Concern</option>
                <option>Right-of-way Concern</option>
              </select>
            </div>
            <div class="form-group">
              <label>Priority</label>
              <select class="form-input" name="priority">
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Description</label>
            <textarea class="form-input" name="description" rows="4" required placeholder="Describe the issue"></textarea>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Recommended Action</label>
            <textarea class="form-input" name="recommended_action" rows="3" placeholder="Optional"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Submit Issue</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Issue Reports</h2>
        <div class="engineer-mini-list" id="engineerIssuesList"><p class="empty-state">Loading...</p></div>
        <div class="pagination-wrap" id="engineerIssuesPager"></div>
      </article>
    </section>
  `;

  document.getElementById('engineerIssueForm').addEventListener('submit', engineerSubmitIssue);
  engineerLoadIssuesList();
}

async function engineerLoadIssuesList() {
  const container = document.getElementById('engineerIssuesList');
  const pager = document.getElementById('engineerIssuesPager');
  if (!container) return;
  const state = engineerListState.issues;

  try {
    const result = await engineerGet('issues', { page: state.page, per_page: state.perPage });

    container.innerHTML = result.data.length ? result.data.map(row => `
      <div class="engineer-mini-row">
        <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.issue_type)}</span>
        <strong>${engineerBadge(row.priority)}</strong>
      </div>
    `).join('') : '<p class="empty-state">No issue reports yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerListState.issues.page = nextPage; engineerLoadIssuesList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load issue reports.</p>';
  }
}

function engineerRenderStatusTracker(selectedProjectId = '') {
  const page = document.getElementById('page-status-tracker');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Project Status Tracker</h1>
        <p class="engineer-scope-note">Update field status while keeping budget values read-only.</p>
      </div>
    </div>
    <section class="engineer-layout">
      <article class="engineer-form-card">
        <h2>Status Update</h2>
        <form id="engineerStatusForm">
          <div class="form-group">
            <label>Assigned Project</label>
            <select class="form-input" name="project_id" required>${engineerProjectOptions(selectedProjectId)}</select>
          </div>
          <div class="form-grid" style="margin-top:12px;">
            <div class="form-group">
              <label>Progress Percent</label>
              <input class="form-input" type="number" min="0" max="100" name="progress_percent" required placeholder="0">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select class="form-input" name="status">
                <option value="active">Active</option>
                <option value="planning">Planning</option>
                <option value="delayed">Delayed</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label>Field Notes</label>
            <textarea class="form-input" name="notes" rows="4" placeholder="Inspection notes"></textarea>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Status Update</button>
          </div>
        </form>
      </article>
      <article class="engineer-history-card">
        <h2>Read-only Budget Watch</h2>
        <p class="engineer-budget-note">Engineers can inspect budget utilization here, but cannot edit budget or expenses.</p>
        <div class="engineer-mini-list" style="margin-top:12px;">${engineerRenderBudgetRows()}</div>
      </article>
    </section>
    <div class="table-card" style="margin-top:16px;">${engineerRenderTrackerTable()}</div>
  `;

  document.getElementById('engineerStatusForm').addEventListener('submit', engineerSubmitStatus);
}

function engineerRenderBudgetRows() {
  if (!engineerState.budgetWatch.length) {
    return '<p class="empty-state">No budget data yet.</p>';
  }

  return engineerState.budgetWatch.map(row => `
    <div class="engineer-mini-row">
      <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.name)}</span>
      <strong>${engineerShortMoney(row.spent)} / ${engineerShortMoney(row.budget)}</strong>
    </div>
  `).join('');
}

function engineerRenderTrackerTable() {
  if (!engineerState.projects.length) {
    return '<p class="empty-state">No assigned projects yet.</p>';
  }

  return `
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Project</th>
          <th>Progress</th>
          <th>Status</th>
          <th>Budget</th>
          <th>Spent</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${engineerState.projects.map(project => `
          <tr>
            <td><span class="proj-id">${engineerEscape(project.project_code)}</span></td>
            <td><strong>${engineerEscape(project.name)}</strong><br><small style="color:#94a3b8">${engineerEscape(project.location || '')}</small></td>
            <td>${Number(project.progress || 0)}%</td>
            <td>${engineerBadge(project.status)}</td>
            <td>${engineerMoney(project.budget)}</td>
            <td>${engineerMoney(project.total_spent)}</td>
            <td><button class="btn-secondary btn-compact" type="button" onclick="engineerOpenProject(${project.id})">Details</button></td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

async function engineerOpenProject(projectId) {
  try {
    const response = await engineerGet('project', { id: projectId });
    const project = response.data;
    engineerOpenModal(`${project.project_code} Details`, `
      <div class="engineer-detail-grid">
        <div class="engineer-detail-box"><span>Project</span><strong>${engineerEscape(project.name)}</strong></div>
        <div class="engineer-detail-box"><span>Location</span><strong>${engineerEscape(project.location || '-')}</strong></div>
        <div class="engineer-detail-box"><span>Status</span><strong>${engineerStatus(project.status)}</strong></div>
        <div class="engineer-detail-box"><span>Progress</span><strong>${Number(project.progress || 0)}%</strong></div>
        <div class="engineer-detail-box"><span>Budget</span><strong>${engineerMoney(project.budget)}</strong></div>
        <div class="engineer-detail-box"><span>Spent</span><strong>${engineerMoney(project.total_spent)}</strong></div>
      </div>
      <h4 style="margin: 12px 0 8px; color:#1e293b;">Milestones</h4>
      <div class="engineer-mini-list">
        ${project.milestones.length ? project.milestones.map(row => `
          <div class="engineer-mini-row">
            <span>${engineerEscape(row.title)} (${engineerDate(row.due_date)})</span>
            <strong>${Number(row.completed) === 1 ? 'Done' : 'Open'}</strong>
          </div>
        `).join('') : '<p class="empty-state">No milestones recorded.</p>'}
      </div>
      <h4 style="margin: 16px 0 8px; color:#1e293b;">Budget Records (Read-only)</h4>
      <div class="engineer-mini-list">
        ${project.budget_records.length ? project.budget_records.slice(0, 6).map(row => `
          <div class="engineer-mini-row">
            <span>${engineerEscape(row.category || 'Expense')} - ${engineerEscape(row.description || '')}</span>
            <strong>${engineerMoney(row.amount)}</strong>
          </div>
        `).join('') : '<p class="empty-state">No budget records available.</p>'}
      </div>
    `);
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

async function engineerRefreshData() {
  const [summary, projects, milestones, pendingInspections, workflow, tracker] = await Promise.all([
    engineerGet('summary'),
    engineerGet('projects'),
    engineerGet('milestones'),
    engineerGet('pending_inspections'),
    engineerWorkflowGet('summary'),
    engineerGet('tracker'),
  ]);

  engineerState.summary = summary;
  engineerState.projects = projects.data || [];
  engineerState.milestones = milestones.data || [];
  engineerState.pendingInspections = pendingInspections.data || [];
  engineerState.paymentRequests = workflow.payment_requests || [];
  engineerState.budgetWatch = tracker.budget_watch || summary.budget_watch || [];
  engineerRenderDashboard();
}

function engineerShowPage(page, selectedProjectId = '') {
  engineerCurrentPage = page;
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });
  document.querySelectorAll('.page-section').forEach(section => {
    section.style.display = section.id === `page-${page}` ? 'block' : 'none';
  });

  if (page === 'dashboard') engineerRenderDashboard();
  if (page === 'assigned-projects') engineerRenderAssignedProjects();
  if (page === 'engineering-review') engineerRenderEngineeringReviewPage();
  if (page === 'milestone-update') engineerRenderMilestonePage();
  if (page === 'inspection-review') engineerRenderInspectionPage();
  if (page === 'payment-review') engineerRenderPaymentReviewPage();
  if (page === 'progress-photos') engineerRenderPhotosPage(selectedProjectId);
  if (page === 'delay-report') engineerRenderDelayPage();
  if (page === 'issue-reporting') engineerRenderIssuePage();
  if (page === 'status-tracker') engineerRenderStatusTracker(selectedProjectId);
}

async function engineerSubmitMilestone(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);
  const [projectId, milestoneId] = String(form.get('milestone_ref') || '').split('|');

  try {
    await engineerPostJson('milestone', {
      project_id: projectId,
      milestone_id: milestoneId,
      completed: form.get('completed') === '1',
      remarks: form.get('remarks'),
    });
    engineerToast('Milestone update saved.');
    await engineerRefreshData();
    engineerShowPage('milestone-update');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitInspection(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostJson('inspection', {
      progress_report_id: form.get('progress_report_id'),
      inspection_date: form.get('inspection_date'),
      actual_progress_percent: form.get('actual_progress_percent'),
      recommendation: form.get('recommendation'),
      findings: form.get('findings'),
    });
    engineerToast('Inspection saved.');
    formEl.reset();
    await engineerRefreshData();
    engineerListState.inspections.page = 1;
    engineerShowPage('inspection-review');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitPaymentReview(event, paymentId, recommendation) {
  event.preventDefault();
  const form = new FormData(event.target);

  try {
    await engineerWorkflowPost('payment_review', {
      payment_request_id: paymentId,
      recommendation,
      remarks: form.get('remarks'),
    });
    engineerCloseModal();
    engineerToast('Payment review saved.');
    await engineerRefreshData();
    engineerShowPage('payment-review');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitPhoto(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostForm('photo', form);
    engineerToast('Progress photo(s) uploaded.');
    formEl.reset();
    engineerListState.photos.page = 1;
    await engineerLoadPhotosList();
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitDelay(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostJson('delay', {
      project_id: form.get('project_id'),
      severity: form.get('severity'),
      impact_days: form.get('impact_days'),
      cause: form.get('cause'),
      mitigation_plan: form.get('mitigation_plan'),
    });
    engineerToast('Delay report submitted.');
    formEl.reset();
    await engineerRefreshData();
    engineerListState.delays.page = 1;
    engineerShowPage('delay-report');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitIssue(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostJson('issue', {
      project_id: form.get('project_id'),
      issue_type: form.get('issue_type'),
      priority: form.get('priority'),
      description: form.get('description'),
      recommended_action: form.get('recommended_action'),
    });
    engineerToast('Issue report submitted.');
    formEl.reset();
    await engineerRefreshData();
    engineerListState.issues.page = 1;
    engineerShowPage('issue-reporting');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

async function engineerSubmitStatus(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostJson('status', {
      project_id: form.get('project_id'),
      progress_percent: form.get('progress_percent'),
      status: form.get('status'),
      notes: form.get('notes'),
    });
    engineerToast('Project status updated.');
    formEl.reset();
    await engineerRefreshData();
    engineerShowPage('status-tracker');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

function engineerWireShell() {
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('open');
  });

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      engineerShowPage(item.dataset.page || 'dashboard');
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

  document.getElementById('modalClose')?.addEventListener('click', engineerCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') engineerCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') engineerCloseModal();
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
    const response = await fetch(ENGINEER_USER_API);
    const result = await response.json();
    const user = result.data || {};
    engineerOpenModal('Profile Settings', `
      <form id="engineerProfileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-input" name="full_name" required value="${engineerEscape(user.full_name)}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-input" type="email" name="email" required value="${engineerEscape(user.email)}">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-input" disabled value="${engineerEscape(user.username)}">
          </div>
          <div class="form-group">
            <label>Role</label>
            <input class="form-input" disabled value="Engineer">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="engineerCloseModal()">Cancel</button>
          <button class="btn-primary" type="submit">Update Profile</button>
        </div>
      </form>
    `);
    document.getElementById('engineerProfileForm').addEventListener('submit', submitProfileForm);
  } catch {
    engineerToast('Failed to load profile.', 'error');
  }
}

function showChangePassword() {
  engineerOpenModal('Change Password', `
    <form id="engineerPasswordForm">
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
        <button class="btn-secondary" type="button" onclick="engineerCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Change Password</button>
      </div>
    </form>
  `);
  document.getElementById('engineerPasswordForm').addEventListener('submit', submitPasswordForm);
}

async function submitProfileForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const body = new URLSearchParams({
    full_name: form.get('full_name'),
    email: form.get('email'),
  });

  try {
    const response = await fetch(ENGINEER_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...ENGINEER_CSRF_HEADERS },
      body,
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    document.querySelector('.user-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-email').textContent = form.get('email');
    engineerCloseModal();
    engineerToast('Profile updated.');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

async function submitPasswordForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  if (form.get('new_password') !== form.get('confirm_password')) {
    engineerToast('New passwords do not match.', 'error');
    return;
  }

  try {
    const response = await fetch(ENGINEER_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...ENGINEER_CSRF_HEADERS },
      body: new URLSearchParams({
        current_password: form.get('current_password'),
        new_password: form.get('new_password'),
      }),
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    engineerCloseModal();
    engineerToast('Password changed.');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

window.engineerShowPage = engineerShowPage;
window.engineerOpenProject = engineerOpenProject;
window.engineerCloseModal = engineerCloseModal;
window.engineerOpenPaymentReview = engineerOpenPaymentReview;
window.engineerOpenProjectPreview = engineerOpenProjectPreview;
window.engineerOpenEngineeringDecision = engineerOpenEngineeringDecision;
window.engineerOpenIssueNtp = engineerOpenIssueNtp;
window.engineerRequestCompletionInspection = engineerRequestCompletionInspection;
window.engineerOpenCompletionDecision = engineerOpenCompletionDecision;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  engineerWireShell();
  try {
    await engineerRefreshData();
  } catch (error) {
    engineerToast(error.message, 'error');
  }
});
