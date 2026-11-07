<x-layout.app title="FROMS - Settings" :assets="['resources/css/Account/account.css']">
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
            <x-layout.topbar title="Settings" subtitle="Manage your account options and browser preferences" />
            <section class="account-content account-preferences-page">
                <nav class="account-preference-breadcrumb" aria-label="Account navigation">
                    <a href="{{ route('account.profile') }}">My Profile</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <span>Settings</span>
                </nav>
                <section class="account-card account-preference-panel" aria-labelledby="accountSettingsTitle">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="accountSettingsTitle">Account settings</h3>
                            <p>Your account options are separate from administrator-managed system settings.</p>
                        </div>
                        <div class="account-card-icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></div>
                    </div>
                    <div class="account-settings-links">
                        <a href="{{ route('account.profile') }}"><i class="fa-regular fa-user" aria-hidden="true"></i><span><strong>My Profile</strong><small>Edit your name, email and profile photo</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        <a href="{{ route('account.settings') }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Security &amp; Password</strong><small>Manage your account password</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        <a href="{{ route('account.personalization') }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span><strong>Personalization</strong><small>Table spacing and reduced motion</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        @if(\Illuminate\Support\Facades\Route::has('onboarding.show'))
                            <a href="{{ route('onboarding.show', ['replay' => 1]) }}" data-system-tutorial-link><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><span><strong>System Tutorial</strong><small>Replay your orientation guide</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </section>
                <section class="account-card account-preference-panel" aria-labelledby="browserPreferenceTitle">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="browserPreferenceTitle">Browser preferences</h3>
                            <p>Reset interface spacing and motion preferences stored locally in this browser for your account. This does not delete your profile, login or business records.</p>
                        </div>
                        <div class="account-card-icon"><i class="fa-solid fa-laptop" aria-hidden="true"></i></div>
                    </div>
                    <button type="button" class="account-reset-preferences" data-gct-reset-preferences>Reset display preferences</button>
                    <p class="account-preference-status" data-gct-preferences-status role="status" aria-live="polite"></p>
                </section>
            </section>
        </main>
    </div>
</x-layout.app>
