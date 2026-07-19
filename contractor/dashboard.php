<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['contractor']);
$extraStylesheets = ['assets/css/pagination.css', 'contractor/assets/css/contractor.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="main-wrapper contractor-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content contractor-content">
    <section id="page-dashboard" class="page-section">
      <div class="page-header">
        <div>
          <h1 class="page-title">Contractor Dashboard</h1>
          <p class="contractor-scope-note">Assigned project access only.</p>
        </div>
        <button class="btn-primary" type="button" id="contractorSubmitReportBtn" onclick="contractorGoToReport()">Submit Report</button>
      </div>

      <section class="kpi-grid">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Assigned Projects</span>
            <strong class="kpi-value" id="contractorAssignedCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Average Progress</span>
            <strong class="kpi-value" id="contractorAverageProgress">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6zm1 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 100 2h4a1 1 0 100-2H8z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Reports Submitted</span>
            <strong class="kpi-value" id="contractorReportsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Pending Payment</span>
            <strong class="kpi-value contractor-money" id="contractorPendingPayment">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Performance Score</span>
            <strong class="kpi-value" id="contractorPerformanceScore">0</strong>
          </div>
        </article>
      </section>

      <section class="charts-row reveal">
        <article class="chart-card chart-main">
          <div class="chart-header">
            <h2 class="chart-title">Reported Progress Over Time</h2>
          </div>
          <div class="chart-body">
            <canvas id="contractorProgressChart"></canvas>
            <p class="empty-state" id="contractorProgressChartEmpty" style="display:none;">Progress will chart here once accomplishment reports are submitted.</p>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Project Status Mix</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="contractorStatusChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="contractorStatusChartTotal">0</span>
              </div>
            </div>
            <div class="budget-legend" id="contractorStatusChartLegend"></div>
          </div>
        </article>
      </section>

      <!-- Populated entirely by contractorRenderStageWidgets() — the panel set
           shown here depends on whether summary.has_awarded_projects is true,
           so no single fixed markup fits both states. -->
      <section class="contractor-dashboard-grid reveal" id="contractorStageWidgets"></section>
    </section>

    <section id="page-company-profile" class="page-section" style="display:none;"></section>
    <section id="page-accreditation-status" class="page-section" style="display:none;"></section>
    <section id="page-accreditation-documents" class="page-section" style="display:none;"></section>
    <section id="page-open-biddings" class="page-section" style="display:none;"></section>
    <section id="page-my-bids" class="page-section" style="display:none;"></section>
    <section id="page-bid-results" class="page-section" style="display:none;"></section>
    <section id="page-assigned-projects" class="page-section" style="display:none;"></section>
    <section id="page-project-timeline" class="page-section" style="display:none;"></section>
    <section id="page-contract-details" class="page-section" style="display:none;"></section>
    <section id="page-progress-updates" class="page-section" style="display:none;"></section>
    <section id="page-accomplishment-report" class="page-section" style="display:none;"></section>
    <section id="page-site-photos" class="page-section" style="display:none;"></section>
    <section id="page-supporting-documents" class="page-section" style="display:none;"></section>
    <section id="page-payment-requests" class="page-section" style="display:none;"></section>
    <section id="page-payment-status" class="page-section" style="display:none;"></section>
    <section id="page-payment-history" class="page-section" style="display:none;"></section>
    <section id="page-performance-rating" class="page-section" style="display:none;"></section>
    <section id="page-compliance-records" class="page-section" style="display:none;"></section>
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

<?php $notifPanelTitle = 'Contractor Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script>window.SIDEBAR_BADGES_PORTAL = 'contractor';</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/pagination.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-toggle.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/contractor/assets/js/contractor.js')) ?>"></script>
</body>
</html>
