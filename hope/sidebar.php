  <aside class="sidebar hope-sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
      </div>
      <div class="logo-text">
        <span class="logo-title">LGU Infrastructure</span>
        <span class="logo-sub">HOPE Portal</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="#" class="nav-item active" data-page="dashboard">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg></span>
        Dashboard
      </a>

      <div class="nav-group">
        <p class="nav-group-label">Approvals</p>
        <a href="#" class="nav-item" data-page="project-approvals">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>
          Project Approvals
        </a>
        <a href="#" class="nav-item" data-page="award-approvals">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM9 12l4.243-4.243-1.415-1.414L9 9.172 7.172 7.343 5.757 8.757 9 12z" clip-rule="evenodd"/></svg></span>
          Contract Award Approvals
        </a>
        <a href="#" class="nav-item" data-page="returned-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 3.293a1 1 0 010 1.414L7.414 7H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>
          Returned Projects
        </a>
        <a href="#" class="nav-item" data-page="decision-history">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg></span>
          Decision History
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Projects</p>
        <a href="#" class="nav-item" data-page="approved-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg></span>
          Approved Projects
        </a>
        <a href="#" class="nav-item" data-page="ongoing-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>
          Ongoing Projects
        </a>
        <a href="#" class="nav-item" data-page="completed-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg></span>
          Completed Projects
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Reports</p>
        <a href="#" class="nav-item" data-page="executive-reports">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6zm1 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 100 2h4a1 1 0 100-2H8z" clip-rule="evenodd"/></svg></span>
          Executive Reports
        </a>
        <a href="#" class="nav-item" data-page="budget-summary">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.51-1.31c-.562-.649-1.413-1.076-2.353-1.253V5z" clip-rule="evenodd"/></svg></span>
          Budget Summary
        </a>
        <a href="#" class="nav-item" data-page="procurement-summary">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V4z" clip-rule="evenodd"/></svg></span>
          Procurement Summary
        </a>
      </div>

      <a href="#" class="nav-item" data-page="notifications">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM8.05 16a2 2 0 003.9 0h-3.9z"/></svg></span>
        Notifications
      </a>
      <a href="#" class="nav-item" data-page="profile">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg></span>
        Profile
      </a>
    </nav>
  </aside>
