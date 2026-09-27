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

    public function test_unplanned_breakdown_preserves_trip_and_reassigns_only_the_bus(): void
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

        $incident = Incident::create([
            'incident_no' => 'INC-BRK-0001',
            'trip_schedule_id' => $trip->id,
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
            ->post(route('incidents.dispatch', $incident), [
                'replacement_bus_id' => $replacementBus->id,
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

        $this->assertDatabaseHas('incident_replacements', [
            'incident_id' => $incident->id,
            'original_bus_id' => $originalBus->id,
            'replacement_bus_id' => $replacementBus->id,
        ]);
    }

    public function test_unplanned_breakdown_context_flows_from_referral_into_job_order(): void
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
            'location' => 'Malvar Highway',
            'description' => 'Brake warning appeared during the active trip.',
            'incident_reported_at' => now(),
            'status' => 'Responding',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertRedirect();

        $referral = MaintenanceReferral::query()->where('incident_id', $incident->id)->firstOrFail();

        $this->assertSame('Pending', $referral->status);
        $this->assertStringContainsString('Unplanned breakdown', (string) $referral->notes);
        $this->assertStringContainsString($trip->trip_code, (string) $referral->notes);

        $this->actingAs($maintenanceHead)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();

        $referral->refresh();

        $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral))
            ->assertRedirect();

        $jobOrder = JobOrder::query()->where('incident_id', $incident->id)->firstOrFail();

        $this->assertSame($referral->id, $jobOrder->maintenance_referral_id);
        $this->assertSame('Repair', $jobOrder->maintenance_type);
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertStringContainsString('[Unplanned Breakdown]', $jobOrder->problem_issue);
        $this->assertStringContainsString($trip->trip_code, $jobOrder->problem_issue);
        $this->assertStringContainsString($route->route_name, $jobOrder->problem_issue);
        $this->assertSame('Under Maintenance', $bus->fresh()->status);
    }
}
