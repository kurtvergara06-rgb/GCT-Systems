<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationTripScheduleHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function operationUser(string $role = 'head'): User
    {
        return User::factory()->create([
            'department' => 'Operation',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function route(string $code = 'R-TEST', int $minutes = 30): ShuttleRoute
    {
        return ShuttleRoute::create([
            'route_code' => $code,
            'route_name' => 'Test Route',
            'origin' => 'Terminal A',
            'origin_latitude' => 14.1000000,
            'origin_longitude' => 121.1000000,
            'destination' => 'Terminal B',
            'destination_latitude' => 14.2000000,
            'destination_longitude' => 121.2000000,
            'distance_km' => 12.5,
            'estimated_time_minutes' => $minutes,
            'status' => 'Active',
        ]);
    }

    private function trip(
        ShuttleRoute $route,
        array $overrides = []
    ): TripSchedule {
        $date = now()->addDay()->toDateString();

        return TripSchedule::create(array_merge([
            'trip_code' => 'T-MANUAL-'.str_pad(
                (string) (TripSchedule::count() + 1),
                2,
                '0',
                STR_PAD_LEFT
            ),
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:30:00',
            'estimated_arrival_date' => $date,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ], $overrides));
    }

    private function assignmentResources(
        string $date,
        string $suffix
    ): array {
        $driver = Driver::create([
            'driver_id' => "D-{$suffix}",
            'driver_name' => "Driver {$suffix}",
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);

        $bus = Bus::create([
            'bus_no' => "BUS-{$suffix}",
            'plate_no' => "PLT-{$suffix}",
            'bus_model' => "Bus {$suffix}",
            'capacity' => 40,
            'status' => 'Active',
        ]);

        return [$driver, $attendance, $bus];
    }

    public function test_new_trip_is_always_scheduled_and_tracks_overnight_arrival_date(): void
    {
        $user = $this->operationUser();
        $route = $this->route(minutes: 30);
        $tripDate = now()->addDay()->toDateString();

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $tripDate,
                'shuttle_route_id' => $route->id,
                'departure_time' => '23:50',
                'status' => 'Cancelled',
            ])
            ->assertRedirect('/operation/trip-schedule');

        $trip = TripSchedule::query()->firstOrFail();

        $this->assertSame('Scheduled', $trip->status);
        $this->assertSame(
            'T-'.str_pad(
                (string) $trip->id,
                3,
                '0',
                STR_PAD_LEFT
            ),
            $trip->trip_code
        );
        $this->assertSame('00:20:00', $trip->estimated_arrival_time);
        $this->assertSame(
            now()->addDays(2)->toDateString(),
            $trip->estimated_arrival_date?->toDateString()
        );
    }

    public function test_new_trip_cannot_be_scheduled_in_the_past(): void
    {
        $user = $this->operationUser();
        $route = $this->route();

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->post(route('trip-schedule.store'), [
                'trip_date' => now()->subDay()->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00',
            ])
            ->assertSessionHasErrors('trip_date')
            ->assertSessionHas('trip_validation_mode', 'create');

        $this->assertDatabaseCount('trip_schedules', 0);
    }

    public function test_cancelled_trip_does_not_block_replacement_departure(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDay()->toDateString();

        $this->trip($route, [
            'trip_code' => 'T-CANCELLED',
            'trip_date' => $date,
            'status' => 'Cancelled',
        ]);

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00',
            ])
            ->assertRedirect('/operation/trip-schedule');

        $this->assertSame(
            2,
            TripSchedule::query()
                ->whereDate('trip_date', $date)
                ->where('shuttle_route_id', $route->id)
                ->count()
        );
    }

    public function test_duplicate_validation_recovers_the_correct_create_and_edit_modal_state(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDay()->toDateString();

        $existing = $this->trip($route, [
            'trip_code' => 'T-EXISTING',
            'trip_date' => $date,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00',
            ])
            ->assertSessionHasErrors('departure_time')
            ->assertSessionHas('trip_validation_mode', 'create');

        $editable = $this->trip($route, [
            'trip_code' => 'T-EDITABLE',
            'trip_date' => $date,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->put(route('trip-schedule.update', $editable), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00',
                'status' => 'Scheduled',
            ])
            ->assertSessionHasErrors('departure_time')
            ->assertSessionHas('trip_validation_mode', 'edit')
            ->assertSessionHas('trip_validation_id', $editable->id)
            ->assertSessionHas('trip_validation_code', 'T-EDITABLE');

        $this->assertSame(
            '08:00:00',
            $existing->fresh()->departure_time
        );
    }

    public function test_same_route_departures_reject_fourteen_minutes_and_accept_fifteen_minutes(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDay()->toDateString();

        $this->trip($route, [
            'trip_code' => 'T-INTERVAL-BASE',
            'trip_date' => $date,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:30:00',
        ]);

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:14',
            ])
            ->assertSessionHasErrors('departure_time');

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:15',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trip_schedules', [
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:15:00',
        ]);
    }

    public function test_ready_or_assigned_trip_cannot_be_edited_from_trip_schedule(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $trip = $this->trip($route, [
            'trip_code' => 'T-READY',
            'status' => 'Ready',
            'assignment_status' => 'Assigned',
        ]);

        $this->actingAs($user)
            ->put(route('trip-schedule.update', $trip), [
                'trip_date' => now()->addDays(2)->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => '09:00',
                'status' => 'Scheduled',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHas('error');

        $trip->refresh();

        $this->assertSame('Ready', $trip->status);
        $this->assertSame('Assigned', $trip->assignment_status);
        $this->assertSame('08:00:00', $trip->departure_time);
    }

    public function test_departed_unassigned_trip_without_history_can_be_edited_and_deleted(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $pastDeparture = now()->subHour()->startOfMinute();
        $pastArrival = $pastDeparture->copy()->addMinutes(30);

        $trip = $this->trip($route, [
            'trip_code' => 'T-MISSED',
            'trip_date' => $pastDeparture->toDateString(),
            'departure_time' => $pastDeparture->format('H:i:s'),
            'estimated_arrival_time' => $pastArrival->format('H:i:s'),
            'estimated_arrival_date' => $pastArrival->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('trip-schedule'))
            ->assertOk()
            ->assertSee('data-id="'.$trip->id.'"', false)
            ->assertSee('data-allow-past="true"', false);

        $correctedDeparture = $pastDeparture
            ->copy()
            ->addMinutes(10);

        $this->actingAs($user)
            ->put(route('trip-schedule.update', $trip), [
                'trip_date' => $correctedDeparture->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => $correctedDeparture->format('H:i'),
                'status' => 'Scheduled',
                'notes' => 'Corrected missed schedule.',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $trip->refresh();

        $this->assertSame(
            $correctedDeparture->format('H:i:s'),
            $trip->departure_time
        );
        $this->assertSame(
            'Corrected missed schedule.',
            $trip->notes
        );

        $this->actingAs($user)
            ->delete(route('trip-schedule.destroy', $trip))
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionMissing('error');

        $this->assertSoftDeleted('trip_schedules', [
            'id' => $trip->id,
        ]);
    }

    public function test_cancelled_trips_are_excluded_from_trip_schedule_kpis(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $today = now()->toDateString();

        $this->trip($route, [
            'trip_code' => 'T-ACTIVE',
            'trip_date' => $today,
        ]);

        $this->trip($route, [
            'trip_code' => 'T-CANCELLED',
            'trip_date' => $today,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:30:00',
            'status' => 'Cancelled',
        ]);

        $this->actingAs($user)
            ->get(route('trip-schedule'))
            ->assertOk()
            ->assertViewHas('totalTripsToday', 1)
            ->assertViewHas('pendingAssignments', 1)
            ->assertViewHas('activeRoutesUsed', 1);
    }

    public function test_trip_with_operational_history_cannot_be_deleted(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $pastDeparture = now()->subHour()->startOfMinute();
        $pastArrival = $pastDeparture->copy()->addMinutes(30);

        $trip = $this->trip($route, [
            'trip_code' => 'T-HISTORY',
            'trip_date' => $pastDeparture->toDateString(),
            'departure_time' => $pastDeparture->format('H:i:s'),
            'estimated_arrival_time' => $pastArrival->format('H:i:s'),
            'estimated_arrival_date' => $pastArrival->toDateString(),
        ]);

        Incident::create([
            'incident_no' => 'INC-TRIP-HISTORY',
            'trip_schedule_id' => $trip->id,
            'incident_type' => 'Traffic Delay',
            'location' => 'Test Location',
            'description' => 'Historical trip event.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('trip-schedule.update', $trip), [
                'trip_date' => $pastDeparture->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => $pastDeparture->format('H:i'),
                'status' => 'Scheduled',
                'notes' => 'Should remain unchanged.',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->delete(route('trip-schedule.destroy', $trip))
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('trip_schedules', [
            'id' => $trip->id,
        ]);
    }

    public function test_trip_code_is_not_reused_after_latest_trip_is_deleted(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDay()->toDateString();

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '07:00',
            ])
            ->assertRedirect();

        $first = TripSchedule::query()->firstOrFail();
        $this->assertSame(
            'T-'.str_pad(
                (string) $first->id,
                3,
                '0',
                STR_PAD_LEFT
            ),
            $first->trip_code
        );

        $this->actingAs($user)
            ->delete(route('trip-schedule.destroy', $first))
            ->assertRedirect();

        $this->assertSoftDeleted('trip_schedules', [
            'id' => $first->id,
        ]);

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00',
            ])
            ->assertRedirect();

        $second = TripSchedule::query()->firstOrFail();

        $this->assertGreaterThan($first->id, $second->id);
        $this->assertSame(
            'T-'.str_pad(
                (string) $second->id,
                3,
                '0',
                STR_PAD_LEFT
            ),
            $second->trip_code
        );
        $this->assertNotSame($first->trip_code, $second->trip_code);
    }

    public function test_departed_trip_cannot_be_assigned_or_have_its_assignment_changed(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $pastDate = now()->subDay()->toDateString();

        Driver::firstOrCreate(
            ['driver_id' => 'D-HIST-001'],
            [
                'driver_name' => 'Historical Driver',
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]
        );

        $driver = DriverAttendance::create([
            'driver_name' => 'Historical Driver',
            'shift' => 'Morning',
            'attendance_date' => $pastDate,
            'status' => 'Present',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-HIST-001',
            'plate_no' => 'HIS-1001',
            'bus_model' => 'Historical Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $trip = $this->trip($route, [
            'trip_code' => 'T-HISTORICAL-ASSIGN',
            'trip_date' => $pastDate,
            'estimated_arrival_date' => $pastDate,
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $driver->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors('trip_schedule_id');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);

        $assignment = TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $driver->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $trip->update([
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->put(route('driver-bus-assignment.update', $assignment), [
                'driver_attendance_id' => $driver->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors('trip_schedule_id');

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->delete(route('driver-bus-assignment.destroy', $assignment))
            ->assertSessionHasErrors('trip_schedule_id');

        $this->assertDatabaseHas('trip_assignments', [
            'id' => $assignment->id,
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_operation_edit_permission_is_enforced_in_ui_and_backend(): void
    {
        $user = $this->operationUser('staff');
        $route = $this->route();

        $permission = RolePermission::query()
            ->where('role_key', 'operation_staff')
            ->firstOrFail();

        $matrix = $permission->permissions;
        data_set($matrix, 'operation.view', true);
        data_set($matrix, 'operation.edit', false);
        $permission->update([
            'permissions' => $matrix,
        ]);

        $this->actingAs($user)
            ->get(route('trip-schedule'))
            ->assertOk()
            ->assertDontSeeText('New Trip')
            ->assertDontSee('id="tripModal"', false);

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => now()->addDay()->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => '07:00',
            ])
            ->assertForbidden();
    }

    public function test_trip_schedule_markup_supports_ajax_filters_and_realtime_regions(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php'
            )
        );

        $echo = file_get_contents(
            resource_path('js/echo.js')
        );

        $assignmentView = file_get_contents(
            resource_path(
                'views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php'
            )
        );

        $autoSchedulingView = file_get_contents(
            resource_path(
                'views/Operation/Scheduling_And_Dispatch/auto-dispatch.blade.php'
            )
        );

        $tripRecordsView = file_get_contents(
            resource_path(
                'views/Operation/Trip_Records/trip-records.blade.php'
            )
        );

        $dailyDriverReportsView = file_get_contents(
            resource_path(
                'views/Operation/Daily_Driver_Reports/index.blade.php'
            )
        );

        $assignmentJs = file_get_contents(
            resource_path(
                'js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js'
            )
        );

        $autoSchedulingJs = file_get_contents(
            resource_path(
                'js/Operation/Scheduling_And_Dispatch/auto-scheduling.js'
            )
        );

        $tripRecordsJs = file_get_contents(
            resource_path(
                'js/Operation/Trip_Records/trip-records.js'
            )
        );

        $this->assertStringContainsString(
            'data-server-filter="true"',
            $view
        );

        $this->assertStringContainsString(
            'data-ajax-region="summary"',
            $view
        );

        $this->assertStringContainsString(
            'data-ajax-region="records"',
            $view
        );

        $this->assertStringContainsString(
            "'Operation:TripSchedule'",
            $echo
        );

        foreach (
            [
                $assignmentView,
                $autoSchedulingView,
                $tripRecordsView,
                $dailyDriverReportsView,
            ] as $realtimeView
        ) {
            $this->assertStringContainsString(
                'data-ajax-region="summary"',
                $realtimeView
            );
        }

        foreach (
            [
                $assignmentView,
                $tripRecordsView,
                $dailyDriverReportsView,
            ] as $recordsView
        ) {
            $this->assertStringContainsString(
                'data-ajax-region="records"',
                $recordsView
            );
        }

        foreach ([$autoSchedulingJs, $tripRecordsJs] as $realtimeJs) {
            $this->assertStringContainsString(
                'system-regions-refreshed',
                $realtimeJs
            );

            $this->assertStringContainsString(
                'gct:navigation-ready',
                $realtimeJs
            );
        }

        foreach (
            [
                ".closest('.open-assignment')",
                ".closest('.edit-assignment')",
                ".closest('.view-assignment')",
                ".closest('.remove-assignment')",
            ] as $delegatedSelector
        ) {
            $this->assertStringContainsString(
                $delegatedSelector,
                $assignmentJs
            );
        }

        $this->assertStringNotContainsString(
            "querySelectorAll('.view-assignment')",
            $assignmentJs
        );
        $this->assertStringContainsString(
            "'Cancelled', 'Missed'",
            $assignmentView
        );
        $this->assertStringContainsString(
            'name="return_trip_date"',
            $assignmentView
        );
        $this->assertStringContainsString(
            'data-server-filter-navigation="true"',
            $assignmentView
        );
        $this->assertStringContainsString(
            'data-ajax-region="assignment-modal"',
            $assignmentView
        );
        $this->assertStringContainsString(
            "regions.includes('assignment-modal')",
            $assignmentJs
        );
    }

    public function test_assignment_index_uses_selected_date_and_excludes_allocated_resources(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $selectedDate = now()->addDays(2)->toDateString();
        $otherDate = now()->addDays(3)->toDateString();

        $unassigned = $this->trip($route, [
            'trip_code' => 'T-SELECTED-OPEN',
            'trip_date' => $selectedDate,
            'estimated_arrival_date' => $selectedDate,
        ]);
        $assigned = $this->trip($route, [
            'trip_code' => 'T-SELECTED-READY',
            'trip_date' => $selectedDate,
            'estimated_arrival_date' => $selectedDate,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:30:00',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        $otherTrip = $this->trip($route, [
            'trip_code' => 'T-OTHER-DATE',
            'trip_date' => $otherDate,
            'estimated_arrival_date' => $otherDate,
        ]);

        [, $busyAttendance, $busyBus] = $this->assignmentResources(
            $selectedDate,
            'SELECTED-BUSY'
        );
        [, $freeAttendance, $freeBus] = $this->assignmentResources(
            $selectedDate,
            'SELECTED-FREE'
        );
        [, $otherAttendance] = $this->assignmentResources(
            $otherDate,
            'OTHER-DATE'
        );

        TripAssignment::create([
            'trip_schedule_id' => $assigned->id,
            'driver_attendance_id' => $busyAttendance->id,
            'driver_id' => $busyAttendance->driver_id,
            'driver_name' => $busyAttendance->driver_name,
            'bus_id' => $busyBus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('driver-bus-assignment', [
                'trip_date' => $selectedDate,
            ]))
            ->assertOk()
            ->assertViewHas('selectedTripDate', $selectedDate)
            ->assertViewHas('scheduledTripsForDate', 2)
            ->assertViewHas('unassignedTrips', function ($trips) use (
                $unassigned,
                $otherTrip
            ): bool {
                return $trips->contains('id', $unassigned->id)
                    && ! $trips->contains('id', $otherTrip->id);
            })
            ->assertViewHas('availableDrivers', function ($drivers) use (
                $busyAttendance,
                $freeAttendance,
                $otherAttendance
            ): bool {
                $ids = $drivers->getCollection()->pluck('id');

                return ! $ids->contains($busyAttendance->id)
                    && $ids->contains($freeAttendance->id)
                    && ! $ids->contains($otherAttendance->id);
            })
            ->assertViewHas('availableBuses', function ($buses) use (
                $busyBus,
                $freeBus
            ): bool {
                $ids = $buses->getCollection()->pluck('id');

                return ! $ids->contains($busyBus->id)
                    && $ids->contains($freeBus->id);
            });
    }

    public function test_assignment_mutations_preserve_selected_filters_and_pages(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $trip = $this->trip($route, ['trip_code' => 'T-CONTEXT']);
        $date = $trip->trip_date->toDateString();
        [, $firstAttendance, $firstBus] = $this->assignmentResources(
            $date,
            'CONTEXT-ONE'
        );
        [, $secondAttendance, $secondBus] = $this->assignmentResources(
            $date,
            'CONTEXT-TWO'
        );
        $context = [
            'return_trip_date' => $date,
            'return_search' => 'T-CONTEXT',
            'return_status' => 'Assigned',
            'trip_page' => 2,
            'driver_page' => 3,
            'bus_page' => 4,
        ];
        $expectedUrl = route('driver-bus-assignment', [
            'trip_date' => $date,
            'search' => 'T-CONTEXT',
            'status' => 'Assigned',
            'trip_page' => 2,
            'driver_page' => 3,
            'bus_page' => 4,
        ]);

        $this->actingAs($user)
            ->post(route('driver-bus-assignment.store'), array_merge($context, [
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $firstAttendance->id,
                'bus_id' => $firstBus->id,
            ]))
            ->assertRedirect($expectedUrl);

        $assignment = TripAssignment::query()->whereBelongsTo($trip)->firstOrFail();

        $this->actingAs($user)
            ->put(
                route('driver-bus-assignment.update', $assignment),
                array_merge($context, [
                    'driver_attendance_id' => $secondAttendance->id,
                    'bus_id' => $secondBus->id,
                ])
            )
            ->assertRedirect($expectedUrl);

        $this->actingAs($user)
            ->delete(
                route('driver-bus-assignment.destroy', $assignment),
                $context
            )
            ->assertRedirect($expectedUrl);
    }

    public function test_duplicate_assignment_submission_creates_only_one_record(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $trip = $this->trip($route, ['trip_code' => 'T-DUPLICATE-ASSIGN']);
        [, $attendance, $bus] = $this->assignmentResources(
            $trip->trip_date->toDateString(),
            'DUPLICATE'
        );
        $payload = [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $bus->id,
        ];

        $this->actingAs($user)
            ->post(route('driver-bus-assignment.store'), $payload)
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), $payload)
            ->assertSessionHasErrors('trip_schedule_id');

        $this->assertSame(
            1,
            TripAssignment::query()
                ->where('trip_schedule_id', $trip->id)
                ->count()
        );
    }

    public function test_assignment_linked_to_daily_driver_report_cannot_be_changed_or_removed(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $trip = $this->trip($route, [
            'trip_code' => 'T-DDR-HISTORY',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        $date = $trip->trip_date->toDateString();
        [, $attendance, $bus] = $this->assignmentResources(
            $date,
            'DDR-HISTORY'
        );
        [, $replacementAttendance, $replacementBus] = $this->assignmentResources(
            $date,
            'DDR-REPLACEMENT'
        );
        $assignment = TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $attendance->driver_id,
            'driver_name' => $attendance->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        DB::table('daily_driver_reports')->insert([
            'ddr_no' => 'DDR-ASSIGNMENT-HISTORY',
            'report_date' => $date,
            'driver_id' => $attendance->driver_id,
            'driver_name' => $attendance->driver_name,
            'bus_id' => $bus->id,
            'trip_schedule_id' => null,
            'trip_assignment_id' => $assignment->id,
            'trip_ticket' => 'TICKET-HISTORY',
            'from_location' => 'Terminal A',
            'to_location' => 'Terminal B',
            'departure_time' => '08:00:00',
            'arrival_time' => '08:30:00',
            'passengers' => 10,
            'encoded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->put(route('driver-bus-assignment.update', $assignment), [
                'driver_attendance_id' => $replacementAttendance->id,
                'bus_id' => $replacementBus->id,
            ])
            ->assertSessionHasErrors('trip_schedule_id');

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->delete(route('driver-bus-assignment.destroy', $assignment))
            ->assertSessionHasErrors('trip_schedule_id');

        $this->assertDatabaseHas('trip_assignments', [
            'id' => $assignment->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $bus->id,
        ]);
    }

    public function test_missed_filter_returns_only_departed_unassigned_trips(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'Asia/Manila'));

        try {
            $user = $this->operationUser();
            $route = $this->route();
            $missed = $this->trip($route, [
                'trip_code' => 'T-MISSED-FILTER',
                'trip_date' => '2026-10-09',
                'estimated_arrival_date' => '2026-10-09',
                'departure_time' => '09:00:00',
                'estimated_arrival_time' => '09:30:00',
            ]);
            $future = $this->trip($route, [
                'trip_code' => 'T-FUTURE-FILTER',
                'trip_date' => '2026-10-09',
                'estimated_arrival_date' => '2026-10-09',
                'departure_time' => '11:00:00',
                'estimated_arrival_time' => '11:30:00',
            ]);

            $this->actingAs($user)
                ->get(route('driver-bus-assignment', [
                    'trip_date' => '2026-10-09',
                    'status' => 'Missed',
                ]))
                ->assertOk()
                ->assertViewHas('trips', function ($trips) use (
                    $missed,
                    $future
                ): bool {
                    $ids = $trips->getCollection()->pluck('id');

                    return $ids->contains($missed->id)
                        && ! $ids->contains($future->id);
                });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_assignment_rejects_inactive_and_wrong_shift_drivers(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $trip = $this->trip($route);
        $date = $trip->trip_date->toDateString();
        $bus = Bus::create([
            'bus_no' => 'BUS-ELIGIBILITY',
            'plate_no' => 'ELG-1001',
            'bus_model' => 'Eligibility Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $inactive = Driver::create([
            'driver_id' => 'D-INACTIVE',
            'driver_name' => 'Inactive Assignment Driver',
            'shift' => 'Morning',
            'employment_status' => 'Inactive',
        ]);
        $inactiveAttendance = DriverAttendance::create([
            'driver_name' => $inactive->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $inactiveAttendance->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors('driver_attendance_id');

        $nightDriver = Driver::create([
            'driver_id' => 'D-NIGHT',
            'driver_name' => 'Night Assignment Driver',
            'shift' => 'Night',
            'employment_status' => 'Active',
        ]);
        $nightAttendance = DriverAttendance::create([
            'driver_name' => $nightDriver->driver_name,
            'shift' => 'Night',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $nightAttendance->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors('driver_attendance_id');

        $this->assertDatabaseMissing('trip_assignments', [
            'trip_schedule_id' => $trip->id,
        ]);
    }

    public function test_trip_availability_is_date_shift_active_and_conflict_aware(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now(config('app.business_timezone'))->addDay()->toDateString();
        $target = $this->trip($route, [
            'trip_code' => 'T-AVAIL-TARGET',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
        ]);
        $overlap = $this->trip($route, [
            'trip_code' => 'T-AVAIL-OVERLAP',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'departure_time' => '08:30:00',
            'estimated_arrival_time' => '09:30:00',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);

        $busyDriver = Driver::create([
            'driver_id' => 'D-BUSY',
            'driver_name' => 'Busy Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $busyAttendance = DriverAttendance::create([
            'driver_name' => $busyDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $freeDriver = Driver::create([
            'driver_id' => 'D-FREE',
            'driver_name' => 'Free Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $freeAttendance = DriverAttendance::create([
            'driver_name' => $freeDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Late',
        ]);
        $inactiveDriver = Driver::create([
            'driver_id' => 'D-HIDDEN',
            'driver_name' => 'Hidden Driver',
            'shift' => 'Morning',
            'employment_status' => 'Inactive',
        ]);
        DriverAttendance::create([
            'driver_name' => $inactiveDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);

        $busyBus = Bus::create([
            'bus_no' => 'BUS-BUSY', 'plate_no' => 'BSY-1001',
            'bus_model' => 'Busy Bus', 'capacity' => 40, 'status' => 'Active',
        ]);
        $freeBus = Bus::create([
            'bus_no' => 'BUS-FREE', 'plate_no' => 'FRE-1001',
            'bus_model' => 'Free Bus', 'capacity' => 40, 'status' => 'Active',
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $overlap->id,
            'driver_attendance_id' => $busyAttendance->id,
            'driver_id' => $busyAttendance->driver_id,
            'driver_name' => $busyAttendance->driver_name,
            'bus_id' => $busyBus->id,
            'assigned_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('driver-bus-assignment.availability', $target))
            ->assertOk();

        $this->assertSame([$freeAttendance->id], $response->json('drivers.*.id'));
        $this->assertSame([$freeBus->id], $response->json('buses.*.id'));
    }

    public function test_overlap_returns_field_validation_errors(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now(config('app.business_timezone'))->addDay()->toDateString();
        $existingTrip = $this->trip($route, [
            'trip_code' => 'T-CONFLICT-ONE',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        $newTrip = $this->trip($route, [
            'trip_code' => 'T-CONFLICT-TWO',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'departure_time' => '08:15:00',
            'estimated_arrival_time' => '08:45:00',
        ]);
        $driver = Driver::create([
            'driver_id' => 'D-CONFLICT',
            'driver_name' => 'Conflict Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $bus = Bus::create([
            'bus_no' => 'BUS-CONFLICT', 'plate_no' => 'CNF-1001',
            'bus_model' => 'Conflict Bus', 'capacity' => 40, 'status' => 'Active',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $existingTrip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $attendance->driver_id,
            'driver_name' => $attendance->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $newTrip->id,
                'driver_attendance_id' => $attendance->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors(['driver_attendance_id', 'bus_id']);
    }

    public function test_trip_schedule_uses_manila_date_for_kpis_and_new_trip_validation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 16:30:00', 'UTC'));

        try {
            $user = $this->operationUser();
            $route = $this->route();

            $this->trip($route, [
                'trip_code' => 'T-MANILA-TODAY',
                'trip_date' => '2026-10-09',
                'estimated_arrival_date' => '2026-10-09',
            ]);
            $this->trip($route, [
                'trip_code' => 'T-UTC-YESTERDAY',
                'trip_date' => '2026-10-08',
                'estimated_arrival_date' => '2026-10-08',
            ]);

            $this->actingAs($user)
                ->get(route('trip-schedule'))
                ->assertOk()
                ->assertViewHas('totalTripsToday', 1);

            $this->actingAs($user)
                ->from(route('trip-schedule'))
                ->post(route('trip-schedule.store'), [
                    'trip_date' => '2026-10-08',
                    'shuttle_route_id' => $route->id,
                    'departure_time' => '23:59',
                ])
                ->assertSessionHasErrors('trip_date');

            $this->actingAs($user)
                ->from(route('trip-schedule'))
                ->post(route('trip-schedule.store'), [
                    'trip_date' => '2026-10-09',
                    'shuttle_route_id' => $route->id,
                    'departure_time' => '00:15',
                ])
                ->assertSessionHasErrors('departure_time');

            $this->actingAs($user)
                ->post(route('trip-schedule.store'), [
                    'trip_date' => '2026-10-09',
                    'shuttle_route_id' => $route->id,
                    'departure_time' => '00:45',
                ])
                ->assertRedirect('/operation/trip-schedule')
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('trip_schedules', [
                'trip_date' => '2026-10-09',
                'departure_time' => '00:45:00',
                'shift' => 'Night',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_operational_departure_checks_use_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 16:30:00', 'UTC'));

        try {
            $route = $this->route();
            $past = $this->trip($route, [
                'trip_code' => 'T-MANILA-PAST',
                'trip_date' => '2026-10-08',
                'estimated_arrival_date' => '2026-10-09',
                'departure_time' => '23:59:00',
                'estimated_arrival_time' => '00:20:00',
            ]);
            $future = $this->trip($route, [
                'trip_code' => 'T-MANILA-FUTURE',
                'trip_date' => '2026-10-09',
                'estimated_arrival_date' => '2026-10-09',
                'departure_time' => '00:45:00',
                'estimated_arrival_time' => '01:15:00',
            ]);

            $this->assertTrue($past->hasDeparted());
            $this->assertFalse($future->hasDeparted());
            $this->assertSame(
                [$future->id],
                TripSchedule::query()->notDeparted()->pluck('id')->all()
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
