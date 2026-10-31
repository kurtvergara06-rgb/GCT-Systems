<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseRestockingPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_multi_item_restock_pr_reaches_purchasing_and_posts_partial_receipts_once(): void
    {
        Storage::fake('local');

        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse', 'role' => 'head', 'status' => 'Active',
        ]);
        $purchaseStaff = User::factory()->create([
            'department' => 'Purchase', 'role' => 'staff', 'status' => 'Active',
        ]);
        $purchaseHead = User::factory()->create([
            'department' => 'Purchase', 'role' => 'head', 'status' => 'Active',
        ]);
        $filter = InventoryItem::create([
            'item_code' => 'RST-FILTER', 'item_name' => 'QA Restock Filter',
            'parts_name' => 'QA Restock Filter', 'category' => 'Filters',
            'on_hand' => 10, 'quantity_available' => 10,
            'unit' => 'pcs', 'unit_of_measurement' => 'pcs', 'reorder_level' => 4,
        ]);
        $belt = InventoryItem::create([
            'item_code' => 'RST-BELT', 'item_name' => 'QA Restock Belt',
            'parts_name' => 'QA Restock Belt', 'category' => 'Mechanical',
            'on_hand' => 5, 'quantity_available' => 5,
            'unit' => 'pcs', 'unit_of_measurement' => 'pcs', 'reorder_level' => 3,
        ]);

        $response = $this->actingAs($warehouseHead)->post(route('part-requests.restock.store'), [
            'purpose' => 'QA restocking workflow',
            'remarks' => 'Isolated automated test request',
            'approval_notes' => 'Approved for workflow verification',
            'signature' => UploadedFile::fake()->create('warehouse-signature.png', 20, 'image/png'),
            'items' => [
                ['inventory_item_id' => $filter->id, 'quantity' => 3],
                ['inventory_item_id' => $belt->id, 'quantity' => 4],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('message', 'Restocking PR submitted to Purchasing.');
        $restock = PurchaseRequest::query()->where('source_type', 'Auto Restock')->firstOrFail();
        $this->assertSame('For Purchase', $restock->status);
        $this->assertSame(7, (int) $restock->quantity);
        $this->assertNotEmpty($restock->restock_signature_path);
        Storage::disk('local')->assertExists($restock->restock_signature_path);
        $this->assertSame(10, (int) $filter->fresh()->quantity_available);
        $this->assertSame(5, (int) $belt->fresh()->quantity_available);

        $this->actingAs($warehouseHead)
            ->get(route('part-requests', ['search' => $restock->pr_no]))
            ->assertOk()->assertSee($restock->pr_no)->assertSee('Warehouse Restock');
        $this->actingAs($purchaseStaff)
            ->get(route('inventory-restock', ['search' => $restock->pr_no]))
            ->assertOk()->assertSee($restock->pr_no)->assertSee('QA Restock Filter');

        $this->actingAs($warehouseHead)->post(route('part-requests.restock.store'), [
            'purpose' => 'Duplicate active request',
            'signature' => UploadedFile::fake()->create('duplicate.png', 20, 'image/png'),
            'items' => [['inventory_item_id' => $filter->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');
        $this->assertSame(1, PurchaseRequest::query()->where('source_type', 'Auto Restock')->count());

        $wrongPo = $this->actingAs($purchaseStaff)->post(route('purchase-orders.store'), [
            'purchase_request_id' => $restock->id,
            'supplier_name' => 'QA Supplier',
            'status' => 'For Review',
            'items' => [[
                'pr_no' => $restock->pr_no, 'item_description' => 'QA Restock Filter',
                'quantity' => 2, 'unit' => 'pcs', 'cost' => 100,
            ]],
        ]);
        $wrongPo->assertSessionHasErrors('items');
        $this->assertDatabaseCount('purchase_orders', 0);

        $this->actingAs($purchaseStaff)->post(route('purchase-orders.store'), [
            'purchase_request_id' => $restock->id,
            'supplier_name' => 'QA Supplier',
            'status' => 'For Review',
            'items' => [
                ['pr_no' => $restock->pr_no, 'item_description' => 'QA Restock Filter', 'quantity' => 3, 'unit' => 'pcs', 'cost' => 100],
                ['pr_no' => $restock->pr_no, 'item_description' => 'QA Restock Belt', 'quantity' => 4, 'unit' => 'pcs', 'cost' => 150],
            ],
        ])->assertRedirect('/purchase-orders')->assertSessionHasNoErrors();

        $order = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('For Review', $order->status);
        $this->assertSame(900.0, (float) $order->net_amount);

        $this->actingAs($purchaseHead)->patch(route('purchase-orders.approve', $order), [
            'signature' => UploadedFile::fake()->create('purchase-head-signature.png', 20, 'image/png'),
            'review_remarks' => 'Supplier and quantities verified.',
        ])->assertRedirect('/purchase-orders')->assertSessionHasNoErrors();
        $this->assertSame('Ordered', $order->fresh()->status);
        $this->assertSame('Ordered', $restock->fresh()->status);
        Storage::disk('local')->assertExists($order->fresh()->approval_signature_path);

        $this->actingAs($warehouseHead)->post(route('incoming-deliveries.receive', $order), [
            'received_quantities' => [2, 0],
        ])->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->inventory_posted_at);
        $this->assertSame([2, 0], array_map('intval', $order->fresh()->received_quantities));
        $this->assertSame(12, (int) $filter->fresh()->quantity_available);
        $this->assertSame(5, (int) $belt->fresh()->quantity_available);

        $this->actingAs($warehouseHead)->post(route('incoming-deliveries.receive', $order), [
            'received_quantities' => [0, 0],
        ])->assertSessionHasErrors('delivery');
        $this->assertSame(12, (int) $filter->fresh()->quantity_available);

        $this->actingAs($warehouseHead)->post(route('incoming-deliveries.receive', $order), [
            'received_quantities' => [1, 4],
        ])->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->inventory_posted_at);
        $this->assertSame('Delivered', $order->fresh()->status);
        $this->assertSame('Delivered', $restock->fresh()->status);
        $this->assertSame(13, (int) $filter->fresh()->quantity_available);
        $this->assertSame(9, (int) $belt->fresh()->quantity_available);
        $this->assertSame(3, StockMovement::query()->where('reference_no', $order->po_no)->count());

        $this->actingAs($warehouseHead)->post(route('incoming-deliveries.receive', $order), [
            'received_quantities' => [1, 1],
        ])->assertSessionHasErrors('delivery');
        $this->assertSame(13, (int) $filter->fresh()->quantity_available);
        $this->assertSame(9, (int) $belt->fresh()->quantity_available);
    }
}
