<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>GCT Registration</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  @vite([
    'resources/css/Login_Register/login.css',
    'resources/css/Login_Register/register.css'
  ])
</head>

<body>

  <main class="login-page">

    <!-- LEFT PANEL -->
    <section class="left-panel">
      <div class="left-content">

        <img src="img/gct_logo.png" alt="GCT Transport Services Logo" class="company-logo">

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

    <!-- RIGHT PANEL -->
    <section class="right-panel">

      <div class="login-card register-card">

        <div class="user-icon">
          <i class="fa-solid fa-user-plus"></i>
        </div>

        <h2>Create Account</h2>
        <p class="subtitle">Register to access the company system</p>

        <form id="registerForm">

          <div class="form-row">
            <div class="form-group">
              <label for="firstName">First Name</label>
              <div class="input-box">
                <i class="fa-regular fa-user"></i>
                <input 
                  type="text" 
                  id="firstName" 
                  name="first_name"
                  placeholder="First name" 
                  required
                >
              </div>
            </div>

            <div class="form-group">
              <label for="lastName">Last Name</label>
              <div class="input-box">
                <i class="fa-regular fa-user"></i>
                <input 
                  type="text" 
                  id="lastName" 
                  name="last_name"
                  placeholder="Last name" 
                  required
                >
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="username">Username</label>
            <div class="input-box">
              <i class="fa-regular fa-user"></i>
              <input 
                type="text" 
                id="username" 
                name="username"
                placeholder="Create a username" 
                required
              >
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-box">
              <i class="fa-regular fa-envelope"></i>
              <input 
                type="email" 
                id="email" 
                name="email"
                placeholder="you@company.com" 
                required
              >
            </div>
          </div>

          <div class="form-group">
            <label for="role">Department Role</label>
            <div class="input-box">
              <i class="fa-solid fa-users-gear"></i>
              <select id="role" name="role" required>
                <option value="">Select department role</option>

                <option value="maintenance">Maintenance Head</option>
                <option value="maintenance">Maintenance Staff</option>

                <option value="purchasing">Purchasing Head</option>
                <option value="purchasing">Purchasing Staff</option>

                <option value="warehouse">Warehouse Head</option>
                <option value="warehouse">Warehouse Staff</option>

                <option value="admin">Admin</option>


              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input 
                  type="password" 
                  id="password" 
                  name="password"
                  placeholder="Create password" 
                  required
                >
              </div>
            </div>

            <div class="form-group">
              <label for="confirmPassword">Confirm Password</label>
              <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input 
                  type="password" 
                  id="confirmPassword" 
                  name="password_confirmation"
                  placeholder="Confirm password" 
                  required
                >
              </div>
            </div>
          </div>

          <button type="submit" class="login-btn">
            Create Account
          </button>

          <div class="register-box">
            <span>Already have an account?</span>
            <a href="{{ route('login') }}">Sign In</a>
          </div>

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