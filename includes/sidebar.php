  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
      </div>
      <div class="logo-text">
        <span class="logo-title">LGU Infrastructure</span>
        <span class="logo-sub">Project Management System</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="#" class="nav-item active" data-page="dashboard">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg></span>
        <span class="nav-label">Dashboard</span>
        <span class="nav-badge" data-badge="dashboard"></span>
      </a>
      <a href="#" class="nav-item" data-page="project-registration">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg></span>
        <span class="nav-label">Project Registration</span>
        <span class="nav-badge" data-badge="project-registration"></span>
      </a>
      <a href="#" class="nav-item" data-page="project-approval">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Project Approval</span>
        <span class="nav-badge" data-badge="project-approval"></span>
      </a>
      <a href="#" class="nav-item" data-page="contractor-assignment">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg></span>
        <span class="nav-label">Contractor Assignment</span>
        <span class="nav-badge" data-badge="contractor-assignment"></span>
      </a>
      <a href="#" class="nav-item" data-page="workflow-management">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v3a2 2 0 002 2h3a2 2 0 002-2V5a2 2 0 00-2-2H4zm9 0a2 2 0 00-2 2v3a2 2 0 002 2h3a2 2 0 002-2V5a2 2 0 00-2-2h-3zM4 12a2 2 0 00-2 2v1a2 2 0 002 2h12a2 2 0 002-2v-1a2 2 0 00-2-2H4z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Contract &amp; Payment Review</span>
        <span class="nav-badge" data-badge="workflow-management"></span>
      </a>
      <a href="#" class="nav-item" data-page="budget-monitoring">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Budget Monitoring</span>
        <span class="nav-badge" data-badge="budget-monitoring"></span>
      </a>
      <a href="#" class="nav-item" data-page="milestone-overview">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Milestone Overview</span>
        <span class="nav-badge" data-badge="milestone-overview"></span>
      </a>
      <a href="#" class="nav-item" data-page="calendar-schedule">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zM4 8h12v8H4V8z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Calendar &amp; Schedule</span>
      </a>
      <a href="#" class="nav-item" data-page="gis-map">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 103 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 002.273 1.765 11.842 11.842 0 00.976.544l.062.029.018.008.006.003zM10 11.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">GIS Map</span>
      </a>
      <a href="#" class="nav-item" data-page="reports">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Reports</span>
      </a>
      <a href="#" class="nav-item" data-page="ai-risk-insights">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 01.967.744L11.98 6.9a3 3 0 002.12 2.12l4.156 1.013a1 1 0 010 1.934l-4.156 1.013a3 3 0 00-2.12 2.12l-1.013 4.156a1 1 0 01-1.934 0l-1.013-4.156a3 3 0 00-2.12-2.12L1.744 11.9a1 1 0 010-1.934L5.9 8.953a3 3 0 002.12-2.12L9.033 2.744A1 1 0 0110 2z"/></svg></span>
        <span class="nav-label">AI Project Assistant</span>
        <span class="nav-badge" data-badge="ai-risk-insights"></span>
      </a>
      <a href="#" class="nav-item" data-page="announcements">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.816L4.766 14H3a1 1 0 01-1-1V7a1 1 0 011-1h1.766l3.617-2.816a1 1 0 011-.108zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 11-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.983 5.983 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.984 3.984 0 00-1.172-2.828 1 1 0 010-1.415z"/></svg></span>
        <span class="nav-label">Announcements</span>
      </a>
      <a href="#" class="nav-item" data-page="citizen-feedback">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Citizen Feedback Review</span>
        <span class="nav-badge" data-badge="citizen-feedback"></span>
      </a>
      <a href="#" class="nav-item" data-page="project-gallery">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Project Gallery</span>
      </a>
      <a href="#" class="nav-item" data-page="citizen-ratings">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.914c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></span>
        <span class="nav-label">Citizen Ratings</span>
      </a>
      <a href="#" class="nav-item" data-page="staff-requests">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg></span>
        <span class="nav-label">Staff Requests</span>
        <span class="nav-badge" data-badge="staff-requests"></span>
      </a>

      <div class="nav-group">
        <p class="nav-group-label">Performance</p>
        <a href="#" class="nav-item" data-page="contractor-performance">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h14a1 1 0 100-2H4V4a1 1 0 00-1-1z"/><path d="M15.707 6.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0L8 9.414l-2.293 2.293a1 1 0 01-1.414-1.414l3-3a1 1 0 011.414 0L10 9.586l3.293-3.293a1 1 0 011.414 0z"/></svg></span>
          <span class="nav-label">Contractor Performance</span>
        </a>
        <a href="#" class="nav-item" data-page="engineer-performance">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a5 5 0 00-5 5v1.083A3.001 3.001 0 003 11v3a1 1 0 001 1h.083A5.002 5.002 0 009 18.917V19a1 1 0 102 0v-.083A5.002 5.002 0 0015.917 15H16a1 1 0 001-1v-3a3.001 3.001 0 00-2-2.917V7a5 5 0 00-5-5zm3 6.083V7a3 3 0 10-6 0v1.083A5.007 5.007 0 018.083 9h3.834A5.007 5.007 0 0113 8.083z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Engineer Performance</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Archive</p>
        <a href="#" class="nav-item" data-page="completed-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Completed Projects</span>
          <span class="nav-badge" data-badge="completed-projects"></span>
        </a>
        <a href="#" class="nav-item" data-page="cancelled-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.293-2.707a1 1 0 00-1.414-1.414L10 7.172 8.707 5.879a1 1 0 00-1.414 1.414L8.586 8.586 7.293 9.879a1 1 0 101.414 1.414L10 10l1.293 1.293a1 1 0 001.414-1.414l-1.293-1.293 1.293-1.293z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Cancelled Projects</span>
          <span class="nav-badge" data-badge="cancelled-projects"></span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">System Integrations</p>
        <a href="#" class="nav-item" data-page="public-facilities-integration">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 16V4a1 1 0 011-1h5a1 1 0 011 1v3h4a1 1 0 011 1v8h1a1 1 0 110 2H3a1 1 0 110-2h1zm3-10a1 1 0 000 2h1a1 1 0 100-2H7zm0 4a1 1 0 000 2h1a1 1 0 100-2H7zm5 2a1 1 0 011-1h1a1 1 0 110 2h-1a1 1 0 01-1-1zm1 3a1 1 0 100 2h1a1 1 0 100-2h-1zM7 12a1 1 0 100 2h1a1 1 0 100-2H7z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Public Facilities Integration</span>
        </a>
      </div>
    </nav>
  </aside>
