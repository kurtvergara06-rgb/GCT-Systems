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

        $issueResponse = $this
            ->actingAs($user)
            ->post(route('part-requests.issue', $purchaseRequest));

        $issueResponse->assertRedirect();

        $this->assertSame('Issued', $purchaseRequest->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(3, (int) $inventoryItem->fresh()->quantity_available);
    }
}
