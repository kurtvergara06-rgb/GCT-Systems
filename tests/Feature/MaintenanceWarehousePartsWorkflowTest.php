<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWarehousePartsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_order_parts_flow_from_purchase_request_creation_to_warehouse_issue(): void
    {
        $user = User::factory()->create();
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
        ]);
        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-0100',
            'bus_no' => 'BUS-0100',
            'problem_issue' => 'Brake pads worn',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('job-orders.create-pr', $jobOrder));

        $createResponse->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Submitted', $purchaseRequest->status);
        $this->assertSame('Maintenance Request', $purchaseRequest->source_type);
        $this->assertSame(2, (int) $purchaseRequest->quantity);
        $this->assertSame('Submitted', $jobOrder->fresh()->part_status);

        // Approval is covered by the Maintenance approval action. At this point
        // the Warehouse handoff requires the request to be approved.
        $purchaseRequest->update(['status' => 'Approved']);
        $jobOrder->update(['part_status' => 'Approved']);

        $inventoryItem = InventoryItem::create([
            'item_code' => 'BRAKE-PAD',
            'item_name' => 'Brake Pad',
            'category' => 'Parts',
            'quantity_available' => 5,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
            'supplier' => 'Test Supplier',
            'storage_location' => 'Warehouse 1',
        ]);

        $this->actingAs($warehouseHead)
            ->post(route('part-requests.approve-for-issue', $purchaseRequest))
            ->assertRedirect();

        $this->assertSame('Approved for Issue', $purchaseRequest->fresh()->warehouse_status);
        $this->assertSame(5, (int) $inventoryItem->fresh()->quantity_available);

        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.prepare', $purchaseRequest))
            ->assertRedirect();

        $this->assertSame('Preparing', $purchaseRequest->fresh()->warehouse_status);
        $this->assertSame(5, (int) $inventoryItem->fresh()->quantity_available);

        $issueResponse = $this
            ->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest), [
                'issued_quantities' => [2],
            ]);

        $issueResponse->assertRedirect();

        $this->assertSame('Issued', $purchaseRequest->fresh()->status);
        $this->assertSame('Issued', $purchaseRequest->fresh()->warehouse_status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(3, (int) $inventoryItem->fresh()->quantity_available);
        $this->assertSame(2, $purchaseRequest->fresh()->warehouse_issue_quantities[0]['issued']);
    }

    public function test_warehouse_roles_are_enforced_for_approval_preparation_and_issue(): void
    {
        $head = User::factory()->create(['department' => 'Warehouse', 'role' => 'head']);
        $staff = User::factory()->create(['department' => 'Warehouse', 'role' => 'staff']);
        $item = InventoryItem::create([
            'item_code' => 'FILTER-01',
            'item_name' => 'Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 4,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $request = PurchaseRequest::create([
            'pr_no' => 'PR-RBAC-01',
            'job_order_no' => 'JO-RBAC-01',
            'bus_no' => 'BUS-01',
            'item' => 'Oil Filter - Qty: 2 pcs',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Pending Warehouse Approval',
        ]);

        $this->actingAs($staff)
            ->post(route('part-requests.approve-for-issue', $request))
            ->assertForbidden();

        $this->actingAs($head)
            ->post(route('part-requests.prepare', $request))
            ->assertForbidden();

        $this->actingAs($head)
            ->post(route('part-requests.approve-for-issue', $request))
            ->assertRedirect();

        $this->actingAs($head)
            ->post(route('part-requests.issue', $request), ['issued_quantities' => [2]])
            ->assertForbidden();

        $this->assertSame(4, (int) $item->fresh()->quantity_available);
    }
}
