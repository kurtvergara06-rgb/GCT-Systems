<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\ActivityLog;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Models\TopbarNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DescriptiveAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_operational_summary_renders_with_kpis_charts_and_recent_records(): void
    {
        $admin = $this->createAdmin();

        $bus = Bus::create([
            'bus_no' => 'GCT-999',
            'plate_no' => 'TEST-999',
            'status' => 'Active',
            'capacity' => 50,
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-TEST-001',
            'route_name' => 'Express Terminal Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-TEST-001',
            'shuttle_route_id' => $route->id,
            'trip_date' => now()->toDateString(),
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'client_name' => 'Test Client',
            'direction' => 'Northbound',
            'required_buses' => 1,
            'route_name_snapshot' => 'Express Terminal Route',
            'status' => 'Completed',
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'slot_number' => 1,
            'bus_id' => $bus->id,
            'driver_id' => 'DRV-101',
            'driver_name' => 'Juan Dela Cruz',
        ]);

        Incident::create([
            'incident_no' => 'INC-TEST-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'status' => 'Resolved',
            'incident_reported_at' => now(),
            'location' => 'Highway km 45',
            'description' => 'Radiator coolant pipe detached',
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.descriptive', [
            'view' => 'operational',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.descriptive.layout')
            ->assertViewHas('activeSummary', 'operational')
            ->assertViewHas('totalScheduledTrips', 1)
            ->assertViewHas('completedTrips', 1)
            ->assertViewHas('tripCompletionRate', 100.0)
            ->assertViewHas('totalAssignedBuses', 1)
            ->assertViewHas('totalIncidents', 1)
            ->assertViewHas('breakdownsCount', 1)
            ->assertViewHas('resolvedIncidents', 1)
            ->assertSeeText('Operational Summary')
            ->assertSeeText('Scheduled Trips')
            ->assertSeeText('Trip Assignments')
            ->assertSeeText('Fleet Readiness')
            ->assertSeeText('T-TEST-001')
            ->assertSeeText('INC-TEST-001');
    }

    public function test_operational_summary_filters_by_specific_bus(): void
    {
        $admin = $this->createAdmin();

        $busA = Bus::create(['bus_no' => 'GCT-101', 'plate_no' => 'PLT-101', 'status' => 'Active', 'capacity' => 50]);
        $busB = Bus::create(['bus_no' => 'GCT-202', 'plate_no' => 'PLT-202', 'status' => 'Under Maintenance', 'capacity' => 50]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-TEST-002',
            'route_name' => 'Route AB',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'status' => 'Active',
        ]);

        $tripA = TripSchedule::create([
            'trip_code' => 'T-BUS-A',
            'shuttle_route_id' => $route->id,
            'trip_date' => now()->toDateString(),
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'required_buses' => 1,
            'route_name_snapshot' => 'Route A',
            'status' => 'Completed',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $tripA->id,
            'slot_number' => 1,
            'bus_id' => $busA->id,
            'driver_id' => 'DRV-102',
            'driver_name' => 'Driver A',
        ]);

        $tripB = TripSchedule::create([
            'trip_code' => 'T-BUS-B',
            'shuttle_route_id' => $route->id,
            'trip_date' => now()->toDateString(),
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '11:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'required_buses' => 1,
            'route_name_snapshot' => 'Route B',
            'status' => 'Scheduled',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $tripB->id,
            'slot_number' => 1,
            'bus_id' => $busB->id,
            'driver_id' => 'DRV-103',
            'driver_name' => 'Driver B',
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.descriptive', [
            'view' => 'operational',
            'bus' => 'GCT-101',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewHas('selectedBus', 'GCT-101')
            ->assertViewHas('totalScheduledTrips', 1)
            ->assertSeeText('T-BUS-A')
            ->assertDontSeeText('T-BUS-B');
    }

    public function test_operational_summary_honest_empty_states_when_no_records(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.descriptive', [
            'view' => 'operational',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewHas('totalScheduledTrips', 0)
            ->assertViewHas('totalIncidents', 0)
            ->assertViewHas('gpsTripCount', 0)
            ->assertSeeText('No Scheduled Trips Recorded')
            ->assertSeeText('Zero Incidents Recorded');
    }

    public function test_administrative_summary_renders_with_users_activities_and_notifications(): void
    {
        $admin = $this->createAdmin();

        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'head',
            'department' => 'Admin',
            'activity' => 'Generated Executive Audit Report',
            'module' => 'Admin',
            'event_type' => 'Report',
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        TopbarNotification::create([
            'module' => 'Admin',
            'entity' => 'BatchUpload',
            'action' => 'processed',
            'message' => 'GPS Telemetry Batch #84 was processed successfully.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.descriptive', [
            'view' => 'administrative',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.descriptive.layout')
            ->assertViewHas('activeSummary', 'administrative')
            ->assertViewHas('totalUsers', 1)
            ->assertViewHas('activeUsers', 1)
            ->assertViewHas('totalActivities', 1)
            ->assertViewHas('totalNotifications', 1)
            ->assertSeeText('Administrative Summary')
            ->assertSeeText('Active Users')
            ->assertSeeText('User Activities')
            ->assertSeeText('System Notifications')
            ->assertSeeText('Generated Executive Audit Report')
            ->assertSeeText('GPS Telemetry Batch #84 was processed successfully.');
    }

    public function test_administrative_summary_honest_empty_states_when_no_logs_in_period(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.descriptive', [
            'view' => 'administrative',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewHas('totalActivities', 0)
            ->assertViewHas('totalNotifications', 0)
            ->assertSeeText('No Activity Records')
            ->assertSeeText('No Notifications');
    }

    public function test_unauthorized_user_cannot_access_descriptive_analytics(): void
    {
        $nonAdmin = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($nonAdmin)->get(route('analytics.descriptive'));
        $response->assertForbidden();
    }
}
