/* BAC portal frontend - live procurement workflow */
const BAC_API = window.BASE_PATH + 'bac/api/portal.php';
const BAC_USER_API = window.BASE_PATH + 'api/user.php';
const BAC_CSRF_HEADERS = window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {};

let bacCurrentPage = 'dashboard';
let bacData = {
  approved_projects: [],
  announcements: [],
  evaluations: [],
  contractors: [],
  bids: [],
  recommendations: [],
  logs: [],
  stats: {},
};

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

function bacRow(title, meta, side, search = '') {
  return `
    <div class="bac-row" data-bac-search="${bacEscape(`${title} ${meta} ${side} ${search}`)}">
      <div class="bac-row-main">
        <strong>${bacEscape(title)}</strong>
        <span>${bacEscape(meta)}</span>
      </div>
      <div>${side}</div>
    </div>
  `;
}

async function bacRequest(action = 'summary', options = {}) {
  const response = await fetch(`${BAC_API}?action=${encodeURIComponent(action)}`, options);
  const result = await response.json();
  if (!response.ok || result.error) {
    throw new Error(result.error || `HTTP ${response.status}`);
  }
  return result;
}

async function bacPost(action, body) {
  return bacRequest(action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...BAC_CSRF_HEADERS },
    body: JSON.stringify(body),
  });
}

async function bacLoadData() {
  bacData = await bacRequest('summary');
  return bacData;
}

async function bacRefresh(page = bacCurrentPage) {
  try {
    await bacLoadData();
    bacRenderers[page]?.();
  } catch (error) {
    bacToast(error.message || 'Failed to load BAC data.', 'error');
  }
}

function bacSetKpis() {
  const stats = bacData.stats || {};
  document.getElementById('bacOpenBids').textContent = stats.open_bids || 0;
  document.getElementById('bacEvaluationCount').textContent = stats.for_evaluation || 0;
  document.getElementById('bacRecommendationCount').textContent = stats.recommendations || 0;
  document.getElementById('bacLogCount').textContent = stats.logs || 0;
  const badge = document.querySelector('.notif-badge');
  if (badge) badge.textContent = String((stats.recommendations || 0) + (stats.approved_waiting || 0));
}

function bacRenderDashboard() {
  bacSetKpis();

  document.getElementById('bacAnnouncementPreview').innerHTML = bacData.announcements.length
    ? bacData.announcements.slice(0, 3).map(item =>
      bacRow(
        item.project_name,
        `${item.reference_no} - Deadline ${bacDate(item.deadline)}`,
        bacBadge(item.status),
        item.project_code
      )
    ).join('')
    : '<p class="empty-state">No bidding announcements posted yet.</p>';

  document.getElementById('bacRecommendationPreview').innerHTML = bacData.recommendations.length
    ? bacData.recommendations.slice(0, 3).map(item =>
      bacRow(
        item.awardee,
        item.project,
        `<strong class="bac-money">${bacMoney(item.amount)}</strong>`,
        item.basis
      )
    ).join('')
    : '<p class="empty-state">No award recommendations sent yet.</p>';

  document.getElementById('bacEvaluationPreview').innerHTML = bacData.evaluations.length
    ? bacData.evaluations.slice(0, 3).map(item =>
      bacRow(
        item.contractor,
        `Compliance: ${bacLabel(item.compliance)}`,
        `<strong class="bac-score">${item.performance}</strong>`,
        item.risk
      )
    ).join('')
    : '<p class="empty-state">No active contractors found.</p>';

  document.getElementById('bacBidPreview').innerHTML = bacData.bids.length
    ? bacData.bids.slice(0, 3).map(item =>
      bacRow(
        item.contractor,
        item.project,
        `<strong class="bac-money">${bacMoney(item.bid)}</strong>`,
        item.status
      )
    ).join('')
    : '<p class="empty-state">No submitted bids yet.</p>';

  document.getElementById('bacLogPreview').innerHTML = bacData.logs.length
    ? bacData.logs.slice(0, 3).map(item =>
      bacRow(
        item.title,
        item.detail || item.project || 'Procurement activity',
        `<span class="bac-log-date">${bacDate(item.date)}</span>`,
        item.status
      )
    ).join('')
    : '<p class="empty-state">No procurement logs yet.</p>';
}

