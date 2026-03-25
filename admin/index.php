<?php include '../includes/header.php'; ?>

  <?php include '../includes/sidebar.php'; ?>

  <!-- MAIN -->
  <div class="main-wrapper">

    <?php include '../includes/topbar.php'; ?>

    <!-- CONTENT (JS will wrap this into page sections) -->
    <main class="content">

      <!-- KPI CARDS -->
      <section class="kpi-grid" id="kpiGrid">
        <div class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Active Projects</span>
            <span class="kpi-value" id="kpi-active">—</span>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-red">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Delayed Projects</span>
            <span class="kpi-value" id="kpi-delayed">—</span>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Budget Utilized</span>
            <div class="kpi-budget">
              <span class="kpi-value kpi-peso" id="kpi-budget">—</span>
              <span class="kpi-budget-total" id="kpi-budget-total">/ ₱—M</span>
            </div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">High-Risk Alerts (AI)</span>
            <span class="kpi-value" id="kpi-alerts">—</span>
          </div>
        </div>
      </section>

      <!-- CHARTS ROW -->
      <section class="charts-row">
        <div class="chart-card chart-main">
          <div class="chart-header">
            <h2 class="chart-title">Project Progress Overview</h2>
            <div class="chart-legend">
              <span class="legend-dot legend-blue"></span><span>Planned</span>
              <span class="legend-dot legend-green"></span><span>Actual</span>
            </div>
          </div>
          <div class="chart-body"><canvas id="progressChart"></canvas></div>
        </div>
        <div class="chart-card chart-budget">
          <div class="chart-header"><h2 class="chart-title">Budget Status</h2></div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="budgetChart"></canvas>
              <div class="donut-center"><span class="donut-pct">—</span></div>
            </div>
            <div class="budget-legend">
              <div class="budget-legend-item"><span class="legend-dot" style="background:#3b82f6"></span><span>Spent <strong>—</strong></span></div>
              <div class="budget-legend-item"><span class="legend-dot" style="background:#22c55e"></span><span>Remaining <strong>—</strong></span></div>
              <div class="budget-legend-item"><span class="legend-dot" style="background:#ef4444"></span><span>Overspending Alerts <strong>4</strong></span></div>
            </div>
          </div>
        </div>
      </section>

      <!-- LOWER ROW -->
      <section class="lower-row">
        <div class="info-card">
          <h3 class="info-card-title">Top Delayed Projects</h3>
          <div class="delayed-list"><p class="empty-state">Loading…</p></div>
        </div>
        <div class="info-card">
          <h3 class="info-card-title">Budget Anomalies</h3>
          <div class="anomaly-list"><p class="empty-state">Loading…</p></div>
          <div class="anomaly-footer">
            <button class="btn-review" onclick="navigate('budget')">Review</button>
          </div>
        </div>
        <div class="info-card">
          <h3 class="info-card-title">Recent Citizen Feedback</h3>
          <div class="feedback-list"><p class="empty-state">Loading…</p></div>
        </div>
      </section>

      <!-- AI INSIGHTS -->
      <section class="ai-insights">
        <h3 class="info-card-title" style="padding:0 0 1rem 0;">AI Insights</h3>
        <div class="ai-grid">
          <div class="ai-card ai-red">
            <div class="ai-card-title">Delay Risk: <strong>—</strong></div>
            <div class="ai-card-body">Loading…</div>
          </div>
          <div class="ai-card ai-orange">
            <div class="ai-card-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9z" clip-rule="evenodd"/></svg></div>
            <div class="ai-card-title"><strong>Budget Alert:</strong> Loading…</div>
            <div class="ai-card-body"></div>
          </div>
          <div class="ai-card ai-green">
            <div class="ai-card-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/></svg></div>
            <div class="ai-card-title"><strong>Top Contractor:</strong> Loading…</div>
            <div class="ai-card-body"></div>
          </div>
        </div>
      </section>

    </main>
  </div>

  <!-- MODAL -->
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal" id="modal">
      <div class="modal-header">
        <h3 id="modalTitle">Details</h3>
        <button class="modal-close" id="modalClose">&times;</button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <!-- NOTIFICATION PANEL -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
      <span>Notifications</span>
      <button class="notif-clear" id="notifClear">Clear all</button>
    </div>
    <div class="notif-item notif-high">
      <div class="notif-dot"></div>
      <div><p class="notif-msg">Brgy. Health Center is over budget</p><span class="notif-time">Loading live data…</span></div>
    </div>
    <div class="notif-item notif-mid">

  <?php include '../includes/footer.php'; 