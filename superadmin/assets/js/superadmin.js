/* Super Admin portal frontend - platform governance workspace */
const SA_API = window.BASE_PATH + 'superadmin/api/portal.php';
const SA_ACCOUNTS_API = window.BASE_PATH + 'superadmin/api/accounts.php';
const SA_USER_API = window.BASE_PATH + 'api/user.php';
const SA_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};
const SA_ROLE_LABELS = {
  super_admin: 'Super Admin',
  admin: 'LGU Admin / Engineering Head',
  bac: 'BAC (Bids & Awards Committee)',
  engineer: 'Engineer',
  contractor: 'Contractor',
  citizen: 'Citizen',
};
const SA_DOCUMENT_TYPES = [
  'General', 'DTI Registration', 'SEC Registration', 'PhilGEPS Registration',
  'Business Permit', 'PRC License', 'Other',
];

let saCurrentPage = 'dashboard';

/* Per-section pagination/filter state — each section fetches and renders itself
   independently now, instead of one global saData blob fetched via `summary`. */
let saListState = {
  users: { page: 1, perPage: 10, search: '', role: '', status: '' },
  pendingCitizens: { page: 1, perPage: 10 },
  documents: { page: 1, perPage: 10 },
  staffRequests: { page: 1, perPage: 10 },
  audit: { page: 1, perPage: 10, search: '' },
  activity: { page: 1, perPage: 10, search: '' },
  logins: { page: 1, perPage: 10, search: '', result: '' },
  loginRisk: { page: 1, perPage: 10 },
  loginLockouts: { page: 1, perPage: 10 },
};

/* Row caches so click handlers (Change Role, Verify, Unlock...) can look up
   full record details by index/id without a second round-trip. */
let saUsersCache = [];
let saPendingCitizensCache = [];
let saDocumentsCache = [];
let saStaffRequestsCache = [];
let saLoginLockoutsCache = [];
let saDashboardData = { stats: {}, activity: [], health: {} };

function saEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function saDateTime(value) {
  if (!value) return '-';
  return String(value).slice(0, 16).replace('T', ' ');
}

function saLabel(value) {
  return String(value || '').replaceAll('_', ' ');
}

function saRoleLabel(value) {
  return SA_ROLE_LABELS[value] || saLabel(value);
}

function saBadge(value, extraClass = '') {
  return `<span class="badge status-${saEscape(value)} ${extraClass}">${saEscape(saLabel(value))}</span>`;
}

function saToast(message, type = 'success') {
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

function saOpenModal(title, html) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function saCloseModal() {
  document.getElementById('modalOverlay')?.classList.remove('open');
}

function saRow(title, meta, side) {
  return `
    <div class="sa-row">
      <div class="sa-row-main">
        <strong>${saEscape(title)}</strong>
        <span>${saEscape(meta)}</span>
      </div>
      <div>${side}</div>
    </div>
  `;
}

/* ---- Networking ------------------------------------------------------- */

function saErrorFrom(result, response) {
  const err = new Error(result?.error || `HTTP ${response.status}`);
  err.fieldErrors = result?.errors || null;
  return err;
}

async function saGetFrom(baseUrl, action, params = {}) {
  const query = new URLSearchParams({ action });
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      query.set(key, value);
    }
  });
  const response = await fetch(`${baseUrl}?${query.toString()}`);
  const result = await response.json();
  if (!response.ok || result.error) throw saErrorFrom(result, response);
  return result;
}

async function saGet(action, params = {}) {
  return saGetFrom(SA_API, action, params);
}

async function saGetAccounts(action, params = {}) {
  return saGetFrom(SA_ACCOUNTS_API, action, params);
}

async function saRequest(action = 'summary', options = {}) {
  const response = await fetch(`${SA_API}?action=${encodeURIComponent(action)}`, options);
  const result = await response.json();
  if (!response.ok || result.error) throw saErrorFrom(result, response);
  return result;
}

