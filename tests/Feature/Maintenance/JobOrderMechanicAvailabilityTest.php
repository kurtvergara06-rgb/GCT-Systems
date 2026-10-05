<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderMechanicAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function maintenanceUser(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    private function mechanic(string $id, string $name, string $status = 'Active'): Mechanic
    {
        return Mechanic::create([
            'mechanic_id' => $id,
            'mechanic_name' => $name,
            'shift' => 'Morning',
            'employment_status' => $status,
        ]);
    }

    private function attendance(Mechanic $mechanic, string $status): MechanicAttendance
    {
        return MechanicAttendance::create([
            'mechanic_id' => $mechanic->mechanic_id,
            'mechanic_name' => $mechanic->mechanic_name,
            'shift' => $mechanic->shift,
            'attendance_date' => today(),
            'status' => $status,
        ]);
    }

    public function test_on_duty_attendance_is_available_when_no_active_job_exists(): void
    {
        $user = $this->maintenanceUser();
        $mechanic = $this->mechanic('MEC-AVAIL-01', 'Available On Duty');
        $this->attendance($mechanic, 'On Duty');

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('job-orders.available-mechanics'));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'mechanic_name' => 'Available On Duty',
            ]);
    }

    public function test_active_job_still_excludes_attended_mechanic(): void
    {
        $user = $this->maintenanceUser();
        $mechanic = $this->mechanic('MEC-BUSY-01', 'Busy Mechanic');
        $this->attendance($mechanic, 'Present');

        JobOrder::create([
            'job_order_no' => 'JO-BUSY-0001',
            'bus_no' => 'BUS-BUSY-01',
            'problem_issue' => 'Existing active repair',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => $mechanic->mechanic_name,
            'status' => 'On Going',
            'start_date' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('job-orders.available-mechanics'));

        $response
            ->assertOk()
            ->assertJsonMissing([
                'mechanic_name' => 'Busy Mechanic',
            ]);
    }

    public function test_cancelled_job_does_not_keep_mechanic_hidden(): void
    {
        $user = $this->maintenanceUser();
        $mechanic = $this->mechanic('MEC-CANCEL-01', 'Released Mechanic');
        $this->attendance($mechanic, 'On Duty');

        JobOrder::create([
            'job_order_no' => 'JO-CANCEL-0001',
            'bus_no' => 'BUS-CANCEL-01',
            'problem_issue' => 'Cancelled repair',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => $mechanic->mechanic_name,
            'status' => 'Cancelled',
            'start_date' => now()->subHour(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('job-orders.available-mechanics'));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'mechanic_name' => 'Released Mechanic',
            ]);
    }

    public function test_inactive_master_record_is_not_assignable_even_with_attendance(): void
    {
        $user = $this->maintenanceUser();
        $mechanic = $this->mechanic('MEC-INACTIVE-01', 'Inactive Mechanic', 'Inactive');
        $this->attendance($mechanic, 'Present');

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('job-orders.available-mechanics'));

        $response
            ->assertOk()
            ->assertJsonMissing([
                'mechanic_name' => 'Inactive Mechanic',
            ]);
    }

    public function test_new_job_order_accepts_free_on_duty_attendance_and_marks_job_ongoing(): void
    {
        $user = $this->maintenanceUser();
        $mechanic = $this->mechanic('MEC-CREATE-01', 'Create Ready Mechanic');
        $this->attendance($mechanic, 'On Duty');

        Bus::create([
            'bus_no' => 'BUS-CREATE-01',
            'plate_no' => 'ABC-1001',
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('job-orders.store'), [
                'bus_no' => 'BUS-CREATE-01',
                'problem_issue' => 'Brake inspection',
                'maintenance_type' => 'Repair',
                'assigned_mechanic' => $mechanic->mechanic_name,
                'estimated_duration_value' => 2,
                'estimated_duration_unit' => 'Hours',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('job_orders', [
            'bus_no' => 'BUS-CREATE-01',
            'assigned_mechanic' => 'Create Ready Mechanic',
            'status' => 'On Going',
        ]);
    }
}
