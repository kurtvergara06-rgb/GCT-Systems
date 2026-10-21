<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GCT Transport Services | Email Verification</title>
  @vite(['resources/css/Main-styles/theme.css', 'resources/css/Login/login.css'])
  <style>
    .otp-heading{text-align:center;margin:0 0 24px}
    .otp-symbol{height:64px;width:64px;display:grid;place-items:center;margin:0 auto 18px;border-radius:18px;background:#eaf2fc;color:#204b80;font-size:30px}
    .otp-heading h2{margin:0 0 10px;color:#152d4b;font-size:28px}
    .otp-heading p{margin:0;color:#62738a;line-height:1.65}
    .otp-email{display:inline-flex;margin-top:14px;padding:9px 15px;background:#f1f5fa;border-radius:9px;font-weight:650;color:#31465e;font-size:14px}
    .otp-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin:13px 0 16px}
    .otp-input{min-width:0;width:100%;height:59px;text-align:center;border:1.5px solid #d6dfeb;border-radius:10px;background:#fff;color:#193550;font-size:25px;font-weight:750;outline:0}
    .otp-input:focus{border-color:#245a91;box-shadow:0 0 0 3px rgba(36,90,145,.12)}
    .otp-input:disabled{opacity:.6}
    .otp-label{font-size:13px;font-weight:650;color:#31465e}
    .otp-help{text-align:center;font-size:13px;color:#64748b}
    .otp-message{margin:12px 0;border:1px solid #c9d9ed;border-radius:10px;padding:11px;background:#f0f6ff;color:#24476a;font-size:13px}
    .otp-footer{text-align:center;margin-top:21px;font-size:13px;color:#64748b}
    .otp-link{background:none;border:0;color:#245a91;font-weight:700;cursor:pointer;text-decoration:none}
    .otp-link:disabled{opacity:.55;cursor:default}
    .otp-back{display:block;text-align:center;margin-top:22px;color:#526680;text-decoration:none}
    @media(max-width:430px){.otp-grid{gap:5px}.otp-input{height:48px;font-size:20px}.otp-heading h2{font-size:23px}}
  </style>
</head>
<body>
  <main class="gct-login-page">
    <section class="gct-brand-panel" aria-label="GCT Transport Services information" style="--gct-hero-image: url('{{ asset('img/BUS-GCT.png') }}');">
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
        <div class="login-card">
          <div class="otp-heading">
            <div class="otp-symbol" aria-hidden="true">&#9993;</div>
            <h2>Verify Your Email</h2>
            <p>Enter the 6-digit verification code sent to your email address.</p>
            <div class="otp-email">employee@gmail.com</div>
          </div>
          <form id="otpPreviewForm" autocomplete="off">
            <label class="otp-label" for="otp-digit-1">Verification code</label>
            <div class="otp-grid" id="otpGrid">
              @for ($i = 1; $i <= 6; $i++)
                <input id="otp-digit-{{ $i }}" class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit {{ $i }} of 6" autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}">
              @endfor
            </div>
            <p class="otp-help">Code expires 5 minutes after sending.</p>
            <button class="login-btn" type="submit">Verify Email &rarr;</button>
            <div class="otp-message" id="otpPreviewMessage" role="status" hidden></div>
          </form>
          <p class="otp-footer">Didn't receive a code? <button id="otpResend" type="button" class="otp-link">Resend Code</button></p>
          <a class="otp-back" href="{{ route('login') }}">&larr; Back to Sign In</a>
          <p class="otp-help" style="margin-top:24px">UI preview only. Sending and verifying codes are not connected yet.</p>
        </div>
        <footer class="login-footer"><p>&copy; {{ date('Y') }} GCT Transport Services, Inc. All rights reserved.</p></footer>
      </div>
    </section>
  </main>
  <script>
    (() => {
      const inputs = [...document.querySelectorAll('.otp-input')];
      const message = document.getElementById('otpPreviewMessage');
      const show = text => { message.hidden = false; message.textContent = text; };
      const fill = (start, raw) => {
        const chars = String(raw).replace(/\D/g, '').slice(0, 6 - start).split('');
        chars.forEach((char, offset) => inputs[start + offset].value = char);
        inputs[Math.min(start + chars.length, 5)].focus();
      };
      inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
          const value = input.value.replace(/\D/g, '');
          input.value = '';
          if (value) fill(index, value);
        });
        input.addEventListener('keydown', event => {
          if (event.key === 'Backspace' && !input.value && index > 0) {
            inputs[index - 1].value = '';
            inputs[index - 1].focus();
          }
          if (event.key === 'ArrowLeft' && index > 0) inputs[index - 1].focus();
          if (event.key === 'ArrowRight' && index < 5) inputs[index + 1].focus();
        });
        input.addEventListener('paste', event => {
          event.preventDefault();
          fill(index, event.clipboardData.getData('text'));
        });
      });
      document.getElementById('otpPreviewForm').addEventListener('submit', event => {
        event.preventDefault();
        show(inputs.every(input => /^\d$/.test(input.value)) ?
          'Preview only: six digits entered. Server verification is not implemented.' :
          'Please enter all six digits.');
      });
      document.getElementById('otpResend').addEventListener('click', () => {
        inputs.forEach(input => input.value = '');
        inputs[0].focus();
        show('Preview only: no email has been sent.');
      });
    })();
  </script>
</body>
</html>
