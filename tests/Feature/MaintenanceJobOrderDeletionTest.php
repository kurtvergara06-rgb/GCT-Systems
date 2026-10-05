<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
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
        $purchaseRequest = $this->createPurchaseRequest(
            'PR-DELETE-0001',
            $jobOrder->job_order_no,
            'Rejected'
        );

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));
        $response->assertSessionHas(
            'success',
            'Rejected Job Order and its linked Purchase Request data were deleted successfully.'
        );

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseMissing('purchase_requests', ['id' => $purchaseRequest->id]);
    }

    public function test_active_purchase_request_still_blocks_job_order_deletion(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0002', 'Submitted');
        $purchaseRequest = $this->createPurchaseRequest(
            'PR-DELETE-0002',
            $jobOrder->job_order_no,
            'Submitted'
        );

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

    public function test_rejected_job_order_deletes_exclusive_purchase_order_without_inventory_effects(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0003', 'Rejected');
        $purchaseRequest = $this->createPurchaseRequest(
            'PR-DELETE-0003',
            $jobOrder->job_order_no,
            'Rejected'
        );

        $purchaseOrderId = $this->insertPurchaseOrder(
            'PO-DELETE-0003',
            $purchaseRequest->id,
            [
                $this->poItem($purchaseRequest->pr_no, 'Brake Pad', 1),
            ]
        );

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));
        $response->assertSessionHas(
            'success',
            'Rejected Job Order and its linked Purchase Request data were deleted successfully.'
        );

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseMissing('purchase_requests', ['id' => $purchaseRequest->id]);
        $this->assertDatabaseMissing('purchase_orders', ['id' => $purchaseOrderId]);
    }

    public function test_rejected_job_order_is_detached_from_shared_purchase_order(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0004', 'Rejected');

        $targetRequest = $this->createPurchaseRequest(
            'PR-DELETE-0004',
            $jobOrder->job_order_no,
            'Rejected'
        );

        $otherRequest = $this->createPurchaseRequest(
            'PR-KEEP-0004',
            'JO-KEEP-0004',
            'For Purchase'
        );

        $purchaseOrderId = $this->insertPurchaseOrder(
            'PO-SHARED-0004',
            $otherRequest->id,
            [
                $this->poItem($targetRequest->pr_no, 'Brake Pad', 1),
                $this->poItem($otherRequest->pr_no, 'Oil Filter', 2),
            ],
            [
                'status' => 'Picked Up',
                'inventory_posted_at' => now(),
            ]
        );

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->delete(route('job-orders.destroy', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
        $this->assertDatabaseMissing('purchase_requests', ['id' => $targetRequest->id]);
        $this->assertDatabaseHas('purchase_requests', ['id' => $otherRequest->id]);

        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        $this->assertSame($otherRequest->id, (int) $purchaseOrder->purchase_request_id);
        $this->assertNotNull($purchaseOrder->inventory_posted_at);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertSame('PR-KEEP-0004', $purchaseOrder->items[0]['pr_no']);
        $this->assertSame('Oil Filter', $purchaseOrder->items[0]['item_description']);
    }

    public function test_shared_purchase_order_primary_link_moves_to_surviving_request(): void
    {
        $jobOrder = $this->createJobOrder('JO-DELETE-0005', 'Rejected');

        $targetRequest = $this->createPurchaseRequest(
            'PR-DELETE-0005',
            $jobOrder->job_order_no,
            'Rejected'
        );

        $otherRequest = $this->createPurchaseRequest(
            'PR-KEEP-0005',
            'JO-KEEP-0005',
            'For Purchase'
        );

        $purchaseOrderId = $this->insertPurchaseOrder(
            'PO-SHARED-0005',
            $targetRequest->id,
            [
                $this->poItem($targetRequest->pr_no, 'Brake Pad', 1),
                $this->poItem($otherRequest->pr_no, 'Air Filter', 1),
            ]
        );

        $this
            ->actingAs($this->maintenanceUser)
            ->delete(route('job-orders.destroy', $jobOrder))
            ->assertRedirect(route('job-orders', [], false));

        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        $this->assertSame($otherRequest->id, (int) $purchaseOrder->purchase_request_id);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertSame('PR-KEEP-0005', $purchaseOrder->items[0]['pr_no']);
    }

    public function test_job_order_page_enables_delete_only_for_rejected_linked_purchase_request(): void
    {
        $rejectedJobOrder = $this->createJobOrder('JO-DELETE-UI-01', 'Rejected');
        $this->createPurchaseRequest(
            'PR-DELETE-UI-01',
            $rejectedJobOrder->job_order_no,
            'Rejected'
        );

        $submittedJobOrder = $this->createJobOrder('JO-DELETE-UI-02', 'Submitted');
        $this->createPurchaseRequest(
            'PR-DELETE-UI-02',
            $submittedJobOrder->job_order_no,
            'Submitted'
        );

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
        string $jobOrderNo,
        string $status
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'pr_no' => $prNo,
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'item' => 'Brake Pad - Qty: 1 pcs',
            'quantity' => 1,
            'status' => $status,
            'source_type' => 'Maintenance Request',
            'remarks' => 'Deletion workflow test',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     */
    private function insertPurchaseOrder(
        string $poNo,
        int $purchaseRequestId,
        array $items,
        array $overrides = []
    ): int {
        return DB::table('purchase_orders')->insertGetId(array_merge([
            'po_no' => $poNo,
            'po_date' => today(),
            'purchase_request_id' => $purchaseRequestId,
            'supplier_name' => 'Test Supplier',
            'items' => json_encode($items),
            'gross_amount' => collect($items)->sum('amount'),
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => collect($items)->sum('amount'),
            'status' => 'Ordered',
            'inventory_posted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function poItem(
        string $prNo,
        string $name,
        int $quantity
    ): array {
        return [
            'pr_no' => $prNo,
            'item_description' => $name,
            'quantity' => $quantity,
            'unit' => 'pcs',
            'cost' => 100,
            'amount' => $quantity * 100,
        ];
    }
}