function bacRenderAnnouncements() {
  const container = document.getElementById('page-bidding-announcements');
  const approved = bacData.approved_projects || [];
  const announcements = bacData.announcements || [];

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
        <div class="bac-list">
          ${approved.length ? approved.map(project => `
            <div class="bac-row" data-bac-search="${bacEscape(`${project.project_code} ${project.name} ${project.location}`)}">
              <div class="bac-row-main">
                <strong>${bacEscape(project.name)}</strong>
                <span>${bacEscape(project.project_code)} - ${bacEscape(project.location || 'No location')}</span>
              </div>
              <button class="btn-primary btn-compact" type="button" onclick="bacOpenPublishForm(${project.id})">Post</button>
            </div>
          `).join('') : '<p class="empty-state">No approved projects waiting for BAC posting.</p>'}
        </div>
      </article>
    </section>

    <div class="table-card" style="margin-top:16px;">
      <table class="data-table">
        <thead>
          <tr><th>Reference</th><th>Project</th><th>Approved Budget</th><th>Published</th><th>Deadline</th><th>Status</th></tr>
        </thead>
        <tbody>
          ${announcements.length ? announcements.map(item => `
            <tr data-bac-search="${bacEscape(`${item.reference_no} ${item.project_name} ${item.status}`)}">
              <td><span class="proj-id">${bacEscape(item.reference_no)}</span></td>
              <td><strong>${bacEscape(item.project_name)}</strong><br><small>${bacEscape(item.project_code || '')}</small></td>
              <td>${bacMoney(item.budget)}</td>
              <td>${bacDate(item.published_at)}</td>
              <td>${bacDate(item.deadline)}</td>
              <td>${bacBadge(item.status)}</td>
            </tr>
          `).join('') : '<tr><td colspan="6"><p class="empty-state">No bidding announcements posted yet.</p></td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

function bacRenderEvaluation() {
  const container = document.getElementById('page-contractor-evaluation');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Contractor Evaluation Page</h2>
        <p class="bac-scope-note">Eligibility, document compliance, performance score, and risk review for active contractors.</p>
      </div>
    </div>
    <div class="bac-page-grid">
      ${bacData.evaluations.length ? bacData.evaluations.map(item => `
        <article class="bac-panel" data-bac-search="${bacEscape(`${item.contractor} ${item.eligibility} ${item.compliance} ${item.risk}`)}">
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
      `).join('') : '<p class="empty-state">No contractors available for evaluation.</p>'}
    </div>
  `;
}

function bacRenderBidComparison() {
  const container = document.getElementById('page-bid-comparison');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Bid Comparison Table</h2>
        <p class="bac-scope-note">Record submitted amounts, variance from approved budget, technical score, and delivery commitment.</p>
      </div>
      <button class="btn-primary" type="button" onclick="bacOpenBidForm()">Record Bid</button>
    </div>
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr><th>Project</th><th>Contractor</th><th>Bid Amount</th><th>Variance</th><th>Technical</th><th>Delivery</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          ${bacData.bids.length ? bacData.bids.map(item => `
            <tr data-bac-search="${bacEscape(`${item.project} ${item.contractor} ${item.status}`)}">
              <td><strong>${bacEscape(item.project)}</strong><br><small>${bacEscape(item.project_code || '')}</small></td>
              <td>${bacEscape(item.contractor)}</td>
              <td>${bacMoney(item.bid)}</td>
              <td>${item.variance > 0 ? '+' : ''}${item.variance}%</td>
              <td><strong class="bac-score">${item.technical}</strong></td>
              <td>${item.deliveryDays || '-'} days</td>
              <td>${bacBadge(item.status)}</td>
              <td>
                <button class="btn-primary btn-compact" type="button" onclick="bacOpenRecommendationForm(${item.id})" ${item.status === 'recommended' ? 'disabled' : ''}>Recommend</button>
              </td>
            </tr>
          `).join('') : '<tr><td colspan="8"><p class="empty-state">No bid submissions recorded yet.</p></td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

function bacRenderRecommendation() {
  const container = document.getElementById('page-award-recommendation');
  const recommendedBidIds = new Set((bacData.recommendations || []).map(item => Number(item.bid_submission_id || 0)));
  const candidateBids = (bacData.bids || []).filter(item => item.status !== 'recommended' && !recommendedBidIds.has(item.id));

  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Award Recommendation</h2>
        <p class="bac-scope-note">Recommended awardees are sent back to LGU Admin for official contractor assignment.</p>
      </div>
      <div class="bac-action-strip">
        <button class="btn-secondary" type="button" onclick="bacOpenResolutionPacket()">Resolution Packet</button>
        <button class="btn-primary" type="button" onclick="bacOpenBidForm()">Record Bid</button>
      </div>
    </div>
    <div class="bac-recommendation">
      <article class="bac-panel bac-panel-wide">
        <div class="bac-panel-head"><h2>Committee Recommendations</h2></div>
        <div class="bac-stack">
          ${bacData.recommendations.length ? bacData.recommendations.map(item => `
            <div class="bac-row" data-bac-search="${bacEscape(`${item.project} ${item.awardee} ${item.basis}`)}">
              <div class="bac-row-main">
                <strong>${bacEscape(item.project)}</strong>
                <span>${bacEscape(item.contract_no || 'Contract pending')} - ${bacEscape(item.basis || 'Award recommendation sent to admin.')}</span>
              </div>
              <div>
                <strong class="bac-money">${bacMoney(item.amount)}</strong><br>
                ${bacBadge(item.status)}
              </div>
            </div>
          `).join('') : '<p class="empty-state">No award recommendations sent yet.</p>'}
        </div>
      </article>
      <article class="bac-panel">
        <div class="bac-panel-head"><h2>Recommendation Queue</h2></div>
        <div class="bac-decision-list">
          ${candidateBids.length ? candidateBids.slice(0, 5).map(item => `
            <div class="bac-decision-item">
              <span>${bacEscape(item.contractor)}<br><small>${bacEscape(item.project)}</small></span>
              <button class="btn-primary btn-compact" type="button" onclick="bacOpenRecommendationForm(${item.id})">Select</button>
            </div>
          `).join('') : '<p class="empty-state">Record bids to build the recommendation queue.</p>'}
        </div>
      </article>
    </div>
  `;
}

function bacRenderLogs() {
  const container = document.getElementById('page-procurement-logs');
  container.innerHTML = `
    <div class="page-header">
      <div>
        <h2 class="page-title">Procurement Logs</h2>
        <p class="bac-scope-note">Chronological audit trail for BAC procurement activities and committee actions.</p>
      </div>
      <button class="btn-secondary" type="button" onclick="bacRefresh('procurement-logs')">Refresh</button>
    </div>
    <div class="bac-timeline">
      ${bacData.logs.length ? bacData.logs.map(item => `
        <article class="bac-log-item" data-bac-search="${bacEscape(`${item.title} ${item.detail} ${item.project || ''}`)}">
          <span class="bac-log-date">${bacDate(item.date)}</span>
          <div>
            <strong>${bacEscape(item.title)}</strong>
            <p>${bacEscape(item.detail || item.project || 'Procurement activity')}</p>
            <div style="margin-top:8px;">${bacBadge(item.status)}</div>
          </div>
        </article>
      `).join('') : '<p class="empty-state">No procurement logs yet.</p>'}
    </div>
  `;
}

const bacRenderers = {
  dashboard: bacRenderDashboard,
  'bidding-announcements': bacRenderAnnouncements,
  'contractor-evaluation': bacRenderEvaluation,
  'bid-comparison': bacRenderBidComparison,
  'award-recommendation': bacRenderRecommendation,
  'procurement-logs': bacRenderLogs,
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

function bacOpenPublishForm(projectId) {
  const project = (bacData.approved_projects || []).find(item => Number(item.id) === Number(projectId));
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
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Post Notice</button>
      </div>
    </form>
  `);

  document.getElementById('bacPublishForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = new FormData(event.target);
    try {
      await bacPost('publish', {
        project_id: projectId,
        deadline: form.get('deadline'),
        notes: form.get('notes'),
      });
      bacCloseModal();
      bacToast('Bidding notice posted.');
      await bacRefresh('bidding-announcements');
    } catch (error) {
      bacToast(error.message, 'error');
    }
  });
}

function bacOpenBidForm() {
  const announcements = bacData.announcements || [];
  const contractors = bacData.contractors || [];

  bacOpenModal('Record Bid Submission', `
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
  `);

  document.getElementById('bacBidForm').addEventListener('submit', async event => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(event.target).entries());
    try {
      await bacPost('bid', body);
      bacCloseModal();
      bacToast('Bid submission recorded.');
      await bacRefresh('bid-comparison');
    } catch (error) {
      bacToast(error.message, 'error');
    }
  });
}

function bacOpenRecommendationForm(bidId) {
  const bid = (bacData.bids || []).find(item => Number(item.id) === Number(bidId));
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
      <div class="form-actions">
        <button class="btn-secondary" type="button" onclick="bacCloseModal()">Cancel</button>
        <button class="btn-primary" type="submit">Send to Admin</button>
      </div>
    </form>
  `);

  document.getElementById('bacRecommendationForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = new FormData(event.target);
    try {
      await bacPost('recommend', {
        bid_id: bidId,
        basis: form.get('basis'),
      });
      bacCloseModal();
      bacToast('Award recommendation sent to LGU Admin.');
      await bacRefresh('award-recommendation');
    } catch (error) {
      bacToast(error.message, 'error');
    }
  });
}

function bacOpenResolutionPacket() {
  bacOpenModal('Resolution Packet', `
    <div class="bac-decision-list">
      <div class="bac-decision-item"><span>Posted Announcements</span><strong>${bacData.announcements.length}</strong></div>
      <div class="bac-decision-item"><span>Bid Submissions</span><strong>${bacData.bids.length}</strong></div>
      <div class="bac-decision-item"><span>Award Recommendations</span><strong>${bacData.recommendations.length}</strong></div>
      <div class="bac-decision-item"><span>Admin Assignment Queue</span><strong>${bacData.recommendations.filter(item => item.status === 'sent_to_admin').length}</strong></div>
    </div>
  `);
}

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

  const notifBtn = document.getElementById('notifBtn');
  const notifPanel = document.getElementById('notifPanel');
  notifBtn?.addEventListener('click', event => {
    event.stopPropagation();
    notifPanel?.classList.toggle('open');
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
    if (notifPanel && !notifPanel.contains(event.target) && event.target !== notifBtn) {
      notifPanel.classList.remove('open');
    }
  });

  document.getElementById('notifClear')?.addEventListener('click', () => {
    document.querySelectorAll('.notif-item').forEach(item => item.remove());
    const badge = document.querySelector('.notif-badge');
    if (badge) badge.style.display = 'none';
  });

  document.getElementById('modalClose')?.addEventListener('click', bacCloseModal);
  document.getElementById('modalOverlay')?.addEventListener('click', event => {
    if (event.target.id === 'modalOverlay') bacCloseModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') bacCloseModal();
  });

  document.getElementById('searchInput')?.addEventListener('input', event => {
    const term = event.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-bac-search]').forEach(row => {
      row.style.display = row.dataset.bacSearch.toLowerCase().includes(term) ? '' : 'none';
    });
  });
}

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
window.bacOpenResolutionPacket = bacOpenResolutionPacket;
window.bacToast = bacToast;
window.showProfileSettings = showProfileSettings;
window.showChangePassword = showChangePassword;

document.addEventListener('DOMContentLoaded', async () => {
  bacWireShell();
  try {
    await bacLoadData();
  } catch (error) {
    bacToast(error.message || 'Failed to load BAC data.', 'error');
  }
  bacRenderDashboard();
});
