<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['super_admin']);
$extraStylesheets = ['assets/css/pagination.css', 'superadmin/assets/css/superadmin.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="main-wrapper superadmin-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content superadmin-content">
    <section id="page-dashboard" class="page-section">
      <div class="sa-hero">
        <div class="sa-hero-copy">
          <span class="sa-eyebrow">Platform governance</span>
          <h1 class="page-title">Super Admin Dashboard</h1>
          <p class="sa-scope-note">Oversee account access, review the audit trail, monitor login security, and confirm system health across the whole platform.</p>
        </div>
        <img class="sa-hero-mark" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
      </div>

      <section class="kpi-grid sa-kpis">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Total Users</span>
            <strong class="kpi-value" id="saTotalUsers">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Active / Inactive</span>
            <strong class="kpi-value" id="saActiveInactive">0 / 0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Pending Verifications</span>
            <strong class="kpi-value" id="saPendingCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Failed Logins (24h)</span>
            <strong class="kpi-value" id="saFailedLogins">0</strong>
          </div>
        </article>
      </section>

      <section class="charts-row reveal">
        <article class="chart-card chart-main">
          <div class="chart-header">
            <h2 class="chart-title">Login Activity — Last 7 Days</h2>
            <div class="chart-legend">
              <span><span class="legend-dot legend-green"></span>Successful</span>
              <span><span class="legend-dot" style="background:#ef4444;"></span>Failed</span>
            </div>
          </div>
          <div class="chart-body">
            <canvas id="saLoginTrendChart"></canvas>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Users by Role</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="saRoleChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="saRoleChartTotal">0</span>
              </div>
            </div>
            <div class="budget-legend" id="saRoleChartLegend"></div>
          </div>
        </article>
      </section>

      <section class="lower-row reveal">
        <article class="info-card" style="grid-column: span 2;">
          <h2 class="info-card-title">Recent System Activity</h2>
          <div id="saActivityPreview" class="sa-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>

        <article class="info-card">
          <h2 class="info-card-title">Users by Role</h2>
          <div id="saRoleBreakdown" class="sa-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>
      </section>
    </section>

    <section id="page-user-governance" class="page-section" style="display:none;">
      <div class="sa-section-head">
        <h1 class="page-title">User & Role Governance</h1>
        <p class="sa-scope-note">Activate, deactivate, or change the role of any account. You cannot change your own account here.</p>
      </div>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>All User Accounts</h2>
          <div class="sa-filters">
            <input type="text" id="saUserSearch" placeholder="Search name, username, email...">
            <select id="saRoleFilter">
              <option value="">All roles</option>
              <option value="super_admin">Super Admin</option>
              <option value="admin">Admin</option>
              <option value="bac">BAC</option>
              <option value="engineer">Engineer</option>
              <option value="contractor">Contractor</option>
              <option value="citizen">Citizen</option>
            </select>
          </div>
        </div>
        <div class="sa-provision-actions">
          <button class="btn-primary btn-compact" type="button" id="saAddUserBtn">+ Add User</button>
          <button class="btn-secondary btn-compact" type="button" id="saAddContractorBtn">+ Add Contractor</button>
          <button class="btn-secondary btn-compact" type="button" id="saAddEngineerBtn">+ Add Engineer</button>
        </div>
        <div id="saUsersTable" class="sa-table-wrap">
          <p class="empty-state">Loading users...</p>
        </div>
        <div class="pagination-wrap" id="saUsersPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>Pending Citizen Verifications</h2>
        </div>
        <div id="saPendingCitizens" class="sa-list">
          <p class="empty-state">Loading pending verifications...</p>
        </div>
        <div class="pagination-wrap" id="saPendingCitizensPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>Pending Document Reviews</h2>
        </div>
        <div id="saPendingDocuments" class="sa-list">
          <p class="empty-state">Loading documents...</p>
        </div>
        <div class="pagination-wrap" id="saPendingDocumentsPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>Pending Staff Account Requests</h2>
        </div>
        <p class="sa-scope-note">Engineer/BAC accounts requested by an admin — approving creates the login and emails the new hire; rejecting requires a reason.</p>
        <div id="saPendingStaffRequests" class="sa-list">
          <p class="empty-state">Loading staff requests...</p>
        </div>
        <div class="pagination-wrap" id="saPendingStaffRequestsPager"></div>
      </section>
    </section>

    <section id="page-audit-trail" class="page-section" style="display:none;">
      <div class="sa-section-head">
        <h1 class="page-title">Audit Trail</h1>
        <p class="sa-scope-note">Governance actions taken in this portal, alongside authentication and access activity across the whole system.</p>
      </div>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>Governance Actions</h2>
          <input type="text" id="saAuditSearch" placeholder="Filter by action or detail...">
        </div>
        <div id="saAuditList" class="sa-list">
          <p class="empty-state">Loading audit trail...</p>
        </div>
        <div class="pagination-wrap" id="saAuditPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>System Activity</h2>
          <input type="text" id="saActivitySearch" placeholder="Filter by action or detail...">
        </div>
        <div id="saActivityList" class="sa-list">
          <p class="empty-state">Loading system activity...</p>
        </div>
        <div class="pagination-wrap" id="saActivityPager"></div>
      </section>
    </section>

    <section id="page-login-security" class="page-section" style="display:none;">
      <div class="sa-section-head">
        <h1 class="page-title">Login Security</h1>
        <p class="sa-scope-note">Accounts and IPs are auto-locked after <span id="saLockoutThreshold">5 attempts / 15 minutes</span>. This view mirrors that same threshold.</p>
      </div>

      <section class="sa-panel">
        <div class="sa-panel-head"><h2>Currently Locked Out</h2></div>
        <div id="saLoginLockouts" class="sa-list">
          <p class="empty-state">Loading lockout status...</p>
        </div>
        <div class="pagination-wrap" id="saLoginLockoutsPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head"><h2>At-Risk Accounts (Repeated Failures)</h2></div>
        <div id="saLoginRisk" class="sa-list">
          <p class="empty-state">Loading login risk...</p>
        </div>
        <div class="pagination-wrap" id="saLoginRiskPager"></div>
      </section>

      <section class="sa-panel">
        <div class="sa-panel-head">
          <h2>Recent Login Attempts</h2>
          <div class="sa-filters">
            <input type="text" id="saLoginSearch" placeholder="Search identifier or IP...">
            <select id="saLoginResultFilter">
              <option value="">All results</option>
              <option value="success">Successful</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </div>
        <div id="saLoginAttempts" class="sa-table-wrap">
          <p class="empty-state">Loading login attempts...</p>
        </div>
        <div class="pagination-wrap" id="saLoginAttemptsPager"></div>
      </section>
    </section>

    <section id="page-system-health" class="page-section" style="display:none;">
      <div class="sa-section-head">
        <h1 class="page-title">System Health</h1>
        <p class="sa-scope-note">Database connectivity and record counts across core tables.</p>
      </div>

      <section class="kpi-grid" id="saHealthGrid">
        <p class="empty-state">Loading system health...</p>
      </section>
    </section>

    <section id="page-settings" class="page-section" style="display:none;">
      <div class="sa-section-head">
        <h1 class="page-title">Settings</h1>
        <p class="sa-scope-note">
          Site information and security-policy display values. These are stored for reference only this phase —
          session timeout and login lockout are still governed by the platform's configured defaults.
        </p>
      </div>

      <section class="sa-panel">
        <form id="saSettingsForm">
          <div class="form-grid">
            <div class="form-group">
              <label>Site Name</label>
              <input class="form-input" name="site_name" required maxlength="150">
            </div>
            <div class="form-group">
              <label>Support Email</label>
              <input class="form-input" type="email" name="support_email" required>
            </div>
            <div class="form-group">
              <label>Session Timeout (minutes)</label>
              <input class="form-input" type="number" name="session_timeout_minutes" min="5" max="1440" required>
            </div>
            <div class="form-group">
              <label>Login Max Attempts</label>
              <input class="form-input" type="number" name="login_max_attempts" min="3" max="20" required>
            </div>
            <div class="form-group">
              <label>Login Lockout Window (minutes)</label>
              <input class="form-input" type="number" name="login_lockout_minutes" min="1" max="1440" required>
            </div>
          </div>
          <div class="form-group" style="margin-top:14px;">
            <label class="form-checkbox-label">
              <span class="toggle-switch">
                <input type="checkbox" name="maintenance_mode">
                <span class="toggle-slider"></span>
              </span>
              Maintenance mode (stored only — not yet enforced)
            </label>
          </div>
          <div class="form-group" style="margin-top:10px;">
            <label class="form-checkbox-label">
              <span class="toggle-switch">
                <input type="checkbox" name="require_staff_2fa">
                <span class="toggle-slider"></span>
              </span>
              Require email OTP two-factor authentication for Super Admin, Admin, and BAC logins
            </label>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Settings</button>
          </div>
        </form>
      </section>
    </section>
  </main>
</div>

<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Details</h3>
      <button class="modal-close" id="modalClose" type="button">&times;</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<?php $notifPanelTitle = 'Super Admin Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script>
  window.CURRENT_USER_ID = <?= (int) ($user['user_id'] ?? 0) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script>window.SIDEBAR_BADGES_PORTAL = 'superadmin';</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/pagination.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-toggle.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/superadmin/assets/js/superadmin.js')) ?>"></script>
</body>
</html>
