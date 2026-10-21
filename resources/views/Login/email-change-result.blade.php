<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Email Verification | GCT Transport Services</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; padding: 24px; background: linear-gradient(135deg, #062c4e 0%, #12568a 100%); }
        .result-card { width: min(100%, 460px); padding: 39px 32px; border-radius: 20px; background: #fff; box-shadow: 0 24px 75px #001b3440; text-align: center; color: #15324f; }
        .result-icon { display: inline-grid; place-items: center; width: 76px; height: 76px; border-radius: 50%; font-size: 40px; font-weight: 800; background: {{ $successful ? '#dbf6e7' : '#ffebeb' }}; color: {{ $successful ? '#0b8a4e' : '#ce3448' }}; }
        h1 { font-size: 25px; line-height: 1.25; margin: 24px 0 12px; }
        p { font-size: 15px; line-height: 1.65; color: #61738a; margin: 0 0 27px; }
        a { display: block; padding: 15px 20px; border-radius: 10px; background: #ffc400; color: #112f4a; font-size: 15px; text-decoration: none; font-weight: 750; }
        a:focus-visible { outline: 3px solid #1269b7; outline-offset: 3px; }
        small { display: block; color: #8797aa; margin-top: 21px; line-height: 1.4; }
    </style>
</head>
<body>
    <main class="result-card" role="status" aria-live="polite">
        <span class="result-icon" aria-hidden="true">{{ $successful ? '✓' : '!' }}</span>
        <h1>{{ $successful ? 'Email Verified Successfully!' : 'Verification Link Unavailable' }}</h1>
        <p>
            @if($successful)
                Your new email address is now verified and active. You can sign in using the new email address and your existing password.
            @else
                This verification link is invalid, expired, or already used. Your previously verified email remains unchanged unless it was verified earlier.
            @endif
        </p>
        <a href="{{ route('login') }}">Continue to GCT-Systems</a>
        <small>GCT Transport Services · Secure email verification</small>
    </main>
</body>
</html>
