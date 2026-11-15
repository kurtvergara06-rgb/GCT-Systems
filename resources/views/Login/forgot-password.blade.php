<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>GCT Transport Services | Password Recovery</title>

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
            <h2>Reset your password</h2>
            <p>Enter your account email to request a secure password reset link.</p>
          </div>

          @if ($errors->any())
            <div class="login-alert" role="alert">
              <i class="fa-solid fa-circle-exclamation"></i>
              <div>
                <strong>Unable to proceed</strong>
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

<form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-group">
              <label for="recoveryEmail">Email Address</label>
              <div class="input-box">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <input type="email" name="email" id="recoveryEmail" value="{{ old('email') }}" required autocomplete="email" placeholder="you@company.com">
              </div>
              @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="login-btn">Send Reset Link <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
          </form>
          <p class="auth-switch"><a href="{{ route('login') }}">Back to Sign In</a></p>

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