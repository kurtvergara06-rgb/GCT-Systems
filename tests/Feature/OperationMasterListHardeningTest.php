<?php

namespace Tests\Feature;

use App\Events\SystemDataUpdated;
use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Operation\OperationClient;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OperationMasterListHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_edit_permission_protects_all_master_list_mutations_and_ui(): void
    {
        $staff = $this->operationUser('staff');
        $this->setOperationEditPermission($staff, false);
        $driver = $this->driver();
        $mechanic = $this->mechanic();
        $bus = $this->bus();

        $this->actingAs($staff)
            ->get(route('operation.personnel.drivers'))
            ->assertOk()
            ->assertDontSee('Add Driver')
            ->assertDontSee('data-personnel-action="edit"', false)
            ->assertDontSee('Deactivate Driver?', false);

        $this->actingAs($staff)
            ->get(route('operation.personnel.mechanics'))
            ->assertOk()
            ->assertDontSee('Add Mechanic')
            ->assertDontSee('data-personnel-action="edit"', false)
            ->assertDontSee('Deactivate Mechanic?', false);

        $this->actingAs($staff)
            ->get(route('bus-master-list'))
            ->assertOk()
            ->assertDontSee('id="openBusModal"', false)
            ->assertDontSee('open-edit-bus', false)
            ->assertDontSee('Deactivate Bus?', false)
            ->assertDontSee('open-delete-bus', false);

        $requests = [
            fn () => $this->post(route('operation.personnel.drivers.store'), $this->driverPayload('D-NEW', 'New Driver')),
            fn () => $this->put(route('operation.personnel.drivers.update', $driver), $this->driverPayload($driver->driver_id, $driver->driver_name)),
            fn () => $this->patch(route('operation.personnel.drivers.deactivate', $driver)),
            fn () => $this->post(route('operation.personnel.mechanics.store'), $this->mechanicPayload('M-NEW', 'New Mechanic')),
            fn () => $this->put(route('operation.personnel.mechanics.update', $mechanic), $this->mechanicPayload($mechanic->mechanic_id, $mechanic->mechanic_name)),
            fn () => $this->patch(route('operation.personnel.mechanics.deactivate', $mechanic)),
            fn () => $this->post(route('bus-master-list.store'), $this->busPayload('BUS-NEW')),
            fn () => $this->put(route('bus-master-list.update', $bus), $this->busPayload($bus->bus_no)),
            fn () => $this->patch(route('bus-master-list.deactivate', $bus)),
            fn () => $this->patch(route('bus-master-list.activate', $bus)),
            fn () => $this->delete(route('bus-master-list.destroy', $bus)),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
    }

    public function test_route_mutation_endpoints_reject_view_only_operation_users(): void
    {
        $staff = $this->operationUser('staff');
        $this->setOperationEditPermission($staff, false);
        $route = $this->route();

        $this->actingAs($staff)
            ->get(route('operation.routes'))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('operation.routes.store'), [])
            ->assertForbidden();
        $this->actingAs($staff)
            ->put(route('operation.routes.update', $route), [])
            ->assertForbidden();
        $this->actingAs($staff)
            ->delete(route('operation.routes.destroy', $route))
            ->assertForbidden();

        $this->assertDatabaseHas('shuttle_routes', ['id' => $route->id]);
    }

    public function test_personnel_ids_become_immutable_after_attendance_history_exists(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => $driver->shift,
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        $this->actingAs($user)
            ->from(route('operation.personnel.drivers'))
            ->put(
                route('operation.personnel.drivers.update', $driver),
                $this->driverPayload('D-CHANGED', $driver->driver_name)
            )
            ->assertSessionHasErrors('driver_id');

        $mechanic = $this->mechanic();
        MechanicAttendance::create([
            'mechanic_name' => $mechanic->mechanic_name,
            'shift' => $mechanic->shift,
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        $this->actingAs($user)
            ->from(route('operation.personnel.mechanics'))
            ->put(
                route('operation.personnel.mechanics.update', $mechanic),
                $this->mechanicPayload('M-CHANGED', $mechanic->mechanic_name)
            )
            ->assertSessionHasErrors('mechanic_id');

        $this->assertSame('D-001', $driver->fresh()->driver_id);
        $this->assertSame('M-001', $mechanic->fresh()->mechanic_id);
    }

    public function test_duplicate_personnel_names_are_rejected_by_validation_and_the_database(): void
    {
        $user = $this->operationUser();
        $this->driver();
        $this->mechanic();

        $this->actingAs($user)
            ->post(
                route('operation.personnel.drivers.store'),
                $this->driverPayload('D-002', 'Driver One')
            )
            ->assertSessionHasErrors('driver_name');

        $this->actingAs($user)
            ->post(
                route('operation.personnel.mechanics.store'),
                $this->mechanicPayload('M-002', 'Mechanic One')
            )
            ->assertSessionHasErrors('mechanic_name');

        $this->expectException(QueryException::class);

        Driver::query()->insert([
            'driver_id' => 'D-LEGACY',
            'driver_name' => 'driver one',
            'shift' => 'Morning',
            'employment_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_driver_cannot_be_deactivated_while_assigned_to_an_active_trip(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->addDay()->toDateString(),
            'status' => 'Present',
        ]);
        $bus = $this->bus();
        $trip = $this->trip(['status' => 'Ready', 'assignment_status' => 'Assigned']);
        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('operation.personnel.drivers'))
            ->patch(route('operation.personnel.drivers.deactivate', $driver))
            ->assertSessionHasErrors('employment_status');

        $this->assertSame('Active', $driver->fresh()->employment_status);
    }

    public function test_expired_license_is_rejected_by_assignment_options_and_transaction_validation(): void
    {
        $user = $this->operationUser();
        $date = now()->addDays(2)->toDateString();
        $driver = $this->driver([
            'license_expiration' => now()->addDay()->toDateString(),
        ]);
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $bus = $this->bus();
        $trip = $this->trip(['trip_date' => $date, 'estimated_arrival_date' => $date]);

        $this->actingAs($user)
            ->getJson(route('driver-bus-assignment.availability', $trip))
            ->assertOk()
            ->assertJsonCount(0, 'drivers');

        $this->actingAs($user)
            ->from(route('driver-bus-assignment'))
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $attendance->id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasErrors('driver_attendance_id');

        $this->assertDatabaseMissing('trip_assignments', ['trip_schedule_id' => $trip->id]);
    }

    public function test_mechanic_cannot_be_deactivated_during_an_ongoing_job_order(): void
    {
        $user = $this->operationUser();
        $mechanic = $this->mechanic();
        $bus = $this->bus();
        $this->jobOrder($bus, $mechanic->mechanic_name);

        $this->actingAs($user)
            ->from(route('operation.personnel.mechanics'))
            ->patch(route('operation.personnel.mechanics.deactivate', $mechanic))
            ->assertSessionHasErrors('employment_status');

        $this->assertSame('Active', $mechanic->fresh()->employment_status);
    }

    public function test_bus_history_blocks_rename_and_delete_and_maintenance_blocks_activation(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus();
        $this->jobOrder($bus, null);

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->put(route('bus-master-list.update', $bus), $this->busPayload('BUS-RENAMED'))
            ->assertSessionHasErrors('bus_no');

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->delete(route('bus-master-list.destroy', $bus))
            ->assertSessionHasErrors('bus');

        $bus->update(['status' => 'Under Maintenance']);

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->put(route('bus-master-list.update', $bus), $this->busPayload($bus->bus_no, 'Active'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('buses', ['id' => $bus->id, 'status' => 'Under Maintenance']);
    }

    public function test_bus_with_trip_history_cannot_be_deleted_and_csv_import_route_is_removed(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        $date = now()->addDay()->toDateString();
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $bus = $this->bus();
        $trip = $this->trip([
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'status' => 'Ready',
            'assignment_status' => 'Assigned',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->delete(route('bus-master-list.destroy', $bus))
            ->assertSessionHasErrors('bus');

        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('bus-master-list.import'));
        $this->actingAs($user)->post('/bus-master-list/import')->assertStatus(405);
    }

    public function test_bus_purpose_cannot_change_while_current_or_future_assignment_is_unresolved(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        $date = now()->addDay()->toDateString();
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $bus = $this->bus(['fleet_purpose' => 'Regular']);
        $trip = $this->trip([
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'status' => 'Ready',
            'assignment_status' => 'Assigned',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->put(route('bus-master-list.update', $bus), array_merge(
                $this->busPayload($bus->bus_no),
                ['fleet_purpose' => 'Reserve']
            ))
            ->assertSessionHasErrors('fleet_purpose');

        $this->assertSame('Regular', $bus->fresh()->fleet_purpose);

        $this->actingAs($user)
            ->get(route('bus-master-list'))
            ->assertOk()
            ->assertSee('data-fleet-purpose-locked="1"', false);

        $trip->update(['status' => 'Completed']);

        $this->actingAs($user)
            ->put(route('bus-master-list.update', $bus), array_merge(
                $this->busPayload($bus->bus_no),
                ['fleet_purpose' => 'Reserve']
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame('Reserve', $bus->fresh()->fleet_purpose);
    }

    public function test_bus_can_be_deactivated_and_reactivated_with_workflow_guards(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus(['status' => 'Active']);

        // 1. Can deactivate free bus
        $this->actingAs($user)
            ->patch(route('bus-master-list.deactivate', $bus))
            ->assertRedirect(route('bus-master-list'))
            ->assertSessionHas('success');

        $this->assertSame('Inactive', $bus->fresh()->status);

        // 2. Can reactivate inactive bus
        $this->actingAs($user)
            ->patch(route('bus-master-list.activate', $bus))
            ->assertRedirect(route('bus-master-list'))
            ->assertSessionHas('success');

        $this->assertSame('Active', $bus->fresh()->status);

        // 3. Ongoing assignment blocks deactivation
        $driver = $this->driver();
        $date = now()->addDay()->toDateString();
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $trip = $this->trip([
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'status' => 'Ready',
            'assignment_status' => 'Assigned',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('bus-master-list'))
            ->patch(route('bus-master-list.deactivate', $bus))
            ->assertSessionHasErrors('bus');

        $this->assertSame('Active', $bus->fresh()->status);
    }

    public function test_reserve_replacement_cannot_be_reclassified_during_its_active_trip(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        $date = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $date,
            'status' => 'Present',
        ]);
        $originalBus = $this->bus();
        $reserveBus = $this->bus([
            'bus_no' => 'BUS-RESERVE-002',
            'plate_no' => 'ABC-1002',
            'fleet_purpose' => 'Reserve',
        ]);
        $trip = $this->trip([
            'trip_date' => $date,
            'estimated_arrival_date' => $date,
            'status' => 'Dispatched',
            'assignment_status' => 'Assigned',
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $reserveBus->id,
            'original_bus_id' => $originalBus->id,
            'assigned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('bus-master-list.update', $reserveBus), array_merge(
                $this->busPayload($reserveBus->bus_no),
                [
                    'plate_no' => $reserveBus->plate_no,
                    'fleet_purpose' => 'Regular',
                ]
            ))
            ->assertSessionHasErrors('fleet_purpose');

        $this->assertSame('Reserve', $reserveBus->fresh()->fleet_purpose);
    }

    public function test_driver_kpis_count_active_and_inactive_drivers_independent_of_legacy_shift(): void
    {
        $user = $this->operationUser();
        $this->driver(['shift' => 'Morning']);
        $this->driver([
            'driver_id' => 'D-002',
            'driver_name' => 'Inactive Morning Driver',
            'employment_status' => 'Inactive',
            'shift' => 'Morning',
        ]);
        $this->driver([
            'driver_id' => 'D-003',
            'driver_name' => 'Active Afternoon Driver',
            'employment_status' => 'Active',
            'shift' => 'Afternoon',
        ]);

        $this->actingAs($user)
            ->get(route('operation.personnel.drivers'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 3
                && $stats['active'] === 2
                && $stats['inactive'] === 1
            );
    }

    public function test_multiple_active_drivers_can_be_authorized_for_the_same_bus(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus();
        $first = $this->driver();
        $first->authorizedBuses()->sync([$bus->id]);

        $this->actingAs($user)->post(
            route('operation.personnel.drivers.store'),
            array_merge(
                $this->driverPayload('D-002', 'Driver Two'),
                ['bus_ids_present' => 1, 'bus_ids' => [$bus->id]]
            )
        )->assertSessionHasNoErrors();

        $second = Driver::query()->where('driver_id', 'D-002')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $bus->fresh()->authorizedDrivers()->pluck('drivers.id')->all()
        );
    }

    public function test_regular_bus_allocation_can_be_cleared_back_to_unallocated(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus(['client_allocation_type' => 'Exclusive']);
        $client = OperationClient::create(['name' => 'EPSON', 'status' => 'Active']);
        $bus->operationClients()->sync([$client->id]);

        $this->actingAs($user)->put(
            route('bus-master-list.update', $bus),
            array_merge($this->busPayload($bus->bus_no), [
                'fleet_purpose' => 'Regular',
                'client_name' => '',
                'client_allocation_submitted' => 1,
            ])
        )->assertSessionHasNoErrors();

        $this->assertNull($bus->fresh()->client_allocation_type);
        $this->assertCount(0, $bus->fresh()->operationClients);
    }

    public function test_edit_validation_preserves_personnel_record_identity_and_update_action(): void
    {
        $user = $this->operationUser();
        $driver = $this->driver();
        $this->driver(['driver_id' => 'D-002', 'driver_name' => 'Second Driver']);

        $response = $this->actingAs($user)
            ->from(route('operation.personnel.drivers'))
            ->put(route('operation.personnel.drivers.update', $driver), array_merge(
                $this->driverPayload($driver->driver_id, 'Second Driver'),
                ['editing_personnel_id' => $driver->id]
            ));

        $response
            ->assertSessionHasErrors('driver_name')
            ->assertSessionHasInput('editing_personnel_id', (string) $driver->id);

        $this->followingRedirects()
            ->actingAs($user)
            ->put(route('operation.personnel.drivers.update', $driver), array_merge(
                $this->driverPayload($driver->driver_id, 'Second Driver'),
                ['editing_personnel_id' => $driver->id]
            ))
            ->assertOk()
            ->assertSee('data-open-on-error="true"', false)
            ->assertSee('name="editing_personnel_id" value="'.$driver->id.'"', false);

        $script = file_get_contents(resource_path('js/Operation/Attendance/personnel-master-modal.js'));
        $this->assertStringContainsString('[data-personnel-action="edit"][data-record-id=', $script);
        $this->assertStringContainsString('form.action = editButton.dataset.updateUrl;', $script);
    }

    public function test_personnel_mutations_broadcast_realtime_updates_to_related_pages(): void
    {
        Event::fake([SystemDataUpdated::class]);
        $user = $this->operationUser();

        $this->actingAs($user)->post(
            route('operation.personnel.drivers.store'),
            $this->driverPayload('D-REALTIME', 'Realtime Driver')
        )->assertSessionHasNoErrors();

        $this->actingAs($user)->post(
            route('operation.personnel.mechanics.store'),
            $this->mechanicPayload('M-REALTIME', 'Realtime Mechanic')
        )->assertSessionHasNoErrors();

        Event::assertDispatched(SystemDataUpdated::class, fn (SystemDataUpdated $event) => $event->module === 'Operation' && $event->entity === 'Driver');
        Event::assertDispatched(SystemDataUpdated::class, fn (SystemDataUpdated $event) => $event->module === 'Operation' && $event->entity === 'Mechanic');

        $echo = file_get_contents(resource_path('js/echo.js'));
        $this->assertStringContainsString("'Operation:Driver'", $echo);
        $this->assertStringContainsString("'Operation:Mechanic'", $echo);
    }

    public function test_bus_availability_flags_unresolved_job_orders_even_if_master_status_is_active(): void
    {
        $user = $this->operationUser();
        $restricted = $this->bus();
        $clear = $this->bus([
            'bus_no' => 'BUS-002',
            'plate_no' => 'ABC-1002',
        ]);

        $jobOrder = $this->jobOrder($restricted, null);

        $this->actingAs($user)
            ->get(route('bus-availability'))
            ->assertOk()
            ->assertViewHas('activeBuses', 1)
            ->assertViewHas('maintenanceBuses', 1)
            ->assertSee('ABC-1001')
            ->assertSee('Under Maintenance')
            ->assertSee('ABC-1002')
            ->assertSee('Available');

        $jobOrder->update(['status' => 'Completed']);

        $this->actingAs($user)
            ->get(route('bus-availability'))
            ->assertOk()
            ->assertViewHas('activeBuses', 2)
            ->assertViewHas('maintenanceBuses', 0);
    }

    public function test_bus_master_list_uses_maintenance_as_the_effective_status_for_legacy_drift(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus(['status' => 'Inactive']);
        $this->jobOrder($bus, null);

        $response = $this->actingAs($user)->get(route('bus-master-list'));

        $response->assertOk()
            ->assertViewHas('activeBuses', 0)
            ->assertViewHas('underMaintenance', 1)
            ->assertViewHas('availableBuses', 0)
            ->assertSee($bus->bus_no)
            ->assertSee('Under Maintenance')
            ->assertDontSee('aria-label="Activate bus '.$bus->bus_no.'"', false);

        $this->assertSame('Inactive', $bus->fresh()->status);
    }

    public function test_bus_availability_view_modal_is_outside_scrollable_table_and_read_only(): void
    {
        $user = $this->operationUser();
        $this->bus();

        $response = $this->actingAs($user)->get(route('bus-availability'));
        $response->assertOk()
            ->assertSee('id="availabilityBusModal"', false)
            ->assertSee('open-availability-bus')
            ->assertDontSee('open-edit-bus')
            ->assertDontSee('open-delete-bus');

        $html = $response->getContent();
        $this->assertGreaterThan(strpos($html, 'class="table-wrap availability-table-wrap"'), strpos($html, 'id="availabilityBusModal"'));
        $this->assertGreaterThan(strpos($html, '</main>'), strpos($html, 'id="availabilityBusModal"'));
    }

    public function test_bus_realtime_route_subscriptions_include_availability_and_dispatch(): void
    {
        $echo = file_get_contents(resource_path('js/echo.js'));

        foreach (['Operation:Bus', 'Operation:TripSchedule', 'Maintenance:JobOrder'] as $eventKey) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote("'".$eventKey."':", '/').'.*'.preg_quote('/operation/bus-availability', '/').'/',
                $echo
            );
        }
    }

    public function test_auto_scheduling_and_manual_bus_assignment_reject_unresolved_job_orders(): void
    {
        $manual = file_get_contents(app_path('Http/Controllers/Operation/TripAssignmentController.php'));
        $automatic = file_get_contents(app_path('Http/Controllers/Operation/AutoSchedulingController.php'));

        $this->assertStringContainsString("->where('status', '!=', 'Completed')", $manual);
        $this->assertStringContainsString('unresolved Maintenance Job Order', $manual);
        $this->assertStringContainsString("->where('status', '!=', 'Completed')", $automatic);
        $this->assertStringContainsString('unresolved Maintenance Job Order', $automatic);
    }

    public function test_assignment_page_keeps_trip_locks_and_read_only_resource_lists(): void
    {
        $user = $this->operationUser();
        $this->bus();

        $response = $this->actingAs($user)->get(route('driver-bus-assignment'));
        $response->assertOk()
            ->assertSee('Route', false)
            ->assertDontSee('Pickup → Dropoff', false);

        $template = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php'));
        $this->assertStringNotContainsString('assignment-journey', $template);
        $this->assertStringContainsString("['Cancelled', 'Dispatched', 'Completed']", $template);
        $this->assertStringContainsString('!$isLocked', $template);
        $this->assertStringNotContainsString('resource-assign-bus', $template);
        $this->assertStringNotContainsString('resource-assign-driver', $template);
        $this->assertStringNotContainsString("'Night'", $template);
    }

    public function test_unresolved_maintenance_job_order_excludes_bus_from_manual_dispatch_options(): void
    {
        $user = $this->operationUser();
        $bus = $this->bus();
        $jobOrder = $this->jobOrder($bus, null);
        $date = now()->addDays(2)->toDateString();

        $this->actingAs($user)
            ->get(route('driver-bus-assignment', ['trip_date' => $date]))
            ->assertOk()
            ->assertViewHas('availableBuses', fn ($buses) => $buses->total() === 0);

        $jobOrder->update(['status' => 'Completed']);

        $this->actingAs($user)
            ->get(route('driver-bus-assignment', ['trip_date' => $date]))
            ->assertOk()
            ->assertViewHas('availableBuses', fn ($buses) => $buses->total() === 1);
    }

    public function test_mechanic_pages_are_listed_in_maintenance_navigation_and_attendance_uses_module_permissions(): void
    {
        $operationUser = $this->operationUser();
        $maintenanceUser = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($operationUser)
            ->get(route('dashboard-operation'))
            ->assertOk()
            ->assertDontSee('Mechanic Attendance')
            ->assertDontSee('Mechanic Master List');

        // Preserve the legacy Operation master-list URL while the visible navigation is moved.
        $this->actingAs($operationUser)
            ->get(route('operation.personnel.mechanics'))
            ->assertOk();

        $this->actingAs($maintenanceUser)
            ->get(route('maintenance-dashboard'))
            ->assertOk()
            ->assertSee('Mechanic Availability')
            ->assertSee('Mechanic Master List')
            ->assertSee('Mechanic Attendance');

        $this->actingAs($maintenanceUser)
            ->get(route('maintenance.personnel.mechanics'))
            ->assertOk()
            ->assertSee('Mechanic Master List');

        $this->actingAs($maintenanceUser)
            ->get(route('mechanic-list'))
            ->assertOk();

        $this->actingAs($maintenanceUser)
            ->get(route('job-orders.available-mechanics'))
            ->assertOk();

        $this->actingAs($maintenanceUser)
            ->get(route('mechanic-attendance'))
            ->assertOk();

        $operationPermission = RolePermission::query()
            ->where('role_key', $operationUser->permissionRoleKey())
            ->firstOrFail();
        $operationPermissions = $operationPermission->permissions;
        data_set($operationPermissions, 'operation.view', false);
        $operationPermission->update(['permissions' => $operationPermissions]);

        $this->actingAs($operationUser)
            ->get(route('mechanic-attendance'))
            ->assertForbidden();

        $maintenancePermission = RolePermission::query()
            ->where('role_key', $maintenanceUser->permissionRoleKey())
            ->firstOrFail();
        $maintenancePermissions = $maintenancePermission->permissions;
        data_set($maintenancePermissions, 'maintenance.view', false);
        $maintenancePermission->update(['permissions' => $maintenancePermissions]);

        $this->actingAs($maintenanceUser)
            ->get(route('mechanic-attendance'))
            ->assertForbidden();

        $this->actingAs($maintenanceUser)
            ->get(route('maintenance.personnel.mechanics'))
            ->assertForbidden();
    }

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

    private function setOperationEditPermission(User $user, bool $enabled): void
    {
        $permission = RolePermission::query()
            ->where('role_key', $user->permissionRoleKey())
            ->firstOrFail();
        $permissions = $permission->permissions;
        data_set($permissions, 'operation.view', true);
        data_set($permissions, 'operation.edit', $enabled);
        $permission->update(['permissions' => $permissions]);
    }

    private function driver(array $overrides = []): Driver
    {
        return Driver::create(array_merge([
            'driver_id' => 'D-001',
            'driver_name' => 'Driver One',
            'shift' => 'Morning',
            'license_number' => 'LIC-001',
            'license_expiration' => now()->addYear()->toDateString(),
            'employment_status' => 'Active',
        ], $overrides));
    }

    private function mechanic(array $overrides = []): Mechanic
    {
        return Mechanic::create(array_merge([
            'mechanic_id' => 'M-001',
            'mechanic_name' => 'Mechanic One',
            'shift' => 'Morning',
            'specialization' => 'Engine',
            'employment_status' => 'Active',
        ], $overrides));
    }

    private function bus(array $overrides = []): Bus
    {
        return Bus::create(array_merge([
            'bus_no' => 'BUS-001',
            'plate_no' => 'ABC-1001',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ], $overrides));
    }

    private function route(): ShuttleRoute
    {
        return ShuttleRoute::create([
            'route_code' => 'R-MASTER',
            'route_name' => 'Master Test Route',
            'origin' => 'A',
            'origin_latitude' => 14.1,
            'origin_longitude' => 121.1,
            'destination' => 'B',
            'destination_latitude' => 14.2,
            'destination_longitude' => 121.2,
            'distance_km' => 10,
            'estimated_time_minutes' => 30,
            'status' => 'Active',
        ]);
    }

    private function trip(array $overrides = []): TripSchedule
    {
        $date = now()->addDay()->toDateString();

        return TripSchedule::create(array_merge([
            'trip_code' => 'T-MASTER-'.(TripSchedule::count() + 1),
            'trip_date' => $date,
            'shuttle_route_id' => $this->route()->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:30:00',
            'estimated_arrival_date' => $date,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ], $overrides));
    }

    private function jobOrder(Bus $bus, ?string $mechanic): JobOrder
    {
        return JobOrder::create([
            'job_order_no' => 'JO-MASTER-'.(JobOrder::count() + 1),
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Test maintenance',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => $mechanic,
            'status' => 'On Going',
        ]);
    }

    private function driverPayload(string $id, string $name): array
    {
        return [
            'driver_id' => $id,
            'driver_name' => $name,
            'shift' => 'Morning',
            'license_number' => 'LIC-'.$id,
            'license_expiration' => now()->addYear()->toDateString(),
            'employment_status' => 'Active',
        ];
    }

    private function mechanicPayload(string $id, string $name): array
    {
        return [
            'mechanic_id' => $id,
            'mechanic_name' => $name,
            'shift' => 'Morning',
            'specialization' => 'Engine',
            'employment_status' => 'Active',
        ];
    }

    private function busPayload(string $busNo, string $status = 'Active'): array
    {
        return [
            'bus_no' => $busNo,
            'plate_no' => 'ABC-1001',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => $status,
        ];
    }
}
