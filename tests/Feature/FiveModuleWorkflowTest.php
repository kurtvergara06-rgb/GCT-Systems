<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Incident;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiveModuleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_incident_can_move_from_reported_to_responding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('incidents.store'), [
            'incident_type' => 'Bus Breakdown',
            'location' => 'Test terminal',
            'description' => 'Engine stopped during a trip.',
        ])->assertRedirect();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame('Reported', $incident->status);

        $this->actingAs($user)->put(route('incidents.update', $incident), [
            'status' => 'Responding',
            'location' => 'Test terminal',
            'resolution_notes' => 'Operations dispatched assistance.',
        ])->assertRedirect();

        $this->assertSame('Responding', $incident->fresh()->status);
    }

    public function test_maintenance_warehouse_purchase_and_inventory_round_trip_stays_synchronized(): void
    {
        $user = User::factory()->create();

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-FLOW-0001',
            'bus_no' => 'BUS-FLOW-0001',
            'problem_issue' => 'Brake pads need replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Flow Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $this->actingAs($user)
            ->post(route('job-orders.create-pr', $jobOrder))
            ->assertRedirect();

        $originalPr = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Submitted', $originalPr->status);
        $this->assertSame('Submitted', $jobOrder->fresh()->part_status);

        $this->actingAs($user)
            ->post(route('purchase-requests.approve', $originalPr))
            ->assertRedirect();

        $this->assertSame('Approved', $originalPr->fresh()->status);
        $this->assertSame('Approved', $jobOrder->fresh()->part_status);

        $this->actingAs($user)
            ->post(route('part-requests.send-to-purchase', $originalPr))
            ->assertRedirect();

        $purchasePr = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->where('id', '!=', $originalPr->id)
            ->firstOrFail();

        $this->assertSame('For Purchase', $purchasePr->status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'purchase_request_id' => $purchasePr->id,
            'supplier_name' => 'Flow Supplier',
            'status' => 'Ordered',
            'items' => [[
                'pr_no' => $purchasePr->pr_no,
                'bus_no' => $purchasePr->bus_no,
                'item_description' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'pcs',
                'cost' => 100,
            ]],
        ])->assertRedirect('/purchase-orders');

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('Ordered', $purchaseOrder->status);
        $this->assertSame('Ordered', $purchasePr->fresh()->status);
        $this->assertSame('Ordered', $jobOrder->fresh()->part_status);

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'For Delivery',
            ])
            ->assertRedirect('/purchase-orders');

        $this->actingAs($user)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'Delivered',
                'warehouse_receive' => 1,
            ])
            ->assertRedirect('/warehouse/incoming-deliveries');

        $inventoryItem = InventoryItem::query()
            ->where('item_name', 'Brake Pad')
            ->firstOrFail();

        $this->assertSame(2, (int) $inventoryItem->quantity_available);
        $this->assertSame('Delivered', $purchasePr->fresh()->status);
        $this->assertSame('Delivered', $jobOrder->fresh()->part_status);
        $this->assertNotNull($purchaseOrder->fresh()->inventory_posted_at);

        $this->actingAs($user)
            ->post(route('part-requests.issue', $originalPr))
            ->assertRedirect();

        $this->assertSame('Issued', $originalPr->fresh()->status);
        $this->assertSame('Issued', $purchasePr->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(0, (int) $inventoryItem->fresh()->quantity_available);
    }

    public function test_administration_security_coverage_remains_part_of_the_five_module_suite(): void
    {
        $this->assertFileExists(base_path('tests/Feature/Admin/AdminPasswordResetSecurityTest.php'));
    }
}
