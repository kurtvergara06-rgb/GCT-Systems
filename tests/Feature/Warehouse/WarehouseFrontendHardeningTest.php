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

    public function test_warehouse_record_pages_expose_active_and_history_tabs(): void
    {
        foreach (['part-requests', 'incoming-deliveries'] as $view) {
            $contents = file_get_contents(resource_path("views/Warehouse/{$view}.blade.php"));

            $this->assertStringContainsString('warehouse-record-tabs', $contents);
            $this->assertStringContainsString("'view' => 'active'", $contents);
            $this->assertStringContainsString("'view' => 'history'", $contents);
            $this->assertStringContainsString('name="view"', $contents);
        }

        $partRequests = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));
        $this->assertStringContainsString('@unless($isHistory)', $partRequests);

        $deliveries = file_get_contents(resource_path('views/Warehouse/incoming-deliveries.blade.php'));
        $this->assertStringContainsString('$isHistory || $received', $deliveries);
    }


    public function test_inventory_add_and_edit_modals_use_shared_form_language_without_breaking_ajax_contracts(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));

        $this->assertStringContainsString('resources/css/Main-styles/form-components.css', $view);
        $this->assertStringContainsString('inventory-form-modal-overlay', $view);
        $this->assertStringContainsString('inventory-section-basic', $view);
        $this->assertStringContainsString('inventory-section-details', $view);
        $this->assertStringContainsString('inventory-section-additional', $view);
        $this->assertStringContainsString('data-ajax-submit="true"', $view);
        $this->assertStringContainsString('data-parent-modal-id="addModal"', $view);
        $this->assertStringContainsString('data-parent-modal-id="editModal"', $view);
        $this->assertStringContainsString('id="edit_adjustment_reason"', $view);
        $this->assertStringContainsString('class="ui-form-close closeModal"', $view);
    }


    public function test_dashboard_matches_reference_layout_and_keeps_queue_previews_compact(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));
        $js = file_get_contents(resource_path('js/Warehouse/dashboard-warehouse.js'));

        $this->assertStringContainsString('warehouse-reference-kpis', $view);
        $this->assertStringContainsString('Inventory Overview', $view);
        $this->assertStringContainsString('Stock Status by Category', $view);
        $this->assertStringContainsString('Alerts & Notifications', $view);
        $this->assertStringContainsString('warehouseCategoryFilter', $view);
        $this->assertStringContainsString('warehouse-feature-header', $view);
        $this->assertStringContainsString('warehouse-audit-table', $view);
        $this->assertStringContainsString('warehouse-item-cell', $view);
        $this->assertStringContainsString('warehouse-user-cell', $view);
        $this->assertStringContainsString('warehouse-view-action', $view);
        $this->assertStringNotContainsString('warehouse-audit-summary', $view);
        $this->assertStringNotContainsString('warehouse-usage-summary', $view);
        $this->assertStringNotContainsString('warehouse-trend-summary', $view);
        $this->assertStringContainsString('$expectedDeliveries->take(5)', $view);
        $this->assertStringContainsString('$activePartRequests->take(5)', $view);
        $this->assertSame(2, substr_count($view, 'warehouse-queue-panel'));

        $this->assertStringContainsString('overflow-x: hidden !important;', $css);
        $this->assertStringContainsString('warehouse-reference-overview', $css);
        $this->assertStringContainsString('OPERATIONAL INTELLIGENCE PANELS', $css);
        $this->assertStringContainsString('LOWER DASHBOARD REFERENCE LAYOUT', $css);
        $this->assertStringContainsString('bindInventoryCategoryFilter', $js);
        $this->assertStringContainsString('For Reorder', $js);
        $this->assertStringContainsString("text: 'Quantity'", $js);

        $controller = file_get_contents(app_path('Http/Controllers/Warehouse/WarehouseDashboardController.php'));
        $this->assertStringContainsString("->with('creator')", $controller);
    }

}
