document.addEventListener('DOMContentLoaded', () => {
  const emailInput = document.getElementById('loginEmail');
  const passwordInput = document.getElementById('loginPassword');
  const passwordToggle = document.getElementById('passwordToggle');
  const passwordIcon = document.getElementById('passwordIcon');
  const loginForm = document.getElementById('loginForm');
  const loginButton = document.getElementById('loginBtn');

  const clearFieldError = (input) => {
    if (!input) return;

    const formGroup = input.closest('.form-group');
    const inputBox = input.closest('.input-box');
    const clientError = formGroup?.querySelector('[data-client-error]');

    inputBox?.classList.remove('input-error');
    input.removeAttribute('aria-invalid');
    clientError?.remove();
  };

  const setFieldError = (input, message) => {
    if (!input) return;

    const formGroup = input.closest('.form-group');
    const inputBox = input.closest('.input-box');

    if (!formGroup || !inputBox) return;

    clearFieldError(input);
    inputBox.classList.add('input-error');
    input.setAttribute('aria-invalid', 'true');

    const error = document.createElement('span');
    error.className = 'field-error';
    error.dataset.clientError = 'true';
    error.textContent = message;
    formGroup.appendChild(error);
  };

  const validateLoginForm = () => {
    let isValid = true;

    clearFieldError(emailInput);
    clearFieldError(passwordInput);

    if (!emailInput?.value.trim()) {
      setFieldError(emailInput, 'Email address is required.');
      isValid = false;
    } else if (!emailInput.validity.valid) {
      setFieldError(emailInput, 'Please enter a valid email address.');
      isValid = false;
    }

    if (!passwordInput?.value) {
      setFieldError(passwordInput, 'Password is required.');
      isValid = false;
    }

    return isValid;
  };

  emailInput?.addEventListener('input', () => clearFieldError(emailInput));
  passwordInput?.addEventListener('input', () => clearFieldError(passwordInput));

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
    if (!validateLoginForm()) {
      event.preventDefault();
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
