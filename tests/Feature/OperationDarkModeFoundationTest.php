<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationDarkModeFoundationTest extends TestCase
{
    public function test_realtime_frontend_exposes_only_public_connection_state_for_diagnostics(): void
    {
        $js = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString('window.GCTRealtimeHealth = {', $js);
        $this->assertStringContainsString("state: window.GCTRealtimeConfigurationReady ? 'initializing' : 'disabled'", $js);
        $this->assertStringContainsString("connection?.bind('state_change'", $js);
        $this->assertStringContainsString('changedAt: new Date().toISOString()', $js);
        $this->assertStringContainsString("state: connection?.state || 'error'", $js);
        $this->assertStringContainsString('window.GCTRealtimeConfig', $js);
    }

    public function test_realtime_frontend_does_not_initialize_echo_without_public_key(): void
    {
        $js = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString("String(import.meta.env.VITE_REVERB_APP_KEY || '').trim()", $js);
        $this->assertStringContainsString('window.GCTRealtimeConfigurationReady = reverbAppKey.length > 0;', $js);
        $this->assertStringContainsString('if (!window.GCTRealtimeConfigurationReady)', $js);
        $this->assertStringContainsString('window.Echo = window.Echo || new Echo({', $js);
        $this->assertStringContainsString('key: reverbAppKey,', $js);
    }

    public function test_navigation_initializer_releases_resources_when_initialization_fails(): void
    {
        $js = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));

        $this->assertStringContainsString('const cleanup = () => {', $js);
        $this->assertStringContainsString('controller.abort();', $js);
        $this->assertStringContainsString('observers.forEach((observer) => observer.disconnect());', $js);
        $this->assertStringContainsString('intervals.forEach((id) => window.clearInterval(id));', $js);
        $this->assertStringContainsString('timeouts.forEach((id) => window.clearTimeout(id));', $js);
        $this->assertStringContainsString('initializerCleanups.set(key, cleanup);', $js);
        $this->assertStringContainsString("} catch (error) {\n        // An initializer can throw", $js);
        $this->assertStringContainsString("cleanup();\n        throw error;", $js);
        $this->assertStringContainsString('EventTarget.prototype.addEventListener = nativeAddEventListener;', $js);
    }

    public function test_partial_navigation_timings_are_opt_in_and_sidebar_remains_safe(): void
    {
        $js = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));

        $this->assertStringContainsString('window.GCTNavigationDiagnostics !== true', $js);
        $this->assertStringContainsString("navClock?.checkpoint('server response')", $js);
        $this->assertStringContainsString("navClock?.checkpoint('assets ready')", $js);
        $this->assertStringContainsString("navClock?.checkpoint('page initialized')", $js);
        $this->assertStringContainsString('navClock?.finish(response.url || targetUrl)', $js);
        $this->assertStringContainsString("const isSidebarLink = Boolean(link.closest('#appSidebar'));", $js);
        $this->assertStringContainsString('cleanupAllInitializers();', $js);
        $this->assertStringContainsString('if (controller.signal.aborted || activeRequest !== controller) return;', $js);
    }

    public function test_operation_stylesheet_has_no_unresolved_merge_markers(): void
    {
        $styles = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/^(?:<{7}|={7}|>{7})(?:[ \\t].*)?\\r?$/m',
            $styles,
            'Resolve both theme implementations before testing or building the stylesheet.'
        );
    }

    public function test_shared_theme_defines_dark_tokens_for_operation_and_maintenance_pages(): void
    {
        $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $this->assertStringContainsString('html[data-gct-theme="dark"] body:is([data-gct-operation-page="1"], [data-gct-maintenance-page="1"], [data-gct-warehouse-page="1"], [data-gct-purchase-page="1"], [data-gct-admin-page="1"])', $theme);

        foreach (['--gct-theme-canvas', '--gct-theme-surface', '--gct-theme-fg', '--gct-theme-border', '--bg', '--card', '--text'] as $token) {
            $this->assertStringContainsString($token.':', $theme);
        }

        $this->assertStringContainsString('--bg-main: var(--gct-theme-canvas)', $theme);
    }

    public function test_operation_stylesheet_is_scoped_and_loaded_after_existing_page_assets(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $css = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString("str_starts_with(\$asset, 'resources/css/Operation/')", $layout);
        $this->assertStringContainsString('data-gct-operation-page="1"', $layout);
        $this->assertStringContainsString("['resources/css/Operation/operation-dark-mode.css']", $layout);
        $this->assertStringContainsString('resources/css/Operation/operation-dark-mode.css', $vite);
        $this->assertStringContainsString('html[data-gct-theme="dark"] body[data-gct-operation-page="1"]', $css);
        // Existing route and modal compatibility rules use targeted !important;
        // picker and sticky Action specificity are verified by dedicated tests.
        $this->assertStringContainsString('background: var(--gct-theme-surface)', $css);
        $this->assertStringNotContainsString('background: #000', $css);
    }

    public function test_existing_dark_styles_are_scoped_and_identifiers_use_theme_tokens(): void
    {
        $preferencesCss = file_get_contents(resource_path('css/Main-styles/account-preferences.css'));
        $sharedUiCss = file_get_contents(resource_path('css/Main-styles/shared-ui-enhancements.css'));
        $identifierCss = file_get_contents(resource_path('css/Main-styles/identifier-text.css'));
        $themeCss = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $operationCss = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $tripCss = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/trip-schedule.css'));
        $preferencesJs = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

        $this->assertStringContainsString('html[data-gct-theme="dark"]:has(.account-page)', $preferencesCss);
        $this->assertStringNotContainsString('html[data-gct-theme="dark"] :is(body,.app,.main,.account-page,.account-main)', $preferencesCss);
        $this->assertStringContainsString('var(--gct-id-background, #f8fbff)', $sharedUiCss);
        $this->assertStringContainsString('var(--gct-id-foreground, #111827)', $identifierCss);
        $this->assertStringContainsString('--gct-id-foreground: #e6f0ff', $themeCss);
        $this->assertStringContainsString('var(--gct-trip-group-heading-bg, #eaf1fb) !important', $tripCss);
        $this->assertStringContainsString('var(--gct-theme-surface)', $operationCss);
        $this->assertStringContainsString('root.style.colorScheme = effectiveTheme', $preferencesJs);
    }

    public function test_driver_master_employment_badges_preserve_readable_semantic_colors(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Personnel Management/driver_master_list.blade.php'));
        $css = file_get_contents(resource_path('css/Operation/Attendance/personnel-master.css'));

        $this->assertStringContainsString('class="driver-employment-status"', $view);
        $this->assertStringContainsString('[data-ui-component="status-badge"].driver-employment-status', $css);
        $this->assertStringContainsString('--gct-status-bg: #eaf9f0', $css);
        $this->assertStringContainsString('--gct-status-text: #176c42', $css);
        $this->assertStringContainsString('--gct-status-bg: #fff0f2', $css);
        $this->assertStringContainsString('.status-badge__icon, .status-badge__label', $css);
    }

    public function test_driver_master_uses_single_canonical_status_badge_and_theme_identifiers(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Personnel Management/driver_master_list.blade.php'));
        $personnel = file_get_contents(resource_path('css/Operation/Attendance/personnel-master.css'));

        $this->assertStringContainsString('class="driver-employment-status"', $view);
        $this->assertStringNotContainsString('class="personnel-status gct-pill gct-pill--', $view);
        $this->assertStringContainsString('background: var(--gct-id-background, #f5f8fc)', $personnel);
        $this->assertStringContainsString('color: var(--gct-id-foreground, #173152)', $personnel);
        $this->assertStringContainsString('.personnel-status:not([data-ui-component="status-badge"])', $personnel);
        $this->assertStringContainsString('.personnel-status.active:not([data-ui-component="status-badge"])', $personnel);
    }

    public function test_driver_master_original_css_uses_theme_tokens_for_legacy_controls(): void
    {
        $css = file_get_contents(resource_path('css/Operation/Attendance/personnel-master.css'));

        $this->assertStringContainsString('background: var(--gct-ui-surface, #fff);', $css);
        $this->assertStringContainsString('background: linear-gradient(110deg, var(--gct-ui-surface, #fff)', $css);
        $this->assertStringContainsString('color: var(--gct-ui-text, #112b50);', $css);
        $this->assertStringContainsString('background: var(--gct-ui-input, #fdfefe)', $css);
        $this->assertStringContainsString('-webkit-text-fill-color: var(--gct-ui-text, #2d405c)', $css);
        $this->assertStringContainsString('border: 1px solid var(--gct-ui-border, #d3e0f1)', $css);
        $this->assertStringContainsString('.personnel-modal-driver .driver-bus-picker-trigger', $css);
        $this->assertStringNotContainsString('background: linear-gradient(110deg, #fff 65%', $css);
        $this->assertDoesNotMatchRegularExpression('/^(?:<{7}|={7}|>{7})(?:[ \\t].*)?\\r?$/m', $css);
    }

    public function test_operation_master_lists_and_sticky_action_columns_use_dark_tokens(): void
    {
        $css = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $personnel = file_get_contents(resource_path('css/Operation/Attendance/personnel-master.css'));
        $sticky = file_get_contents(resource_path('css/Main-styles/gct-scrollbar.css'));

        $this->assertStringContainsString('.gct-responsive-records.gct-sticky-actions > table > thead > tr > th:last-child', $css);
        $this->assertStringContainsString('.gct-responsive-records.gct-sticky-actions > table > tbody > tr > td:last-child:not([colspan])', $css);
        $this->assertStringContainsString('background: var(--gct-theme-surface-raised)', $css);
        $this->assertStringContainsString('background: var(--gct-theme-surface)', $css);
        $this->assertStringContainsString('background: var(--gct-sticky-actions-background, #fff)', $sticky);

        $this->assertStringContainsString('.driver-master-records-table .driver-bus-details strong', $css);
        $this->assertStringContainsString('.driver-master-records-table .driver-bus-details small', $css);
        $this->assertStringContainsString('.personnel-master-page .personnel-record-tab.is-active', $css);
        $this->assertStringContainsString('.gct-table-tool-trigger', $css);
        $this->assertStringContainsString('.personnel-driver-form .driver-input-shell > :is(input, select)', $css);
        $this->assertStringContainsString('.personnel-modal-overlay[data-mode="view"]', $css);
        $this->assertStringContainsString('.driver-bus-details strong', $personnel);

        foreach (['.routes-page', '.bus-master-list-page', '.ddr-page', '.trip-records-page', '.driver-attendance-page', '.client-master-page'] as $page) {
            $this->assertStringContainsString($page, $css);
        }
        // Require the shared sticky Action column's dark-theme rules to be
        // token-based and free from specificity overrides. Unrelated legacy
        // route/modal rules in this stylesheet may still use !important.
        foreach ([
            '.gct-responsive-records.gct-sticky-actions > table > thead > tr > th:last-child',
            '.gct-responsive-records.gct-sticky-actions > table > tbody > tr > td:last-child:not([colspan])',
        ] as $selector) {
            $start = strpos($css, $selector);
            $this->assertNotFalse($start);
            $end = strpos($css, '}', $start);
            $this->assertNotFalse($end);
            $rule = substr($css, $start, $end - $start);
            $this->assertStringContainsString('background: var(--gct-theme-surface', $rule);
            $this->assertStringNotContainsString('!important', $rule);
        }
        $this->assertStringContainsString('Keep the designated yellow Export action', $css);
    }

    public function test_command_center_reference_table_and_fleet_cards_are_dark_themed(): void
    {
        $styles = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $dashboard = file_get_contents(resource_path('views/Operation/dashboard-operation.blade.php'));

        $this->assertStringContainsString('Dashboard contrast repair.', $styles);
        $this->assertStringContainsString('.operation-dashboard-page .operation-reference-table tbody tr > td', $styles);
        $this->assertStringContainsString('.operation-dashboard-page .operation-reference-table tbody tr:hover > td', $styles);
        $this->assertStringContainsString('.operation-dashboard-page .operation-reference-table tbody tr > td:last-child', $styles);
        $this->assertStringContainsString('.operation-dashboard-page .operation-fleet-status-stack .fleet-status-item', $styles);
        $this->assertStringContainsString('.operation-client-name, .operation-driver-info strong', $styles);
        $this->assertStringContainsString('var(--gct-theme-fg)', $styles);
        $this->assertStringContainsString('var(--gct-theme-surface)', $styles);
        $this->assertStringContainsString('operation-reference-table', $dashboard);
        $this->assertStringContainsString('fleet-status-item', $dashboard);
    }

    public function test_operation_source_stylesheets_use_semantic_surface_tokens(): void
    {
        $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $this->assertStringContainsString('--gct-ui-surface: #ffffff', $theme);
        $this->assertStringContainsString('--gct-ui-text: #0f172a', $theme);
        $this->assertStringContainsString('--gct-ui-surface: var(--gct-theme-surface)', $theme);
        $this->assertStringContainsString('--gct-ui-text: var(--gct-theme-fg)', $theme);

        $migrated = [
            'dashboard-operation.css',
            'Attendance/personnel-master.css',
            'Attendance/driver-attendance.css',
            'Attendance/batch-attendance.css',
            'Clients/client-master-list.css',
            'Daily_Driver_Reports/daily-driver-reports.css',
            'Incidents/incidents.css',
            'Routes/routes-stops.css',
            'Scheduling_And_Dispatch/auto-dispatch.css',
            'Scheduling_And_Dispatch/client-trip-times.css',
            'Scheduling_And_Dispatch/driver-bus-assignment.css',
            'Scheduling_And_Dispatch/trip-schedule.css',
            'Shuttle_Bus_Management/bus-master-list.css',
            'Trip_Records/trip-records.css',
        ];

        foreach ($migrated as $path) {
            $css = file_get_contents(resource_path('css/Operation/'.$path));
            $this->assertStringContainsString('var(--gct-ui-surface', $css, $path);
            $this->assertStringNotContainsString('<<<<<<< HEAD', $css, $path);
            $this->assertStringNotContainsString('>>>>>>>', $css, $path);
        }
    }

    public function test_saved_theme_hydrates_before_vite_and_scope_tracks_partial_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $navigation = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
        $preferences = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

        $this->assertStringContainsString("'gct:ui-preferences:'.auth()->id()", $layout);
        $this->assertStringContainsString('(prefers-color-scheme: dark)', $layout);
        $this->assertLessThan(
            strpos($layout, '@vite($viteAssets)'),
            strpos($layout, 'document.documentElement.dataset.gctTheme = theme')
        );
        $this->assertStringContainsString("['gctOperationPage', 'gctMaintenancePage', 'gctWarehousePage', 'gctPurchasePage', 'gctAdminPage']", $navigation);
        $this->assertStringContainsString("nextDocument.body.dataset[scope] === '1'", $navigation);
        $this->assertStringContainsString('delete document.body.dataset[scope]', $navigation);
        $this->assertStringContainsString("theme: 'light'", $preferences);
        $this->assertStringContainsString("['light', 'dark', 'system']", $preferences);
    }
    public function test_screen_palette_covers_portals_and_preserves_print_fallbacks(): void
    {
        $css = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $ddrCss = file_get_contents(resource_path('css/Operation/Daily_Driver_Reports/daily-driver-reports.css'));
        $ddrView = file_get_contents(resource_path('views/Operation/Daily_Driver_Reports/index.blade.php'));
        $busJs = file_get_contents(resource_path('js/Operation/Shuttle_Bus_Management/bus-master-list.js'));

        $this->assertStringContainsString('@media screen', $css);
        foreach (['.inc-workflow-board', '.inc-wf-step-card', '#ddrEncodeModal', '#viewBusModal', '.operation-command-header'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
        $this->assertStringContainsString('var(--gct-screen-table-head, #f3f7fc)', $ddrCss);
        $this->assertStringContainsString('var(--gct-screen-fg, var(--ddr-navy))', $ddrView);
        $this->assertStringContainsString("(button.dataset.clientNames || 'Not allocated')", $busJs);
        // Legacy route/dialog overrides still need targeted !important rules.
        // The new shared picker palette is checked separately below.
        $this->assertStringContainsString('--gct-trip-group-heading-bg:', $css);
    }

    public function test_body_portal_date_and_time_pickers_have_an_operation_screen_palette(): void
    {
        $css = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $start = strpos($css, '/* Shared date/time pickers');
        $end = strpos($css, 'Command Center workforce', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        // Test only the picker bridge: later legacy dialog overrides legitimately
        // contain !important and should not affect the picker palette contract.
        $bridge = substr($css, $start, $end - $start);

        $this->assertStringContainsString('@media screen', $bridge);
        $this->assertStringContainsString('html[data-gct-theme="dark"] body[data-gct-operation-page="1"]', $bridge);
        foreach (['.gct-picker-popover', '.gct-calendar-panel', '.gct-time-panel', '.gct-time-select', '.gct-picker-secondary'] as $selector) {
            $this->assertStringContainsString($selector, $bridge);
        }
        $this->assertStringContainsString('background: var(--gct-theme-surface)', $bridge);
        $this->assertStringContainsString('.gct-calendar-day:not(.is-selected)', $bridge);
        $this->assertStringNotContainsString('.gct-picker-primary', $bridge);
        $this->assertStringNotContainsString('!important', $bridge);
    }
    public function test_operation_css_consolidation_preserves_theme_and_action_contracts(): void
    {
        $dark = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $shared = file_get_contents(resource_path('css/Main-styles/gct-scrollbar.css'));
        $auto = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/auto-dispatch.css'));
        $incidents = file_get_contents(resource_path('css/Operation/Incidents/incidents.css'));
        $trips = file_get_contents(resource_path('css/Operation/Trip_Records/trip-records.css'));

        // The Operation-scoped semantic tokens are defined only once.
        $this->assertSame(1, substr_count($dark, '--gct-sticky-actions-background:'));
        $this->assertStringContainsString('--gct-trip-group-heading-bg: var(--gct-theme-surface-raised);', $dark);
        $this->assertStringContainsString('html[data-gct-theme="dark"] body[data-gct-operation-page="1"]', $dark);

        // Sticky Action cells retain their behavior without repeated seams.
        $this->assertSame(1, substr_count($shared, 'box-shadow: none !important;'));
        $this->assertStringContainsString('One shared seamless Action rule', $shared);
        $this->assertStringContainsString('gct-sticky-actions:not(.has-horizontal-overflow)', $shared);
        $this->assertStringContainsString('position: sticky;', $shared);

        // High-traffic page contracts remain after removing duplicate rules.
        $this->assertStringContainsString('.gct-conflict-workspace:not(.is-report-closed)', $auto);
        $this->assertStringContainsString('max-width: 1250px', $auto);
        $this->assertStringContainsString('.inc-status-dot-pill[data-ui-component="status-badge"]', $incidents);
        $this->assertStringContainsString('.inc-modal-kpis', $incidents);
        $this->assertStringContainsString('.unified-trip-history-table', $trips);
        $this->assertStringContainsString('.view-trip-btn', $trips);
    }

}
