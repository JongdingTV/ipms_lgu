<header class="topbar">
  <!-- Left: Menu & Search -->
  <div class="topbar-left">
    <button class="menu-toggle" id="sidebarToggle" title="Toggle sidebar">
      <svg viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
      </svg>
    </button>

    <div class="search-bar">
      <svg viewBox="0 0 20 20" fill="currentColor" class="search-icon">
        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
      </svg>
      <input type="text" id="searchInput" placeholder="Search projects, contractors...">
    </div>
  </div>

  <!-- Right: Notifications, User -->
  <div class="topbar-right">
    <!-- Notifications -->
    <button class="icon-btn" id="notifBtn" title="Notifications">
      <svg viewBox="0 0 20 20" fill="currentColor">
        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
      </svg>
      <span class="notif-badge">0</span>
    </button>

    <!-- User Profile + Menu -->
    <div class="user-section">
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
        <div class="user-role"><?= ucfirst(htmlspecialchars($_SESSION['role'])) ?></div>
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
              <div class="user-menu-role"><?= ucfirst(htmlspecialchars($_SESSION['role'])) ?></div>
              <div class="user-menu-email"><?= htmlspecialchars($_SESSION['email']) ?></div>
            </div>
          </div>
          <div class="user-menu-divider"></div>
          <a href="#" class="user-menu-item" onclick="showProfileSettings()">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
            </svg>
            <span>Profile Settings</span>
          </a>
          <a href="#" class="user-menu-item" onclick="showChangePassword()">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <span>Change Password</span>
          </a>
          <div class="user-menu-divider"></div>
          <a href="<?= $BASE_PATH ?>includes/logout.php" class="user-menu-item user-menu-logout">
            <svg viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
            </svg>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

// Submit profile form (placeholder - implement in your API)
function submitProfileForm(e) {
  e.preventDefault();
  toast('Profile update functionality coming soon', 'info');
  closeModal();
}

// Submit password form (placeholder - implement in your API)
function submitPasswordForm(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  if (formData.get('new_password') !== formData.get('confirm_password')) {
    toast('Passwords do not match', 'error');
    return;
  }
  
  toast('Password change functionality coming soon', 'info');
  closeModal();
}
</script>
