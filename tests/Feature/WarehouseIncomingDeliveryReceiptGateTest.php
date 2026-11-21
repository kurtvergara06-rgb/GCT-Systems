<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseIncomingDeliveryReceiptGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchased_parts_must_be_received_before_staff_can_issue(): void
    {
        Storage::fake('local');
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

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-RECEIVE-0001',
            'bus_no' => 'BUS-RECEIVE-01',
            'problem_issue' => 'Replace brake pads',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Receipt Gate Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'review_status' => 'Approved',
            'parts_issue_strategy' => 'wait_until_complete',
            'inventory_snapshot' => ['shortages' => [['name' => 'Brake Pad', 'quantity' => 2, 'unit' => 'pcs']]],
            'part_status' => 'For Purchase',
            'status' => 'On Hold',
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

        // Direct PR issuance is always forbidden; physical handover must use IR/SWIS.
        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $originalRequest))
            ->assertSessionHas('error')
            ->assertRedirect(route('warehouse.item-requests'));

        $this->assertNotSame('Issued', $originalRequest->fresh()->warehouse_status);
        $this->assertNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertNull(InventoryItem::query()->where('item_name', 'Brake Pad')->first());

        // Warehouse physically receives the PO. This is the stock-in event.
        $this->actingAs($warehouseHead)
            ->post(route('incoming-deliveries.receive', $purchaseOrder), [
                'received_quantities' => [2],
            ])
            ->assertRedirect('/warehouse/incoming-deliveries');

        $inventoryItem = InventoryItem::query()
            ->where('item_name', 'Brake Pad')
            ->firstOrFail();

        $this->assertNotNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertSame('Delivered', $purchaseRequest->fresh()->status);
        $this->assertSame(2, (int) $inventoryItem->quantity_available);

        // Once received into inventory, Warehouse Staff can record the signed IR handover.
        $itemRequest = ItemRequest::create([
            'job_order_id' => $jobOrder->id,
            'requested_by' => $warehouseStaff->id,
            'status' => 'Submitted',
            'requested_parts' => [[
                'name' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'pcs',
                'inventory_item_id' => $inventoryItem->id,
            ]],
        ]);
        $this->actingAs($warehouseStaff)
            ->post(route('warehouse.item-requests.issue', $itemRequest), [
                'received_by' => 'Receipt Gate Mechanic',
                'signed_slip' => UploadedFile::fake()->create('receipt-gate.pdf', 100, 'application/pdf'),
                'handover_confirmed' => '1',
                'issued_quantities' => [2],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Issued', $itemRequest->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(0, (int) $inventoryItem->fresh()->quantity_available);
    }

    public function test_partial_receipts_restock_the_same_ir_for_successive_signed_swis_batches(): void
    {
        Storage::fake('local');
        $warehouse = User::factory()->create([
            'department' => 'Warehouse', 'role' => 'head', 'status' => 'Active',
        ]);
        $item = InventoryItem::create([
            'item_code' => 'RECEIPT-FILTER', 'parts_name' => 'Oil Filter',
            'item_name' => 'Oil Filter', 'category' => 'Filters',
            'on_hand' => 2, 'quantity_available' => 2,
            'unit' => 'pcs', 'unit_of_measurement' => 'pcs', 'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-RECEIVE-PARTIAL', 'bus_no' => 'BUS-RECEIVE-02',
            'problem_issue' => 'Replace filters', 'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Oil Filter - Qty: 7 pcs',
            'review_status' => 'Approved', 'parts_issue_strategy' => 'issue_available_only',
            'inventory_snapshot' => null, // Legacy JO must still get replenishment top-ups.
            'part_status' => 'Submitted', 'status' => 'On Hold',
        ]);
        \App\Models\Maintenance\JobOrderPartReservation::create([
            'job_order_id' => $jo->id, 'inventory_item_id' => $item->id, 'quantity' => 2,
        ]);
        $ir = ItemRequest::create([
            'job_order_id' => $jo->id, 'requested_by' => $warehouse->id,
            'status' => 'Submitted',
            'requested_parts' => [[
                'name' => 'Oil Filter', 'quantity' => 7, 'unit' => 'pcs',
                'inventory_item_id' => $item->id,
            ]],
        ]);
        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-RECEIVE-PARTIAL',
            'job_order_no' => $jo->job_order_no, 'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter - Qty: 5 pcs', 'quantity' => 5,
            'status' => 'For Delivery', 'source_type' => 'Maintenance Request',
        ]);
        $po = PurchaseOrder::create([
            'po_no' => 'PO-RECEIVE-PARTIAL', 'po_date' => today()->toDateString(),
            'purchase_request_id' => $pr->id,
            'supplier_name' => 'Test Supplier',
            'items' => [[
                'pr_no' => $pr->pr_no, 'item_description' => 'Oil Filter',
                'quantity' => 5, 'unit' => 'pcs', 'cost' => 100,
            ]],
            'status' => 'For Delivery',
        ]);

        $issue = function (int $quantity, string $filename) use ($warehouse, $ir): void {
            $this->actingAs($warehouse)->post(route('warehouse.item-requests.issue', $ir), [
                'received_by' => 'Test Mechanic',
                'signed_slip' => UploadedFile::fake()->create($filename, 100, 'application/pdf'),
                'handover_confirmed' => '1',
                'issued_quantities' => [$quantity],
            ])->assertRedirect()->assertSessionHasNoErrors();
        };
        $issue(2, 'first.pdf');
        $this->assertSame('Partially Issued', $ir->fresh()->status);

        $this->actingAs($warehouse)->post(route('incoming-deliveries.receive', $po), [
            'received_quantities' => [2],
        ])->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();
        $this->assertNull($po->fresh()->inventory_posted_at);
        $this->assertSame(2, (int) $item->fresh()->quantity_available);
        $this->assertSame(2, (int) \App\Models\Maintenance\JobOrderPartReservation::where('job_order_id', $jo->id)->sum('quantity'));
        $this->assertSame(1, ItemRequest::where('job_order_id', $jo->id)->count());
        $issue(2, 'second.pdf');

        $this->actingAs($warehouse)->post(route('incoming-deliveries.receive', $po), [
            'received_quantities' => [3],
        ])->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();
        $this->assertNotNull($po->fresh()->inventory_posted_at);
        $this->assertSame(3, (int) $item->fresh()->quantity_available);
        $this->assertSame(3, (int) \App\Models\Maintenance\JobOrderPartReservation::where('job_order_id', $jo->id)->sum('quantity'));
        $issue(3, 'third.pdf');

        $this->assertSame('Issued', $ir->fresh()->status);
        $this->assertSame(0, (int) $item->fresh()->quantity_available);
        $this->assertSame(1, ItemRequest::where('job_order_id', $jo->id)->count());
        $this->assertSame(3, \App\Models\Warehouse\InventoryIssuance::query()->confirmedSigned()->count());
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
            'status' => 'For Review',
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
