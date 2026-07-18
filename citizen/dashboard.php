<?php
require_once __DIR__ . '/../auth/session.php';

$user = requireLogin(['citizen']);
$topbarSearchPlaceholder = 'Search projects...';
// filemtime as cache-buster so style/behavior changes show up without a hard refresh
$extraStylesheets = ['citizen/assets/css/citizen.css?v=' . filemtime(__DIR__ . '/assets/css/citizen.css')];

require_once __DIR__ . '/includes/qc-locations.php';
require_once __DIR__ . '/includes/feedback-categories.php';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';

// Get citizen data
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
// Accounts without a citizens row (e.g. the seeded demo login) still get a
// working dashboard — every field below falls back through null coalescing.
$citizen = $stmt->fetch() ?: [];

$verificationStatus = $citizen['verification_status'] ?? 'unverified';
$hasIdPhoto = !empty($citizen['id_photo_path']);

// Unverified accounts are subject to removal after this grace period. The
// countdown shown here is a reminder; actual cleanup is a staff-side task.
$unverifiedGraceDays = 30;
$verifyDaysLeft = null;
if ($verificationStatus !== 'verified' && !empty($citizen['created_at'])) {
    $elapsedDays = (int) floor((time() - strtotime($citizen['created_at'])) / 86400);
    $verifyDaysLeft = max(0, $unverifiedGraceDays - $elapsedDays);
}

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$statusChip = [
    'verified' => ['label' => 'Verified', 'class' => 'chip-verified'],
    'rejected' => ['label' => 'Verification Rejected', 'class' => 'chip-rejected'],
    'unverified' => $hasIdPhoto
        ? ['label' => 'Pending Review', 'class' => 'chip-pending']
        : ['label' => 'Unverified', 'class' => 'chip-unverified'],
][$verificationStatus] ?? ['label' => ucfirst($verificationStatus), 'class' => 'chip-unverified'];
?>

