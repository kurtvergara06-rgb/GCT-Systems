document.addEventListener('DOMContentLoaded', function () {
  /*
   * Sidebar menu dropdown
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