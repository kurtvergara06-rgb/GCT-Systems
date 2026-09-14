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
                @if (session('success'))
                    <div class="account-alert account-alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('success') }}</span>
                        <button type="button" class="account-alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                @endif

                {{-- Hero Identity Card --}}
                <div class="account-hero-card">
                    <div class="account-hero-identity">
                        <div class="account-avatar-wrapper">
                            <div class="account-avatar-large">{{ $initials }}</div>
                            <span class="account-avatar-status-dot" title="Active Account"></span>
                        </div>
                        <div class="account-hero-copy">
                            <h2>{{ $user->name }}</h2>
                            <div class="account-hero-badges">
                                <span class="account-badge account-badge-dept">
                                    <i class="fa-solid {{ $icon }}"></i>
                                    {{ $department }}
                                </span>
                                <span class="account-badge account-badge-role">
                                    <i class="fa-solid fa-user-shield"></i>
                                    {{ $displayRole }}
                                </span>
                                <span class="account-badge account-badge-status">
                                    <i class="fa-solid fa-circle-check"></i>
                                    {{ $user->status ?: 'Active' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="account-hero-meta">
                        <div class="account-hero-meta-item">
                            <div class="account-hero-meta-icon">
                                <i class="fa-solid fa-id-badge"></i>
                            </div>
                            <div class="account-hero-meta-text">
                                <span>System ID</span>
                                <strong>{{ $displayUserId }}</strong>
                            </div>
                        </div>
                        <div class="account-hero-meta-item">
                            <div class="account-hero-meta-icon">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div class="account-hero-meta-text">
                                <span>Security Level</span>
                                <strong>Standard Credential</strong>
                            </div>
                        </div>
                        <div class="account-hero-meta-item">
                            <div class="account-hero-meta-icon">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <div class="account-hero-meta-text">
                                <span>Last Activity</span>
                                <strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Current Session' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Unified Module Tabs --}}
                <nav class="account-nav-tabs">
                    <a href="{{ route('account.profile') }}" class="account-nav-tab">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('account.settings') }}" class="account-nav-tab active">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Security & Password</span>
                    </a>
                </nav>

                <div class="account-settings-grid">
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
                    <aside class="account-card account-summary-card">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3>Security Best Practices</h3>
                                <p>Essential recommendations to safeguard your account and operational access.</p>
                            </div>
                            <div class="account-card-icon">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>
                        </div>

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
                                    <strong>Activity Logging Active</strong>
                                    <span>All sign-ins and password updates are automatically recorded in system audit logs.</span>
                                </div>
                            </li>
                        </ul>

                        <div class="account-tile-list" style="margin-top: 14px;">
                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Signed In As</span>
                                    <strong>{{ $user->email }}</strong>
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
