document.addEventListener('DOMContentLoaded', () => {
  const emailInput = document.getElementById('loginEmail');
  const passwordInput = document.getElementById('loginPassword');
  const passwordToggle = document.getElementById('passwordToggle');
  const passwordIcon = document.getElementById('passwordIcon');
  const loginForm = document.getElementById('loginForm');
  const loginButton = document.getElementById('loginBtn');
  const accountStatusModal = document.querySelector('[data-account-status-modal]');
  const cooldown = document.querySelector('[data-login-cooldown]');
  const cooldownLabel = document.querySelector('[data-login-cooldown-label]');
  if (cooldown && cooldownLabel) {
    let remaining = Number(cooldown.dataset.loginCooldown) || 0;
    const renderCooldown = () => {
      cooldownLabel.textContent = String(Math.max(0, remaining));
      if (loginButton) loginButton.disabled = remaining > 0;
      if (remaining <= 0) {
        cooldown.remove();
        window.clearInterval(timer);
      }
    };
    const timer = window.setInterval(() => {
      remaining -= 1;
      renderCooldown();
    }, 1000);
    renderCooldown();
  }

  const accountStatusClose = document.querySelector('[data-account-status-close]');

  const closeAccountStatusModal = () => {
    if (!accountStatusModal) return;
    accountStatusModal.remove();
    emailInput?.focus();
  };

  accountStatusClose?.addEventListener('click', closeAccountStatusModal);

  accountStatusModal?.addEventListener('click', (event) => {
    if (event.target === accountStatusModal) {
      closeAccountStatusModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && accountStatusModal?.isConnected) {
      closeAccountStatusModal();
    }
  });

  const clearFieldError = (input) => {
    if (!input) return;

    const formGroup = input.closest('.form-group');
    const inputBox = input.closest('.input-box');
    const clientError = formGroup?.querySelectorAll('.field-error');

    inputBox?.classList.remove('input-error');
    input.removeAttribute('aria-invalid');
    clientError?.forEach((element) => element.remove());
  };

  const setFieldError = (input, message) => {
    if (!input) return;

    const formGroup = input.closest('.form-group');
    const inputBox = input.closest('.input-box');

    if (!formGroup || !inputBox) return;

    clearFieldError(input);
    inputBox.classList.add('input-error');
    input.setAttribute('aria-invalid', 'true');

    // Trigger subtle tactile shake animation
    inputBox.classList.remove('input-shake');
    void inputBox.offsetWidth;
    inputBox.classList.add('input-shake');

    const error = document.createElement('span');
    error.className = 'field-error';
    error.dataset.clientError = 'true';
    error.setAttribute('role', 'alert');
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-circle-exclamation';
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = message;
    error.append(icon, text);
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

    if (!loginButton || loginButton.classList.contains('is-loading')) {
      event.preventDefault();
      return;
    }

    // Activate the spinner component and loading state
    loginButton.classList.add('is-loading');
    loginButton.setAttribute('aria-busy', 'true');

    const textSpan = loginButton.querySelector('.btn-text');
    if (textSpan) {
      textSpan.textContent = 'Signing in...';
    }

    // Allow form submission to start before disabling to prevent browser abort
    setTimeout(() => {
      loginButton.disabled = true;
    }, 20);
  });
});

/* Installation is optional; authentication must work independently of PWA support. */
let fromsInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  fromsInstallPrompt = event;
});

window.addEventListener('appinstalled', () => {
  fromsInstallPrompt = null;
  document.getElementById('pwaInstallButton')?.setAttribute('hidden', '');
  document.getElementById('pwaInstallHelp')?.close();
});

document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('pwaInstallButton');
  const dialog = document.getElementById('pwaInstallHelp');
  const confirm = document.getElementById('pwaInstallConfirm');
  const status = document.getElementById('pwaInstallStatus');
  const steps = document.getElementById('pwaInstallSteps');
  const list = document.getElementById('pwaInstallStepList');
  if (!button || !dialog || !confirm || !status || !steps || !list) return;

  const installed = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

  if (installed()) {
    button.hidden = true;
    return;
  }

  if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      // Login and install instructions remain usable if registration fails.
    });
  }

  const isAppleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  const showSteps = (items) => {
    list.replaceChildren();
    items.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });
    steps.hidden = false;
  };

  const updateGuide = () => {
    status.textContent = '';
    confirm.hidden = true;
    steps.hidden = true;

    if (!window.isSecureContext) {
      status.textContent = 'Installation requires HTTPS or localhost. Open the secure GCT website and try again.';
      return;
    }

    if (isAppleMobile) {
      showSteps([
        'Open this website in Safari.',
        'Tap the Share button in the browser toolbar.',
        'Choose Add to Home Screen, then tap Add.',
      ]);
      return;
    }

    if (fromsInstallPrompt) {
      confirm.hidden = false;
      return;
    }

    showSteps([
      'Open your browser menu or look for the Install icon beside the address bar.',
      'Select Install app or Add to Home Screen if your browser offers it.',
      'Confirm the installation to open FROMS as a separate app.',
    ]);
  };

  dialog.querySelectorAll('[data-pwa-install-close]').forEach((close) => {
    close.addEventListener('click', () => dialog.close());
  });

  button.addEventListener('click', () => {
    if (installed()) {
      button.hidden = true;
      return;
    }
    updateGuide();
    dialog.showModal();
  });

  confirm.addEventListener('click', async () => {
    if (!fromsInstallPrompt) {
      updateGuide();
      return;
    }
    const prompt = fromsInstallPrompt;
    fromsInstallPrompt = null;
    confirm.disabled = true;

    try {
      await prompt.prompt();
      const choice = await prompt.userChoice;
      if (choice?.outcome === 'accepted') {
        status.textContent = 'Installation confirmed. You can open FROMS from your device once it is installed.';
        dialog.close();
      } else {
        status.textContent = 'Installation was cancelled. You can install FROMS later using your browser menu.';
        showSteps([
          'Open the browser menu or the Install icon beside the address bar.',
          'Select Install app and confirm when you are ready.',
        ]);
      }
    } catch {
      status.textContent = 'The browser could not open installation right now. Use your browser menu to install instead.';
      showSteps([
        'Open the browser menu or the Install icon beside the address bar.',
        'Select Install app when it becomes available.',
      ]);
    } finally {
      confirm.disabled = false;
      confirm.hidden = true;
    }
  });
});
