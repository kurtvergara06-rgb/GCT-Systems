<x-layout.app
    title="FROMS - Account Settings"
    :assets="[
        'resources/css/Account/account.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Account/account.js'
    ]"
>
    @php
        $department = ucfirst(strtolower((string) $user->department));
        $role = ucfirst(strtolower((string) $user->role));
        $displayRole = strtolower((string) $user->role) === 'head'
            ? $department . ' Head'
            : $department . ' ' . $role;

        $departmentCode = match (strtolower((string) $user->department)) {
            'maintenance' => 'MTN',
            'operation', 'operations' => 'OPS',
            'warehouse' => 'WHS',
            'purchase', 'purchasing' => 'PUR',
            'admin', 'administration' => 'ADM',
            default => 'USR',
        };

        $displayUserId = 'GCT-' . $departmentCode . '-' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);

        $icon = match (strtolower((string) $user->department)) {
            'maintenance' => 'fa-truck',
            'operation', 'operations' => 'fa-route',
            'warehouse' => 'fa-warehouse',
            'purchase', 'purchasing' => 'fa-cart-shopping',
            'admin', 'administration' => 'fa-shield-halved',
            default => 'fa-building',
        };

        $dashboardRoute = match (strtolower((string) $user->department)) {
            'maintenance' => 'maintenance-dashboard',
            'operation', 'operations' => 'dashboard-operation',
            'warehouse' => 'dashboard-warehouse',
            'purchase', 'purchasing' => 'dashboard-purchase',
            'admin', 'administration' => 'admin.dashboard',
            default => null,
        };

        $sidebarItems = $dashboardRoute
            ? [[
                'label' => 'Dashboard',
                'route' => $dashboardRoute,
                'icon' => 'fa-table-cells-large',
            ]]
            : [];

        $nameParts = collect(preg_split('/\s+/', trim((string) $user->name)))
            ->filter()
            ->values();
        $initials = strtoupper(
            substr($nameParts->get(0, ''), 0, 1)
            . substr($nameParts->get(1, ''), 0, 1)
        ) ?: 'U';
        $profilePhotoUrl = $user->profilePhotoUrl();
    @endphp

    <div class="app account-page">
        <x-layout.sidebar
            :department="$department"
            subtitle="Department Module"
            :icon="$icon"
            :items="$sidebarItems"
        />

        <main class="main account-main">
            <x-layout.topbar
                title="Account Settings"
                subtitle="Manage your sign-in credentials, access security, and account preferences"
            />

            <section class="account-content">
                @if ($user->must_change_password)
                    <div class="account-alert account-alert-success">
                        <i class="fa-solid fa-route"></i>
                        <span><strong>Security requirement:</strong> Change your temporary password before accessing your account.</span>
                    </div>
                @endif

                {{-- Security Center: distinguish account safety from the My Profile identity page. --}}
                <header class="account-security-hero" aria-labelledby="accountSecurityHeading">
                    <div class="account-security-intro">
                        <span class="account-security-emblem" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                        <div class="account-security-heading">
                            <span class="account-security-eyebrow">ACCOUNT PROTECTION</span>
                            <h2 id="accountSecurityHeading">Account Security</h2>
                            <p>Manage your password and protect your GCT account.</p>
                            <div class="account-security-identity">
                                <span>{{ $user->name }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $displayRole }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="account-security-highlights">
                        <div class="account-security-highlight">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <span><small>Authentication</small><strong>Password</strong></span>
                        </div>
                        <div class="account-security-highlight">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            <span><small>Last Recorded Login</small><strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</strong></span>
                        </div>
                    </div>
                </header>

                {{-- Unified Module Tabs --}}
                <nav class="account-nav-tabs account-security-tabs" aria-label="Account navigation">
                    <a href="{{ route('account.profile') }}" class="account-nav-tab">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('account.settings') }}" class="account-nav-tab active" aria-current="page">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Security & Password</span>
                    </a>
                    @if (! $user->must_change_password)
                        <a href="{{ route('onboarding.show', ['replay' => 1]) }}" class="account-nav-tab" data-system-tutorial-link>
                            <i class="fa-solid fa-graduation-cap"></i>
                            <span>System Tutorial</span>
                        </a>
                    @endif
                </nav>

                <div class="account-settings-grid account-security-grid">
                    {{-- Change Password Form --}}
                    <section class="account-card">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3>Change Password</h3>
                                <p>Update your system authentication password to protect your account access.</p>
                            </div>
                            <div class="account-card-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>
                        </div>

                        <form
                            action="{{ route('account.password.update', [], false) }}"
                            method="POST"
                            class="account-form"
                        >
                            @csrf
                            @method('PUT')

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
                                        placeholder="Enter your existing password"
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
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

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
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-field">
                                <label for="confirmPassword">
                                    <span>Confirm New Password</span>
                                    <span class="account-field-tag">Confirmation</span>
                                </label>
                                <div class="account-input-wrap">
                                    <i class="fa-solid fa-check-double account-input-icon"></i>
                                    <input
                                        id="confirmPassword"
                                        type="password"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        placeholder="Re-type your new password"
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
                                <button type="submit" class="account-primary-button">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </section>

                    {{-- Security Recommendations & Access Sidebar --}}
                    <aside class="account-card account-summary-card account-security-recommendations">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3>Security Best Practices</h3>
                                <p>Essential recommendations to safeguard your account and operational access.</p>
                            </div>
                            <div class="account-card-icon">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>
                        </div>

                        <a href="{{ route('account.personalization', [], false) }}" class="account-security-appearance">
                            <span class="account-security-appearance-icon" aria-hidden="true"><i class="fa-solid fa-circle-half-stroke"></i></span>
                            <span><strong>Appearance Preferences</strong><small>Choose Light, Dark, or System theme in Personalization.</small></span>
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>

                        {{-- Development-only, visual role preview: never alters authentication or permissions. --}}
                        @if (app()->environment('local')
                            && strtolower((string) $user->department) === 'admin'
                            && strtolower((string) $user->role) === 'head')
                            <section class="account-rbac-preview" data-rbac-preview aria-labelledby="accountRbacPreviewTitle">
                                <div class="account-rbac-preview-header">
                                    <div>
                                        <strong id="accountRbacPreviewTitle"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> RBAC Interface Preview</strong>
                                        <p>Local development only. Review departmental navigation without changing permissions.</p>
                                    </div>
                                    <label class="account-rbac-preview-toggle">
                                        <input type="checkbox" data-rbac-preview-toggle aria-controls="accountRbacPreviewDetails">
                                        <span>Enable preview</span>
                                    </label>
                                </div>
                                <div id="accountRbacPreviewDetails" data-rbac-preview-details hidden>
                                    <label for="accountRbacPreviewDepartment">Department interface</label>
                                    <select id="accountRbacPreviewDepartment" data-rbac-preview-select>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="operation">Operations</option>
                                        <option value="warehouse">Warehouse</option>
                                        <option value="purchase">Purchasing</option>
                                        <option value="admin">Administration</option>
                                    </select>
                                    <div class="account-rbac-preview-panel" data-rbac-preview-panel="maintenance">
                                        <strong>Maintenance navigation</strong>
                                        <p>Dashboard · Job Orders · Mechanic Management · Item Requests · Purchase Requisitions · Fuel Reports</p>
                                    </div>
                                    <div class="account-rbac-preview-panel" data-rbac-preview-panel="operation" hidden>
                                        <strong>Operations navigation</strong>
                                        <p>Dashboard · Trip Scheduling · Driver &amp; Bus Assignment · Trip Records · DDR · Incidents</p>
                                    </div>
                                    <div class="account-rbac-preview-panel" data-rbac-preview-panel="warehouse" hidden>
                                        <strong>Warehouse navigation</strong>
                                        <p>Dashboard · Inventory · Stock Movements · Part Requests · Incoming Deliveries</p>
                                    </div>
                                    <div class="account-rbac-preview-panel" data-rbac-preview-panel="purchase" hidden>
                                        <strong>Purchasing navigation</strong>
                                        <p>Dashboard · Maintenance Requests · Inventory Restock · Purchase Orders</p>
                                    </div>
                                    <div class="account-rbac-preview-panel" data-rbac-preview-panel="admin" hidden>
                                        <strong>Administration navigation</strong>
                                        <p>Dashboard · Account Management · Roles &amp; Permissions · System Monitoring · Analytics · Settings</p>
                                    </div>
                                    <p class="account-rbac-preview-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Preview only. Your actual account role, sidebar, routes, API authorization, and approval rules remain enforced.</p>
                                </div>
                            </section>
                        @endif

                        <ul class="account-tips-list">
                            <li class="account-tip-item">
                                <div class="account-tip-icon">
                                    <i class="fa-solid fa-fingerprint"></i>
                                </div>
                                <div class="account-tip-text">
                                    <strong>Use a Unique Passphrase</strong>
                                    <span>Avoid reusing passwords from personal or external accounts on FROMS.</span>
                                </div>
                            </li>

                            <li class="account-tip-item">
                                <div class="account-tip-icon">
                                    <i class="fa-solid fa-display"></i>
                                </div>
                                <div class="account-tip-text">
                                    <strong>Lock Workstation</strong>
                                    <span>Always sign out or lock your terminal before leaving your station unattended.</span>
                                </div>
                            </li>

                            <li class="account-tip-item">
                                <div class="account-tip-icon">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div class="account-tip-text">
                                    <strong>Protect Verification Codes</strong>
                                    <span>Never share account verification codes or password-reset links.</span>
                                </div>
                            </li>
                        </ul>

                        <h4 class="account-security-access-title">Account Information</h4>
                        <div class="account-tile-list account-security-access" >
                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Signed In As</span>
                                    <strong>{{ $user->email }}</strong>
                                </div>
                            </div>
                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon"><i class="fa-solid fa-id-badge" aria-hidden="true"></i></div>
                                <div class="account-meta-tile-copy">
                                    <span>System ID</span>
                                    <strong>{{ $displayUserId }}</strong>
                                </div>
                            </div>
                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div>
                                <div class="account-meta-tile-copy">
                                    <span>Department</span>
                                    <strong>{{ $department }}</strong>
                                </div>
                            </div>
                        </div>

                        <a
                            href="{{ route('account.profile', [], false) }}"
                            class="account-secondary-link"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Return to My Profile</span>
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
