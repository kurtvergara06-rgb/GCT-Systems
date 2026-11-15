<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceJobOrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $maintenanceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceUser = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
        ]);
    }

    public function test_ongoing_job_order_with_no_parts_can_be_completed(): void
    {
        Mechanic::create([
            'mechanic_id' => 'MECH-001',
            'mechanic_name' => 'Juan Dela Cruz',
            'employment_status' => 'Active',
        ]);

        $mechanic = MechanicAttendance::create([
            'mechanic_name' => 'Juan Dela Cruz',
            'attendance_date' => today(),
            'status' => 'On Duty',
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1001',
            'bus_no' => 'BUS-101',
            'problem_issue' => 'Inspection only',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Needed',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));
        $response->assertSessionHas('success', 'Job order marked as completed.');

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertNotNull($jobOrder->completion_date);

        // Mechanic released back to Present
        $this->assertSame('Present', $mechanic->fresh()->status);
    }

    public function test_job_order_with_parts_not_requested_cannot_be_completed(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1002',
            'bus_no' => 'BUS-102',
            'problem_issue' => 'Brake service',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Brake Pad (2 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This job order cannot be completed yet. Required parts must be issued first.');

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_job_order_with_parts_submitted_cannot_be_completed(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1003',
            'bus_no' => 'BUS-103',
            'problem_issue' => 'Oil replacement',
            'maintenance_type' => 'Preventive',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Oil Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This job order cannot be completed yet. Required parts must be issued first.');

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_job_order_with_parts_delivered_cannot_be_completed(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1004',
            'bus_no' => 'BUS-104',
            'problem_issue' => 'Fuel line fix',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Fuel Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Delivered',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This job order cannot be completed yet. Required parts must be issued first.');

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_job_order_with_parts_rejected_cannot_be_completed(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1005',
            'bus_no' => 'BUS-105',
            'problem_issue' => 'Fan belt worn',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Fan Belt (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Rejected',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This job order cannot be completed yet. Required parts must be issued first.');

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_job_order_with_parts_issued_can_be_completed(): void
    {
        Mechanic::create([
            'mechanic_id' => 'MECH-002',
            'mechanic_name' => 'Pedro Penduko',
            'employment_status' => 'Active',
        ]);

        $mechanic = MechanicAttendance::create([
            'mechanic_name' => 'Pedro Penduko',
            'attendance_date' => today(),
            'status' => 'On Duty',
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1006',
            'bus_no' => 'BUS-106',
            'problem_issue' => 'Air filter swap',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Pedro Penduko',
            'part_needed' => 'Air Filter (1 pcs)',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Issued',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect(route('job-orders', [], false));
        $response->assertSessionHas('success', 'Job order marked as completed.');

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertNotNull($jobOrder->completion_date);

        // Mechanic released back to Present
        $this->assertSame('Present', $mechanic->fresh()->status);
    }

    public function test_on_hold_job_order_cannot_be_completed(): void
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1007',
            'bus_no' => 'BUS-107',
            'problem_issue' => 'Waiting for mechanic',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => null,
            'part_needed' => null,
            'start_date' => null,
            'status' => 'On Hold',
            'part_status' => 'No Parts Needed',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This job order cannot be completed because it is currently on hold.');

        $jobOrder->refresh();
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertNull($jobOrder->completion_date);
    }

    public function test_completed_job_order_cannot_be_completed_again(): void
    {
        $initialCompletionDate = now()->subHours(2);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-1008',
            'bus_no' => 'BUS-108',
            'problem_issue' => 'Already done',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => null,
            'start_date' => now()->subDay(),
            'completion_date' => $initialCompletionDate,
            'status' => 'Completed',
            'part_status' => 'No Parts Needed',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->post(route('job-orders.finish', $jobOrder));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Job order is already completed.');

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertSame(
            $initialCompletionDate->format('Y-m-d H:i:s'),
            $jobOrder->completion_date->format('Y-m-d H:i:s')
        );
    }

    public function test_ui_renders_complete_buttons_and_modal_correctly(): void
    {
        // 1. JO Ongoing, no parts -> enabled Complete button
        $joNoParts = JobOrder::create([
            'job_order_no' => 'JO-UI-001',
            'bus_no' => 'BUS-101',
            'problem_issue' => 'Tire check',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => null,
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'No Parts Needed',
        ]);

        // 2. JO Ongoing, parts not requested -> locked Complete button
        $joPartsPending = JobOrder::create([
            'job_order_no' => 'JO-UI-002',
            'bus_no' => 'BUS-102',
            'problem_issue' => 'Wiper broken',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Wiper Blade',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Not Requested',
        ]);

        // 3. JO Ongoing, parts issued -> enabled Complete button
        $joPartsIssued = JobOrder::create([
            'job_order_no' => 'JO-UI-003',
            'bus_no' => 'BUS-103',
            'problem_issue' => 'Clutch pad',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Clutch Pad',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Issued',
        ]);

        // 4. JO On Hold -> locked Complete button
        $joOnHold = JobOrder::create([
            'job_order_no' => 'JO-UI-004',
            'bus_no' => 'BUS-104',
            'problem_issue' => 'Radiator repair',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => null,
            'part_needed' => null,
            'start_date' => null,
            'status' => 'On Hold',
            'part_status' => 'No Parts Needed',
        ]);

        // 5. JO Completed -> shows completion date & time, no button
        $joCompleted = JobOrder::create([
            'job_order_no' => 'JO-UI-005',
            'bus_no' => 'BUS-105',
            'problem_issue' => 'Battery replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Juan Dela Cruz',
            'part_needed' => 'Battery',
            'start_date' => now()->subDay(),
            'completion_date' => now()->subHours(5),
            'status' => 'Completed',
            'part_status' => 'Issued',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->get(route('job-orders'));

        $response->assertOk();

        // Modal wording assertions
        $response->assertSee('Complete Job Order?');
        $response->assertSee('Confirm that all maintenance work for this Job Order has been completed.');
        $response->assertSee('Completion date and time will be recorded automatically.');
        $response->assertSee('Yes, Complete');
        $response->assertDontSee('Yes, Finish');
        $response->assertDontSee('Finish Job Order?');

        // Form actions for enabled complete buttons
        $response->assertSee('action="' . route('job-orders.finish', $joNoParts->id) . '"', false);
        $response->assertSee('action="' . route('job-orders.finish', $joPartsIssued->id) . '"', false);

        // Locked buttons with appropriate reasons
        $response->assertSee('Cannot complete until required parts are issued by warehouse.');
        $response->assertSee('Cannot complete while job order is on hold.');

        // Completed row displays completion date format
        $response->assertSee(date('M d, Y', strtotime($joCompleted->completion_date)));
        $response->assertDontSee('action="' . route('job-orders.finish', $joCompleted->id) . '"', false);
    }
}
