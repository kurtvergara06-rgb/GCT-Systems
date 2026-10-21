<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Complete Registration | GCT-Systems</title>
@vite(['resources/css/Main-styles/theme.css','resources/css/Login/login.css'])
<style>
  .google-register-panel{width:100%;max-width:470px;margin:0 auto}
  .google-register-card{padding:26px;border:1px solid #dce6f1;background:#fff;border-radius:16px}
  .google-register-card h2{font-size:25px;color:#102e54;line-height:1.3;margin:0 0 9px}
  .google-register-card p{color:#62748d;font-size:14px;line-height:1.6}
  .google-register-card label{font-weight:700;color:#183651;display:block;font-size:13px;margin:23px 0 9px}
  .google-register-card input{display:block;width:100%;height:47px;padding:12px 14px;border:1px solid #cbd9e9;border-radius:9px;font-size:14px;box-sizing:border-box}
  .google-register-card input:focus{outline:2px solid #87b5e9;outline-offset:1px}
  .google-register-card input[readonly]{background:#f1f5fa;color:#5a6d84}
  .google-register-help{font-size:12px!important;margin:7px 0 0}
  .google-register-error{font-size:12px!important;color:#b42318!important;margin:8px 0 0}
  .google-register-note{display:flex;gap:9px;margin:22px 0;padding:12px 14px;border-radius:9px;background:#eff5fc;color:#315981;font-size:12px;line-height:1.6}
  .google-register-password-hint{font-size:12px!important;color:#62748d;margin:8px 0 0}
  .google-register-submit{border:0;width:100%;padding:14px 18px;background:#0c4c8a;color:#fff;border-radius:9px;font-weight:750;cursor:pointer}
  .google-register-submit:hover{background:#083968}
  .google-register-back{display:block;margin-top:20px;text-align:center;color:#174e87;font-size:13px;text-decoration:none}
</style>
</head>
<body>
<main class="gct-login-page">
<section class="gct-brand-panel" aria-label="GCT Transport Services" style="--gct-hero-image:url('{{ asset('img/BUS-GCT.png') }}')">
  <div class="brand-content">
    <header class="brand-header">
      <img src="{{ asset('img/gct_logo.png') }}" alt="GCT Transport Services logo" class="company-logo">
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
    <div class="login-card google-register-panel">
      <div class="google-register-card">
        <h2>Complete Your Registration</h2>
        <p>Enter your official full name and create a GCT-Systems password. You can use Google or your password to sign in after approval.</p>
        <form method="POST" action="{{ route('auth.google.register.submit') }}">
          @csrf
          <label for="googleEmployeeName">Full Name</label>
          <input type="text" id="googleEmployeeName" name="name" value="{{ old('name', $googleName) }}" placeholder="Enter your complete name" maxlength="255" autocomplete="name" autofocus required>
          @error('name')<p class="google-register-error" role="alert">{{ $message }}</p>@enderror
          <label for="googleEmployeeEmail">Google Email Address</label>
          <input id="googleEmployeeEmail" type="email" value="{{ $googleEmail }}" readonly aria-readonly="true">
          <p class="google-register-help">This email is provided by Google and cannot be changed here.</p>
          <label for="googleEmployeePassword">Create GCT-Systems Password</label>
          <input id="googleEmployeePassword" type="password" name="password" placeholder="Create a secure password" autocomplete="new-password" minlength="8" required>
          <p class="google-register-password-hint">At least 8 characters. This is separate from your Google password.</p>
          @error('password')<p class="google-register-error" role="alert">{{ $message }}</p>@enderror
          <label for="googleEmployeePasswordConfirmation">Confirm Password</label>
          <input id="googleEmployeePasswordConfirmation" type="password" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password" minlength="8" required>
          <div class="google-register-note"><span aria-hidden="true">ⓘ</span><span>After submitting, you'll receive a six-digit email OTP. An administrator will assign your department and role after verification.</span></div>
          <button type="submit" class="google-register-submit">Continue to Email Verification →</button>
        </form>
        <a class="google-register-back" href="{{ route('login') }}">Back to Sign In</a>
      </div>
    </div>
  </div>
</section>
</main>
</body>
</html>