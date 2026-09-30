<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceJobOrderAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $maintenanceUser;
    private Mechanic $mechanic;
    private MechanicAttendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceUser = User::factory()->create([
            'role' => 'Maintenance Staff',
        ]);

        Bus::create([
            'bus_no' => 'BUS-201',
            'plate_no' => 'XYZ-201',
            'status' => 'Active',
        ]);

        $this->mechanic = Mechanic::create([
            'mechanic_id' => 'MECH-001',
            'mechanic_name' => 'Mario Rossi',
            'employment_status' => 'Active',
        ]);

        $this->attendance = MechanicAttendance::create([
            'mechanic_name' => 'Mario Rossi',
            'attendance_date' => today(),
            'status' => 'Present',
        ]);
    }

    public function test_ajax_complete_job_order_returns_json(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AJAX-001',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Brake test',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->postJson(route('job-orders.finish', $jobOrder));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Job order marked as completed.',
        ]);

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertNotNull($jobOrder->completion_date);
        $this->assertSame('Present', $this->attendance->fresh()->status);
    }

    public function test_ajax_complete_locked_job_order_returns_422_json(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AJAX-002',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Engine check',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->postJson(route('job-orders.finish', $jobOrder));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This job order cannot be completed yet. Required parts must be issued first.',
        ]);

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_ajax_delete_job_order_returns_json(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AJAX-003',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Tire change',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->deleteJson(route('job-orders.destroy', $jobOrder));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Job order deleted successfully.',
        ]);

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
    }

    public function test_ajax_update_job_order_returns_json(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AJAX-004',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Initial problem',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->putJson(route('job-orders.update', $jobOrder), [
                'job_order_no' => 'JO-AJAX-004',
                'bus_no' => 'BUS-201',
                'problem_issue' => 'Updated issue description',
                'maintenance_type' => 'Repair',
                'assigned_mechanic' => 'Mario Rossi',
                'status' => 'On Going',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Job order updated successfully.',
        ]);

        $jobOrder->refresh();
        $this->assertSame('Updated issue description', $jobOrder->problem_issue);
    }

    public function test_ajax_create_purchase_request_returns_json(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-AJAX-005',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Suspension noise',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => 'Shock Absorber (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->postJson(route('job-orders.create-pr', $jobOrder));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Purchase request created successfully.',
        ]);

        $jobOrder->refresh();
        $this->assertSame('Submitted', $jobOrder->part_status);
        $this->assertDatabaseHas('purchase_requests', [
            'job_order_no' => 'JO-AJAX-005',
            'status' => 'Submitted',
        ]);
    }

    public function test_non_ajax_request_retains_redirect_behavior(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-TRAD-001',
            'bus_no' => 'BUS-201',
            'problem_issue' => 'Traditional form post',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mario Rossi',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Job order marked as completed.');
    }
}
