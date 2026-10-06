<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseIncomingDeliveryReceiptGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchased_parts_must_be_received_before_staff_can_issue(): void
    {
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $originalRequest = PurchaseRequest::create([
            'pr_no' => 'PR-RECEIVE-0001',
            'job_order_no' => 'JO-RECEIVE-0001',
            'bus_no' => 'BUS-RECEIVE-01',
            'item' => 'Brake Pad - Qty: 2 pcs',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Pending Warehouse Approval',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Missing parts sent to Purchase as PR-RECEIVE-0001-P1.',
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => 'PR-RECEIVE-0001-P1',
            'job_order_no' => 'JO-RECEIVE-0001',
            'bus_no' => 'BUS-RECEIVE-01',
            'item' => 'Brake Pad - Qty: 2 pcs',
            'quantity' => 2,
            'status' => 'For Delivery',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Missing parts from PR-RECEIVE-0001. Only unavailable parts were sent to Purchase Department.',
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_no' => 'PO-RECEIVE-0001',
            'po_date' => now()->toDateString(),
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_name' => 'Receipt Gate Supplier',
            'items' => [[
                'pr_no' => $purchaseRequest->pr_no,
                'bus_no' => $purchaseRequest->bus_no,
                'item_description' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'pcs',
                'cost' => 100,
            ]],
            'gross_amount' => 200,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 200,
            'status' => 'For Delivery',
        ]);

        // A purchased part that is merely in transit cannot be issued.
        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $originalRequest))
            ->assertSessionHasErrors('workflow');

        $this->assertNotSame('Issued', $originalRequest->fresh()->warehouse_status);
        $this->assertNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertNull(InventoryItem::query()->where('item_name', 'Brake Pad')->first());

        // Warehouse physically receives the PO. This is the stock-in event.
        $this->actingAs($warehouseHead)
            ->post(route('incoming-deliveries.receive', $purchaseOrder))
            ->assertRedirect('/warehouse/incoming-deliveries');

        $inventoryItem = InventoryItem::query()
            ->where('item_name', 'Brake Pad')
            ->firstOrFail();

        $this->assertNotNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertSame('Delivered', $purchaseRequest->fresh()->status);
        $this->assertSame(2, (int) $inventoryItem->quantity_available);

        // Once received into inventory, Warehouse Staff can issue it directly.
        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $originalRequest))
            ->assertRedirect();

        $this->assertSame('Issued', $originalRequest->fresh()->status);
        $this->assertSame('Issued', $originalRequest->fresh()->warehouse_status);
        $this->assertSame(0, (int) $inventoryItem->fresh()->quantity_available);
    }

    public function test_ineligible_or_invalid_purchase_order_cannot_change_inventory(): void
    {
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $order = PurchaseOrder::create([
            'po_no' => 'PO-INVALID-STATE',
            'po_date' => today(),
            'supplier_name' => 'Invalid State Supplier',
            'items' => [[
                'item_description' => 'Unsafe Part',
                'quantity' => 1,
                'unit' => 'pcs',
                'cost' => 1,
            ]],
            'status' => 'Ordered',
        ]);

        $this->actingAs($warehouseHead)
            ->post(route('incoming-deliveries.receive', $order))
            ->assertSessionHasErrors('delivery');

        $this->assertNull($order->fresh()->inventory_posted_at);
        $this->assertDatabaseMissing('inventory_items', ['item_name' => 'Unsafe Part']);

        $order->update([
            'status' => 'For Delivery',
            'items' => [[
                'item_description' => 'Unsafe Part',
                'quantity' => 0,
                'unit' => 'pcs',
                'cost' => 1,
            ]],
        ]);

        $this->actingAs($warehouseHead)
            ->post(route('incoming-deliveries.receive', $order))
            ->assertSessionHasErrors('delivery');

        $this->assertNull($order->fresh()->inventory_posted_at);
        $this->assertDatabaseMissing('inventory_items', ['item_name' => 'Unsafe Part']);
    }
}
