<x-layout.app
    title="FROMS - Security Settings"
    :assets="[
        'resources/css/Admin/Settings/security-settings.css'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main security-settings-page">

            <x-layout.topbar
                title="Security Settings"
                subtitle="Configure authentication, session protection, login rules, and account security policies"
            />


            {{-- =====================================================
                SECURITY OVERVIEW
            ====================================================== --}}
            <section class="security-overview">

                <div class="security-overview-main">

                    <div class="security-shield">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>

                    <div>

                        <span class="security-kicker">
                            Security Center
                        </span>

                        <h2>
                            FROMS Access Protection
                        </h2>

                        <p>
                            Manage authentication rules and account protection policies
                            used by authorized FROMS users.
                        </p>

                    </div>

                </div>


                <div class="security-state">

                    <div class="security-state-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>

                    <div>
                        <span>Security Status</span>
                        <strong>Protected</strong>
                        <small>Core protections enabled</small>
                    </div>

                </div>

            </section>


            {{-- =====================================================
                SECURITY SNAPSHOT
            ====================================================== --}}
            <section class="security-snapshot">

                <div class="security-snapshot-item good">

                    <div class="snapshot-icon">
                        <i class="fa-solid fa-key"></i>
                    </div>

                    <div>
                        <span>Password Policy</span>
                        <strong>{{ $policy['minimum_password_length'] }}+ Characters</strong>
                    </div>

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div class="security-snapshot-item good">

                    <div class="snapshot-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <span>Session Timeout</span>
                        <strong>{{ $policy['session_timeout'] ? $policy['session_timeout'].' Minutes' : config('session.lifetime').' Minutes (Server)' }}</strong>
                    </div>

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div class="security-snapshot-item warning">

                    <div class="snapshot-icon">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>

                    <div>
                        <span>Account Lockout</span>
                        <strong>{{ $policy['failed_attempts'] }} Attempts</strong>
                    </div>

                    <i class="fa-solid fa-triangle-exclamation"></i>

                </div>


                <div class="security-snapshot-item info">

                    <div class="snapshot-icon">
                        <i class="fa-solid fa-list"></i>
                    </div>

                    <div>
                        <span>Security Logging</span>
                        <strong>Mandatory</strong>
                    </div>

                    <i class="fa-solid fa-circle-info"></i>

                </div>

            </section>


            {{-- =====================================================
                SECURITY WORKSPACE
            ====================================================== --}}
            <section class="security-workspace">

                {{-- LEFT POLICY MENU --}}
                <aside class="security-menu">

                    <div class="security-menu-heading">
                        <span>Security Policies</span>
                        <strong>Access Control</strong>
                    </div>

                    <nav class="security-menu-list">

                        <a
                            href="#password-policy"
                            class="security-menu-item active"
                        >
                            <div class="menu-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>

                            <div>
                                <strong>Password Policy</strong>
                                <span>Credential requirements</span>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>
                        </a>


                        <a
                            href="#session-security"
                            class="security-menu-item"
                        >
                            <div class="menu-icon">
                                <i class="fa-solid fa-laptop"></i>
                            </div>

                            <div>
                                <strong>Session Security</strong>
                                <span>Login and timeout protection</span>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>
                        </a>


                        <a
                            href="#lockout-policy"
                            class="security-menu-item"
                        >
                            <div class="menu-icon">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>

                            <div>
                                <strong>Account Lockout</strong>
                                <span>Failed-login protection</span>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>
                        </a>


                        <a
                            href="#security-monitoring"
                            class="security-menu-item"
                        >
                            <div class="menu-icon">
                                <i class="fa-solid fa-eye"></i>
                            </div>

                            <div>
                                <strong>Security Monitoring</strong>
                                <span>Activity and login records</span>
                            </div>

                            <i class="fa-solid fa-chevron-right"></i>
                        </a>

                    </nav>


                    <div class="security-advice">

                        <div class="security-advice-icon">
                            <i class="fa-solid fa-shield"></i>
                        </div>

                        <p>
                            Security configuration should only be changed by authorized
                            system administrators.
                        </p>

                    </div>

                </aside>


                {{-- RIGHT SETTINGS --}}
                <form class="security-content" id="security-settings-form" action="{{ route('admin.settings.security.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    @if(session('success'))
                        <div class="security-policy-message" role="status">{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="security-policy-message security-policy-error" role="alert">{{ $errors->first() }}</div>
                    @endif
                    @unless($canEdit)
                        <div class="security-policy-message">Read only: only authorized administrators can edit security policies.</div>
                    @endunless

                    {{-- =================================================
                        PASSWORD POLICY
                    ================================================== --}}
                    <section
                        id="password-policy"
                        class="security-panel"
                    >

                        <div class="security-panel-header">

                            <div class="security-panel-icon blue">
                                <i class="fa-solid fa-key"></i>
                            </div>

                            <div>
                                <span>Authentication</span>
                                <h2>Password Policy</h2>
                                <p>
                                    Define minimum password requirements for FROMS user accounts.
                                </p>
                            </div>

                        </div>


                        <div class="password-policy-grid">

                            <div class="security-field">

                                <label for="minimum_password_length">
                                    Minimum Password Length
                                </label>

                                <select id="minimum_password_length" name="minimum_password_length" @disabled(!$canEdit)>
<option value="8" @selected((int) old('minimum_password_length', $policy['minimum_password_length']) === 8)>8 Characters</option>
<option value="10" @selected((int) old('minimum_password_length', $policy['minimum_password_length']) === 10)>10 Characters</option>
<option value="12" @selected((int) old('minimum_password_length', $policy['minimum_password_length']) === 12)>12 Characters</option>
</select>

                                <small>
                                    Recommended minimum is 8 characters.
                                </small>

                            </div>


                            <div class="security-field">

                                <label for="password_expiration">
                                    Password Expiration
                                </label>

                                <select id="password_expiration" name="password_expiration" @disabled(!$canEdit)>
<option value="0" @selected((int) old('password_expiration', $policy['password_expiration']) === 0)>Never</option>
<option value="30" @selected((int) old('password_expiration', $policy['password_expiration']) === 30)>30 Days</option>
<option value="90" @selected((int) old('password_expiration', $policy['password_expiration']) === 90)>90 Days</option>
<option value="180" @selected((int) old('password_expiration', $policy['password_expiration']) === 180)>180 Days</option>
</select>

                                <small>
                                    Defines when users should update their password.
                                </small>

                            </div>

                        </div>


                        <div class="security-rule-list">

                            <div class="security-rule-row">

                                <div>

                                    <strong>
                                        Require Uppercase Letter
                                    </strong>

                                    <span>
                                        Password must contain at least one uppercase character.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" name="require_uppercase" value="1" @checked(old('require_uppercase', $policy['require_uppercase'])) @disabled(!$canEdit)>
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="security-rule-row">

                                <div>

                                    <strong>
                                        Require Number
                                    </strong>

                                    <span>
                                        Password must include at least one numeric character.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" name="require_number" value="1" @checked(old('require_number', $policy['require_number'])) @disabled(!$canEdit)>
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="security-rule-row">

                                <div>

                                    <strong>
                                        Require Special Character
                                    </strong>

                                    <span>
                                        Password must contain at least one symbol such as @, #, or !.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" name="require_special" value="1" @checked(old('require_special', $policy['require_special'])) @disabled(!$canEdit)>
                                    <span class="switch-slider"></span>
                                </label>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        SESSION SECURITY
                    ================================================== --}}
                    <section
                        id="session-security"
                        class="security-panel"
                    >

                        <div class="security-panel-header">

                            <div class="security-panel-icon green">
                                <i class="fa-solid fa-laptop"></i>
                            </div>

                            <div>
                                <span>Login Protection</span>
                                <h2>Session Security</h2>
                                <p>
                                    Control session behavior and authenticated user access.
                                </p>
                            </div>

                        </div>


                        <div class="session-layout">

                            <div class="session-control">

                                <div class="session-control-icon">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                </div>

                                <div class="session-control-content">

                                    <label for="session_timeout">
                                        Session Timeout
                                    </label>

                                    <p>
                                        Automatically end inactive authenticated sessions.
                                    </p>

                                </div>

                                <select id="session_timeout" name="session_timeout" @disabled(!$canEdit)>
