<x-layout.app
    title="FROMS - General Settings"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/Settings/general-settings.css',
        'resources/js/Main-js/sidebar.js'
    ]"
>
    <div class="app">

        <x-layout.sidebar department="Admin" />

        <main class="main general-settings-page">

            <x-layout.topbar
                title="General Settings"
                subtitle="Configure system identity, regional preferences, and administrative defaults"
                notification-count="6"
            />

            {{-- =====================================================
                SETTINGS INTRO
            ====================================================== --}}
            <section class="settings-intro">

                <div class="settings-intro-icon">
                    <i class="fa-solid fa-sliders"></i>
                </div>

                <div class="settings-intro-content">

                    <span>
                        System Configuration
                    </span>

                    <h2>
                        General FROMS Settings
                    </h2>

                    <p>
                        Manage organization information and general system preferences
                        used across the FROMS modules.
                    </p>

                </div>

                <div class="settings-status">

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Configuration Active
                    </span>

                    <small>
                        Last updated Jul 24, 2026
                    </small>

                </div>

            </section>


            {{-- =====================================================
                SETTINGS WORKSPACE
            ====================================================== --}}
            <section class="settings-workspace">

                {{-- LEFT NAV --}}
                <aside class="settings-navigation">

                    <div class="settings-navigation-header">
                        <span>Settings</span>
                        <strong>General</strong>
                    </div>

                    <nav class="settings-nav-list">

                        <a href="#system-information" class="settings-nav-item active">

                            <div>
                                <i class="fa-solid fa-building"></i>
                            </div>

                            <span>
                                <strong>System Information</strong>
                                <small>Organization and system identity</small>
                            </span>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>


                        <a href="#regional-settings" class="settings-nav-item">

                            <div>
                                <i class="fa-solid fa-globe"></i>
                            </div>

                            <span>
                                <strong>Regional Settings</strong>
                                <small>Date, time, and regional preferences</small>
                            </span>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>


                        <a href="#system-defaults" class="settings-nav-item">

                            <div>
                                <i class="fa-solid fa-list-check"></i>
                            </div>

                            <span>
                                <strong>System Defaults</strong>
                                <small>Default system behavior</small>
                            </span>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>


                        <a href="#display-settings" class="settings-nav-item">

                            <div>
                                <i class="fa-solid fa-display"></i>
                            </div>

                            <span>
                                <strong>Display Preferences</strong>
                                <small>Interface and record display options</small>
                            </span>

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </nav>


                    <div class="settings-nav-note">

                        <div>
                            <i class="fa-solid fa-circle-info"></i>
                        </div>

                        <p>
                            Changes on this page affect general FROMS configuration
                            and should only be updated by authorized administrators.
                        </p>

                    </div>

                </aside>


                {{-- RIGHT CONTENT --}}
                <div class="settings-content">

                    {{-- =================================================
                        SYSTEM INFORMATION
                    ================================================== --}}
                    <section
                        id="system-information"
                        class="settings-section"
                    >

                        <div class="settings-section-heading">

                            <div class="section-heading-icon">
                                <i class="fa-solid fa-building"></i>
                            </div>

                            <div>
                                <span>Organization</span>

                                <h2>
                                    System Information
                                </h2>

                                <p>
                                    Basic information displayed throughout the FROMS system.
                                </p>
                            </div>

                        </div>


                        <div class="settings-form-grid">

                            <div class="form-group form-group-wide">

                                <label for="organization_name">
                                    Organization Name
                                </label>

                                <input
                                    type="text"
                                    id="organization_name"
                                    value="GCT Transport Services, Inc."
                                >

                                <small>
                                    Name displayed in system records and administrative pages.
                                </small>

                            </div>


                            <div class="form-group">

                                <label for="system_name">
                                    System Name
                                </label>

                                <input
                                    type="text"
                                    id="system_name"
                                    value="FROMS"
                                >

                            </div>


                            <div class="form-group">

                                <label for="system_full_name">
                                    System Full Name
                                </label>

                                <input
                                    type="text"
                                    id="system_full_name"
                                    value="Fleet Resource Optimization and Management System"
                                >

                            </div>


                            <div class="form-group">

                                <label for="organization_email">
                                    Organization Email
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-envelope"></i>

                                    <input
                                        type="email"
                                        id="organization_email"
                                        value="admin@gcttransport.com"
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label for="organization_phone">
                                    Contact Number
                                </label>

                                <div class="input-with-icon">

                                    <i class="fa-solid fa-phone"></i>

                                    <input
                                        type="text"
                                        id="organization_phone"
                                        value="+63 912 345 6789"
                                    >

                                </div>

                            </div>


                            <div class="form-group form-group-wide">

                                <label for="organization_address">
                                    Organization Address
                                </label>

                                <textarea
                                    id="organization_address"
                                    rows="3"
                                >Batangas, CALABARZON, Philippines</textarea>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        REGIONAL SETTINGS
                    ================================================== --}}
                    <section
                        id="regional-settings"
                        class="settings-section"
                    >

                        <div class="settings-section-heading">

                            <div class="section-heading-icon regional">
                                <i class="fa-solid fa-globe"></i>
                            </div>

                            <div>
                                <span>Regional Preferences</span>

                                <h2>
                                    Date & Time Settings
                                </h2>

                                <p>
                                    Configure how system dates and times are displayed.
                                </p>
                            </div>

                        </div>


                        <div class="settings-form-grid">

                            <div class="form-group">

                                <label for="timezone">
                                    Time Zone
                                </label>

                                <select id="timezone">
                                    <option selected>
                                        Asia/Manila (UTC+08:00)
                                    </option>
                                </select>

                            </div>


                            <div class="form-group">

                                <label for="date_format">
                                    Date Format
                                </label>

                                <select id="date_format">
                                    <option selected>
                                        Month DD, YYYY
                                    </option>

                                    <option>
                                        MM/DD/YYYY
                                    </option>

                                    <option>
                                        DD/MM/YYYY
                                    </option>
                                </select>

                            </div>


                            <div class="form-group">

                                <label for="time_format">
                                    Time Format
                                </label>

                                <select id="time_format">
                                    <option selected>
                                        12-hour (2:30 PM)
                                    </option>

                                    <option>
                                        24-hour (14:30)
                                    </option>
                                </select>

                            </div>


                            <div class="form-group">

                                <label for="language">
                                    System Language
                                </label>

                                <select id="language">
                                    <option selected>
                                        English
                                    </option>
                                </select>

                            </div>

                        </div>


                        <div class="regional-preview">

                            <div class="preview-icon">
                                <i class="fa-solid fa-calendar-days"></i>
                            </div>

                            <div>
                                <span>Display Preview</span>

                                <strong>
                                    July 25, 2026 · 3:24 PM
                                </strong>

                                <small>
                                    Asia/Manila
                                </small>
                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        SYSTEM DEFAULTS
                    ================================================== --}}
                    <section
                        id="system-defaults"
                        class="settings-section"
                    >

                        <div class="settings-section-heading">

                            <div class="section-heading-icon defaults">
                                <i class="fa-solid fa-list-check"></i>
                            </div>

                            <div>
                                <span>Defaults</span>

                                <h2>
                                    System Defaults
                                </h2>

                                <p>
                                    Configure default behavior for administrative records.
                                </p>
                            </div>

                        </div>


                        <div class="default-setting-list">

                            <div class="default-setting-row">

                                <div class="default-setting-info">

                                    <div class="default-icon">
                                        <i class="fa-solid fa-table-list"></i>
                                    </div>

                                    <div>
                                        <strong>
                                            Records per Page
                                        </strong>

                                        <span>
                                            Default number of rows shown in record tables.
                                        </span>
                                    </div>

                                </div>

                                <select>
                                    <option>10</option>
                                    <option selected>25</option>
                                    <option>50</option>
                                    <option>100</option>
                                </select>

                            </div>


                            <div class="default-setting-row">

                                <div class="default-setting-info">

                                    <div class="default-icon">
                                        <i class="fa-solid fa-file-export"></i>
                                    </div>

                                    <div>
                                        <strong>
                                            Default Export Format
                                        </strong>

                                        <span>
                                            Preferred format when exporting system records.
                                        </span>
                                    </div>

                                </div>

                                <select>
                                    <option selected>Excel (.xlsx)</option>
                                    <option>CSV (.csv)</option>
                                    <option>PDF (.pdf)</option>
                                </select>

                            </div>


                            <div class="default-setting-row">

                                <div class="default-setting-info">

                                    <div class="default-icon">
                                        <i class="fa-solid fa-arrow-down-wide-short"></i>
                                    </div>

                                    <div>
                                        <strong>
                                            Default Record Order
                                        </strong>

                                        <span>
                                            Order used when displaying system records.
                                        </span>
                                    </div>

                                </div>

                                <select>
                                    <option selected>Newest First</option>
                                    <option>Oldest First</option>
                                </select>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        DISPLAY SETTINGS
                    ================================================== --}}
                    <section
                        id="display-settings"
                        class="settings-section"
                    >

                        <div class="settings-section-heading">

                            <div class="section-heading-icon display">
                                <i class="fa-solid fa-display"></i>
                            </div>

                            <div>
                                <span>Interface</span>

                                <h2>
                                    Display Preferences
                                </h2>

                                <p>
                                    Control selected interface behaviors across administrative pages.
                                </p>
                            </div>

                        </div>


                        <div class="toggle-setting-list">

                            <div class="toggle-setting-row">

                                <div>

                                    <strong>
                                        Show Summary Cards
                                    </strong>

                                    <span>
                                        Display summary cards on supported administrative pages.
                                    </span>

                                </div>

                                <label class="switch">

                                    <input
                                        type="checkbox"
                                        checked
                                    >

                                    <span class="switch-slider"></span>

                                </label>

                            </div>


                            <div class="toggle-setting-row">

                                <div>

                                    <strong>
                                        Show Secondary Information
                                    </strong>

                                    <span>
                                        Display additional descriptions and supporting record details.
                                    </span>

                                </div>

                                <label class="switch">

                                    <input
                                        type="checkbox"
                                        checked
                                    >

                                    <span class="switch-slider"></span>

                                </label>

                            </div>


                            <div class="toggle-setting-row">

                                <div>

                                    <strong>
                                        Confirm Important Actions
                                    </strong>

                                    <span>
                                        Show confirmation dialogs before selected administrative actions.
                                    </span>

                                </div>

                                <label class="switch">

                                    <input
                                        type="checkbox"
                                        checked
                                    >

                                    <span class="switch-slider"></span>

                                </label>

                            </div>

                        </div>

                    </section>


                    {{-- =================================================
                        SAVE BAR
                    ================================================== --}}
                    <section class="settings-save-bar">

                        <div>

                            <strong>
                                General Settings
                            </strong>

                            <span>
                                Review your changes before saving.
                            </span>

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

                </div>

            </section>

        </main>

    </div>

</x-layout.app>