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
            <x-layout.topbar title="Personalization" subtitle="Make GCT-Systems comfortable for your daily work" />
            <section class="account-content account-preferences-page account-personalization-page">
                <nav class="account-preference-breadcrumb" aria-label="Account navigation">
                    <a href="{{ route('account.profile') }}">My Profile</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    <span>Personalization</span>
                </nav>
                <header class="gct-personalization-intro">
                    <div class="gct-personalization-icon"><i class="fa-solid fa-palette" aria-hidden="true"></i></div>
                    <div>
                        <h2>Make your workspace yours</h2>
                        <p>Adjust appearance, typography, and table spacing without changing business records or permissions.</p>
                    </div>
                    <span class="gct-preference-device-label"><i class="fa-solid fa-laptop" aria-hidden="true"></i> This browser</span>
                </header>

                <section class="account-card account-preference-panel gct-preference-section" aria-labelledby="appearanceHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="appearanceHeading"><i class="fa-solid fa-moon" aria-hidden="true"></i> Appearance</h3>
                            <p>Choose a light, dark, or device-matched interface.</p>
                        </div>
                    </div>
                    <fieldset class="gct-theme-options" data-gct-theme-options>
                        <legend class="gct-visually-hidden">Color theme</legend>
                        <label class="gct-theme-choice">
                            <input type="radio" name="gct_theme" value="light" data-gct-preference="theme" checked>
                            <span class="gct-theme-thumbnail gct-theme-thumbnail-light" aria-hidden="true"><span></span><i></i><b></b></span>
                            <span class="gct-theme-choice-caption"><strong>Light</strong><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                        </label>
                        <label class="gct-theme-choice">
                            <input type="radio" name="gct_theme" value="dark" data-gct-preference="theme">
                            <span class="gct-theme-thumbnail gct-theme-thumbnail-dark" aria-hidden="true"><span></span><i></i><b></b></span>
                            <span class="gct-theme-choice-caption"><strong>Dark</strong><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                        </label>
                        <label class="gct-theme-choice">
                            <input type="radio" name="gct_theme" value="system" data-gct-preference="theme">
                            <span class="gct-theme-thumbnail gct-theme-thumbnail-system" aria-hidden="true"><span></span><i></i><b></b></span>
                            <span class="gct-theme-choice-caption"><strong>System</strong><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                        </label>
                    </fieldset>
                </section>

                <section class="account-card account-preference-panel gct-preference-section" aria-labelledby="typographyHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="typographyHeading"><i class="fa-solid fa-font" aria-hidden="true"></i> Typography</h3>
                            <p>Make labels and content easier to read during long shifts.</p>
                        </div>
                    </div>
                    <div class="account-preference-row">
                        <div><strong>Font family</strong><p>Select the typeface used for GCT interface text.</p></div>
                        <select id="gctFontPreference" data-gct-preference="font" aria-label="Font family">
                            <option value="poppins">Poppins (GCT default)</option>
                            <option value="inter">Inter</option>
                            <option value="roboto">Roboto</option>
                        </select>
                    </div>
                    <div class="account-preference-row">
                        <div><strong>Text size</strong><p>Adjust the size of common page text and controls.</p></div>
                        <select id="gctFontSizePreference" data-gct-preference="fontSize" aria-label="Text size">
                            <option value="small">Small</option>
                            <option value="default">Default</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                    <div class="gct-preference-preview" aria-label="Typography preview">
                        <div><i class="fa-solid fa-eye" aria-hidden="true"></i> Live preview</div>
                        <strong>Trip Schedule Overview</strong>
                        <p>Review assignments, schedules, and fleet information with comfortable, readable text.</p>
                        <span>Ready for dispatch</span>
                    </div>
                </section>

                <section class="account-card account-preference-panel gct-preference-section" aria-labelledby="workspaceHeading">
                    <div class="account-card-header">
                        <div class="account-card-header-copy">
                            <h3 id="workspaceHeading"><i class="fa-solid fa-table-cells" aria-hidden="true"></i> Workspace</h3>
                            <p>Choose spacing and animation preferences for everyday operations.</p>
                        </div>
                    </div>
                    <div class="account-preference-row">
                        <div><strong>Table density</strong><p>Compact shows more rows; Comfortable gives records more breathing room.</p></div>
                        <select id="gctDensityPreference" data-gct-preference="density" aria-label="Table density">
                            <option value="comfortable">Comfortable</option>
                            <option value="compact">Compact</option>
                        </select>
                    </div>
                    <div class="account-preference-row">
                        <div><strong>Reduce interface motion</strong><p>Minimize nonessential transitions and animations.</p></div>
                        <label class="account-preference-check"><input type="checkbox" id="gctMotionPreference" data-gct-preference="reducedMotion"><span>Reduce motion</span></label>
                    </div>
                </section>
                <div class="gct-preferences-footer">
                    <div><strong>Saved automatically</strong><p>Preferences are kept for your account in this browser, not synced across devices.</p></div>
                    <button type="button" class="account-reset-preferences" data-gct-reset-preferences><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Restore defaults</button>
                    <a class="account-secondary-link account-preference-link" href="{{ route('account.preferences') }}"><i class="fa-solid fa-gear" aria-hidden="true"></i> General Settings</a>
                </div>
                <p class="account-preference-status" data-gct-preferences-status role="status" aria-live="polite"></p>
            </section>
        </main>
    </div>
</x-layout.app>