<option value="0" @selected((int) old('session_timeout', $policy['session_timeout']) === 0)>Server Default</option>
<option value="30" @selected((int) old('session_timeout', $policy['session_timeout']) === 30) @disabled((int) config('session.lifetime') < 30)>30 Minutes</option>
<option value="60" @selected((int) old('session_timeout', $policy['session_timeout']) === 60) @disabled((int) config('session.lifetime') < 60)>60 Minutes</option>
<option value="120" @selected((int) old('session_timeout', $policy['session_timeout']) === 120) @disabled((int) config('session.lifetime') < 120)>120 Minutes</option>
<option value="240" @selected((int) old('session_timeout', $policy['session_timeout']) === 240) @disabled((int) config('session.lifetime') < 240)>240 Minutes</option>
</select>

                            </div>


                            <div class="session-control">

                                <div class="session-control-icon">
                                    <i class="fa-solid fa-desktop"></i>
                                </div>

                                <div class="session-control-content">

                                    <strong>
                                        Allow Multiple Active Sessions
                                    </strong>

                                    <p>
                                        Allow one account to remain signed in on multiple devices.
                                    </p>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" name="allow_multiple_sessions" value="1" @checked(old('allow_multiple_sessions', $policy['allow_multiple_sessions'])) @disabled(!$canEdit)>
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="session-control">

                                <div class="session-control-icon">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                </div>

                                <div class="session-control-content">

                                    <strong>
                                        Logout on Password Change
                                    </strong>

                                    <p>
                                        End other active sessions after an account password is changed.
                                    </p>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" name="logout_on_password_change" value="1" @checked(old('logout_on_password_change', $policy['logout_on_password_change'])) @disabled(!$canEdit)>
                                    <span class="switch-slider"></span>
                                </label>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        LOCKOUT POLICY
                    ================================================== --}}
                    <section
                        id="lockout-policy"
                        class="security-panel lockout-panel"
                    >

                        <div class="security-panel-header">

                            <div class="security-panel-icon red">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>

                            <div>
                                <span>Brute-Force Protection</span>
                                <h2>Account Lockout Policy</h2>
                                <p>
                                    Restrict repeated failed login attempts to protect user accounts.
                                </p>
                            </div>

                        </div>


                        <div class="lockout-visual">

                            <div class="lockout-step">

                                <div class="lockout-step-number">
                                    1
                                </div>

                                <strong>Login Attempt</strong>

                                <span>
                                    User enters account credentials.
                                </span>

                            </div>


                            <div class="lockout-arrow">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>


                            <div class="lockout-step warning">

                                <div class="lockout-step-number">
                                    2
                                </div>

                                <strong>Failed Attempts</strong>

                                <span>
                                    System counts unsuccessful login attempts.
                                </span>

                            </div>


                            <div class="lockout-arrow">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>


                            <div class="lockout-step critical">

                                <div class="lockout-step-number">
                                    3
                                </div>

                                <strong>Temporary Lock</strong>

                                <span>
                                    Account access is temporarily restricted.
                                </span>

                            </div>

                        </div>


                        <div class="lockout-settings-grid">

                            <div class="security-field">

                                <label for="failed_attempts">
                                    Maximum Failed Attempts
                                </label>

                                <select id="failed_attempts" name="failed_attempts" @disabled(!$canEdit)>
