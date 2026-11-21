<?php

namespace Tests\Feature;

use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryReorderPointService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryReorderPointServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.nlp.api_url', 'https://ml.test');
        Config::set('services.inventory_ml.enabled', true);
        Config::set('services.inventory_ml.max_batch_parts', 100);
        Config::set('services.inventory_ml.safety_percent', 25);
    }

    private function part(int $id = 901): InventoryItem
    {
        $item = new InventoryItem([
            'item_code' => 'PART-901',
            'unit_of_measurement' => 'pcs',
            'quantity_available' => 20,
            'reorder_level' => 0,
        ]);
        $item->id = $id;

        return $item;
    }

    public function test_without_configured_lead_time_rules_are_used_without_any_python_call(): void
    {
        Config::set('services.inventory_ml.replenishment_lead_days', 0);
        Http::fake();

        $resolved = app(InventoryReorderPointService::class)->resolveMany(
            collect([$this->part()]),
            [901 => ['reorder_point' => 6]]
        );

        $this->assertSame(6, $resolved[901]['reorder_point']);
        $this->assertSame('signed_issuances', $resolved[901]['source']);
        Http::assertNothingSent();
    }

    public function test_unknown_threshold_is_not_fabricated_when_ml_is_not_ready(): void
    {
        Config::set('services.inventory_ml.replenishment_lead_days', 7);
        Http::fake(['https://ml.test/inventory/status' => Http::response([
            'model_ready' => false,
            'data_source' => 'genuine',
        ])]);

        $resolved = app(InventoryReorderPointService::class)->resolveMany(
            collect([$this->part()]),
            [901 => ['reorder_point' => null]]
        );

        $this->assertNull($resolved[901]['reorder_point']);
        $this->assertSame('pending', $resolved[901]['source']);
        Http::assertSentCount(1);
    }

    public function test_genuine_weekly_forecast_is_converted_with_explicit_lead_days_and_safety_margin(): void
    {
        Config::set('services.inventory_ml.replenishment_lead_days', 14);
        Http::fake([
            'https://ml.test/inventory/status' => Http::response([
                'model_ready' => true,
                'is_production_model' => true,
                'source' => 'genuine',
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
            ]),
            'https://ml.test/inventory/predict/batch' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
                'results' => [[
                    'success' => true,
                    'part_id' => '901',
                    'model_ready' => true,
                    'is_production_model' => true,
                    'data_source' => 'genuine',
                    'forecast_scope' => 'fleet_part',
                    'forecast_period' => 'next_week',
                    'unit' => 'pcs',
                    'predicted_quantity_issued' => 4,
                ]],
            ]),
        ]);

        $resolved = app(InventoryReorderPointService::class)->resolveMany(
            collect([$this->part()]),
            [901 => ['reorder_point' => 6]]
        );

        $this->assertSame(10, $resolved[901]['reorder_point']);
        $this->assertSame('ml_prediction', $resolved[901]['source']);
        Http::assertSentCount(2);
    }

    public function test_sample_or_inconsistent_model_result_can_never_override_signed_rules(): void
    {
        Config::set('services.inventory_ml.replenishment_lead_days', 14);
        Http::fake(['https://ml.test/inventory/status' => Http::response([
            'model_ready' => true,
            'is_production_model' => false,
            'source' => 'sample',
            'data_source' => 'sample',
            'forecast_scope' => 'bus_part',
        ])]);

        $resolved = app(InventoryReorderPointService::class)->resolveMany(
            collect([$this->part()]),
            [901 => ['reorder_point' => 6]]
        );

        $this->assertSame(6, $resolved[901]['reorder_point']);
        $this->assertSame('signed_issuances', $resolved[901]['source']);
        Http::assertSentCount(1);
    }

    public function test_weaker_ml_forecast_does_not_replace_a_verified_rule(): void
    {
        Config::set('services.inventory_ml.replenishment_lead_days', 7);
        Http::fake([
            'https://ml.test/inventory/status' => Http::response([
                'model_ready' => true,
                'is_production_model' => true,
                'source' => 'genuine',
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
            ]),
            'https://ml.test/inventory/predict/batch' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'forecast_scope' => 'fleet_part',
                'results' => [[
                    'success' => true,
                    'part_id' => '901',
                    'model_ready' => true,
                    'is_production_model' => true,
                    'data_source' => 'genuine',
                    'forecast_scope' => 'fleet_part',
                    'forecast_period' => 'next_week',
                    'unit' => 'pcs',
                    'predicted_quantity_issued' => 2,
                ]],
            ]),
        ]);

        $resolved = app(InventoryReorderPointService::class)->resolveMany(
            collect([$this->part()]),
            [901 => ['reorder_point' => 6]]
        );

        $this->assertSame(6, $resolved[901]['reorder_point']);
        $this->assertSame('signed_issuances', $resolved[901]['source']);
    }

    public function test_inventory_and_dashboard_consume_the_same_reorder_resolver(): void
    {
        $inventoryController = file_get_contents(app_path('Http/Controllers/Warehouse/InventoryController.php'));
        $dashboardController = file_get_contents(app_path('Http/Controllers/Warehouse/WarehouseDashboardController.php'));
        $inventoryView = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));

        $this->assertStringContainsString('InventoryReorderPointService::class', $inventoryController);
        $this->assertStringContainsString('$summaryResolved', $inventoryController);
        $this->assertStringContainsString('$resolveSummaryWithMl', $inventoryController);
        $this->assertStringContainsString('InventoryReorderPointService::class', $dashboardController);
        $this->assertStringContainsString('$resolvedReorderPoints[$item->id]', $inventoryView);
        $this->assertStringContainsString("ml_prediction", $inventoryView);
    }

    public function test_inventory_reorder_column_explains_uncalculated_and_calculation_sources(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
        $resolver = file_get_contents(app_path('Services/Warehouse/InventoryReorderPointService.php'));

        $this->assertStringContainsString('Not Calculated', $view);
        $this->assertStringNotContainsString('>Pending</strong>', $view);
        $this->assertStringContainsString("reorderSource === 'ml_prediction'", $view);
        $this->assertStringContainsString("reorderSource === 'signed_issuances'", $view);
        $this->assertStringContainsString("reorderSource === 'existing_rule'", $view);
        $this->assertStringContainsString('inventory-reorder-auto--existing', $css);
        $this->assertStringContainsString('inventory-reorder-auto--ml', $css);
        $this->assertStringContainsString('--wh-reorder-ml-bg:', $dark);
        $this->assertStringContainsString('does not mean the item is out of stock', $resolver);
    }
}
