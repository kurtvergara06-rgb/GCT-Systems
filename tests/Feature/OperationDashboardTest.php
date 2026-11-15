<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard-operation'));
        $response->assertRedirect('/');
    }

    public function test_operation_dashboard_renders_successfully_with_summary_data(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        // Create buses
        Bus::create(['bus_no' => 'GCT-101', 'plate_no' => 'ABC-101', 'capacity' => 40, 'status' => 'Active']);
        Bus::create(['bus_no' => 'GCT-102', 'plate_no' => 'ABC-102', 'capacity' => 40, 'status' => 'Under Maintenance']);
        Bus::create(['bus_no' => 'GCT-103', 'plate_no' => 'ABC-103', 'capacity' => 40, 'status' => 'Inactive']);

        // Create route and trip
        $route = ShuttleRoute::create([
            'route_name' => 'Downtown - Uptown',
            'route_code' => 'DT-UT',
            'origin' => 'Downtown',
            'destination' => 'Uptown',
            'distance_km' => 25,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-1001',
            'shuttle_route_id' => $route->id,
            'trip_date' => $today,
            'direction' => 'Incoming',
            'departure_time' => '07:00:00',
            'estimated_arrival_time' => '08:00:00',
            'estimated_arrival_date' => $today,
            'shift' => 'Morning',
            'required_buses' => 1,
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        // Create driver master and attendance
        $driverMaster = Driver::create([
            'driver_id' => 'D-2026-0001',
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        DriverAttendance::create([
            'driver_id' => $driverMaster->driver_id,
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard-operation'));

        $response->assertOk();
        $response->assertSee('Operations Command Center');
        $response->assertSee('Trip & Assignment Fulfillment', false);
        $response->assertSee('Trip Performance — Last 7 Days');
        $response->assertSee('data-trip-performance', false);
        $response->assertSee('data-fleet-readiness', false);
        $response->assertSee('Operational date');
        $response->assertSee('Completed Trips');
        $response->assertSee('Bus Availability');
        $response->assertSee('Driver Attendance');
        $response->assertDontSee('Mechanic Attendance');
        $response->assertSee('Recent Operational Incidents');
        $response->assertSee('Recent Driver Reports');
        $response->assertSee('Hybrid AI Scheduling');
        $response->assertSee('T-1001');
        $response->assertSee('Downtown - Uptown');
        $response->assertDontSee('External Bus Rental');
        $response->assertDontSee('Live Telemetry Online');
        $response->assertDontSee('AI Active');
    }

    public function test_dashboard_separates_planning_eligibility_from_confirmed_attendance(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        foreach ([
            ['D-AVAILABLE', 'Available Driver'],
            ['D-PRESENT', 'Present Driver'],
            ['D-ABSENT', 'Absent Driver'],
        ] as [$driverId, $name]) {
            Driver::create([
                'driver_id' => $driverId,
                'driver_name' => $name,
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]);
        }

        DriverAttendance::create([
            'driver_id' => 'D-PRESENT',
            'driver_name' => 'Present Driver',
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);
        DriverAttendance::create([
            'driver_id' => 'D-ABSENT',
            'driver_name' => 'Absent Driver',
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Absent',
        ]);

        foreach ([
            ['INC-ACTIVE', 'Reported'],
            ['INC-RESOLVED', 'Resolved'],
            ['INC-CANCELLED', 'Cancelled'],
        ] as [$number, $status]) {
            Incident::create([
                'incident_no' => $number,
                'incident_type' => 'Delay',
                'incident_reported_at' => now(),
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard-operation'));

        $response->assertOk()
            ->assertViewHas('activeDrivers', 3)
            ->assertViewHas('driversAvailable', 2)
            ->assertViewHas('presentDrivers', 1)
            ->assertViewHas('attendanceRecords', 2)
            ->assertViewHas('activeIncidents', 1)
            ->assertSee('Active drivers minus Absent and On Leave records')
            ->assertSee('DDR-confirmed attendance, separate from planning eligibility');
    }

    public function test_dashboard_filters_trip_rows_without_changing_date_scoped_kpis(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $date = '2026-10-18';
        $route = ShuttleRoute::create([
            'route_name' => 'Filtered Route',
            'route_code' => 'FLT',
            'origin' => 'A',
            'destination' => 'B',
            'distance_km' => 10,
            'estimated_time_minutes' => 30,
            'status' => 'Active',
        ]);

        foreach ([['T-READY', 'Ready'], ['T-CANCELLED', 'Cancelled']] as [$code, $status]) {
            TripSchedule::create([
                'trip_code' => $code,
                'shuttle_route_id' => $route->id,
                'trip_date' => $date,
                'direction' => 'Outgoing',
                'departure_time' => '08:00:00',
                'estimated_arrival_time' => '08:30:00',
                'estimated_arrival_date' => $date,
                'shift' => 'Morning',
                'required_buses' => 1,
                'assignment_status' => 'Unassigned',
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard-operation', [
            'date' => $date,
            'status' => 'Ready',
        ]));

        $response->assertOk()
            ->assertViewHas('trips', fn ($trips) => $trips->count() === 2)
            ->assertViewHas('displayTrips', fn ($trips) => $trips->count() === 1)
            ->assertViewHas('requiredSlots', 1)
            ->assertViewHas('assignedSlots', 0)
            ->assertSee('Trip status filtering applies to the table only.')
            ->assertSee('T-READY')
            ->assertDontSee('T-CANCELLED');
    }
}
