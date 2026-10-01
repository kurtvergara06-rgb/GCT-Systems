<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
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
}
