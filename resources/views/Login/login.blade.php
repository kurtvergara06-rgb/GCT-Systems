<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>GCT Transport Services | Sign In</title>
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="theme-color" content="#062d61">
  <link rel="icon" type="image/svg+xml" href="{{ asset('pwa-icon.svg') }}">
  <link rel="apple-touch-icon" href="{{ asset('img/gct_logo.png') }}">

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  >

  @vite([
    'resources/css/Main-styles/theme.css',
    'resources/css/Login/login.css',
    'resources/js/Login/legal-modal.js',
    'resources/js/Login/login.js',
  ])

  {{-- Keep the sign-in entrance in the login document itself.
       A stale Vite build must not silently remove the entrance animation. --}}
  <style id="gct-login-entrance-v4">
    @keyframes gct-login-entrance-brand-v4 {
      from { opacity: 0; transform: translateX(-65px); }
      to { opacity: 1; transform: translateX(0); }
    }

    @keyframes gct-login-entrance-card-v4 {
      from { opacity: 0; transform: translateY(90px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: no-preference) {
      .gct-login-page--signin .brand-header {
        animation: gct-login-entrance-brand-v4 2.5s ease-out 0.15s both;
      }
      .gct-login-page--signin .brand-copy {
        animation: gct-login-entrance-brand-v4 2.7s ease-out 0.35s both;
      }
      .gct-login-page--signin .login-card {
        animation: gct-login-entrance-card-v4 3s ease-out 0.45s both;
      }
      .gct-login-page--signin .login-footer {
        animation: gct-login-entrance-card-v4 2.5s ease-out 0.8s both;
      }
    }

    /* Reduced-motion users receive a brief opacity-only entrance.
       The stronger slide animations remain opt-in via OS motion preferences. */
    @keyframes gct-login-entrance-fade-v4 {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @media (prefers-reduced-motion: reduce) {
      .gct-login-page--signin .brand-header,
      .gct-login-page--signin .brand-copy,
      .gct-login-page--signin .login-card,
      .gct-login-page--signin .login-footer {
        animation-name: gct-login-entrance-fade-v4 !important;
        animation-duration: 350ms !important;
        animation-delay: 0ms !important;
        animation-timing-function: ease-out !important;
        animation-iteration-count: 1 !important;
        animation-fill-mode: both !important;
        transform: none;
      }
    }
  </style>

</head>

<body>
  <main class="gct-login-page gct-login-page--signin">
    <section
      class="gct-brand-panel"
      aria-label="GCT Transport Services information"
      style="--gct-hero-image: url('{{ asset('img/BUS-GCT.png') }}');"
    >
      <div class="brand-content">
        <header class="brand-header">
          <img
            src="{{ asset('img/gct_logo.png') }}"
            alt="GCT Transport Services logo"
            class="company-logo"
          >

          <p class="brand-name">GCT Transport<br>Services, Inc.</p>
        </header>

                <div class="brand-copy">
          <p class="brand-kicker">People moving possibilities</p>
          <h1>Keeping your operations <span>moving.</span></h1>
          <p class="brand-services">Fleet <b>•</b> Maintenance <b>•</b> Inventory <b>•</b> Dispatch</p>
        </div>

        <div class="brand-bottomline">
          <p class="brand-footer">Safer journeys<br>Stronger tomorrows</p>
          <p class="brand-nav">People <span>|</span> Places <span>|</span> Progress</p>
        </div>
      </div>
    </section>

    <section class="gct-form-panel">
      <div class="login-shell">
        <div class="login-card">
          <div class="login-heading">
            <h2>Welcome back</h2>
            <p>Sign in to continue to the GCT fleet operations platform.</p>
          </div>

          @if (session('status'))
            <p class="login-alert login-alert-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>{{ session('status') }}</span></p>
          @endif

          @if(session('login_lockout_seconds'))
            <p class="login-cooldown" role="status" data-login-cooldown="{{ (int) session('login_lockout_seconds') }}">
              Too many failed attempts. Try again in <strong data-login-cooldown-label>{{ (int) session('login_lockout_seconds') }}</strong> seconds.
            </p>
          @endif

          <form id="loginForm" method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf

            <div class="form-group">
              <label for="loginEmail">Email Address</label>
              <div class="input-box @error('email') input-error @enderror">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <input
                  type="email"
                  id="loginEmail"
                  name="email"
                  value="{{ old('email') }}"
                  placeholder="you@company.com"
                  required
                  autocomplete="email"
                  autofocus
                  aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                >
              </div>
              @if ($errors->has('email') || session('error') || ($errors->any() && !$errors->has('password')))
                <span class="field-error" id="loginEmailError" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>{{ $errors->has('email') ? $errors->first('email') : (session('error') ?: $errors->first()) }}</span></span>
              @endif
            </div>

            <div class="form-group">
              <label for="loginPassword">Password</label>
              <div class="input-box @error('password') input-error @enderror">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input
                  type="password"
                  id="loginPassword"
                  name="password"
                  placeholder="Enter your password"
                  required
                  autocomplete="current-password"
                  aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                >
                <button
                  type="button"
                  class="toggle-password"
                  id="passwordToggle"
                  aria-label="Show password"
                  aria-pressed="false"
                >
                  <i class="fa-regular fa-eye" id="passwordIcon" aria-hidden="true"></i>
                </button>
              </div>
              @if ($errors->has('password'))
                <span class="field-error" id="loginPasswordError" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>{{ $errors->first('password') }}</span></span>
              @endif
            </div>

            <div class="login-options">
              <label class="remember-control" for="rememberLogin">
                <input
                  type="checkbox"
                  id="rememberLogin"
                  name="remember"
                  value="1"
                  @checked(old('remember'))
                >
                <span>Remember me</span>
              </label>

              <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
              <span class="btn-text">Sign In</span>
              <i class="fa-solid fa-arrow-right btn-icon" aria-hidden="true"></i>
              <span class="btn-spinner" aria-hidden="true">
                <svg class="spinner-svg" viewBox="0 0 24 24" fill="none">
                  <circle class="spinner-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                  <circle class="spinner-fill" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                </svg>
              </span>
            </button>
          </form>

          <div class="google-auth-separator"><span>or</span></div>
          <a class="google-auth-button" href="{{ route('auth.google') }}">
            <svg width="19" height="19" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="#4285F4" d="M21.35 12.25c0-.75-.07-1.48-.2-2.18H12v4.13h5.23a4.47 4.47 0 0 1-1.94 2.93v2.43h3.13c1.83-1.69 2.93-4.17 2.93-7.31Z"/>
              <path fill="#34A853" d="M12 21.5c2.62 0 4.82-.87 6.42-2.34l-3.13-2.43c-.87.58-1.98.93-3.29.93-2.53 0-4.68-1.71-5.44-4.01H3.33v2.51A9.7 9.7 0 0 0 12 21.5Z"/>
              <path fill="#FBBC05" d="M6.56 13.65a5.8 5.8 0 0 1 0-3.3V7.84H3.33a9.7 9.7 0 0 0 0 8.32l3.23-2.51Z"/>
              <path fill="#EA4335" d="M12 6.34c1.42 0 2.69.49 3.69 1.45l2.77-2.77A9.3 9.3 0 0 0 12 2.5a9.7 9.7 0 0 0-8.67 5.34l3.23 2.51c.76-2.3 2.91-4.01 5.44-4.01Z"/>
            </svg>
            Continue with Google
          </a>
          <div class="auth-switch auth-switch--install">
            <span>New employee? <a href="{{ route('register') }}">Sign Up</a></span>
            <button class="pwa-install-link" type="button" id="pwaInstallButton" aria-haspopup="dialog" aria-controls="pwaInstallHelp"><i class="fa-solid fa-download" aria-hidden="true"></i> Install App</button>
          </div>

          <nav class="auth-legal-links" aria-label="Legal information"><a data-legal-modal="Privacy Notice" href="{{ route('legal.privacy') }}">Privacy Notice</a><span aria-hidden="true">·</span><a data-legal-modal="Terms of Use" href="{{ route('legal.terms') }}">Terms of Use</a></nav>

          <div class="trust-divider" aria-hidden="true">
            <span></span>
            <p>Moving a more connected Philippines</p>
            <span></span>
          </div>
        </div>

        <footer class="login-footer">
          <p>&copy; {{ date('Y') }} GCT Transport Services, Inc. All rights reserved.</p>
        </footer>
      </div>
    </section>
  </main>

  @php
    $blockedAccountStatus = strtolower(trim((string) session('blocked_account_status')));
    $blockedAccountTitle = match ($blockedAccountStatus) {
        'inactive' => 'Account Deactivated',
        'pending' => 'Account Pending Activation',
        default => 'Account Unavailable',
    };
    $blockedAccountMessage = match ($blockedAccountStatus) {
        'inactive' => 'This account has been deactivated and cannot sign in. Please contact your system administrator if you need the account reactivated.',
        'pending' => 'This account is still pending activation. Please contact your system administrator before trying to sign in again.',
        default => 'This account is not currently allowed to sign in. Please contact your system administrator.',
    };
  @endphp

  @if ($blockedAccountStatus !== '')
    <div
      class="account-status-modal is-open"
      id="accountStatusModal"
      data-account-status-modal
      role="presentation"
    >
      <div
        class="account-status-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="accountStatusTitle"
        aria-describedby="accountStatusMessage"
      >
        <div class="account-status-icon" aria-hidden="true">
          <i class="fa-solid fa-user-lock"></i>
        </div>

        <h2 id="accountStatusTitle">{{ $blockedAccountTitle }}</h2>
        <p id="accountStatusMessage">{{ $blockedAccountMessage }}</p>

        <button
          type="button"
          class="account-status-confirm"
          data-account-status-close
          autofocus
        >
          OK
        </button>
      </div>
    </div>
  @endif

  <dialog id="pwaInstallHelp" class="pwa-install-dialog" aria-labelledby="pwaInstallTitle" aria-describedby="pwaInstallInstructions">
    <div class="pwa-install-dialog-heading">
      <span class="pwa-install-dialog-eyebrow"><i class="fa-solid fa-desktop" aria-hidden="true"></i> GCT Workspace</span>
      <button type="button" class="pwa-install-dialog-close" data-pwa-install-close aria-label="Close installation guide"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="pwa-install-app-identity">
      <span class="pwa-install-app-icon"><img src="{{ asset('img/gct_logo.png') }}" alt="" width="56" height="56"></span>
      <div>
        <h2 id="pwaInstallTitle">FROMS, one click away.</h2>
        <p>Fleet Resource Optimization and Management System</p>
      </div>
    </div>
    <p class="pwa-install-lead" id="pwaInstallInstructions">Add FROMS to your device for quick access in its own dedicated window.</p>
    <div class="pwa-install-benefits" aria-label="Installation benefits">
      <div><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Open directly from your desktop or home screen</span></div>
      <div><i class="fa-solid fa-window-maximize" aria-hidden="true"></i><span>Enjoy a focused, app-like workspace</span></div>
      <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Keep your existing secure GCT sign-in</span></div>
    </div>
    <div class="pwa-install-steps" id="pwaInstallSteps" hidden>
      <p class="pwa-install-steps-title">How to install</p>
      <ol id="pwaInstallStepList"></ol>
    </div>
    <p class="pwa-install-status" id="pwaInstallStatus" role="status" aria-live="polite"></p>
    <div class="pwa-install-actions">
      <button type="button" class="pwa-install-dialog-secondary" data-pwa-install-close>Not now</button>
      <button type="button" class="pwa-install-dialog-primary" id="pwaInstallConfirm"><i class="fa-solid fa-download" aria-hidden="true"></i><span>Continue to Install</span></button>
    </div>
    <p class="pwa-install-note">Installation is optional. An internet connection is still needed for live fleet data.</p>
  </dialog>

  <script>
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>
</body>
</html>
