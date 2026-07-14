<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['citizen']);
$topbarSearchPlaceholder = 'Search projects...';
$extraStylesheets = ['citizen/assets/css/citizen.css'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';

// Get citizen data
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
?>

<div class="main-wrapper citizen-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content citizen-content">
    <section id="page-dashboard" class="page-section">
      <div class="page-header">
        <div>
          <h1 class="page-title">Welcome, <?= htmlspecialchars($citizen['first_name'] ?? $user['full_name']) ?>!</h1>
          <p class="citizen-scope-note">Monitor infrastructure projects and submit feedback</p>
        </div>
        <div class="header-actions">
          <span class="verification-status" style="background: <?= $citizen['verification_status'] === 'verified' ? '#d4edda' : '#fff3cd' ?>; color: <?= $citizen['verification_status'] === 'verified' ? '#155724' : '#856404' ?>;">
            ✓ <?= ucfirst($citizen['verification_status']) ?>
          </span>
        </div>
      </div>

      <!-- KPI Cards -->
      <section class="kpi-grid">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Active Projects</span>
            <strong class="kpi-value" id="activeProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Completed Projects</span>
            <strong class="kpi-value" id="completedProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Delayed Projects</span>
            <strong class="kpi-value" id="delayedProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-purple">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">My Submissions</span>
            <strong class="kpi-value" id="mySubmissionsCount">0</strong>
          </div>
        </article>
      </section>

      <section class="charts-row reveal">
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Project Status Mix</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="citizenStatusChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="citizenStatusChartTotal">0</span>
              </div>
            </div>
            <div class="budget-legend" id="citizenStatusChartLegend"></div>
          </div>
        </article>
      </section>

      <!-- Recent Projects Section -->
      <section class="dashboard-section reveal" style="transition-delay:.08s;">
        <div class="section-header">
          <h2>Recent Projects in Your Area</h2>
          <a href="#" class="view-all-link" onclick="changePage('projects')">View All →</a>
        </div>
        <div id="recentProjectsContainer" class="projects-grid">
          <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
        </div>
      </section>

      <!-- Recent Feedback Section -->
      <section class="dashboard-section reveal" style="transition-delay:.14s;">
        <div class="section-header">
          <h2>Your Feedback & Complaints</h2>
          <a href="#" class="view-all-link" onclick="changePage('track-feedback')">View All →</a>
        </div>
        <div id="recentFeedbackContainer" class="feedback-list">
          <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
        </div>
      </section>
    </section>

    <!-- Projects Page -->
    <section id="page-projects" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Public Projects</h1>
      </div>
      <div class="filters">
        <input type="text" id="projectSearch" placeholder="Search projects..." class="search-box">
        <select id="statusFilter" class="filter-select">
          <option value="">All Status</option>
          <option value="approved">Approved</option>
          <option value="bidding">Bidding</option>
          <option value="awarded">Awarded</option>
          <option value="assigned">Assigned</option>
          <option value="active">Active</option>
          <option value="delayed">Delayed</option>
          <option value="on_hold">On Hold</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div id="projectsGridContainer" class="projects-grid">
        <p style="text-align: center; color: #999; padding: 2rem;">Loading projects...</p>
      </div>
    </section>

    <!-- Project Status Page -->
    <section id="page-project-status" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Project Status Tracking</h1>
      </div>
      <div id="projectStatusContainer" class="status-list">
        <p style="text-align: center; color: #999; padding: 2rem;">Loading project details...</p>
      </div>
    </section>

    <!-- Submit Feedback Page -->
    <section id="page-submit-feedback" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Submit Feedback or Complaint</h1>
      </div>
      <div class="form-container">
        <form id="feedbackForm" method="POST">
          <div class="form-group">
            <label for="feedbackProject">Project *</label>
            <select id="feedbackProject" name="project_id" required>
              <option value="">Select a project</option>
            </select>
          </div>
          <div class="form-group">
            <label for="feedbackCategory">Category *</label>
            <select id="feedbackCategory" name="category" required>
              <option value="">Select category</option>
              <option value="complaint">Complaint</option>
              <option value="suggestion">Suggestion</option>
              <option value="inquiry">Inquiry</option>
            </select>
          </div>
          <div class="form-group">
            <label for="feedbackPriority">Priority *</label>
            <select id="feedbackPriority" name="priority" required>
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="form-group">
            <label for="feedbackMessage">Message *</label>
            <textarea id="feedbackMessage" name="message" rows="6" placeholder="Please describe your feedback..." required></textarea>
          </div>
          <button type="submit" class="btn-primary">Submit Feedback</button>
        </form>
      </div>
    </section>

    <!-- Track Feedback Page -->
    <section id="page-track-feedback" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Track Your Feedback & Complaints</h1>
      </div>
      <div id="trackedFeedbackContainer" class="feedback-list">
        <p style="text-align: center; color: #999; padding: 2rem;">Loading your submissions...</p>
      </div>
    </section>

    <!-- Transparency Dashboard -->
    <section id="page-transparency" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Transparency Dashboard</h1>
        <p style="color: #666; margin-top: 0.5rem;">Budget allocation, expenses, and project performance metrics</p>
      </div>
      
      <div class="transparency-grid">
        <div class="transparency-card">
          <h3>Total Budget Allocated</h3>
          <p class="metric-value" id="totalBudget">₱0</p>
        </div>
        <div class="transparency-card">
          <h3>Total Expenses</h3>
          <p class="metric-value" id="totalExpenses">₱0</p>
        </div>
        <div class="transparency-card">
          <h3>Budget Remaining</h3>
          <p class="metric-value" id="budgetRemaining">₱0</p>
        </div>
        <div class="transparency-card">
          <h3>Projects On-Time</h3>
          <p class="metric-value" id="onTimeProjects">0</p>
        </div>
      </div>

      <section class="dashboard-section">
        <h2>Project Expenses Breakdown</h2>
        <div id="expensesContainer" class="expenses-list">
          <p style="text-align: center; color: #999; padding: 2rem;">Loading expense data...</p>
        </div>
      </section>
    </section>
  </main>
</div>

<?php $notifPanelTitle = 'Citizen Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/citizen/assets/js/citizen.js')) ?>"></script>
