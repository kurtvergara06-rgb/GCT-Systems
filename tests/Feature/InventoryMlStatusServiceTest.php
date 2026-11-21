<?php

namespace Tests\Feature;

use App\Services\Warehouse\InventoryMlStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryMlStatusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();
        config()->set('services.nlp.api_url', 'https://inventory-ml.test');
        config()->set('services.inventory_ml.enabled', true);
        config()->set('services.inventory_ml.replenishment_lead_days', 0);
    }

    public function test_reports_genuine_model_online_without_claiming_reorder_is_enabled(): void
    {
        Http::fake([
            'https://inventory-ml.test/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'is_production_model' => true,
                'source' => 'genuine',
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
            ]),
        ]);

        $result = app(InventoryMlStatusService::class)->summary();

        $this->assertSame('online', $result['state']);
        $this->assertSame('ML Online', $result['label']);
        $this->assertStringContainsString('Set supplier lead time', $result['message']);
        Http::assertSentCount(1);

        // Header navigation can render again without a second remote call.
        app(InventoryMlStatusService::class)->summary();
        Http::assertSentCount(1);
    }

    public function test_reports_online_when_eligible_genuine_reorder_is_enabled(): void
    {
        config()->set('services.inventory_ml.replenishment_lead_days', 14);

        Http::fake([
            'https://inventory-ml.test/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'is_production_model' => true,
                'source' => 'genuine',
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
            ]),
        ]);

        $this->assertStringContainsString(
            'ML reorder predictions enabled',
            app(InventoryMlStatusService::class)->summary()['message']
        );
    }

    public function test_reports_fallback_when_engine_is_reachable_but_model_is_not_eligible(): void
    {
        Http::fake([
            'https://inventory-ml.test/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'is_production_model' => false,
                'source' => 'sample',
                'data_source' => 'sample',
                'forecast_scope' => 'bus_part',
            ]),
        ]);

        $result = app(InventoryMlStatusService::class)->summary();

        $this->assertSame('fallback', $result['state']);
        $this->assertSame('ML Not Ready', $result['label']);
    }

    public function test_reports_offline_when_inventory_api_fails(): void
    {
        Http::fake(['https://inventory-ml.test/inventory/status' => Http::response([], 503)]);

        $result = app(InventoryMlStatusService::class)->summary();

        $this->assertSame('offline', $result['state']);
        $this->assertSame('ML Offline', $result['label']);
    }

    public function test_disabled_ml_does_not_contact_python_engine(): void
    {
        config()->set('services.inventory_ml.enabled', false);
        Http::fake();

        $result = app(InventoryMlStatusService::class)->summary();

        $this->assertSame('fallback', $result['state']);
        $this->assertSame('ML Disabled', $result['label']);
        Http::assertNothingSent();
    }

    public function test_inventory_header_displays_ml_state_beneath_subtitle(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));
        $darkCss = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
        $controller = file_get_contents(app_path('Http/Controllers/Warehouse/InventoryController.php'));

        $this->assertStringContainsString('InventoryMlStatusService::class', $controller);
        $this->assertStringContainsString("'inventoryMlStatus'", $controller);
        $this->assertStringContainsString('inventory-ml-indicator--{{ $inventoryMlStatus[\'state\'] }}', $view);
        $this->assertStringContainsString('aria-label="Inventory machine learning status"', $view);
        $this->assertStringContainsString('.inventory-ml-indicator__dot', $css);
        $this->assertStringContainsString('.inventory-ml-indicator--online', $darkCss);
    }

    public function test_inventory_toolbar_and_add_item_share_one_right_aligned_controls_group(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));

        $headerStart = strpos($view, 'class="section-header inventory-records-header"');
        $controlsStart = strpos($view, 'class="inventory-records-controls"', $headerStart);
        $addStart = strpos($view, 'class="inventory-header-actions"', $controlsStart);
        $toolbarStart = strpos($view, 'class="inventory-list-controls"', $controlsStart);
        $tableStart = strpos($view, 'class="table-wrap gct-responsive-records', $headerStart);

        $this->assertNotFalse($headerStart);
        $this->assertNotFalse($controlsStart);
        $this->assertNotFalse($addStart);
        $this->assertNotFalse($toolbarStart);
        $this->assertNotFalse($tableStart);
        $this->assertTrue($headerStart < $controlsStart && $controlsStart < $addStart
            && $addStart < $toolbarStart && $toolbarStart < $tableStart);
        $this->assertStringContainsString('.inventory-card .inventory-records-controls {', $css);
        $this->assertStringContainsString('flex-direction: column;', $css);
        $this->assertStringContainsString('align-items: flex-end;', $css);
        $this->assertStringContainsString('gap: 20px;', $css);
        $this->assertStringContainsString('@media (max-width: 1100px)', $css);
        $this->assertStringContainsString('@media (max-width: 680px)', $css);
    }

    public function test_add_inventory_modal_is_compact_and_explains_the_stock_unit(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));

        $this->assertStringContainsString('Stock Unit <span class="inventory-required">*</span>', $view);
        $this->assertStringContainsString('(pcs, bottles, liters)', $view);
        $this->assertStringContainsString('name="unit_of_measurement"', $view);
        $this->assertStringContainsString('Reorder point is automatic.', $view);
        $this->assertStringContainsString('Not Calculated', $view);
        $this->assertStringNotContainsString('New items show Pending Calculation', $view);
        $this->assertStringContainsString('max-width:720px;', $css);
        $this->assertStringContainsString('.inventory-add-field-hint', $css);
        $this->assertStringContainsString('.inventory-add-note--summary', $css);
    }

    public function test_inventory_history_and_edit_modals_have_dark_mode_contrast_without_changing_workflows(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $styles = file_get_contents(resource_path('css/Warehouse/inventory.css'));
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));

        $this->assertStringContainsString('id="movementHistoryModal"', $view);
        $this->assertStringContainsString('id="editModal"', $view);
        $this->assertStringContainsString('id="editForm"', $view);
        $this->assertStringContainsString('Stock Unit</label>', $view);
        $this->assertStringContainsString('Uses eligible genuine ML or signed issuance history.', $view);

        $this->assertStringContainsString('--wh-modal-surface: #1a293e;', $dark);
        $this->assertStringContainsString('--wh-modal-ink: #eff5ff;', $dark);
        $this->assertStringContainsString(':is(#movementHistoryModal,#editModal)', $dark);

        $this->assertStringContainsString('background:var(--wh-modal-surface,#fff)!important', $styles);
        $this->assertStringContainsString('color:var(--wh-modal-ink,#1f395c)', $styles);
        $this->assertStringContainsString('#movementHistoryContent .stock-movement-table tbody tr:nth-child(even) td', $styles);
        $this->assertStringContainsString('#editModal .inventory-edit-locked input', $styles);
        $this->assertStringContainsString('#editModal .inventory-edit-footer .secondary-btn', $styles);
        $this->assertStringContainsString('#movementHistoryContent .table-footer', $styles);
        $this->assertStringNotContainsString('background:var(--wh-modal-surface,#fff)1ce', $styles);
    }
}
