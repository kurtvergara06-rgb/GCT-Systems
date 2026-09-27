<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>GCT Transport Services | Sign In</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  >

  @vite([
    'resources/css/Main-styles/theme.css',
    'resources/css/Login/login.css',
    'resources/js/Login/login.js',
  ])

</head>

<body>
  <main class="gct-login-page">
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

          @if ($errors->any())
            <div class="login-alert" role="alert">
              <i class="fa-solid fa-circle-exclamation"></i>
              <div>
                <strong>Unable to sign in</strong>
                <span>{{ $errors->first() }}</span>
              </div>
            </div>
          @endif

          @if (session('status'))
            <div class="login-alert login-alert-success" role="status">
              <i class="fa-solid fa-circle-check"></i>
              <span>{{ session('status') }}</span>
            </div>
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
              @error('email')
                <span class="field-error">{{ $message }}</span>
              @enderror
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
              @error('password')
                <span class="field-error">{{ $message }}</span>
              @enderror
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

              <a href="#" class="forgot-link">Forgot password?</a>
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

  <script>
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>
</body>
</html>
