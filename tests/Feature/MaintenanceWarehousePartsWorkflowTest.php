<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Admin\RolePermission;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWarehousePartsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_order_parts_flow_from_purchase_request_creation_to_warehouse_issue(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
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

        $this->assertSame(5, (int) $inventoryItem->fresh()->quantity_available);

        $issueResponse = $this
            ->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest));

        $issueResponse->assertRedirect();

        $this->assertSame('Issued', $purchaseRequest->fresh()->status);
        $this->assertSame('Issued', $purchaseRequest->fresh()->warehouse_status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(3, (int) $inventoryItem->fresh()->quantity_available);
        $this->assertSame(2, $purchaseRequest->fresh()->warehouse_issue_quantities[0]['issued']);

        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest))
            ->assertSessionHas('error');

        $this->assertSame(3, (int) $inventoryItem->fresh()->quantity_available);
        $this->assertSame(1, InventoryIssuance::where('reference_no', $purchaseRequest->pr_no)->count());
    }

    public function test_warehouse_edit_capability_controls_direct_issue(): void
    {
        $head = User::factory()->create(['department' => 'Warehouse', 'role' => 'head', 'status' => 'Active']);
        $staff = User::factory()->create(['department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active']);
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

        $headPermissions = RolePermission::where('role_key', 'warehouse_head')->firstOrFail();
        $headMatrix = $headPermissions->permissions;
        data_set($headMatrix, 'warehouse.edit', false);
        $headPermissions->update(['permissions' => $headMatrix]);

        $this->actingAs($head)
            ->post(route('part-requests.issue', $request))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('part-requests.issue', $request))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, (int) $item->fresh()->quantity_available);
    }

    public function test_warehouse_staff_issues_available_parts_using_requested_quantity_automatically(): void
    {
        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $itemA = InventoryItem::create([
            'item_code' => 'AIR-FLTR-01',
            'item_name' => 'Air Filter',
            'category' => 'Filters',
            'quantity_available' => 20,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
        ]);

        $itemB = InventoryItem::create([
            'item_code' => 'FUEL-FLTR-01',
            'item_name' => 'Fuel Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AUTO-001',
            'bus_no' => 'BUS-200',
            'problem_issue' => 'Periodic filter replacement',
            'maintenance_type' => 'Preventive',
            'assigned_mechanic' => 'Lead Mechanic',
            'part_needed' => 'Air Filter (1 pcs), Fuel Filter (3 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => 'PR-AUTO-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'item' => 'Air Filter (1 pcs), Fuel Filter (3 pcs)',
            'quantity' => 4,
            'status' => 'Approved',
        ]);

        // Post issue without any issued_quantities payload
        $response = $this
            ->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Parts issued successfully.');

        $updatedPr = $purchaseRequest->fresh();
        $this->assertSame('Issued', $updatedPr->status);
        $this->assertSame('Issued', $updatedPr->warehouse_status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);

        // Inventory deductions match requested quantities exactly
        $this->assertSame(19, (int) $itemA->fresh()->quantity_available);
        $this->assertSame(7, (int) $itemB->fresh()->quantity_available);

        $issuance = InventoryIssuance::where('reference_no', $purchaseRequest->pr_no)->firstOrFail();
        $this->assertSame($warehouseStaff->id, $issuance->issued_by);
        $this->assertCount(2, InventoryIssuanceItem::where('inventory_issuance_id', $issuance->id)->get());

        // warehouse_issue_quantities history matches requested quantities automatically
        $history = $updatedPr->warehouse_issue_quantities;
        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        $this->assertSame('Air Filter', $history[0]['name']);
        $this->assertSame(1, $history[0]['requested']);
        $this->assertSame(1, $history[0]['issued']);
        $this->assertSame('Fuel Filter', $history[1]['name']);
        $this->assertSame(3, $history[1]['requested']);
        $this->assertSame(3, $history[1]['issued']);

        // Stock movement ledger records generated
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $itemA->id,
            'movement_type' => 'Stock Out',
            'quantity_change' => -1,
            'reference_no' => $purchaseRequest->pr_no,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $itemB->id,
            'movement_type' => 'Stock Out',
            'quantity_change' => -3,
            'reference_no' => $purchaseRequest->pr_no,
        ]);
    }
}

