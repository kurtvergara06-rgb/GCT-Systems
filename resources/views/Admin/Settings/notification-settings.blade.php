<x-layout.app
    title="FROMS - Notification Settings"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Settings/notification-settings.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main notification-settings-page">

            <x-layout.topbar
                title="Notification Settings"
                subtitle="Control which system events generate alerts and how administrators receive them"
                notification-count="6"
            />


            {{-- =====================================================
                INTRO
            ====================================================== --}}
            <section class="notification-intro">

                <div class="notification-intro-main">

                    <div class="notification-intro-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>

                    <div>
                        <span class="intro-kicker">
                            Notification Control
                        </span>

                        <h2>
                            Manage FROMS Alert Preferences
                        </h2>

                        <p>
                            Configure notification channels, alert priorities, module events,
                            and delivery preferences for administrative monitoring.
                        </p>
                    </div>

                </div>

                <div class="notification-master-control">

                    <div>
                        <strong>System Notifications</strong>
                        <span>Enable notification generation across FROMS.</span>
                    </div>

                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="switch-slider"></span>
                    </label>

                </div>

            </section>


            {{-- =====================================================
                CHANNELS + PRIORITY
            ====================================================== --}}
            <section class="notification-main-grid">

                {{-- CHANNELS --}}
                <article class="notification-panel">

                    <div class="panel-heading">

                        <div class="panel-icon blue">
                            <i class="fa-solid fa-satellite-dish"></i>
                        </div>

                        <div>
                            <span>Delivery</span>
                            <h2>Notification Channels</h2>
                            <p>
                                Select how alerts should be delivered to administrators.
                            </p>
                        </div>

                    </div>


                    <div class="channel-list">

                        <div class="channel-row">

                            <div class="channel-identity">

                                <div class="channel-icon in-app">
                                    <i class="fa-solid fa-bell"></i>
                                </div>

                                <div>
                                    <strong>In-App Notifications</strong>
                                    <span>
                                        Display alerts in the FROMS notification center.
                                    </span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="channel-row">

                            <div class="channel-identity">

                                <div class="channel-icon email">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>

                                <div>
                                    <strong>Email Notifications</strong>
                                    <span>
                                        Send selected important alerts through email.
                                    </span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="channel-row disabled">

                            <div class="channel-identity">

                                <div class="channel-icon sms">
                                    <i class="fa-solid fa-comment-sms"></i>
                                </div>

                                <div>
                                    <strong>SMS Notifications</strong>
                                    <span>
                                        Mobile SMS delivery is not currently configured.
                                    </span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox">
                                <span class="switch-slider"></span>
                            </label>

                        </div>

                    </div>

                </article>


                {{-- PRIORITY RULES --}}
                <article class="notification-panel">

                    <div class="panel-heading">

                        <div class="panel-icon red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div>
                            <span>Priority Rules</span>
                            <h2>Alert Severity</h2>
                            <p>
                                Control which severity levels should generate notifications.
                            </p>
                        </div>

                    </div>


                    <div class="severity-list">

                        <div class="severity-row critical">

                            <div class="severity-indicator">
                                <i class="fa-solid fa-circle-exclamation"></i>
                            </div>

                            <div class="severity-content">
                                <strong>Critical Alerts</strong>
                                <span>
                                    PMS threshold reached, critical stock, system or security risks.
                                </span>
                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="severity-row warning">

                            <div class="severity-indicator">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>

                            <div class="severity-content">
                                <strong>Warning Alerts</strong>
                                <span>
                                    PMS approaching, low stock, pending administrative actions.
                                </span>
                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="severity-row info">

                            <div class="severity-indicator">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>

                            <div class="severity-content">
                                <strong>Information Alerts</strong>
                                <span>
                                    Completed processing, status updates, and general system activity.
                                </span>
                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>

                    </div>

                </article>

            </section>


            {{-- =====================================================
                MODULE PREFERENCES
            ====================================================== --}}
            <section class="module-preferences-section">

                <div class="section-heading">

                    <div>
                        <span class="section-kicker">
                            Module Events
                        </span>

                        <h2>Notification Preferences by Module</h2>

                        <p>
                            Choose which operational events should notify administrators.
                        </p>
                    </div>

                    <span class="enabled-count">
                        12 Enabled Events
                    </span>

                </div>


                <div class="module-preference-grid">

                    {{-- MAINTENANCE --}}
                    <article class="module-preference-card maintenance">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </div>

                                <div>
                                    <strong>Maintenance</strong>
                                    <span>PMS and job order alerts</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>PMS threshold reached</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>PMS approaching threshold</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Job order status changes</span>
                            </label>

                        </div>

                    </article>


                    {{-- INVENTORY --}}
                    <article class="module-preference-card inventory">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>

                                <div>
                                    <strong>Warehouse</strong>
                                    <span>Inventory and stock alerts</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Critical stock reached</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Low stock warning</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>Incoming delivery recorded</span>
                            </label>

                        </div>

                    </article>


                    {{-- PURCHASE --}}
                    <article class="module-preference-card purchase">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                </div>

                                <div>
                                    <strong>Purchase</strong>
                                    <span>PR and PO activity</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>New purchase request</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Purchase order status updated</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>Scheduled purchase reminder</span>
                            </label>

                        </div>

                    </article>


                    {{-- OPERATION --}}
                    <article class="module-preference-card operation">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-bus"></i>
                                </div>

                                <div>
                                    <strong>Operation</strong>
                                    <span>Fleet and attendance activity</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Bus status changed</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>Driver attendance issue</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>Trip schedule updated</span>
                            </label>

                        </div>

                    </article>


                    {{-- DATA MANAGEMENT --}}
                    <article class="module-preference-card data">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-file-import"></i>
                                </div>

                                <div>
                                    <strong>Data Management</strong>
                                    <span>Batch processing and import alerts</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Batch processing completed</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>File processing failed</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>Import completed</span>
                            </label>

                        </div>

                    </article>


                    {{-- ADMIN --}}
                    <article class="module-preference-card admin">

                        <div class="module-card-header">

                            <div class="module-title">

                                <div class="module-icon">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>

                                <div>
                                    <strong>Administration</strong>
                                    <span>Account and system events</span>
                                </div>

                            </div>

                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="switch-slider"></span>
                            </label>

                        </div>


                        <div class="event-list">

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>New account request</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox" checked>
                                <span>Security-related activity</span>
                            </label>

                            <label class="event-option">
                                <input type="checkbox">
                                <span>User status changed</span>
                            </label>

                        </div>

                    </article>

                </div>

            </section>


            {{-- =====================================================
                DELIVERY SCHEDULE
            ====================================================== --}}
            <section class="delivery-settings-panel">

                <div class="delivery-heading">

                    <div class="delivery-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <span>Delivery Timing</span>
                        <h2>Notification Schedule</h2>
                        <p>
                            Configure alert timing and quiet-hour behavior.
                        </p>
                    </div>

                </div>


                <div class="delivery-settings-grid">

                    <div class="delivery-setting">

                        <label for="delivery_mode">
                            Delivery Mode
                        </label>

                        <select id="delivery_mode">
                            <option selected>Immediate</option>
                            <option>Hourly Digest</option>
                            <option>Daily Digest</option>
                        </select>

                        <small>
                            Critical alerts should remain immediate.
                        </small>

                    </div>


                    <div class="delivery-setting">

                        <label for="digest_time">
                            Daily Digest Time
                        </label>

                        <input
                            type="time"
                            id="digest_time"
                            value="08:00"
                        >

                        <small>
                            Used only when Daily Digest is selected.
                        </small>

                    </div>


                    <div class="quiet-hours-card">

                        <div>

                            <strong>Quiet Hours</strong>

                            <span>
                                Reduce non-critical notification delivery during selected hours.
                            </span>

                        </div>

                        <label class="switch">
                            <input type="checkbox">
                            <span class="switch-slider"></span>
                        </label>

                    </div>

                </div>

            </section>


            {{-- =====================================================
                SAVE BAR
            ====================================================== --}}
            <section class="notification-save-bar">

                <div>
                    <strong>Notification Settings</strong>
                    <span>Review your notification preferences before saving.</span>
                </div>

                <div class="save-actions">

                    <button
                        type="button"
                        class="reset-button"
                    >
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </button>

                    <button
                        type="button"
                        class="save-button"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes
                    </button>

                </div>

            </section>

        </main>

    </div>

</x-layout.app>