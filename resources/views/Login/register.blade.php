<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>GCT Transport Services | Sign Up</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  >

  @vite([
    'resources/css/Main-styles/theme.css',
    'resources/css/Login/login.css',
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
            <h2>Create an account</h2>
            <p>Register for GCT-Systems. Access begins after email verification and administrator approval.</p>
          </div>

          @if ($errors->any())
            <div class="login-alert" role="alert">
              <i class="fa-solid fa-circle-exclamation"></i>
              <div>
                <strong>Unable to register</strong>
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

          @if (session('error'))
            <div class="login-alert" role="alert">
              <i class="fa-solid fa-circle-exclamation"></i>
              <span>{{ session('error') }}</span>
            </div>
          @endif

<form method="POST" action="{{ route('register.submit') }}">
            @csrf
            <div class="form-group">
              <label for="regName">Full Name</label>
              <div class="input-box"><i class="fa-regular fa-user" aria-hidden="true"></i>
                <input id="regName" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name" placeholder="Your full name">
              </div>
              @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
              <label for="regEmail">Email Address</label>
              <div class="input-box"><i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <input id="regEmail" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="you@company.com">
              </div>
              @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
              <label for="regPassword">Password</label>
              <div class="input-box"><i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input id="regPassword" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
              </div>
              @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
              <label for="regConfirm">Confirm Password</label>
              <div class="input-box"><i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input id="regConfirm" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" placeholder="Repeat your password">
              </div>
            </div>
            <p class="registration-notice">Company access requires email verification and administrator approval. Departments and roles are assigned by the administrator.</p>
            <button class="login-btn" type="submit">Create Account <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
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
          <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>

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
