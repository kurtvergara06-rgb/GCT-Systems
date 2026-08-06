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
    'resources/css/Login/login-polish.css',
    'resources/js/Login/login.js',
  ])
</head>

<body>
  <main class="gct-login-page">
    <section class="gct-brand-panel" aria-label="GCT Transport Services information">
      <div class="brand-grid" aria-hidden="true"></div>
      <div class="route-line route-line-one" aria-hidden="true"></div>
      <div class="route-line route-line-two" aria-hidden="true"></div>

      <div class="brand-content">
        <header class="brand-header">
          <img
            src="{{ asset('img/gct_logo.png') }}"
            alt="GCT Transport Services logo"
            class="company-logo"
          >

          <div class="brand-heading">
            <p class="brand-eyebrow">GCT Transport Services</p>
            <h1>GCT Transport<br>Services, Inc.</h1>
            <div class="brand-divider"></div>
            <p class="brand-tagline">Leading shuttle service provider in CALABARZON</p>
          </div>
        </header>

        <div class="services-list" aria-label="Our services">
          <article class="service-item">
            <span class="service-icon"><i class="fa-solid fa-bus-simple"></i></span>
            <div>
              <h2>Company Shuttle Service</h2>
              <p>Reliable daily transportation for your workforce.</p>
            </div>
          </article>

          <article class="service-item">
            <span class="service-icon"><i class="fa-regular fa-calendar-check"></i></span>
            <div>
              <h2>Special Trips</h2>
              <p>Flexible transport solutions for every occasion.</p>
            </div>
          </article>

          <article class="service-item">
            <span class="service-icon"><i class="fa-solid fa-people-group"></i></span>
            <div>
              <h2>Group Tours</h2>
              <p>Comfortable and coordinated travel for groups.</p>
            </div>
          </article>

          <article class="service-item">
            <span class="service-icon"><i class="fa-solid fa-graduation-cap"></i></span>
            <div>
              <h2>Educational Tours</h2>
              <p>Safe and enriching transport experiences.</p>
            </div>
          </article>
        </div>

        <div class="fleet-visual" aria-hidden="true">
          <div class="light-trail light-trail-one"></div>
          <div class="light-trail light-trail-two"></div>

          <div class="vehicle vehicle-car-one">
            <img
              src="{{ asset('img/GCT_bus2.png') }}"
              alt=""
              class="fleet-asset-small"
            >
          </div>

          <div class="vehicle vehicle-van">
            <img
              src="{{ asset('img/GCT_bus1.png') }}"
              alt=""
              class="fleet-asset-medium"
            >
          </div>

          <div class="vehicle vehicle-bus">
            <img
              src="{{ asset('img/GCT_bus.png') }}"
              alt=""
              class="fleet-asset-large"
            >
          </div>
        </div>

        <div class="security-note">
          <span class="security-shield"><i class="fa-solid fa-shield-halved"></i></span>
          <div>
            <strong>Your security is our priority.</strong>
            <span>All account data is protected and encrypted.</span>
          </div>
        </div>
      </div>
    </section>

    <section class="gct-form-panel">
      <div class="panel-glow panel-glow-one" aria-hidden="true"></div>
      <div class="panel-glow panel-glow-two" aria-hidden="true"></div>

      <div class="login-shell">
        <div class="login-card">
          <div class="user-icon" aria-hidden="true">
            <i class="fa-regular fa-user"></i>
          </div>

          <div class="login-heading">
            <span class="login-eyebrow">Secure account access</span>
            <h2>Welcome Back</h2>
            <p>Sign in to access your company system.</p>
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
              <span>Sign In</span>
              <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
          </form>

          <div class="trust-divider">
            <span></span>
            <p><i class="fa-solid fa-shield-halved"></i> Trusted. Secure. Encrypted.</p>
            <span></span>
          </div>
        </div>

        <footer class="login-footer">
          <p>© 2026 GCT Transport Services, Inc. All rights reserved.</p>
          <nav class="footer-links" aria-label="Legal links">
            <a href="#">Privacy Policy</a>
            <span aria-hidden="true">•</span>
            <a href="#">Terms of Use</a>
          </nav>
        </footer>
      </div>
    </section>
  </main>
</body>
</html>
