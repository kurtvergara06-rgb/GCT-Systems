<x-layout.app title="FROMS - Settings" :assets="['resources/css/Account/account.css', 'resources/css/Main-styles/account-preferences.css']">
    @php
        $department = ucfirst(strtolower((string) $user->department));
        $icon = match (strtolower((string) $user->department)) {
            'maintenance' => 'fa-truck', 'operation', 'operations' => 'fa-route',
            'warehouse' => 'fa-warehouse', 'purchase', 'purchasing' => 'fa-cart-shopping',
            'admin', 'administration' => 'fa-shield-halved', default => 'fa-building',
        };
    @endphp
    <div class="app account-page">
        <x-layout.sidebar :department="$department" :icon="$icon" />
        <main class="main account-main">
            <x-layout.topbar title="Settings" subtitle="Notifications, account security and browser preferences" />
            <section class="account-content account-preferences-page gct-settings-page">
                <nav class="account-preference-breadcrumb" aria-label="Account navigation">
                    <a href="{{ route('account.profile') }}">My Profile</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <span>Settings</span>
                </nav>
                <header class="gct-settings-intro">
                    <span class="gct-settings-intro-icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></span>
                    <span><strong>My Account Settings</strong><small>Personal controls for your GCT-Systems workspace</small></span>
                </header>

                <section class="account-card account-preference-panel gct-settings-panel" aria-labelledby="notificationSettingsHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="notificationSettingsHeading"><i class="fa-regular fa-bell" aria-hidden="true"></i> Notifications</h3>
                            <p>Manage optional notification behavior while keeping essential fleet and approval updates visible.</p>
                        </div>
                    </div>
                    <div class="gct-settings-row">
                        <div><strong>In-app operational notifications</strong><p>Required incident, scheduling, approval and workflow alerts remain available.</p></div>
                        <span class="gct-settings-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> Always enabled</span>
                    </div>
                    <div class="gct-settings-row">
                        <div><strong>Notification sounds</strong><p>Play a brief sound when a new unread notification arrives after this browser is activated.</p></div>
                        <label class="account-preference-check">
                            <input type="checkbox" data-gct-notification-sound aria-label="Notification sounds">
                            <span>Enable sounds</span>
                        </label>
                    </div>
                    <p class="gct-settings-note">Sound is optional and applies only to this browser. Browser playback restrictions may prevent sound until you interact with the page.</p>
                    <p class="account-preference-status" data-gct-settings-status role="status" aria-live="polite"></p>
                </section>

                <section class="account-card account-preference-panel gct-settings-panel" aria-labelledby="securitySessionsHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="securitySessionsHeading"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Security &amp; Sessions</h3>
                            <p>Review your sign-ins and protect your own account.</p>
                        </div>
                    </div>
                    <a href="{{ route('account.settings') }}" class="gct-settings-row gct-settings-row-link">
                        <span><strong>Security &amp; Password</strong><p>Update your password through the existing protected form.</p></span>
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                    <div class="gct-settings-sessions-heading">
                        <strong>Active sessions</strong>
                        <small>Only sessions belonging to your account are shown.</small>
                    </div>
                    @if($sessionsSupported)
                        @forelse($sessions as $session)
                            <div class="gct-settings-session">
                                <span class="gct-session-icon"><i class="fa-solid {{ $session->current ? 'fa-laptop' : 'fa-desktop' }}" aria-hidden="true"></i></span>
                                <div class="gct-settings-session-details">
                                    <strong>{{ $session->current ? 'This device' : 'Other signed-in session' }}</strong>
                                    <small>{{ $session->device }}</small>
                                    <small>{{ $session->ip_address }} · Last active {{ $session->last_activity }}</small>
                                </div>
                                @if($session->current)
                                    <span class="gct-session-current">Current</span>
                                @else
                                    <form action="{{ route('account.sessions.revoke', $session->id) }}" method="POST"
                                        data-confirm-form
                                        data-confirm-title="Sign Out Device"
                                        data-confirm-message="Sign out this session? The other device will need to sign in again."
                                        data-confirm-button="Sign Out"
                                        data-confirm-type="warning">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="gct-settings-revoke">Sign out</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="gct-settings-empty">No active database sessions are currently listed for this account.</p>
                        @endforelse
                    @else
                        <p class="gct-settings-empty"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Session management is available when GCT uses the database session driver. Your current session remains protected by the existing login and logout system.</p>
                    @endif
                </section>

                <section class="account-card account-preference-panel gct-settings-panel" aria-labelledby="browserDisplayHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="browserDisplayHeading"><i class="fa-solid fa-display" aria-hidden="true"></i> Browser &amp; Display</h3>
                            <p>Personal appearance controls stay separate from fleet operations and permissions.</p>
                        </div>
                    </div>
                    <a href="{{ route('account.personalization') }}" class="gct-settings-row gct-settings-row-link">
                        <span><strong>Personalization</strong><p>Font, table density, reduced motion and saved appearance choices.</p></span>
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                    <div class="gct-settings-row">
                        <div><strong>Restore display defaults</strong><p>Reset appearance preferences for your account on this browser only.</p></div>
                        <button type="button" class="account-reset-preferences" data-gct-reset-preferences><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Reset preferences</button>
                    </div>
                    <p class="account-preference-status" data-gct-preferences-status role="status" aria-live="polite"></p>
                </section>
            </section>
        </main>
    </div>
</x-layout.app>
