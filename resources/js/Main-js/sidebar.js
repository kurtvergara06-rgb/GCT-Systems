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