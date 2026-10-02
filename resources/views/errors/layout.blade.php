<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · @yield('title') | GCT Transport Services</title>

    <style>
        :root {
            --brand-blue: #0B40B5;
            --brand-navy: #061F3D;
            --brand-yellow: #FFC400;
            --brand-yellow-dark: #F5A800;
            --bg-main: #F4F7FE;
            --surface: #FFFFFF;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --border-soft: #DCE5F2;
            --danger: #EF4444;
            --shadow-card: 0 24px 70px rgba(6, 31, 61, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at 10% 10%, rgba(11, 64, 181, 0.10), transparent 32rem),
                radial-gradient(circle at 90% 90%, rgba(255, 196, 0, 0.12), transparent 28rem),
                var(--bg-main);
        }

        .error-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 20px;
        }

        .error-card {
            width: min(920px, 100%);
            display: grid;
            grid-template-columns: minmax(260px, 0.85fr) minmax(320px, 1.15fr);
            overflow: hidden;
            border: 1px solid var(--border-soft);
            border-radius: 24px;
            background: var(--surface);
            box-shadow: var(--shadow-card);
        }

        .error-brand-panel {
            position: relative;
            min-height: 480px;
            padding: 34px;
            color: #fff;
            background:
                linear-gradient(150deg, rgba(6, 31, 61, 0.96), rgba(11, 64, 181, 0.92)),
                linear-gradient(180deg, var(--brand-navy), var(--brand-blue));
            overflow: hidden;
        }

        .error-brand-panel::before,
        .error-brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .error-brand-panel::before {
            width: 240px;
            height: 240px;
            right: -90px;
            top: -70px;
            border: 42px solid rgba(255, 196, 0, 0.13);
        }

        .error-brand-panel::after {
            width: 180px;
            height: 180px;
            left: -70px;
            bottom: -80px;
            background: rgba(255, 255, 255, 0.06);
        }

        .brand-lockup {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-lockup img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            padding: 6px;
            border-radius: 14px;
            background: #fff;
        }

        .brand-lockup strong,
        .brand-lockup span {
            display: block;
        }

        .brand-lockup strong {
            font-size: 16px;
            line-height: 1.2;
        }

        .brand-lockup span {
            margin-top: 3px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.72);
        }

        .status-visual {
            position: relative;
            z-index: 1;
            margin-top: 78px;
        }

        .status-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .status-kicker::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--brand-yellow);
            box-shadow: 0 0 0 5px rgba(255, 196, 0, 0.12);
        }

        .status-code {
            margin: 14px 0 0;
            font-size: clamp(76px, 10vw, 118px);
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.06em;
            color: #fff;
        }

        .status-caption {
            margin: 18px 0 0;
            max-width: 280px;
            font-size: 13px;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.70);
        }

        .error-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 58px 56px;
        }

        .error-eyebrow {
            margin: 0 0 12px;
            color: var(--brand-blue);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .error-content h1 {
            margin: 0;
            color: var(--brand-navy);
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.08;
            letter-spacing: -0.035em;
        }

        .error-message {
            margin: 20px 0 0;
            color: var(--text-main);
            font-size: 16px;
            line-height: 1.7;
        }

        .error-hint {
            margin: 14px 0 0;
            padding: 14px 16px;
            border: 1px solid #E4EBF6;
            border-radius: 12px;
            background: #F8FAFD;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .error-btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
        }

        .error-btn:hover {
            transform: translateY(-1px);
        }

        .error-btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--brand-blue), var(--brand-navy));
            box-shadow: 0 10px 22px rgba(11, 64, 181, 0.20);
        }

        .error-btn-secondary {
            color: var(--brand-navy);
            border-color: var(--border-soft);
            background: #fff;
        }

        .error-footer {
            margin-top: 30px;
            color: #94A3B8;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 760px) {
            .error-shell {
                padding: 18px;
            }

            .error-card {
                grid-template-columns: 1fr;
            }

            .error-brand-panel {
                min-height: 270px;
                padding: 26px;
            }

            .status-visual {
                margin-top: 42px;
            }

            .status-code {
                font-size: 76px;
            }

            .status-caption {
                display: none;
            }

            .error-content {
                padding: 38px 28px;
            }
        }

        @media (max-width: 420px) {
            .error-actions {
                display: grid;
            }

            .error-btn {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .error-btn {
                transition: none;
            }

            .error-btn:hover {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <main class="error-shell">
        <section class="error-card" aria-labelledby="error-title">
            <aside class="error-brand-panel" aria-label="GCT Transport Services">
                <div class="brand-lockup">
                    <img src="{{ asset('img/gct_logo.png') }}" alt="GCT Transport Services logo">
                    <div>
                        <strong>GCT Transport Services, Inc.</strong>
                        <span>Fleet Operations Management System</span>
                    </div>
                </div>

                <div class="status-visual">
                    <span class="status-kicker">System response</span>
                    <div class="status-code">@yield('code')</div>
                    <p class="status-caption">
                        The system handled this request safely. Use the options provided to continue.
                    </p>
                </div>
            </aside>

            <div class="error-content">
                <p class="error-eyebrow">GCT Systems</p>
                <h1 id="error-title">@yield('title')</h1>

                <p class="error-message">@yield('message')</p>
                <p class="error-hint">@yield('hint')</p>

                <div class="error-actions">
                    <a class="error-btn error-btn-primary" href="{{ url('/') }}">
                        Return to system
                    </a>
                    <button class="error-btn error-btn-secondary" type="button" onclick="history.back()">
                        Go back
                    </button>
                </div>

                <p class="error-footer">
                    @yield('footer', 'If the problem continues, contact your system administrator and include the error code shown on this page.')
                </p>
            </div>
        </section>
    </main>
</body>
</html>
