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
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
            </span>
            <span class="nav-label">Dashboard</span>
            <span class="nav-badge" data-badge="dashboard"></span>
        </a>
        <a href="#" class="nav-item" data-page="announcements">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.816L4.766 14H3a1 1 0 01-1-1V7a1 1 0 011-1h1.766l3.617-2.816a1 1 0 011-.108zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 11-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.983 5.983 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.984 3.984 0 00-1.172-2.828 1 1 0 010-1.415z"/></svg>
            </span>
            <span class="nav-label">Announcements</span>
        </a>
        <a href="#" class="nav-item" data-page="projects">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
            </span>
            <span class="nav-label">Public Projects</span>
        </a>
        <a href="#" class="nav-item" data-page="submit-feedback">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
            </span>
            <span class="nav-label">Submit Feedback</span>
        </a>
        <a href="#" class="nav-item" data-page="track-feedback">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            </span>
            <span class="nav-label">Track Complaints</span>
            <span class="nav-badge" data-badge="track-feedback"></span>
        </a>
        <a href="#" class="nav-item" data-page="transparency">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
            </span>
            <span class="nav-label">Transparency Dashboard</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= htmlspecialchars(appUrl('/auth/logout.php')) ?>" class="btn-logout">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
            </span>
            Logout
        </a>
    </div>
</aside>
