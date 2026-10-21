<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>GCT Transport Services | Pending Approval</title>

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
  >

  @vite([
    'resources/css/Main-styles/theme.css',
    'resources/css/Login/login.css',
  ])

  <style>
    .pending-section{display:flex;flex-direction:column;gap:18px;margin:23px 0 28px}
    .pending-verified{display:flex;align-items:center;gap:14px;background:#ecf9f1;border:1px solid #a9e5bf;border-radius:12px;padding:15px 17px;color:#146b3b}
    .pending-verified-icon{height:40px;width:40px;display:grid;place-items:center;flex-shrink:0;background:#178348;color:white;border-radius:50%;font-size:18px}
    .pending-verified strong{font-size:15px}.pending-verified p{margin:4px 0 0;color:#367557;font-size:13px;line-height:1.5}
    .pending-status-card{display:flex;gap:16px;padding:23px 20px;background:#fff8eb;border:1px solid #f2d49e;border-radius:14px}
    .pending-status-icon{width:51px;height:51px;flex-shrink:0;display:grid;place-items:center;border-radius:15px;background:#ffedc8;color:#a66a08;font-size:24px}
    .pending-eyebrow{font-size:10px;letter-spacing:1.3px;font-weight:800;color:#9c650e}
    .pending-status-card h3{font-size:18px;color:#132c50;line-height:1.3;margin:7px 0 9px}
    .pending-status-card p{font-size:14px;color:#546783;line-height:1.65;margin:0}
    .pending-access-note{display:flex;gap:12px;align-items:center;border:1px solid #d9e6f7;background:#f3f7fd;border-radius:10px;padding:13px 15px;color:#22538f}
    .pending-access-note p{margin:0;font-size:13px;line-height:1.6;color:#536c8b}
    .pending-unverified{font-size:13px;line-height:1.6;color:#965c09;margin:0}
    .pending-back-button{display:flex;justify-content:center;align-items:center;gap:10px;padding:14px;border-radius:10px;background:#0c4c8a;color:#fff;text-decoration:none;font-weight:700;font-size:14px;transition:background .15s}
    .pending-back-button:hover{background:#073969}.pending-back-button:focus-visible{outline:3px solid #ffc400;outline-offset:3px}
    @media(max-width:480px){.pending-status-card{padding:17px 14px;gap:12px}.pending-status-icon{width:43px;height:43px}.pending-status-card h3{font-size:16px}}
  </style>
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
            <h2>Account Pending Approval</h2>
            <p>Your employee registration has been received.</p>
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

          <section class="pending-section" aria-labelledby="pendingStatusTitle">
            @if($emailVerified ?? session('pending_verified', false))
              <div class="pending-verified" role="status">
                <span class="pending-verified-icon"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                <div><strong>Email verified successfully</strong><p>Your email address has been confirmed. No further verification is required.</p></div>
              </div>
            @endif

            <div class="pending-status-card">
              <span class="pending-status-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
              <div>
                <span class="pending-eyebrow">CURRENT ACCOUNT STATUS</span>
                <h3 id="pendingStatusTitle">Awaiting administrator approval</h3>
                <p>Your registration is waiting for an administrator to review your application and assign your department and role.</p>
              </div>
            </div>

            <div class="pending-access-note">
              <i class="fa-solid fa-lock" aria-hidden="true"></i>
              <p>Dashboard access will become available after your account is approved and activated.</p>
            </div>
            @if(isset($emailVerified) && ! $emailVerified)
              <p class="pending-unverified">Your email hasn't been verified yet. Complete the OTP verification before your account can be approved.</p>
            @endif
            <a class="pending-back-button" href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Sign In</a>
          </section>

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
