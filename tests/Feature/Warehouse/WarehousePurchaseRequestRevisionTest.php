<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehousePurchaseRequestRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_head_can_return_unforwarded_maintenance_pr_for_revision(): void
    {
        $head = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'onboarding_completed' => true,
            'must_change_password' => false,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-REV-001',
            'bus_no' => 'BUS-WH-REV-001',
            'problem_issue' => 'Oil filter replacement',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Warehouse Revision Mechanic',
            'part_needed' => 'Oil Filter - Qty: 2 pcs',
            'review_status' => 'Approved',
            'status' => 'On Hold',
            'part_status' => 'For Purchase',
        ]);
        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-WH-REV-001',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter - Qty: 2 pcs',
            'quantity' => 2,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Original request.',
        ]);

        $this->actingAs($head)
            ->post(route('part-requests.return-for-revision', $pr), [
                'revision_remarks' => 'Clarify the required unit and quantity.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pr->refresh();
        $this->assertSame('Rejected', $pr->status);
        $this->assertSame('Revision Required', $pr->warehouse_status);
        $this->assertNotNull($pr->rejected_at);
        $this->assertStringContainsString('Clarify the required unit and quantity.', $pr->remarks);
    }
}
