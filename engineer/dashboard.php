<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['engineer']);
$topbarSearchPlaceholder = 'Search assigned projects...';
$extraStylesheets = ['engineer/assets/css/engineer.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="main-wrapper engineer-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content engineer-content">
    <section id="page-dashboard" class="page-section">
      <div class="page-header">
        <div>
          <h1 class="page-title">Engineer Dashboard</h1>
          <p class="engineer-scope-note">Field monitoring workspace for assigned infrastructure projects.</p>
        </div>
        <button class="btn-primary" type="button" onclick="engineerShowPage('milestone-update')">Update Milestone</button>
      </div>

      <section class="kpi-grid">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Assigned Projects</span>
            <strong class="kpi-value" id="engineerAssignedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Average Progress</span>
            <strong class="kpi-value" id="engineerAverageProgress">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.596c.75 1.334-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.742-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-6a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Delayed Projects</span>
            <strong class="kpi-value" id="engineerDelayedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Open Issues</span>
            <strong class="kpi-value" id="engineerOpenIssues">0</strong>
          </div>
        </article>
      </section>

      <section class="engineer-dashboard-grid">
        <article class="engineer-panel engineer-panel-wide">
          <div class="engineer-panel-head">
            <h2>My Assigned Projects</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="engineerShowPage('assigned-projects')">View All</button>
          </div>
          <div id="engineerProjectPreview" class="engineer-project-list">
            <p class="empty-state">Loading assigned projects...</p>
          </div>
        </article>

        <article class="engineer-panel">
          <div class="engineer-panel-head">
            <h2>Budget Snapshot</h2>
            <span class="engineer-readonly-pill">Read-only</span>
          </div>
          <div id="engineerBudgetPreview" class="engineer-mini-list">
            <p class="empty-state">Loading budget data...</p>
          </div>
        </article>
      </section>

      <section class="lower-row">
        <article class="info-card">
          <h2 class="info-card-title">Upcoming Milestones</h2>
          <div id="engineerMilestonePreview" class="engineer-mini-list">
            <p class="empty-state">Loading milestones...</p>
          </div>
        </article>

        <article class="info-card">
          <h2 class="info-card-title">Recent Issues</h2>
          <div id="engineerIssuePreview" class="engineer-mini-list">
            <p class="empty-state">Loading issues...</p>
          </div>
        </article>

        <article class="info-card">
          <h2 class="info-card-title">Photo Submissions</h2>
          <div id="engineerPhotoPreview" class="engineer-mini-list">
            <p class="empty-state">Loading progress photos...</p>
          </div>
        </article>
      </section>
    </section>

    <section id="page-assigned-projects" class="page-section" style="display:none;"></section>
    <section id="page-milestone-update" class="page-section" style="display:none;"></section>
    <section id="page-inspection-review" class="page-section" style="display:none;"></section>
    <section id="page-payment-review" class="page-section" style="display:none;"></section>
    <section id="page-progress-photos" class="page-section" style="display:none;"></section>
    <section id="page-delay-report" class="page-section" style="display:none;"></section>
    <section id="page-issue-reporting" class="page-section" style="display:none;"></section>
    <section id="page-status-tracker" class="page-section" style="display:none;"></section>
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

<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-header">
    <span>Engineer Updates</span>
    <button class="notif-clear" id="notifClear" type="button">Clear all</button>
  </div>
  <div class="notif-item notif-low">
    <div class="notif-dot"></div>
    <div><p class="notif-msg">Field monitoring workspace is ready.</p><span class="notif-time">Engineer portal</span></div>
  </div>
</div>

<script src="<?= htmlspecialchars($BASE_PATH) ?>engineer/assets/js/engineer.js"></script>
</body>
</html>
