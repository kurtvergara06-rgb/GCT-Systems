<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Link Google Account | GCT-Systems</title>
@vite(['resources/css/Main-styles/theme.css','resources/css/Login/login.css'])
<style>
  .link-panel{max-width:460px;width:100%;margin:0 auto}
  .link-card{border:1px solid #dae5f1;border-radius:14px;padding:24px;background:#fff}
  .link-card h2{font-size:23px;color:#102f57;margin:0 0 12px}
  .link-card p{font-size:14px;color:#596f89;line-height:1.7}
  .link-card label{display:block;color:#183552;font-weight:700;font-size:13px;margin:22px 0 8px}
  .link-card input{width:100%;border:1px solid #bfcfe2;border-radius:9px;padding:13px;font-size:15px}
  .link-card button{width:100%;margin-top:18px;background:#0c4c8a;color:white;border:0;border-radius:9px;padding:14px;font-weight:700;cursor:pointer}
  .link-card a{display:block;text-align:center;margin-top:20px;color:#154c87;font-size:13px}
  .link-error{color:#b42318;font-size:13px;margin-top:8px}
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
    <div class="login-card link-panel">
      <div class="link-card">
        <h2>Connect Your Google Account</h2>
        <p>An existing GCT-Systems account was found for <strong>{{ $account->email }}</strong>. For your security, confirm your existing GCT-Systems password to link your Google account.</p>
        <form method="post" action="{{ route('auth.google.link.confirm') }}">
          @csrf
          <label for="existingPassword">Current GCT-Systems password</label>
          <input id="existingPassword" name="password" type="password" required autocomplete="current-password" autofocus>
          @error('password')<p class="link-error" role="alert">{{ $message }}</p>@enderror
          <button type="submit">Verify and Connect Google</button>
        </form>
        <a href="{{ route('login') }}">Back to Sign In</a>
      </div>
    </div>
  </div>
</section>
</main>
</body>
</html>