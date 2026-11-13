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
                title="My Profile"
                subtitle="Manage your profile details and review your system access"
            />

            <section class="account-content">
                {{-- Compact employee identity header. Photo crop and upload are unchanged. --}}
                <div class="account-hero-card" aria-label="Profile summary">
                    <div class="account-hero-identity">
                        <span class="account-hero-avatar" aria-hidden="true">
                            @if ($profilePhotoUrl)
                                <img src="{{ $profilePhotoUrl }}" alt="" width="96" height="96">
                            @else
                                <span>{{ $initials }}</span>
                            @endif
                        </span>
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
                                <x-ui.status-badge
                                    :status="$user->status ?: 'Active'"
                                    class="account-badge account-badge-status"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="account-profile-metrics" aria-label="Account overview">
                        <div><i class="fa-regular fa-id-card" aria-hidden="true"></i><span><small>System ID</small><strong>{{ $displayUserId }}</strong></span></div>
                        <div><i class="fa-regular fa-calendar" aria-hidden="true"></i><span><small>Member Since</small><strong>{{ $user->created_at?->format('M d, Y') ?? 'System Setup' }}</strong></span></div>
                        <div><i class="fa-regular fa-clock" aria-hidden="true"></i><span><small>Last Login</small><strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</strong></span></div>
                    </div>
                </div>

                <div class="account-crop-modal" data-avatar-crop-modal hidden>
                    <div class="account-crop-backdrop" data-avatar-crop-cancel></div>
                    <section
                        class="account-crop-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="avatarCropTitle"
                    >
                        <div class="account-crop-header">
                            <div>
                                <span class="account-crop-eyebrow">Profile photo</span>
                                <h2 id="avatarCropTitle">Crop your photo</h2>
                                <p>Drag the image to reposition it. Use the slider to zoom.</p>
                            </div>
                            <button
                                type="button"
                                class="account-crop-close"
                                data-avatar-crop-cancel
                                aria-label="Close crop dialog"
                            >&times;</button>
                        </div>

                        <div class="account-crop-workspace">
                            <div class="account-crop-viewport" data-avatar-crop-viewport>
                                <img data-avatar-crop-image alt="Photo selected for cropping" draggable="false">
                                <div class="account-crop-mask" aria-hidden="true"></div>
                            </div>
                        </div>

                        <label class="account-crop-zoom" for="avatarCropZoom">
                            <i class="fa-solid fa-image" aria-hidden="true"></i>
                            <input
                                id="avatarCropZoom"
                                type="range"
                                min="1"
                                max="3"
                                step="0.01"
                                value="1"
                                data-avatar-crop-zoom
                            >
                            <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                        </label>

                        <div class="account-crop-actions">
                            <button type="button" class="account-crop-button secondary" data-avatar-crop-cancel>
                                Cancel
                            </button>
                            <button type="button" class="account-crop-button primary" data-avatar-crop-apply>
                                <i class="fa-solid fa-crop-simple"></i>
                                Crop & Upload
                            </button>
                        </div>
                    </section>
                </div>

                <nav class="account-nav-tabs account-profile-tabs" aria-label="Account sections">
                    <a href="{{ route('account.profile') }}" class="account-nav-tab active" aria-current="page"><i class="fa-solid fa-user" aria-hidden="true"></i> Profile</a>
                    <a href="{{ route('account.personalization') }}" class="account-nav-tab"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Personalization</a>
                    <a href="{{ route('account.preferences') }}" class="account-nav-tab"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
                    <a href="{{ route('account.settings') }}" class="account-nav-tab"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Security &amp; Password</a>
                    <a href="{{ route('onboarding.show', ['replay' => 1]) }}" class="account-nav-tab" data-system-tutorial-link><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> System Tutorial</a>
                </nav>

                <div class="account-grid account-profile-grid">
                    <div class="account-profile-primary">
                    {{-- Personal Information Form --}}
                    <section class="account-card" id="accountPersonalInformation" aria-labelledby="personalInformationHeading">
                        <div class="account-card-header">
                            <div class="account-card-header-copy">
                                <h3 id="personalInformationHeading">Personal Information</h3>
                                <p>Update your personal identity details connected to your system profile.</p>
                            </div>
                            <a class="account-profile-edit-link" href="#accountName" aria-label="Edit your profile information">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Profile
                            </a>
                        </div>

                        <div class="account-profile-photo-fields">
                            <div class="account-profile-photo">
                                <form
                                action="{{ route('account.profile.photo.update', [], false) }}"
                                method="POST"
                                enctype="multipart/form-data"
                                class="account-avatar-form"
                                data-avatar-upload-form
                            >
                                @csrf
                                @method('PUT')
                                <button
                                    type="button"
                                    class="account-avatar-wrapper account-avatar-trigger"
                                    data-avatar-trigger
                                    aria-label="Choose a new profile photo"
                                >
                                    <span class="account-avatar-large" data-avatar-preview>
                                        @if ($profilePhotoUrl)
                                            <img src="{{ $profilePhotoUrl }}" alt="{{ $user->name }} profile photo">
                                        @else
                                            <span data-avatar-initials>{{ $initials }}</span>
                                        @endif
                                    </span>
                                    <span class="account-avatar-edit" aria-hidden="true">
                                        <i class="fa-solid fa-camera"></i>
                                    </span>
                                    <span class="account-avatar-status-dot" title="Active Account"></span>
                                </button>
                                <input
                                    type="file"
                                    name="avatar"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="account-avatar-input"
                                    data-avatar-input
                                >
                            </form>
                                <strong>Profile Photo</strong>
                                <small>JPG, PNG or WebP (max 2 MB)</small>
                                <button type="button" class="account-photo-change-button" data-avatar-change-button><i class="fa-solid fa-upload" aria-hidden="true"></i> Change Photo</button>
                                <div class="account-avatar-help">
                                <span>Choose a photo (JPG, PNG or WebP, up to 2 MB).</span>
                                @if ($profilePhotoUrl)
                                    <form
                                        action="{{ route('account.profile.photo.destroy', [], false) }}"
                                        method="POST"
                                        data-confirm-form
                                        data-confirm-title="Remove Profile Photo"
                                        data-confirm-message="Remove your current profile photo and return to your initials?"
                                        data-confirm-button="Remove Photo"
                                        data-confirm-type="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="account-avatar-remove">Remove photo</button>
                                    </form>
                                @endif
                            </div>
                            @error('avatar')
                                <span class="account-field-error account-avatar-error">{{ $message }}</span>
                            @enderror
                            <span class="account-field-error account-avatar-error" data-avatar-client-error hidden></span>
                            </div>
                            <div class="account-profile-fields">
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

                            <div class="account-form-actions">
                                <button type="submit" class="account-primary-button">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                            </div>
                        </div>
                    </section>
                    <section class="account-card account-quick-actions" aria-labelledby="accountQuickActionsHeading">
                        <div class="account-quick-actions-heading">
                            <span class="account-section-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
                            <div><h3 id="accountQuickActionsHeading">Quick Actions</h3><p>Manage your existing account settings.</p></div>
                        </div>
                        <div class="account-quick-action-links">
                            <a href="{{ route('account.settings') }}"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Security &amp; Password</span></a>
                            <a href="#accountHistory"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Account History</span></a>
                            <a href="{{ route('account.personalization') }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Personalization</span></a>
                        </div>
                    </section>
                    </div>

                    <aside class="account-profile-aside" aria-label="Account access and history">
                        <section class="account-access-section" aria-labelledby="accountAccessHeading">
                            <div class="account-access-header">
                                <div>
                                    <h3 id="accountAccessHeading">System Access</h3>
                                    <p>Assigned by the system administrator. These details cannot be changed from your profile.</p>
                                </div>

                            </div>
                            <dl class="account-access-grid">
                                <div><dt><i class="fa-solid fa-building" aria-hidden="true"></i> Department</dt><dd>{{ $department }}</dd></div>
                                <div><dt><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Role &amp; Title</dt><dd>{{ $displayRole }}</dd></div>
                                <div><dt><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Account Status</dt><dd>{{ $user->status ?: 'Active' }}</dd></div>
                            </dl>
                        </section>
                        {{-- Only persisted membership and last-login timestamps are displayed.
                             Never infer password changes or fabricate activity records. --}}
                        <section id="accountHistory" class="account-card account-activity-card" aria-labelledby="accountHistoryHeading">
                            <div class="account-activity-heading">
                                <span class="account-section-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                                <div>
                                    <h3 id="accountHistoryHeading">Account Activity</h3>
                                    <p>Recorded membership and sign-in information.</p>
                                </div>
                            </div>
                            <ol class="account-activity-timeline">
                                @if ($user->last_login_at)
                                    <li class="account-activity-entry is-recent">
                                        <span class="account-activity-marker" aria-hidden="true"></span>
                                        <div><strong>Last recorded sign-in</strong><small>{{ $user->last_login_at->format('M d, Y \a\t h:i A') }}</small></div>
                                    </li>
                                @endif
                                @if ($user->created_at)
                                    <li class="account-activity-entry">
                                        <span class="account-activity-marker" aria-hidden="true"></span>
                                        <div><strong>Account created</strong><small>{{ $user->created_at->format('M d, Y') }}</small></div>
                                    </li>
                                @endif
                                @if (! $user->last_login_at && ! $user->created_at)
                                    <li class="account-activity-empty">No account activity timestamps are recorded.</li>
                                @endif
                            </ol>
                            <p class="account-activity-note">Only available account timestamps are shown. This is not a complete audit log.</p>
                        </section>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
