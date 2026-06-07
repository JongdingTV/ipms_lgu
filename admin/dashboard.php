<?php
require_once '../auth/session.php';
$user = requireLogin(['admin']);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-wrapper">
  <?php require_once '../includes/topbar.php'; ?>

  <main class="content">
    <section class="kpi-grid">
      <article class="kpi-card">
        <div class="kpi-icon kpi-blue">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
          </svg>
        </div>
        <div class="kpi-info">
          <span class="kpi-label">Active Projects</span>
          <strong class="kpi-value" id="kpi-active">0</strong>
        </div>
      </article>

      <article class="kpi-card">
        <div class="kpi-icon kpi-orange">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.596c.75 1.334-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.742-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-6a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="kpi-info">
          <span class="kpi-label">Delayed Projects</span>
          <strong class="kpi-value" id="kpi-delayed">0</strong>
        </div>
      </article>

      <article class="kpi-card">
        <div class="kpi-icon kpi-green">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="kpi-info">
          <span class="kpi-label">Budget Utilized</span>
          <div class="kpi-budget">
            <strong class="kpi-value kpi-peso" id="kpi-budget">0</strong>
            <span class="kpi-budget-total" id="kpi-budget-total">/ P0.0M</span>
          </div>
        </div>
      </article>

      <article class="kpi-card">
        <div class="kpi-icon kpi-red">
          <svg viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="kpi-info">
          <span class="kpi-label">High-Risk Alerts</span>
          <strong class="kpi-value" id="kpi-alerts">0</strong>
        </div>
      </article>
    </section>

    <section class="charts-row">
      <article class="chart-card chart-main">
        <div class="chart-header">
          <h2 class="chart-title">Project Progress Overview</h2>
          <div class="chart-legend">
            <span><span class="legend-dot legend-blue"></span>Planned</span>
            <span><span class="legend-dot legend-green"></span>Actual</span>
          </div>
        </div>
        <div class="chart-body">
          <canvas id="progressChart"></canvas>
        </div>
      </article>

      <article class="chart-card">
        <div class="chart-header">
          <h2 class="chart-title">Budget Status</h2>
        </div>
        <div class="chart-body budget-body">
          <div class="donut-wrapper">
            <canvas id="budgetChart"></canvas>
            <div class="donut-center">
              <span class="donut-pct">0%</span>
            </div>
          </div>
          <div class="budget-legend">
            <div class="budget-legend-item">
              <span class="legend-dot legend-blue"></span>
              <span>Spent <strong>0%</strong></span>
            </div>
            <div class="budget-legend-item">
              <span class="legend-dot legend-green"></span>
              <span>Remaining <strong>100%</strong></span>
            </div>
            <div class="budget-legend-item">
              <span class="legend-dot" style="background:#ef4444;"></span>
              <span>Anomaly Flagged Expenses</span>
            </div>
          </div>
        </div>
      </article>
    </section>

    <section class="lower-row">
      <article class="info-card">
        <h2 class="info-card-title">Top Delayed Projects</h2>
        <div class="delayed-list">
          <p class="empty-state">Loading delayed projects...</p>
        </div>
      </article>

      <article class="info-card">
        <h2 class="info-card-title">Budget Anomalies</h2>
        <div class="anomaly-list">
          <p class="empty-state">Scanning budget activity...</p>
        </div>
        <div class="anomaly-footer">
          <button class="btn-review" type="button" onclick="navigate('budget-monitoring')">Review Budget</button>
        </div>
      </article>

      <article class="info-card">
        <h2 class="info-card-title">Recent Citizen Feedback</h2>
        <div class="feedback-list">
          <p class="empty-state">Loading feedback...</p>
        </div>
      </article>
    </section>

    <section class="ai-insights">
      <div class="ai-grid">
        <article class="ai-card ai-red">
          <div class="ai-card-title">Delay Risk: <strong>Loading</strong></div>
          <p class="ai-card-body">Overall schedule pressure based on delayed project counts.</p>
        </article>
        <article class="ai-card ai-orange">
          <div class="ai-card-title"><strong>Budget:</strong> Loading</div>
          <p class="ai-card-body">Tracks flagged expenses and potential overspending signals.</p>
        </article>
        <article class="ai-card ai-green">
          <div class="ai-card-title"><strong>Top Contractor:</strong> Loading</div>
          <p class="ai-card-body">Highlights the current best-performing active contractor.</p>
        </article>
      </div>
    </section>

    <section class="lower-row" style="margin-top:18px;">
      <article class="info-card">
        <h2 class="info-card-title">Workflow Connections</h2>
        <div id="workflowConnectionList" class="anomaly-list">
          <p class="empty-state">Loading role connections...</p>
        </div>
      </article>

      <article class="info-card" style="grid-column: span 2;">
        <h2 class="info-card-title">Recent Contract, Inspection, and Payment Records</h2>
        <div id="workflowActivityList" class="feedback-list">
          <p class="empty-state">Loading workflow activity...</p>
        </div>
      </article>
    </section>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
