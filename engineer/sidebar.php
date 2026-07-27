  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
      </div>
      <div class="logo-text">
        <span class="logo-title">LGU Infrastructure</span>
        <span class="logo-sub">Engineer Portal</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="#" class="nav-item active" data-page="dashboard">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg></span>
        <span class="nav-label">Dashboard</span>
        <span class="nav-badge" data-badge="dashboard"></span>
      </a>
      <a href="#" class="nav-item" data-page="assigned-projects">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg></span>
        <span class="nav-label">My Assigned Projects</span>
        <span class="nav-badge" data-badge="assigned-projects"></span>
      </a>
      <a href="#" class="nav-item" data-page="engineering-review">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Engineering Review</span>
        <span class="nav-badge" data-badge="engineering-review"></span>
      </a>
      <a href="#" class="nav-item" data-page="milestone-update">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Milestone Update</span>
        <span class="nav-badge" data-badge="milestone-update"></span>
      </a>
      <a href="#" class="nav-item" data-page="inspection-review">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h8.586A2 2 0 0014 16.414l3.414-3.414A2 2 0 0018 11.586V5a2 2 0 00-2-2H4zm4 4a1 1 0 000 2h4a1 1 0 100-2H8zm0 4a1 1 0 100 2h2a1 1 0 100-2H8z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Inspection Review</span>
        <span class="nav-badge" data-badge="inspection-review"></span>
      </a>
      <a href="#" class="nav-item" data-page="payment-review">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM5 12a1 1 0 100 2h2a1 1 0 100-2H5z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Payment Review</span>
        <span class="nav-badge" data-badge="payment-review"></span>
      </a>
      <a href="#" class="nav-item" data-page="progress-photos">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm11 12H5l3.2-4.267a1 1 0 011.6 0L11 12.333l1.2-1.6a1 1 0 011.6 0L15 12.333V15zM6.5 8a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Upload Progress Photos</span>
      </a>
      <a href="#" class="nav-item" data-page="delay-report">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.596c.75 1.334-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.742-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-6a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Delay Report Form</span>
      </a>
      <a href="#" class="nav-item" data-page="issue-reporting">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Issue Reporting</span>
      </a>
      <a href="#" class="nav-item" data-page="status-tracker">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2h1v10a2 2 0 002 2h8a2 2 0 002-2V5h1a1 1 0 100-2H3zm4 5a1 1 0 012 0v5a1 1 0 11-2 0V8zm4-2a1 1 0 012 0v7a1 1 0 11-2 0V6z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Project Status Tracker</span>
        <span class="nav-badge" data-badge="status-tracker"></span>
      </a>

      <!-- External-system integrations (e.g. Urban Planning System) — additive
           section, separate from the native Engineer Portal modules above. -->
      <div class="nav-group">
        <p class="nav-group-label has-icon">
          <span class="nav-group-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.586 4.586a2 2 0 112.828 2.828l-1 1a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l1-1a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-3 5a4 4 0 00-5.656 0l-1 1a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l1-1a2 2 0 012.828 0 1 1 0 001.414-1.414z" clip-rule="evenodd"/></svg></span>
          System Integrations
        </p>
        <a href="#" class="nav-item" data-page="urban-planning-inspection">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.499-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.497-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0A6.004 6.004 0 014.083 11h1.946c.089 1.546.383 2.97.837 4.118z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Urban Planning Inspection</span>
          <span class="nav-badge" data-badge="urban-planning-inspection"></span>
        </a>
        <a href="#" class="nav-item" data-page="road-inspection-history">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 9a1 1 0 011-1h.01a1 1 0 110 2H9a1 1 0 01-1-1zm4 0a1 1 0 011-1h.01a1 1 0 110 2H13a1 1 0 01-1-1zM8 14a1 1 0 011-1h.01a1 1 0 110 2H9a1 1 0 01-1-1zm4 0a1 1 0 011-1h.01a1 1 0 110 2H13a1 1 0 01-1-1z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Road Inspection History</span>
        </a>
      </div>
    </nav>
  </aside>
