<x-layout.app
    title="FROMS - My Profile"
    :assets="[
        'resources/css/Account/account.css',
        'resources/js/Main-js/sidebar.js'
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
                subtitle="View and manage your personal account information"
            />

            <section class="account-content">
                <div class="account-hero-card">
                    <div class="account-hero-identity">
                        <div class="account-avatar-large">{{ $initials }}</div>
                        <div class="account-hero-copy">
                            <h2>{{ $user->name }}</h2>
                            <p>{{ $displayRole }} · {{ $department }}</p>
                            <span class="account-status-badge">
                                <i class="fa-solid fa-circle-check"></i>
                                {{ $user->status ?: 'Active' }}
                            </span>
                        </div>
                    </div>

                    <div class="account-hero-meta">
                        <div class="account-hero-meta-item">
                            <span>User ID</span>
                            <strong>{{ $displayUserId }}</strong>
                        </div>
                        <div class="account-hero-meta-item">
                            <span>Last Login</span>
                            <strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="account-grid">
                    <section class="account-card">
                        <div class="account-card-header">
                            <div>
                                <h3>Personal Information</h3>
                                <p>Update the information connected to your account.</p>
                            </div>
                            <i class="fa-regular fa-id-card"></i>
                        </div>

                        <form
                            action="{{ route('account.profile.update', [], false) }}"
                            method="POST"
                            class="account-form"
                        >
                            @csrf
                            @method('PUT')

                            <div class="account-field">
                                <label for="accountName">Full Name</label>
                                <input
                                    id="accountName"
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $user->name) }}"
                                    required
                                >
                                @error('name')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-field">
                                <label for="accountEmail">Email Address</label>
                                <input
                                    id="accountEmail"
                                    type="email"
                                    name="email"
                                    value="{{ old('email', $user->email) }}"
                                    required
                                >
                                @error('email')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-form-row">
                                <div class="account-field">
                                    <label>Department</label>
                                    <input type="text" value="{{ $department }}" readonly>
                                    <small>Managed by the system administrator.</small>
                                </div>

                                <div class="account-field">
                                    <label>Role</label>
                                    <input type="text" value="{{ $displayRole }}" readonly>
                                    <small>Managed by the system administrator.</small>
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

                    <aside class="account-card account-summary-card">
                        <div class="account-card-header">
                            <div>
                                <h3>Account Details</h3>
                                <p>System information associated with your account.</p>
                            </div>
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>

                        <div class="account-detail-list">
                            <div class="account-detail-row">
                                <span>User ID</span>
                                <strong>{{ $displayUserId }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Account Status</span>
                                <strong>{{ $user->status ?: 'Active' }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Department</span>
                                <strong>{{ $department }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Role</span>
                                <strong>{{ $displayRole }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Account Created</span>
                                <strong>{{ $user->created_at?->format('M d, Y') ?? 'Not recorded' }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Last Login</span>
                                <strong>
                                    {{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Not recorded' }}
                                </strong>
                            </div>
                        </div>

                        <a
                            href="{{ route('account.settings', [], false) }}"
                            class="account-secondary-link"
                        >
                            <i class="fa-solid fa-gear"></i>
                            Open Account Settings
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
