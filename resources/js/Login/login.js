document.addEventListener('DOMContentLoaded', () => {
  const passwordInput = document.getElementById('loginPassword');
  const passwordToggle = document.getElementById('passwordToggle');
  const passwordIcon = document.getElementById('passwordIcon');
  const loginForm = document.getElementById('loginForm');
  const loginButton = document.getElementById('loginBtn');

  passwordToggle?.addEventListener('click', () => {
    if (!passwordInput || !passwordIcon) return;

    const isVisible = passwordInput.type === 'text';
    passwordInput.type = isVisible ? 'password' : 'text';

    passwordToggle.setAttribute('aria-pressed', String(!isVisible));
    passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');

    passwordIcon.classList.toggle('fa-eye', isVisible);
    passwordIcon.classList.toggle('fa-eye-slash', !isVisible);

    passwordInput.focus();
  });

  loginForm?.addEventListener('submit', (event) => {
    if (!loginForm.checkValidity()) {
      event.preventDefault();
      loginForm.reportValidity();
      return;
    }

    if (!loginButton || loginButton.disabled) return;

    loginButton.disabled = true;
    loginButton.setAttribute('aria-busy', 'true');
    loginButton.innerHTML = `
      <span>Signing In</span>
      <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
    `;
  });
});
