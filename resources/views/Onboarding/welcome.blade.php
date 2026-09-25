<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to GCT - Fleet & Operations Management System</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --gct-navy: #061f3d;
            --gct-navy-deep: #031428;
            --gct-blue: #0b40b5;
            --gct-blue-light: #e0ecff;
            --gct-yellow: #ffc400;
            --gct-yellow-dark: #f5a800;
            --gct-text: #0f172a;
            --gct-muted: #64748b;
            --gct-line: #dce5f2;
            --gct-card: #ffffff;
            --gct-soft: #f8fafc;
            --gct-green: #10b981;
            --gct-green-light: #d1fae5;
            --gct-red: #ef4444;
            --gct-red-light: #fee2e2;
            --gct-shadow: 0 16px 40px rgba(6, 31, 61, 0.08);
            --gct-shadow-sm: 0 4px 12px rgba(6, 31, 61, 0.05);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0f5fc 0%, #e8f0fb 50%, #f4f8fe 100%);
            color: var(--gct-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .shell {
            width: min(960px, 100%);
            margin: 0 auto;
        }

        /* =========================================================
           TOP BRAND HEADER
        ========================================================= */
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .brand-logo-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #ffffff;
            border: 2px solid var(--gct-line);
            box-shadow: 0 4px 12px rgba(6, 31, 61, 0.08);
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            color: var(--gct-navy);
            letter-spacing: -0.01em;
        }

        .brand-text p {
            margin: 3px 0 0;
            color: var(--gct-muted);
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pill-replay {
            background: #ffffff;
            border: 1px solid var(--gct-line);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            color: var(--gct-blue);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--gct-shadow-sm);
        }

        /* =========================================================
           MAIN CARD CONTAINER
        ========================================================= */
        .card {
            background: var(--gct-card);
            border: 1px solid var(--gct-line);
            border-radius: 20px;
            box-shadow: var(--gct-shadow);
            overflow: hidden;
            position: relative;
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gct-blue) 0%, var(--gct-yellow) 100%);
        }

        /* =========================================================
           STEPPER PROGRESS TABS
        ========================================================= */
        .progress {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            border-bottom: 1px solid var(--gct-line);
            background: #ffffff;
        }

        .step-tab {
            border: 0;
            background: transparent;
            padding: 16px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gct-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .step-tab .step-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            transition: all 0.2s ease;
        }

        .step-tab:hover:not(.locked) {
            background: #f8fbff;
            color: var(--gct-navy);
        }

        .step-tab.active {
            color: var(--gct-blue);
            border-bottom-color: var(--gct-blue);
            font-weight: 700;
            background: #f8fbff;
        }

        .step-tab.active .step-num {
            background: var(--gct-blue);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(11, 64, 181, 0.35);
        }

        .step-tab.completed .step-num {
            background: var(--gct-green);
            color: #ffffff;
        }

        .step-tab.locked {
            cursor: not-allowed;
            opacity: 0.45;
        }

        /* =========================================================
           STEP CONTENTS
        ========================================================= */
        .step {
            display: none;
            padding: 36px 40px;
        }

        .step.active {
            display: block;
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* =========================================================
           STEP 1: WELCOME HERO
        ========================================================= */
        .hero {
            text-align: center;
            padding: 16px 12px 20px;
        }

        .hero-logo-emblem {
            width: 104px;
            height: 104px;
            border-radius: 28px;
            background: #ffffff;
            border: 3px solid #ffea79;
            box-shadow: 0 10px 30px rgba(11, 64, 181, 0.12), 0 2px 8px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            padding: 10px;
            position: relative;
        }

        .hero-logo-emblem img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .hero-logo-emblem::after {
            content: "FROMS";
            position: absolute;
            bottom: -10px;
            background: linear-gradient(135deg, var(--gct-blue), var(--gct-navy));
            color: #ffffff;
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            letter-spacing: 0.1em;
            box-shadow: 0 2px 6px rgba(6, 31, 61, 0.2);
        }

        h2 {
            font-size: 26px;
            font-weight: 800;
            color: var(--gct-navy);
            margin: 0 0 10px;
            letter-spacing: -0.01em;
        }

        .lead {
            color: var(--gct-muted);
            max-width: 680px;
            margin: 0 auto;
            line-height: 1.6;
            font-size: 13.5px;
        }

        .identity-bar {
            margin: 26px auto 0;
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .identity-pill {
            background: #f8fafc;
            border: 1px solid var(--gct-line);
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .identity-pill strong {
            color: var(--gct-navy);
            font-weight: 700;
        }

        .identity-pill i {
            color: var(--gct-blue);
            font-size: 12px;
        }

        .identity-pill.status-active .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gct-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            display: inline-block;
        }

        /* =========================================================
           STEP 2: SECURE ACCOUNT (MATCHING IMAGE 2 EXACTLY)
        ========================================================= */
        .secure-card {
            max-width: 680px;
            margin: 0 auto;
            border: 1px solid var(--gct-line);
            border-radius: 18px;
            padding: 26px 28px;
            background: #ffffff;
            box-shadow: var(--gct-shadow-sm);
        }

        .account-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 18px;
            border-bottom: 1px solid #edf2f7;
            margin-bottom: 22px;
        }

        .account-card-header-copy h2,
        .account-card-header-copy h3 {
            margin: 0;
            color: var(--gct-navy);
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .account-card-header-copy p {
            margin: 4px 0 0;
            color: var(--gct-muted);
            font-size: 12.5px;
            line-height: 1.5;
        }

        .account-card-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px;
            background: #f0f5fc;
            border: 1px solid #e1ebf7;
            color: var(--gct-blue);
            font-size: 16px;
        }

        .account-field {
            display: grid;
            gap: 7px;
            margin-bottom: 20px;
        }

        .account-field label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #1e334a;
            font-size: 12.5px;
            font-weight: 700;
        }

        .account-field-tag {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .account-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .account-input-icon {
            position: absolute;
            left: 14px;
            color: #8fa0b5;
            font-size: 14px;
            pointer-events: none;
            transition: color 150ms ease;
            z-index: 1;
        }

        .account-input-wrap input {
            width: 100%;
            min-height: 46px;
            padding: 11px 44px 11px 40px;
            border: 1.5px solid #d6e1ee;
            border-radius: 12px;
            background: #ffffff;
            color: #142a42;
            font: inherit;
            font-size: 13.5px;
            font-weight: 500;
            outline: none;
            transition: all 160ms ease;
        }

        .account-input-wrap input:hover:not([readonly]) {
            border-color: #bccfe6;
        }

        .account-input-wrap input:focus {
            border-color: var(--gct-blue);
            background: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(11, 64, 181, 0.12);
        }

        .account-input-wrap input:focus ~ .account-input-icon,
        .account-input-wrap:focus-within .account-input-icon {
            color: var(--gct-blue);
        }

        .account-pw-toggle {
            position: absolute;
            right: 8px;
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #718196;
            cursor: pointer;
            transition: all 140ms ease;
            z-index: 2;
        }

        .account-pw-toggle:hover {
            background: #f0f4f9;
            color: var(--gct-blue);
        }

        .account-field-error {
            color: #dc2626;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Live Strength Box */
        .account-pw-strength {
            display: grid;
            gap: 8px;
            margin-top: 8px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8faff;
            border: 1px solid #e2eaf4;
        }

        .account-pw-strength-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            font-weight: 600;
            color: #55677d;
        }

        .account-pw-strength-label {
            font-weight: 700;
            font-size: 11.5px;
        }

        .account-pw-track {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .account-pw-bar {
            width: 0%;
            height: 100%;
            border-radius: 999px;
            transition: width 240ms ease, background-color 240ms ease;
        }

        .account-pw-bar.is-weak {
            width: 33%;
            background: #ef4444;
        }

        .account-pw-bar.is-moderate {
            width: 66%;
            background: #f59e0b;
        }

        .account-pw-bar.is-strong {
            width: 100%;
            background: #10b981;
        }

        .account-pw-checklist {
            list-style: none;
            margin: 4px 0 0;
            padding: 0;
            display: grid;
            gap: 6px;
        }

        .account-pw-checklist li {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            color: #64748b;
            transition: color 150ms ease;
        }

        .account-pw-checklist li i {
            font-size: 11.5px;
            color: #94a3b8;
            transition: all 150ms ease;
        }

        .account-pw-checklist li.is-met {
            color: #0f766e;
            font-weight: 600;
        }

        .account-pw-checklist li.is-met i {
            color: #10b981;
        }

        .account-pw-match-feedback {
            font-size: 11.5px;
            font-weight: 600;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .account-pw-match-feedback.is-match {
            color: #10b981;
        }

        .account-pw-match-feedback.is-mismatch {
            color: #ef4444;
        }

        .account-form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .secure-status {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            padding: 18px 20px;
            border-radius: 12px;
            line-height: 1.5;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .secure-status i {
            font-size: 22px;
            color: #16a34a;
            flex-shrink: 0;
        }

        /* =========================================================
           STEP 3: CONFIRM PROFILE
        ========================================================= */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--gct-navy);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .input-group {
            position: relative;
        }

        .input-group i.input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }

        .input-group input {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px 10px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--gct-text);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--gct-blue);
            box-shadow: 0 0 0 3px rgba(11, 64, 181, 0.15);
        }

        .input-group input[readonly] {
            background: #f8fafc;
            color: #475569;
            cursor: default;
        }

        .field-readonly-badge {
            margin-left: auto;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 4px;
        }

        /* =========================================================
           STEP 4: MODULE TUTORIAL CARDS
        ========================================================= */
        .tips {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .tip-card {
            border: 1px solid var(--gct-line);
            border-radius: 14px;
            padding: 16px 18px;
            background: #ffffff;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(6, 31, 61, 0.03);
        }

        .tip-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(6, 31, 61, 0.06);
        }

        .tip-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .tip-icon.blue { background: #e0ecff; color: #0b40b5; }
        .tip-icon.yellow { background: #fef3c7; color: #b45309; }
        .tip-icon.green { background: #dcfce7; color: #15803d; }
        .tip-icon.purple { background: #e0e7ff; color: #4338ca; }
        .tip-icon.red { background: #fee2e2; color: #dc2626; }

        .tip-content {
            min-width: 0;
        }

        .tip-content strong {
            display: block;
            margin-bottom: 4px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--gct-navy);
        }

        .tip-content span {
            color: var(--gct-muted);
            font-size: 11.5px;
            line-height: 1.45;
            display: block;
        }

        .safety {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12.5px;
            color: #1e40af;
        }

        .safety i {
            font-size: 20px;
            color: var(--gct-blue);
            flex-shrink: 0;
        }

        /* =========================================================
           STEP 5: READY HERO
        ========================================================= */
        .ready-badge-wrap {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 32px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35);
        }

        /* =========================================================
           ACTIONS FOOTER
        ========================================================= */
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 18px 40px;
            border-top: 1px solid var(--gct-line);
            background: #ffffff;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.18s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gct-blue) 0%, var(--gct-navy) 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(11, 64, 181, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(11, 64, 181, 0.35);
        }

        .btn-secondary {
            background: #ffffff;
            color: #334155;
            border: 1px solid var(--gct-line);
        }

        .btn-secondary:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: var(--gct-navy);
        }

        .btn-secondary:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .btn-link {
            background: transparent;
            color: var(--gct-muted);
            border: 0;
            font-weight: 600;
        }

        .btn-link:hover {
            color: var(--gct-navy);
            text-decoration: underline;
        }

        .alert-success-banner {
            margin: 0 40px 16px;
            padding: 12px 16px;
            border-radius: 10px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success-banner i {
            color: #10b981;
            font-size: 16px;
        }

        .error {
            color: var(--gct-red);
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .small {
            color: var(--gct-muted);
            font-size: 11.5px;
            margin-top: 8px;
        }

        @media (max-width: 820px) {
            body { padding: 16px 8px; }
            .progress { grid-template-columns: 1fr; }
            .step-tab { border-bottom: 1px solid var(--gct-line); justify-content: flex-start; padding: 12px 18px; }
            .grid, .tips { grid-template-columns: 1fr; }
            .step { padding: 24px 20px; }
            .actions { padding: 16px 20px; flex-wrap: wrap; }
            h2 { font-size: 22px; }
            .account-form-actions { justify-content: stretch; }
            .account-form-actions button { width: 100%; }
        }
    </style>
</head>
<body>

<div class="shell">
    {{-- TOP BRAND HEADER --}}
    <div class="top">
        <div class="brand">
            <div class="brand-logo-wrap">
                <img src="{{ asset('img/gct_logo.png') }}" alt="GCT Transport Services logo" class="brand-logo-img">
            </div>
            <div class="brand-text">
                <h1>GCT Fleet & Operations Management System</h1>
                <p>{{ $replay ? 'System tutorial replay' : 'First-time account setup and system orientation' }}</p>
            </div>
        </div>
        @if($replay)
            <span class="badge-pill-replay"><i class="fa-solid fa-clock-rotate-left"></i> Tutorial Replay</span>
        @endif
    </div>

    {{-- ONBOARDING CARD --}}
    <div class="card">
        {{-- STEPPER NAVIGATION --}}
        <div class="progress">
            <button type="button" class="step-tab active" data-step="0">
                <span class="step-num">1</span>
                <span>1. Welcome</span>
            </button>
            <button type="button" class="step-tab" data-step="1">
                <span class="step-num">2</span>
                <span>2. Secure Account</span>
            </button>
            <button type="button" class="step-tab" data-step="2">
                <span class="step-num">3</span>
                <span>3. Confirm Profile</span>
            </button>
            <button type="button" class="step-tab" data-step="3">
                <span class="step-num">4</span>
                <span>4. Module Tutorial</span>
            </button>
            <button type="button" class="step-tab" data-step="4">
                <span class="step-num">5</span>
                <span>5. Ready</span>
            </button>
        </div>

        @if(session('success'))
            <div class="alert-success-banner mt-3" style="margin-top: 16px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- STEP 1: WELCOME --}}
        <section class="step active">
            <div class="hero">
                <div class="hero-logo-emblem">
                    <img src="{{ asset('img/gct_logo.png') }}" alt="GCT Transport Services logo">
                </div>
                <h2>Welcome to GCT, {{ explode(' ', trim($user->name))[0] ?: 'User' }}!</h2>
                <p class="lead">Welcome to your GCT workspace. This setup will secure your account, confirm your profile, introduce the tools assigned to your department, and prepare you for your first day using the system.</p>
                <div class="identity-bar">
                    @php
                        $deptIcon = match(strtolower($user->department)) {
                            'maintenance' => 'fa-screwdriver-wrench',
                            'operation', 'operations' => 'fa-bus',
                            'warehouse' => 'fa-warehouse',
                            'purchase', 'purchasing' => 'fa-cart-shopping',
                            'admin', 'administration' => 'fa-user-shield',
                            default => 'fa-building',
                        };
                    @endphp
                    <span class="identity-pill">
                        <i class="fa-solid {{ $deptIcon }}"></i> Department: <strong>{{ $user->department }}</strong>
                    </span>
                    <span class="identity-pill">
                        <i class="fa-solid fa-id-badge"></i> Role: <strong>{{ ucfirst($user->role) }}</strong>
                    </span>
                    <span class="identity-pill status-active">
                        <span class="pulse-dot"></span> Account: <strong>{{ $user->status }}</strong>
                    </span>
                </div>
            </div>
        </section>

        {{-- STEP 2: SECURE ACCOUNT (IDENTICAL TO IMAGE 2) --}}
        <section class="step">
            <div class="secure-card">
                <div class="account-card-header">
                    <div class="account-card-header-copy">
                        <h2>Secure your account</h2>
                        <p>Update your system authentication password to protect your account access.</p>
                    </div>
                    <div class="account-card-icon">
                        <i class="fa-solid fa-key"></i>
                    </div>
                </div>

                @if($user->must_change_password)
                    <form action="{{ route('account.password.update', [], false) }}" method="POST">
                        @csrf
                        @method('PUT')

                        {{-- Current / Temporary Password Field --}}
                        <div class="account-field">
                            <label for="currentPassword">
                                <span>Current Password</span>
                                <span class="account-field-tag">Verification</span>
                            </label>
                            <div class="account-input-wrap">
                                <i class="fa-solid fa-lock account-input-icon"></i>
                                <input
                                    id="currentPassword"
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    placeholder="Enter your temporary / current password"
                                    required
                                >
                                <button
                                    type="button"
                                    class="account-pw-toggle"
                                    data-target="currentPassword"
                                    aria-label="Toggle password visibility"
                                    title="Show/Hide Password"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            @error('current_password')
                                <span class="account-field-error">
                                    <i class="fa-solid fa-circle-exclamation"></i> {{ $message }}
                                </span>
                            @enderror
                        </div>

                        {{-- New Password Field with Live Strength Meter --}}
                        <div class="account-field">
                            <label for="newPassword">
                                <span>New Password</span>
                                <span class="account-field-tag">Minimum 8 Chars</span>
                            </label>
                            <div class="account-input-wrap">
                                <i class="fa-solid fa-shield-halved account-input-icon"></i>
                                <input
                                    id="newPassword"
                                    type="password"
                                    name="password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    placeholder="Enter your new password"
                                    required
                                >
                                <button
                                    type="button"
                                    class="account-pw-toggle"
                                    data-target="newPassword"
                                    aria-label="Toggle password visibility"
                                    title="Show/Hide Password"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>

                            {{-- Live Password Strength Meter --}}
                            <div class="account-pw-strength">
                                <div class="account-pw-strength-head">
                                    <span>Security Rating:</span>
                                    <span id="pwStrengthLabel" class="account-pw-strength-label">Password strength</span>
                                </div>
                                <div class="account-pw-track">
                                    <div id="pwStrengthBar" class="account-pw-bar"></div>
                                </div>
                                <ul class="account-pw-checklist">
                                    <li id="req-length">
                                        <i class="fa-regular fa-circle"></i>
                                        <span>At least 8 characters long</span>
                                    </li>
                                    <li id="req-case">
                                        <i class="fa-regular fa-circle"></i>
                                        <span>Contains uppercase & lowercase letters</span>
                                    </li>
                                    <li id="req-number">
                                        <i class="fa-regular fa-circle"></i>
                                        <span>Contains a number or special character</span>
                                    </li>
                                </ul>
                            </div>

                            @error('password')
                                <span class="account-field-error">
                                    <i class="fa-solid fa-circle-exclamation"></i> {{ $message }}
                                </span>
                            @enderror
                        </div>

                        {{-- Confirm New Password Field with Match Feedback --}}
                        <div class="account-field">
                            <label for="confirmPassword">
                                <span>Confirm New Password</span>
                                <span class="account-field-tag">Confirmation</span>
                            </label>
                            <div class="account-input-wrap">
                                <i class="fa-solid fa-check account-input-icon"></i>
                                <input
                                    id="confirmPassword"
                                    type="password"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    minlength="8"
                                    placeholder="Repeat your new password"
                                    required
                                >
                                <button
                                    type="button"
                                    class="account-pw-toggle"
                                    data-target="confirmPassword"
                                    aria-label="Toggle password visibility"
                                    title="Show/Hide Password"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <div id="pwMatchFeedback" class="account-pw-match-feedback"></div>
                        </div>

                        <div class="account-form-actions">
                            <button type="submit" class="btn btn-primary" id="updatePasswordSubmitBtn">
                                <i class="fa-solid fa-shield-halved"></i> Update Password & Continue
                            </button>
                        </div>
                    </form>
                @else
                    <div class="secure-status">
                        <i class="fa-solid fa-circle-check"></i>
                        <div>
                            <strong>Account secured.</strong> Your temporary password has already been replaced. You can continue to your profile confirmation, or change your password later from Account Settings.
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- STEP 3: CONFIRM PROFILE --}}
        <section class="step">
            <h2>Confirm your profile</h2>
            <p class="lead" style="margin: 0 0 22px; text-align: left;">
                Review your name and email before continuing. Your department and role are assigned by the System Administrator and are read-only here.
            </p>

            <form id="profileForm" action="{{ route('onboarding.profile.update', [], false) }}" method="POST">
                @csrf @method('PUT')
                <div class="grid">
                    <div class="field">
                        <label for="name">
                            <i class="fa-solid fa-user"></i> Display Name
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">
                            <i class="fa-solid fa-envelope"></i> Email Address
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>
                            <i class="fa-solid fa-building"></i> Department
                            <span class="field-readonly-badge"><i class="fa-solid fa-lock"></i> Read-only</span>
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-building input-icon"></i>
                            <input value="{{ $user->department }}" readonly>
                        </div>
                    </div>

                    <div class="field">
                        <label>
                            <i class="fa-solid fa-id-badge"></i> Role
                            <span class="field-readonly-badge"><i class="fa-solid fa-lock"></i> Read-only</span>
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-id-badge input-icon"></i>
                            <input value="{{ ucfirst($user->role) }}" readonly>
                        </div>
                    </div>
                </div>

                @if($errors->has('name') || $errors->has('email'))
                    <p class="error"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first('name') ?: $errors->first('email') }}</p>
                @endif

                <div style="margin-top: 20px;">
                    <button class="btn btn-secondary" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
                    </button>
                </div>
            </form>
        </section>

        {{-- STEP 4: MODULE TUTORIAL --}}
        <section class="step">
            <h2>Your {{ $user->department }} module</h2>
            <p class="lead" style="margin: 0; text-align: left;">
                These are the main tools and workflows available to you based on your assigned department.
            </p>

            <div class="tips">
                @foreach($tips as [$title, $description])
                    @php
                        $iconClass = match(strtolower($title)) {
                            'maintenance referrals', 'maintenance referral' => ['fa-arrow-right-arrow-left', 'red'],
                            'job orders' => ['fa-clipboard-list', 'blue'],
                            'pms scheduling' => ['fa-calendar-check', 'yellow'],
                            'purchase requests', 'purchase orders' => ['fa-file-invoice', 'green'],
                            'trip scheduling' => ['fa-calendar-days', 'blue'],
                            'daily driver reports' => ['fa-clipboard-user', 'purple'],
                            'incidents' => ['fa-triangle-exclamation', 'red'],
                            'inventory' => ['fa-boxes-stacked', 'blue'],
                            'part requests' => ['fa-dolly', 'green'],
                            'incoming deliveries', 'delivery flow' => ['fa-truck-fast', 'yellow'],
                            'stock movements' => ['fa-arrow-right-arrow-left', 'purple'],
                            'requested purchase' => ['fa-cart-shopping', 'blue'],
                            'scheduled purchase' => ['fa-calendar-check', 'yellow'],
                            'accounts' => ['fa-users-gear', 'blue'],
                            'roles & permissions' => ['fa-shield-halved', 'purple'],
                            'analytics' => ['fa-chart-line', 'green'],
                            'activity logs' => ['fa-clock-rotate-left', 'yellow'],
                            default => ['fa-layer-group', 'blue'],
                        };
                    @endphp
                    <div class="tip-card">
                        <div class="tip-icon {{ $iconClass[1] }}">
                            <i class="fa-solid {{ $iconClass[0] }}"></i>
                        </div>
                        <div class="tip-content">
                            <strong>{{ $title }}</strong>
                            <span>{{ $description }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="safety">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong>Quick tip:</strong> Never share your password or use another employee's account. System activity is logged for accountability and traceability.
                </div>
            </div>
        </section>

        {{-- STEP 5: READY --}}
        <section class="step">
            <div class="hero">
                <div class="ready-badge-wrap">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2>You’re ready to start</h2>
                <p class="lead">Finish setup to open your department dashboard. You can replay this tutorial later from Account Settings whenever you need a refresher.</p>

                <div class="tips" style="text-align: left; max-width: 720px; margin: 24px auto 0;">
                    <div class="tip-card">
                        <div class="tip-icon blue">
                            <i class="fa-solid fa-table-cells-large"></i>
                        </div>
                        <div class="tip-content">
                            <strong>Start with your dashboard</strong>
                            <span>Your dashboard summarizes records and actions relevant to your department.</span>
                        </div>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon yellow">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="tip-content">
                            <strong>Watch notifications</strong>
                            <span>Pending approvals, workflow changes, and operational updates appear in the system top bar.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ACTIONS FOOTER --}}
        <div class="actions">
            <button type="button" class="btn btn-secondary" id="backBtn" disabled>
                <i class="fa-solid fa-arrow-left"></i> Back
            </button>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; align-items: center;">
                @if(!$replay && !$user->must_change_password)
                    <form action="{{ route('onboarding.skip', [], false) }}" method="POST">
                        @csrf
                        <button class="btn btn-link" type="submit">Skip Tutorial</button>
                    </form>
                @elseif($replay)
                    <a href="{{ $dashboardUrl }}" class="btn btn-link">Exit Tutorial</a>
                @endif

                <button type="button" class="btn btn-primary" id="nextBtn">
                    Next <i class="fa-solid fa-arrow-right"></i>
                </button>

                <form id="finishForm" action="{{ route('onboarding.complete', [], false) }}" method="POST" style="display: none;">
                    @csrf
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-check"></i> Finish Setup
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    // 1. Step Navigation
    const steps = [...document.querySelectorAll('.step')];
    const tabs = [...document.querySelectorAll('.step-tab')];
    const back = document.getElementById('backBtn');
    const next = document.getElementById('nextBtn');
    const finish = document.getElementById('finishForm');
    const passwordRequired = @json((bool) $user->must_change_password);
    const requestedStep = Number(@json((int) request('step', 0)));
    let index = passwordRequired ? Math.min(Math.max(requestedStep, 0), 1) : Math.min(Math.max(requestedStep, 0), steps.length - 1);

    const show = (i) => {
        let target = Math.max(0, Math.min(i, steps.length - 1));
        if (passwordRequired && target > 1) target = 1;
        index = target;

        steps.forEach((s, n) => s.classList.toggle('active', n === index));

        tabs.forEach((t, n) => {
            t.classList.toggle('active', n === index);
            t.classList.toggle('completed', n < index);
            t.classList.toggle('locked', passwordRequired && n > 1);
        });

        back.disabled = index === 0;
        const requiresPasswordSubmit = passwordRequired && index === 1;
        next.style.display = (index === steps.length - 1 || requiresPasswordSubmit) ? 'none' : 'inline-flex';
        finish.style.display = (!passwordRequired && index === steps.length - 1) ? 'block' : 'none';
    };

    back.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));

    tabs.forEach((tab, n) => tab.addEventListener('click', () => {
        if (passwordRequired && n > 1) return;
        show(n);
    }));

    show(index);

    // 2. Password Visibility Toggles (Exact implementation from Image 2)
    const toggleButtons = document.querySelectorAll('.account-pw-toggle');
    toggleButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-target');
            const input = inputId ? document.getElementById(inputId) : btn.closest('.account-input-wrap')?.querySelector('input');
            if (!input) return;

            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                btn.setAttribute('aria-label', 'Hide password');
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                btn.setAttribute('aria-label', 'Show password');
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    });

    // 3. Password Strength & Requirements Validation (Exact logic from Image 2)
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('pwStrengthBar');
    const strengthLabel = document.getElementById('pwStrengthLabel');
    const matchFeedback = document.getElementById('pwMatchFeedback');

    const reqLength = document.getElementById('req-length');
    const reqCase = document.getElementById('req-case');
    const reqNumber = document.getElementById('req-number');

    function updateRequirementItem(el, isMet) {
        if (!el) return;
        if (isMet) {
            el.classList.add('is-met');
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-circle-check';
            }
        } else {
            el.classList.remove('is-met');
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = 'fa-regular fa-circle';
            }
        }
    }

    function evaluatePassword(password) {
        if (!password) {
            return { score: 0, label: 'Password strength', class: '' };
        }

        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasMixedCase = hasUpper && hasLower;
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        updateRequirementItem(reqLength, hasLength);
        updateRequirementItem(reqCase, hasMixedCase);
        updateRequirementItem(reqNumber, hasNumber || hasSpecial);

        let score = 0;
        if (hasLength) score++;
        if (password.length >= 12) score++;
        if (hasMixedCase) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        if (score <= 1) {
            return { score: 1, label: 'Weak', class: 'is-weak' };
        } else if (score <= 3) {
            return { score: 2, label: 'Moderate', class: 'is-moderate' };
        } else {
            return { score: 3, label: 'Strong', class: 'is-strong' };
        }
    }

    function checkMatch() {
        if (!confirmPasswordInput || !matchFeedback) return;
        const newPass = newPasswordInput ? newPasswordInput.value : '';
        const confirmPass = confirmPasswordInput.value;

        if (!confirmPass) {
            matchFeedback.textContent = '';
            matchFeedback.className = 'account-pw-match-feedback';
            return;
        }

        if (newPass === confirmPass) {
            matchFeedback.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
            matchFeedback.className = 'account-pw-match-feedback is-match';
        } else {
            matchFeedback.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
            matchFeedback.className = 'account-pw-match-feedback is-mismatch';
        }
    }

    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', () => {
            const val = newPasswordInput.value;
            const evalResult = evaluatePassword(val);

            if (strengthBar && strengthLabel) {
                strengthBar.className = 'account-pw-bar ' + evalResult.class;
                strengthLabel.textContent = val ? evalResult.label : 'Password strength';
            }

            checkMatch();
        });
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkMatch);
    }
})();
</script>

</body>
</html>
