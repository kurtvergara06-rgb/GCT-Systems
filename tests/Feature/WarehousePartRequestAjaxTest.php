<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehousePartRequestAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $warehouseHead;
    private User $warehouseStaff;
    private InventoryItem $inventoryItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
        ]);

        $this->warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
        ]);

        $this->inventoryItem = InventoryItem::create([
            'item_code' => 'OIL-FILTER-01',
            'item_name' => 'Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
        ]);
    }

    public function test_ajax_approve_for_issue_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-01',
            'bus_no' => 'BUS-01',
            'problem_issue' => 'Filter replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'John',
            'part_needed' => 'Oil Filter (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-001',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (2 pcs)',
            'quantity' => 2,
            'status' => 'Approved',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->warehouseHead)
            ->post(route('part-requests.approve-for-issue', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Part issuance approved. Warehouse Staff may now prepare the request.',
        ]);

        $this->assertSame('Approved for Issue', $pr->fresh()->warehouse_status);
    }

    public function test_ajax_prepare_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-02',
            'bus_no' => 'BUS-02',
            'problem_issue' => 'Filter replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'John',
            'part_needed' => 'Oil Filter (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-002',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (2 pcs)',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Approved for Issue',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->warehouseStaff)
            ->post(route('part-requests.prepare', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Parts marked as preparing. Record actual quantities when issuing.',
        ]);

        $this->assertSame('Preparing', $pr->fresh()->warehouse_status);
    }

    public function test_ajax_issue_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-03',
            'bus_no' => 'BUS-03',
            'problem_issue' => 'Filter replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'John',
            'part_needed' => 'Oil Filter (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-003',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (2 pcs)',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Preparing',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->warehouseStaff)
            ->post(route('part-requests.issue', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Parts issued successfully.',
        ]);

        $this->assertSame('Issued', $pr->fresh()->status);
        $this->assertSame('Issued', $pr->fresh()->warehouse_status);
        $this->assertSame('Issued', $jo->fresh()->part_status);
        $this->assertSame(8, (int) $this->inventoryItem->fresh()->quantity_available);
    }

    public function test_ajax_hold_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-04',
            'bus_no' => 'BUS-04',
            'problem_issue' => 'Filter replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'John',
            'part_needed' => 'Oil Filter (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-004',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (2 pcs)',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Approved for Issue',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->warehouseHead)
            ->post(route('part-requests.hold', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Part issuance placed on hold.',
        ]);

        $this->assertSame('On Hold', $pr->fresh()->warehouse_status);
    }

    public function test_non_ajax_prepare_falls_back_to_redirect(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-05',
            'bus_no' => 'BUS-05',
            'problem_issue' => 'Filter replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'John',
            'part_needed' => 'Oil Filter (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-005',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (2 pcs)',
            'quantity' => 2,
            'status' => 'Approved',
            'warehouse_status' => 'Approved for Issue',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->warehouseStaff)
            ->post(route('part-requests.prepare', $pr->id));

        $response->assertStatus(302);
    }
}
