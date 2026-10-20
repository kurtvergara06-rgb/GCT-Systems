<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentReplacement;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationMultiBusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ShuttleRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->route = ShuttleRoute::create([
            'route_code' => 'R-MULTI-01',
            'route_name' => 'Tanauan to SM Terminal',
            'origin' => 'Tanauan',
            'destination' => 'SM Terminal',
            'distance_km' => 20.0,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);
    }

    private function createDriver(string $suffix, string $status = 'Active'): Driver
    {
        $driver = Driver::create([
            'driver_id' => 'DRV-' . $suffix,
            'driver_name' => 'Driver ' . $suffix,
            'shift' => 'Morning',
            'employment_status' => $status,
        ]);

        $busIds = Bus::query()->pluck('id')->all();
        if ($busIds !== []) {
            $driver->authorizedBuses()->syncWithoutDetaching($busIds);
        }

        return $driver;
    }

    private function createAttendance(Driver $driver, string $date, string $status = 'Present'): DriverAttendance
    {
        return DriverAttendance::create([
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'shift' => $driver->shift ?? 'Morning',
            'attendance_date' => $date,
            'status' => $status,
        ]);
    }

    private function createBus(string $suffix, string $status = 'Active', string $fleetPurpose = 'Regular'): Bus
    {
        $bus = Bus::create([
            'bus_no' => 'BUS-' . $suffix,
            'plate_no' => 'PLT-' . $suffix,
            'bus_model' => 'Hino Multi',
            'capacity' => 45,
            'status' => $status,
            'fleet_purpose' => $fleetPurpose,
        ]);

        Driver::query()->each(fn (Driver $driver) => $driver->authorizedBuses()->syncWithoutDetaching([$bus->id]));

        return $bus;
    }

    private function createTripSchedule(int $requiredBuses = 1, array $overrides = []): TripSchedule
    {
        $date = now(config('app.business_timezone', 'Asia/Manila'))->addDay()->toDateString();

        return TripSchedule::create(array_merge([
            'trip_code' => 'TRIP-MB-' . uniqid(),
            'trip_date' => $date,
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '08:45:00',
            'estimated_arrival_date' => $date,
            'shift' => 'Morning',
            'direction' => 'Incoming',
            'required_buses' => $requiredBuses,
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    /**
     * Test 1: One schedule requiring one bus works and marks Ready upon assignment.
     */
    public function test_01_one_schedule_requiring_one_bus_works_and_completes_when_assigned(): void
    {
        $trip = $this->createTripSchedule(1);
        $driver = $this->createDriver('T1');
        $attendance = $this->createAttendance($driver, $trip->trip_date->toDateString());
        $bus = $this->createBus('T1');

        $response = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'bus_id' => $bus->id,
        ]);

        $response->assertSessionHasNoErrors();
        $trip->refresh();

        $this->assertCount(1, $trip->assignments);
        $this->assertEquals(1, $trip->assigned_buses_count);
        $this->assertTrue($trip->is_fully_assigned);
        $this->assertEquals('Assigned', $trip->assignment_status);
        $this->assertEquals('Ready', $trip->status);
        $this->assertNotNull($trip->assignment);
        $this->assertEquals($bus->id, $trip->assignment->bus_id);
    }

    /**
     * Test 2: One schedule requiring three buses maintains independent assignments.
     */
    public function test_02_one_schedule_requiring_three_buses_supports_multiple_assignments(): void
    {
        $trip = $this->createTripSchedule(3);
        $date = $trip->trip_date->toDateString();

        $driver1 = $this->createDriver('T2-1');
        $att1 = $this->createAttendance($driver1, $date);
        $bus1 = $this->createBus('T2-1');

        $driver2 = $this->createDriver('T2-2');
        $att2 = $this->createAttendance($driver2, $date);
        $bus2 = $this->createBus('T2-2');

        $driver3 = $this->createDriver('T2-3');
        $att3 = $this->createAttendance($driver3, $date);
        $bus3 = $this->createBus('T2-3');

        // Assign 1st bus
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $att1->id,
            'bus_id' => $bus1->id,
        ]);
        $trip->refresh();
        $this->assertEquals(1, $trip->assigned_buses_count);
        $this->assertEquals('Partially Assigned', $trip->assignment_status);
        $this->assertEquals('Scheduled', $trip->status);

        // Assign 2nd bus
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $att2->id,
            'bus_id' => $bus2->id,
        ]);
        $trip->refresh();
        $this->assertEquals(2, $trip->assigned_buses_count);
        $this->assertEquals('Partially Assigned', $trip->assignment_status);
        $this->assertEquals('Scheduled', $trip->status);

        // Assign 3rd bus
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $att3->id,
            'bus_id' => $bus3->id,
        ]);
        $trip->refresh();
        $this->assertEquals(3, $trip->assigned_buses_count);
        $this->assertTrue($trip->is_fully_assigned);
        $this->assertEquals('Assigned', $trip->assignment_status);
        $this->assertEquals('Ready', $trip->status);
    }

    /**
     * Test 3: Partial assignments are tracked and never marked Ready.
     */
    public function test_03_partial_assignments_tracked_correctly_and_never_prematurely_ready(): void
    {
        $trip = $this->createTripSchedule(3);
        $date = $trip->trip_date->toDateString();

        $driver = $this->createDriver('T3');
        $att = $this->createAttendance($driver, $date);
        $bus = $this->createBus('T3');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $att->id,
            'bus_id' => $bus->id,
        ]);

        $trip->refresh();
        $this->assertTrue($trip->is_partially_assigned);
        $this->assertFalse($trip->is_fully_assigned);
        $this->assertEquals('Partially Assigned', $trip->assignment_status);
        $this->assertNotEquals('Ready', $trip->status);
        $this->assertEquals('Scheduled', $trip->status);
    }

    /**
     * Test 4: Full assignments automatically transition status to Ready and Assigned.
     */
    public function test_04_full_assignments_transition_to_ready(): void
    {
        $trip = $this->createTripSchedule(2);
        $date = $trip->trip_date->toDateString();

        $d1 = $this->createDriver('T4-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T4-1');

        $d2 = $this->createDriver('T4-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T4-2');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b1->id,
        ]);
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a2->id,
            'bus_id' => $b2->id,
        ]);

        $trip->refresh();
        $this->assertEquals(2, $trip->assigned_buses_count);
        $this->assertEquals('Assigned', $trip->assignment_status);
        $this->assertEquals('Ready', $trip->status);
    }

    /**
     * Test 5: Assignment removal recalculates status and preserves other assignments.
     */
    public function test_05_assignment_removal_recalculates_status_and_preserves_remaining_assignments(): void
    {
        $trip = $this->createTripSchedule(3);
        $date = $trip->trip_date->toDateString();

        $d1 = $this->createDriver('T5-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T5-1');

        $d2 = $this->createDriver('T5-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T5-2');

        $d3 = $this->createDriver('T5-3');
        $a3 = $this->createAttendance($d3, $date);
        $b3 = $this->createBus('T5-3');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b1->id,
        ]);
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a2->id,
            'bus_id' => $b2->id,
        ]);
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a3->id,
            'bus_id' => $b3->id,
        ]);

        $trip->refresh();
        $this->assertEquals(3, $trip->assignments()->count());
        $this->assertEquals('Ready', $trip->status);

        $asgn2 = $trip->assignments()->where('bus_id', $b2->id)->firstOrFail();

        // Remove assignment 2
        $this->actingAs($this->admin)->delete(route('driver-bus-assignment.destroy', $asgn2->id));

        $trip->refresh();
        $this->assertEquals(2, $trip->assignments()->count());
        $this->assertDatabaseMissing('trip_assignments', ['id' => $asgn2->id]);
        $this->assertDatabaseHas('trip_assignments', ['bus_id' => $b1->id]);
        $this->assertDatabaseHas('trip_assignments', ['bus_id' => $b3->id]);
        $this->assertEquals('Partially Assigned', $trip->assignment_status);
        $this->assertEquals('Scheduled', $trip->status);
    }

    /**
     * Test 6: Duplicate driver or bus prevention on same schedule.
     */
    public function test_06_duplicate_driver_or_bus_on_same_schedule_prevented(): void
    {
        $trip = $this->createTripSchedule(2);
        $date = $trip->trip_date->toDateString();

        $d1 = $this->createDriver('T6-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T6-1');

        $d2 = $this->createDriver('T6-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T6-2');

        // Slot 1
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b1->id,
        ]);

        // Attempt duplicate driver with different bus
        $response1 = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b2->id,
        ]);
        $response1->assertSessionHasErrors('driver_attendance_id');

        // Attempt duplicate bus with different driver
        $response2 = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a2->id,
            'bus_id' => $b1->id,
        ]);
        $response2->assertSessionHasErrors('bus_id');
    }

    /**
     * Test 7: Overlapping vs non-overlapping schedules.
     */
    public function test_07_overlapping_schedules_prevent_reuse_while_non_overlapping_allows_reuse(): void
    {
        $date = now()->addDays(2)->toDateString();
        $tripOverlap = $this->createTripSchedule(1, [
            'trip_date' => $date,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'estimated_arrival_date' => $date,
        ]);
        $tripConcurrent = $this->createTripSchedule(1, [
            'trip_date' => $date,
            'departure_time' => '08:30:00',
            'estimated_arrival_time' => '09:30:00',
            'estimated_arrival_date' => $date,
        ]);
        $tripLater = $this->createTripSchedule(1, [
            'trip_date' => $date,
            'departure_time' => '13:00:00',
            'estimated_arrival_time' => '14:00:00',
            'estimated_arrival_date' => $date,
        ]);

        $d = $this->createDriver('T7');
        $a = $this->createAttendance($d, $date);
        $b = $this->createBus('T7');

        // Assign to first trip
        $res1 = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripOverlap->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);
        $res1->assertSessionHasNoErrors();

        // Attempt concurrent/overlapping
        $response = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripConcurrent->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);
        $response->assertSessionHasErrors();

        // Assign to non-overlapping later trip
        $responseLater = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripLater->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);
        $responseLater->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $tripLater->id,
            'bus_id' => $b->id,
        ]);
    }

    /**
     * Test 8: Ineligible, off-duty, or absent driver rejection.
     */
    public function test_08_absent_and_ineligible_drivers_rejected(): void
    {
        $trip = $this->createTripSchedule(1);
        $date = $trip->trip_date->toDateString();
        $bus = $this->createBus('T8');

        // Absent driver
        $dAbsent = $this->createDriver('T8-ABS');
        $aAbsent = $this->createAttendance($dAbsent, $date, 'Absent');

        $respAbsent = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $aAbsent->id,
            'bus_id' => $bus->id,
        ]);
        $respAbsent->assertSessionHasNoErrors();

        $inactiveTrip = $this->createTripSchedule(1);
        $inactiveBus = $this->createBus('T8-INACTIVE');

        // Inactive master list driver
        $dInactive = $this->createDriver('T8-INA', 'Inactive');
        $aInactive = $this->createAttendance($dInactive, $date, 'Present');

        $respInactive = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $inactiveTrip->id,
            'driver_attendance_id' => $aInactive->id,
            'bus_id' => $inactiveBus->id,
        ]);
        $respInactive->assertSessionHasErrors('driver_attendance_id');
    }

    /**
     * Test 9: Maintenance, Inactive, and Reserve buses rejected for regular assignment.
     */
    public function test_09_maintenance_inactive_and_reserve_buses_rejected(): void
    {
        $trip = $this->createTripSchedule(1);
        $date = $trip->trip_date->toDateString();
        $d = $this->createDriver('T9');
        $a = $this->createAttendance($d, $date);

        // Under Maintenance bus
        $bMaint = $this->createBus('T9-M', 'Under Maintenance');
        $respMaint = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $bMaint->id,
        ]);
        $respMaint->assertSessionHasErrors('bus_id');

        // Inactive bus
        $bInact = $this->createBus('T9-I', 'Inactive');
        $respInact = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $bInact->id,
        ]);
        $respInact->assertSessionHasErrors('bus_id');

        // Reserve bus
        $bRes = $this->createBus('T9-R', 'Active', 'Reserve');
        $respRes = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $bRes->id,
        ]);
        $respRes->assertSessionHasErrors('bus_id');
    }

    /**
     * Test 10: Auto-scheduling allocates multiple buses per schedule and handles resource exhaustion.
     */
    public function test_10_auto_scheduling_allocates_up_to_required_buses_and_handles_insufficient_resources(): void
    {
        $trip = $this->createTripSchedule(3);
        $date = $trip->trip_date->toDateString();

        // Only 2 drivers and buses available for 3 required slots
        $d1 = $this->createDriver('T10-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T10-1');

        $d2 = $this->createDriver('T10-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T10-2');

        // Generate recommendations
        $genResp = $this->actingAs($this->admin)->postJson(route('auto-scheduling.generate'), [
            'schedule_date' => $date,
        ]);
        $genResp->assertOk();
        $recommendations = $genResp->json('recommendations');
        $conflicts = $genResp->json('conflicts');

        $this->assertCount(2, $recommendations);
        $this->assertEquals($trip->id, $recommendations[0]['trip_schedule_id']);
        $this->assertEquals($trip->id, $recommendations[1]['trip_schedule_id']);
        $this->assertNotEquals($recommendations[0]['bus_id'], $recommendations[1]['bus_id']);
        $this->assertNotEquals($recommendations[0]['driver_attendance_id'], $recommendations[1]['driver_attendance_id']);

        // Confirm recommendations
        $confResp = $this->actingAs($this->admin)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => $recommendations,
            'decisions' => [[
                'trip_schedule_id' => $trip->id,
                'slot' => $conflicts[0]['slot'],
                'decision' => 'manual_later',
                'notes' => 'Assign an available company bus and driver later.'
            ]],
        ]);
        $confResp->assertOk();

        $trip->refresh();
        $this->assertEquals(2, $trip->assignments()->count());
        $this->assertEquals('Partially Assigned', $trip->assignment_status);
        $this->assertEquals('Scheduled', $trip->status);
        $this->assertNotEquals('Ready', $trip->status);
        $this->assertDatabaseHas('auto_schedule_slot_decisions', [
            'trip_schedule_id' => $trip->id,
            'slot_number' => 3,
            'decision' => 'manual_later',
            'status' => 'Pending',
        ]);
    }

    /**
     * Test 11: Protection against reducing required buses below existing assignments count.
     */
    public function test_11_cannot_reduce_required_buses_below_existing_assignments(): void
    {
        $trip = $this->createTripSchedule(3);
        $date = $trip->trip_date->toDateString();

        $d1 = $this->createDriver('T11-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T11-1');

        $d2 = $this->createDriver('T11-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T11-2');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b1->id,
        ]);
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a2->id,
            'bus_id' => $b2->id,
        ]);

        $trip->refresh();
        $this->assertEquals(2, $trip->assignments()->count());
        $this->assertEquals('Partially Assigned', $trip->assignment_status);

        // Attempt reducing required_buses to 1 (below current assignments count 2)
        $resp = $this->actingAs($this->admin)->put(route('trip-schedule.update', $trip->id), [
            'trip_date' => $date,
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '08:00',
            'required_buses' => 1,
            'direction' => 'Incoming',
            'status' => 'Scheduled',
        ]);
        $resp->assertSessionHasErrors('required_buses');

        // Reducing to 2 (matching existing assignments count) is allowed
        $resp2 = $this->actingAs($this->admin)->put(route('trip-schedule.update', $trip->id), [
            'trip_date' => $date,
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '08:00',
            'required_buses' => 2,
            'direction' => 'Incoming',
            'status' => 'Scheduled',
        ]);
        $resp2->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertEquals(2, $trip->required_buses);
        $this->assertEquals('Assigned', $trip->assignment_status);
        $this->assertEquals('Ready', $trip->status);
    }

    /**
     * Test 12: Incomplete multi-bus service cannot be marked Ready or dispatched.
     */
    public function test_12_incomplete_service_cannot_be_dispatched(): void
    {
        $trip = $this->createTripSchedule(2);
        $date = $trip->trip_date->toDateString();

        $d = $this->createDriver('T12');
        $a = $this->createAttendance($d, $date);
        $b = $this->createBus('T12');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);

        $trip->refresh();
        $this->assertFalse($trip->canBeDispatched());
        $this->assertEquals('Scheduled', $trip->status);
    }

    public function test_active_driver_can_be_assigned_before_attendance_is_verified_by_ddr(): void
    {
        $trip = $this->createTripSchedule();
        $driver = $this->createDriver('NO-DDR');
        $bus = $this->createBus('NO-DDR');

        $this->assertDatabaseMissing('driver_attendances', [
            'driver_id' => $driver->driver_id,
            'attendance_date' => $trip->trip_date->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('driver-bus-assignment.store'), [
                'trip_schedule_id' => $trip->id,
                'driver_id' => $driver->driver_id,
                'bus_id' => $bus->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trip_assignments', [
            'trip_schedule_id' => $trip->id,
            'driver_id' => $driver->driver_id,
            'bus_id' => $bus->id,
            'driver_attendance_id' => null,
        ]);
        $this->assertDatabaseMissing('driver_attendances', [
            'driver_id' => $driver->driver_id,
            'attendance_date' => $trip->trip_date->toDateString(),
        ]);
    }

    /**
     * Test 13: Incident management reserve deployment replaces only affected bus.
     */
    public function test_13_reserve_deployment_replaces_only_the_affected_bus_on_multi_bus_trip(): void
    {
        $trip = $this->createTripSchedule(2);
        $date = $trip->trip_date->toDateString();

        $d1 = $this->createDriver('T13-1');
        $a1 = $this->createAttendance($d1, $date);
        $b1 = $this->createBus('T13-1');

        $d2 = $this->createDriver('T13-2');
        $a2 = $this->createAttendance($d2, $date);
        $b2 = $this->createBus('T13-2');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a1->id,
            'bus_id' => $b1->id,
        ]);
        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a2->id,
            'bus_id' => $b2->id,
        ]);

        $trip->refresh();
        $asgn1 = $trip->assignments()->where('bus_id', $b1->id)->firstOrFail();
        $asgn2 = $trip->assignments()->where('bus_id', $b2->id)->firstOrFail();

        // Bus 2 breaks down
        $incident = Incident::create([
            'incident_no' => 'INC-T13-' . uniqid(),
            'trip_schedule_id' => $trip->id,
            'trip_assignment_id' => $asgn2->id,
            'bus_id' => $b2->id,
            'driver_name' => $d2->driver_name,
            'incident_type' => 'Bus Breakdown',
            'severity' => 'Major',
            'status' => 'Pending Replacement',
            'incident_time' => now(),
            'incident_reported_at' => now(),
            'notes' => 'Engine overheating',
        ]);

        $reserveBus = $this->createBus('T13-RES', 'Active', 'Reserve');
        $this->actingAs($this->admin)->post(route('incidents.response', $incident), [
            'notes' => 'OIC instructed the team to deploy the reserve bus.',
        ])->assertSessionHasNoErrors();

        $resp = $this->actingAs($this->admin)->post(route('incidents.dispatch', $incident), [
            'replacement_bus_id' => $reserveBus->id,
            'oic_authorized' => '1',
            'notes' => 'Deploying reserve unit',
        ]);
        $resp->assertSessionHasNoErrors();

        $asgn1->refresh();
        $asgn2->refresh();

        // Unaffected assignment 1 is untouched
        $this->assertEquals($b1->id, $asgn1->bus_id);

        // Affected assignment 2 received the reserve bus
        $this->assertEquals($reserveBus->id, $asgn2->bus_id);
    }

    /**
     * Test 14: Original bus and driver history preserved upon reserve replacement.
     */
    public function test_14_original_bus_and_driver_history_preserved_upon_reserve_replacement(): void
    {
        $trip = $this->createTripSchedule(1);
        $date = $trip->trip_date->toDateString();

        $d = $this->createDriver('T14');
        $a = $this->createAttendance($d, $date);
        $origBus = $this->createBus('T14-ORIG');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $origBus->id,
        ]);

        $trip->refresh();
        $asgn = $trip->assignments->first();

        $incident = Incident::create([
            'incident_no' => 'INC-T14-' . uniqid(),
            'trip_schedule_id' => $trip->id,
            'trip_assignment_id' => $asgn->id,
            'bus_id' => $origBus->id,
            'driver_name' => $d->driver_name,
            'incident_type' => 'Bus Breakdown',
            'severity' => 'Moderate',
            'status' => 'Pending Replacement',
            'incident_time' => now(),
            'incident_reported_at' => now(),
        ]);

        $resBus = $this->createBus('T14-RES', 'Active', 'Reserve');
        $this->actingAs($this->admin)->post(route('incidents.response', $incident), [
            'notes' => 'OIC approved the replacement bus for the affected assignment.',
        ])->assertSessionHasNoErrors();

        $resp = $this->actingAs($this->admin)->post(route('incidents.dispatch', $incident), [
            'replacement_bus_id' => $resBus->id,
            'oic_authorized' => '1',
        ]);
        $resp->assertSessionHasNoErrors();

        $replacement = IncidentReplacement::where('incident_id', $incident->id)->first();
        $this->assertNotNull($replacement);
        $this->assertEquals($origBus->id, $replacement->original_bus_id);
        $this->assertEquals($resBus->id, $replacement->replacement_bus_id);
    }

    /**
     * Test 15: Completed and cancelled schedules cannot be mutated.
     */
    public function test_15_completed_and_cancelled_schedules_protected_from_mutation(): void
    {
        $tripCompleted = $this->createTripSchedule(1, ['status' => 'Completed']);
        $tripCancelled = $this->createTripSchedule(1, ['status' => 'Cancelled']);

        $date = $tripCompleted->trip_date->toDateString();
        $d = $this->createDriver('T15');
        $a = $this->createAttendance($d, $date);
        $b = $this->createBus('T15');

        $resp1 = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripCompleted->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);
        $resp1->assertSessionHasErrors('trip_schedule_id');

        $resp2 = $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripCancelled->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);
        $resp2->assertSessionHasErrors('trip_schedule_id');
    }

    /**
     * Test 16: Unauthorized mutation attempts rejected.
     */
    public function test_16_unauthorized_mutation_attempts_rejected(): void
    {
        $staffUser = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $trip = $this->createTripSchedule(1);
        $date = $trip->trip_date->toDateString();
        $d = $this->createDriver('T16');
        $a = $this->createAttendance($d, $date);
        $b = $this->createBus('T16');

        $resp = $this->actingAs($staffUser)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);

        $resp->assertForbidden();
    }

    /**
     * Test 17: Full regression coverage for legacy single-bus relations.
     */
    public function test_17_regression_coverage_for_legacy_single_bus_workflow(): void
    {
        $trip = $this->createTripSchedule(1);
        $date = $trip->trip_date->toDateString();
        $d = $this->createDriver('T17');
        $a = $this->createAttendance($d, $date);
        $b = $this->createBus('T17');

        $this->actingAs($this->admin)->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $a->id,
            'bus_id' => $b->id,
        ]);

        $trip->refresh();

        // Verify HasOne legacy relation still functions seamlessly
        $this->assertNotNull($trip->assignment);
        $this->assertEquals($b->id, $trip->assignment->bus->id);
        $this->assertEquals($d->driver_name, $trip->assignment->driver_name);
        $this->assertTrue($trip->is_fully_assigned);
        $this->assertEquals('Assigned', $trip->assignment_status);
        $this->assertEquals('Ready', $trip->status);
    }
}
