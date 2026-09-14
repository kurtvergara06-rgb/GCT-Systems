<x-layout.app
    title="FROMS - My Profile"
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
                title="My Profile"
                subtitle="View and manage your personal account identity and system details"
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
                                <span>Account Health</span>
                                <strong>Verified Active</strong>
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
                    <a href="{{ route('account.profile') }}" class="account-nav-tab active">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('account.settings') }}" class="account-nav-tab">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Security & Password</span>
                    </a>
                </nav>

                <div class="account-grid">
                    {{-- Personal Information Form --}}
                    <section class="account-card">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3>Personal Information</h3>
                                <p>Update your personal identity details connected to your system profile.</p>
                            </div>
                            <div class="account-card-icon">
                                <i class="fa-regular fa-id-card"></i>
                            </div>
                        </div>

                        <form
                            action="{{ route('account.profile.update', [], false) }}"
                            method="POST"
                            class="account-form"
                        >
                            @csrf
                            @method('PUT')

                            <div class="account-field">
                                <label for="accountName">
                                    <span>Full Name</span>
                                    <span class="account-field-tag">Required</span>
                                </label>
                                <div class="account-input-wrap">
                                    <i class="fa-regular fa-user account-input-icon"></i>
                                    <input
                                        id="accountName"
                                        type="text"
                                        name="name"
                                        value="{{ old('name', $user->name) }}"
                                        placeholder="Enter your full name"
                                        required
                                    >
                                </div>
                                @error('name')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-field">
                                <label for="accountEmail">
                                    <span>Email Address</span>
                                    <span class="account-field-tag">System Sign-in</span>
                                </label>
                                <div class="account-input-wrap">
                                    <i class="fa-regular fa-envelope account-input-icon"></i>
                                    <input
                                        id="accountEmail"
                                        type="email"
                                        name="email"
                                        value="{{ old('email', $user->email) }}"
                                        placeholder="user@gct.test"
                                        required
                                    >
                                </div>
                                @error('email')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-form-row">
                                <div class="account-field">
                                    <label>
                                        <span>Department</span>
                                        <span class="account-field-tag">Administrative</span>
                                    </label>
                                    <div class="account-input-wrap">
                                        <i class="fa-solid {{ $icon }} account-input-icon"></i>
                                        <input type="text" value="{{ $department }}" readonly>
                                        <span class="account-locked-pill">
                                            <i class="fa-solid fa-lock"></i> Locked
                                        </span>
                                    </div>
                                    <small><i class="fa-solid fa-circle-info"></i> Department module assignment is managed by Admin.</small>
                                </div>

                                <div class="account-field">
                                    <label>
                                        <span>Role & Title</span>
                                        <span class="account-field-tag">Administrative</span>
                                    </label>
                                    <div class="account-input-wrap">
                                        <i class="fa-solid fa-shield-halved account-input-icon"></i>
                                        <input type="text" value="{{ $displayRole }}" readonly>
                                        <span class="account-locked-pill">
                                            <i class="fa-solid fa-lock"></i> Locked
                                        </span>
                                    </div>
                                    <small><i class="fa-solid fa-circle-info"></i> Role-based permissions are managed by Admin.</small>
                                </div>
                            </div>

                            <div class="account-form-actions">
                                <button type="submit" class="account-primary-button">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </section>

                    {{-- Account Details Sidebar --}}
                    <aside class="account-card account-summary-card">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3>Account Overview</h3>
                                <p>System credentials and operational parameters for your account.</p>
                            </div>
                            <div class="account-card-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                        </div>

                        <div class="account-tile-list">
                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid fa-id-badge"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>System ID</span>
                                    <strong>{{ $displayUserId }}</strong>
                                </div>
                            </div>

                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Account Status</span>
                                    <strong>{{ $user->status ?: 'Active' }}</strong>
                                </div>
                            </div>

                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid {{ $icon }}"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Department & Role</span>
                                    <strong>{{ $displayRole }}</strong>
                                </div>
                            </div>

                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-regular fa-calendar-check"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Member Since</span>
                                    <strong>{{ $user->created_at?->format('M d, Y') ?? 'System Setup' }}</strong>
                                </div>
                            </div>

                            <div class="account-meta-tile">
                                <div class="account-meta-tile-icon">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <div class="account-meta-tile-copy">
                                    <span>Last Login Recorded</span>
                                    <strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</strong>
                                </div>
                            </div>
                        </div>

                        <a
                            href="{{ route('account.settings', [], false) }}"
                            class="account-secondary-link"
                        >
                            <i class="fa-solid fa-key"></i>
                            <span>Manage Password & Security</span>
                            <i class="fa-solid fa-arrow-right" style="margin-left: auto; font-size: 11px;"></i>
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
