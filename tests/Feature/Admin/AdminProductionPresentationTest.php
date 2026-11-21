<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminProductionPresentationTest extends TestCase
{
    public function test_admin_pages_do_not_hard_code_notification_counts(): void
    {
        foreach ([
            'Admin/admin-dashboard.blade.php',
            'Admin/Settings/security-settings.blade.php',
            'Admin/System_Monitoring/activity-logs.blade.php',
            'Admin/Data_Management/generic-batch-review.blade.php',
            'Admin/Data_Management/uploading-data.blade.php',
        ] as $path) {
            $view = file_get_contents(resource_path('views/'.$path));
            $this->assertStringNotContainsString('notification-count="6"', $view, $path);
        }
    }

    public function test_shared_admin_css_loads_by_admin_theme_detection_not_route_name_only(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $this->assertStringContainsString('$adminThemePage', $layout);
        $this->assertStringContainsString("'resources/css/Admin/administration-standards.css'", $layout);
        $css = file_get_contents(resource_path('css/Admin/administration-standards.css'));
        $this->assertStringContainsString('.activity-logs-page .activity-stats-grid', $css);
        $this->assertStringContainsString('.data-history-page .history-stats-grid', $css);
        $this->assertStringContainsString('.notifications-page .notification-stats-grid', $css);
    }

    public function test_admin_views_delegate_global_styles_to_shared_layout(): void
    {
        foreach ([
            'Admin/admin-dashboard.blade.php',
            'Admin/User_Management/users.blade.php',
            'Admin/User_Management/permissions.blade.php',
            'Admin/Settings/general-settings.blade.php',
            'Admin/Settings/security-settings.blade.php',
            'Admin/System_Monitoring/activity-logs.blade.php',
            'Admin/System_Monitoring/notifications.blade.php',
        ] as $path) {
            $view = file_get_contents(resource_path('views/'.$path));
            $this->assertStringNotContainsString("'resources/css/Main-styles/main.css'", $view, $path);
            $this->assertStringNotContainsString("'resources/css/Main-styles/sidebar.css'", $view, $path);
            $this->assertStringNotContainsString("'resources/js/Main-js/sidebar.js'", $view, $path);
        }
    }

    public function test_admin_dialogs_and_table_popovers_share_responsive_positioning(): void
    {
        $css = file_get_contents(resource_path('css/Admin/administration-standards.css'));
        foreach ([
            '.admin-modal-overlay',
            '.activity-modal-overlay',
            '.history-modal-overlay',
            '.records-modal-overlay',
            '.batch-delete-modal-overlay',
            '.generic-batch-review-overlay',
            '.admin-user-modal',
            '.activity-modal-body',
            '.history-modal-body',
            '.gct-table-tool-popover:not([hidden])',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
        $this->assertStringContainsString('align-items: center;', $css);
        $this->assertStringContainsString('justify-content: center;', $css);
        $this->assertStringContainsString('max-height: calc(100dvh -', $css);
        $this->assertStringContainsString('viewport-aware positioning runtime', $css);
    }

    public function test_shared_modal_rules_preserve_existing_dialog_widths_and_toolbar_positioning(): void
    {
        $css = file_get_contents(resource_path('css/Admin/administration-standards.css'));
        $this->assertStringNotContainsString('width: min(var(--admin-dialog-width, 720px), 100%);', $css);
        $this->assertStringNotContainsString('--admin-dialog-width: 1380px;', $css);
        $this->assertStringNotContainsString('On phones, use the existing shared fixed bottom-sheet', $css);

        $toolbarScript = file_get_contents(resource_path('js/Main-js/table-toolbar.js'));
        $this->assertStringContainsString('positionTableToolPopover(trigger, popover)', $toolbarScript);
        $this->assertStringContainsString("zIndex: '99990'", $toolbarScript);
    }

    public function test_batch_delete_modal_has_no_backdrop_click_to_close_handler(): void
    {
        $script = file_get_contents(resource_path('js/Admin/Data_Management/batch-file-processing.js'));
        $this->assertStringNotContainsString("deleteModal.addEventListener('click'", $script);
        $this->assertStringContainsString('cancelDeleteButton.addEventListener', $script);
    }

    public function test_prescriptive_preview_is_non_production_only(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AnalyticsStageController.php'));
        $this->assertSame(2, substr_count(
            $source, 'app()->environment(\'local\', \'testing\') && $request->boolean(\'preview\', false)'
        ));
    }
}
