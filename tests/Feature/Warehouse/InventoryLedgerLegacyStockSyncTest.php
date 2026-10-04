<?php

namespace Tests\Feature\Warehouse;

use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryLedgerLegacyStockSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_out_uses_quantity_available_when_legacy_on_hand_is_stale(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'CLUTCH-DISC-001',
            'item_name' => 'Clutch Disc',
            'category' => 'Transmission',
            'quantity_available' => 154,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 5,
        ]);

        // Simulate an older/imported row that drifted before both stock columns
        // were kept in sync by the current model/ledger write paths.
        DB::table('inventory_items')
            ->where('id', $item->id)
            ->update([
                'on_hand' => 0,
                'quantity_available' => 154,
            ]);

        $movement = app(InventoryLedgerService::class)->stockOut(
            $item->fresh(),
            1,
            'PR-GCT-0012',
            'Issued through Warehouse Part Request.'
        );

        $freshItem = $item->fresh();

        $this->assertSame(154, (int) $movement->previous_stock);
        $this->assertSame(-1, (int) $movement->quantity_change);
        $this->assertSame(153, (int) $movement->new_stock);
        $this->assertSame(153, (int) $freshItem->quantity_available);
        $this->assertSame(153, (int) $freshItem->on_hand);
    }
}
