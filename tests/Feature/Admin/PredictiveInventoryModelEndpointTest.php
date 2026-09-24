<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictiveInventoryModelEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_URL = '*/inventory/status';

    private const PREDICT_URL = '*/inventory/predict';

    public function test_inventory_prediction_endpoint_returns_frontend_item_forecast(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create([
            'item_code' => 'DEMO-PART-001',
            'parts_name' => 'Brake Pad Set',
            'category' => 'Brakes',
            'on_hand' => 8,
            'quantity_available' => 8,
            'unit' => 'pcs',
            'reorder_level' => 10,
            'status' => 'Low Stock',
        ]);

        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_ready' => true,
                'dataset_type' => 'FRONTEND DEMO / SYNTHETIC WAREHOUSE DATA',
                'data_source' => 'synthetic',
                'is_production_model' => false,
                'model_type' => 'Synthetic / Development Model',
                'runtime_mode' => 'development',
                'message' => 'INVENTORY_ML_READY (DEMO)',
                'disclaimer' => 'DEMO / SYNTHETIC ONLY',
            ]),
            self::PREDICT_URL => Http::response([
                'success' => true,
                'model_ready' => true,
                'source' => 'ml',
                'part_id' => (string) $item->id,
                'part_name' => 'Brake Pad Set',
                'unit' => 'pcs',
                'bus_id' => 'FLEET',
                'forecast_scope' => 'bus_part',
                'forecast_period' => 'next_week',
                'predicted_quantity_issued' => 5.75,
                'data_source' => 'sample',
                'is_production_model' => false,
                'model_type' => 'Sample / Development Model',
                'risk_status' => 'LOW_STOCK',
                'recommended_action' => 'Restocking may be required.',
                'suggested_order_qty' => 7.75,
                'lead_note' => 'Review replenishment timing now.',
                'disclaimer' => 'DEMO / SYNTHETIC ONLY',
            ]),
        ]);

        $response = $this->actingAs($user)->postJson(route('analytics.inventory-predictions'), [
            'item_codes' => [$item->item_code],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('model_ready', true)
            ->assertJsonPath('is_production_model', false)
            ->assertJsonPath("predictions.{$item->item_code}.available", true)
            ->assertJsonPath("predictions.{$item->item_code}.context.on_hand", 8)
            ->assertJsonPath("predictions.{$item->item_code}.prediction.predicted_quantity_issued", 5.75)
            ->assertJsonPath("predictions.{$item->item_code}.prediction.risk_status", 'LOW_STOCK');

        Http::assertSent(function ($request) use ($item): bool {
            if (! str_ends_with($request->url(), '/inventory/predict')) {
                return false;
            }

            return $request['part_id'] === (string) $item->id
                && (float) $request['current_stock'] === 8.0;
        });
    }

    public function test_inventory_prediction_endpoint_does_not_predict_when_model_is_not_ready(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create([
            'item_code' => 'DEMO-PART-002',
            'parts_name' => 'Oil Filter',
            'category' => 'Filters',
            'on_hand' => 20,
            'quantity_available' => 20,
            'unit' => 'pcs',
            'reorder_level' => 8,
            'status' => 'In Stock',
        ]);

        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_ready' => false,
                'dataset_type' => 'GENUINE GCT INVENTORY LEDGER',
                'data_source' => 'genuine',
                'is_production_model' => false,
                'message' => 'MODEL NOT READY',
            ]),
            self::PREDICT_URL => Http::response([
                'success' => true,
                'predicted_quantity_issued' => 99,
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('analytics.inventory-predictions'), [
                'item_codes' => [$item->item_code],
            ])
            ->assertOk()
            ->assertJsonPath('model_ready', false)
            ->assertJsonPath("predictions.{$item->item_code}.available", false)
            ->assertJsonPath("predictions.{$item->item_code}.prediction", null);

        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/inventory/predict'));
    }

    public function test_inventory_prediction_endpoint_requires_authentication(): void
    {
        $this->postJson('/analytics/inventory-predictions', [
            'item_codes' => ['DEMO-PART-001'],
        ])->assertUnauthorized();
    }
}
