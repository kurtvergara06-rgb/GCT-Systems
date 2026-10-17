<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\OperationClient;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationAutoSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_editor_lists_eligible_resources_without_persisting(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();

        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), [
            'trip_schedule_id' => $trip->id,
        ])->assertOk()
            ->assertJsonFragment(['id' => $driver->id, 'name' => $driver->driver_name])
            ->assertJsonFragment(['id' => $bus->id, 'name' => $bus->bus_no]);

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_preview_editor_validates_a_selection_without_saving(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();

        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'recommendations' => [],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_generation_and_confirmation_use_active_driver_without_creating_attendance(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();
        $attendance->delete();

        $recommendation = $this->actingAs($user)
            ->postJson(route('auto-scheduling.generate'), [
                'schedule_date' => $trip->trip_date->toDateString(),
                'shift' => 'all',
                'shuttle_route_id' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('recommendations.0.driver_reference_type', 'driver_master')
            ->assertJsonPath('recommendations.0.driver_attendance_id', $driver->id)
            ->json('recommendations.0');

        $this->assertDatabaseCount('driver_attendances', 0);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [$recommendation],
            ])
            ->assertOk()
            ->assertJsonPath('saved', 1);

        $this->assertDatabaseCount('driver_attendances', 0);
        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'bus_id' => $bus->id,
            'driver_master_id' => $driver->id,
            'driver_attendance_id' => null,
        ]);
    }

    public function test_preview_editor_accepts_any_of_multiple_bus_clients_and_rejects_other_clients(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();
        $sm = \App\Models\Operation\OperationClient::create(['name' => 'SM']);
        $epson = \App\Models\Operation\OperationClient::create(['name' => 'EPSON']);
        $bus->update(['client_allocation_type' => 'Shared']);
        $bus->operationClients()->sync([$sm->id, $epson->id]);

        $payload = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'recommendations' => [],
        ];

        $trip->update(['client_name' => 'EPSON']);
        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), $payload)
            ->assertOk()->assertJsonPath('success', true);

        $trip->update(['client_name' => 'SM']);
        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), $payload)
            ->assertOk()->assertJsonPath('success', true);

        $trip->update(['client_name' => 'Murata']);
        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('assignment');

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_client_trip_uses_allocated_regular_bus_and_does_not_treat_unallocated_bus_as_universal(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $allocatedBus] = $this->makeScheduleResources();
        $client = OperationClient::create(['name' => 'EPSON', 'status' => 'Active']);
        $trip->update(['client_name' => $client->name]);
        $allocatedBus->update(['client_allocation_type' => 'Exclusive']);
        $allocatedBus->operationClients()->sync([$client->id]);

        $unallocatedBus = Bus::create([
            'bus_no' => 'AAA-UNALLOCATED',
            'plate_no' => 'UNA-001',
            'bus_model' => 'Unallocated Regular',
            'capacity' => 40,
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);
        Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail()
            ->authorizedBuses()->syncWithoutDetaching([$unallocatedBus->id]);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.generate'), [
                'schedule_date' => $trip->trip_date->toDateString(),
                'shift' => 'all',
                'shuttle_route_id' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('recommendations.0.bus_id', $allocatedBus->id);
    }

    public function test_preview_editor_uses_active_driver_master_and_does_not_treat_attendance_as_eligibility(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $attendance->update(['status' => 'Absent']);
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();

        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
        ])->assertOk()->assertJsonPath('driver.reference_type', 'driver_master');

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
        $this->assertDatabaseHas('driver_attendances', [
            'id' => $attendance->id,
            'status' => 'Absent',
        ]);
    }

    public function test_preview_editor_rejects_inactive_driver_master_without_saving(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();
        $driver->update(['employment_status' => 'Inactive']);

        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('assignment');

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_preview_editor_authorizes_reserve_bus_selection_server_side(): void
    {
        $head = User::factory()->create();
        $staff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
        ]);
        [$trip, $attendance, $regular] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();
        $regular->update(['status' => 'Inactive']);
        $reserve = Bus::create([
            'bus_no' => 'BUS-RES-PREVIEW',
            'plate_no' => 'RES-PREV',
            'bus_model' => 'Reserve Preview Bus',
            'capacity' => 40,
            'status' => 'Active',
            'fleet_purpose' => 'Reserve',
        ]);
        $payload = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $reserve->id,
            'recommendations' => [],
        ];

        $this->actingAs($staff)->postJson(route('auto-scheduling.edit-preview'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bus_id');

        $this->actingAs($head)->postJson(route('auto-scheduling.edit-preview'), $payload)
            ->assertOk()
            ->assertJsonPath('bus.id', $reserve->id)
            ->assertJsonPath('bus.purpose', 'Reserve');

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_preview_editor_rejects_resources_used_by_an_overlapping_preview_assignment(): void
    {
        $user = User::factory()->create();
        [$firstTrip, $attendance, $bus, $route] = $this->makeScheduleResources();
        $driver = Driver::query()->where('driver_id', $attendance->driver_id)->firstOrFail();
        $overlappingTrip = TripSchedule::create([
            'trip_code' => 'T-PREVIEW-OVERLAP',
            'trip_date' => $firstTrip->trip_date->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '09:30:00',
            'estimated_arrival_time' => '10:30:00',
            'estimated_arrival_date' => $firstTrip->trip_date->toDateString(),
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($user)->postJson(route('auto-scheduling.edit-preview'), [
            'trip_schedule_id' => $overlappingTrip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'recommendations' => [[
                'trip_schedule_id' => $firstTrip->id,
                'driver_attendance_id' => $driver->id,
                'driver_reference_type' => 'driver_master',
                'bus_id' => $bus->id,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('assignment');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $overlappingTrip->id,
        ]);
    }

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
                        'trip_schedule_id' => $trip->id,
                        'driver_attendance_id' => $driver->id,
                        'bus_id' => $bus->id,
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
                'trip_schedule_id' => $trip->id,
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
        Carbon::setTestNow(Carbon::parse('2026-10-08 16:30:00', 'UTC'));

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
            Carbon::setTestNow();
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

    public function test_auto_scheduling_confirm_does_not_require_driver_license_expiration(): void
    {
        $user = User::factory()->create();
        [$trip, $attendance, $bus] = $this->makeScheduleResources();

        Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->update(['license_expiration' => $trip->trip_date->copy()->subDay()->toDateString()]);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [[
                    'trip_schedule_id' => $trip->id,
                    'driver_attendance_id' => $attendance->id,
                    'bus_id' => $bus->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('saved', 1);

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $bus->id,
        ]);
    }

    public function test_resolve_keeps_multi_bus_trip_partially_assigned(): void
    {
        $user = User::factory()->create();
        [$trip] = $this->makeScheduleResources();
        $trip->update(['required_buses' => 3]);

        $this->actingAs($user)
            ->postJson(route('auto-scheduling.resolve'), [
                'trip_schedule_id' => $trip->id,
                'proposed_departure_time' => '09:15:00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
            'assignment_status' => 'Partially Assigned',
            'status' => 'Scheduled',
        ]);
        $this->assertSame(1, TripAssignment::query()->where('trip_schedule_id', $trip->id)->count());
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

        $driverMaster = $this->createDriverMaster();
        $driverMaster->authorizedBuses()->syncWithoutDetaching([$bus->id]);

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
        ]);

        return [$trip, $driver, $bus, $route];
    }

    public function test_auto_scheduling_page_renders_incoming_and_outgoing_service_panels(): void
    {
        $user = User::factory()->create();
        $scheduleDate = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $response = $this->actingAs($user)
            ->get(route('auto-scheduling', ['schedule_date' => $scheduleDate]));

        $response->assertOk()
            ->assertSee('id="autoIncomingPanel"', false)
            ->assertSee('id="autoOutgoingPanel"', false)
            ->assertSee('Incoming Trips')
            ->assertSee('Buses transporting employees to work')
            ->assertSee('Outgoing Trips')
            ->assertSee('Buses transporting employees home')
            ->assertSee('id="incomingTripCount"', false)
            ->assertSee('id="incomingRequiredBusesCount"', false)
            ->assertSee('id="incomingReadyCount"', false)
            ->assertSee('id="incomingReviewCount"', false)
            ->assertSee('id="outgoingTripCount"', false)
            ->assertSee('id="outgoingRequiredBusesCount"', false)
            ->assertSee('id="outgoingReadyCount"', false)
            ->assertSee('id="outgoingReviewCount"', false)
            ->assertSee('id="autoScheduleIncomingBody"', false)
            ->assertSee('id="autoScheduleOutgoingBody"', false)
            ->assertSee('id="confirmScheduleButton"', false);
    }

    public function test_generate_returns_directional_grouping_and_summary_breakdown(): void
    {
        $user = User::factory()->create();
        $scheduleDate = '2026-10-20';

        $route = ShuttleRoute::create([
            'route_code' => 'R-TEST',
            'route_name' => 'Test Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'status' => 'Active',
        ]);

        $incomingTrip = TripSchedule::create([
            'trip_code' => 'T-INC-01',
            'trip_date' => $scheduleDate,
            'shuttle_route_id' => $route->id,
            'direction' => 'Incoming',
            'required_buses' => 2,
            'departure_time' => '18:00:00', // Departing late, but explicitly Incoming
            'estimated_arrival_time' => '19:00:00',
            'estimated_arrival_date' => $scheduleDate,
            'shift' => 'Night',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $outgoingTrip = TripSchedule::create([
            'trip_code' => 'T-OUT-01',
            'trip_date' => $scheduleDate,
            'shuttle_route_id' => $route->id,
            'direction' => 'Outgoing',
            'required_buses' => 1,
            'departure_time' => '06:00:00', // Departing early, but explicitly Outgoing
            'estimated_arrival_time' => '07:00:00',
            'estimated_arrival_date' => $scheduleDate,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('auto-scheduling.generate'), [
                'schedule_date' => $scheduleDate,
                'shift' => 'all',
                'shuttle_route_id' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.incoming.trips', 1)
            ->assertJsonPath('summary.incoming.required_buses', 2)
            ->assertJsonPath('summary.outgoing.trips', 1)
            ->assertJsonPath('summary.outgoing.required_buses', 1);

        $data = $response->json();
        $allSlots = array_merge($data['recommendations'], $data['conflicts']);

        $incomingSlots = array_filter($allSlots, fn ($s) => $s['direction'] === 'Incoming');
        $outgoingSlots = array_filter($allSlots, fn ($s) => $s['direction'] === 'Outgoing');

        $this->assertCount(2, $incomingSlots);
        $this->assertCount(1, $outgoingSlots);

        foreach ($incomingSlots as $slot) {
            $this->assertSame('Incoming', $slot['direction']);
            $this->assertSame(2, $slot['required_buses']);
        }

        foreach ($outgoingSlots as $slot) {
            $this->assertSame('Outgoing', $slot['direction']);
            $this->assertSame(1, $slot['required_buses']);
        }
    }

    public function test_confirm_submits_recommendations_from_both_incoming_and_outgoing(): void
    {
        $user = User::factory()->create();
        $scheduleDate = '2026-10-21';

        $route = ShuttleRoute::create([
            'route_code' => 'R-BOTH',
            'route_name' => 'Both Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'status' => 'Active',
        ]);

        $bus1 = Bus::create([
            'bus_no' => 'GCT-901',
            'plate_number' => 'ABC-901',
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);

        $bus2 = Bus::create([
            'bus_no' => 'GCT-902',
            'plate_number' => 'ABC-902',
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);

        $driver1Master = Driver::firstOrCreate(
            ['driver_id' => 'D-TEST-901'],
            ['driver_name' => 'Driver One', 'shift' => 'Morning', 'employment_status' => 'Active']
        );
        $driver1 = DriverAttendance::create([
            'driver_id' => $driver1Master->driver_id,
            'driver_name' => 'Driver One',
            'shift' => 'Morning',
            'attendance_date' => $scheduleDate,
            'status' => 'Present',
        ]);

        $driver2Master = Driver::firstOrCreate(
            ['driver_id' => 'D-TEST-902'],
            ['driver_name' => 'Driver Two', 'shift' => 'Afternoon', 'employment_status' => 'Active']
        );
        $driver2 = DriverAttendance::create([
            'driver_id' => $driver2Master->driver_id,
            'driver_name' => 'Driver Two',
            'shift' => 'Afternoon',
            'attendance_date' => $scheduleDate,
            'status' => 'Present',
        ]);

        $driver1Master->authorizedBuses()->syncWithoutDetaching([$bus1->id]);
        $driver2Master->authorizedBuses()->syncWithoutDetaching([$bus2->id]);

        $incomingTrip = TripSchedule::create([
            'trip_code' => 'T-INC-CONFIRM',
            'trip_date' => $scheduleDate,
            'shuttle_route_id' => $route->id,
            'direction' => 'Incoming',
            'required_buses' => 1,
            'departure_time' => '07:00:00',
            'estimated_arrival_time' => '08:00:00',
            'estimated_arrival_date' => $scheduleDate,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $outgoingTrip = TripSchedule::create([
            'trip_code' => 'T-OUT-CONFIRM',
            'trip_date' => $scheduleDate,
            'shuttle_route_id' => $route->id,
            'direction' => 'Outgoing',
            'required_buses' => 1,
            'departure_time' => '17:00:00',
            'estimated_arrival_time' => '18:00:00',
            'estimated_arrival_date' => $scheduleDate,
            'shift' => 'Afternoon',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('auto-scheduling.confirm'), [
                'recommendations' => [
                    [
                        'trip_schedule_id' => $incomingTrip->id,
                        'driver_attendance_id' => $driver1->id,
                        'bus_id' => $bus1->id,
                    ],
                    [
                        'trip_schedule_id' => $outgoingTrip->id,
                        'driver_attendance_id' => $driver2->id,
                        'bus_id' => $bus2->id,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('saved', 2);

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $incomingTrip->id,
            'bus_id' => $bus1->id,
        ]);
        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $outgoingTrip->id,
            'bus_id' => $bus2->id,
        ]);

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $incomingTrip->id,
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        $this->assertDatabaseHas('trip_schedules', [
            'id' => $outgoingTrip->id,
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
    }

    public function test_reserve_bus_is_recommended_only_when_no_regular_bus_is_available(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $regular] = $this->makeScheduleResources();
        $reserve = Bus::create([
            'bus_no' => 'BUS-RES-001',
            'plate_no' => 'RES-1001',
            'bus_model' => 'Reserve Test Bus',
            'capacity' => 40,
            'status' => 'Active',
            'fleet_purpose' => 'Reserve',
        ]);

        $payload = [
            'schedule_date' => $trip->trip_date->toDateString(),
            'shift' => 'all',
            'shuttle_route_id' => 'all',
        ];

        $regularResult = $this->actingAs($user)
            ->postJson(route('auto-scheduling.generate'), $payload)
            ->assertOk()->json('recommendations');
        $this->assertSame($regular->id, $regularResult[0]['bus_id']);
        $this->assertFalse($regularResult[0]['reserve_approval_required']);

        $regular->update(['status' => 'Inactive']);
        $reserveResponse = $this->actingAs($user)
            ->postJson(route('auto-scheduling.generate'), $payload)
            ->assertOk();
        $this->assertSame([], $reserveResponse->json('recommendations'));
        $reserveSuggestion = collect($reserveResponse->json('conflicts'))
            ->first(fn (array $conflict): bool => $conflict['issue_type'] === 'reserve_approval_required');
        $this->assertNotNull($reserveSuggestion);
        $this->assertFalse($reserveSuggestion['missing_driver']);
        $this->assertFalse($reserveSuggestion['missing_bus']);
        $driverMaster = Driver::query()->where('driver_id', $driver->driver_id)->firstOrFail();
        $this->assertSame($driverMaster->id, (int) $reserveSuggestion['suggested_driver_attendance_id']);
        $this->assertSame($reserve->id, (int) $reserveSuggestion['suggested_bus_id']);
        $this->assertSame('Conflict', $reserveSuggestion['result']);
        $this->assertSame($driverMaster->id, (int) $reserveSuggestion['ai']['valid_pairs'][0]['driver_attendance_id']);
        $this->assertSame($reserve->id, (int) $reserveSuggestion['ai']['valid_pairs'][0]['bus_id']);
        $this->assertSame('rule_fallback', $reserveSuggestion['ai']['valid_pairs'][0]['driver_score_source']);
        $this->assertSame('rule_fallback', $reserveSuggestion['ai']['valid_pairs'][0]['bus_score_source']);
        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_reserve_assignment_requires_operations_head_and_regular_shortage(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $regular] = $this->makeScheduleResources();
        $reserve = Bus::create([
            'bus_no' => 'BUS-RES-002',
            'plate_no' => 'RES-1002',
            'bus_model' => 'Reserve Test Bus',
            'capacity' => 40,
            'status' => 'Active',
            'fleet_purpose' => 'Reserve',
        ]);
        $payload = ['recommendations' => [[
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $reserve->id,
        ]]];

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('recommendations.0');

        $staff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
        ]);
        $staffPermission = RolePermission::where('role_key', 'operation_staff')->firstOrFail();
        $staffPermissions = $staffPermission->permissions;
        data_set($staffPermissions, 'operation.edit', true);
        $staffPermission->update(['permissions' => $staffPermissions]);

        $regular->update(['status' => 'Inactive']);
        $this->actingAs($staff)->postJson(route('auto-scheduling.confirm'), $payload)
            ->assertForbidden();

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), $payload)
            ->assertOk()->assertJsonPath('saved', 1);

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'bus_id' => $reserve->id,
        ]);

        $this->assertDatabaseHas('auto_schedule_reserve_approvals', [
            'recorded_by' => $user->id,
            'approved_by' => 'Operations Head (direct assignment)',
            'approval_method' => 'Direct',
        ]);
    }

    public function test_operations_head_can_confirm_reserve_when_active_regular_bus_has_no_eligible_authorized_driver(): void
    {
        $head = User::factory()->create();
        [$trip, $attendance, $regular] = $this->makeScheduleResources();
        $reserve = Bus::create([
            'bus_no' => 'BUS-RES-NO-PAIR',
            'plate_no' => 'RES-NP-01',
            'bus_model' => 'Reserve No Pair Bus',
            'capacity' => 40,
            'status' => 'Active',
            'fleet_purpose' => 'Reserve',
        ]);

        Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->firstOrFail()
            ->authorizedBuses()
            ->detach($regular->id);

        $this->actingAs($head)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [[
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $attendance->id,
                'bus_id' => $reserve->id,
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $reserve->id,
        ]);
    }

    public function test_confirm_refuses_unresolved_schedule_conflicts_even_with_ready_recommendations(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();
        $filters = [
            'schedule_date' => $trip->trip_date->toDateString(),
            'shift' => 'all',
            'shuttle_route_id' => 'all',
        ];
        $recommendations = [[
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
        ]];
        $conflicts = [['trip_schedule_id' => $trip->id, 'issue_type' => 'missing_bus']];

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => $recommendations,
            'conflicts' => $conflicts,
        ])->assertUnprocessable()->assertJsonValidationErrors('conflicts');

        $this->actingAs($user)->postJson(route('auto-scheduling.draft.save'),
            $filters + ['recommendations' => $recommendations, 'conflicts' => $conflicts])
            ->assertOk();

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => $recommendations,
            'conflicts' => [],
            'draft_filters' => $filters,
        ])->assertUnprocessable()->assertJsonValidationErrors('conflicts');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_auto_schedule_draft_persists_without_assigning_a_bus(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();
        $filters = ['schedule_date' => $trip->trip_date->toDateString(), 'shift' => 'all', 'shuttle_route_id' => 'all'];
        $preview = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'result' => 'Ready',
        ];
        $this->actingAs($user)->postJson(route('auto-scheduling.draft.save'),
            $filters + ['recommendations' => [$preview], 'conflicts' => []])
            ->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($user)->getJson(route('auto-scheduling.draft.load', $filters))
            ->assertOk()->assertJsonPath('draft.recommendations.0.bus_id', $bus->id);

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
        $this->actingAs($user)->deleteJson(route('auto-scheduling.draft.delete', $filters))
            ->assertOk()->assertJsonPath('deleted', true);
        $this->actingAs($user)->getJson(route('auto-scheduling.draft.load', $filters))
            ->assertOk()->assertJsonPath('draft', null);
    }

    public function test_drafts_are_private_to_the_operator(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$trip] = $this->makeScheduleResources();
        $filters = ['schedule_date' => $trip->trip_date->toDateString(), 'shift' => 'all', 'shuttle_route_id' => 'all'];

        $this->actingAs($owner)->postJson(route('auto-scheduling.draft.save'),
            $filters + ['recommendations' => [], 'conflicts' => []])->assertOk();
        $this->actingAs($other)->getJson(route('auto-scheduling.draft.load', $filters))
            ->assertOk()->assertJsonPath('draft', null);
    }

    public function test_partial_confirmation_requires_and_persists_an_explicit_slot_decision(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();
        $trip->update(['required_buses' => 2]);

        $recommendation = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'slot' => 1,
        ];

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [$recommendation],
            'decisions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('decisions');

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [$recommendation],
            'decisions' => [[
                'trip_schedule_id' => $trip->id,
                'slot' => 2,
                'decision' => 'manual_later',
                'notes' => 'Operations will assign the remaining unit later.',
            ]],
        ])->assertOk()
            ->assertJsonPath('saved', 1)
            ->assertJsonPath('pending_decisions', 1);

        $this->assertSame('Partially Assigned', $trip->fresh()->assignment_status);
        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 2,
            'decision' => 'manual_later',
            'status' => 'Pending',
        ]);

        $secondDriver = Driver::create([
            'driver_id' => 'D-2026-0002',
            'driver_name' => 'Second Test Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $secondAttendance = DriverAttendance::create([
            'driver_id' => $secondDriver->driver_id,
            'driver_name' => $secondDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $trip->trip_date->toDateString(),
            'status' => 'Late',
        ]);
        $secondBus = Bus::create([
            'bus_no' => 'BUS-002',
            'plate_no' => 'ABC-5678',
            'bus_model' => 'Second Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
        $secondDriver->authorizedBuses()->syncWithoutDetaching([$secondBus->id]);

        $this->actingAs($user)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $secondAttendance->id,
            'bus_id' => $secondBus->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Assigned', $trip->fresh()->assignment_status);
        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 2,
            'status' => 'Resolved',
        ]);
    }

    public function test_draft_saves_and_restores_manual_and_external_slot_decisions(): void
    {
        $user = User::factory()->create();
        [$trip] = $this->makeScheduleResources();
        $filters = ['schedule_date' => $trip->trip_date->toDateString(), 'shift' => 'all', 'shuttle_route_id' => 'all'];
        $decision = [
            'trip_schedule_id' => $trip->id,
            'slot' => 1,
            'decision' => 'external_bus',
            'notes' => 'Rental request pending.',
        ];

        $this->actingAs($user)->postJson(route('auto-scheduling.draft.save'), $filters + [
            'recommendations' => [],
            'conflicts' => [],
            'decisions' => [$decision],
        ])->assertOk()->assertJsonPath('saved', true);

        $this->actingAs($user)->getJson(route('auto-scheduling.draft.load', $filters))
            ->assertOk()
            ->assertJsonPath('draft.decisions.0.decision', 'external_bus')
            ->assertJsonPath('draft.decisions.0.notes', 'Rental request pending.');
    }

    public function test_assignment_resolves_only_the_exact_deferred_slot(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();
        $trip->update(['required_buses' => 2]);

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [[
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $driver->id,
                'bus_id' => $bus->id,
                'slot' => 2,
            ]],
            'decisions' => [[
                'trip_schedule_id' => $trip->id,
                'slot' => 1,
                'decision' => 'manual_later',
                'notes' => 'Fill the first slot manually.',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 2,
        ]);
        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 1,
            'status' => 'Pending',
            'fulfilled_by_assignment_id' => null,
        ]);

        $secondDriver = Driver::create([
            'driver_id' => 'D-2026-0099',
            'driver_name' => 'Exact Slot Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $secondAttendance = DriverAttendance::create([
            'driver_id' => $secondDriver->driver_id,
            'driver_name' => $secondDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $trip->trip_date->toDateString(),
            'status' => 'Present',
        ]);
        $secondBus = Bus::create([
            'bus_no' => 'BUS-099',
            'plate_no' => 'SLOT-099',
            'bus_model' => 'Exact Slot Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
        $secondDriver->authorizedBuses()->syncWithoutDetaching([$secondBus->id]);

        $this->actingAs($user)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $secondAttendance->id,
            'bus_id' => $secondBus->id,
        ])->assertSessionHasNoErrors();

        $slotOneAssignment = TripAssignment::query()
            ->where('trip_schedule_id', $trip->id)
            ->where('slot_number', 1)
            ->firstOrFail();

        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 1,
            'status' => 'Resolved',
            'fulfilled_by_assignment_id' => $slotOneAssignment->id,
        ]);

        $this->actingAs($user)
            ->delete(route('driver-bus-assignment.destroy', $slotOneAssignment))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 1,
            'status' => 'Pending',
            'fulfilled_by_assignment_id' => null,
        ]);
        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 2,
        ]);
    }

    public function test_confirmation_rejects_draft_content_changes_and_missing_draft_identity(): void
    {
        $user = User::factory()->create();
        [$trip, $driver, $bus] = $this->makeScheduleResources();
        $filters = [
            'schedule_date' => $trip->trip_date->toDateString(),
            'shift' => 'all',
            'shuttle_route_id' => 'all',
        ];
        $recommendation = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'bus_id' => $bus->id,
            'slot' => 1,
        ];

        $this->actingAs($user)->postJson(route('auto-scheduling.draft.save'), $filters + [
            'recommendations' => [$recommendation],
            'conflicts' => [],
            'decisions' => [],
        ])->assertOk();

        $changed = $recommendation;
        $changed['slot'] = 2;
        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [$changed],
            'conflicts' => [],
            'decisions' => [],
            'draft_filters' => $filters,
        ])->assertUnprocessable()->assertJsonValidationErrors('recommendations');

        $this->actingAs($user)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [$recommendation],
            'conflicts' => [],
            'decisions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('draft_filters');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_e2e_test_a_multi_client_multi_bus_direction_generation_and_same_route_different_client_separation(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $date = now()->addDays(2)->toDateString();

        $routeFpipCalamba = ShuttleRoute::create([
            'route_code' => 'R-FPIP-CAL',
            'route_name' => 'FPIP – Calamba',
            'origin' => 'FPIP',
            'destination' => 'Calamba',
            'distance_km' => 15.0,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);
        $routeCalambaFpip = ShuttleRoute::create([
            'route_code' => 'R-CAL-FPIP',
            'route_name' => 'Calamba – FPIP',
            'origin' => 'Calamba',
            'destination' => 'FPIP',
            'distance_km' => 15.0,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);
        $routeFpipTanauan = ShuttleRoute::create([
            'route_code' => 'R-FPIP-TAN',
            'route_name' => 'FPIP – Tanauan',
            'origin' => 'FPIP',
            'destination' => 'Tanauan',
            'distance_km' => 10.0,
            'estimated_time_minutes' => 30,
            'status' => 'Active',
        ]);
        $routeInternal = ShuttleRoute::create([
            'route_code' => 'R-INTERNAL',
            'route_name' => 'Internal Shuttle',
            'origin' => 'Gate 1',
            'destination' => 'Gate 2',
            'distance_km' => 5.0,
            'estimated_time_minutes' => 20,
            'status' => 'Active',
        ]);

        // Trip 1: Incoming | Client: EPSON | Route: FPIP – Calamba | 06:00 AM | 1 bus
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeFpipCalamba->id,
            'client_name' => 'EPSON',
            'direction' => 'Incoming',
            'departure_time' => '06:00',
            'required_buses' => 1,
        ])->assertSessionHasNoErrors();

        // Trip 2: Incoming | Client: EPSON | Route: FPIP – Calamba | 07:00 AM | 2 buses
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeFpipCalamba->id,
            'client_name' => 'EPSON',
            'direction' => 'Incoming',
            'departure_time' => '07:00',
            'required_buses' => 2,
        ])->assertSessionHasNoErrors();

        // Trip 3: Incoming | Client: SM | Route: FPIP – Calamba | 06:00 AM | 1 bus (Same route & time, different client)
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeFpipCalamba->id,
            'client_name' => 'SM',
            'direction' => 'Incoming',
            'departure_time' => '06:00',
            'required_buses' => 1,
        ])->assertSessionHasNoErrors();

        // Same route, same time, SAME client (EPSON at 06:00 AM) must still be rejected as duplicate
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeFpipCalamba->id,
            'client_name' => 'epson',
            'direction' => 'Incoming',
            'departure_time' => '06:00',
            'required_buses' => 1,
        ])->assertSessionHasErrors('departure_time');

        // Trip 4: Outgoing | Client: EPSON | Route: Calamba – FPIP | 05:00 PM | 1 bus
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeCalambaFpip->id,
            'client_name' => 'EPSON',
            'direction' => 'Outgoing',
            'departure_time' => '17:00',
            'required_buses' => 1,
        ])->assertSessionHasNoErrors();

        // Trip 5: Outgoing | Client: Murata | Route: FPIP – Tanauan | 05:30 PM | 1 bus
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeFpipTanauan->id,
            'client_name' => 'Murata',
            'direction' => 'Outgoing',
            'departure_time' => '17:30',
            'required_buses' => 1,
        ])->assertSessionHasNoErrors();

        // Trip 6: Incoming | No client | Route: Internal Shuttle | 08:00 AM | 1 bus
        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $routeInternal->id,
            'client_name' => null,
            'direction' => 'Incoming',
            'departure_time' => '08:00',
            'required_buses' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, TripSchedule::query()->whereDate('trip_date', $date)->count());

        $response = $this->actingAs($user)->postJson(route('auto-scheduling.generate'), [
            'schedule_date' => $date,
            'shift' => 'all',
            'shuttle_route_id' => 'all',
        ])->assertOk();

        // 6 trips with 1+2+1+1+1+1 = 7 total slots generated in preview
        $allSlots = collect($response->json('recommendations'))->concat($response->json('conflicts'));
        $this->assertCount(7, $allSlots);
        $this->assertCount(5, $allSlots->where('direction', 'Incoming'));
        $this->assertCount(2, $allSlots->where('direction', 'Outgoing'));

        // Zero trip_assignments rows created before Confirm Schedule
        $this->assertDatabaseCount('trip_assignments', 0);
    }

    public function test_e2e_test_b_manual_assignment_availability_filters_buses_by_trip_client(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);
        [$epsonTrip, $attendance, $busShared] = $this->makeScheduleResources();
        $epsonTrip->update(['client_name' => 'EPSON']);
        $epsonClient = OperationClient::firstOrCreate(['name' => 'EPSON'], ['status' => 'Active']);
        $smClient = OperationClient::firstOrCreate(['name' => 'SM'], ['status' => 'Active']);
        $murataClient = OperationClient::firstOrCreate(['name' => 'Murata'], ['status' => 'Active']);

        // Bus 100 (shared): EPSON + SM
        $busShared->operationClients()->sync([$epsonClient->id, $smClient->id]);

        // Bus 110 (exclusive Murata): Murata only
        $busMurata = Bus::create([
            'bus_no' => 'BUS-110',
            'plate_no' => 'MUR-110',
            'bus_model' => 'Murata Exclusive',
            'capacity' => 45,
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);
        $busMurata->operationClients()->sync([$murataClient->id]);

        // Bus 120 (unallocated): no clients
        $busUnallocated = Bus::create([
            'bus_no' => 'BUS-120',
            'plate_no' => 'UNA-120',
            'bus_model' => 'Unallocated Bus',
            'capacity' => 45,
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);

        // Availability for EPSON trip returns only Bus 100
        $epsonAvail = $this->actingAs($user)
            ->getJson(route('driver-bus-assignment.availability', $epsonTrip))
            ->assertOk();
        $this->assertSame([$busShared->id], $epsonAvail->json('buses.*.id'));

        // Availability for non-client trip returns only Bus 120 (unallocated)
        $noClientTrip = TripSchedule::create([
            'trip_code' => 'T-NOCLIENT-AVAIL',
            'trip_date' => $epsonTrip->trip_date,
            'shuttle_route_id' => $epsonTrip->shuttle_route_id,
            'client_name' => null,
            'direction' => 'Incoming',
            'required_buses' => 1,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:30:00',
            'estimated_arrival_date' => $epsonTrip->trip_date,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);

        $noClientAvail = $this->actingAs($user)
            ->getJson(route('driver-bus-assignment.availability', $noClientTrip))
            ->assertOk();
        $this->assertSame([$busUnallocated->id], $noClientAvail->json('buses.*.id'));
    }

    public function test_e2e_test_f_resolve_endpoint_excludes_buses_with_open_job_orders_and_mismatched_clients(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);
        [$trip, $attendance, $bus] = $this->makeScheduleResources();
        $trip->update([
            'client_name' => 'EPSON',
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:30:00',
        ]);
        $epson = OperationClient::firstOrCreate(['name' => 'EPSON'], ['status' => 'Active']);
        $bus->operationClients()->sync([$epson->id]);

        // Put an open Job Order on the only compatible bus
        JobOrder::create([
            'job_order_no' => 'JO-TEST-RESOLVE-001',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Brake Repair',
            'work_to_perform' => 'Replace brake pads',
            'maintenance_type' => 'Corrective',
            'status' => 'On Going',
        ]);

        $this->actingAs($user)->postJson(route('auto-scheduling.resolve'), [
            'trip_schedule_id' => $trip->id,
            'proposed_departure_time' => '08:30:00',
            'slot' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('resolution');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
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
