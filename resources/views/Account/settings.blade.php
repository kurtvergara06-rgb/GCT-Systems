<x-layout.app
    title="FROMS - Account Settings"
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
                subtitle="Manage your sign-in security and account preferences"
            />

            <section class="account-content">
                <div class="account-settings-grid">
                    <section class="account-card">
                        <div class="account-card-header">
                            <div>
                                <h3>Change Password</h3>
                                <p>Use a strong password that you do not use on other systems.</p>
                            </div>
                            <i class="fa-solid fa-key"></i>
                        </div>

                        <form
                            action="{{ route('account.password.update', [], false) }}"
                            method="POST"
                            class="account-form"
                        >
                            @csrf
                            @method('PUT')

                            <div class="account-field">
                                <label for="currentPassword">Current Password</label>
                                <input
                                    id="currentPassword"
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                >
                                @error('current_password')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-field">
                                <label for="newPassword">New Password</label>
                                <input
                                    id="newPassword"
                                    type="password"
                                    name="password"
                                    autocomplete="new-password"
                                    required
                                >
                                <small>Use at least 8 characters.</small>
                                @error('password')
                                    <span class="account-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="account-field">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input
                                    id="confirmPassword"
                                    type="password"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>

                            <div class="account-form-actions">
                                <button type="submit" class="account-primary-button">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </section>

                    <aside class="account-card account-summary-card">
                        <div class="account-card-header">
                            <div>
                                <h3>Account Access</h3>
                                <p>Your department and role determine the modules available to you.</p>
                            </div>
                            <i class="fa-solid fa-user-lock"></i>
                        </div>

                        <div class="account-detail-list">
                            <div class="account-detail-row">
                                <span>Name</span>
                                <strong>{{ $user->name }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Email</span>
                                <strong>{{ $user->email }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Department</span>
                                <strong>{{ $department }}</strong>
                            </div>
                            <div class="account-detail-row">
                                <span>Role</span>
                                <strong>{{ $displayRole }}</strong>
                            </div>
                        </div>

                        <a
                            href="{{ route('account.profile', [], false) }}"
                            class="account-secondary-link"
                        >
                            <i class="fa-solid fa-user"></i>
                            Back to My Profile
                        </a>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
