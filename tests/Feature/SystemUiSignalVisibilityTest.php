<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SystemUiSignalVisibilityTest extends TestCase
{
    public function test_shared_status_badges_expose_semantic_status_data(): void
    {
        $source = file_get_contents(
            resource_path('views/components/ui/status-badge.blade.php')
        );

        $this->assertStringContainsString(
            "'data-ui-component' => 'status-badge'",
            $source
        );
        $this->assertStringContainsString(
            "'data-status' =>",
            $source
        );
        $this->assertStringContainsString(
            "'data-status-key' =>",
            $source
        );
        $this->assertStringContainsString('status-badge__icon', $source);
    }

    public function test_shared_status_badge_renders_the_semantic_icon_tone_and_label(): void
    {
        $html = Blade::render('<x-ui.status-badge status="Under Maintenance" />');

        $this->assertStringContainsString('data-status="maintenance"', $html);
        $this->assertStringContainsString('data-status-key="under-maintenance"', $html);
        $this->assertStringContainsString('fa-wrench', $html);
        $this->assertStringContainsString('Under Maintenance', $html);
    }

    public function test_missed_assignment_badge_keeps_its_icon_and_visible_label(): void
    {
        $html = Blade::render('<x-ui.status-badge status="Missed" class="assignment-status missed" title="Departure time has already passed." />');

        $this->assertStringContainsString('data-status="negative"', $html);
        $this->assertStringContainsString('fa-calendar-xmark', $html);
        $this->assertStringContainsString('status-badge__label">Missed</span>', $html);
        $this->assertStringContainsString('title="Departure time has already passed."', $html);
    }

    public function test_primary_department_views_use_the_shared_status_component(): void
    {
        $views = [
            resource_path('views/Admin/Data_Management/uploading-data.blade.php'),
            resource_path('views/Operation/Shuttle_Bus_Management/bus-master-list.blade.php'),
            resource_path('views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php'),
            resource_path('views/Operation/Incidents/index.blade.php'),
            resource_path('views/Maintenance/job-order.blade.php'),
            resource_path('views/Warehouse/part-requests.blade.php'),
            resource_path('views/Purchase/purchase-orders.blade.php'),
        ];

        foreach ($views as $view) {
            $this->assertStringContainsString(
                '<x-ui.status-badge',
                file_get_contents($view),
                "Expected {$view} to use the centralized status badge."
            );
        }
    }

    public function test_shared_action_buttons_expose_semantic_action_data(): void
    {
        $source = file_get_contents(
            resource_path('views/components/ui/action-button.blade.php')
        );

        $this->assertStringContainsString(
            'data-ui-component="action-button"',
            $source
        );
        $this->assertStringContainsString(
            'data-action="{{',
            $source
        );
        $this->assertStringContainsString(
            "'delete' => 'fa-trash'",
            $source
        );
        $this->assertStringNotContainsString('fa-trash-can', $source);
    }

    public function test_delete_actions_use_the_canonical_solid_trash_icon(): void
    {
        $sources = [
            resource_path('views'),
            resource_path('js'),
        ];

        foreach ($sources as $sourceDirectory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $path = $file->getPathname();

                if (! str_ends_with($path, '.blade.php') && ! str_ends_with($path, '.js')) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'fa-trash-can',
                    file_get_contents($path),
                    "Expected {$path} to use the canonical fa-trash icon."
                );
            }
        }
    }

    public function test_all_departments_use_the_canonical_record_action_icons(): void
    {
        $representativeViews = [
            'administration' => resource_path('views/Admin/System_Monitoring/activity-logs.blade.php'),
            'operation' => resource_path('views/Operation/Incidents/index.blade.php'),
            'maintenance' => resource_path('views/Maintenance/fuel-reports.blade.php'),
            'warehouse' => resource_path('views/Warehouse/part-requests.blade.php'),
            'purchase' => resource_path('views/Purchase/scheduled-purchase.blade.php'),
        ];

        foreach ($representativeViews as $department => $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString(
                'fa-solid fa-eye',
                $source,
                "Expected the {$department} module to use its approved View or Workflow icon."
            );
        }

        $this->assertStringContainsString(
            'fa-solid fa-pen-to-square',
            file_get_contents(resource_path('views/Maintenance/fuel-reports.blade.php'))
        );
        $this->assertStringContainsString(
            'fa-solid fa-trash',
            file_get_contents(resource_path('views/Purchase/scheduled-purchase.blade.php'))
        );
    }

    public function test_global_record_action_styles_cover_shared_and_legacy_tables(): void
    {
        $css = file_get_contents(
            resource_path('css/Main-styles/record-action-buttons.css')
        );

        foreach ([
            '.action-btn.view',
            '.table-action-btn.view',
            '.fuel-action-btn.view',
            '.route-action.view',
            '.assignment-action.view',
            '.ddr-action.view',
            '.view-btn',
            '.pms-edit-task-btn',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $css,
                "Expected the system-wide action stylesheet to cover {$selector}."
            );
        }
    }

    public function test_global_styles_keep_sources_statuses_and_actions_visible(): void
    {
        $css = file_get_contents(
            resource_path('css/Main-styles/shared-ui-enhancements.css')
        );

        $this->assertStringContainsString(
            'SYSTEM-WIDE TABLE SIGNAL VISIBILITY',
            $css
        );
        $this->assertStringContainsString('.source-badge {', $css);
        $this->assertStringContainsString(
            '[data-ui-component="status-badge"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="status-badge"][data-status="maintenance"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="view"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="create-po"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="approve"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="delete"]',
            $css
        );
    }
}
