<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>GCT Login</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  @vite([
    'resources/css/Login/login.css',
    'resources/js/Login/login.js'
  ])
</head>

<body>

  <main class="login-page">

    <section class="left-panel">
      <div class="left-content">

        <img src="{{ asset('img/gct_logo.png') }}" alt="GCT Transport Services Logo" class="company-logo">

        <h1>GCT Transport Services, Inc.</h1>

        <h3>
          LEADING SHUTTLE SERVICE PROVIDER <br>
          IN CALABARZON
        </h3>

        <ul class="service-list">
          <li>Company Shuttle Service</li>
          <li>Special Trips</li>
          <li>Group Tours</li>
          <li>Educational Tours</li>
        </ul>

        <div class="bus-image">
          <i class="fa-solid fa-bus"></i>
        </div>

        <div class="security-box">
          <div class="security-icon">
            <i class="fa-solid fa-lock"></i>
          </div>

          <div>
            <h4>Your security is our priority.</h4>
            <p>All data is encrypted and secure.</p>
          </div>
        </div>

      </div>
    </section>

    <section class="right-panel">

      <div class="login-card">

        <div class="user-icon">
          <i class="fa-regular fa-user"></i>
        </div>

        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to access your company system</p>

        @if(session('error'))
          <div class="login-alert">
            {{ session('error') }}
          </div>
        @endif

        @if($errors->any())
          <div class="login-alert">
            {{ $errors->first() }}
          </div>
        @endif

        <form id="loginForm" method="POST" action="{{ route('login.submit') }}">
          @csrf

          <div class="form-group">
            <label for="loginEmail">Email</label>

            <div class="input-box">
              <i class="fa-regular fa-user"></i>
              <input
                type="email"
                id="loginEmail"
                name="email"
                value="{{ old('email') }}"
                placeholder="Enter your email"
                required
                autocomplete="email"
              >
            </div>
          </div>

          <div class="form-group">
            <label for="loginPassword">Password</label>

            <div class="input-box">
              <i class="fa-solid fa-lock"></i>
              <input
                type="password"
                id="loginPassword"
                name="password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
              >

              <i class="fa-regular fa-eye toggle-password" id="passwordIcon"></i>
            </div>
          </div>

          <div class="login-options">
            <label>
              <input type="checkbox" name="remember" value="1">
              Remember me
            </label>

            <a href="#">Forgot password?</a>
          </div>

          <button type="submit" class="login-btn" id="loginBtn">
            Sign In
          </button>
        </form>

      </div>

      <footer class="login-footer">
        <p>
          <i class="fa-solid fa-shield-halved"></i>
          © 2026 GCT Transport Services, Inc. All rights reserved.
        </p>

        <div class="footer-links">
          <a href="#">Privacy Policy</a>
          <span>|</span>
          <a href="#">Terms of Use</a>
        </div>
      </footer>

    </section>

  </main>

</body>
</html>