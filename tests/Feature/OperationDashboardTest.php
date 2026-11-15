<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
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
        $response->assertSee('Bus Availability');
        $response->assertSee('Driver Attendance');
        $response->assertSee('Mechanic Attendance');
        $response->assertSee('Recent Operational Incidents');
        $response->assertSee('Recent Driver Reports');
        $response->assertSee('Auto Scheduling Engine');
        $response->assertSee('T-1001');
        $response->assertSee('Downtown → Uptown');
    }
}
