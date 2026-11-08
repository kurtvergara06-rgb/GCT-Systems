<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemWideModalConsistencyTest extends TestCase
{
    public function test_custom_module_modal_overlays_are_owned_by_shared_animation_system(): void
    {
        $animations = file_get_contents(
            resource_path('js/Main-js/system-animations.js')
        );
        $backdrop = file_get_contents(
            resource_path('js/Main-js/global-modal-backdrop.js')
        );

        $this->assertStringContainsString(
            <<<'JS'
'[class*="modal-overlay"]'
JS,
            $animations
        );
        $this->assertStringContainsString(
            <<<'JS'
'[class*="modal-overlay"]'
JS,
            $backdrop
        );

        $views = [
            resource_path('views/Admin/System_Monitoring/activity-logs.blade.php')
                => 'activity-modal-overlay',
            resource_path('views/Operation/Trip_Records/trip-records.blade.php')
                => 'trip-modal-overlay',
            resource_path('views/Maintenance/purchase-requests.blade.php')
                => 'pr-review-modal-overlay',
            resource_path('views/Warehouse/inventory.blade.php')
                => 'inventory-form-modal-overlay',
            resource_path('views/Purchase/Requested_Purchase/inventory-restock.blade.php')
                => 'restock-view-overlay',
        ];

        foreach ($views as $path => $overlayClass) {
            $this->assertStringContainsString(
                $overlayClass,
                file_get_contents($path)
            );
        }

        $this->assertStringContainsString(
            'return Array.from(overlay.children).find',
            $animations
        );
        $this->assertStringContainsString(
            "surface?.classList.add('gct-system-modal-surface-animated')",
            $animations
        );
    }
    public function test_shared_form_modals_keep_action_footer_outside_the_scrollport(): void
    {
        $component = file_get_contents(
            resource_path('views/components/ui/form-modal.blade.php')
        );
        $css = file_get_contents(
            resource_path('css/Main-styles/form-components.css')
        );

        $this->assertStringContainsString('class="ui-form-modal-scrollable"', $component);
        $this->assertStringContainsString('class="ui-form-actions"', $component);
        $this->assertGreaterThan(
            strpos($component, 'class="ui-form-modal-scrollable"'),
            strpos($component, 'class="ui-form-actions"')
        );
        $this->assertStringContainsString('GCT shared form modal scroll contract', $css);
        $this->assertStringContainsString(
            '.ui-form-overlay > .ui-form-modal > .ui-form-content > .ui-form-modal-body > .ui-form-modal-scrollable',
            $css
        );
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString(
            '.ui-form-overlay > .ui-form-modal > .ui-form-content > .ui-form-modal-body > .ui-form-actions',
            $css
        );
    }

    public function test_trip_history_modal_stays_centered_and_scrollable_on_short_viewports(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Trip_Records/trip-records.blade.php'));
        $css = file_get_contents(resource_path('css/Operation/Trip_Records/trip-records.css'));
        $backdrop = file_get_contents(resource_path('js/Main-js/global-modal-backdrop.js'));
        $formCss = file_get_contents(resource_path('css/Main-styles/form-components.css'));

        $this->assertStringContainsString('id="tripDetailModal"', $view);
        $this->assertStringContainsString('Operation final audit: viewport-safe Trip History modal centering', $css);
        $this->assertStringContainsString('#tripDetailModal.trip-modal-overlay > .trip-modal', $css);
        $this->assertStringContainsString('align-items: safe center;', $css);
        $this->assertStringContainsString('justify-content: safe center;', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - clamp(20px, 5vh, 44px));', $css);
        $this->assertStringContainsString('hasFixedPositionContainingBlock(overlay)', $backdrop);
        $this->assertStringContainsString('syncViewportPortals()', $backdrop);
        $this->assertStringContainsString('GCT shared form modal scroll contract', $formCss);
    }

    public function test_operation_custom_dialogs_allow_compact_viewports_without_changing_dismissal(): void
    {
        $ddrCss = file_get_contents(resource_path('css/Operation/Daily_Driver_Reports/daily-driver-reports.css'));
        $incidentCss = file_get_contents(resource_path('css/Operation/Incidents/incidents.css'));
        $routesCss = file_get_contents(resource_path('css/Operation/Routes/routes-stops.css'));
        $tripCss = file_get_contents(resource_path('css/Operation/Trip_Records/trip-records.css'));
        $backdrop = file_get_contents(resource_path('js/Main-js/global-modal-backdrop.js'));

        $this->assertStringContainsString('Operation final audit: DDR record dialogs', $ddrCss);
        $this->assertStringContainsString('.ddr-record-overlay.is-open > .ddr-record-dialog', $ddrCss);
        $this->assertStringContainsString('Operation final audit: avoid minimum-height clipping', $incidentCss);
        $this->assertStringContainsString('.inc-workflow-overlay > .inc-workflow-dialog', $incidentCss);
        $this->assertStringContainsString('min-height: 0;', $incidentCss);
        $this->assertStringContainsString('Operation final audit: route dialogs center', $routesCss);
        $this->assertStringContainsString('.route-modal-overlay > :is(.route-modal, .route-details-modal)', $routesCss);
        $this->assertStringContainsString('Operation final audit: viewport-safe Trip History modal centering', $tripCss);
        $this->assertStringContainsString('hasFixedPositionContainingBlock(overlay)', $backdrop);
        $this->assertStringContainsString("'.inc-workflow-overlay'", $backdrop);
    }

}
