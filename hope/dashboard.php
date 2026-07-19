<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['hope']);
$extraStylesheets = ['assets/css/pagination.css', 'hope/assets/css/hope.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="main-wrapper hope-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content hope-content">
    <section id="page-dashboard" class="page-section">
      <div class="hope-hero">
        <div class="hope-hero-copy">
          <span class="hope-eyebrow">Head of the Procuring Entity</span>
          <h1 class="page-title">HOPE Dashboard</h1>
          <p class="hope-scope-note">Review and decide on infrastructure projects registered by Admin, per RA 12009's assignment of project-approval authority to the Head of the Procuring Entity.</p>
        </div>
        <img class="hope-hero-mark" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
      </div>

      <section class="kpi-grid hope-kpis">
        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6zm1 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Pending Project Approvals</span>
            <strong class="kpi-value" id="hopePendingCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Pending Contract Award Approvals</span>
            <strong class="kpi-value" id="hopePendingAwardsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Pending Deletion Requests</span>
            <strong class="kpi-value" id="hopePendingDeletionsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Approved This Month</span>
            <strong class="kpi-value" id="hopeApprovedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V6.828a2 2 0 00-.586-1.414l-2.828-2.828A2 2 0 009.172 2H4zm3 8a1 1 0 011-1h.01a1 1 0 110 2H8a1 1 0 01-1-1zm1-3a1 1 0 100 2h.01a1 1 0 100-2H8z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Returned Projects</span>
            <strong class="kpi-value" id="hopeReturnedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Rejected Projects</span>
            <strong class="kpi-value" id="hopeRejectedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.51-1.31c-.562-.649-1.413-1.076-2.353-1.253V5z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Total Infrastructure Budget</span>
            <strong class="kpi-value hope-money" id="hopeTotalBudget">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Delayed Projects</span>
            <strong class="kpi-value" id="hopeDelayedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Projects Near Completion</span>
            <strong class="kpi-value" id="hopeNearCompletionCount">0</strong>
          </div>
        </article>
      </section>

      <section class="charts-row reveal">
        <article class="chart-card chart-main">
          <div class="chart-header">
            <h2 class="chart-title">Approval Decisions — Last 6 Months</h2>
            <div class="chart-legend">
              <span><span class="legend-dot legend-green"></span>Approved</span>
              <span><span class="legend-dot" style="background:#f97316;"></span>Returned</span>
              <span><span class="legend-dot" style="background:#ef4444;"></span>Rejected</span>
            </div>
          </div>
          <div class="chart-body">
            <canvas id="hopeDecisionChart"></canvas>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Portfolio by Stage</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="hopeStageChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="hopeStageChartTotal">0</span>
              </div>
            </div>
            <div class="budget-legend" id="hopeStageChartLegend"></div>
          </div>
        </article>
      </section>

      <section class="lower-row reveal">
        <article class="info-card" style="grid-column: span 2;">
          <div class="hope-panel-head">
            <h2 class="info-card-title">Projects Awaiting Approval</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="hopeShowPage('project-approvals')">Review All</button>
          </div>
          <div id="hopePendingPreview" class="hope-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>

        <article class="info-card" style="grid-column: span 1;">
          <div class="hope-panel-head">
            <h2 class="info-card-title">High-Risk Projects</h2>
            <span class="hope-scope-note">AI Summary — advisory only</span>
          </div>
          <div id="hopeHighRiskList" class="hope-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>
      </section>
    </section>

    <section id="page-project-approvals" class="page-section" style="display:none;"></section>
    <section id="page-award-approvals" class="page-section" style="display:none;"></section>
    <section id="page-returned-projects" class="page-section" style="display:none;"></section>
    <section id="page-deletion-requests" class="page-section" style="display:none;"></section>
    <section id="page-decision-history" class="page-section" style="display:none;"></section>
    <section id="page-approved-projects" class="page-section" style="display:none;"></section>
    <section id="page-ongoing-projects" class="page-section" style="display:none;"></section>
    <section id="page-completed-projects" class="page-section" style="display:none;"></section>
    <section id="page-executive-reports" class="page-section" style="display:none;"></section>
    <section id="page-budget-summary" class="page-section" style="display:none;"></section>
    <section id="page-procurement-summary" class="page-section" style="display:none;"></section>
    <section id="page-notifications" class="page-section" style="display:none;"></section>
    <section id="page-profile" class="page-section" style="display:none;"></section>
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

<?php $notifPanelTitle = 'HOPE Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script>window.SIDEBAR_BADGES_PORTAL = 'hope';</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/pagination.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-toggle.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/hope/assets/js/hope.js')) ?>"></script>
</body>
</html>
