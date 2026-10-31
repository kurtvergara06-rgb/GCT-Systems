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

    public function test_submitted_purchase_request_is_visible_but_cannot_issue_before_procurement_ready(): void
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
            'review_status' => 'Approved',
            'approved_at' => now(),
            'part_status' => 'Not Requested',
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
            ]);

        $createResponse->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Submitted', $purchaseRequest->status);
        $this->assertNull($purchaseRequest->approved_at);
        $this->assertSame('Maintenance Request', $purchaseRequest->source_type);
        $this->assertSame(2, (int) $purchaseRequest->quantity);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);

        // Warehouse may see the request for stock reference, but physical issue
        // stays locked until the procurement/receipt workflow makes it ready.
        $this->actingAs($warehouseStaff)->get(route('part-requests'))->assertOk()->assertSee($purchaseRequest->pr_no);

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

        $issueResponse->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Submitted', $purchaseRequest->fresh()->status);
        $this->assertNotSame('Issued', $purchaseRequest->fresh()->warehouse_status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);
        $this->assertSame(5, (int) $inventoryItem->fresh()->quantity_available);

        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest))
            ->assertSessionHas('error');

        $this->assertSame(5, (int) $inventoryItem->fresh()->quantity_available);
        $this->assertSame(0, InventoryIssuance::where('reference_no', $purchaseRequest->pr_no)->count());
    }

    public function test_warehouse_edit_capability_controls_retired_direct_issue_route(): void
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
            ->assertSessionHas('error');

        $this->assertSame(4, (int) $item->fresh()->quantity_available);
        $this->assertSame('Approved', $request->fresh()->status);
    }

    public function test_warehouse_staff_cannot_bypass_ir_and_signed_handover_with_direct_pr_issue(): void
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
        $response->assertSessionHas('error');

        $updatedPr = $purchaseRequest->fresh();
        $this->assertSame('Approved', $updatedPr->status);
        $this->assertNotSame('Issued', $updatedPr->warehouse_status);
        $this->assertSame('Not Requested', $jobOrder->fresh()->part_status);

        // Inventory deductions match requested quantities exactly
        $this->assertSame(20, (int) $itemA->fresh()->quantity_available);
        $this->assertSame(10, (int) $itemB->fresh()->quantity_available);
        $this->assertSame(0, InventoryIssuance::where('reference_no', $purchaseRequest->pr_no)->count());
        $this->assertDatabaseMissing('stock_movements', ['reference_no' => $purchaseRequest->pr_no]);
    }
    public function test_similar_inventory_names_are_not_fuzzily_issued(): void
    {
        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $similarItem = InventoryItem::create([
            'item_code' => 'ENG-OIL-FLTR',
            'item_name' => 'Engine Oil Filter',
            'parts_name' => 'Engine Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => 'PR-STRICT-MATCH-001',
            'job_order_no' => 'JO-STRICT-MATCH-001',
            'bus_no' => 'BUS-STRICT-001',
            'item' => 'Oil Filter - Qty: 1 pcs',
            'quantity' => 1,
            'status' => 'Approved',
            'source_type' => 'Maintenance Request',
        ]);

        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $purchaseRequest))
            ->assertSessionHas('error');

        $this->assertSame(10, (int) $similarItem->fresh()->quantity_available);
        $this->assertSame('Approved', $purchaseRequest->fresh()->status);
        $this->assertSame(
            0,
            InventoryIssuance::where('reference_no', $purchaseRequest->pr_no)->count()
        );
    }

    public function test_forwarding_shortage_reuses_pr_and_never_directly_deducts_stock(): void
    {
        $warehouseHead = User::factory()->create(['department' => 'Warehouse', 'role' => 'head', 'status' => 'Active']);
        $warehouseStaff = User::factory()->create(['department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active']);
        $item = InventoryItem::create([
            'item_code' => 'PARTIAL-BRAKE', 'item_name' => 'Brake Pad', 'parts_name' => 'Brake Pad',
            'category' => 'Brakes', 'quantity_available' => 2, 'unit_of_measurement' => 'pcs', 'reorder_level' => 1,
        ]);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-PARTIAL-001', 'bus_no' => 'BUS-PARTIAL-001',
            'problem_issue' => 'Brake pad replacement', 'maintenance_type' => 'Repair',
            'part_needed' => 'Brake Pad - Qty: 5 pcs', 'status' => 'On Going',
            'review_status' => 'Approved', 'part_status' => 'Approved',
            'parts_issue_strategy' => 'issue_available_only',
        ]);
        $request = PurchaseRequest::create([
            'pr_no' => 'PR-PARTIAL-001', 'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no, 'item' => 'Brake Pad - Qty: 5 pcs',
            'quantity' => 5, 'status' => 'Approved', 'source_type' => 'Maintenance Request',
        ]);

        $this->actingAs($warehouseHead)->post(route('part-requests.send-to-purchase', $request))->assertRedirect();
        $this->assertSame('For Purchase', $request->fresh()->status);
        $this->assertSame(1, PurchaseRequest::where('job_order_no', $jobOrder->job_order_no)->count());

        $this->actingAs($warehouseStaff)->post(route('part-requests.issue', $request))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(2, (int) $item->fresh()->quantity_available);
        $this->assertSame('For Purchase', $request->fresh()->status);
        $this->assertNotSame('Partially Issued', $request->fresh()->warehouse_status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);
        $this->assertDatabaseMissing('inventory_issuance_items', ['item_name' => 'Brake Pad']);
    }


}

