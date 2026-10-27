<?php

namespace Tests\Feature\Warehouse;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryImportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_cannot_adjust_stock_below_active_reservations(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'IMPORT-GUARD-01',
            'item_name' => 'Oil Filter',
            'parts_name' => 'Oil Filter',
            'category' => 'Filters',
            'on_hand' => 10,
            'quantity_available' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-IMPORT-GUARD',
            'bus_no' => 'BUS-IMPORT-GUARD',
            'problem_issue' => 'Replace filters',
            'maintenance_type' => 'Repair',
            'review_status' => 'Approved',
            'status' => 'On Hold',
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jobOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 8,
        ]);

        try {
            app(InventoryImportService::class)->reconcile([
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'parts_name' => $item->parts_name,
                'category' => $item->category,
                'on_hand' => 5,
                'quantity_available' => 5,
                'unit' => 'pcs',
                'unit_of_measurement' => 'pcs',
                'reorder_level' => 1,
            ], 'Test import');
            $this->fail('Import must not reduce stock below active reservations.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('reserved', $exception->getMessage());
        }

        $this->assertSame(10, (int) $item->fresh()->on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_import_stock_change_is_recorded_in_the_central_ledger(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'IMPORT-LEDGER-01',
            'item_name' => 'Fan Belt',
            'parts_name' => 'Fan Belt',
            'category' => 'Mechanical',
            'on_hand' => 2,
            'quantity_available' => 2,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);

        $updated = app(InventoryImportService::class)->reconcile([
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'parts_name' => $item->parts_name,
            'category' => $item->category,
            'on_hand' => 7,
            'quantity_available' => 7,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ], 'Test import');

        $this->assertSame(7, (int) $updated->on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'Adjustment',
            'quantity_change' => 5,
            'previous_stock' => 2,
            'new_stock' => 7,
        ]);
    }
}
