<x-layout.app title="FROMS - Personalization" :assets="['resources/css/Account/account.css']">
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
            <x-layout.topbar title="Personalization" subtitle="Choose how GCT-Systems looks and feels on this browser" />
            <section class="account-content account-preferences-page">
                <nav class="account-preference-breadcrumb" aria-label="Account navigation">
                    <a href="{{ route('account.profile') }}">My Profile</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <span>Personalization</span>
                </nav>
                <section class="account-card account-preference-panel" aria-labelledby="personalizationTitle">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="personalizationTitle">Display preferences</h3>
                            <p>These preferences affect your interface on this browser only. They do not change system data or permissions.</p>
                        </div>
                        <div class="account-card-icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
                    </div>
                    <div class="account-preference-row">
                        <div>
                            <strong>Table density</strong>
                            <p>Choose comfortable spacing or show more records at once.</p>
                        </div>
                        <select id="gctDensityPreference" data-gct-preference="density" aria-label="Table density">
                            <option value="comfortable">Comfortable</option>
                            <option value="compact">Compact</option>
                        </select>
                    </div>
                    <div class="account-preference-row">
                        <div>
                            <strong>Reduce interface motion</strong>
                            <p>Minimize transitions and animated movement, including sidebar effects.</p>
                        </div>
                        <label class="account-preference-check">
                            <input type="checkbox" id="gctMotionPreference" data-gct-preference="reducedMotion">
                            <span>Reduce motion</span>
                        </label>
                    </div>
                    <p class="account-preference-status" data-gct-preferences-status role="status" aria-live="polite"></p>
                    <a class="account-secondary-link account-preference-link" href="{{ route('account.preferences') }}">
                        <i class="fa-solid fa-gear" aria-hidden="true"></i> General Settings
                    </a>
                </section>
            </section>
        </main>
    </div>
</x-layout.app>
