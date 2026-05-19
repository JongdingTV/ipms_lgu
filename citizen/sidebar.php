<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-badge">
            <img src="<?= htmlspecialchars(appUrl('/assets/img/ipms-icon.png')) ?>" alt="" aria-hidden="true">
        </div>
        <div class="logo-text">
            <span class="logo-title">IPMS Citizen</span>
            <span class="logo-sub">Public Portal</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-page="dashboard">
            <span class="nav-icon">📊</span>
            Dashboard
        </a>
        <a href="#" class="nav-item" data-page="projects">
            <span class="nav-icon">📋</span>
            Public Projects
        </a>
        <a href="#" class="nav-item" data-page="project-status">
            <span class="nav-icon">📈</span>
            Project Status
        </a>
        <a href="#" class="nav-item" data-page="submit-feedback">
            <span class="nav-icon">💬</span>
            Submit Feedback
        </a>
        <a href="#" class="nav-item" data-page="track-feedback">
            <span class="nav-icon">📍</span>
            Track Complaints
        </a>
        <a href="#" class="nav-item" data-page="transparency">
            <span class="nav-icon">👁️</span>
            Transparency Dashboard
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= htmlspecialchars(appUrl('/auth/logout.php')) ?>" class="btn-logout">
            <span>🚪</span> Logout
        </a>
    </div>
</aside>