<option value="3" @selected((int) old('failed_attempts', $policy['failed_attempts']) === 3)>3 Attempts</option>
<option value="5" @selected((int) old('failed_attempts', $policy['failed_attempts']) === 5)>5 Attempts</option>
<option value="7" @selected((int) old('failed_attempts', $policy['failed_attempts']) === 7)>7 Attempts</option>
<option value="10" @selected((int) old('failed_attempts', $policy['failed_attempts']) === 10)>10 Attempts</option>
</select>

                            </div>


                            <div class="security-field">

                                <label for="lockout_duration">
                                    Lockout Duration
                                </label>

                                <select id="lockout_duration" name="lockout_duration" @disabled(!$canEdit)>
<option value="5" @selected((int) old('lockout_duration', $policy['lockout_duration']) === 5)>5 Minutes</option>
<option value="15" @selected((int) old('lockout_duration', $policy['lockout_duration']) === 15)>15 Minutes</option>
<option value="30" @selected((int) old('lockout_duration', $policy['lockout_duration']) === 30)>30 Minutes</option>
<option value="60" @selected((int) old('lockout_duration', $policy['lockout_duration']) === 60)>60 Minutes</option>
</select>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        SECURITY MONITORING
                    ================================================== --}}
                    <section
                        id="security-monitoring"
                        class="security-panel"
                    >

                        <div class="security-panel-header">

                            <div class="security-panel-icon purple">
                                <i class="fa-solid fa-eye"></i>
                            </div>

                            <div>
                                <span>Monitoring</span>
                                <h2>Security Activity Monitoring</h2>
                                <p>
                                    Control the recording of security-related account activity.
                                </p>
                            </div>

                        </div>


                        <div class="monitoring-rule-grid">

                            <div class="monitoring-rule">

                                <div class="monitoring-rule-icon">
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                </div>

                                <div>

                                    <strong>
                                        Record Login Activity
                                    </strong>

                                    <span>
                                        Log successful and failed authentication attempts.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" checked disabled title="Core security audit logging is mandatory">
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="monitoring-rule">

                                <div class="monitoring-rule-icon">
                                    <i class="fa-solid fa-key"></i>
                                </div>

                                <div>

                                    <strong>
                                        Record Password Changes
                                    </strong>

                                    <span>
                                        Log when account credentials are updated.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" checked disabled title="Core security audit logging is mandatory">
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="monitoring-rule">

                                <div class="monitoring-rule-icon">
                                    <i class="fa-solid fa-user-gear"></i>
                                </div>

                                <div>

                                    <strong>
                                        Record Role & Permission Changes
                                    </strong>

                                    <span>
                                        Log modifications to account access permissions.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" checked disabled title="Core security audit logging is mandatory">
                                    <span class="switch-slider"></span>
                                </label>

                            </div>


                            <div class="monitoring-rule">

                                <div class="monitoring-rule-icon">
                                    <i class="fa-solid fa-ban"></i>
                                </div>

                                <div>

                                    <strong>
                                        Audit Suspicious Login Activity
                                    </strong>

                                    <span>
                                        Record repeated login failures in Activity Logs; alerts are not configured on this page.
                                    </span>

                                </div>

                                <label class="switch">
                                    <input type="checkbox" checked disabled title="Core security audit logging is mandatory">
                                    <span class="switch-slider"></span>
                                </label>

                            </div>

                        </div>


                        <div class="activity-link-box">

                            <div>

                                <i class="fa-solid fa-clock-rotate-left"></i>

                                <div>
                                    <strong>Security records are available in Activity Logs.</strong>
                                    <span>
                                        Review authentication and administrative activity from System Monitoring.
                                    </span>
                                </div>

                            </div>

                            <a href="{{ route('admin.activity-logs') }}">
                                Open Activity Logs
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>

                        </div>

                    </section>


                    {{-- =================================================
                        SAVE BAR
                    ================================================== --}}
                    <section class="security-save-bar">

                        <div>

                            <strong>
                                Security Configuration
                            </strong>

                            <span>
                                Core audit logging remains mandatory. Changes apply to subsequent requests.
                            </span>

                        </div>

                        <div class="save-actions">

                            <button
                                type="reset"
                                class="reset-button" @disabled(!$canEdit)
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                Reset
                            </button>

                            <button
                                type="submit"
                                class="save-button" @disabled(!$canEdit)
                            >
                                <i class="fa-solid fa-shield-halved"></i>
                                Save Security Settings
                            </button>

                        </div>

                    </section>

                </form>

            </section>

        </main>

    </div>

</x-layout.app>