async function saPost(action, body) {
  return saRequest(action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...SA_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
}

async function saPostForm(action, formData) {
  const response = await fetch(`${SA_ACCOUNTS_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { ...SA_CSRF_HEADERS },
    body: formData,
  });
  const result = await response.json();
  if (!response.ok || result.error) throw saErrorFrom(result, response);
  return result;
}

/* review_document/decide_staff_request/etc. live in accounts.php, not
   portal.php — this is the JSON-body counterpart to saPostForm above. */
async function saPostAccounts(action, body) {
  const response = await fetch(`${SA_ACCOUNTS_API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...SA_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
  const result = await response.json();
  if (!response.ok || result.error) throw saErrorFrom(result, response);
  return result;
}

/* ---- Inline field-error rendering (new — existing endpoints keep working
   via the toast-only path since `error` is always still present too) ------ */

function saClearFieldErrors(form) {
  form.querySelectorAll('.field-error-msg').forEach(el => el.remove());
  form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
}

function saShowFieldErrors(form, fieldErrors) {
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

function saDocRowHtml(index) {
  return `
    <div class="doc-row" data-doc-index="${index}">
      <select class="form-input" name="documents[${index}][document_type]">
        ${SA_DOCUMENT_TYPES.map(type => `<option value="${saEscape(type)}">${saEscape(type)}</option>`).join('')}
      </select>
      <input class="form-input" type="text" name="documents[${index}][title]" placeholder="Document title">
      <input class="form-input" type="file" name="document_files[${index}]">
      <button type="button" class="doc-row-remove" aria-label="Remove document row">&times;</button>
    </div>
  `;
}

function saWireDocRows(container, addBtn) {
  let nextIndex = 1;
  addBtn.addEventListener('click', () => {
    container.insertAdjacentHTML('beforeend', saDocRowHtml(nextIndex));
    nextIndex += 1;
  });
  container.addEventListener('click', event => {
    if (event.target.classList.contains('doc-row-remove')) {
      event.target.closest('.doc-row')?.remove();
    }
  });
}

function saDocSectionHtml(label) {
  return `
    <div class="doc-section">
      <label>${saEscape(label)}</label>
      <div class="doc-rows" id="docRows"></div>
      <button type="button" class="doc-add-btn" id="docAddBtn">+ Add another document</button>
    </div>
  `;
}

function saShowTempPasswordModal(name, tempPassword, context = 'created') {
  const copy = context === 'reset'
    ? `<strong>${saEscape(name)}</strong>'s password has been reset.`
    : `<strong>${saEscape(name)}</strong>'s account was created successfully.`;

  saOpenModal(context === 'reset' ? 'Password Reset' : 'Account Created', `
    <p>${copy}</p>
    <div class="form-group" style="margin-top:12px;">
      <label>Temporary password (shown once — copy it now)</label>
      <input class="form-input" readonly value="${saEscape(tempPassword)}" onclick="this.select()">
    </div>
    <div class="form-actions">
      <button class="btn-primary" type="button" onclick="saCloseModal()">Done</button>
    </div>
  `);
}

/* ---- Refresh dispatch --------------------------------------------------- */

async function saRefresh(page = saCurrentPage) {
  try {
    await saRenderers[page]?.();
  } catch (error) {
    saToast(error.message || 'Failed to load Super Admin data.', 'error');
  }
}

/* ---- Dashboard ----------------------------------------------------------- */

async function saRenderDashboard() {
  try {
    saDashboardData = await saGet('summary');
  } catch (error) {
    saToast(error.message || 'Failed to load dashboard data.', 'error');
    return;
  }

  const stats = saDashboardData.stats || {};
  document.getElementById('saTotalUsers').textContent = stats.total_users || 0;
  document.getElementById('saActiveInactive').textContent = `${stats.active_users || 0} / ${stats.inactive_users || 0}`;
  document.getElementById('saPendingCount').textContent = stats.pending_verifications || 0;
  document.getElementById('saFailedLogins').textContent = stats.failed_logins_24h || 0;

  const activity = saDashboardData.activity || [];
  document.getElementById('saActivityPreview').innerHTML = activity.length
    ? activity.map(item => saRow(
      saLabel(item.action),
      `${item.actor_name || 'System'} - ${saDateTime(item.created_at)}`,
      item.details ? saEscape(item.details).slice(0, 60) : ''
    )).join('')
    : '<p class="empty-state">No system activity recorded yet.</p>';

  const byRole = stats.by_role || {};
  const roleEntries = Object.entries(byRole);
  document.getElementById('saRoleBreakdown').innerHTML = roleEntries.length
    ? roleEntries.map(([role, count]) => saRow(saRoleLabel(role), `${count} account${count === 1 ? '' : 's'}`, '')).join('')
    : '<p class="empty-state">No user accounts found.</p>';

  try {
    saRenderRoleChart(byRole);
    saRenderLoginTrendChart(saDashboardData.login_trend || []);
  } catch (error) {
    console.error('Failed to render dashboard charts:', error);
  }
}

let saLoginTrendChartInst = null;

function saRenderLoginTrendChart(rows) {
  const ctx = document.getElementById('saLoginTrendChart')?.getContext('2d');
  if (!ctx) return;
  if (saLoginTrendChartInst) saLoginTrendChartInst.destroy();

  // Last seven days, oldest first, zero-filled from the query rows
  // (rows carry MySQL DATE strings, e.g. "2026-07-19").
  const days = [];
  const now = new Date();
  for (let i = 6; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() - i);
    days.push({
      key: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
      label: d.toLocaleString('en', { weekday: 'short' }),
    });
  }
  const success = days.map(() => 0);
  const failed = days.map(() => 0);
  rows.forEach(row => {
    const index = days.findIndex(day => day.key === row.d);
    if (index === -1) return;
    success[index] = Number(row.success);
    failed[index] = Number(row.failed);
  });

  const gridColor = document.documentElement.getAttribute('data-theme') === 'dark'
    ? 'rgba(148,163,184,.18)' : 'rgba(100,116,139,.12)';

  saLoginTrendChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels: days.map(day => day.label),
      datasets: [
        {
          label: 'Successful',
          data: success,
          borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.06)',
          borderWidth: 2.5, tension: 0.4, fill: true,
          pointBackgroundColor: '#22c55e', pointBorderColor: '#fff',
          pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
        },
        {
          label: 'Failed',
          data: failed,
          borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.06)',
          borderWidth: 2.5, tension: 0.4, fill: true,
          pointBackgroundColor: '#ef4444', pointBorderColor: '#fff',
          pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
        },
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
        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } }, grid: { color: gridColor }, border: { display: false } },
      },
    },
  });
}

let saRoleChartInst = null;

function saRenderRoleChart(byRole) {
  const ctx = document.getElementById('saRoleChart')?.getContext('2d');
  if (!ctx) return;
  if (saRoleChartInst) saRoleChartInst.destroy();

  const roleColors = {
    super_admin: '#7c3aed', admin: '#6366f1', bac: '#ef4444',
    engineer: '#16a34a', contractor: '#d97706', citizen: '#3b82f6',
  };
  const entries = Object.entries(byRole);
  const total = entries.reduce((sum, [, count]) => sum + Number(count), 0) || 1;

  saRoleChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: entries.map(([role]) => saRoleLabel(role)),
      datasets: [{
        data: entries.map(([, count]) => count),
        backgroundColor: entries.map(([role]) => roleColors[role] || '#94a3b8'),
        borderColor: entries.map(() => '#fff'), borderWidth: 3, hoverOffset: 6,
      }],
    },
    options: {
      responsive: false, cutout: '70%',
      animation: { duration: 900 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2a3b',
          callbacks: { label: c => ` ${c.label}: ${c.raw}` },
        },
      },
    },
  });

  document.getElementById('saRoleChartTotal').textContent = total;

  document.getElementById('saRoleChartLegend').innerHTML = entries.map(([role, count]) => `
    <div class="budget-legend-item">
      <span class="legend-dot" style="background:${roleColors[role] || '#94a3b8'};"></span>
      <span>${saEscape(saRoleLabel(role))} <strong>${count}</strong></span>
    </div>
  `).join('');
}

