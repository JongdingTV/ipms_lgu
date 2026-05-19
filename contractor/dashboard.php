<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['contractor']);
$topbarSearchPlaceholder = 'Search assigned projects...';
$extraStylesheets = ['contractor/assets/css/contractor.css'];

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
        <button class="btn-primary" type="button" onclick="contractorGoToReport()">Submit Report</button>
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
      </section>

      <section class="contractor-dashboard-grid">
        <article class="contractor-panel contractor-panel-wide">
          <div class="contractor-panel-head">
            <h2>Assigned Projects</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('assigned-projects')">View All</button>
          </div>
          <div id="contractorProjectPreview" class="contractor-project-list">
            <p class="empty-state">Loading assigned projects...</p>
          </div>
        </article>

        <article class="contractor-panel">
          <div class="contractor-panel-head">
            <h2>Payment Status</h2>
            <button class="btn-secondary btn-compact" type="button" onclick="contractorShowPage('payment-status')">Review</button>
          </div>
          <div id="contractorPaymentPreview" class="contractor-mini-list">
            <p class="empty-state">Loading payment status...</p>
          </div>
        </article>
      </section>
    </section>

    <section id="page-assigned-projects" class="page-section" style="display:none;"></section>
    <section id="page-accomplishment-report" class="page-section" style="display:none;"></section>
    <section id="page-supporting-documents" class="page-section" style="display:none;"></section>
    <section id="page-contract-details" class="page-section" style="display:none;"></section>
    <section id="page-payment-status" class="page-section" style="display:none;"></section>
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
    <span>Contractor Updates</span>
    <button class="notif-clear" id="notifClear" type="button">Clear all</button>
  </div>
  <div class="notif-item notif-low">
    <div class="notif-dot"></div>
    <div><p class="notif-msg">Assigned project workspace is ready.</p><span class="notif-time">Contractor portal</span></div>
  </div>
</div>

<script src="<?= htmlspecialchars($BASE_PATH) ?>contractor/assets/js/contractor.js"></script>
</body>
</html>
