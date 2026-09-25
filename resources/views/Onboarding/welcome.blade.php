<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to GCT</title>
    <style>
        :root { --navy:#0f172a; --blue:#2563eb; --muted:#64748b; --line:#e2e8f0; --soft:#f8fafc; --green:#15803d; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,Segoe UI,Arial,sans-serif; background:linear-gradient(135deg,#eff6ff,#f8fafc 45%,#eef2ff); color:var(--navy); min-height:100vh; }
        .shell { width:min(980px,calc(100% - 32px)); margin:32px auto; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; }
        .brand { display:flex; gap:12px; align-items:center; }
        .logo { width:44px; height:44px; border-radius:12px; background:#1d4ed8; color:#fff; display:grid; place-items:center; font-weight:800; }
        .brand h1 { margin:0; font-size:20px; } .brand p { margin:3px 0 0; color:var(--muted); font-size:12px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 16px 40px rgba(15,23,42,.08); overflow:hidden; }
        .progress { display:grid; grid-template-columns:repeat(4,1fr); border-bottom:1px solid var(--line); background:#fff; }
        .progress button { border:0; background:transparent; padding:14px 8px; font-size:12px; color:var(--muted); cursor:pointer; border-bottom:3px solid transparent; }
        .progress button.active { color:var(--blue); border-bottom-color:var(--blue); font-weight:700; }
        .step { display:none; padding:32px; } .step.active { display:block; }
        .hero { text-align:center; padding:28px 12px; }
        .hero-badge { width:70px; height:70px; border-radius:20px; background:#dbeafe; color:#1d4ed8; display:grid; place-items:center; margin:0 auto 16px; font-size:30px; font-weight:800; }
        h2 { font-size:28px; margin:0 0 8px; } .lead { color:var(--muted); max-width:680px; margin:0 auto; line-height:1.6; }
        .identity { margin:26px auto 0; display:inline-flex; gap:8px; flex-wrap:wrap; justify-content:center; }
        .pill { background:#f1f5f9; border:1px solid var(--line); border-radius:999px; padding:7px 11px; font-size:12px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .field { display:flex; flex-direction:column; gap:7px; } .field.full { grid-column:1/-1; }
        label { font-size:12px; font-weight:700; } input { width:100%; padding:11px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:14px; }
        input[readonly] { background:#f8fafc; color:#475569; }
        .tips { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:20px; }
        .tip { border:1px solid var(--line); border-radius:14px; padding:16px; background:var(--soft); }
        .tip strong { display:block; margin-bottom:5px; font-size:14px; } .tip span { color:var(--muted); font-size:12px; line-height:1.5; }
        .safety { border:1px solid #bbf7d0; background:#f0fdf4; border-radius:14px; padding:18px; margin-top:18px; }
        .safety strong { color:var(--green); }
        .actions { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:18px 32px; border-top:1px solid var(--line); background:#fff; }
        .btn { border:0; border-radius:10px; padding:10px 16px; font-weight:700; cursor:pointer; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
        .btn-primary { background:var(--blue); color:#fff; } .btn-secondary { background:#eef2f7; color:#334155; } .btn-link { background:transparent; color:var(--muted); }
        .alert { margin:0 32px 16px; padding:11px 13px; border-radius:10px; background:#ecfdf5; color:#166534; font-size:13px; }
        .small { color:var(--muted); font-size:12px; }
        @media (max-width:720px){ .grid,.tips{grid-template-columns:1fr;} .field.full{grid-column:auto;} .step{padding:22px;} .actions{padding:16px 22px; flex-wrap:wrap;} h2{font-size:24px;} }
    </style>
</head>
<body>
<div class="shell">
    <div class="top">
        <div class="brand">
            <div class="logo">GCT</div>
            <div>
                <h1>GCT Fleet & Operations Management System</h1>
                <p>{{ $replay ? 'System tutorial replay' : 'Password secured — complete your first-time account setup' }}</p>
            </div>
        </div>
        @if($replay)<span class="pill">Tutorial Replay</span>@endif
    </div>

    <div class="card">
        <div class="progress">
            <button type="button" class="active" data-step="0">1. Welcome</button>
            <button type="button" data-step="1">2. Confirm Profile</button>
            <button type="button" data-step="2">3. Module Tutorial</button>
            <button type="button" data-step="3">4. Ready to Start</button>
        </div>

        @if(session('success'))<div class="alert">{{ session('success') }}</div>@endif

        <section class="step active">
            <div class="hero">
                <div class="hero-badge">✓</div>
                <h2>Welcome to GCT, {{ explode(' ', trim($user->name))[0] ?: 'User' }}!</h2>
                <p class="lead">Your sign-in password is already secured. This short onboarding will confirm your profile, introduce the tools for your department, and prepare you for your first use of the system.</p>
                <div class="identity">
                    <span class="pill">Department: {{ $user->department }}</span>
                    <span class="pill">Role: {{ ucfirst($user->role) }}</span>
                    <span class="pill">Account: {{ $user->status }}</span>
                </div>
            </div>
        </section>

        <section class="step">
            <h2>Confirm your profile</h2>
            <p class="lead" style="margin:0 0 22px;text-align:left">Review your name and email before continuing. Your department and role are assigned by the System Administrator and are read-only here.</p>
            <form id="profileForm" action="{{ route('onboarding.profile.update', [], false) }}" method="POST">
                @csrf @method('PUT')
                <div class="grid">
                    <div class="field"><label for="name">Display Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
                    <div class="field"><label for="email">Email Address</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                    <div class="field"><label>Department</label><input value="{{ $user->department }}" readonly></div>
                    <div class="field"><label>Role</label><input value="{{ ucfirst($user->role) }}" readonly></div>
                </div>
                @if($errors->any())<p class="small" style="color:#b91c1c">{{ $errors->first() }}</p>@endif
                <div style="margin-top:16px"><button class="btn btn-secondary" type="submit">Save Profile Details</button></div>
            </form>
        </section>

        <section class="step">
            <h2>Your {{ $user->department }} module</h2>
            <p class="lead" style="margin:0;text-align:left">These are the main tools and workflows available to you based on your assigned department.</p>
            <div class="tips">
                @foreach($tips as [$title, $description])
                    <div class="tip"><strong>{{ $title }}</strong><span>{{ $description }}</span></div>
                @endforeach
            </div>
            <div class="safety"><strong>Quick tip:</strong> Never share your password or use another employee's account. System activity is logged for accountability and traceability.</div>
        </section>

        <section class="step">
            <div class="hero">
                <div class="hero-badge">GCT</div>
                <h2>You’re ready to start</h2>
                <p class="lead">Finish setup to open your department dashboard. You can replay this tutorial later from Account Settings whenever you need a refresher.</p>
                <div class="tips" style="text-align:left">
                    <div class="tip"><strong>Start with your dashboard</strong><span>Your dashboard summarizes records and actions relevant to your department.</span></div>
                    <div class="tip"><strong>Watch notifications</strong><span>Pending approvals, workflow changes, and operational updates appear in the system top bar.</span></div>
                </div>
            </div>
        </section>

        <div class="actions">
            <button type="button" class="btn btn-secondary" id="backBtn" disabled>Back</button>
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                @if(!$replay)
                    <form action="{{ route('onboarding.skip', [], false) }}" method="POST">@csrf<button class="btn btn-link" type="submit">Skip Tutorial</button></form>
                @else
                    <a href="{{ $dashboardUrl }}" class="btn btn-link">Exit Tutorial</a>
                @endif
                <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
                <form id="finishForm" action="{{ route('onboarding.complete', [], false) }}" method="POST" style="display:none">@csrf<button class="btn btn-primary" type="submit">Finish Setup</button></form>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const steps = [...document.querySelectorAll('.step')];
    const tabs = [...document.querySelectorAll('.progress button')];
    const back = document.getElementById('backBtn');
    const next = document.getElementById('nextBtn');
    const finish = document.getElementById('finishForm');
    let index = 0;
    const show = (i) => {
        index = Math.max(0, Math.min(i, steps.length - 1));
        steps.forEach((s, n) => s.classList.toggle('active', n === index));
        tabs.forEach((t, n) => t.classList.toggle('active', n === index));
        back.disabled = index === 0;
        next.style.display = index === steps.length - 1 ? 'none' : 'inline-flex';
        finish.style.display = index === steps.length - 1 ? 'block' : 'none';
    };
    back.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    tabs.forEach((tab, n) => tab.addEventListener('click', () => show(n)));
})();
</script>
</body>
</html>
