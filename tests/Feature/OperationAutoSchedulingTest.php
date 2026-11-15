<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
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

    public function test_confirm_rejects_a_trip_when_its_route_is_inactive(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus, $route] =
            $this->makeScheduleResources();

        $route->update([
            'status' => 'Inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('auto-scheduling.confirm'),
                [
                    'recommendations' => [[
                        'trip_schedule_id' =>
                            $trip->id,
                        'driver_attendance_id' =>
                            $driver->id,
                        'bus_id' =>
                            $bus->id,
                    ]],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'recommendations.0'
            );

        $this->assertDatabaseMissing(
            'trip_assignments',
            [
                'trip_schedule_id' =>
                    $trip->id,
            ]
        );
    }

    public function test_confirm_rejects_a_new_overlap_and_keeps_the_trip_unassigned(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus, $route] = $this->makeScheduleResources();

        $existingTrip = TripSchedule::create([
            'trip_code' => 'T-EXISTING',
            'trip_date' => $trip->trip_date->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:30:00',
            'estimated_arrival_time' => '09:30:00',
            'estimated_arrival_date' => $trip->trip_date->toDateString(),
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
        $this->createDriverMaster();

        DriverAttendance::create([
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-08-03',
            'status' => 'Present',
        ]);

        DriverAttendance::create([
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-08-04',
            'status' => 'Present',
        ]);

        $this->assertDatabaseCount('driver_attendances', 2);
    }

    public function test_confirm_and_resolve_reject_a_historical_trip(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();

        $pastDate = now()->subDay()->toDateString();

        $trip->update([
            'trip_date' => $pastDate,
            'estimated_arrival_date' => $pastDate,
        ]);

        $driver->update([
            'attendance_date' => $pastDate,
        ]);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $driver->id,
                    'bus_id' => $bus->id,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendations.0');

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.resolve'), [
                'trip_schedule_id' => $trip->id,
                'proposed_departure_time' => '09:15:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trip_schedule_id');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }


    public function test_resolve_recalculates_shift_for_afternoon_departure(): void
    {
        $user = User::factory()->create();
        [$trip, $driver] = $this->makeScheduleResources();
        $driver->update(['shift' => 'Afternoon']);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.resolve'), [
                'trip_schedule_id' => $trip->id,
                'proposed_departure_time' => '13:15:00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('resolution.departure_time', '13:15:00')
            ->assertJsonPath('resolution.arrival_time', '14:15:00')
            ->assertJsonPath('resolution.shift', 'Afternoon');

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
            'departure_time' => '13:15:00',
            'estimated_arrival_time' => '14:15:00',
            'shift' => 'Afternoon',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
    }

    public function test_resolve_preserves_next_day_arrival(): void
    {
        $user = User::factory()->create();
        [$trip, $driver] = $this->makeScheduleResources();
        $driver->update(['shift' => 'Night']);
        $trip->update([
            'departure_time' => '22:00:00',
            'estimated_arrival_time' => '23:00:00',
            'shift' => 'Night',
        ]);

        $expectedArrivalDate = $trip->trip_date->copy()->addDay()->toDateString();

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.resolve'), [
                'trip_schedule_id' => $trip->id,
                'proposed_departure_time' => '23:30:00',
            ])
            ->assertOk()
            ->assertJsonPath('resolution.departure_time', '23:30:00')
            ->assertJsonPath('resolution.arrival_time', '00:30:00')
            ->assertJsonPath('resolution.arrival_date', $expectedArrivalDate);

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
            'estimated_arrival_date' => $expectedArrivalDate,
            'estimated_arrival_time' => '00:30:00',
            'shift' => 'Night',
        ]);
    }

    public function test_confirm_rejects_overlap_with_trip_departing_next_day(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus, $route] = $this->makeScheduleResources();
        $dayAfter = $trip->trip_date->copy()->addDay()->toDateString();
        $trip->update([
            'departure_time' => '23:30:00',
            'estimated_arrival_time' => '01:00:00',
            'estimated_arrival_date' => $dayAfter,
            'shift' => 'Night',
        ]);
        $driver->update(['shift' => 'Night']);

        $nextTrip = TripSchedule::create([
            'trip_code' => 'T-NEXT-DAY',
            'trip_date' => $dayAfter,
            'shuttle_route_id' => $route->id,
            'departure_time' => '00:15:00',
            'estimated_arrival_time' => '00:45:00',
            'estimated_arrival_date' => $dayAfter,
            'shift' => 'Night',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $nextTrip->id,
            'driver_attendance_id' => $driver->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $driver->id,
                    'bus_id' => $bus->id,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendations.0');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_resolve_rejects_proposed_departure_past_in_manila(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-08 16:30:00', 'UTC'));

        try {
            $user = User::factory()->create();
            [$trip, $driver] = $this->makeScheduleResources();
            $trip->update([
                'trip_date' => '2026-10-09',
                'departure_time' => '00:45:00',
                'estimated_arrival_time' => '01:45:00',
                'estimated_arrival_date' => '2026-10-09',
                'shift' => 'Night',
            ]);
            $driver->update([
                'attendance_date' => '2026-10-09',
                'shift' => 'Night',
            ]);

            $this->actingAs($user)
                ->postJson(route('auto-scheduling.resolve'), [
                    'trip_schedule_id' => $trip->id,
                    'proposed_departure_time' => '00:10:00',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('proposed_departure_time');
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_auto_scheduling_counts_only_active_master_drivers(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance] = $this->makeScheduleResources();

        $this->actingAs($user)
            ->get(route('auto-scheduling', [
                'schedule_date' => $trip->trip_date->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('availableDrivers', 1);

        Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->update(['employment_status' => 'Inactive']);

        $this->actingAs($user)
            ->get(route('auto-scheduling', [
                'schedule_date' => $trip->trip_date->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('availableDrivers', 0);
    }

    public function test_auto_scheduling_confirm_rejects_inactive_master_driver(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();

        Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->update(['employment_status' => 'Inactive']);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $attendance->id,
                    'bus_id' => $bus->id,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendations.0');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_auto_scheduling_resolve_rejects_inactive_master_driver(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance] = $this->makeScheduleResources();

        Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->update(['employment_status' => 'Inactive']);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.resolve'), [
                'trip_schedule_id' => $trip->id,
                'proposed_departure_time' => '09:15:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    private function makeScheduleResources(): array
    {
        $scheduleDate = now()->addDay()->toDateString();

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

        $this->createDriverMaster();

        $driver = DriverAttendance::create([
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => $scheduleDate,
            'status' => 'Present',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-001',
            'trip_date' => $scheduleDate,
            'shuttle_route_id' => $route->id,
            'departure_time' => '09:00:00',
            'estimated_arrival_time' => '10:00:00',
            'estimated_arrival_date' => $scheduleDate,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        return [$trip, $driver, $bus, $route];
    }

    private function createDriverMaster(): Driver
    {
        return Driver::firstOrCreate(
            ['driver_id' => 'D-2026-0001'],
            [
                'driver_name' => 'Test Driver',
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]
        );
    }
}
