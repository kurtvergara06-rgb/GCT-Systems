<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceJobOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $maintenanceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceUser = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
    }

    public function test_rejected_purchase_request_allows_safe_job_order_deletion(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0001', 'Rejected');
        $purchaseRequest = $this->createPurchaseRequest('PR-DELETE-0001', $jobOrder, 'Rejected');

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));
        $response->assertSessionHas(
            'success',
            'Rejected Job Order and its rejected Purchase Request were deleted successfully.'
        );

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseMissing('purchase_requests', ['id' => $purchaseRequest->id]);
    }

    public function test_active_purchase_request_still_blocks_job_order_deletion(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0002', 'Submitted');
        $purchaseRequest = $this->createPurchaseRequest('PR-DELETE-0002', $jobOrder, 'Submitted');

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->from(route('job-orders'))
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders'));
        $response->assertSessionHas(
            'error',
            'This Job Order cannot be deleted because it already has an active linked Purchase Request.'
        );

        $this->assertDatabaseHas('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id]);
    }

    public function test_rejected_purchase_request_with_downstream_purchase_order_is_blocked(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0003', 'Rejected');
        $purchaseRequest = $this->createPurchaseRequest('PR-DELETE-0003', $jobOrder, 'Rejected');

        DB::table('purchase_orders')->insert([
            'po_no' => 'PO-DELETE-0003',
            'po_date' => today(),
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_name' => 'Test Supplier',
            'items' => json_encode([
                [
                    'pr_no' => $purchaseRequest->pr_no,
                    'item_description' => 'Brake Pad',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'cost' => 100,
                ],
            ]),
            'gross_amount' => 100,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 100,
            'status' => 'Ordered',
            'inventory_posted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->from(route('job-orders'))
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders'));
        $response->assertSessionHas(
            'error',
            'This rejected Job Order cannot be deleted safely because it still has downstream or unrelated linked records.'
        );

        $this->assertDatabaseHas('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id]);
        $this->assertDatabaseHas('purchase_orders', ['po_no' => 'PO-DELETE-0003']);
    }

    public function test_job_order_page_enables_delete_only_for_rejected_linked_purchase_request(): void
    {
        $rejectedJobOrder = $this->createJobOrder('JO-DELETE-UI-01', 'Rejected');
        $this->createPurchaseRequest('PR-DELETE-UI-01', $rejectedJobOrder, 'Rejected');

        $submittedJobOrder = $this->createJobOrder('JO-DELETE-UI-02', 'Submitted');
        $this->createPurchaseRequest('PR-DELETE-UI-02', $submittedJobOrder, 'Submitted');

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->get(route('job-orders'));

        $response->assertOk();
        $response->assertSee('Delete Rejected Job Order and Purchase Request');
        $response->assertSee('data-rejected-pr="1"', false);
        $response->assertSee('Cannot delete: this Job Order has an active linked Purchase Request.');
    }

    private function createJobOrder(string $jobOrderNo, string $partStatus): JobOrder
    {
        return JobOrder::create([
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Deletion workflow test',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Brake Pad - Qty: 1 pcs',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => $partStatus,
        ]);
    }

    private function createPurchaseRequest(
        string $prNo,
        JobOrder $jobOrder,
        string $status
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'pr_no' => $prNo,
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'item' => $jobOrder->part_needed,
            'quantity' => 1,
            'status' => $status,
            'source_type' => 'Maintenance Request',
            'remarks' => 'Deletion workflow test',
        ]);
    }
}
