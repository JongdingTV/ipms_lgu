  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
      </div>
      <div class="logo-text">
        <span class="logo-title">LGU Infrastructure</span>
        <span class="logo-sub">Contractor Portal</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="#" class="nav-item active" data-page="dashboard">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg></span>
        <span class="nav-label">Dashboard</span>
        <span class="nav-badge" data-badge="dashboard"></span>
      </a>

      <div class="nav-group">
        <p class="nav-group-label">Accreditation</p>
        <a href="#" class="nav-item" data-page="company-profile">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v14l-6-3-6 3V4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Company Profile</span>
        </a>
        <a href="#" class="nav-item" data-page="accreditation-status">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Accreditation Status</span>
        </a>
        <a href="#" class="nav-item" data-page="accreditation-documents">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a3 3 0 016 0v7a5 5 0 01-10 0V7a1 1 0 112 0v4a3 3 0 106 0V4a1 1 0 10-2 0v7a1 1 0 11-2 0V4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Accreditation Documents</span>
          <span class="nav-badge" data-badge="accreditation-documents"></span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Procurement</p>
        <a href="#" class="nav-item" data-page="open-biddings">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Available Bidding Projects</span>
          <span class="nav-badge" data-badge="open-biddings"></span>
        </a>
        <a href="#" class="nav-item" data-page="my-bids">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">My Submitted Bids</span>
        </a>
        <a href="#" class="nav-item" data-page="bid-results">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-3.815 10.631C4.83 14.164 4 16.09 4 18h12c0-1.91-.83-3.836-2.185-5.369A6 6 0 0010 2zm0 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Bid Results</span>
          <span class="nav-badge" data-badge="bid-results"></span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Projects</p>
        <a href="#" class="nav-item" data-page="assigned-projects">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg></span>
          <span class="nav-label">Assigned Projects</span>
          <span class="nav-badge" data-badge="assigned-projects"></span>
        </a>
        <a href="#" class="nav-item" data-page="project-timeline">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 6a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Project Timeline</span>
        </a>
        <a href="#" class="nav-item" data-page="contract-details">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 012-2h5.586A2 2 0 0113 1.586L16.414 5A2 2 0 0117 6.414V17a2 2 0 01-2 2H6a2 2 0 01-2-2V3zm5 7a1 1 0 011-1h3a1 1 0 110 2h-3a1 1 0 01-1-1zm-2 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Contract Details</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Project Execution</p>
        <a href="#" class="nav-item" data-page="progress-updates">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Progress Updates</span>
        </a>
        <a href="#" class="nav-item" data-page="accomplishment-report">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0013.414 6L10 2.586A2 2 0 008.586 2H6zm1 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 100 2h4a1 1 0 100-2H8z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Accomplishment Reports</span>
        </a>
        <a href="#" class="nav-item" data-page="site-photos">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Site Photos</span>
        </a>
        <a href="#" class="nav-item" data-page="supporting-documents">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a3 3 0 016 0v7a5 5 0 01-10 0V7a1 1 0 112 0v4a3 3 0 106 0V4a1 1 0 10-2 0v7a1 1 0 11-2 0V4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Supporting Documents</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Payments</p>
        <a href="#" class="nav-item" data-page="payment-requests">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.51-1.31c-.562-.649-1.413-1.076-2.353-1.253V5z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Payment Requests</span>
        </a>
        <a href="#" class="nav-item" data-page="payment-status">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Payment Status</span>
          <span class="nav-badge" data-badge="payment-status"></span>
        </a>
        <a href="#" class="nav-item" data-page="payment-history">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm7 1v3a1 1 0 001 1h3l-4-4z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Payment History</span>
        </a>
      </div>

      <div class="nav-group">
        <p class="nav-group-label">Performance</p>
        <a href="#" class="nav-item" data-page="performance-rating">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.454 1.405 1.02L10 15.591l4.069 2.485c.713.434 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Performance Rating</span>
        </a>
        <a href="#" class="nav-item" data-page="compliance-records">
          <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"/></svg></span>
          <span class="nav-label">Compliance Records</span>
        </a>
      </div>

      <a href="#" class="nav-item" data-page="notifications">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM8.05 16a2 2 0 003.9 0h-3.9z"/></svg></span>
        <span class="nav-label">Notifications</span>
      </a>
      <a href="#" class="nav-item" data-page="profile">
        <span class="nav-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg></span>
        <span class="nav-label">Profile</span>
      </a>
    </nav>
  </aside>
