document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function () {
      const dropdown = this.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });
});