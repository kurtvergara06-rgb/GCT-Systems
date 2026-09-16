<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin\User;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use App\Services\Warehouse\InventoryLedgerService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function forgeItem(
        string $code = 'OIL-ENG-15W40',
        int $stock = 10,
        string $unit = 'liter'
    ): InventoryItem {
        return InventoryItem::create([
            'item_code' => $code,
            'item_name' => 'Engine Oil 15W-40',
            'category' => 'Lubricants & Fluids',
            'quantity_available' => $stock,
            'unit_of_measurement' => $unit,
            'reorder_level' => 0,
            'supplier' => 'Test Supplier',
            'storage_location' => 'Warehouse',
        ]);
    }

    private function warehouseUser(): User
    {
        return User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
        ]);
    }

    public function test_ledger_stock_in_increases_stock_and_writes_referenced_movement(): void
    {
        $item = $this->forgeItem('OIL-001', 10, 'liter');

        $movement = app(InventoryLedgerService::class)
            ->stockIn($item, 4, 'PO-2026-0001', 'Received from Purchase Order.');

        $this->assertSame('Stock In', $movement->movement_type);
        $this->assertSame(4, $movement->quantity_change);
        $this->assertSame(10, $movement->previous_stock);
        $this->assertSame(14, $movement->new_stock);
        $this->assertSame('PO-2026-0001', $movement->reference_no);
        $this->assertSame('app', $movement->source);
        $this->assertSame(14, (int) $item->fresh()->on_hand);
        $this->assertSame(14, (int) $item->fresh()->quantity_available);
    }

    public function test_ledger_stock_out_decreases_stock_and_writes_signed_movement(): void
    {
        $item = $this->forgeItem('BRK-001', 5, 'pcs');

        $movement = app(InventoryLedgerService::class)
            ->stockOut($item, 2, 'ISS-2026-0001', 'Issued to Maintenance.');

        $this->assertSame('Stock Out', $movement->movement_type);
        $this->assertSame(-2, $movement->quantity_change);
        $this->assertSame(5, $movement->previous_stock);
        $this->assertSame(3, $movement->new_stock);
        $this->assertSame('ISS-2026-0001', $movement->reference_no);
        $this->assertSame(3, (int) $item->fresh()->on_hand);
    }

    public function test_ledger_rejects_stock_out_exceeding_available_and_leaves_no_movement(): void
    {
        $item = $this->forgeItem('BRK-002', 2, 'pcs');

        try {
            app(InventoryLedgerService::class)->stockOut($item, 5, 'ISS-2026-0002');
            $this->fail('Expected InvalidArgumentException for insufficient stock.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Insufficient stock', $exception->getMessage());
        }

        $this->assertSame(2, (int) $item->fresh()->on_hand);
        $this->assertSame(2, (int) $item->fresh()->quantity_available);
        $this->assertSame(0, StockMovement::where('inventory_item_id', $item->id)->count());
    }

    public function test_ledger_adjust_to_writes_adjustment_movement(): void
    {
        $item = $this->forgeItem('ADJ-001', 10, 'pcs');

        $movement = app(InventoryLedgerService::class)
            ->adjustTo($item, 7, 'ADJ-001', 'Manual inventory adjustment.');

        $this->assertSame('Adjustment', $movement->movement_type);
        $this->assertSame(-3, $movement->quantity_change);
        $this->assertSame(10, $movement->previous_stock);
        $this->assertSame(7, $movement->new_stock);
        $this->assertSame(7, (int) $item->fresh()->on_hand);
    }

    public function test_every_movement_keeps_new_stock_equals_previous_plus_change_invariant(): void
    {
        $item = $this->forgeItem('INV-001', 8, 'liter');
        $ledger = app(InventoryLedgerService::class);

        $ledger->stockIn($item, 6, 'PO-2026-0010');
        $ledger->stockOut($item, 5, 'ISS-2026-0010');
        $ledger->adjustTo($item, 4, 'ADJ');
        $ledger->stockOut($item, 1, 'ISS-2026-0011');

        $movements = StockMovement::where('inventory_item_id', $item->id)->orderBy('id')->get();

        $this->assertCount(4, $movements);

        $running = 8;

        foreach ($movements as $movement) {
            $this->assertSame($movement->previous_stock, $running);
            $this->assertSame($movement->new_stock, $movement->previous_stock + $movement->quantity_change);
            $running = $movement->new_stock;
        }

        $this->assertSame(3, (int) $item->fresh()->on_hand);
        $this->assertSame(3, (int) $item->fresh()->quantity_available);
    }

    public function test_model_crud_does_not_fabricate_movements(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'CRUD-001',
            'item_name' => 'Test Part',
            'category' => 'Parts',
            'quantity_available' => 0,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 0,
        ]);

        $this->assertSame(0, StockMovement::count());

        $item->update([
            'supplier' => 'Supplier A',
            'status' => 'In Stock',
        ]);

        $this->assertSame(0, StockMovement::count(), 'Metadata changes must not log movements.');

        $item->update([
            'quantity_available' => 5,
            'on_hand' => 5,
        ]);

        $this->assertSame(
            0,
            StockMovement::count(),
            'Direct model stock writes must not fabricate movements; only the ledger may.'
        );
    }

    public function test_po_receipt_creates_stock_in_movements_with_po_reference(): void
    {
        $user = User::factory()->create();
        $item = $this->forgeItem('OIL-PO', 10, 'liter');

        $purchaseOrder = PurchaseOrder::create([
            'po_no' => 'PO-2026-TEST1',
            'po_date' => now(),
            'supplier_name' => 'AutoPlus Trading Cebu',
            'items' => [
                [
                    'pr_no' => null,
                    'item_description' => 'Engine Oil 15W-40',
                    'quantity' => 4,
                    'unit' => 'liter',
                    'cost' => 250,
                ],
            ],
            'status' => 'For Delivery',
        ]);

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'Delivered',
                'warehouse_receive' => 1,
            ])
            ->assertRedirect();

        $this->assertNotNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertSame(14, (int) $item->fresh()->on_hand);

        $movement = StockMovement::where('inventory_item_id', $item->id)->firstOrFail();

        $this->assertSame('Stock In', $movement->movement_type);
        $this->assertSame(4, $movement->quantity_change);
        $this->assertSame(10, $movement->previous_stock);
        $this->assertSame(14, $movement->new_stock);
        $this->assertSame('PO-2026-TEST1', $movement->reference_no);
        $this->assertSame('app', $movement->source);
    }

    public function test_po_receipt_creating_a_new_item_writes_an_opening_stock_in_movement(): void
    {
        $user = User::factory()->create();

        $purchaseOrder = PurchaseOrder::create([
            'po_no' => 'PO-2026-TEST2',
            'po_date' => now(),
            'supplier_name' => 'Tri-City Auto Supply',
            'items' => [
                [
                    'pr_no' => null,
                    'item_description' => 'Air Filter',
                    'quantity' => 3,
                    'unit' => 'pc',
                    'cost' => 120,
                ],
            ],
            'status' => 'For Pick-up',
        ]);

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'Picked Up',
                'warehouse_receive' => 1,
            ])
            ->assertRedirect();

        $item = InventoryItem::where('item_name', 'Air Filter')->firstOrFail();

        $this->assertSame(3, (int) $item->on_hand);

        $movement = StockMovement::where('inventory_item_id', $item->id)->firstOrFail();

        $this->assertSame('Stock In', $movement->movement_type);
        $this->assertSame(3, $movement->quantity_change);
        $this->assertSame(0, $movement->previous_stock);
        $this->assertSame(3, $movement->new_stock);
        $this->assertSame('PO-2026-TEST2', $movement->reference_no);
    }

    public function test_issuance_via_http_deducts_stock_and_writes_signed_movement(): void
    {
        $user = $this->warehouseUser();
        $item = $this->forgeItem('ENG-OIL', 10, 'liter');

        $this->actingAs($user)
            ->post(route('inventory.issue'), [
                'inventory_item_id' => $item->id,
                'quantity' => 4,
                'issued_to' => 'Maintenance',
                'purpose' => 'Preventive Maintenance',
                'reference_no' => 'JO-2026-015',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(6, (int) $item->fresh()->on_hand);

        $issuance = InventoryIssuance::firstOrFail();
        $issuanceItem = InventoryIssuanceItem::firstOrFail();
        $movement = StockMovement::firstOrFail();

        $this->assertMatchesRegularExpression('/^ISS-2026-\d{4}$/', $issuance->issue_no);
        $this->assertSame('Maintenance', $issuance->issued_to);
        $this->assertSame('Preventive Maintenance', $issuance->purpose);
        $this->assertSame('JO-2026-015', $issuance->reference_no);

        $this->assertSame($issuance->id, $issuanceItem->inventory_issuance_id);
        $this->assertSame($movement->id, $issuanceItem->stock_movement_id);
        $this->assertSame(10, $issuanceItem->previous_stock);
        $this->assertSame(6, $issuanceItem->new_stock);

        $this->assertSame('Stock Out', $movement->movement_type);
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertSame(10, $movement->previous_stock);
        $this->assertSame(6, $movement->new_stock);
        $this->assertSame($issuance->issue_no, $movement->reference_no);
        $this->assertSame('app', $movement->source);
        $this->assertSame('liter', $movement->unit);
    }

    public function test_issuance_rejects_insufficient_stock_atomically(): void
    {
        $this->actingAs($this->warehouseUser());

        $item = $this->forgeItem('BRK-PAD', 2, 'set');

        $this->post(route('inventory.issue'), [
            'inventory_item_id' => $item->id,
            'quantity' => 5,
            'issued_to' => 'Maintenance',
            'purpose' => 'Brake replacement',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(2, (int) $item->fresh()->on_hand);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, InventoryIssuance::count());
        $this->assertSame(0, InventoryIssuanceItem::count());
    }

    public function test_duplicate_issue_submission_produces_unique_issue_documents(): void
    {
        $user = $this->warehouseUser();
        $item = $this->forgeItem('TIR-001', 5, 'pc');

        $payload = [
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'issued_to' => 'Maintenance',
            'purpose' => 'Tire replacement',
        ];

        $this->actingAs($user)->post(route('inventory.issue'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('inventory.issue'), $payload)->assertRedirect();

        $this->assertSame(1, (int) $item->fresh()->on_hand);

        $issuances = InventoryIssuance::orderBy('id')->get();

        $this->assertCount(2, $issuances);
        $this->assertCount(2, $issuances->pluck('issue_no')->unique(), 'Each issuance must have a unique Issue No.');
        $this->assertNotSame($issuances[0]->issue_no, $issuances[1]->issue_no);

        $this->assertSame(2, StockMovement::where('movement_type', 'Stock Out')->count());
        $this->assertSame(
            2,
            StockMovement::where('movement_type', 'Stock Out')->pluck('reference_no')->unique()->count()
        );
    }

    public function test_po_receipt_cannot_be_posted_twice(): void
    {
        $user = User::factory()->create();
        $item = $this->forgeItem('OIL-DBL', 10, 'liter');

        $purchaseOrder = PurchaseOrder::create([
            'po_no' => 'PO-2026-DBL',
            'po_date' => now(),
            'supplier_name' => 'PhilHino Sales Corp.',
            'items' => [
                [
                    'item_description' => 'Engine Oil 15W-40',
                    'quantity' => 3,
                    'unit' => 'liter',
                    'cost' => 250,
                ],
            ],
            'status' => 'For Delivery',
        ]);

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'Delivered',
                'warehouse_receive' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'Delivered',
                'warehouse_receive' => 1,
            ]);

        $this->assertSame(13, (int) $item->fresh()->on_hand);
        $this->assertSame(1, StockMovement::where('inventory_item_id', $item->id)->count());
    }

    public function test_unit_of_measure_is_preserved_on_every_movement(): void
    {
        $item = $this->forgeItem('GRS-001', 18, 'kg');

        app(InventoryLedgerService::class)->stockIn($item, 5, 'PO-2026-100');

        $this->actingAs($this->warehouseUser())
            ->post(route('inventory.issue'), [
                'inventory_item_id' => $item->id,
                'quantity' => 3,
                'issued_to' => 'Maintenance',
                'purpose' => 'Lubrication',
            ])->assertRedirect();

        $units = StockMovement::where('inventory_item_id', $item->id)->pluck('unit')->unique();

        $this->assertSame(['kg'], $units->all());
    }

    public function test_issuance_is_restricted_to_warehouse_personnel(): void
    {
        $nonWarehouse = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
        ]);

        $item = $this->forgeItem('FLT-001', 3, 'pc');

        $this->actingAs($nonWarehouse)
            ->post(route('inventory.issue'), [
                'inventory_item_id' => $item->id,
                'quantity' => 1,
                'issued_to' => 'Maintenance',
                'purpose' => 'Testing',
            ])
            ->assertForbidden();

        $this->assertSame(3, (int) $item->fresh()->on_hand);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_opening_balance_on_new_item_is_recorded_as_adjustment_movement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('inventory.store'), [
                'item_code' => 'NEW-OPN-001',
                'item_name' => 'New Spare Part',
                'category' => 'Engine Parts',
                'on_hand' => 20,
                'unit_of_measurement' => 'pcs',
                'reorder_level' => 0,
            ])
            ->assertRedirect();

        $item = InventoryItem::where('item_code', 'NEW-OPN-001')->firstOrFail();

        $this->assertSame(20, (int) $item->on_hand);

        $movement = StockMovement::where('inventory_item_id', $item->id)->firstOrFail();

        $this->assertSame('Adjustment', $movement->movement_type);
        $this->assertSame(20, $movement->quantity_change);
        $this->assertSame(0, $movement->previous_stock);
        $this->assertSame(20, $movement->new_stock);
        $this->assertSame('app', $movement->source);
    }

    public function test_seeder_no_longer_defines_fabricated_stock_movement_generation(): void
    {
        $this->assertFalse(
            method_exists(DemoDataSeeder::class, 'seedStockMovements'),
            'DemoDataSeeder must not generate fabricated inventory transactions.'
        );
    }
}