<div class="main-wrapper citizen-wrapper">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

  <main class="content citizen-content">
    <section id="page-dashboard" class="page-section">
      <!-- Hero banner -->
      <div class="dashboard-hero">
        <div class="hero-content">
          <p class="hero-date"><?= date('l, F j, Y') ?></p>
          <h1 class="hero-title"><?= $greeting ?>, <?= htmlspecialchars($citizen['first_name'] ?? $user['full_name']) ?>!</h1>
          <p class="hero-sub">Monitor infrastructure projects in your community and make your voice heard.</p>
          <div class="hero-actions">
            <button class="hero-btn hero-btn-light" onclick="changePage('projects')">
              <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
              Browse Projects
            </button>
            <button class="hero-btn hero-btn-outline" onclick="changePage('submit-feedback')">
              <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>
              Submit Feedback
            </button>
          </div>
        </div>
        <div class="hero-side">
          <span class="verification-chip <?= $statusChip['class'] ?>">
            <?php if ($verificationStatus === 'verified'): ?>
              <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <?php else: ?>
              <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <?php endif; ?>
            <?= htmlspecialchars($statusChip['label']) ?>
          </span>
        </div>
      </div>

      <?php if ($verificationStatus !== 'verified' && !$hasIdPhoto): ?>
        <div class="verify-banner">
          <div class="verify-banner-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"/></svg>
          </div>
          <div class="verify-banner-text">
            <strong>Verify your account<?= $verifyDaysLeft !== null ? ' — ' . ($verifyDaysLeft > 0 ? $verifyDaysLeft . ' day' . ($verifyDaysLeft === 1 ? '' : 's') . ' left' : 'grace period over') : '' ?></strong>
            <span>You haven't submitted an ID photo yet. Verified status unlocks features that need a confirmed identity, like filing formal feedback tied to your name.</span>
            <span class="verify-deadline-note">Important: accounts that stay unverified past the <?= $unverifiedGraceDays ?>-day grace period may be terminated and removed. Upload a valid government ID to keep your account.</span>
          </div>
          <button class="verify-banner-btn" onclick="changePage('profile')">Upload ID Now</button>
        </div>
      <?php elseif ($verificationStatus === 'rejected'): ?>
        <div class="verify-banner verify-banner-rejected">
          <div class="verify-banner-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
          </div>
          <div class="verify-banner-text">
            <strong>Your ID was rejected</strong>
            <span>The ID you submitted couldn't be verified. Please upload a clearer photo of a valid government ID.</span>
          </div>
          <button class="verify-banner-btn" onclick="changePage('profile')">Re-upload ID</button>
        </div>
      <?php endif; ?>

      <!-- KPI Cards -->
      <section class="kpi-grid">
        <article class="kpi-card">
          <div class="kpi-icon kpi-blue">
            <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Active Projects</span>
            <strong class="kpi-value" id="activeProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-green">
            <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Completed Projects</span>
            <strong class="kpi-value" id="completedProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-orange">
            <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <div class="kpi-info">
            <span class="kpi-label">Delayed Projects</span>
            <strong class="kpi-value" id="delayedProjectsCount">0</strong>
          </div>
        </article>

        <article class="kpi-card">
          <div class="kpi-icon kpi-purple">
            <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
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
            <h2 class="chart-title">Latest Updates from the Field</h2>
            <a href="#" class="view-all-link" onclick="changePage('project-status'); return false;">All Projects
              <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </a>
          </div>
          <div id="latestUpdatesContainer" class="updates-feed">
            <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
          </div>
        </article>
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

      <!-- Planned vs actual progress — same series the staff dashboard charts -->
      <section class="chart-card chart-card-full reveal" style="transition-delay:.04s;">
        <div class="chart-header">
          <h2 class="chart-title">Project Progress Overview</h2>
          <span class="chart-subnote">Average planned vs actual progress across public projects, by month</span>
        </div>
        <div class="chart-canvas-box chart-canvas-tall">
          <canvas id="citizenProgressChart"></canvas>
        </div>
      </section>

      <!-- Second charts row: money by stage, project starts, feedback mix -->
      <section class="charts-grid-3 reveal" style="transition-delay:.06s;">
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Budget by Stage</h2>
          </div>
          <div class="chart-canvas-box">
            <canvas id="budgetByStageChart"></canvas>
          </div>
        </article>
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">New Projects per Month</h2>
          </div>
          <div class="chart-canvas-box">
            <canvas id="projectsStartedChart"></canvas>
          </div>
        </article>
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Feedback by Category</h2>
          </div>
          <div class="chart-canvas-box">
            <canvas id="feedbackCategoryChart"></canvas>
          </div>
        </article>
      </section>

      <!-- Recent Projects Section -->
      <section class="dashboard-section reveal" style="transition-delay:.08s;">
        <div class="section-header">
          <h2>Recent Projects in Your Area</h2>
          <a href="#" class="view-all-link" onclick="changePage('projects'); return false;">View All
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
        </div>
        <div id="recentProjectsContainer" class="projects-grid">
          <div class="skeleton-group"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
        </div>
      </section>

      <!-- Recent Feedback Section -->
      <section class="dashboard-section reveal" style="transition-delay:.14s;">
        <div class="section-header">
          <h2>Your Feedback & Complaints</h2>
          <a href="#" class="view-all-link" onclick="changePage('track-feedback'); return false;">View All
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
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
      <div class="list-toolbar">
        <div class="list-search">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="projectSearch" placeholder="Search projects by name, location, or description">
        </div>
        <select id="statusFilter" class="filter-select">
          <option value="">All Status</option>
          <option value="approved">Approved</option>
          <option value="bidding">Bidding</option>
          <option value="awarded">Awarded</option>
          <option value="assigned">Assigned</option>
          <option value="active">Active</option>
          <option value="delayed">Delayed</option>
          <option value="on_hold">On Hold</option>
          <option value="completion_inspection">Final Inspection</option>
          <option value="completed">Completed</option>
          <option value="turnover">Turned Over</option>
        </select>
        <div class="list-pager">
          <span class="list-pager-info" id="projectsPagerInfo">0 of 0</span>
          <button type="button" class="list-pager-btn" id="projectsPagerPrev" title="Previous page" aria-label="Previous page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></button>
          <button type="button" class="list-pager-btn" id="projectsPagerNext" title="Next page" aria-label="Next page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></button>
        </div>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Project</th>
              <th>Budget</th>
              <th>Timeline</th>
              <th>Progress</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="projectsTableBody">
            <tr><td colspan="5" class="table-empty">Loading projects…</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Project Status Page -->
    <section id="page-project-status" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Project Status Tracking</h1>
      </div>
      <div class="list-toolbar">
        <div class="list-search">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="psSearch" placeholder="Search projects">
        </div>
        <div class="list-pager">
          <span class="list-pager-info" id="psPagerInfo">0 of 0</span>
          <button type="button" class="list-pager-btn" id="psPagerPrev" title="Previous page" aria-label="Previous page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></button>
          <button type="button" class="list-pager-btn" id="psPagerNext" title="Next page" aria-label="Next page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></button>
        </div>
      </div>
      <!-- Tracker cards, deliberately distinct from the Public Projects
           directory table: each card walks the project's workflow stages. -->
      <div id="projectStatusBody" class="tracker-list">
        <p class="empty-state">Loading project details…</p>
      </div>
    </section>

    <!-- Submit Feedback Page -->
    <section id="page-submit-feedback" class="page-section fb-page" style="display: none;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Submit Feedback or Complaint</h1>
          <p class="citizen-scope-note">Tell us what's going on — we'll route it to the right office.</p>
        </div>
      </div>

      <?php if ($verificationStatus !== 'verified'): ?>
        <!-- Feedback is verified-citizens-only; api/submit-feedback.php enforces the same rule server-side. -->
        <div class="verify-banner" style="margin-bottom: 0;">
          <div class="verify-banner-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          </div>
          <div class="verify-banner-text">
            <strong>Verified citizens only</strong>
            <?php if ($hasIdPhoto): ?>
              <p>Your submitted ID is still awaiting review by LGU staff. You'll be able to submit feedback as soon as your account is verified. You can view your submitted ID in your <a href="#" onclick="changePage('profile'); return false;">Profile</a>.</p>
            <?php else: ?>
              <p>Submitting feedback requires a verified account. Upload a photo of a valid government ID in your <a href="#" onclick="changePage('profile'); return false;">Profile</a> to get verified.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>

      <!-- Top progress indicator -->
      <div class="fb-progress-top">
        <span class="fb-progress-label" id="fbProgressLabel">Step 1 of 4</span>
        <div class="fb-progress-track"><div class="fb-progress-fill" id="fbProgressFill"></div></div>
      </div>

      <div class="fb-wizard-shell">
        <!-- LEFT: vertical stepper -->
        <aside class="fb-stepper" id="fbStepper" aria-label="Feedback submission steps">
          <div class="fb-step active" data-step="1">
            <span class="fb-step-dot">1</span>
            <div class="fb-step-text"><strong>Choose Concern Type</strong><span>What kind of issue is this?</span></div>
          </div>
          <div class="fb-step" data-step="2">
            <span class="fb-step-dot">2</span>
            <div class="fb-step-text"><strong>Fill Information</strong><span>Details, location, photos</span></div>
          </div>
          <div class="fb-step" data-step="3">
            <span class="fb-step-dot">3</span>
            <div class="fb-step-text"><strong>Review</strong><span>Confirm before sending</span></div>
          </div>
          <div class="fb-step" data-step="4">
            <span class="fb-step-dot">4</span>
            <div class="fb-step-text"><strong>Submit</strong><span>We'll take it from here</span></div>
          </div>
        </aside>

        <!-- CENTER + RIGHT -->
        <div class="fb-body">

          <!-- ============ STEP 1: Concern type ============ -->
          <div class="fb-panel active" data-panel="1">
            <div class="fb-panel-main">
              <h2 class="fb-panel-title">What would you like to report?</h2>
              <p class="fb-panel-sub">Choose the type of concern so we can route your report to the correct government office.</p>

              <div class="fb-concern-cards">
                <button type="button" class="fb-concern-card" data-concern="project">
                  <span class="fb-concern-icon fb-icon-project" aria-hidden="true">🏗️</span>
                  <span class="fb-concern-name">Infrastructure Project Concern</span>
                  <span class="fb-concern-desc">Issues related to ongoing or completed government infrastructure projects.</span>
                  <ul class="fb-concern-examples">
                    <li>Project delay</li>
                    <li>Poor workmanship</li>
                    <li>Contractor complaint</li>
                    <li>Construction issue</li>
                    <li>Project transparency</li>
                    <li>Budget concern</li>
                  </ul>
                  <span class="fb-concern-footer fb-footer-ipms">Managed by IPMS</span>
                </button>

                <button type="button" class="fb-concern-card" data-concern="maintenance">
                  <span class="fb-concern-icon fb-icon-maintenance" aria-hidden="true">🛠️</span>
                  <span class="fb-concern-name">Infrastructure Maintenance Issue</span>
                  <span class="fb-concern-desc">Problems involving public facilities that require maintenance.</span>
                  <ul class="fb-concern-examples">
                    <li>Broken streetlight</li>
                    <li>Damaged road</li>
                    <li>Drainage</li>
                    <li>Sidewalk damage</li>
                    <li>Flooding</li>
                    <li>Fallen trees</li>
                    <li>Public facility repair</li>
                  </ul>
                  <span class="fb-concern-footer fb-footer-cimms">Connected with Community Infrastructure Maintenance Management System (CIMMS)</span>
                </button>
              </div>
            </div>

            <aside class="fb-illustration" id="fbIllustration1" data-state="empty">
              <div class="fb-illu-empty">
                <span class="fb-illu-empty-icon">👆</span>
                <p>Pick a card to see what happens next.</p>
              </div>
            </aside>
          </div>

          <!-- ============ STEP 2: Form ============ -->
          <div class="fb-panel" data-panel="2">
            <!-- Project concerns use the IPMS wizard form below; maintenance
                 issues swap it for the CIMMS replica card (#fbCimmsWrap). -->
            <div class="fb-panel-main" id="fbProjectWrap">
              <div class="fb-panel-headrow">
                <div>
                  <h2 class="fb-panel-title" id="fbStep2Title">Tell us more</h2>
                  <p class="fb-panel-sub" id="fbStep2Sub">A few details help the right office respond faster.</p>
                </div>
                <span class="fb-time-badge">⏱ Approximately 2–3 minutes</span>
              </div>

              <div class="fb-form-tips-layout">
                <form id="feedbackForm" method="POST" data-profile-phone="<?= htmlspecialchars(preg_replace('/\D+/', '', (string) ($citizen['phone'] ?? '')), ENT_QUOTES) ?>">
                  <input type="hidden" name="concern_type" id="feedbackConcernType" value="project">

                  <div class="fb-anon-row" id="fbAnonRow">
                    <label class="fb-toggle">
                      <input type="checkbox" id="feedbackAnonymous" name="anonymous" value="1">
                      <span class="fb-toggle-track"><span class="fb-toggle-thumb"></span></span>
                      <span class="fb-toggle-label">Submit this report anonymously</span>
                    </label>
                    <p class="fb-toggle-hint">We won't attach your name to this report. You can still add contact details below if you want a callback.</p>
                  </div>

                  <div class="fb-contact-section" id="fbContactGrid">
                    <p class="fb-contact-heading">Contact Information <span class="fb-optional">(optional)</span></p>
                    <div class="fb-contact-grid">
                      <div class="form-group">
                        <label for="feedbackContactName">Contact Name</label>
                        <input type="text" id="feedbackContactName" name="contact_name" placeholder="Your name" value="<?= htmlspecialchars(trim(($citizen['first_name'] ?? '') . ' ' . ($citizen['last_name'] ?? ''))) ?>">
                      </div>
                      <div class="form-group">
                        <label for="feedbackContactPhone" id="fbPhoneLabel">Contact Number</label>
                        <input type="tel" id="feedbackContactPhone" name="contact_phone" placeholder="09XX-XXX-XXXX" maxlength="13" value="<?= htmlspecialchars((string) ($citizen['phone'] ?? '')) ?>">
                      </div>
                      <div class="form-group">
                        <label for="feedbackContactEmail">Email</label>
                        <input type="email" id="feedbackContactEmail" name="contact_email" placeholder="you@example.com" value="<?= htmlspecialchars((string) ($citizen['email'] ?? '')) ?>">
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="feedbackProjectName" id="fbProjectNameLabel">Project Name <span class="fb-optional">(optional)</span></label>
                    <input type="text" id="feedbackProjectName" name="project_name" placeholder="e.g. Barangay Culiat Road Widening">
                  </div>

                  <div class="location-fieldset">
                    <div class="location-fieldset-head">
                      <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                      <span>Location in Quezon City</span>
                    </div>

                    <div class="form-group">
                      <label for="feedbackDistrict">District *</label>
                      <select id="feedbackDistrict" name="district" required>
                        <option value="">Select your district</option>
                        <?php foreach (array_keys(qcDistricts()) as $districtName): ?>
                          <option value="<?= htmlspecialchars($districtName) ?>"><?= htmlspecialchars($districtName) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="form-group">
                      <label for="feedbackBarangay">Barangay *</label>
                      <select id="feedbackBarangay" name="barangay" required disabled>
                        <option value="">Select a district first</option>
                      </select>
                      <div class="barangay-alt-hint" id="barangayAltHint"></div>
                    </div>

                    <div class="form-group">
                      <label>Exact spot on the map <span class="fb-optional">(optional, recommended)</span></label>
                      <p class="pin-hint">Tap the exact spot on the interactive map to drop a pin — you can drag it to fine-tune. This helps responders find the precise location.</p>
                      <input type="hidden" name="latitude" id="feedbackLat">
                      <input type="hidden" name="longitude" id="feedbackLng">
                    </div>

                    <div class="location-pill" id="locationPill" style="display: none;">
                      <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                      <span id="locationPillText"></span>
                    </div>

                    <div class="qc-map-inline">
                      <div id="qcMap" class="qc-map">
                        <div class="qc-map-loading">Loading map…</div>
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="feedbackCategory" id="fbCategoryLabel">Category *</label>
                    <select id="feedbackCategory" name="category" required>
                      <option value="">Select category</option>
                      <?php foreach (feedbackCategories() as $catValue => $catLabel): ?>
                        <option value="<?= htmlspecialchars($catValue) ?>" data-concern="<?= htmlspecialchars(in_array($catValue, ['project_delay'], true) ? 'project' : (in_array($catValue, ['road_damage', 'drainage_flooding', 'streetlight', 'sidewalk_accessibility', 'safety_hazard'], true) ? 'maintenance' : 'both')) ?>">
                          <?= htmlspecialchars($catLabel) ?>
                        </option>
                      <?php endforeach; ?>
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
                    <label for="feedbackMessage" id="fbMessageLabel">Description *</label>
                    <textarea id="feedbackMessage" name="message" rows="6" placeholder="Describe your concern, report, or complaint about your area..." required></textarea>
                  </div>

                  <div class="form-group">
                    <label for="feedbackPhotos" id="fbPhotosLabel">Photos (proof) <span class="fb-optional">— optional, up to 4 images, 3MB each</span></label>
                    <label for="feedbackPhotos" class="id-upload-box feedback-upload-box">
                      <input type="file" id="feedbackPhotos" name="photos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                      <svg width="26" height="26" viewBox="0 0 20 20" fill="currentColor" class="id-upload-icon"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                      <p class="id-upload-text">Click or drag photos here</p>
                      <p class="id-upload-hint">JPG, PNG, GIF, or WEBP — maximum of 4 photos, 3MB each</p>
                    </label>
                    <div class="feedback-photo-previews" id="feedbackPhotoPreviews"></div>
                    <div class="id-upload-status" id="feedbackPhotoStatus" style="display: none;"></div>
                  </div>

                  <div class="fb-panel-actions">
                    <button type="button" class="btn-outline" id="fbBackBtn2">Back</button>
                    <button type="button" class="btn-primary" id="fbNextBtn2">Continue</button>
                  </div>
                </form>
              </div>

              <div class="fb-tips" id="fbTips">
                <span>✔ Include photos for faster verification.</span>
                <span>✔ Pin the exact location.</span>
                <span>✔ Provide clear descriptions.</span>
              </div>
            </div>

            <!-- ============ Maintenance path: replica of the CIMM
                 "Submit a Request" form. Same fields, same order, same
                 look — location is picked on a map (search / tap / GPS)
                 and fills itself in. Submits to the same feedback API. -->
            <div id="fbCimmsWrap" class="cimms-form-wrapper" style="display: none;">
              <div class="cimms-report-card">
                <h2>Submit a Request</h2>
                <form id="cimmsForm" autocomplete="off">
                  <!-- Hybrid dropdown/input: Infrastructure -->
                  <div class="cimms-input-group">
                    <label for="cimmsInfraSelect">INFRASTRUCTURE TYPE <span class="cimms-req">*</span></label>
                    <select id="cimmsInfraSelect" name="infrastructure">
                      <option value="">— Select infrastructure —</option>
                      <option value="Roads">Roads</option>
                      <option value="Street Lights">Street Lights</option>
                      <option value="Drainage">Drainage</option>
                      <option value="Public Facilities">Public Facilities</option>
                      <option value="Water Supply">Water Supply</option>
                      <option value="Electrical">Electrical</option>
                      <option value="Other">Other</option>
                    </select>
                    <input
                      type="text"
                      id="cimmsInfraOther"
                      name="infrastructure_other"
                      placeholder="Specify infrastructure"
                      style="display:none;"
                      autocomplete="off"
                    >
                  </div>
                  <div class="cimms-input-group">
                    <label for="cimmsLocationInput">LOCATION <span class="cimms-req">*</span></label>
                    <input
                      type="text"
                      id="cimmsLocationInput"
                      name="location"
                      placeholder="Click to select location"
                      autocomplete="off"
                      readonly
                    >
                  </div>
                  <div class="cimms-input-group">
                    <label for="cimmsName">NAME <span class="cimms-opt">(Optional)</span></label>
                    <input type="text" id="cimmsName" name="name" placeholder="Your name" value="<?= htmlspecialchars(trim(($citizen['first_name'] ?? '') . ' ' . ($citizen['last_name'] ?? ''))) ?>">
                  </div>
                  <div class="cimms-input-group">
                    <label for="cimmsContactNumber">CONTACT NUMBER <span class="cimms-req">*</span></label>
                    <input
                      type="tel"
                      id="cimmsContactNumber"
                      name="contact_number"
                      placeholder="09XX-XXX-XXXX"
                      maxlength="13"
                      value="<?= htmlspecialchars(preg_replace('/\D+/', '', (string) ($citizen['phone'] ?? ''))) ?>"
                      required
                    >
                  </div>
                  <div class="cimms-input-group cimms-full-width">
                    <label for="cimmsEmail">EMAIL ADDRESS <span class="cimms-opt">(Optional)</span></label>
                    <input type="email" id="cimmsEmail" name="email" placeholder="your@email.com" value="<?= htmlspecialchars((string) ($citizen['email'] ?? '')) ?>">
                    <p class="cimms-hint">
                      <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                      If provided, we'll send you progress updates on your report.
                    </p>
                  </div>
                  <div class="cimms-input-group cimms-full-width">
                    <label for="cimmsIssue">ISSUE / DAMAGE DESCRIPTION <span class="cimms-req">*</span></label>
                    <textarea id="cimmsIssue" name="issue" placeholder="Describe the problem in detail..." required></textarea>
                  </div>
                  <div class="cimms-input-group cimms-full-width">
                    <label>EVIDENCE PHOTOS</label>
                    <div class="cimms-dropzone" id="cimmsDropzone">
                      <input type="file" id="cimmsEvidence" accept="image/*" multiple hidden>
                      <span class="cimms-dropzone-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.5 17a4.5 4.5 0 01-1.44-8.765 4.5 4.5 0 018.302-3.046 3.5 3.5 0 014.504 4.272A4 4 0 0115 17H5.5zm3.75-2.75a.75.75 0 001.5 0V9.66l1.95 2.1a.75.75 0 101.1-1.02l-3.25-3.5a.75.75 0 00-1.1 0l-3.25 3.5a.75.75 0 101.1 1.02l1.95-2.1v4.59z" clip-rule="evenodd"/></svg>
                      </span>
                      <p class="cimms-dropzone-text">Click or drag to upload images</p>
                      <p class="cimms-dropzone-hint">JPG, PNG, WEBP</p>
                    </div>
                    <div id="cimmsImagePreview" style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;"></div>
                  </div>
                  <div class="cimms-terms cimms-full-width">
                    <label>
                      <input type="checkbox" id="cimmsAgree" required>
                      <span>I AGREE TO THE <a href="#" onclick="return false;">Terms and Conditions</a> AND <a href="#" onclick="return false;">Privacy Policy</a></span>
                    </label>
                  </div>
                  <!-- Back sits at the bottom, matching the wizard's other steps -->
                  <div class="cimms-actions cimms-full-width">
                    <button type="button" class="btn-outline" id="fbCimmsBack">Back</button>
                    <button type="submit" class="cimms-btn-primary" id="cimmsSubmitBtn">
                      <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                      Submit Request
                    </button>
                    <span></span>
                  </div>
                </form>
              </div>
            </div>

            <aside class="fb-illustration" id="fbIllustration2" data-state="project"></aside>
          </div>

          <!-- ============ STEP 3: Review ============ -->
          <div class="fb-panel" data-panel="3">
            <div class="fb-panel-main">
              <h2 class="fb-panel-title">Review your report</h2>
              <p class="fb-panel-sub">Make sure everything looks right before you send it.</p>

              <div class="fb-review-card" id="fbReviewCard"></div>

              <div class="fb-submit-error" id="fbSubmitError" style="display:none;"></div>

              <div class="fb-panel-actions">
                <button type="button" class="btn-outline" id="fbBackBtn3">Back</button>
                <button type="button" class="btn-primary" id="fbNextBtn3">Confirm &amp; Submit</button>
              </div>
            </div>

            <aside class="fb-illustration" id="fbIllustration3" data-state="project"></aside>
          </div>

          <!-- ============ STEP 4: Success ============ -->
          <div class="fb-panel fb-panel-success" data-panel="4">
            <div class="fb-success-wrap">
              <div class="fb-success-mark" aria-hidden="true">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" fill="var(--success, #27ae60)" opacity=".12"/><path d="M7 12.5l3 3 7-7.5" stroke="var(--success, #27ae60)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <h2 class="fb-panel-title">Your report has been received.</h2>
              <p class="fb-panel-sub">Your concern will be routed to the appropriate government office. A tracking number has been generated below.</p>
              <div class="fb-tracking-chip" id="fbTrackingChip">#FB-000000</div>
              <p class="fb-success-cimm-note" id="fbSuccessCimmNote" style="display:none;"></p>
              <div class="fb-success-actions">
                <button type="button" class="btn-outline" id="fbBtnDashboard">Return to Dashboard</button>
                <button type="button" class="btn-outline" id="fbBtnTrack">Track Report</button>
                <button type="button" class="btn-primary" id="fbBtnAnother">Submit Another Report</button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- CIMMS-style submit confirmation modal (maintenance path only) -->
      <div id="cimmsAlertBackdrop">
        <div id="cimmsAlertModal">
          <div class="cimms-icon-wrap">
            <span class="cimms-icon">✅</span>
          </div>
          <div class="cimms-alert-title">Confirm Submission</div>
          <div class="cimms-alert-desc">Are you sure you want to submit this maintenance request?</div>
          <div class="cimms-alert-btns">
            <button class="cimms-alert-btn cancel" type="button" id="cimmsAlertCancel">Cancel</button>
            <button class="cimms-alert-btn confirm" type="button" id="cimmsAlertConfirm">Submit</button>
          </div>
        </div>
      </div>

      <!-- CIMMS-style map picker: drop or drag the pin and the Location
           field fills itself from OpenStreetMap reverse geocoding. -->
      <div id="cimmsMapBackdrop">
        <div id="cimmsMapModal">
          <div class="cimms-map-title">Pin the Location</div>
          <div class="cimms-map-desc">Tap the map to drop a pin — drag it to fine-tune. The address fills in automatically.</div>
          <div id="cimmsMapCanvas"></div>
          <div class="cimms-map-address" id="cimmsMapAddress"></div>
          <div class="cimms-map-btns">
            <button class="cimms-alert-btn cancel" type="button" id="cimmsMapCancel">Cancel</button>
            <button class="cimms-alert-btn confirm" type="button" id="cimmsMapUse">Use this location</button>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </section>

    <!-- Track Feedback Page -->
    <section id="page-track-feedback" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">Track Your Feedback & Complaints</h1>
      </div>
      <div class="list-toolbar">
        <div class="list-search">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="tfSearch" placeholder="Search your reports">
        </div>
        <div class="list-pager">
          <span class="list-pager-info" id="tfPagerInfo">0 of 0</span>
          <button type="button" class="list-pager-btn" id="tfPagerPrev" title="Previous page" aria-label="Previous page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></button>
          <button type="button" class="list-pager-btn" id="tfPagerNext" title="Next page" aria-label="Next page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></button>
        </div>
      </div>
      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Report</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Location</th>
              <th>Submitted</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="trackedFeedbackBody">
            <tr><td colspan="6" class="table-empty">Loading your submissions…</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Transparency Dashboard -->
    <section id="page-transparency" class="page-section" style="display: none;">
      <div class="page-header">
        <div>
          <h1 class="page-title">Transparency Dashboard</h1>
          <p class="citizen-scope-note">Budget allocation, expenses, and project performance metrics</p>
        </div>
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

      <!-- Charts: all figures come straight from the staff-side projects &
           expenses tables via citizen/api/transparency.php -->
      <div class="transparency-charts">
        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Budget Utilization</h2>
          </div>
          <div class="chart-body budget-body">
            <div class="donut-wrapper">
              <canvas id="budgetDonutChart"></canvas>
              <div class="donut-center">
                <span class="donut-pct" id="budgetDonutPct">0%</span>
                <span class="donut-sub">spent</span>
              </div>
            </div>
            <div class="budget-legend" id="budgetDonutLegend"></div>
          </div>
        </article>

        <article class="chart-card">
          <div class="chart-header">
            <h2 class="chart-title">Spending by Category</h2>
          </div>
          <div class="chart-canvas-box">
            <canvas id="categorySpendChart"></canvas>
          </div>
        </article>

        <article class="chart-card chart-card-full">
          <div class="chart-header">
            <h2 class="chart-title">Monthly Spending</h2>
            <span class="chart-subnote">Expenses recorded over the last 12 months</span>
          </div>
          <div class="chart-canvas-box chart-canvas-tall">
            <canvas id="monthlySpendChart"></canvas>
          </div>
        </article>

        <article class="chart-card chart-card-full">
          <div class="chart-header">
            <h2 class="chart-title">Budget vs Spent by Project</h2>
            <span class="chart-subnote">The largest public projects by allocated budget</span>
          </div>
          <div class="chart-canvas-box chart-canvas-tall">
            <canvas id="projectBudgetChart"></canvas>
          </div>
        </article>
      </div>

      <section class="dashboard-section">
        <div class="section-header">
          <h2>Project Expenses Breakdown</h2>
        </div>
        <div class="list-toolbar">
          <div class="list-search">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
            <input type="text" id="expSearch" placeholder="Search expenses by project or category">
          </div>
          <div class="list-pager">
            <span class="list-pager-info" id="expPagerInfo">0 of 0</span>
            <button type="button" class="list-pager-btn" id="expPagerPrev" title="Previous page" aria-label="Previous page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></button>
            <button type="button" class="list-pager-btn" id="expPagerNext" title="Next page" aria-label="Next page"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></button>
          </div>
        </div>
        <div class="table-card">
          <table class="data-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Category</th>
                <th>Date</th>
                <th class="cell-num">Amount</th>
              </tr>
            </thead>
            <tbody id="expensesBody">
              <tr><td colspan="4" class="table-empty">Loading expense data…</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <!-- My Profile Page -->
    <section id="page-profile" class="page-section" style="display: none;">
      <div class="page-header">
        <h1 class="page-title">My Profile</h1>
      </div>

      <div class="profile-layout">
        <!-- Identity card -->
        <div class="profile-card profile-identity">
          <div class="profile-avatar"><?= strtoupper(substr($citizen['first_name'] ?? $user['full_name'], 0, 1)) ?></div>
          <h2 class="profile-name"><?= htmlspecialchars(trim(($citizen['first_name'] ?? '') . ' ' . ($citizen['middle_name'] ?? '') . ' ' . ($citizen['last_name'] ?? '')) ?: $user['full_name']) ?></h2>
          <p class="profile-email"><?= htmlspecialchars($citizen['email'] ?? $user['email']) ?></p>
          <span class="verification-chip <?= $statusChip['class'] ?>"><?= htmlspecialchars($statusChip['label']) ?></span>
          <?php if (!empty($citizen['created_at'])): ?>
            <p class="profile-since">Member since <?= date('F Y', strtotime($citizen['created_at'])) ?></p>
          <?php endif; ?>
        </div>

        <div class="profile-main">
          <!-- Personal info -->
          <div class="profile-card">
            <h3 class="profile-card-title">
              <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
              Personal Information
            </h3>
            <div class="profile-grid">
              <div class="profile-field"><span class="profile-label">Date of Birth</span><span class="profile-value"><?= htmlspecialchars(!empty($citizen['date_of_birth']) ? date('F j, Y', strtotime($citizen['date_of_birth'])) : '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Gender</span><span class="profile-value"><?= htmlspecialchars(($citizen['gender'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Civil Status</span><span class="profile-value"><?= htmlspecialchars(($citizen['civil_status'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Phone</span><span class="profile-value"><?= htmlspecialchars(($citizen['phone'] ?? '') ?: '—') ?></span></div>
            </div>
          </div>

          <!-- Address -->
          <div class="profile-card">
            <h3 class="profile-card-title">
              <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
              Address
            </h3>
            <div class="profile-grid">
              <div class="profile-field profile-field-wide"><span class="profile-label">Street Address</span><span class="profile-value"><?= htmlspecialchars(($citizen['address'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Barangay</span><span class="profile-value"><?= htmlspecialchars(($citizen['barangay'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">City</span><span class="profile-value"><?= htmlspecialchars(($citizen['city'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Province</span><span class="profile-value"><?= htmlspecialchars(($citizen['province'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">Postal Code</span><span class="profile-value"><?= htmlspecialchars(($citizen['postal_code'] ?? '') ?: '—') ?></span></div>
            </div>
          </div>

          <!-- Verification -->
          <div class="profile-card" id="verificationCard">
            <h3 class="profile-card-title">
              <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"/></svg>
              Account Verification
            </h3>

            <div class="profile-grid" style="margin-bottom: 1.25rem;">
              <div class="profile-field"><span class="profile-label">ID Type</span><span class="profile-value"><?= htmlspecialchars(($citizen['id_type'] ?? '') ?: '—') ?></span></div>
              <div class="profile-field"><span class="profile-label">ID Number</span><span class="profile-value"><?= htmlspecialchars(($citizen['id_number'] ?? '') ?: '—') ?></span></div>
            </div>

            <?php if ($verificationStatus === 'verified'): ?>
              <div class="verify-note verify-note-success">
                <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Your identity has been verified by LGU staff. No further action needed.
              </div>
            <?php else: ?>
              <?php if ($hasIdPhoto): ?>
                <div class="verify-note <?= $verificationStatus === 'rejected' ? 'verify-note-rejected' : 'verify-note-pending' ?>">
                  <?php if ($verificationStatus === 'rejected'): ?>
                    <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    Your submitted ID was rejected. Please upload a clearer photo of a valid government ID below.
                  <?php else: ?>
                    <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                    Your ID has been submitted and is awaiting review by LGU staff. You can replace it below if needed.
                  <?php endif; ?>
                </div>
                <div class="current-id-preview">
                  <div class="current-id-head">
                    <span class="profile-label">Submitted ID</span>
                    <a class="id-fullsize-link" href="<?= htmlspecialchars(appUrl($citizen['id_photo_path'])) ?>" target="_blank" rel="noopener">
                      <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                      View full size
                    </a>
                  </div>
                  <a href="<?= htmlspecialchars(appUrl($citizen['id_photo_path'])) ?>" target="_blank" rel="noopener" title="Open your submitted ID in a new tab">
                    <img src="<?= htmlspecialchars(appUrl($citizen['id_photo_path'])) ?>" alt="Submitted ID photo" id="currentIdImage">
                  </a>
                </div>
              <?php else: ?>
                <div class="verify-note verify-note-pending">
                  <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  Upload a photo of a valid government ID to get your account verified.
                </div>
                <div class="verify-note verify-note-rejected">
                  <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  Accounts that stay unverified past the <?= $unverifiedGraceDays ?>-day grace period may be terminated and removed<?= $verifyDaysLeft !== null && $verifyDaysLeft > 0 ? ' — you have ' . $verifyDaysLeft . ' day' . ($verifyDaysLeft === 1 ? '' : 's') . ' left' : '' ?>.
                </div>
              <?php endif; ?>

              <form id="idUploadForm">
                <label for="profile_id_photo" class="id-upload-box" id="idUploadBox">
                  <input type="file" id="profile_id_photo" name="id_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                  <svg width="30" height="30" viewBox="0 0 20 20" fill="currentColor" class="id-upload-icon"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                  <p class="id-upload-text"><?= $hasIdPhoto ? 'Click to replace your ID photo' : 'Click to upload your ID photo' ?></p>
                  <p class="id-upload-hint">JPG, PNG, GIF, or WEBP — max 3MB</p>
                </label>
                <div class="id-upload-preview" id="idUploadPreview" style="display: none;">
                  <img id="idUploadPreviewImg" alt="Selected ID preview">
                </div>
                <div class="id-upload-status" id="idUploadStatus" style="display: none;"></div>
                <button type="submit" class="btn-primary" id="idUploadBtn" style="display: none; margin-top: 1rem;">Submit for Verification</button>
              </form>
            <?php endif; ?>
          </div>

          <!-- Security -->
          <div class="profile-card">
            <h3 class="profile-card-title">
              <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
              Security
            </h3>
            <div class="security-row">
              <div class="security-info">
                <strong>Password</strong>
                <p>Use a strong password that you don't reuse on other sites. Changing it regularly keeps your account safe.</p>
              </div>
              <button type="button" class="btn-outline" onclick="showChangePassword()">Change Password</button>
            </div>
            <div class="security-row">
              <div class="security-info">
                <strong>Account email</strong>
                <p><?= htmlspecialchars($citizen['email'] ?? $user['email']) ?> — used for sign-in, one-time codes, and password resets.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="changePasswordModal" style="display: none;">
  <div class="modal-card">
    <div class="modal-head">
      <h3>
        <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
        Change Password
      </h3>
      <button type="button" class="modal-close" id="changePasswordClose" title="Close">&times;</button>
    </div>
    <form id="changePasswordForm">
      <div class="form-group">
        <label for="currentPassword">Current Password *</label>
        <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label for="newPassword">New Password *</label>
        <input type="password" id="newPassword" name="new_password" autocomplete="new-password" required>
        <small class="pin-hint">At least 8 characters, with an uppercase and lowercase letter, a number, and a special character (!@#$%^&*).</small>
      </div>
      <div class="form-group">
        <label for="confirmNewPassword">Confirm New Password *</label>
        <input type="password" id="confirmNewPassword" name="confirm_password" autocomplete="new-password" required>
      </div>
      <div class="id-upload-status" id="changePasswordStatus" style="display: none;"></div>
      <button type="submit" class="btn-primary" id="changePasswordBtn" style="width: 100%; margin-top: 0.5rem;">Update Password</button>
    </form>
  </div>
</div>

<!-- Project Detail Modal (read-only view of staff-side project data) -->
<div class="modal-overlay" id="projectDetailModal" style="display: none;">
  <div class="modal-card modal-card-wide">
    <div class="modal-head">
      <h3 id="projectDetailTitle">
        <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
        Project Details
      </h3>
      <button type="button" class="modal-close" id="projectDetailClose" title="Close">&times;</button>
    </div>
    <div class="project-detail-body" id="projectDetailBody">
      <p class="empty-state">Loading project details...</p>
    </div>
  </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal-overlay" id="logoutConfirmModal" style="display: none;">
  <div class="modal-card">
    <div class="modal-head">
      <h3>
        <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
        Log Out?
      </h3>
      <button type="button" class="modal-close" id="logoutConfirmClose" title="Close">&times;</button>
    </div>
    <p class="modal-text">Are you sure you want to log out? You'll need to sign in again to access your account.</p>
    <div class="modal-actions">
      <button type="button" class="btn-modal-ghost" id="logoutCancelBtn">Cancel</button>
      <a href="<?= htmlspecialchars(appUrl('/auth/logout.php')) ?>" class="btn-modal-danger">Yes, Log Out</a>
    </div>
  </div>
</div>

<!-- Inactivity Auto-Logout Warning Modal -->
<div class="modal-overlay" id="idleWarningModal" style="display: none;">
  <div class="modal-card">
    <div class="modal-head">
      <h3>
        <svg width="17" height="17" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        Are you still there?
      </h3>
    </div>
    <p class="modal-text">You've been inactive for a while. For your security, you'll be logged out automatically in <strong id="idleCountdown">60</strong> seconds.</p>
    <div class="modal-actions">
      <a href="<?= htmlspecialchars(appUrl('/auth/logout.php')) ?>?timeout=1" class="btn-modal-ghost">Log Out Now</a>
      <button type="button" class="btn-modal-primary" id="idleStayBtn">Stay Logged In</button>
    </div>
  </div>
</div>

<?php $notifPanelTitle = 'Citizen Updates'; include __DIR__ . '/../includes/notifications-panel.php'; ?>

<script>
  // District → barangay data for the feedback form + map (see citizen/includes/qc-locations.php)
  window.QC_DISTRICTS = <?= json_encode(qcDistricts(), JSON_UNESCAPED_UNICODE) ?>;
  window.QC_GEOJSON_URL = <?= json_encode(appUrl('/citizen/assets/data/qc-barangays.geojson')) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/notifications.js')) ?>"></script>
<script>window.SIDEBAR_BADGES_PORTAL = 'citizen';</script>
<script src="<?= htmlspecialchars(assetUrl('/assets/js/sidebar-badges.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('/citizen/assets/js/citizen.js')) ?>"></script>