/* ---- User & Role Governance ---------------------------------------------- */

async function saRenderUserGovernance() {
  await Promise.all([
    saLoadUsersTable(),
    saLoadPendingCitizens(),
    saLoadPendingDocuments(),
    saLoadPendingStaffRequests(),
  ]);
}

async function saLoadUsersTable() {
  const table = document.getElementById('saUsersTable');
  const pager = document.getElementById('saUsersPager');
  const state = saListState.users;

  try {
    const result = await saGet('list_users', {
      page: state.page, per_page: state.perPage, search: state.search, role: state.role, status: state.status,
    });
    saUsersCache = result.data;

    table.innerHTML = result.data.length ? `
      <table class="data-table">
        <thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
          ${result.data.map(u => {
            const isSelf = Number(u.id) === Number(window.CURRENT_USER_ID);
            return `
            <tr>
              <td><strong>${saEscape(u.full_name)}</strong>${isSelf ? ' <small>(you)</small>' : ''}</td>
              <td>${saEscape(u.username)}<br><small>${saEscape(u.email)}</small></td>
              <td>${saBadge(u.role)}</td>
              <td>${saBadge(u.status)}</td>
              <td>${saDateTime(u.last_login)}</td>
              <td>
                ${isSelf ? '<small>No self-actions</small>' : `
                  <button class="btn-secondary btn-compact" type="button" onclick="saOpenStatusConfirm(${u.id})">
                    ${u.status === 'active' ? 'Deactivate' : 'Activate'}
                  </button>
                  <button class="btn-secondary btn-compact" type="button" onclick="saOpenRoleForm(${u.id})">Change Role</button>
                  <button class="btn-secondary btn-compact" type="button" onclick="saOpenResetPasswordConfirm(${u.id})">Reset Password</button>
                `}
              </td>
            </tr>
          `;
          }).join('')}
        </tbody>
      </table>
    ` : '<p class="empty-state">No user accounts found.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.users.page = nextPage; saLoadUsersTable(); },
    });
  } catch (error) {
    table.innerHTML = '<p class="empty-state">Unable to load users.</p>';
    saToast(error.message, 'error');
  }
}

async function saLoadPendingCitizens() {
  const container = document.getElementById('saPendingCitizens');
  const pager = document.getElementById('saPendingCitizensPager');
  const state = saListState.pendingCitizens;

  try {
    const result = await saGet('list_pending_citizens', { page: state.page, per_page: state.perPage });
    saPendingCitizensCache = result.data;

    container.innerHTML = result.data.length
      ? result.data.map(citizen => `
        <div class="sa-row">
          <div class="sa-row-main">
            <strong>${saEscape(citizen.first_name)} ${saEscape(citizen.last_name)}</strong>
            <span>${saEscape(citizen.id_type)} - ${saEscape(citizen.id_number)} - ${saEscape(citizen.barangay || 'No barangay on file')}</span>
          </div>
          <div>
            <button class="btn-primary btn-compact" type="button" onclick="saOpenCitizenDecision(${citizen.id}, 'verified')">Verify</button>
            <button class="btn-secondary btn-compact" type="button" onclick="saOpenCitizenDecision(${citizen.id}, 'rejected')">Reject</button>
          </div>
        </div>
      `).join('')
      : '<p class="empty-state">No pending citizen ID verifications.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.pendingCitizens.page = nextPage; saLoadPendingCitizens(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load pending verifications.</p>';
  }
}

async function saLoadPendingDocuments() {
  const container = document.getElementById('saPendingDocuments');
  const pager = document.getElementById('saPendingDocumentsPager');
  const state = saListState.documents;

  try {
    const result = await saGetAccounts('list_documents', { page: state.page, per_page: state.perPage, status: 'pending' });
    saDocumentsCache = result.data;

    container.innerHTML = result.data.length
      ? result.data.map((doc, idx) => `
        <div class="sa-row">
          <div class="sa-row-main">
            <strong>${saEscape(doc.title)}</strong>
            <span>${saEscape(doc.document_type)} - ${saEscape(doc.owner_type)} #${doc.owner_id} - uploaded by ${saEscape(doc.uploaded_by_name || 'Unknown')} - ${saDateTime(doc.created_at)}</span>
          </div>
          <div>
            <button class="btn-primary btn-compact" type="button" onclick="saOpenDocumentReview(${idx})">Review</button>
          </div>
        </div>
      `).join('')
      : '<p class="empty-state">No documents awaiting review.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.documents.page = nextPage; saLoadPendingDocuments(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load documents.</p>';
  }
}

