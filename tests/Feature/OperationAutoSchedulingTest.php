<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationAutoSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_saves_a_valid_recommendation(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();

        $response = $this
            ->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $driver->id,
                    'bus_id' => $bus->id,
                ]],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('saved', 1)
            ->assertJsonPath(
                'redirect_url',
                '/operation/driver-bus-assignment'
            );

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'driver_id' => $driver->driver_id,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
    }

    public function test_confirm_rejects_a_new_overlap_and_keeps_the_trip_unassigned(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus, $route] = $this->makeScheduleResources();

        $existingTrip = TripSchedule::create([
            'trip_code' => 'T-EXISTING',
            'trip_date' => '2026-08-03',
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:30:00',
            'estimated_arrival_time' => '09:30:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
            'created_by' => $user->id,
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $existingTrip->id,
            'driver_attendance_id' => $driver->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $driver->id,
                    'bus_id' => $bus->id,
                ]],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendations.0');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);
    }

    public function test_the_same_driver_id_can_have_attendance_on_different_dates(): void
    {
        DriverAttendance::create([
            'driver_id' => 'D-2026-0001',
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-08-03',
            'status' => 'Present',
        ]);

        DriverAttendance::create([
            'driver_id' => 'D-2026-0001',
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-08-04',
            'status' => 'Present',
        ]);

        $this->assertDatabaseCount('driver_attendances', 2);
    }

    private function makeScheduleResources(): array
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-001',
            'route_name' => 'Test Route',
            'origin' => 'Batangas',
            'destination' => 'Lipa',
            'distance_km' => 25,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-001',
            'plate_no' => 'ABC-1234',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $driver = DriverAttendance::create([
            'driver_id' => 'D-2026-0001',
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-08-03',
            'status' => 'Present',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-001',
            'trip_date' => '2026-08-03',
            'shuttle_route_id' => $route->id,
            'departure_time' => '09:00:00',
            'estimated_arrival_time' => '10:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        return [$trip, $driver, $bus, $route];
    }
}
