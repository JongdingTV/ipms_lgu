<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['bac']);
$topbarSearchPlaceholder = 'Search bids, contractors, procurement logs...';
$extraStylesheets = ['assets/css/pagination.css', 'bac/assets/css/bac.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div class="main-wrapper bac-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content bac-content">
    <section id="page-dashboard" class="page-section">
      <div class="bac-hero">
        <div class="bac-hero-copy">
          <span class="bac-eyebrow">Procurement and contractor evaluation</span>
          <h1 class="page-title">BAC Dashboard</h1>
          <p class="bac-scope-note">Manage bidding visibility, compare offers, evaluate contractor readiness, and prepare award recommendations.</p>
        </div>
        <img class="bac-hero-mark" src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon2.png')) ?>" alt="<?= htmlspecialchars(APP_NAME) ?>">
      </div>

      <section class="kpi-grid bac-kpis">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/><path d="M15 7h1a2 2 0 012 2v4a2 2 0 01-2 2h-1V7z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Open Bids</span>
            <strong class="kpi-value" id="bacOpenBids">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6zm1 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">For Evaluation</span>
            <strong class="kpi-value" id="bacEvaluationCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Recommendations</span>
            <strong class="kpi-value" id="bacRecommendationCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 00-2 2v1h16V5a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 8H2v7a2 2 0 002 2h12a2 2 0 002-2V8z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Active Logs</span>
            <strong class="kpi-value" id="bacLogCount">0</strong>
          </div>
        </article>
      </section>

      <section class="charts-row reveal">
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Procurement Pipeline</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="bacPipelineChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="bacPipelineChartTotal">0</span>
              </div>
            </div>
            <div class="budget-legend" id="bacPipelineChartLegend"></div>
          </div>
        </article>
      </section>

      <section class="bac-dashboard-grid reveal">
        <article class="bac-panel bac-panel-wide">
          <div class="bac-panel-head">
            <h2>Bidding Announcements</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="bacShowPage('bidding-announcements')">View All</button>
          </div>
          <div id="bacAnnouncementPreview" class="bac-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>

        <article class="bac-panel">
          <div class="bac-panel-head">
            <h2>Award Recommendation</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="bacShowPage('award-recommendation')">Review</button>
          </div>
          <div id="bacRecommendationPreview" class="bac-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>
      </section>

      <section class="lower-row reveal" style="transition-delay:.08s;">
        <article class="info-card">
          <h2 class="info-card-title">Contractor Evaluation</h2>
          <div id="bacEvaluationPreview" class="bac-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>

        <article class="info-card">
          <h2 class="info-card-title">Bid Comparison</h2>
          <div id="bacBidPreview" class="bac-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>

        <article class="info-card">
          <h2 class="info-card-title">Procurement Logs</h2>
          <div id="bacLogPreview" class="bac-list">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>
      </section>
    </section>

    <section id="page-bidding-announcements" class="page-section" style="display:none;"></section>
    <section id="page-contractor-evaluation" class="page-section" style="display:none;"></section>
    <section id="page-bid-comparison" class="page-section" style="display:none;"></section>
    <section id="page-award-recommendation" class="page-section" style="display:none;"></section>
    <section id="page-procurement-logs" class="page-section" style="display:none;"></section>
    <section id="page-procurement-documents" class="page-section" style="display:none;"></section>
    <section id="page-contractor-applications" class="page-section" style="display:none;"></section>
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

<?php $notifPanelTitle = 'BAC Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script>window.SIDEBAR_BADGES_PORTAL = 'bac';</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/pagination.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/bac/assets/js/bac.js')) ?>"></script>
</body>
</html>