function saOpenDocumentReview(index) {
  const doc = saDocumentsCache[index];
  if (!doc) {
    saToast('Document not found.', 'error');
    return;
  }

  saOpenModal('Review Document', `
    <p><strong>${saEscape(doc.title)}</strong> (${saEscape(doc.document_type)})</p>
    <p><small>${saEscape(doc.owner_type)} #${doc.owner_id} - ${saEscape(doc.original_name)} - uploaded by ${saEscape(doc.uploaded_by_name || 'Unknown')}</small></p>
    <p><a href="${saEscape(window.BASE_PATH + doc.file_path)}" target="_blank" rel="noopener">Open document in a new tab</a></p>
    <form id="saDocumentReviewForm">
      <div class="form-group" style="margin-top:12px;">
        <label>Remarks (optional)</label>
        <textarea class="form-input" name="remarks" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-secondary" type="submit" data-decision="rejected">Reject</button>
        <button class="btn-primary" type="submit" data-decision="verified">Verify</button>
      </div>
    </form>
  `);

  document.getElementById('saDocumentReviewForm').addEventListener('submit', async event => {
    event.preventDefault();
    const decision = event.submitter?.dataset.decision || 'verified';
    const remarks = new FormData(event.target).get('remarks') || '';
    try {
      await saPostAccounts('review_document', { document_id: doc.id, decision, remarks });
      saCloseModal();
      saToast(`Document ${decision}.`);
      await saLoadPendingDocuments();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

async function saLoadPendingStaffRequests() {
  const container = document.getElementById('saPendingStaffRequests');
  const pager = document.getElementById('saPendingStaffRequestsPager');
  const state = saListState.staffRequests;

  try {
    const result = await saGetAccounts('list_staff_requests', { page: state.page, per_page: state.perPage });
    saStaffRequestsCache = result.data;

    container.innerHTML = result.data.length
      ? result.data.map((req, idx) => `
        <div class="sa-row">
          <div class="sa-row-main">
            <strong>${saEscape(req.full_name)}</strong>
            <span>${saEscape(SA_ROLE_LABELS[req.requested_role] || req.requested_role)} - ${saEscape(req.username)} - ${saEscape(req.email)} - requested by ${saEscape(req.requested_by_name || 'Unknown')} - ${saDateTime(req.created_at)}</span>
          </div>
          <div>
            <button class="btn-primary btn-compact" type="button" onclick="saOpenStaffRequestReview(${idx})">Review</button>
          </div>
        </div>
      `).join('')
      : '<p class="empty-state">No staff account requests awaiting review.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.staffRequests.page = nextPage; saLoadPendingStaffRequests(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load staff account requests.</p>';
  }
}

function saOpenStaffRequestReview(index) {
  const req = saStaffRequestsCache[index];
  if (!req) {
    saToast('Request not found.', 'error');
    return;
  }

  saOpenModal('Review Staff Account Request', `
    <p><strong>${saEscape(req.full_name)}</strong> - ${saEscape(SA_ROLE_LABELS[req.requested_role] || req.requested_role)}</p>
    <p><small>Username: ${saEscape(req.username)} - Email: ${saEscape(req.email)} - requested by ${saEscape(req.requested_by_name || 'Unknown')}</small></p>
    <form id="saStaffRequestForm">
      <div class="form-group" style="margin-top:12px;">
        <label>Reason (required to reject)</label>
        <textarea class="form-input" name="reason" rows="3"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-secondary" type="submit" data-decision="reject">Reject</button>
        <button class="btn-primary" type="submit" data-decision="approve">Approve</button>
      </div>
    </form>
  `);

  document.getElementById('saStaffRequestForm').addEventListener('submit', async event => {
    event.preventDefault();
    const decision = event.submitter?.dataset.decision || 'approve';
    const reason = new FormData(event.target).get('reason') || '';
    try {
      const result = await saPostAccounts('decide_staff_request', { request_id: req.id, decision, reason });
      saCloseModal();
      if (decision === 'approve') {
        saShowTempPasswordModal(req.full_name, result.temp_password, 'created');
      } else {
        saToast('Request rejected.');
      }
      await saLoadPendingStaffRequests();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

function saOpenStatusConfirm(userId) {
  const user = saUsersCache.find(item => Number(item.id) === Number(userId));
  if (!user) {
    saToast('User not found.', 'error');
    return;
  }
  const newStatus = user.status === 'active' ? 'inactive' : 'active';

  saOpenModal(`${newStatus === 'active' ? 'Activate' : 'Deactivate'} Account`, `
    <p>Set <strong>${saEscape(user.full_name)}</strong> (${saEscape(saRoleLabel(user.role))}) to <strong>${saEscape(newStatus)}</strong>?</p>
    ${newStatus === 'inactive' ? '<p><small>They will be signed out and unable to log in until reactivated.</small></p>' : ''}
    <div class="form-actions">
      <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
      <button class="btn-primary" type="button" id="saStatusConfirmBtn">Confirm</button>
    </div>
  `);

  document.getElementById('saStatusConfirmBtn').addEventListener('click', async () => {
    try {
      await saPost('update_status', { user_id: userId, status: newStatus });
      saCloseModal();
      saToast(`${user.full_name} is now ${newStatus}.`);
      await saLoadUsersTable();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

function saOpenRoleForm(userId) {
  const user = saUsersCache.find(item => Number(item.id) === Number(userId));
  if (!user) {
    saToast('User not found.', 'error');
    return;
  }

  saOpenModal('Change Role', `
    <form id="saRoleForm">
      <div class="form-group">
        <label>${saEscape(user.full_name)} - current role: ${saEscape(saRoleLabel(user.role))}</label>
        <select class="form-input" name="role" required>
          ${Object.entries(SA_ROLE_LABELS).map(([value, label]) => `
            <option value="${value}" ${value === user.role ? 'selected' : ''}>${saEscape(label)}</option>
          `).join('')}
        </select>
      </div>
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Save Role</button>
      </div>
    </form>
  `);

  document.getElementById('saRoleForm').addEventListener('submit', async event => {
    event.preventDefault();
    const role = new FormData(event.target).get('role');
    if (role === user.role) {
      saCloseModal();
      return;
    }
    try {
      await saPost('update_role', { user_id: userId, role });
      saCloseModal();
      saToast(`${user.full_name} is now ${saRoleLabel(role)}.`);
      await saLoadUsersTable();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

function saOpenResetPasswordConfirm(userId) {
  const user = saUsersCache.find(item => Number(item.id) === Number(userId));
  if (!user) {
    saToast('User not found.', 'error');
    return;
  }

  saOpenModal('Reset Password', `
    <p>Reset the password for <strong>${saEscape(user.full_name)}</strong> (${saEscape(saRoleLabel(user.role))})?</p>
    <p><small>A new temporary password will be generated. Their current password will stop working immediately.</small></p>
    <div class="form-actions">
      <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
      <button class="btn-primary" type="button" id="saResetPasswordConfirmBtn">Confirm Reset</button>
    </div>
  `);

  document.getElementById('saResetPasswordConfirmBtn').addEventListener('click', async () => {
    try {
      const result = await saPost('reset_user_password', { user_id: userId });
      saShowTempPasswordModal(user.full_name, result.temp_password, 'reset');
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

function saOpenCitizenDecision(citizenId, decision) {
  const citizen = saPendingCitizensCache.find(item => Number(item.id) === Number(citizenId));
  if (!citizen) {
    saToast('Citizen record not found.', 'error');
    return;
  }

  saOpenModal(decision === 'verified' ? 'Verify Citizen ID' : 'Reject Citizen ID', `
    <form id="saCitizenForm">
      <p><strong>${saEscape(citizen.first_name)} ${saEscape(citizen.last_name)}</strong> - ${saEscape(citizen.id_type)} #${saEscape(citizen.id_number)}</p>
      ${decision === 'rejected' ? `
        <div class="form-group" style="margin-top:12px;">
          <label>Reason (shown to the citizen)</label>
          <textarea class="form-input" name="reason" rows="3" placeholder="e.g. ID photo unreadable, details do not match"></textarea>
        </div>
      ` : ''}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Confirm ${decision === 'verified' ? 'Verification' : 'Rejection'}</button>
      </div>
    </form>
  `);

  document.getElementById('saCitizenForm').addEventListener('submit', async event => {
    event.preventDefault();
    const reason = new FormData(event.target).get('reason') || '';
    try {
      await saPost('verify_citizen', { citizen_id: citizenId, decision, reason });
      saCloseModal();
      saToast(`Citizen ID ${decision}.`);
      await saLoadPendingCitizens();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

/* ---- Add User / Contractor / Engineer (provisioning + dynamic docs) ---- */

function saOpenAddUserForm() {
  saOpenModal('Add User Account', `
    <form id="saAddUserForm">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-input" name="full_name" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select class="form-input" name="role" required>
            ${Object.entries(SA_ROLE_LABELS).map(([value, label]) => `<option value="${value}">${saEscape(label)}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input class="form-input" name="username" required minlength="3">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-input" type="email" name="email" required>
        </div>
      </div>
      ${saDocSectionHtml('Supporting documents (optional)')}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Create Account</button>
      </div>
    </form>
  `);

  saWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));

  document.getElementById('saAddUserForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    saClearFieldErrors(form);
    try {
      const result = await saPostForm('create_user', new FormData(form));
      saShowTempPasswordModal(form.full_name.value, result.temp_password);
      saToast('Account created.');
      await saLoadUsersTable();
    } catch (error) {
      saShowFieldErrors(form, error.fieldErrors);
      saToast(error.message || 'Unable to create user.', 'error');
    }
  });
}

function saOpenAddContractorForm() {
  saOpenModal('Add Contractor', `
    <form id="saAddContractorForm">
      <div class="form-grid">
        <div class="form-group">
          <label>Company / Contractor Name</label>
          <input class="form-input" name="name" required>
        </div>
        <div class="form-group">
          <label>Contact Person</label>
          <input class="form-input" name="contact_person">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-input" type="email" name="email">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input class="form-input" name="phone">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Address</label>
          <input class="form-input" name="address">
        </div>
      </div>
      <div class="form-group" style="margin-top:10px;">
        <label class="form-checkbox-label">
          <input type="checkbox" id="createLoginToggle" name="create_login" value="1">
          Also create a portal login for this contractor
        </label>
      </div>
      <div class="form-grid" id="loginFields" style="display:none;margin-top:8px;">
        <div class="form-group">
          <label>Username</label>
          <input class="form-input" name="username" minlength="3">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-input" type="password" name="password" minlength="8">
        </div>
      </div>
      ${saDocSectionHtml('Supporting documents (DTI, SEC, PhilGEPS, etc.)')}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Create Contractor</button>
      </div>
    </form>
  `);

  saWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));

  document.getElementById('createLoginToggle').addEventListener('change', event => {
    document.getElementById('loginFields').style.display = event.target.checked ? 'grid' : 'none';
  });

  document.getElementById('saAddContractorForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    saClearFieldErrors(form);
    try {
      await saPostForm('create_contractor', new FormData(form));
      saCloseModal();
      saToast('Contractor created.');
      await saLoadUsersTable();
    } catch (error) {
      saShowFieldErrors(form, error.fieldErrors);
      saToast(error.message || 'Unable to create contractor.', 'error');
    }
  });
}

function saOpenAddEngineerForm() {
  saOpenModal('Add Engineer', `
    <form id="saAddEngineerForm">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name</label>
          <input class="form-input" name="full_name" required>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input class="form-input" name="username" required minlength="3">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Email</label>
          <input class="form-input" type="email" name="email" required>
        </div>
      </div>
      ${saDocSectionHtml('Supporting documents (e.g. PRC license)')}
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Create Engineer</button>
      </div>
    </form>
  `);

  saWireDocRows(document.getElementById('docRows'), document.getElementById('docAddBtn'));

  document.getElementById('saAddEngineerForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.target;
    saClearFieldErrors(form);
    try {
      const result = await saPostForm('create_engineer', new FormData(form));
      saShowTempPasswordModal(form.full_name.value, result.temp_password);
      saToast('Engineer account created.');
      await saLoadUsersTable();
    } catch (error) {
      saShowFieldErrors(form, error.fieldErrors);
      saToast(error.message || 'Unable to create engineer.', 'error');
    }
  });
}

/* ---- Audit Trail ---------------------------------------------------------- */

async function saRenderAuditTrail() {
  await Promise.all([saLoadAuditList(), saLoadActivityList()]);
}

async function saLoadAuditList() {
  const container = document.getElementById('saAuditList');
  const pager = document.getElementById('saAuditPager');
  const state = saListState.audit;

  try {
    const result = await saGet('list_audit', { page: state.page, per_page: state.perPage, search: state.search });
    container.innerHTML = result.data.length
      ? result.data.map(item => saRow(
        saLabel(item.action),
        item.details || '',
        `<span class="sa-log-date">${saDateTime(item.created_at)}</span><br><small>${saEscape(item.actor_name || 'System')}</small>`
      )).join('')
      : '<p class="empty-state">No governance actions recorded yet. Actions taken in User & Role Governance will appear here.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.audit.page = nextPage; saLoadAuditList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load the audit trail.</p>';
  }
}

async function saLoadActivityList() {
  const container = document.getElementById('saActivityList');
  const pager = document.getElementById('saActivityPager');
  const state = saListState.activity;

  try {
    const result = await saGet('list_activity', { page: state.page, per_page: state.perPage, search: state.search });
    container.innerHTML = result.data.length
      ? result.data.map(item => saRow(
        saLabel(item.action),
        item.details || '',
        `<span class="sa-log-date">${saDateTime(item.created_at)}</span><br><small>${saEscape(item.actor_name || 'System')}</small>`
      )).join('')
      : '<p class="empty-state">No system activity recorded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.activity.page = nextPage; saLoadActivityList(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load system activity.</p>';
  }
}

/* ---- Login Security --------------------------------------------------- */

async function saRenderLoginSecurity() {
  const threshold = (saDashboardData.health || {}).lockout_threshold || { max_attempts: 5, window_minutes: 15 };
  const thresholdEl = document.getElementById('saLockoutThreshold');
  if (thresholdEl) thresholdEl.textContent = `${threshold.max_attempts} attempts / ${threshold.window_minutes} minutes`;

  await Promise.all([saLoadLoginLockouts(), saLoadLoginRisk(), saLoadLoginAttempts()]);
}

async function saLoadLoginLockouts() {
  const container = document.getElementById('saLoginLockouts');
  const pager = document.getElementById('saLoginLockoutsPager');
  const state = saListState.loginLockouts;

  try {
    const result = await saGet('list_login_lockouts', { page: state.page, per_page: state.perPage });
    saLoginLockoutsCache = result.data;

    container.innerHTML = result.data.length
      ? result.data.map((item, idx) => `
        <div class="sa-row">
          <div class="sa-row-main">
            <strong>${saEscape(item.identifier)}</strong>
            <span>${saEscape(item.ip_address)} - ${item.failed_count} failed attempts - last try ${saDateTime(item.last_attempt)}</span>
          </div>
          <div>
            ${saBadge('locked_out')}
            <button class="btn-secondary btn-compact" type="button" onclick="saUnlockLogin(${idx})">Unlock</button>
          </div>
        </div>
      `).join('')
      : '<p class="empty-state">Nobody is currently locked out.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.loginLockouts.page = nextPage; saLoadLoginLockouts(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load lockout status.</p>';
  }
}

function saUnlockLogin(index) {
  const item = saLoginLockoutsCache[index];
  if (!item) {
    saToast('Record not found.', 'error');
    return;
  }

  saOpenModal('Unlock Account', `
    <p>Clear recent failed login attempts for <strong>${saEscape(item.identifier)}</strong> (${saEscape(item.ip_address)})?</p>
    <p><small>They will be able to try signing in again immediately.</small></p>
    <div class="form-actions">
      <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
      <button class="btn-primary" type="button" id="saUnlockConfirmBtn">Unlock</button>
    </div>
  `);

  document.getElementById('saUnlockConfirmBtn').addEventListener('click', async () => {
    try {
      await saPost('unlock_login', { identifier: item.identifier, ip_address: item.ip_address });
      saCloseModal();
      saToast(`${item.identifier} unlocked.`);
      await saLoadLoginLockouts();
      await saLoadLoginRisk();
    } catch (error) {
      saToast(error.message, 'error');
    }
  });
}

async function saLoadLoginRisk() {
  const container = document.getElementById('saLoginRisk');
  const pager = document.getElementById('saLoginRiskPager');
  const state = saListState.loginRisk;
  const threshold = (saDashboardData.health || {}).lockout_threshold || { max_attempts: 5 };

  try {
    const result = await saGet('list_login_risk', { page: state.page, per_page: state.perPage });
    container.innerHTML = result.data.length
      ? result.data.map(item => saRow(
        item.identifier,
        `${item.ip_address} - last attempt ${saDateTime(item.last_attempt)}`,
        saBadge(Number(item.failed_count) >= threshold.max_attempts ? 'locked_out' : 'watch')
      )).join('')
      : '<p class="empty-state">No repeated login failures in the current lockout window.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.loginRisk.page = nextPage; saLoadLoginRisk(); },
    });
  } catch (error) {
    container.innerHTML = '<p class="empty-state">Unable to load login risk.</p>';
  }
}

async function saLoadLoginAttempts() {
  const table = document.getElementById('saLoginAttempts');
  const pager = document.getElementById('saLoginAttemptsPager');
  const state = saListState.logins;

  try {
    const result = await saGet('list_logins', {
      page: state.page, per_page: state.perPage, search: state.search, result: state.result,
    });

    table.innerHTML = result.data.length ? `
      <table class="data-table">
        <thead><tr><th>Identifier</th><th>IP Address</th><th>Result</th><th>Attempted At</th></tr></thead>
        <tbody>
          ${result.data.map(item => `
            <tr>
              <td>${saEscape(item.identifier)}</td>
              <td>${saEscape(item.ip_address)}</td>
              <td>${saBadge(Number(item.successful) ? 'success' : 'failed')}</td>
              <td>${saDateTime(item.attempted_at)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    ` : '<p class="empty-state">No login attempts recorded yet.</p>';

    renderPagination(pager, {
      page: result.page, lastPage: result.last_page, total: result.total, perPage: result.per_page,
      onPageChange: nextPage => { saListState.logins.page = nextPage; saLoadLoginAttempts(); },
    });
  } catch (error) {
    table.innerHTML = '<p class="empty-state">Unable to load login attempts.</p>';
  }
}

/* ---- System Health ------------------------------------------------------ */

function saFormatBytes(bytes) {
  if (bytes === null || bytes === undefined) return 'Unknown';
  const gb = bytes / (1024 * 1024 * 1024);
  return gb >= 1 ? `${gb.toFixed(1)} GB free` : `${(bytes / (1024 * 1024)).toFixed(0)} MB free`;
}

async function saRenderSystemHealth() {
  let health = saDashboardData.health || {};
  try {
    saDashboardData = await saGet('summary');
    health = saDashboardData.health || {};
  } catch (error) {
    saToast(error.message || 'Failed to load system health.', 'error');
  }

  const counts = health.counts || {};
  const grid = document.getElementById('saHealthGrid');

  const tiles = [
    { label: 'Database Connectivity', value: health.db_ok ? 'Online' : 'Down', tone: health.db_ok ? 'kpi-green' : 'kpi-red' },
    { label: 'Projects', value: counts.projects ?? 0, tone: 'kpi-blue' },
    { label: 'User Accounts', value: counts.users ?? 0, tone: 'kpi-blue' },
    { label: 'Contractors', value: counts.contractors ?? 0, tone: 'kpi-orange' },
    { label: 'Citizen Feedback', value: counts.feedback ?? 0, tone: 'kpi-green' },
    { label: 'Expense Records', value: counts.expenses ?? 0, tone: 'kpi-orange' },
    { label: 'Documents Awaiting Review', value: health.pending_documents ?? 0, tone: 'kpi-orange' },
    { label: 'PHP Version', value: health.php_version || 'Unknown', tone: 'kpi-blue' },
    { label: 'Uploads Disk Space', value: saFormatBytes(health.disk_free_bytes), tone: 'kpi-green' },
  ];

  grid.innerHTML = tiles.map(tile => `
    <article class="kpi-card">
      <div class="kpi-icon ${tile.tone}">
        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/></svg>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">${saEscape(tile.label)}</span>
        <strong class="kpi-value">${saEscape(String(tile.value))}</strong>
      </div>
    </article>
  `).join('');
}

/* ---- Settings (display/storage only — auth/session.php is untouched this
   phase, so these values do not yet change real timeout/lockout behavior) - */

async function saRenderSettings() {
  const form = document.getElementById('saSettingsForm');
  if (!form) return;

  try {
    const result = await saGet('get_settings');
    const settings = result.settings || {};
    form.site_name.value = settings.site_name || '';
    form.support_email.value = settings.support_email || '';
    form.session_timeout_minutes.value = settings.session_timeout_minutes ?? 30;
    form.login_max_attempts.value = settings.login_max_attempts ?? 5;
    form.login_lockout_minutes.value = settings.login_lockout_minutes ?? 15;
    form.maintenance_mode.checked = Boolean(settings.maintenance_mode);
    form.require_staff_2fa.checked = Boolean(settings.require_staff_2fa);
  } catch (error) {
    saToast(error.message || 'Failed to load settings.', 'error');
  }
}

function saWireSettingsForm() {
  const form = document.getElementById('saSettingsForm');
  if (!form) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();
    saClearFieldErrors(form);
    const data = new FormData(form);

    try {
      await saPost('update_settings', {
        site_name: data.get('site_name'),
        support_email: data.get('support_email'),
        session_timeout_minutes: data.get('session_timeout_minutes'),
        login_max_attempts: data.get('login_max_attempts'),
        login_lockout_minutes: data.get('login_lockout_minutes'),
        maintenance_mode: form.maintenance_mode.checked ? '1' : '0',
        require_staff_2fa: form.require_staff_2fa.checked ? '1' : '0',
      });
      saToast('Settings saved.');
    } catch (error) {
      saShowFieldErrors(form, error.fieldErrors);
      saToast(error.message || 'Unable to save settings.', 'error');
    }
  });
}

/* ---- Page shell ----------------------------------------------------------- */

const saRenderers = {
  dashboard: saRenderDashboard,
  'user-governance': saRenderUserGovernance,
  'audit-trail': saRenderAuditTrail,
  'login-security': saRenderLoginSecurity,
  'system-health': saRenderSystemHealth,
  settings: saRenderSettings,
};

function saShowPage(page) {
  saCurrentPage = page;

  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.page === page);
  });

  document.querySelectorAll('.page-section').forEach(section => {
    section.style.display = section.id === `page-${page}` ? 'block' : 'none';
  });

  saRenderers[page]?.();
}

window.GLOBAL_SEARCH_NAVIGATE = saShowPage;
window.GLOBAL_SEARCH_SOURCES = [
  {
    label: 'Users',
    url: SA_API,
    extraParams: { action: 'list_users' },
    mapItem: (row) => ({
      title: row.full_name || row.username,
      meta: `${row.role || ''} · ${row.email || ''}`.replace(/^ · /, ''),
      page: 'user-governance',
    }),
  },
];

function saWireShell() {
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      saShowPage(item.dataset.page || 'dashboard');
    });
  });

  const sidebarToggle = document.getElementById('sidebarToggle');
  sidebarToggle?.addEventListener('click', () => {
    if (window.matchMedia('(min-width: 769px)').matches) {
      document.body.classList.toggle('sidebar-collapsed');
      return;
    }
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

  document.getElementById('modalClose')?.addEventListener('click', saCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') saCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') saCloseModal();
  });

  // Topbar search now proxies into whichever single page-specific (server-backed)
  // search box is visible, since list search moved server-side with pagination.
  // No-ops when the current page has none or more than one search box (ambiguous,
  // e.g. Audit Trail has two independent search fields).
  document.getElementById('searchInput')?.addEventListener('input', event => {
    const pageEl = document.getElementById(`page-${saCurrentPage}`);
    const candidates = pageEl ? pageEl.querySelectorAll('input[type="text"][id$="Search"]') : [];
    if (candidates.length === 1) {
      candidates[0].value = event.target.value;
      candidates[0].dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  document.getElementById('saAddUserBtn')?.addEventListener('click', saOpenAddUserForm);
  document.getElementById('saAddContractorBtn')?.addEventListener('click', saOpenAddContractorForm);
  document.getElementById('saAddEngineerBtn')?.addEventListener('click', saOpenAddEngineerForm);

  const debouncedUsers = debounce(() => { saListState.users.page = 1; saLoadUsersTable(); }, 350);
  document.getElementById('saUserSearch')?.addEventListener('input', event => {
    saListState.users.search = event.target.value.trim();
    debouncedUsers();
  });
  document.getElementById('saRoleFilter')?.addEventListener('change', event => {
    saListState.users.role = event.target.value;
    saListState.users.page = 1;
    saLoadUsersTable();
  });

  const debouncedAudit = debounce(() => { saListState.audit.page = 1; saLoadAuditList(); }, 350);
  document.getElementById('saAuditSearch')?.addEventListener('input', event => {
    saListState.audit.search = event.target.value.trim();
    debouncedAudit();
  });

  const debouncedActivity = debounce(() => { saListState.activity.page = 1; saLoadActivityList(); }, 350);
  document.getElementById('saActivitySearch')?.addEventListener('input', event => {
    saListState.activity.search = event.target.value.trim();
    debouncedActivity();
  });

  const debouncedLogins = debounce(() => { saListState.logins.page = 1; saLoadLoginAttempts(); }, 350);
  document.getElementById('saLoginSearch')?.addEventListener('input', event => {
    saListState.logins.search = event.target.value.trim();
    debouncedLogins();
  });
  document.getElementById('saLoginResultFilter')?.addEventListener('change', event => {
    saListState.logins.result = event.target.value;
    saListState.logins.page = 1;
    saLoadLoginAttempts();
  });

  saWireSettingsForm();
}

/* ---- Profile / password (unrelated to this phase, unchanged) ------------ */

async function showProfileSettings() {
  try {
    const response = await fetch(SA_USER_API);
    const result = await response.json();
    const user = result.data || {};
    saOpenModal('Profile Settings', `
      <form id="saProfileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input class="form-input" name="full_name" required value="${saEscape(user.full_name)}">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-input" type="email" name="email" required value="${saEscape(user.email)}">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-input" disabled value="${saEscape(user.username)}">
          </div>
          <div class="form-group">
            <label>Role</label>
            <input class="form-input" disabled value="Super Admin">
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
          <button class="btn-primary" type="submit">Update Profile</button>
        </div>
      </form>
    `);
    document.getElementById('saProfileForm').addEventListener('submit', submitProfileForm);
  } catch {
    saToast('Failed to load profile.', 'error');
  }
}

function showChangePassword() {
  saOpenModal('Change Password', `
    <form id="saPasswordForm">
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
        <button class="btn-secondary" type="button" onclick="saCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Change Password</button>
      </div>
    </form>
  `);
  document.getElementById('saPasswordForm').addEventListener('submit', submitPasswordForm);
}

async function submitProfileForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const body = new URLSearchParams({
    full_name: form.get('full_name'),
    email: form.get('email'),
  });

  try {
    const response = await fetch(SA_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...SA_CSRF_HEADERS },
      body,
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    document.querySelector('.user-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-name').textContent = form.get('full_name');
    document.querySelector('.user-menu-email').textContent = form.get('email');
    saCloseModal();
    saToast('Profile updated.');
  } catch (error) {
    saToast(error.message, 'error');
  }
}

async function submitPasswordForm(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  if (form.get('new_password') !== form.get('confirm_password')) {
    saToast('New passwords do not match.', 'error');
    return;
  }

  try {
    const response = await fetch(SA_USER_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...SA_CSRF_HEADERS },
      body: new URLSearchParams({
        current_password: form.get('current_password'),
        new_password: form.get('new_password'),
      }),
    });
    const result = await response.json();
    if (result.error) throw new Error(result.error);
    saCloseModal();
    saToast('Password changed.');
  } catch (error) {
    saToast(error.message, 'error');
  }
}

window.saShowPage = saShowPage;
window.saCloseModal = saCloseModal;
window.saRefresh = saRefresh;
window.saOpenStatusConfirm = saOpenStatusConfirm;
window.saOpenRoleForm = saOpenRoleForm;
window.saOpenCitizenDecision = saOpenCitizenDecision;
window.saOpenDocumentReview = saOpenDocumentReview;
window.saUnlockLogin = saUnlockLogin;
window.saToast = saToast;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  saWireShell();
  await saRenderDashboard();
});
