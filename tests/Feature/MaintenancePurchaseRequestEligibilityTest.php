<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePurchaseRequestEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function maintenanceStaff(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
    }

    private function createJobOrder(string $number, ?string $parts): JobOrder
    {
        return JobOrder::create([
            'job_order_no' => $number,
            'bus_no' => 'BUS-' . substr($number, -2),
            'problem_issue' => 'Inspection finding',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => $parts,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => $parts ? 'Not Requested' : 'No Parts Required',
        ]);
    }

    private function createInventoryItem(string $name, int $stock, string $category = 'Mechanical'): InventoryItem
    {
        return InventoryItem::create([
            'item_code' => strtoupper(str_replace(' ', '-', $name)),
            'item_name' => $name,
            'parts_name' => $name,
            'category' => $category,
            'on_hand' => $stock,
            'quantity_available' => $stock,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 0,
            'status' => $stock > 0 ? 'In Stock' : 'Out of Stock',
            'source' => 'app',
        ]);
    }

    public function test_new_pr_dropdown_only_lists_job_orders_with_parts_and_without_existing_pr(): void
    {
        $user = $this->maintenanceStaff();

        $eligible = $this->createJobOrder(
            'JO-ELIGIBLE-01',
            'Oil Filter - Qty: 1 pcs'
        );

        $withoutParts = $this->createJobOrder(
            'JO-NO-PARTS-02',
            null
        );

        $alreadyRequested = $this->createJobOrder(
            'JO-HAS-PR-03',
            'Brake Pad - Qty: 2 pcs'
        );

        PurchaseRequest::create([
            'pr_no' => 'PR-2026-9001',
            'job_order_no' => $alreadyRequested->job_order_no,
            'bus_no' => $alreadyRequested->bus_no,
            'item' => $alreadyRequested->part_needed,
            'quantity' => 2,
            'status' => 'Issued',
            'source_type' => 'Maintenance Request',
            'date_requested' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('purchase-requests'));

        $response->assertOk();
        $response->assertSee('value="' . $eligible->job_order_no . '"', false);
        $response->assertDontSee('value="' . $withoutParts->job_order_no . '"', false);
        $response->assertDontSee('value="' . $alreadyRequested->job_order_no . '"', false);
    }

    public function test_store_rejects_job_order_without_requested_parts(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder('JO-NO-PARTS-04', null);

        $response = $this
            ->actingAs($user)
            ->postJson(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
                'parts' => [
                    ['name' => 'Oil Filter', 'quantity' => 1, 'unit' => 'pcs'],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'This Job Order has no requested parts.');

        $this->assertDatabaseMissing('purchase_requests', [
            'job_order_no' => $jobOrder->job_order_no,
        ]);
    }

    public function test_store_rejects_second_pr_for_same_job_order_even_after_issue(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder(
            'JO-HAS-PR-05',
            'Air Filter - Qty: 1 pcs'
        );

        PurchaseRequest::create([
            'pr_no' => 'PR-2026-9002',
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'item' => $jobOrder->part_needed,
            'quantity' => 1,
            'status' => 'Issued',
            'source_type' => 'Maintenance Request',
            'date_requested' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
                'parts' => [
                    ['name' => 'Air Filter', 'quantity' => 1, 'unit' => 'pcs'],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'This Job Order already has a Purchase Request.');

        $this->assertSame(
            1,
            PurchaseRequest::query()
                ->where('job_order_no', $jobOrder->job_order_no)
                ->count()
        );
    }

    public function test_requisition_data_contains_only_missing_parts_and_does_not_expose_stock(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder(
            'JO-SHORTAGE-06',
            'Oil Filter - Qty: 2 pcs, Brake Pad - Qty: 4 pcs, Fan Belt - Qty: 5 pcs'
        );
        $this->createInventoryItem('Oil Filter', 2);
        $this->createInventoryItem('Brake Pad', 4);
        $this->createInventoryItem('Fan Belt', 3);

        $response = $this
            ->actingAs($user)
            ->getJson(route('purchase-requests.job-order-data', $jobOrder));

        $response->assertOk()
            ->assertJsonPath('job_order_no', $jobOrder->job_order_no)
            ->assertJsonPath('bus_no', $jobOrder->bus_no)
            ->assertJsonCount(1, 'parts')
            ->assertJsonPath('parts.0.name', 'Fan Belt')
            ->assertJsonPath('parts.0.category', 'Mechanical')
            ->assertJsonPath('parts.0.quantity', 2)
            ->assertJsonPath('parts.0.availability', 'Unavailable')
            ->assertJsonMissingPath('parts.0.available')
            ->assertJsonMissingPath('parts.0.shortage')
            ->assertJsonMissingPath('parts.0.inventory_item_id');
    }

    public function test_store_recalculates_missing_quantities_and_preserves_full_job_order_parts(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder(
            'JO-SHORTAGE-07',
            'Oil Filter - Qty: 2 pcs, Fan Belt - Qty: 5 pcs'
        );
        $this->createInventoryItem('Oil Filter', 2);
        $this->createInventoryItem('Fan Belt', 3);

        $response = $this
            ->actingAs($user)
            ->postJson(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
                'purpose' => 'Repair bus alternator',
                'target_date' => now()->addDay()->toDateString(),
                'parts' => [
                    ['name' => 'Oil Filter', 'quantity' => 99, 'unit' => 'pcs'],
                    ['name' => 'Fan Belt', 'quantity' => 1, 'unit' => 'pcs'],
                ],
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $purchaseRequest = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Fan Belt - Qty: 2 pcs', $purchaseRequest->item);
        $this->assertSame(2, $purchaseRequest->quantity);
        $this->assertSame('Repair bus alternator', $purchaseRequest->purpose);
        $this->assertSame($user->id, $purchaseRequest->requested_by);
        $this->assertSame(
            'Oil Filter - Qty: 2 pcs, Fan Belt - Qty: 5 pcs',
            $jobOrder->fresh()->part_needed
        );
    }

    public function test_store_rejects_requisition_when_all_requested_parts_are_available(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder(
            'JO-AVAILABLE-08',
            'Oil Filter - Qty: 2 pcs'
        );
        $this->createInventoryItem('Oil Filter', 2);

        $response = $this
            ->actingAs($user)
            ->postJson(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
                'parts' => [
                    ['name' => 'Oil Filter', 'quantity' => 2, 'unit' => 'pcs'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('job_order_no');

        $this->assertDatabaseMissing('purchase_requests', [
            'job_order_no' => $jobOrder->job_order_no,
        ]);
    }

    public function test_canonical_pr_submission_requests_only_the_shortage(): void
    {
        $user = $this->maintenanceStaff();
        $jobOrder = $this->createJobOrder(
            'JO-SHORTAGE-09',
            'Oil Filter - Qty: 2 pcs, Fan Belt - Qty: 5 pcs'
        );
        $this->createInventoryItem('Oil Filter', 2);
        $this->createInventoryItem('Fan Belt', 3);

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Fan Belt - Qty: 2 pcs', $purchaseRequest->item);
        $this->assertSame(2, $purchaseRequest->quantity);
        $this->assertSame(
            'Oil Filter - Qty: 2 pcs, Fan Belt - Qty: 5 pcs',
            $jobOrder->fresh()->part_needed
        );
    }
}
