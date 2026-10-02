<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceJobOrderRecordViewTest extends TestCase
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

    public function test_active_job_order_view_shows_new_action_and_only_non_completed_records(): void
    {
        JobOrder::create([
            'job_order_no' => 'JO-ACTIVE-001',
            'bus_no' => 'BUS-ACTIVE',
            'problem_issue' => 'Active repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'part_status' => 'No Parts Needed',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-HISTORY-001',
            'bus_no' => 'BUS-HISTORY',
            'problem_issue' => 'Completed repair',
            'maintenance_type' => 'Repair',
            'status' => 'Completed',
            'completion_date' => now(),
            'part_status' => 'Issued',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->get(route('job-orders'));

        $response->assertOk();
        $response->assertSee('JO-ACTIVE-001');
        $response->assertDontSee('JO-HISTORY-001');
        $response->assertSee('New JO');
        $response->assertSee('Track job order details, assigned mechanics, completion status, and parts progress');
    }

    public function test_history_job_order_view_hides_new_action_and_active_description(): void
    {
        JobOrder::create([
            'job_order_no' => 'JO-ACTIVE-002',
            'bus_no' => 'BUS-ACTIVE-2',
            'problem_issue' => 'Active repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'part_status' => 'No Parts Needed',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-HISTORY-002',
            'bus_no' => 'BUS-HISTORY-2',
            'problem_issue' => 'Completed repair',
            'maintenance_type' => 'Repair',
            'status' => 'Completed',
            'completion_date' => now(),
            'part_status' => 'Issued',
        ]);

        $response = $this
            ->actingAs($this->maintenanceUser)
            ->get(route('job-orders', ['record_view' => 'history']));

        $response->assertOk();
        $response->assertSee('JO-HISTORY-002');
        $response->assertDontSee('JO-ACTIVE-002');
        $response->assertDontSee('New JO');
        $response->assertDontSee('Track job order details, assigned mechanics, completion status, and parts progress');
        $response->assertSee('Completed Job Orders are kept here for reference and audit history.');
        $response->assertSee('name="record_view"', false);
        $response->assertSee('value="history"', false);
    }
}
