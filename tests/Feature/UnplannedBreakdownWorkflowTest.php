<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnplannedBreakdownWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unplanned_breakdown_preserves_trip_driver_and_route_when_replacing_bus(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $originalBus = Bus::create([
            'bus_no' => 'BUS-BRK-001',
            'plate_no' => 'BRK-1001',
            'bus_model' => 'Original Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $replacementBus = Bus::create([
            'bus_no' => 'BUS-SPARE-001',
            'fleet_purpose' => 'Reserve',
            'plate_no' => 'SPR-1001',
            'bus_model' => 'Spare Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $driver = Driver::firstOrCreate(
            ['driver_id' => 'DRV-BRK-001'],
            [
                'driver_name' => 'Breakdown Driver',
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]
        );

        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-BRK-001',
            'route_name' => 'Batangas to Santo Tomas',
            'origin' => 'Batangas',
            'destination' => 'Santo Tomas',
            'distance_km' => 42,
            'estimated_time_minutes' => 70,
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-BRK-001',
            'trip_date' => now()->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:10:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $operationHead->id,
        ]);

        $assignment = TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $originalBus->id,
            'assigned_by' => $operationHead->id,
        ]);

        $this->assertSame($originalBus->id, $assignment->bus_id);
        $this->assertSame($driver->driver_id, $assignment->driver_id);
        $this->assertSame($driver->driver_name, $assignment->driver_name);

        $incident = Incident::create([
            'incident_no' => 'INC-BRK-0001',
            'trip_schedule_id' => $trip->id,
            'trip_assignment_id' => $assignment->id,
            'bus_id' => $originalBus->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'STAR Tollway',
            'description' => 'Engine lost power while the bus was in service.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        $this->assertTrue($incident->is_unplanned_breakdown);
        $this->assertSame('Under Maintenance', $originalBus->fresh()->status);

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $trip->trip_date->toDateString()]))
            ->assertOk()
            ->assertSee('In Progress — Breakdown Reported');

        $this->actingAs($operationHead)->post(route('incidents.response', $incident), [
            'notes' => 'OIC instructed reserve deployment.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $trip->trip_date->toDateString()]))
            ->assertOk()
            ->assertSee('In Progress — Awaiting Replacement');

        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $replacementBus->id,
                'oic_authorized' => '1',
                'notes' => 'Deploying reserve unit',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $assignment->refresh();
        $trip->refresh();

        $this->assertSame($originalBus->id, $assignment->original_bus_id);
        $this->assertSame($replacementBus->id, $assignment->bus_id);
        $this->assertSame($driver->driver_id, $assignment->driver_id);
        $this->assertSame($driver->driver_name, $assignment->driver_name);
        $this->assertSame($route->id, $trip->shuttle_route_id);
        $this->assertSame('Dispatched', $trip->status);
        $this->assertSame('Under Maintenance', $originalBus->fresh()->status);

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $trip->trip_date->toDateString()]))
            ->assertOk()
            ->assertSee('In Progress — Reserve Deployed');

        $this->assertDatabaseHas('incident_replacements', [
            'incident_id' => $incident->id,
            'original_bus_id' => $originalBus->id,
            'replacement_bus_id' => $replacementBus->id,
        ]);
    }

    public function test_unplanned_breakdown_context_flows_into_referral_and_job_order(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-BRK-002',
            'plate_no' => 'BRK-1002',
            'status' => 'Active',
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-BRK-002',
            'route_name' => 'Malvar Shuttle Route',
            'origin' => 'Malvar',
            'destination' => 'Lipa',
            'distance_km' => 20,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-BRK-002',
            'trip_date' => now()->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:45:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $operationHead->id,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-BRK-0002',
            'trip_schedule_id' => $trip->id,
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Malvar',
            'description' => 'Brake pressure warning occurred while in service.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', ['incident' => $incident->incident_no]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $referral = MaintenanceReferral::query()->where('incident_id', $incident->id)->firstOrFail();

        $this->assertSame('Pending', $referral->status);
        $this->assertStringContainsString('Unplanned breakdown', (string) $referral->notes);
        $this->assertSame('Under Maintenance', $bus->fresh()->status);

        $this->actingAs($maintenanceHead)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();

        $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral))
            ->assertRedirect();

        $jobOrder = JobOrder::query()->where('incident_id', $incident->id)->firstOrFail();

        $this->assertSame($referral->id, $jobOrder->maintenance_referral_id);
        $this->assertSame('Repair', $jobOrder->maintenance_type);
        $this->assertStringContainsString('[Unplanned Breakdown]', $jobOrder->problem_issue);
        $this->assertStringContainsString('T-BRK-002', $jobOrder->problem_issue);
        $this->assertStringContainsString('Malvar Shuttle Route', $jobOrder->problem_issue);
        $this->assertSame('Under Maintenance', $bus->fresh()->status);
    }

    public function test_scenario_a_and_g_ready_trip_restores_readiness_and_syncs_across_pages_after_reserve_deployment(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $originalBus = Bus::create([
            'bus_no' => 'BUS-SCEN-A-ORIG',
            'plate_no' => 'SCA-1001',
            'bus_model' => 'Hino RK8J',
            'capacity' => 45,
            'fleet_purpose' => 'Regular',
            'status' => 'Active',
        ]);

        $reserveBus = Bus::create([
            'bus_no' => 'BUS-SCEN-A-RES',
            'plate_no' => 'SCA-9001',
            'bus_model' => 'Yutong ZK6107',
            'capacity' => 45,
            'fleet_purpose' => 'Reserve',
            'status' => 'Active',
        ]);

        $driver = Driver::firstOrCreate(
            ['driver_id' => 'DRV-SCEN-A-001'],
            [
                'driver_name' => 'Scenario A Driver',
                'shift' => 'Night',
                'employment_status' => 'Active',
            ]
        );

        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Night',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-SCEN-A',
            'route_name' => 'Lipa to Sto Tomas Express',
            'origin' => 'Lipa',
            'destination' => 'Sto Tomas',
            'distance_km' => 28,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-SCEN-A-001',
            'trip_date' => $today,
            'shuttle_route_id' => $route->id,
            'direction' => 'Incoming',
            'required_buses' => 1,
            'departure_time' => '23:30:00',
            'estimated_arrival_time' => '23:59:00',
            'shift' => 'Night',
            'assignment_status' => 'Assigned',
            'status' => 'Ready',
            'created_by' => $operationHead->id,
        ]);

        $assignment = TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'slot_number' => 1,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $originalBus->id,
            'assigned_by' => $operationHead->id,
        ]);

        // 1. Report breakdown via IncidentController::store
        $this->actingAs($operationHead)
            ->post(route('incidents.store'), [
                'trip_schedule_id' => $trip->id,
                'trip_assignment_id' => $assignment->id,
                'bus_id' => $originalBus->id,
                'incident_type' => 'Bus Breakdown',
                'location' => 'Malvar Exit',
                'description' => 'Coolant hose burst before departure.',
            ])
            ->assertRedirect();

        $incident = Incident::where('trip_schedule_id', $trip->id)->firstOrFail();

        // Broken bus is now Under Maintenance and future trip was temporarily downgraded to Scheduled
        $this->assertSame('Under Maintenance', $originalBus->fresh()->status);
        $this->assertSame('Scheduled', $trip->fresh()->status);

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $today]))
            ->assertOk()
            ->assertSee('Breakdown Reported — OIC Review');

        // 2. Record OIC response and deploy Reserve Bus
        $this->actingAs($operationHead)
            ->post(route('incidents.response', ['incident' => $incident->incident_no]), [
                'notes' => 'OIC authorized Reserve Bus BUS-SCEN-A-RES.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $today]))
            ->assertOk()
            ->assertSee('Incident — Awaiting Replacement');

        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $reserveBus->id,
                'oic_authorized' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // 3. Verify trip readiness is restored to Ready and original bus is preserved
        $trip->refresh();
        $assignment->refresh();
        $this->assertSame('Ready', $trip->status);
        $this->assertSame('Assigned', $trip->assignment_status);
        $this->assertSame($originalBus->id, $assignment->original_bus_id);
        $this->assertSame($reserveBus->id, $assignment->bus_id);

        // 4. Verify cross-page synchronization on Driver & Bus Assignment and Print Sheet
        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment', ['trip_date' => $today]))
            ->assertOk()
            ->assertSee('BUS-SCEN-A-RES')
            ->assertSee('Reserve Deployed')
            ->assertSee('Ready — Replacement Assigned')
            ->assertSee('OIC Approved')
            ->assertDontSee('Approval Not Recorded');

        $this->actingAs($operationHead)
            ->get(route('driver-bus-assignment.print', ['trip_date' => $today]))
            ->assertOk()
            ->assertSee('BUS-SCEN-A-RES')
            ->assertSee('Reserve · OIC Approved');

        // 5. Verify Bus Availability page reflects original bus Under Maintenance and Reserve bus Available
        $this->actingAs($operationHead)
            ->get(route('bus-availability', ['search' => 'BUS-SCEN-A']))
            ->assertOk()
            ->assertSee('BUS-SCEN-A-ORIG')
            ->assertSee('Under Maintenance')
            ->assertSee('BUS-SCEN-A-RES');
    }

    public function test_scenario_b_and_d_reserve_eligibility_overlap_and_driver_replacement_validation(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $route = ShuttleRoute::create([
            'route_code' => 'R-SCEN-BD',
            'route_name' => 'Tanauan to Calamba',
            'origin' => 'Tanauan',
            'destination' => 'Calamba',
            'distance_km' => 22,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);

        $brokenBus = Bus::create([
            'bus_no' => 'BUS-BD-BROKEN',
            'fleet_purpose' => 'Regular',
            'status' => 'Active',
        ]);

        $busyReserveBus = Bus::create([
            'bus_no' => 'BUS-BD-RES-BUSY',
            'fleet_purpose' => 'Reserve',
            'status' => 'Active',
        ]);

        $joReserveBus = Bus::create([
            'bus_no' => 'BUS-BD-RES-JO',
            'fleet_purpose' => 'Reserve',
            'status' => 'Active',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-2026-BD01',
            'bus_no' => $joReserveBus->bus_no,
            'problem_issue' => 'Pending brake inspection',
            'maintenance_type' => 'Repair',
            'start_date' => now(),
            'status' => 'On Hold',
            'part_status' => 'No Parts Required',
        ]);

        $freeReserveBus = Bus::create([
            'bus_no' => 'BUS-BD-RES-FREE',
            'fleet_purpose' => 'Reserve',
            'status' => 'Active',
        ]);

        $originalDriver = Driver::create([
            'driver_id' => 'DRV-BD-ORIG',
            'driver_name' => 'Original Breakdown Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $originalAttendance = DriverAttendance::create([
            'driver_name' => $originalDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);

        $busyDriver = Driver::create([
            'driver_id' => 'DRV-BD-BUSY',
            'driver_name' => 'Busy Overlapping Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $busyAttendance = DriverAttendance::create([
            'driver_name' => $busyDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);

        $absentDriver = Driver::create([
            'driver_id' => 'DRV-BD-ABSENT',
            'driver_name' => 'Absent Replacement Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $absentAttendance = DriverAttendance::create([
            'driver_name' => $absentDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Absent',
        ]);

        $eligibleReplacementDriver = Driver::create([
            'driver_id' => 'DRV-BD-REPL',
            'driver_name' => 'Eligible Relief Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $eligibleReplacementAttendance = DriverAttendance::create([
            'driver_name' => $eligibleReplacementDriver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => $today,
            'status' => 'Present',
        ]);

        // Overlapping active trip using $busyReserveBus and $busyDriver
        $overlappingTrip = TripSchedule::create([
            'trip_code' => 'T-BD-OVERLAP',
            'trip_date' => $today,
            'shuttle_route_id' => $route->id,
            'departure_time' => '10:15:00',
            'estimated_arrival_time' => '11:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $operationHead->id,
        ]);
        TripAssignment::create([
            'trip_schedule_id' => $overlappingTrip->id,
            'slot_number' => 1,
            'driver_attendance_id' => $busyAttendance->id,
            'driver_id' => $busyDriver->driver_id,
            'driver_name' => $busyDriver->driver_name,
            'bus_id' => $busyReserveBus->id,
            'assigned_by' => $operationHead->id,
        ]);

        // Affected trip (10:00 - 10:45 overlaps 10:15 - 11:00)
        $affectedTrip = TripSchedule::create([
            'trip_code' => 'T-BD-AFFECTED',
            'trip_date' => $today,
            'shuttle_route_id' => $route->id,
            'departure_time' => '10:00:00',
            'estimated_arrival_time' => '10:45:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $operationHead->id,
        ]);
        $affectedAssignment = TripAssignment::create([
            'trip_schedule_id' => $affectedTrip->id,
            'slot_number' => 1,
            'driver_attendance_id' => $originalAttendance->id,
            'driver_id' => $originalDriver->driver_id,
            'driver_name' => $originalDriver->driver_name,
            'bus_id' => $brokenBus->id,
            'assigned_by' => $operationHead->id,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-BD-0001',
            'trip_schedule_id' => $affectedTrip->id,
            'trip_assignment_id' => $affectedAssignment->id,
            'bus_id' => $brokenBus->id,
            'driver_id' => $originalDriver->driver_id,
            'driver_name' => $originalDriver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'SLEX Calamba',
            'description' => 'Transmission lockup mid-trip.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        // Active relief drivers are eligible without DDR attendance; busy drivers and unavailable buses remain excluded.
        $this->actingAs($operationHead)
            ->get(route('incidents.show', ['incident' => $incident->incident_no]))
            ->assertOk()
            ->assertSee('BUS-BD-RES-FREE')
            ->assertDontSee('BUS-BD-RES-BUSY')
            ->assertDontSee('BUS-BD-RES-JO')
            ->assertSee('Eligible Relief Driver')
            ->assertDontSee('Busy Overlapping Driver')
            ->assertSee('Absent Replacement Driver');

        $this->actingAs($operationHead)
            ->post(route('incidents.response', ['incident' => $incident->incident_no]), [
                'notes' => 'OIC approved reserve bus deployment and relief driver.',
            ])
            ->assertSessionHasNoErrors();

        // Reject overlapping Reserve Bus
        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $busyReserveBus->id,
                'oic_authorized' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'The selected bus has an overlapping trip assignment at this time.');

        // Reject Reserve Bus with unresolved Job Order
        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $joReserveBus->id,
                'oic_authorized' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'The selected replacement bus has an unresolved Maintenance Job Order.');

        // Historical Absent status does not block an Active driver under
        // GCT's assumed-available scheduling policy. DDR verifies actual
        // attendance after the trip, not before replacement dispatch.

        // Reject Overlapping replacement driver
        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $freeReserveBus->id,
                'replacement_driver_attendance_id' => $busyAttendance->id,
                'oic_authorized' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'The selected replacement driver has an overlapping trip assignment at this time.');

        // Case 2: Dispatch with eligible replacement driver
        $this->actingAs($operationHead)
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $freeReserveBus->id,
                'replacement_driver_attendance_id' => $eligibleReplacementAttendance->id,
                'oic_authorized' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $affectedAssignment->refresh();
        $incident->refresh();

        // TripAssignment updated to replacement Reserve Bus and replacement driver
        $this->assertSame($brokenBus->id, $affectedAssignment->original_bus_id);
        $this->assertSame($freeReserveBus->id, $affectedAssignment->bus_id);
        $this->assertSame($eligibleReplacementDriver->driver_id, $affectedAssignment->driver_id);
        $this->assertSame($eligibleReplacementDriver->driver_name, $affectedAssignment->driver_name);

        // Incident preserves original driver and original bus history
        $this->assertSame($originalDriver->driver_id, $incident->driver_id);
        $this->assertSame($originalDriver->driver_name, $incident->driver_name);
        $this->assertSame($brokenBus->id, $incident->bus_id);
    }
}
