<?php

namespace Tests\Feature;

use App\Events\SystemDataUpdated;
use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\ClientTripTimeConfiguration;
use App\Models\Operation\ClientTripTimeSlot;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\OperationClient;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        $busIds = Bus::query()->pluck('id')->all();
        Driver::query()->each(fn (Driver $d) => $d->authorizedBuses()->syncWithoutDetaching($busIds));

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
                'direction' => 'Incoming',
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

    public function test_configured_client_departure_accepts_only_an_enabled_slot(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $tripDate = now()->addDay()->toDateString();
        $client = OperationClient::create([
            'name' => 'Configured Trip Client',
            'status' => 'Active',
        ]);
        $configuration = ClientTripTimeConfiguration::create([
            'operation_client_id' => $client->id,
            'category' => 'incoming_am',
            'start_time' => '06:00:00',
            'end_time' => '10:00:00',
            'is_active' => true,
        ]);

        ClientTripTimeSlot::create([
            'client_trip_time_configuration_id' => $configuration->id,
            'slot_time' => '07:00:00',
            'is_enabled' => true,
        ]);

        $payload = [
            'trip_date' => $tripDate,
            'shuttle_route_id' => $route->id,
            'client_name' => $client->name,
            'direction' => 'Incoming',
            'required_buses' => 1,
        ];

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->post(route('trip-schedule.store'), $payload + [
                'departure_time' => '07:00',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->from(route('trip-schedule'))
            ->post(route('trip-schedule.store'), $payload + [
                'departure_time' => '08:00',
            ])
            ->assertRedirect(route('trip-schedule'))
            ->assertSessionHasErrors('departure_time');

        $this->assertSame(1, TripSchedule::query()->count());
    }

    public function test_trip_schedule_persists_incoming_service_bus_demand_and_filters_direction(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDays(2)->toDateString();

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '06:00',
                'direction' => 'Incoming',
                'required_buses' => 3,
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHasNoErrors();

        $trip = TripSchedule::query()->firstOrFail();
        $this->assertSame('Incoming', $trip->direction);
        $this->assertSame(3, $trip->required_buses);
        $this->assertSame('Unassigned', $trip->assignment_status);

        $this->actingAs($user)
            ->get(route('trip-schedule', [
                'trip_date' => $date,
                'direction' => 'Incoming',
            ]))
            ->assertOk()
            ->assertSee($trip->trip_code);

        $this->actingAs($user)
            ->get(route('trip-schedule', [
                'trip_date' => $date,
                'direction' => 'Outgoing',
            ]))
            ->assertOk()
            ->assertDontSee($trip->trip_code);
    }

    public function test_direction_dashboard_groups_records_and_counts_required_buses(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now()->addDays(2)->toDateString();

        $incoming = $this->trip($route, [
            'trip_date' => $date,
            'direction' => 'Incoming',
            'required_buses' => 3,
        ]);
        $outgoing = $this->trip($route, [
            'trip_date' => $date,
            'direction' => 'Outgoing',
            'required_buses' => 2,
            'departure_time' => '17:00:00',
            'estimated_arrival_time' => '17:30:00',
        ]);

        $response = $this->actingAs($user)
            ->get(route('trip-schedule', ['trip_date' => $date]))
            ->assertOk()
            ->assertViewHas('scheduledServices', 2)
            ->assertViewHas('incomingServices', 1)
            ->assertViewHas('outgoingServices', 1)
            ->assertViewHas('totalBusesRequired', 5)
            ->assertSee('Incoming Trips')
            ->assertSee('Outgoing Trips')
            ->assertSee($incoming->trip_code)
            ->assertSee($outgoing->trip_code);

        $this->assertStringContainsString('3', $response->getContent());
    }

    public function test_trip_schedule_rejects_invalid_direction_and_bus_quantity(): void
    {
        $user = $this->operationUser();
        $route = $this->route();

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => now()->addDays(2)->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => '06:00',
                'direction' => 'Other',
                'required_buses' => 0,
            ])
            ->assertSessionHasErrors(['direction', 'required_buses']);

        $this->assertDatabaseCount('trip_schedules', 0);
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
                'direction' => 'Incoming',
            ])
            ->assertSessionHasErrors('trip_date')
            ->assertSessionHas('trip_validation_mode', 'create');

        $this->assertDatabaseCount('trip_schedules', 0);
    }

    public function test_selected_trip_date_is_shared_and_updated_after_trip_creation(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $selectedDate = now()->addDays(2)->toDateString();
        $createdDate = now()->addDays(3)->toDateString();

        $this->actingAs($user)
            ->get(route('trip-schedule', ['trip_date' => $selectedDate]))
            ->assertOk()
            ->assertSessionHas('operation.selected_trip_date', $selectedDate);

        $this->get(route('driver-bus-assignment'))
            ->assertOk()
            ->assertViewHas('selectedTripDate', $selectedDate);

        $this->post(route('trip-schedule.store'), [
            'trip_date' => $createdDate,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00',
            'direction' => 'Incoming',
        ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHas('operation.selected_trip_date', $createdDate);
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
                'direction' => 'Incoming',
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
            'direction' => 'Incoming',
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
                'direction' => 'Incoming',
            ])
            ->assertSessionHasErrors('departure_time')
            ->assertSessionHas('trip_validation_mode', 'create');

        $editable = $this->trip($route, [
            'trip_code' => 'T-EDITABLE',
            'direction' => 'Incoming',
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
            'direction' => 'Incoming',
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
                'direction' => 'Incoming',
            ])
            ->assertSessionHasErrors('departure_time');

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:15',
                'direction' => 'Incoming',
            ])
            ->assertRedirect('/operation/trip-schedule')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trip_schedules', [
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:15:00',
        ]);
    }

    public function test_opposite_directions_can_depart_together_but_same_direction_cannot(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now(config('app.business_timezone', 'Asia/Manila'))->addDay()->toDateString();

        $this->trip($route, [
            'trip_code' => 'T-INCOMING-BASE',
            'trip_date' => $date,
            'direction' => 'Incoming',
            'departure_time' => '08:00:00',
        ]);

        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'direction' => 'Outgoing',
            'departure_time' => '08:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('trip-schedule.store'), [
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'direction' => 'Incoming',
            'departure_time' => '08:14',
        ])->assertSessionHasErrors('departure_time');
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

    public function test_departed_unassigned_trip_is_read_only_and_cannot_be_deleted(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $departure = now(config('app.business_timezone', 'Asia/Manila'))->subHour()->startOfMinute();
        $trip = $this->trip($route, [
            'trip_code' => 'T-MISSED',
            'direction' => 'Incoming',
            'trip_date' => $departure->toDateString(),
            'departure_time' => $departure->format('H:i:s'),
            'estimated_arrival_time' => $departure->copy()->addMinutes(30)->format('H:i:s'),
        ]);

        $this->assertFalse($trip->canBeManagedFromSchedule());
        $this->actingAs($user)
            ->put(route('trip-schedule.update', $trip), [
                'trip_date' => $departure->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => $departure->format('H:i'),
                'status' => 'Scheduled',
            ])
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->delete(route('trip-schedule.destroy', $trip))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('trip_schedules', ['id' => $trip->id, 'deleted_at' => null]);
    }

    public function test_cancelled_trips_are_excluded_from_trip_schedule_kpis(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

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
        $date = now(config('app.business_timezone', 'Asia/Manila'))
            ->addDay()
            ->toDateString();

        $this->actingAs($user)
            ->post(route('trip-schedule.store'), [
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '07:00',
                'direction' => 'Incoming',
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
                'direction' => 'Incoming',
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
        ).file_get_contents(
            resource_path(
                'views/components/operation/trip-history-layout.blade.php'
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

    public function test_configured_departure_slots_hide_the_enhanced_manual_time_trigger(): void
    {
        $pickerJs = file_get_contents(
            resource_path('js/Main-js/date-time-picker.js')
        );
        $pickerCss = file_get_contents(
            resource_path('css/Main-styles/date-time-picker.css')
        );
        $tripCss = file_get_contents(
            resource_path(
                'css/Operation/Scheduling_And_Dispatch/trip-schedule.css'
            )
        );
        $tripView = file_get_contents(
            resource_path(
                'views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php'
            )
        );
        $tripJs = file_get_contents(
            resource_path(
                'js/Operation/Scheduling_And_Dispatch/trip-schedule.js'
            )
        );

        $this->assertStringContainsString(
            'trigger.hidden = input.hidden;',
            $pickerJs
        );
        $this->assertStringContainsString(
            "attributeFilter: ['disabled', 'hidden', 'value']",
            $pickerJs
        );
        $this->assertStringContainsString(
            '.gct-picker-trigger[hidden]',
            $pickerCss
        );
        $this->assertStringContainsString(
            '.gct-picker-trigger[data-picker-for="departureTime"]',
            $tripCss
        );
        $this->assertStringContainsString(
            '<select id="departureTimeSlot" disabled hidden>',
            $tripView
        );
        $this->assertStringNotContainsString(
            '<select name="departure_time" id="departureTimeSlot"',
            $tripView
        );
        $this->assertStringContainsString(
            "arrivalTime.dispatchEvent(new Event('input', { bubbles: true }))",
            $tripJs
        );
    }

    public function test_assignment_index_uses_selected_date_and_keeps_reusable_resources_visible(): void
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
                $ids = $drivers->getCollection()->pluck('driver_id');

                // Attendance dates do not filter Active drivers for planning.
                return $ids->contains($busyAttendance->driver_id)
                    && $ids->contains($freeAttendance->driver_id)
                    && $ids->contains($otherAttendance->driver_id);
            })
            ->assertViewHas('availableBuses', function ($buses) use (
                $busyBus,
                $freeBus
            ): bool {
                $ids = $buses->getCollection()->pluck('id');

                return $ids->contains($busyBus->id)
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

    public function test_overdue_unassigned_trips_are_persisted_as_missed_without_changing_terminal_trips(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'Asia/Manila'));

        try {
            Event::fake([SystemDataUpdated::class]);

            $route = $this->route();
            $overdue = $this->trip($route, [
                'trip_code' => 'T-OVERDUE-UNASSIGNED',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
            ]);
            $future = $this->trip($route, [
                'trip_code' => 'T-FUTURE-UNASSIGNED',
                'trip_date' => '2026-10-10',
                'estimated_arrival_date' => '2026-10-10',
            ]);
            $cancelled = $this->trip($route, [
                'trip_code' => 'T-CANCELLED-OLDER',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
                'status' => 'Cancelled',
            ]);
            $completed = $this->trip($route, [
                'trip_code' => 'T-COMPLETED-OLDER',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
                'status' => 'Completed',
            ]);
            $dispatched = $this->trip($route, [
                'trip_code' => 'T-DISPATCHED-OLDER',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
                'status' => 'Dispatched',
            ]);
            $assigned = $this->trip($route, [
                'trip_code' => 'T-ASSIGNMENT-HISTORY',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
            ]);
            [, $attendance, $bus] = $this->assignmentResources(
                '2026-10-07',
                'MISSED-PROTECTED'
            );
            TripAssignment::create([
                'trip_schedule_id' => $assigned->id,
                'driver_attendance_id' => $attendance->id,
                'driver_id' => 'D-MISSED-PROTECTED',
                'driver_name' => $attendance->driver_name,
                'bus_id' => $bus->id,
            ]);
            $incidentTrip = $this->trip($route, [
                'trip_code' => 'T-INCIDENT-HISTORY',
                'trip_date' => '2026-10-07',
                'estimated_arrival_date' => '2026-10-07',
            ]);
            Incident::create([
                'incident_no' => 'INC-MISSED-PROTECTED',
                'trip_schedule_id' => $incidentTrip->id,
                'incident_type' => 'Traffic Delay',
                'location' => 'Test Location',
                'description' => 'Operational history must remain protected.',
                'incident_reported_at' => now(),
                'status' => 'Reported',
            ]);

            $this->artisan('operation:mark-missed-trips')->assertSuccessful();

            $this->assertSame('Missed', $overdue->fresh()->status);
            $this->assertSame('Scheduled', $future->fresh()->status);
            $this->assertSame('Cancelled', $cancelled->fresh()->status);
            $this->assertSame('Completed', $completed->fresh()->status);
            $this->assertSame('Dispatched', $dispatched->fresh()->status);
            $this->assertSame('Scheduled', $assigned->fresh()->status);
            $this->assertSame('Scheduled', $incidentTrip->fresh()->status);
            Event::assertDispatched(SystemDataUpdated::class, function ($event): bool {
                return $event->module === 'Operation'
                    && $event->entity === 'TripSchedule'
                    && $event->action === 'updated';
            });

            $this->artisan('operation:mark-missed-trips')->assertSuccessful();
            $this->assertSame('Missed', $overdue->fresh()->status);
            Event::assertDispatchedTimes(SystemDataUpdated::class, 1);
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

        $this->assertSame([$freeDriver->driver_id], $response->json('drivers.*.id'));
        $this->assertSame([$freeBus->id], $response->json('buses.*.id'));
    }

    public function test_resources_can_be_reused_for_a_later_non_overlapping_trip(): void
    {
        $user = $this->operationUser();
        $route = $this->route();
        $date = now(config('app.business_timezone'))->addDay()->toDateString();
        $earlierTrip = $this->trip($route, [
            'trip_code' => 'T-REUSE-EARLY',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
        ]);
        $laterTrip = $this->trip($route, [
            'trip_code' => 'T-REUSE-LATE',
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '11:00:00',
        ]);
        [, $attendance, $bus] = $this->assignmentResources($date, 'REUSE');

        TripAssignment::create([
            'trip_schedule_id' => $earlierTrip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $attendance->driver_id,
            'driver_name' => $attendance->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $availability = $this->actingAs($user)
            ->getJson(route('driver-bus-assignment.availability', $laterTrip))
            ->assertOk();

        $this->assertContains($attendance->driver_id, $availability->json('drivers.*.id'));
        $this->assertContains($bus->id, $availability->json('buses.*.id'));

        $this->actingAs($user)
            ->from(route('driver-bus-assignment', ['trip_date' => $date]))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $laterTrip->id,
                'driver_attendance_id' => $attendance->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $laterTrip->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $bus->id,
        ]);
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
        $driver->authorizedBuses()->syncWithoutDetaching([$bus->id]);
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
                    'direction' => 'Incoming',
                ])
                ->assertSessionHasErrors('trip_date');

            $this->actingAs($user)
                ->from(route('trip-schedule'))
                ->post(route('trip-schedule.store'), [
                    'trip_date' => '2026-10-09',
                    'shuttle_route_id' => $route->id,
                    'departure_time' => '00:15',
                    'direction' => 'Incoming',
                ])
                ->assertSessionHasErrors('departure_time');

            $this->actingAs($user)
                ->post(route('trip-schedule.store'), [
                    'trip_date' => '2026-10-09',
                    'shuttle_route_id' => $route->id,
                    'departure_time' => '00:45',
                    'direction' => 'Incoming',
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
