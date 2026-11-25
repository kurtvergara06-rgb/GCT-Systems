<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\BusPmsMonitoring;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceDispatchAndPartsWaitTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_pms_blocks_a_previously_assigned_trip_before_dispatch(): void
    {
        $this->travelTo(now(config('app.business_timezone'))->setTime(10, 0));
        $user = User::factory()->create(['department' => 'Operation', 'role' => 'head', 'status' => 'Active']);
        $bus = Bus::create(['bus_no' => 'QA-DUE', 'status' => 'Active']);
        $route = ShuttleRoute::create([
            'route_code' => 'QA-DUE', 'route_name' => 'QA Route', 'origin' => 'A', 'destination' => 'B',
            'distance_km' => 10, 'estimated_time_minutes' => 60, 'status' => 'Active',
        ]);
        $trip = TripSchedule::create([
            'trip_code' => 'QA-DUE-TRIP', 'trip_date' => today(config('app.business_timezone')),
            'shuttle_route_id' => $route->id, 'departure_time' => '09:30:00', 'estimated_arrival_time' => '10:30:00',
            'shift' => 'Morning', 'assignment_status' => 'Assigned', 'status' => 'Ready', 'created_by' => $user->id,
        ]);
        $trip->assignments()->create(['bus_id' => $bus->id, 'driver_name' => 'QA Driver', 'driver_id' => 'QA-01']);
        $this->assertTrue($trip->canBeDispatched());
        $monitor = BusPmsMonitoring::create([
            'bus_id' => $bus->id, 'last_completed_pms_date' => now()->subMonth()->toDateString(),
            'last_completed_pms_odometer_km' => 40000, 'verified_current_odometer_km' => 50000,
            'current_odometer_verified_at' => now(), 'is_active' => true, 'configured_by' => $user->id,
            'interval_km' => 10000, 'interval_months' => 3,
        ]);
        $this->assertFalse($trip->canBeDispatched());
        $this->actingAs($user)->get(route('bus-master-list', ['search' => 'QA-DUE', 'status' => 'Under Maintenance']))
            ->assertOk()->assertSee('QA-DUE')->assertSee('Under Maintenance');
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Ready', $trip->fresh()->status);
        $monitor->update([
            'last_completed_pms_date' => now()->toDateString(),
            'last_completed_pms_odometer_km' => 50000,
        ]);
        $this->assertTrue($trip->fresh()->canBeDispatched());
        $monitor->update(['last_completed_pms_date' => now()->subMonthsNoOverflow(3)->toDateString()]);
        $this->assertFalse($trip->fresh()->canBeDispatched());
    }

    public function test_etc_excludes_time_waiting_for_parts_even_when_a_wait_is_saved_again(): void
    {
        $start = now()->startOfMinute();
        $this->travelTo($start);
        $job = JobOrder::create([
            'job_order_no' => 'QA-ETC', 'bus_no' => 'QA-ETC', 'problem_issue' => 'QA repair',
            'maintenance_type' => 'Repair', 'review_status' => 'Approved', 'status' => 'On Going',
            'actual_started_at' => $start, 'estimated_duration_value' => 2, 'estimated_duration_unit' => 'Hours',
        ]);
        $this->assertTrue($job->estimated_due_at->equalTo($start->copy()->addHours(2)));

        $this->travel(30)->minutes();
        $job->update(['part_needed' => 'QA filter - Qty: 1 pcs', 'part_status' => 'For Purchase']);
        $pausedAt = $job->parts_wait_started_at->copy();
        $this->assertSame('Waiting for Parts', $job->display_status);
        $this->assertTrue(JobOrder::query()->whereKey($job->id)->whereDisplayStatus('Waiting for Parts')->exists());
        $this->assertNull($job->estimated_due_at);

        $this->travel(3)->hours();
        $job->update(['part_status' => 'Ordered']);
        $this->assertTrue($job->parts_wait_started_at->equalTo($pausedAt));
        $this->assertFalse($job->is_overdue);
        $job->update(['part_status' => 'Issued']);
        $this->assertSame(10800, $job->parts_wait_seconds);
        $this->assertNull($job->parts_wait_started_at);
        $this->assertTrue($job->estimated_due_at->equalTo($start->copy()->addHours(5)));
        $this->assertSame('In Progress', $job->display_status);

        $job->update(['part_status' => 'Issued']);
        $this->assertSame(10800, $job->parts_wait_seconds);
    }
}
