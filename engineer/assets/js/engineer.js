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
  paymentRequests: [],
  budgetWatch: [],
};

/* photos/delays/issues accumulate over time, so they're fetched paginated,
   per-page, rather than bulk-loaded like the small/bounded lists above.
   (Mobile Inspection's own paginated lists use miListState, defined near its
   other code, not this one — see engineerRenderInspectionPage() and friends.) */
let engineerListState = {
  photos: { page: 1, perPage: 12 },
  delays: { page: 1, perPage: 10 },
  issues: { page: 1, perPage: 10 },
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
  // accept="image/*" capture="environment" — on a phone this opens the
  // camera directly instead of a generic file browser (server still
  // enforces the strict png/jpg/jpeg/webp allowlist in ENGINEER_PHOTO_EXTENSIONS).
  return `
    <div class="doc-row" data-doc-index="${index}">
      <input class="form-input" type="text" name="photos[${index}][title]" placeholder="Photo title">
      <input class="form-input" type="text" name="photos[${index}][caption]" placeholder="Caption (optional)">
      <input class="form-input" type="file" name="photo_files[${index}]" accept="image/*" capture="environment">
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
  taskCenterInitDashboardWidget();

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

async function engineerRenderAvailableProjects() {
  const page = document.getElementById('page-available-projects');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Available Projects</h1>
        <p class="engineer-scope-note">Projects in your district with no field-monitoring engineer assigned yet. Accepting one adds it to your Assigned Projects.</p>
      </div>
    </div>
    <div class="engineer-stack" id="availableProjectsList"><p class="empty-state">Loading...</p></div>
  `;

  try {
    const result = await engineerGet('available_projects');
    const rows = result.data || [];
    document.getElementById('availableProjectsList').innerHTML = rows.length
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
            <div class="engineer-meta-item"><span>Contractor</span><strong>${engineerEscape(p.contractor_name || '-')}</strong></div>
          </div>
          <div class="engineer-card-actions">
            <button class="btn-secondary btn-compact" type="button" onclick="engineerOpenProjectPreview(${p.id})">View Details</button>
            <button class="btn-primary btn-compact" type="button" onclick="engineerAcceptProject(${p.id})">Accept</button>
          </div>
        </article>
      `).join('')
      : '<p class="empty-state">No available projects in your district right now.</p>';
  } catch (error) {
    document.getElementById('availableProjectsList').innerHTML = '<p class="empty-state">Failed to load projects.</p>';
  }
}

async function engineerAcceptProject(projectId) {
  try {
    await engineerPostJson('accept_project', { project_id: projectId });
    engineerToast('Project accepted — added to My Assigned Projects.');
    await engineerRefreshData();
    engineerRenderAvailableProjects();
  } catch (error) {
    engineerToast(error.message || 'Unable to accept project', 'error');
  }
}

async function engineerRenderEngineeringReviewPage() {
  const page = document.getElementById('page-engineering-review');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Engineering Review</h1>
        <p class="engineer-scope-note">Newly registered projects awaiting a technical feasibility endorsement, before Budget/City Mayor review.</p>
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
      <h4 style="margin: 16px 0 8px; color:#1e293b;">Document Checklist</h4>
      <div id="engineerProjectDocChecklist"></div>
      <h4 style="margin: 16px 0 8px; color:#1e293b;">Activity Timeline</h4>
      <div id="projectTimelineSection"></div>
    `);
    renderProjectTimeline('projectTimelineSection', project.id);
    documentChecklistInit('engineerProjectDocChecklist', project.id);
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
      <div class="engineer-stack">
        <article class="engineer-form-card">
          <h2>Add New Milestone</h2>
          <form id="engineerAddMilestoneForm">
            <div class="form-group">
              <label>Project</label>
              <select class="form-input" name="project_id" required>${engineerProjectOptions()}</select>
            </div>
            <div class="form-group" style="margin-top:12px;">
              <label>Milestone Title</label>
              <input class="form-input" type="text" name="title" maxlength="200" placeholder="e.g. Foundation works complete" required>
            </div>
            <div class="form-group" style="margin-top:12px;">
              <label>Due Date</label>
              <input class="form-input" type="date" name="due_date">
            </div>
            <div class="form-actions">
              <button class="btn-primary" type="submit">Add Milestone</button>
            </div>
          </form>
        </article>
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
      </div>
      <article class="engineer-history-card">
        <h2>Assigned Milestones</h2>
        <div class="engineer-mini-list">
          ${engineerRenderMilestoneRows()}
        </div>
      </article>
    </section>
  `;

  document.getElementById('engineerAddMilestoneForm').addEventListener('submit', engineerSubmitAddMilestone);
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

/* ============================================================
   Mobile Inspection — extends this same "Inspection Review" sidebar entry
   (data-page="inspection-review" is unchanged) into a small in-page tab bar:
   My Inspections / Start-Continue / Inspection Review / Returned / History.
   Backed by engineer/api/mobile-inspection.php, which reads/writes the same
   native `inspections` table the old single-shot form used — this is a
   richer, resumable way to reach the same submission, not a parallel system.
   ============================================================ */
const ENGINEER_MI_API = window.BASE_PATH + 'engineer/api/mobile-inspection.php';

async function engineerMiGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const response = await fetch(`${ENGINEER_MI_API}?${qs}`);
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerMiPostJson(action, body) {
  const response = await fetch(`${ENGINEER_MI_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...ENGINEER_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerMiPostForm(action, formData) {
  const response = await fetch(`${ENGINEER_MI_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...ENGINEER_CSRF_HEADERS },
    body: formData,
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

function miTimeLabel(value) {
  if (!value) return '';
  const d = new Date(String(value).replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/* ---- Tab shell -------------------------------------------------------- */

function engineerRenderInspectionPage() {
  const page = document.getElementById('page-inspection-review');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Inspections</h1>
        <p class="engineer-scope-note">Start a field inspection from your phone at the site, or review what's already been submitted for your assigned projects.</p>
      </div>
    </div>
    <div class="mi-tabbar" role="tablist">
      <button type="button" class="mi-tab" data-mi-tab="my-inspections">My Inspections</button>
      <button type="button" class="mi-tab" data-mi-tab="wizard">Start / Continue</button>
      <button type="button" class="mi-tab" data-mi-tab="review">Inspection Review</button>
      <button type="button" class="mi-tab" data-mi-tab="returned">Returned</button>
      <button type="button" class="mi-tab" data-mi-tab="history">History</button>
    </div>
    <div id="miTabBody"></div>
  `;

  page.querySelectorAll('.mi-tab').forEach(btn => {
    btn.addEventListener('click', () => miShowTab(btn.dataset.miTab));
  });

  miShowTab('my-inspections');
}

let miActiveTab = 'my-inspections';

function miShowTab(tab) {
  miActiveTab = tab;
  document.querySelectorAll('#page-inspection-review .mi-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.miTab === tab);
  });

  const body = document.getElementById('miTabBody');
  if (!body) return;

  if (tab === 'my-inspections') return miRenderMyInspections(body);
  if (tab === 'wizard') return miRenderWizardTab(body);
  if (tab === 'review') return miRenderSubmittedList(body, 'review', 'No reviewed inspections yet.');
  if (tab === 'returned') return miRenderReturnedList(body);
  if (tab === 'history') return miRenderSubmittedList(body, 'history', 'No inspection history yet.');
}

/* ---- My Inspections: Overdue/Today/Upcoming + Resume ------------------ */

async function miRenderMyInspections(container) {
  container.innerHTML = '<p class="empty-state">Loading...</p>';
  try {
    const data = await engineerMiGet('my_inspections');
    const buckets = data.buckets || { overdue: [], today: [], upcoming: [] };
    const inProgress = data.in_progress || [];

    container.innerHTML = `
      ${inProgress.length ? `
        <section class="mi-section">
          <h2 class="mi-section-title">Resume</h2>
          <div class="mi-card-list">${inProgress.map(miResumeCardHtml).join('')}</div>
        </section>
      ` : ''}
      <section class="mi-section">
        <div class="mi-section-title-row">
          <h2 class="mi-section-title">My Inspections</h2>
          <button type="button" class="btn-secondary btn-compact" id="miAdHocBtn">+ New Inspection</button>
        </div>
        <div class="mi-bucket-grid">
          ${miBucketColumnHtml('🔴 Overdue', buckets.overdue)}
          ${miBucketColumnHtml('🟠 Today', buckets.today)}
          ${miBucketColumnHtml('🟢 Upcoming', buckets.upcoming)}
        </div>
      </section>
    `;

    document.getElementById('miAdHocBtn')?.addEventListener('click', miOpenAdHocPicker);
    container.querySelectorAll('[data-mi-start-report]').forEach(btn => {
      btn.addEventListener('click', () => miStartInspection({ progress_report_id: btn.dataset.miStartReport }));
    });
    container.querySelectorAll('[data-mi-resume]').forEach(btn => {
      btn.addEventListener('click', () => miOpenWizard(Number(btn.dataset.miResume)));
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load your inspections.</p>';
    engineerToast(error.message, 'error');
  }
}

function miBucketColumnHtml(label, items) {
  return `
    <div class="mi-bucket-col">
      <div class="mi-bucket-head">${label} <span class="mi-bucket-count">${items.length}</span></div>
      ${items.length ? items.map(miCandidateCardHtml).join('') : '<p class="empty-state">Nothing here.</p>'}
    </div>
  `;
}

function miCandidateCardHtml(item) {
  return `
    <article class="mi-card">
      <div class="mi-card-title">${engineerEscape(item.project_name)}</div>
      <div class="mi-card-code">${engineerEscape(item.project_code)}</div>
      <div class="mi-card-loc">📍 ${engineerEscape(item.location || 'No location set')}</div>
      <div class="mi-card-meta">Report: ${engineerDate(item.report_date)} · Target: ${engineerDate(item.target_date)}</div>
      <div class="mi-card-foot">
        ${engineerBadge('progress', 'Progress Inspection')}
        <button type="button" class="btn-primary btn-compact" data-mi-start-report="${item.progress_report_id}">Start Inspection</button>
      </div>
    </article>
  `;
}

function miResumeCardHtml(item) {
  const typeLabel = item.inspection_type === 'reinspection' ? 'Reinspection' : item.inspection_type === 'special' ? 'Ad-hoc Visit' : 'Progress Inspection';
  return `
    <article class="mi-card mi-card-resume">
      <div class="mi-card-title">${engineerEscape(item.project_name)}</div>
      <div class="mi-card-code">${engineerEscape(item.project_code)}</div>
      <div class="mi-card-loc">📍 ${engineerEscape(item.location || 'No location set')}</div>
      <div class="mi-card-meta">${engineerEscape(typeLabel)} · Started ${engineerDate(item.created_at)}${item.draft_saved_at ? ` · Last saved ${miTimeLabel(item.draft_saved_at)}` : ''}</div>
      <div class="mi-card-foot">
        ${engineerBadge('in_progress', 'In Progress')}
        <button type="button" class="btn-primary btn-compact" data-mi-resume="${item.id}">Continue</button>
      </div>
    </article>
  `;
}

function miOpenAdHocPicker() {
  engineerOpenModal('Start a New Inspection', `
    <div class="form-group">
      <label>Project</label>
      <select class="form-input" id="miAdHocProject">${engineerProjectOptions()}</select>
    </div>
    <div class="form-actions">
      <button class="btn-primary" type="button" id="miAdHocStartBtn">Start Inspection</button>
    </div>
  `);
  document.getElementById('miAdHocStartBtn').addEventListener('click', () => {
    const projectId = document.getElementById('miAdHocProject').value;
    if (!projectId) { engineerToast('Choose a project first.', 'error'); return; }
    engineerCloseModal();
    miStartInspection({ project_id: projectId });
  });
}

async function miStartInspection(payload) {
  try {
    const result = await engineerMiPostJson('inspection_start', payload);
    if (result.status === 'submitted') {
      engineerToast('This inspection was already submitted — opening it for review.');
      miShowTab('review');
      return;
    }
    miOpenWizard(result.id);
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

/* ---- Start / Continue: the 7-step wizard ------------------------------- */

const MI_STEPS = ['project-info', 'gis', 'checklist', 'findings', 'photos', 'remarks', 'review'];
const MI_STEP_LABELS = {
  'project-info': 'Project Information',
  'gis': 'GIS Location',
  'checklist': 'Checklist',
  'findings': 'Findings',
  'photos': 'Photos',
  'remarks': 'Remarks & Recommendations',
  'review': 'Review & Submit',
};

let miWizardState = null;

async function miOpenWizard(id) {
  miShowTab('wizard');
  const body = document.getElementById('miTabBody');
  body.innerHTML = '<p class="empty-state">Loading inspection...</p>';

  try {
    const detail = await engineerMiGet('detail', { id });
    const inspection = detail.data;
    const [checklist, projectInfo] = await Promise.all([
      engineerMiGet('checklist', { project_id: inspection.project_id }),
      engineerMiGet('project_info', { project_id: inspection.project_id }),
    ]);

    miWizardState = {
      id: inspection.id,
      stepIndex: 0,
      project: projectInfo.data,
      inspection,
      checklistGrouped: checklist.grouped,
      answers: inspection.checklist_answers || {},
      findings: inspection.findings || '',
      itemizedFindings: inspection.itemized_findings || [],
      recommendationNotes: inspection.recommendation_notes || [],
      photos: inspection.photos || [],
      gps: { lat: inspection.gps_latitude, lng: inspection.gps_longitude, accuracy: inspection.gps_accuracy_meters },
      actualProgress: Number(inspection.actual_progress_percent || 0),
      recommendation: inspection.recommendation || 'approved',
      draftSavedAt: inspection.draft_saved_at,
    };

    miRenderWizardStep();
  } catch (error) {
    body.innerHTML = '<p class="empty-state">Unable to load this inspection.</p>';
    engineerToast(error.message, 'error');
  }
}

function miRenderWizardTab(container) {
  if (miWizardState) {
    miRenderWizardStep();
    return;
  }
  container.innerHTML = `
    <div class="mi-empty-wizard">
      <p class="empty-state">No inspection in progress. Choose one from "My Inspections" to begin.</p>
      <button type="button" class="btn-primary btn-compact" onclick="miShowTab('my-inspections')">Go to My Inspections</button>
    </div>
  `;
}

function miRenderWizardStep() {
  const body = document.getElementById('miTabBody');
  if (!body || !miWizardState) return;

  const stepKey = MI_STEPS[miWizardState.stepIndex];
  const stepNum = miWizardState.stepIndex + 1;

  body.innerHTML = `
    <div class="mi-wizard">
      <div class="mi-stepper">
        <div class="mi-stepper-label">Step ${stepNum} of ${MI_STEPS.length} — ${MI_STEP_LABELS[stepKey]}</div>
        <div class="mi-stepper-track"><div class="mi-stepper-fill" style="width:${(stepNum / MI_STEPS.length) * 100}%"></div></div>
      </div>
      <div class="mi-step-body" id="miStepBody"></div>
      <div class="mi-step-nav">
        <button type="button" class="btn-secondary btn-compact" id="miBackBtn" ${miWizardState.stepIndex === 0 ? 'disabled' : ''}>Back</button>
        <span class="mi-draft-status" id="miDraftStatus">${miWizardState.draftSavedAt ? 'Draft saved · Last saved ' + miTimeLabel(miWizardState.draftSavedAt) : ''}</span>
        <button type="button" class="btn-secondary btn-compact" id="miSaveDraftBtn">Save Draft</button>
        ${stepKey === 'review'
          ? '<button type="button" class="btn-primary btn-compact" id="miSubmitBtn">Submit Inspection</button>'
          : '<button type="button" class="btn-primary btn-compact" id="miNextBtn">Next</button>'}
      </div>
    </div>
  `;

  miRenderStepBody(stepKey);

  document.getElementById('miBackBtn').addEventListener('click', () => miGoStep(-1));
  document.getElementById('miNextBtn')?.addEventListener('click', () => miGoStep(1));
  document.getElementById('miSaveDraftBtn').addEventListener('click', miSaveDraft);
  document.getElementById('miSubmitBtn')?.addEventListener('click', miSubmitInspection);
}

function miRenderStepBody(stepKey) {
  const el = document.getElementById('miStepBody');
  if (!el) return;

  if (stepKey === 'project-info') return miStepProjectInfo(el);
  if (stepKey === 'gis') return miStepGis(el);
  if (stepKey === 'checklist') return miStepChecklist(el);
  if (stepKey === 'findings') return miStepFindings(el);
  if (stepKey === 'photos') return miStepPhotos(el);
  if (stepKey === 'remarks') return miStepRemarks(el);
  if (stepKey === 'review') return miStepReview(el);
}

function miCollectStepInputs() {
  const stepKey = MI_STEPS[miWizardState.stepIndex];
  if (stepKey === 'remarks') {
    const input = document.getElementById('miRemarksInput');
    if (input) miWizardState.findings = input.value;
  }
  if (stepKey === 'review') {
    const progress = document.getElementById('miActualProgress');
    const reco = document.getElementById('miRecommendation');
    if (progress) miWizardState.actualProgress = Number(progress.value) || 0;
    if (reco) miWizardState.recommendation = reco.value;
  }
}

function miGoStep(delta) {
  miCollectStepInputs();
  const nextIndex = miWizardState.stepIndex + delta;
  if (nextIndex < 0 || nextIndex >= MI_STEPS.length) return;
  miWizardState.stepIndex = nextIndex;
  miRenderWizardStep();
}

async function miSaveDraft() {
  miCollectStepInputs();
  try {
    const result = await engineerMiPostJson('inspection_draft', {
      id: miWizardState.id,
      checklist_answers: miWizardState.answers,
      findings: miWizardState.findings,
      recommendation_notes: miWizardState.recommendationNotes,
      actual_progress_percent: miWizardState.actualProgress,
      gps_latitude: miWizardState.gps?.lat ?? null,
      gps_longitude: miWizardState.gps?.lng ?? null,
      gps_accuracy_meters: miWizardState.gps?.accuracy ?? null,
    });
    miWizardState.draftSavedAt = result.draft_saved_at;
    const statusEl = document.getElementById('miDraftStatus');
    if (statusEl) statusEl.textContent = 'Draft saved · Last saved ' + miTimeLabel(result.draft_saved_at);
    engineerToast('Draft saved.');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

async function miSubmitInspection() {
  miCollectStepInputs();
  if (!miWizardState.findings || miWizardState.findings.trim().length < 3) {
    engineerToast('Please add remarks (at least a short summary) before submitting.', 'error');
    miWizardState.stepIndex = MI_STEPS.indexOf('remarks');
    miRenderWizardStep();
    return;
  }

  try {
    await engineerMiPostJson('inspection_draft', {
      id: miWizardState.id,
      checklist_answers: miWizardState.answers,
      findings: miWizardState.findings,
      recommendation_notes: miWizardState.recommendationNotes,
    });
    await engineerMiPostJson('inspection_submit', {
      id: miWizardState.id,
      actual_progress_percent: miWizardState.actualProgress,
      recommendation: miWizardState.recommendation,
      findings: miWizardState.findings,
    });
    engineerToast('Inspection submitted.');
    miWizardState = null;
    await engineerRefreshData();
    miShowTab('review');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

/* Step 1: Project Information (read-only) */
function miStepProjectInfo(el) {
  const p = miWizardState.project || {};
  el.innerHTML = `
    <div class="mi-info-grid">
      <div class="mi-info-item"><span>Project Name</span><strong>${engineerEscape(p.name)}</strong></div>
      <div class="mi-info-item"><span>Project ID</span><strong>${engineerEscape(p.project_code)}</strong></div>
      <div class="mi-info-item"><span>Category</span><strong>${engineerEscape(p.category || '-')}</strong></div>
      <div class="mi-info-item"><span>Location</span><strong>${engineerEscape(p.location || '-')}</strong></div>
      <div class="mi-info-item"><span>Budget</span><strong>${engineerMoney(p.budget)}</strong></div>
      <div class="mi-info-item"><span>Contractor</span><strong>${engineerEscape(p.contractor_name || '-')}</strong></div>
      <div class="mi-info-item"><span>Start Date</span><strong>${engineerDate(p.start_date)}</strong></div>
      <div class="mi-info-item"><span>Expected Completion</span><strong>${engineerDate(p.end_date)}</strong></div>
      <div class="mi-info-item"><span>Current Status</span><strong>${engineerBadge(p.status)}</strong></div>
      <div class="mi-info-item"><span>Current Progress</span><strong>${Number(p.progress || 0)}%</strong></div>
      <div class="mi-info-item"><span>Assigned Engineer</span><strong>${engineerEscape(p.engineer_name || '-')}</strong></div>
    </div>
    ${p.description ? `<p class="mi-info-note">${engineerEscape(p.description)}</p>` : ''}
    ${p.category === 'Roads and Bridges' ? `
      <button type="button" class="btn-secondary btn-compact" onclick="miCompareRoadHistory('${engineerEscape((p.location || p.name || '').split(',')[0].trim())}')">Compare with Road Inspection History →</button>
    ` : ''}
  `;
}

/* Step 2: GIS Location — reuses engineerUpShowMap()'s exact map pattern for
   the "Open Map" modal; the inline preview below uses the same Leaflet +
   ProjectMap pin construction directly embedded in the step. */
let miInlineMapInstance = null;

function miInitInlineMap(lat, lng) {
  const container = document.getElementById('miInlineMap');
  if (!container || typeof L === 'undefined') return;
  if (miInlineMapInstance) { miInlineMapInstance.remove(); miInlineMapInstance = null; }
  miInlineMapInstance = L.map('miInlineMap').setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(miInlineMapInstance);
  const pinIcon = window.ProjectMap ? window.ProjectMap.pinIcon(null, { neutral: true }) : undefined;
  L.marker([lat, lng], pinIcon ? { icon: pinIcon } : {}).addTo(miInlineMapInstance);
  setTimeout(() => miInlineMapInstance.invalidateSize(), 50);
}

function miCompareRoadHistory(searchTerm) {
  engineerShowPage('road-inspection-history');
  engineerRenderRoadHistoryPage(searchTerm);
}

function miStepGis(el) {
  const p = miWizardState.project || {};
  const hasCoords = p.latitude != null && p.longitude != null;
  const gps = miWizardState.gps || {};

  el.innerHTML = `
    <div class="mi-gis-map" id="miInlineMap">${hasCoords ? '' : '<p class="empty-state">No official coordinates on file for this project.</p>'}</div>
    <div class="mi-info-grid" style="margin-top:12px;">
      <div class="mi-info-item"><span>Latitude</span><strong>${hasCoords ? Number(p.latitude).toFixed(6) : '-'}</strong></div>
      <div class="mi-info-item"><span>Longitude</span><strong>${hasCoords ? Number(p.longitude).toFixed(6) : '-'}</strong></div>
    </div>
    ${hasCoords ? `<button type="button" class="btn-secondary btn-compact" onclick="engineerUpShowMap(${Number(p.latitude)}, ${Number(p.longitude)}, '${engineerEscape(p.name)}')">Open Map</button>` : ''}
    <div class="mi-gps-block">
      <button type="button" class="btn-secondary btn-compact" id="miGpsBtn">Use My Current Location</button>
      <p class="mi-gps-note">Supporting information only — GPS accuracy varies and this is not proof of physical presence.</p>
      <div id="miGpsResult">${gps.lat ? `<span>📍 ${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)}${gps.accuracy ? ' (±' + Math.round(gps.accuracy) + 'm)' : ''}</span>` : ''}</div>
    </div>
  `;

  if (hasCoords) {
    setTimeout(() => miInitInlineMap(Number(p.latitude), Number(p.longitude)), 50);
  }

  document.getElementById('miGpsBtn').addEventListener('click', () => {
    if (!navigator.geolocation) {
      engineerToast('Location services are not available in this browser.', 'error');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        miWizardState.gps = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
        document.getElementById('miGpsResult').innerHTML =
          `<span>📍 ${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)} (±${Math.round(pos.coords.accuracy)}m)</span>`;
      },
      () => engineerToast('Could not get your location. This step is optional — you can continue without it.', 'error')
    );
  });
}

/* Step 3: Checklist — category-grouped, adapts to project category via
   includes/InspectionChecklist.php's applies_to filter. */
function miStepChecklist(el) {
  const grouped = miWizardState.checklistGrouped || {};
  el.innerHTML = Object.entries(grouped).map(([category, items]) => `
    <div class="mi-checklist-group">
      <h3 class="mi-checklist-cat">${engineerEscape(category)}</h3>
      ${items.map(item => `
        <label class="mi-checklist-item">
          <input type="checkbox" data-mi-check="${item.key}" ${miWizardState.answers[item.key] ? 'checked' : ''}>
          <span>${engineerEscape(item.label)}</span>
        </label>
      `).join('')}
    </div>
  `).join('');

  el.querySelectorAll('[data-mi-check]').forEach(input => {
    input.addEventListener('change', () => {
      miWizardState.answers[input.dataset.miCheck] = input.checked;
    });
  });
}

/* Step 4: Findings — multiple structured items, own child-table CRUD so
   add/remove take effect immediately rather than waiting for Save Draft. */
function miRenderFindingsList(el) {
  const findings = miWizardState.itemizedFindings || [];
  el.innerHTML = `
    <div class="mi-findings-count">Findings: ${findings.length}</div>
    <div class="mi-finding-list">
      ${findings.map(f => `
        <div class="mi-finding-item">
          <div class="mi-finding-head">
            <strong>${engineerEscape(f.finding_type)}</strong>
            ${engineerBadge(f.severity)}
          </div>
          <p>${engineerEscape(f.description)}</p>
          ${f.affected_area ? `<p class="mi-finding-sub">Area: ${engineerEscape(f.affected_area)}</p>` : ''}
          ${f.recommended_action ? `<p class="mi-finding-sub">Action: ${engineerEscape(f.recommended_action)}</p>` : ''}
          <button type="button" class="doc-row-remove" data-mi-remove-finding="${f.id}">&times;</button>
        </div>
      `).join('') || '<p class="empty-state">No findings added yet.</p>'}
    </div>
    <button type="button" class="doc-add-btn" id="miAddFindingBtn">+ Add Finding</button>
  `;

  document.getElementById('miAddFindingBtn').addEventListener('click', miOpenAddFindingForm);
  el.querySelectorAll('[data-mi-remove-finding]').forEach(btn => {
    btn.addEventListener('click', () => miRemoveFinding(Number(btn.dataset.miRemoveFinding)));
  });
}

function miStepFindings(el) {
  miRenderFindingsList(el);
}

function miOpenAddFindingForm() {
  engineerOpenModal('Add Finding', `
    <form id="miFindingForm">
      <div class="form-group">
        <label>Finding Type</label>
        <input class="form-input" type="text" name="finding_type" placeholder="e.g. Drainage, Pavement, Safety">
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Description</label>
        <textarea class="form-input" name="description" rows="3" required placeholder="What did you observe?"></textarea>
      </div>
      <div class="form-grid" style="margin-top:12px;">
        <div class="form-group">
          <label>Severity</label>
          <select class="form-input" name="severity">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <div class="form-group">
          <label>Affected Area</label>
          <input class="form-input" type="text" name="affected_area" placeholder="e.g. Northbound lane, KM 3+200">
        </div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Recommended Action</label>
        <textarea class="form-input" name="recommended_action" rows="2" placeholder="Optional"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-primary" type="submit">Add Finding</button>
      </div>
    </form>
  `);
  document.getElementById('miFindingForm').addEventListener('submit', miSubmitFinding);
}

async function miSubmitFinding(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  try {
    const result = await engineerMiPostJson('inspection_finding_add', {
      inspection_id: miWizardState.id,
      finding_type: form.get('finding_type'),
      description: form.get('description'),
      severity: form.get('severity'),
      affected_area: form.get('affected_area'),
      recommended_action: form.get('recommended_action'),
    });
    miWizardState.itemizedFindings.push({
      id: result.id,
      finding_type: form.get('finding_type') || 'Other',
      description: form.get('description'),
      severity: form.get('severity'),
      affected_area: form.get('affected_area'),
      recommended_action: form.get('recommended_action'),
    });
    engineerCloseModal();
    miRenderFindingsList(document.getElementById('miStepBody'));
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

async function miRemoveFinding(findingId) {
  try {
    await engineerMiPostJson('inspection_finding_delete', { finding_id: findingId });
    miWizardState.itemizedFindings = miWizardState.itemizedFindings.filter(f => Number(f.id) !== findingId);
    miRenderFindingsList(document.getElementById('miStepBody'));
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

/* Step 5: Photos — same photos[N][title]/[caption] + photo_files[N]
   convention as engineerPhotoRowHtml(), plus a client-side preview before
   upload, uploaded immediately per-row (not batched until submit). */
function miPhotoRowHtml(index) {
  return `
    <div class="doc-row mi-photo-row" data-doc-index="${index}">
      <input class="form-input" type="text" name="photos[${index}][title]" placeholder="Photo title">
      <input class="form-input" type="text" name="photos[${index}][caption]" placeholder="Description (optional)">
      <input class="form-input mi-photo-file" type="file" name="photo_files[${index}]" accept="image/*" capture="environment">
      <button type="button" class="doc-row-remove" aria-label="Remove photo row">&times;</button>
    </div>
  `;
}

function miWirePhotoPreview(input) {
  input.addEventListener('change', () => {
    const row = input.closest('.doc-row');
    row.querySelectorAll('.mi-photo-preview').forEach(p => p.remove());
    const file = input.files?.[0];
    if (!file) return;
    const img = document.createElement('img');
    img.className = 'mi-photo-preview';
    img.src = URL.createObjectURL(file);
    img.onload = () => URL.revokeObjectURL(img.src);
    row.appendChild(img);
  });
}

function miWirePhotoRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', miPhotoRowHtml(nextIndex));
    const newInput = container.querySelector(`[data-doc-index="${nextIndex}"] .mi-photo-file`);
    if (newInput) miWirePhotoPreview(newInput);
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

function miStepPhotos(el) {
  const photos = miWizardState.photos || [];
  el.innerHTML = `
    <div class="mi-photo-grid" id="miPhotoGrid">
      ${photos.map(p => `
        <figure class="mi-photo-thumb">
          <img src="${window.BASE_PATH}${engineerEscape(p.file_path)}" alt="">
          <figcaption>${engineerEscape(p.title)}</figcaption>
        </figure>
      `).join('') || '<p class="empty-state">No photos uploaded yet.</p>'}
    </div>
    <form id="miPhotoForm" enctype="multipart/form-data">
      <div class="doc-rows" id="miPhotoRows">${miPhotoRowHtml(0)}</div>
      <button type="button" class="doc-add-btn" id="miPhotoAddBtn">+ Add another photo</button>
      <div class="form-actions">
        <button class="btn-primary btn-compact" type="submit">Upload Photo(s)</button>
      </div>
    </form>
  `;

  miWirePhotoRows(document.getElementById('miPhotoRows'), document.getElementById('miPhotoAddBtn'));
  miWirePhotoPreview(el.querySelector('.mi-photo-file'));
  document.getElementById('miPhotoForm').addEventListener('submit', miUploadPhotos);
}

async function miUploadPhotos(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  form.set('inspection_id', miWizardState.id);
  try {
    await engineerMiPostForm('inspection_photo', form);
    engineerToast('Photo(s) uploaded.');
    const detail = await engineerMiGet('detail', { id: miWizardState.id });
    miWizardState.photos = detail.data.photos || [];
    miRenderStepBody('photos');
  } catch (error) {
    engineerToast(error.message, 'error');
  }
}

/* Step 6: Remarks & Recommendations — Remarks writes into the same required
   `inspections.findings` column the legacy form always used, so every
   existing reader of that field (contractor notifications, etc.) keeps
   working unchanged. */
function miStepRemarks(el) {
  const notes = miWizardState.recommendationNotes || [];
  el.innerHTML = `
    <div class="form-group">
      <label>Remarks</label>
      <textarea class="form-input" id="miRemarksInput" rows="4" placeholder="Additional observations not captured in the checklist or findings...">${engineerEscape(miWizardState.findings)}</textarea>
    </div>
    <div class="mi-reco-block" style="margin-top:16px;">
      <div class="mi-findings-count">Recommendations: ${notes.length}</div>
      <div class="mi-reco-list" id="miRecoList">
        ${notes.map((n, i) => `
          <div class="mi-reco-item">
            <span>${engineerEscape(n)}</span>
            <button type="button" class="doc-row-remove" data-mi-remove-reco="${i}">&times;</button>
          </div>
        `).join('') || '<p class="empty-state">No recommendations added yet.</p>'}
      </div>
      <div class="mi-reco-add">
        <input class="form-input" type="text" id="miRecoInput" placeholder="e.g. Complete corrective drainage work before the next inspection">
        <button type="button" class="btn-secondary btn-compact" id="miRecoAddBtn">+ Add Recommendation</button>
      </div>
    </div>
  `;

  document.getElementById('miRecoAddBtn').addEventListener('click', () => {
    const input = document.getElementById('miRecoInput');
    const value = input.value.trim();
    if (!value) return;
    const remarksInput = document.getElementById('miRemarksInput');
    if (remarksInput) miWizardState.findings = remarksInput.value;
    miWizardState.recommendationNotes.push(value);
    miRenderStepBody('remarks');
  });
  el.querySelectorAll('[data-mi-remove-reco]').forEach(btn => {
    btn.addEventListener('click', () => {
      const remarksInput = document.getElementById('miRemarksInput');
      if (remarksInput) miWizardState.findings = remarksInput.value;
      miWizardState.recommendationNotes.splice(Number(btn.dataset.miRemoveReco), 1);
      miRenderStepBody('remarks');
    });
  });
}

/* Step 7: Review & Submit */
function miStepReview(el) {
  const s = miWizardState;
  const totalChecklist = Object.values(s.checklistGrouped || {}).flat().length;
  const answeredChecklist = Object.keys(s.answers || {}).filter(k => s.answers[k]).length;

  el.innerHTML = `
    <div class="mi-review-grid">
      <div class="mi-info-item"><span>Project</span><strong>${engineerEscape(s.project?.name)}</strong></div>
      <div class="mi-info-item"><span>Engineer</span><strong>${engineerEscape(s.project?.engineer_name || '')}</strong></div>
      <div class="mi-info-item"><span>Inspection Date</span><strong>${engineerDate(s.inspection.inspection_date)}</strong></div>
      <div class="mi-info-item"><span>Checklist</span><strong>${answeredChecklist} / ${totalChecklist} Completed</strong></div>
      <div class="mi-info-item"><span>Findings</span><strong>${s.itemizedFindings.length}</strong></div>
      <div class="mi-info-item"><span>Photos</span><strong>${s.photos.length}</strong></div>
      <div class="mi-info-item"><span>Recommendations</span><strong>${s.recommendationNotes.length}</strong></div>
      <div class="mi-info-item"><span>Remarks</span><strong>${s.findings ? 'Available' : 'Not provided'}</strong></div>
    </div>
    <div class="form-grid" style="margin-top:16px;">
      <div class="form-group">
        <label>Actual Progress Percent</label>
        <input class="form-input" type="number" id="miActualProgress" min="0" max="100" value="${s.actualProgress}">
      </div>
      <div class="form-group">
        <label>Overall Recommendation</label>
        <select class="form-input" id="miRecommendation">
          <option value="approved" ${s.recommendation === 'approved' ? 'selected' : ''}>Approved</option>
          <option value="needs_correction" ${s.recommendation === 'needs_correction' ? 'selected' : ''}>Needs Correction</option>
          <option value="for_reinspection" ${s.recommendation === 'for_reinspection' ? 'selected' : ''}>For Reinspection</option>
        </select>
      </div>
    </div>
  `;
}

/* ---- Inspection Review / Returned / History tabs ----------------------- */

let miListState = { review: { page: 1, perPage: 10 }, history: { page: 1, perPage: 10 } };

async function miRenderSubmittedList(container, action, emptyText) {
  container.innerHTML = `
    <div id="miListBody"><p class="empty-state">Loading...</p></div>
    <div class="pagination-wrap" id="miListPager"></div>
  `;
  await miLoadSubmittedList(action, emptyText);
}

async function miLoadSubmittedList(action, emptyText) {
  const listBody = document.getElementById('miListBody');
  const pager = document.getElementById('miListPager');
  if (!listBody) return;
  const state = miListState[action];

  try {
    const result = await engineerMiGet(action, { page: state.page, per_page: state.perPage });
    listBody.innerHTML = result.data.length ? result.data.map(miSubmittedRowHtml).join('') : `<p class="empty-state">${emptyText}</p>`;
    listBody.querySelectorAll('[data-mi-view]').forEach(btn => {
      btn.addEventListener('click', () => miOpenSubmittedDetail(Number(btn.dataset.miView)));
    });
    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { miListState[action].page = nextPage; miLoadSubmittedList(action, emptyText); },
    });
  } catch (error) {
    listBody.innerHTML = '<p class="empty-state">Unable to load inspections.</p>';
  }
}

function miSubmittedRowHtml(row) {
  return `
    <div class="engineer-mini-row">
      <span>${engineerEscape(row.project_code)} - ${engineerEscape(row.project_name)} - ${engineerDate(row.inspection_date)}</span>
      <span>
        ${engineerBadge(row.recommendation)}
        <button type="button" class="btn-secondary btn-compact" data-mi-view="${row.id}">View</button>
      </span>
    </div>
  `;
}

async function miRenderReturnedList(container) {
  container.innerHTML = '<p class="empty-state">Loading...</p>';
  try {
    const result = await engineerMiGet('returned');
    const rows = result.data || [];
    container.innerHTML = rows.length ? `
      <div class="mi-card-list">
        ${rows.map(row => `
          <article class="mi-card">
            <div class="mi-card-title">${engineerEscape(row.project_name)}</div>
            <div class="mi-card-code">${engineerEscape(row.project_code)}</div>
            <div class="mi-card-loc">📍 ${engineerEscape(row.location || 'No location set')}</div>
            <div class="mi-card-meta">Last inspected ${engineerDate(row.inspection_date)} · ${engineerBadge(row.recommendation)}</div>
            <p class="mi-finding-sub">${engineerEscape((row.findings || '').slice(0, 160))}</p>
            <div class="mi-card-foot">
              <button type="button" class="btn-secondary btn-compact" data-mi-view="${row.id}">View</button>
              <button type="button" class="btn-primary btn-compact" data-mi-reinspect="${row.id}">Start Reinspection</button>
            </div>
          </article>
        `).join('')}
      </div>
    ` : '<p class="empty-state">Nothing needs reinspection right now.</p>';

    container.querySelectorAll('[data-mi-view]').forEach(btn => btn.addEventListener('click', () => miOpenSubmittedDetail(Number(btn.dataset.miView))));
    container.querySelectorAll('[data-mi-reinspect]').forEach(btn => btn.addEventListener('click', () => miStartInspection({ follow_up_of_inspection_id: btn.dataset.miReinspect })));
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load returned inspections.</p>';
  }
}

async function miOpenSubmittedDetail(id) {
  try {
    const detail = await engineerMiGet('detail', { id });
    const d = detail.data;
    engineerOpenModal(`Inspection — ${d.project_name}`, `
      <div class="mi-info-grid">
        <div class="mi-info-item"><span>Project</span><strong>${engineerEscape(d.project_name)}</strong></div>
        <div class="mi-info-item"><span>Date</span><strong>${engineerDate(d.inspection_date)}</strong></div>
        <div class="mi-info-item"><span>Type</span><strong>${engineerEscape(d.inspection_type)}</strong></div>
        <div class="mi-info-item"><span>Actual Progress</span><strong>${Number(d.actual_progress_percent || 0)}%</strong></div>
        <div class="mi-info-item"><span>Recommendation</span><strong>${engineerBadge(d.recommendation)}</strong></div>
      </div>
      <h4 style="margin:16px 0 8px;">Remarks</h4>
      <p>${engineerEscape(d.findings || '-')}</p>
      <h4 style="margin:16px 0 8px;">Findings (${d.itemized_findings.length})</h4>
      <div class="mi-finding-list">
        ${d.itemized_findings.map(f => `
          <div class="mi-finding-item">
            <div class="mi-finding-head"><strong>${engineerEscape(f.finding_type)}</strong>${engineerBadge(f.severity)}</div>
            <p>${engineerEscape(f.description)}</p>
          </div>
        `).join('') || '<p class="empty-state">No itemized findings.</p>'}
      </div>
      <h4 style="margin:16px 0 8px;">Recommendations (${(d.recommendation_notes || []).length})</h4>
      <ul>${(d.recommendation_notes || []).map(n => `<li>${engineerEscape(n)}</li>`).join('') || '<li>None</li>'}</ul>
      <h4 style="margin:16px 0 8px;">Photos (${d.photos.length})</h4>
      <div class="mi-photo-grid">
        ${d.photos.map(p => `<figure class="mi-photo-thumb"><img src="${window.BASE_PATH}${engineerEscape(p.file_path)}" alt=""><figcaption>${engineerEscape(p.title)}</figcaption></figure>`).join('') || '<p class="empty-state">No photos.</p>'}
      </div>
    `);
  } catch (error) {
    engineerToast(error.message, 'error');
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
      ${project.latitude && project.longitude
        ? `<button type="button" class="btn-secondary" style="margin-top:10px;" onclick="engineerUpShowMap(${project.latitude}, ${project.longitude}, '${engineerEscape(project.name).replace(/'/g, "\\'")}')">View Map</button>`
        : `<p class="engineer-scope-note" style="margin-top:10px;">Site location not pinned yet.</p>`}
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
  const [summary, projects, milestones, workflow, tracker] = await Promise.all([
    engineerGet('summary'),
    engineerGet('projects'),
    engineerGet('milestones'),
    engineerWorkflowGet('summary'),
    engineerGet('tracker'),
  ]);

  engineerState.summary = summary;
  engineerState.projects = projects.data || [];
  engineerState.milestones = milestones.data || [];
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
  if (page === 'my-tasks') taskCenterInitPage('page-my-tasks');
  if (page === 'assigned-projects') engineerRenderAssignedProjects();
  if (page === 'available-projects') engineerRenderAvailableProjects();
  if (page === 'engineering-review') engineerRenderEngineeringReviewPage();
  if (page === 'milestone-update') engineerRenderMilestonePage();
  if (page === 'inspection-review') engineerRenderInspectionPage();
  if (page === 'payment-review') engineerRenderPaymentReviewPage();
  if (page === 'progress-photos') engineerRenderPhotosPage(selectedProjectId);
  if (page === 'delay-report') engineerRenderDelayPage();
  if (page === 'issue-reporting') engineerRenderIssuePage();
  if (page === 'status-tracker') engineerRenderStatusTracker(selectedProjectId);
  if (page === 'urban-planning-inspection') engineerRenderUrbanPlanningPage();
  if (page === 'road-inspection-history') engineerRenderRoadHistoryPage();
}

async function engineerSubmitAddMilestone(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerPostJson('add_milestone', {
      project_id: form.get('project_id'),
      title: form.get('title'),
      due_date: form.get('due_date'),
    });
    engineerToast('Milestone added.');
    await engineerRefreshData();
    engineerShowPage('milestone-update');
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
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

window.GLOBAL_SEARCH_NAVIGATE = engineerShowPage;

// Smart Task & Assignment Center (assets/js/task-center.js) glue.
// engineerShowPage's 2nd arg is a project id, unlike the other portals'
// single-arg routers — unpack link_params.project_id into that slot.
window.TASK_CENTER_NAVIGATE = (page, params) => engineerShowPage(page, (params && params.project_id) || '');
window.TASK_CENTER_OPEN_MODAL = engineerOpenModal;
window.TASK_CENTER_CLOSE_MODAL = engineerCloseModal;
window.TASK_CENTER_ESCAPE = engineerEscape;
window.TASK_CENTER_TOAST = engineerToast;

window.GLOBAL_SEARCH_SOURCES = [
  {
    label: 'Assigned Projects',
    url: `${ENGINEER_API}?action=projects`,
    mapItem: row => ({
      title: row.name,
      meta: `${row.project_code || ''} · ${row.status || ''}`.replace(/^ · /, ''),
      page: 'assigned-projects',
    }),
  },
];

function engineerWireShell() {
  // Sidebar toggle (open/close + backdrop) is handled by assets/js/sidebar-toggle.js.

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

/* ============================================================
   URBAN PLANNING SYSTEM INTEGRATION
   Additive, separate from the native Engineer Portal modules above: its
   own API file (engineer/api/urban-planning.php), its own state, its own
   render functions. Road/request fields (road_id, road_name, barangay,
   district, road_type, road_length, priority, requested_by, request_date,
   map location) are read-only here — owned by the Urban Planning System.
   Only the inspection result (everything in the form below) is ever
   written from this portal.
   ============================================================ */
const ENGINEER_UP_API = window.BASE_PATH + 'engineer/api/urban-planning.php';
const ENGINEER_UP_CONDITIONS = ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'];
const ENGINEER_UP_SEVERITIES = ['low', 'medium', 'high', 'critical'];
const ENGINEER_UP_RECOMMENDATIONS = ['Routine Maintenance', 'Repair', 'Rehabilitation', 'Road Reconstruction', 'Further Investigation', 'No Action Needed'];

let engineerUpListState = { page: 1, perPage: 10 };
let engineerUpHistoryState = { page: 1, perPage: 10, search: '', status: '', overall_condition: '', recommendation: '', date_from: '', date_to: '' };
let engineerUpMapInstance = null;

async function engineerUpGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const response = await fetch(`${ENGINEER_UP_API}?${qs}`);
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerUpPostForm(action, formData) {
  const response = await fetch(`${ENGINEER_UP_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...ENGINEER_CSRF_HEADERS },
    body: formData,
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

function engineerUpConditionBadge(value) {
  if (!value) return '—';
  const classes = { Excellent: 'status-completed', Good: 'status-active', Fair: 'status-planning', Poor: 'status-delayed', Critical: 'status-cancelled' };
  return `<span class="badge ${classes[value] || ''}">${engineerEscape(value)}</span>`;
}

function engineerUpStatusBadge(status) {
  const labels = { pending: 'Pending', assigned: 'Assigned', in_progress: 'In Progress', completed: 'Completed', returned: 'Returned' };
  const classes = { pending: 'status-draft', assigned: 'status-planning', in_progress: 'status-active', completed: 'status-completed', returned: 'status-returned' };
  return `<span class="badge ${classes[status] || ''}">${labels[status] || engineerEscape(status)}</span>`;
}

function engineerUpPriorityBadge(priority) {
  const classes = { low: 'status-completed', medium: 'status-planning', high: 'status-delayed', urgent: 'status-cancelled' };
  const label = String(priority || '').charAt(0).toUpperCase() + String(priority || '').slice(1);
  return `<span class="badge ${classes[priority] || ''}">${engineerEscape(label)}</span>`;
}

function engineerUpConditionOptions(selected = '') {
  return '<option value="">Select</option>' + ENGINEER_UP_CONDITIONS.map(c =>
    `<option value="${c}" ${selected === c ? 'selected' : ''}>${c}</option>`).join('');
}

/* ---- Urban Planning Inspection: incoming requests + inspection form ---- */

function engineerRenderUrbanPlanningPage() {
  const page = document.getElementById('page-urban-planning-inspection');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Urban Planning Inspection</h1>
        <p class="engineer-scope-note">Incoming road inspection requests from the Urban Planning System. Road data below is theirs — only the inspection result is ours to fill in.</p>
      </div>
    </div>
    <article class="engineer-history-card">
      <div id="engineerUpList"><p class="empty-state">Loading...</p></div>
      <div class="pagination-wrap" id="engineerUpPager"></div>
    </article>
  `;
  engineerUpListState.page = 1;
  engineerLoadUpList();
}

async function engineerLoadUpList() {
  const container = document.getElementById('engineerUpList');
  const pager = document.getElementById('engineerUpPager');
  if (!container) return;

  try {
    const result = await engineerUpGet('list_requests', { page: engineerUpListState.page, per_page: engineerUpListState.perPage });

    container.innerHTML = result.data.length ? `
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Road ID</th><th>Road Name</th><th>Barangay</th><th>District</th><th>Type</th>
              <th>Length</th><th>Priority</th><th>Status</th><th>Requested By</th><th>Request Date</th><th>Map</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            ${result.data.map(row => `
              <tr>
                <td><span class="proj-id">${engineerEscape(row.road_id)}</span></td>
                <td>${engineerEscape(row.road_name)}</td>
                <td>${engineerEscape(row.barangay)}</td>
                <td>${engineerEscape(row.district)}</td>
                <td>${engineerEscape(row.road_type || '—')}</td>
                <td>${row.road_length ? Number(row.road_length).toFixed(1) + ' km' : '—'}</td>
                <td>${engineerUpPriorityBadge(row.priority)}</td>
                <td>${engineerUpStatusBadge(row.status)}</td>
                <td>${engineerEscape(row.requested_by || '—')}</td>
                <td>${engineerDate(row.request_date)}</td>
                <td>${row.road_latitude && row.road_longitude
                  ? `<button type="button" class="btn-secondary btn-compact" onclick="engineerUpShowMap(${row.road_latitude}, ${row.road_longitude}, '${engineerEscape(row.road_name).replace(/'/g, "\\'")}')">View</button>`
                  : '—'}</td>
                <td><button type="button" class="btn-primary btn-compact" onclick="engineerUpOpenInspectionForm(${row.id})">Inspect</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    ` : '<p class="empty-state">No pending inspection requests from the Urban Planning System.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerUpListState.page = nextPage; engineerLoadUpList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load inspection requests.</p>';
  }
}

function engineerUpShowMap(lat, lng, label) {
  engineerOpenModal(`Map Location — ${label}`, `<div id="engineerUpMapView" style="height:320px;border-radius:8px;overflow:hidden;"></div>`);
  if (engineerUpMapInstance) {
    engineerUpMapInstance.remove();
    engineerUpMapInstance = null;
  }
  setTimeout(() => {
    const container = document.getElementById('engineerUpMapView');
    if (!container || typeof L === 'undefined') return;
    engineerUpMapInstance = L.map('engineerUpMapView').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(engineerUpMapInstance);
    // Same pin design as every other project map (assets/js/project-map.js)
    // in place of Leaflet's plain default marker; this view has no
    // status/rating data to preview, so it's the neutral variant.
    const pinIcon = window.ProjectMap ? window.ProjectMap.pinIcon(null, { neutral: true }) : undefined;
    L.marker([lat, lng], pinIcon ? { icon: pinIcon } : {}).addTo(engineerUpMapInstance);
    setTimeout(() => engineerUpMapInstance.invalidateSize(), 50);
  }, 50);
}

async function engineerUpOpenInspectionForm(id) {
  let detail;
  try {
    detail = await engineerUpGet('detail', { id });
  } catch (error) {
    engineerToast(error.message, 'error');
    return;
  }

  const engineerName = document.querySelector('.user-name')?.textContent?.trim() || 'You';

  engineerOpenModal(`Inspect — ${engineerEscape(detail.road_name)}`, `
    <div class="engineer-up-detail" style="margin-bottom:14px;">
      <div class="form-grid">
        <div><p class="modal-label">ROAD ID</p><p class="modal-val">${engineerEscape(detail.road_id)}</p></div>
        <div><p class="modal-label">BARANGAY / DISTRICT</p><p class="modal-val">${engineerEscape(detail.barangay)}, ${engineerEscape(detail.district)}</p></div>
        <div><p class="modal-label">ROAD TYPE</p><p class="modal-val">${engineerEscape(detail.road_type || '—')}</p></div>
        <div><p class="modal-label">ROAD LENGTH</p><p class="modal-val">${detail.road_length ? Number(detail.road_length).toFixed(1) + ' km' : '—'}</p></div>
        <div><p class="modal-label">PRIORITY</p><p class="modal-val">${engineerUpPriorityBadge(detail.priority)}</p></div>
        <div><p class="modal-label">REQUESTED BY</p><p class="modal-val">${engineerEscape(detail.requested_by || '—')}</p></div>
      </div>
    </div>
    <form id="engineerUpForm" enctype="multipart/form-data">
      <input type="hidden" name="id" value="${detail.id}">
      <div class="form-grid">
        <div class="form-group">
          <label>Inspection Date *</label>
          <input class="form-input" type="date" name="inspection_date" required value="${new Date().toISOString().slice(0, 10)}">
        </div>
        <div class="form-group">
          <label>Engineer Assigned</label>
          <input class="form-input" disabled value="${engineerEscape(engineerName)}">
        </div>
        <div class="form-group">
          <label>Road Condition *</label>
          <select class="form-input" name="road_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Surface Condition *</label>
          <select class="form-input" name="surface_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Drainage Condition *</label>
          <select class="form-input" name="drainage_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Sidewalk Condition *</label>
          <select class="form-input" name="sidewalk_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Streetlight Condition *</label>
          <select class="form-input" name="streetlight_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Traffic Sign Condition *</label>
          <select class="form-input" name="traffic_sign_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Overall Condition *</label>
          <select class="form-input" name="overall_condition" required>${engineerUpConditionOptions()}</select>
        </div>
        <div class="form-group">
          <label>Severity *</label>
          <select class="form-input" name="severity" required>
            <option value="">Select</option>
            ${ENGINEER_UP_SEVERITIES.map(s => `<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group" style="grid-column: span 2;">
          <label>Recommendation *</label>
          <select class="form-input" name="recommendation" required>
            <option value="">Select</option>
            ${ENGINEER_UP_RECOMMENDATIONS.map(r => `<option value="${r}">${r}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>GPS Latitude <small>(optional)</small></label>
          <input class="form-input" type="number" step="0.0000001" name="latitude" placeholder="e.g. 14.6760">
        </div>
        <div class="form-group">
          <label>GPS Longitude <small>(optional)</small></label>
          <input class="form-input" type="number" step="0.0000001" name="longitude" placeholder="e.g. 121.0437">
        </div>
      </div>
      <div class="form-group">
        <label>Remarks</label>
        <textarea class="form-input" name="remarks" rows="3" placeholder="Observations for the record"></textarea>
      </div>
      <div class="form-group">
        <label>Upload Photos</label>
        <input class="form-input" type="file" name="photos[]" accept=".png,.jpg,.jpeg,.webp" multiple>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="engineerCloseModal()">Cancel</button>
        <button type="submit" class="btn-primary">Submit Inspection</button>
      </div>
    </form>
  `);

  document.getElementById('engineerUpForm').addEventListener('submit', engineerUpSubmitInspection);
}

async function engineerUpSubmitInspection(event) {
  event.preventDefault();
  const formEl = event.target;
  engineerClearFieldErrors(formEl);
  const form = new FormData(formEl);

  try {
    await engineerUpPostForm('submit_inspection', form);
    engineerToast('Inspection submitted — now available to the Urban Planning System.');
    engineerCloseModal();
    engineerLoadUpList();
  } catch (error) {
    engineerShowFieldErrors(formEl, error.fieldErrors);
    engineerToast(error.message, 'error');
  }
}

/* ---- Road Inspection History: read-only, search/filter/export/print ---- */

function engineerRenderRoadHistoryPage(prefillSearch = '') {
  const page = document.getElementById('page-road-inspection-history');
  page.innerHTML = `
    <div class="page-header">
      <div>
        <h1 class="page-title">Road Inspection History</h1>
        <p class="engineer-scope-note">Read-only record of completed and returned Urban Planning inspections.</p>
      </div>
    </div>
    <div class="filter-bar" style="flex-wrap:wrap;">
      <input class="filter-input" id="engineerUpSearch" placeholder="Search road, barangay, road ID..." value="${engineerEscape(prefillSearch)}">
      <select class="filter-select" id="engineerUpStatusFilter">
        <option value="">All Statuses</option>
        <option value="completed">Completed</option>
        <option value="returned">Returned</option>
      </select>
      <select class="filter-select" id="engineerUpConditionFilter">
        <option value="">All Conditions</option>
        ${ENGINEER_UP_CONDITIONS.map(c => `<option value="${c}">${c}</option>`).join('')}
      </select>
      <select class="filter-select" id="engineerUpRecommendationFilter">
        <option value="">All Recommendations</option>
        ${ENGINEER_UP_RECOMMENDATIONS.map(r => `<option value="${r}">${r}</option>`).join('')}
      </select>
      <input class="filter-input" id="engineerUpDateFrom" type="date" style="max-width:150px;">
      <input class="filter-input" id="engineerUpDateTo" type="date" style="max-width:150px;">
      <button class="btn-secondary btn-compact" id="engineerUpApplyFilters" type="button">Apply</button>
      <button class="btn-secondary" style="margin-left:auto;" type="button" id="engineerUpExportBtn">Export CSV</button>
    </div>
    <article class="engineer-history-card">
      <div id="engineerUpHistoryList"><p class="empty-state">Loading...</p></div>
      <div class="pagination-wrap" id="engineerUpHistoryPager"></div>
    </article>
  `;

  document.getElementById('engineerUpApplyFilters').addEventListener('click', () => {
    engineerUpHistoryState = {
      ...engineerUpHistoryState,
      page: 1,
      search: document.getElementById('engineerUpSearch').value,
      status: document.getElementById('engineerUpStatusFilter').value,
      overall_condition: document.getElementById('engineerUpConditionFilter').value,
      recommendation: document.getElementById('engineerUpRecommendationFilter').value,
      date_from: document.getElementById('engineerUpDateFrom').value,
      date_to: document.getElementById('engineerUpDateTo').value,
    };
    engineerLoadUpHistory();
  });
  document.getElementById('engineerUpExportBtn').addEventListener('click', engineerUpExportHistoryCsv);

  engineerUpHistoryState = { ...engineerUpHistoryState, page: 1, search: prefillSearch };
  engineerLoadUpHistory();
}

async function engineerLoadUpHistory() {
  const container = document.getElementById('engineerUpHistoryList');
  const pager = document.getElementById('engineerUpHistoryPager');
  if (!container) return;
  const state = engineerUpHistoryState;

  try {
    const result = await engineerUpGet('list_history', {
      page: state.page, per_page: state.perPage, search: state.search, status: state.status,
      overall_condition: state.overall_condition, recommendation: state.recommendation,
      date_from: state.date_from, date_to: state.date_to,
    });

    container.innerHTML = result.data.length ? `
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Inspection Date</th><th>Road Name</th><th>Barangay</th><th>Engineer</th>
              <th>Condition Rating</th><th>Recommendation</th><th>Status</th><th>Photos</th><th>Report</th>
            </tr>
          </thead>
          <tbody>
            ${result.data.map(row => `
              <tr>
                <td>${engineerDate(row.inspection_date)}</td>
                <td>${engineerEscape(row.road_name)}</td>
                <td>${engineerEscape(row.barangay)}</td>
                <td>${engineerEscape(row.engineer_name || '—')}</td>
                <td>${engineerUpConditionBadge(row.overall_condition)}</td>
                <td>${engineerEscape(row.recommendation || '—')}</td>
                <td>${engineerUpStatusBadge(row.status)}</td>
                <td>${row.photo_count > 0 ? `${row.photo_count} photo(s)` : '—'}</td>
                <td><button type="button" class="btn-secondary btn-compact" onclick="engineerUpPrintReport(${row.id})">Print</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    ` : '<p class="empty-state">No inspection history matches this filter.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { engineerUpHistoryState.page = nextPage; engineerLoadUpHistory(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load inspection history.</p>';
  }
}

async function engineerUpPrintReport(id) {
  let detail;
  try {
    detail = await engineerUpGet('detail', { id });
  } catch (error) {
    engineerToast(error.message, 'error');
    return;
  }

  const win = window.open('', '_blank');
  win.document.write(`
    <html><head><title>Inspection Report — ${detail.road_id}</title>
    <style>
      body { font-family: sans-serif; padding: 32px; color: #1e293b; }
      h1 { font-size: 1.3rem; } h2 { font-size: .95rem; margin-top: 20px; color: #475569; }
      table { width: 100%; border-collapse: collapse; margin-top: 8px; }
      td, th { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: .85rem; }
      .photos img { width: 140px; height: 100px; object-fit: cover; margin: 6px 6px 0 0; border-radius: 6px; }
    </style></head><body>
      <h1>Road Inspection Report</h1>
      <p>${engineerEscape(detail.road_name)} (${engineerEscape(detail.road_id)}) — ${engineerEscape(detail.barangay)}, ${engineerEscape(detail.district)}</p>
      <h2>Inspection Summary</h2>
      <table>
        <tr><th>Inspection Date</th><td>${engineerDate(detail.inspection_date)}</td></tr>
        <tr><th>Overall Condition</th><td>${engineerEscape(detail.overall_condition || '—')}</td></tr>
        <tr><th>Severity</th><td>${engineerEscape(detail.severity || '—')}</td></tr>
        <tr><th>Recommendation</th><td>${engineerEscape(detail.recommendation || '—')}</td></tr>
        <tr><th>Remarks</th><td>${engineerEscape(detail.remarks || '—')}</td></tr>
      </table>
      <h2>Condition Detail</h2>
      <table>
        <tr><th>Road</th><td>${engineerEscape(detail.road_condition || '—')}</td></tr>
        <tr><th>Surface</th><td>${engineerEscape(detail.surface_condition || '—')}</td></tr>
        <tr><th>Drainage</th><td>${engineerEscape(detail.drainage_condition || '—')}</td></tr>
        <tr><th>Sidewalk</th><td>${engineerEscape(detail.sidewalk_condition || '—')}</td></tr>
        <tr><th>Streetlight</th><td>${engineerEscape(detail.streetlight_condition || '—')}</td></tr>
        <tr><th>Traffic Sign</th><td>${engineerEscape(detail.traffic_sign_condition || '—')}</td></tr>
      </table>
      ${detail.photos?.length ? `<h2>Photos</h2><div class="photos">${detail.photos.map(p => `<img src="${window.BASE_PATH}${p.photo_path}">`).join('')}</div>` : ''}
    </body></html>
  `);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 300);
}

async function engineerUpExportHistoryCsv() {
  const state = engineerUpHistoryState;
  let rows = [];
  try {
    const result = await engineerUpGet('list_history', {
      page: 1, per_page: 100, search: state.search, status: state.status,
      overall_condition: state.overall_condition, recommendation: state.recommendation,
      date_from: state.date_from, date_to: state.date_to,
    });
    rows = result.data;
  } catch (error) {
    engineerToast('Failed to export history.', 'error');
    return;
  }

  const header = ['Inspection Date', 'Road Name', 'Barangay', 'Engineer', 'Condition Rating', 'Recommendation', 'Status'];
  const csvRows = rows.map(row => [
    row.inspection_date || '', row.road_name, row.barangay, row.engineer_name || '',
    row.overall_condition || '', row.recommendation || '', row.status,
  ]);
  const csv = [header, ...csvRows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');

  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'road-inspection-history.csv';
  link.click();
  URL.revokeObjectURL(url);
}

/* ---- "My Field Status" (topbar user-menu, see includes/topbar.php) ----
   Reports presence/work status to the Admin/Head Office Engineer Live
   Status widget (assets/js/engineer-status-widget.js) via a separate
   endpoint (api/engineer-status.php) from the rest of this portal. */
const ENGINEER_STATUS_PROJECT_REQUIRED = ['on_site', 'on_inspection', 'field_assignment'];
// Same status→color mapping as the Admin/Head Office widget's work-status
// dots (assets/css/engineer-status-widget.css's .work-* classes), so the
// color means the same thing everywhere it appears in the app.
const ENGINEER_STATUS_META = {
  available: { label: 'Available', ring: '#22c55e' },
  on_site: { label: 'On Site', ring: '#3b82f6' },
  on_inspection: { label: 'On Inspection', ring: '#f97316' },
  field_assignment: { label: 'Field Assignment', ring: '#a855f7' },
  busy: { label: 'Busy', ring: '#eab308' },
  off_duty: { label: 'Off Duty', ring: '#64748b' },
};

// Colors the topbar avatar's ring to match "My Field Status" — no separate
// badge element; the avatar itself (#userMenuBtn) is the indicator.
function engineerStatusRenderBadge(workStatus) {
  const avatar = document.getElementById('userMenuBtn');
  if (!avatar) return;
  const meta = ENGINEER_STATUS_META[workStatus] || ENGINEER_STATUS_META.available;
  avatar.style.setProperty('--status-ring-color', meta.ring);
  avatar.title = `My Field Status: ${meta.label} (click to change)`;
}

async function engineerStatusGet(action) {
  const response = await fetch(`${window.ENGINEER_STATUS_ENDPOINT}?action=${encodeURIComponent(action)}`, {
    headers: ENGINEER_CSRF_HEADERS,
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

async function engineerStatusPost(body) {
  const response = await fetch(`${window.ENGINEER_STATUS_ENDPOINT}?action=set_status`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...ENGINEER_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.error) throw engineerErrorFrom(data, response);
  return data;
}

function engineerStatusToggleProjectFields(workStatus) {
  const wrap = document.getElementById('engMyStatusProjectWrap');
  if (wrap) wrap.style.display = ENGINEER_STATUS_PROJECT_REQUIRED.includes(workStatus) ? 'flex' : 'none';
}

function engineerStatusSave() {
  const select = document.getElementById('engMyStatusSelect');
  const projectSelect = document.getElementById('engMyStatusProject');
  const activityInput = document.getElementById('engMyStatusActivity');
  if (!select) return;

  const workStatus = select.value;
  const needsProject = ENGINEER_STATUS_PROJECT_REQUIRED.includes(workStatus);
  engineerStatusRenderBadge(workStatus); // optimistic — flips instantly, no need to wait on the round trip
  engineerStatusPost({
    work_status: workStatus,
    project_id: needsProject ? Number(projectSelect.value || 0) : null,
    activity: needsProject ? activityInput.value : null,
  }).catch(error => engineerToast(error.message, 'error'));
}

async function engineerStatusInit() {
  const select = document.getElementById('engMyStatusSelect');
  if (!select) return; // topbar only renders this for role === 'engineer'

  const projectSelect = document.getElementById('engMyStatusProject');
  const activityInput = document.getElementById('engMyStatusActivity');

  try {
    const mine = await engineerStatusGet('mine');
    projectSelect.innerHTML = mine.projects.map(p =>
      `<option value="${p.id}">${engineerEscape(p.project_code)} — ${engineerEscape(p.name)}</option>`
    ).join('') || '<option value="">No active assignments</option>';

    select.value = mine.work_status;
    if (mine.project_id) projectSelect.value = String(mine.project_id);
    activityInput.value = mine.activity || '';
    engineerStatusToggleProjectFields(mine.work_status);
    engineerStatusRenderBadge(mine.work_status);
  } catch (error) {
    // Non-critical widget-reporting feature — the rest of the portal keeps working.
  }

  select.addEventListener('change', () => {
    engineerStatusToggleProjectFields(select.value);
    engineerStatusSave();
  });
  projectSelect.addEventListener('change', engineerStatusSave);
  activityInput.addEventListener('blur', engineerStatusSave);
}

window.engineerUpOpenInspectionForm = engineerUpOpenInspectionForm;
window.engineerUpShowMap = engineerUpShowMap;
window.engineerUpPrintReport = engineerUpPrintReport;

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
  engineerStatusInit();
  try {
    await engineerRefreshData();
  } catch (error) {
    engineerToast(error.message, 'error');
  }
});
