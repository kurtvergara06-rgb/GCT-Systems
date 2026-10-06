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
}
