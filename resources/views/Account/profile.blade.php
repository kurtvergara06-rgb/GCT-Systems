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
                <div class="account-hero-card">
                    <div class="account-hero-identity">
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

                <nav class="account-profile-links" aria-label="Related account pages">
                    <a href="{{ route('account.personalization') }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Personalization</a>
                    <a href="{{ route('account.preferences') }}"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
                    <a href="{{ route('account.settings') }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Security</a>
                </nav>

                <div class="account-grid account-profile-grid">
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


                            <div class="account-form-actions">
                                <button type="submit" class="account-primary-button">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>


                    </section>

                    <aside class="account-profile-aside" aria-label="Account access and history">
                        <section class="account-access-section" aria-labelledby="accountAccessHeading">
                            <div class="account-access-header">
                                <div>
                                    <h3 id="accountAccessHeading">System Access</h3>
                                    <p>Assigned by the system administrator. These details cannot be changed from your profile.</p>
                                </div>
                                <span class="account-access-status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $user->status ?: 'Active' }}</span>
                            </div>
                            <dl class="account-access-grid">
                                <div>
                                    <dt>System ID</dt>
                                    <dd>{{ $displayUserId }}</dd>
                                </div>
                                <div>
                                    <dt>Department</dt>
                                    <dd>{{ $department }}</dd>
                                </div>
                                <div>
                                    <dt>Role</dt>
                                    <dd>{{ $displayRole }}</dd>
                                </div>
                            </dl>
                        </section>
                    {{-- Account metadata stays available without a permanent second column. --}}
                    <details class="account-card account-summary-card account-details-disclosure">
                        <summary class="account-details-summary">
                            <span class="account-details-heading">
                                <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
                                <span>
                                    <strong>Account History</strong>
                                    <small>Membership and last recorded sign-in</small>
                                </span>
                            </span>
                            <i class="fa-solid fa-chevron-down account-details-chevron" aria-hidden="true"></i>
                        </summary>

                        <div class="account-tile-list">
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
                    </details>
                    </aside>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
