document.addEventListener('DOMContentLoaded', function () {
  const body = document.body;
  const sidebar = document.getElementById('appSidebar');
  const collapseBtn = document.getElementById('sidebarCollapseBtn');

  /*
   * Sidebar collapse / expand
   * Sidebar will only open/close when clicking the arrow button.
   */
  function setSidebarCollapsed(isCollapsed) {
  if (!sidebar) return;

  document.documentElement.classList.toggle('sidebar-start-collapsed', isCollapsed);

    sidebar.classList.toggle('collapsed', isCollapsed);
    body.classList.toggle('sidebar-collapsed', isCollapsed);

    localStorage.setItem('gctSidebarCollapsed', isCollapsed ? '1' : '0');
  }

  const savedState = localStorage.getItem('gctSidebarCollapsed');

  if (savedState === '1') {
    setSidebarCollapsed(true);
  }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      const isCollapsed = sidebar.classList.contains('collapsed');
      setSidebarCollapsed(!isCollapsed);
    });
  }

  /*
   * Sidebar menu dropdown
   * This will NOT auto-open the sidebar when collapsed.
   */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.dropdown-toggle');

    if (!button) return;

    event.preventDefault();

    const dropdown = button.closest('.menu-dropdown');

    if (!dropdown) return;

    dropdown.classList.toggle('open');

    button.setAttribute(
      'aria-expanded',
      dropdown.classList.contains('open') ? 'true' : 'false'
    );
  });

  /*
   * Sidebar profile popup
   */
  const profileToggle = document.getElementById('sidebarProfileToggle');
  const profileMenu = document.getElementById('sidebarProfileMenu');

  function closeProfileMenu() {
    if (!profileToggle || !profileMenu) return;

    profileMenu.classList.remove('show');
    profileToggle.classList.remove('active');
    profileToggle.setAttribute('aria-expanded', 'false');
  }

  function toggleProfileMenu() {
    if (!profileToggle || !profileMenu) return;

    const isOpen = profileMenu.classList.contains('show');

    if (isOpen) {
      closeProfileMenu();
      return;
    }

    profileMenu.classList.add('show');
    profileToggle.classList.add('active');
    profileToggle.setAttribute('aria-expanded', 'true');
  }

  if (profileToggle && profileMenu) {
    profileToggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      toggleProfileMenu();
    });

    profileMenu.addEventListener('click', function (event) {
      event.stopPropagation();
    });

    document.addEventListener('click', function () {
      closeProfileMenu();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeProfileMenu();
      }
    });
  }
});