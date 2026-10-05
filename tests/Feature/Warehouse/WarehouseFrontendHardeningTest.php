<?php

namespace Tests\Feature\Warehouse;

use Tests\TestCase;

class WarehouseFrontendHardeningTest extends TestCase
{
    public function test_realtime_maps_cover_warehouse_pages_and_permission_updates(): void
    {
        $echo = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString("'Warehouse:RolePermission'", $echo);
        $this->assertStringContainsString("'Warehouse:PurchaseOrder'", $echo);

        foreach (['/warehouse/dashboard', '/inventory', '/part-requests', '/warehouse/incoming-deliveries', '/warehouse/stock-movements'] as $path) {
            $this->assertStringContainsString($path, $echo);
        }
    }

    public function test_warehouse_assets_are_registered_and_filters_use_ajax_regions(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString('resources/css/Warehouse/part-requests-cleanup.css', $vite);
        $this->assertStringContainsString('resources/js/Warehouse/part-requests-cleanup.js', $vite);
        $this->assertStringContainsString('resources/js/Warehouse/dashboard-warehouse.js', $vite);

        foreach (['inventory', 'part-requests', 'incoming-deliveries', 'stock-movements'] as $view) {
            $contents = file_get_contents(resource_path("views/Warehouse/{$view}.blade.php"));
            $this->assertStringContainsString('data-ajax-region="summary"', $contents);
            $this->assertStringContainsString('data-ajax-region="records"', $contents);
            $this->assertStringContainsString('data-server-filter="true"', $contents);
        }
    }

    public function test_warehouse_modal_scripts_do_not_close_on_backdrop_click(): void
    {
        foreach (['inventory.js', 'part-requests.js'] as $script) {
            $contents = file_get_contents(resource_path("js/Warehouse/{$script}"));
            $this->assertStringNotContainsString('event.target === modal', $contents);
            $this->assertStringNotContainsString('event.target === viewPrModal', $contents);
            $this->assertStringNotContainsString('event.target === issuePartsModal', $contents);
        }
    }

    public function test_dashboard_uses_unique_realtime_regions(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));
        preg_match_all('/data-ajax-region="([^"]+)"/', $view, $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertSame($matches[1], array_values(array_unique($matches[1])));
        $this->assertContains('recent-stock-movements', $matches[1]);
        $this->assertStringContainsString('warehouseInventoryBar', $view);
        $this->assertStringContainsString('warehouseInventoryDonut', $view);
        $this->assertStringContainsString('warehouseMovementTrend', $view);
    }
}
