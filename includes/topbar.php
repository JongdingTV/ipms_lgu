<header class="topbar">
  <!-- Left: Menu & Search -->
  <div class="topbar-left">
    <button class="menu-toggle" id="sidebarToggle" title="Toggle sidebar">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
      </svg>
    </button>

    <?php if (($_SESSION['role'] ?? '') !== 'citizen'): // citizens use the global "Search everything" instead ?>
    <div class="search-bar">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" class="search-icon">
        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
      </svg>
      <input type="text" id="searchInput" placeholder="<?= htmlspecialchars($topbarSearchPlaceholder ?? 'Search projects, contractors...') ?>">
    </div>
    <?php endif; ?>

    <button class="gsearch-trigger" id="globalSearchBtn" type="button" title="Search everything (Ctrl+K)">
      <svg viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
      </svg>
      <span class="hide-tablet">Search everything</span>
      <kbd>Ctrl K</kbd>
    </button>
  </div>

  <!-- Right: Notifications, User -->
  <div class="topbar-right">
    <!-- Theme toggle -->
    <button class="icon-btn theme-toggle-btn" id="themeToggleBtn" type="button" title="Switch to dark mode" aria-label="Switch to dark mode">
      <svg viewBox="0 0 20 20" fill="currentColor" class="icon-sun">
        <path d="M10 3a1 1 0 011 1v1a1 1 0 11-2 0V4a1 1 0 011-1zm0 12a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm7-5a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zm9.66-5.66a1 1 0 010 1.42l-.7.7a1 1 0 11-1.42-1.42l.7-.7a1 1 0 011.42 0zM6.46 13.54a1 1 0 010 1.42l-.7.7a1 1 0 11-1.42-1.42l.7-.7a1 1 0 011.42 0zm8.5 1.42a1 1 0 01-1.42 0l-.7-.7a1 1 0 111.42-1.42l.7.7a1 1 0 010 1.42zM5.76 5.76a1 1 0 01-1.42 0l-.7-.7A1 1 0 015.06 3.64l.7.7a1 1 0 010 1.42zM10 6a4 4 0 100 8 4 4 0 000-8z"/>
      </svg>
      <svg viewBox="0 0 20 20" fill="currentColor" class="icon-moon">
        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
      </svg>
    </button>

    <!-- Notifications -->
    <button class="icon-btn" id="notifBtn" title="Notifications">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
      </svg>
      <span class="notif-badge">0</span>
    </button>

    <!-- User Profile + Menu -->
    <div class="user-section">
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
        <div class="user-role"><?= htmlspecialchars(roleLabel($_SESSION['role'])) ?></div>
      </div>
      
      <div class="user-avatar-wrapper">
        <button class="user-avatar" id="userMenuBtn" title="User menu">
          <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
        </button>

        <!-- Dropdown Menu -->
        <div class="user-menu" id="userMenu">
          <div class="user-menu-header">
            <div class="user-menu-avatar">
              <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
            <div class="user-menu-info">
              <div class="user-menu-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
              <div class="user-menu-role"><?= htmlspecialchars(roleLabel($_SESSION['role'])) ?></div>
              <div class="user-menu-email"><?= htmlspecialchars($_SESSION['email']) ?></div>
            </div>
          </div>
          <div class="user-menu-divider"></div>
          <a href="#" class="user-menu-item" onclick="showProfileSettings(); return false;">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
            </svg>
            <span>Profile Settings</span>
          </a>
          <a href="#" class="user-menu-item" onclick="showChangePassword(); return false;">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <span>Change Password</span>
          </a>
          <?php if (($_SESSION['role'] ?? '') !== 'citizen'): // citizens log out via the sidebar footer ?>
          <div class="user-menu-divider"></div>
          <a href="<?= $BASE_PATH ?>auth/logout.php" class="user-menu-item user-menu-logout">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
            </svg>
            <span>Logout</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>
