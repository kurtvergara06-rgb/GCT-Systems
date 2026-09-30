<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePurchaseRequestAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $maintenanceStaff;
    private User $maintenanceHead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceStaff = User::factory()->create([
            'role' => 'Maintenance Staff',
            'department' => 'Maintenance',
        ]);

        $this->maintenanceHead = User::factory()->create([
            'role' => 'Maintenance Head',
            'department' => 'Maintenance',
        ]);

        Bus::create([
            'bus_no' => 'BUS-301',
            'plate_no' => 'XYZ-301',
            'status' => 'Active',
        ]);

        Mechanic::create([
            'mechanic_id' => 'MECH-002',
            'mechanic_name' => 'Luigi Verdi',
            'employment_status' => 'Active',
        ]);

        MechanicAttendance::create([
            'mechanic_name' => 'Luigi Verdi',
            'attendance_date' => today(),
            'status' => 'Present',
        ]);
    }

    public function test_ajax_store_purchase_request_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-PR-AJAX-01',
            'bus_no' => 'BUS-301',
            'problem_issue' => 'Oil leak',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Luigi Verdi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $response = $this->actingAs($this->maintenanceStaff)
            ->post(route('purchase-requests.store'), [
                'job_order_no' => $jo->job_order_no,
                'bus_no' => $jo->bus_no,
                'parts' => [
                    ['name' => 'Oil Filter', 'quantity' => 1, 'unit' => 'pcs'],
                ],
                'remarks' => 'Urgent replacement',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchase request created successfully.',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'job_order_no' => 'JO-PR-AJAX-01',
            'status' => 'Submitted',
        ]);
    }

    public function test_ajax_approve_purchase_request_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-PR-AJAX-02',
            'bus_no' => 'BUS-301',
            'problem_issue' => 'Oil leak',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Luigi Verdi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-AJAX-002',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (1 pcs)',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->maintenanceHead)
            ->post(route('purchase-requests.approve', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchase request approved successfully.',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'Approved',
        ]);
    }

    public function test_ajax_reject_purchase_request_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-PR-AJAX-03',
            'bus_no' => 'BUS-301',
            'problem_issue' => 'Oil leak',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Luigi Verdi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-AJAX-003',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (1 pcs)',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->maintenanceHead)
            ->post(route('purchase-requests.reject', $pr->id), [
                'remarks' => 'Not approved for this quarter',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchase request rejected successfully.',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'Rejected',
        ]);
    }

    public function test_ajax_destroy_purchase_request_returns_json(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-PR-AJAX-04',
            'bus_no' => 'BUS-301',
            'problem_issue' => 'Oil leak',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Luigi Verdi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-AJAX-004',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (1 pcs)',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->maintenanceStaff)
            ->delete(route('purchase-requests.destroy', $pr->id), [], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Purchase request deleted successfully.',
        ]);

        $this->assertDatabaseMissing('purchase_requests', [
            'id' => $pr->id,
        ]);
    }

    public function test_non_ajax_approve_falls_back_to_redirect(): void
    {
        $jo = JobOrder::create([
            'job_order_no' => 'JO-PR-AJAX-05',
            'bus_no' => 'BUS-301',
            'problem_issue' => 'Oil leak',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Luigi Verdi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-AJAX-005',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Oil Filter (1 pcs)',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->maintenanceHead)
            ->post(route('purchase-requests.approve', $pr->id));

        $response->assertStatus(302);
    }
}
