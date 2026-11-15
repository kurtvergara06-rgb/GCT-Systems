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
use App\Services\Operation\DailyDriverReportScheduleMatchService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationTripIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Driver $driver;
    private Bus $bus;
    private ShuttleRoute $route;
    private DriverAttendance $attendance;
    private TripSchedule $schedule;
    private TripAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
        ]);

        $this->driver = Driver::firstOrCreate(
            ['driver_id' => 'D-INTEGRITY-001'],
            [
                'driver_name' => 'Integrity Driver',
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]
        );

        $this->bus = Bus::create([
            'bus_no' => 'BUS-INTEGRITY-001',
            'plate_no' => 'INT-1001',
            'bus_model' => 'Integrity Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $this->route = ShuttleRoute::create([
            'route_code' => 'R-INT-001',
            'route_name' => 'Integrity Route',
            'origin' => 'Batangas',
            'destination' => 'Lipa',
            'distance_km' => 25,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);

        $this->attendance = DriverAttendance::create([
            'driver_name' => $this->driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => '2026-09-22',
            'status' => 'Present',
        ]);

        $this->schedule = TripSchedule::create([
            'trip_code' => 'GCT-INT-001',
            'trip_date' => '2026-09-22',
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '05:00:00',
            'estimated_arrival_time' => '06:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Scheduled',
            'created_by' => $this->user->id,
        ]);

        $this->assignment = TripAssignment::create([
            'trip_schedule_id' => $this->schedule->id,
            'driver_attendance_id' => $this->attendance->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_new_ddr_persists_exact_schedule_and_assignment_links(): void
    {
        $report = $this->createReport();
        $report->refresh();

        $this->assertSame($this->schedule->id, $report->trip_schedule_id);
        $this->assertSame($this->assignment->id, $report->trip_assignment_id);
        $this->assertTrue($report->tripSchedule->is($this->schedule));
        $this->assertTrue($report->tripAssignment->is($this->assignment));
    }

    public function test_matcher_prefers_persisted_direct_schedule_link(): void
    {
        $report = $this->createReport();

        $otherSchedule = TripSchedule::create([
            'trip_code' => 'GCT-INT-002',
            'trip_date' => '2026-09-22',
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '05:31:00',
            'estimated_arrival_time' => '06:31:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Scheduled',
            'created_by' => $this->user->id,
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $otherSchedule->id,
            'driver_attendance_id' => $this->attendance->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'assigned_by' => $this->user->id,
        ]);

        $matched = app(DailyDriverReportScheduleMatchService::class)
            ->match($report->fresh());

        $this->assertNotNull($matched);
        $this->assertSame($this->schedule->id, $matched->id);
    }

    public function test_replacement_preserves_original_bus_and_updates_effective_bus(): void
    {
        $incident = $this->createIncident();

        $replacementBus = Bus::create([
            'bus_no' => 'BUS-INTEGRITY-REPL',
            'plate_no' => 'INT-2002',
            'bus_model' => 'Replacement Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        DB::transaction(function () use ($incident, $replacementBus): void {
            IncidentReplacement::create([
                'incident_id' => $incident->id,
                'original_bus_id' => $this->bus->id,
                'replacement_bus_id' => $replacementBus->id,
                'dispatched_at' => now(),
                'dispatched_by' => $this->user->id,
            ]);
        });

        $this->assignment->refresh();

        $this->assertSame($this->bus->id, $this->assignment->original_bus_id);
        $this->assertSame($replacementBus->id, $this->assignment->bus_id);
    }

    public function test_reopening_resolved_incident_clears_resolution_metadata(): void
    {
        $incident = $this->createIncident();

        $incident->update([
            'status' => 'Resolved',
            'resolved_at' => now(),
            'resolved_by' => $this->user->id,
        ]);

        $this->assertNotNull($incident->fresh()->resolved_at);

        $incident->update(['status' => 'Monitoring']);
        $incident->refresh();

        $this->assertNull($incident->resolved_at);
        $this->assertNull($incident->resolved_by);
    }

    public function test_database_prevents_duplicate_replacement_for_same_incident(): void
    {
        $incident = $this->createIncident();

        $replacementBus = Bus::create([
            'bus_no' => 'BUS-INTEGRITY-REPL-A',
            'plate_no' => 'INT-3003',
            'status' => 'Active',
        ]);

        $secondBus = Bus::create([
            'bus_no' => 'BUS-INTEGRITY-REPL-B',
            'plate_no' => 'INT-4004',
            'status' => 'Active',
        ]);

        IncidentReplacement::create([
            'incident_id' => $incident->id,
            'original_bus_id' => $this->bus->id,
            'replacement_bus_id' => $replacementBus->id,
            'dispatched_at' => now(),
            'dispatched_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);

        IncidentReplacement::create([
            'incident_id' => $incident->id,
            'original_bus_id' => $this->bus->id,
            'replacement_bus_id' => $secondBus->id,
            'dispatched_at' => now(),
            'dispatched_by' => $this->user->id,
        ]);
    }

    public function test_delay_export_does_not_leak_incident_from_another_explicit_trip(): void
    {
        $this->createReport();

        $otherSchedule = TripSchedule::create([
            'trip_code' => 'GCT-OTHER-001',
            'trip_date' => '2026-09-22',
            'shuttle_route_id' => $this->route->id,
            'departure_time' => '07:00:00',
            'estimated_arrival_time' => '08:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Scheduled',
            'created_by' => $this->user->id,
        ]);

        $otherAssignment = TripAssignment::create([
            'trip_schedule_id' => $otherSchedule->id,
            'driver_attendance_id' => $this->attendance->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'assigned_by' => $this->user->id,
        ]);

        Incident::create([
            'incident_no' => 'INC-OTHER-0001',
            'trip_schedule_id' => $otherSchedule->id,
            'trip_assignment_id' => $otherAssignment->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'incident_type' => 'Traffic',
            'location' => 'Other Trip',
            'incident_reported_at' => '2026-09-22 04:30:00',
            'status' => 'Reported',
            'reported_by' => $this->user->id,
        ]);

        $path = storage_path('framework/testing/genuine-delay-integrity.csv');

        $this->artisan('delay:export-genuine', ['--path' => $path])
            ->assertExitCode(0);

        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
        $header = array_shift($rows);
        $row = array_combine($header, $rows[0]);

        $this->assertSame('GCT-INT-001', $row['trip_code']);
        $this->assertSame('0', $row['incident_before_count']);
        $this->assertSame('0', $row['incident_traffic_count']);

        @unlink($path);
        @unlink($path.'.meta.json');
    }

    private function createReport(): DailyDriverReport
    {
        return DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-9001',
            'report_date' => '2026-09-22',
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'GCT-INT-001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:30:00',
            'arrival_time' => '06:30:00',
            'passengers' => 20,
            'encoded_by' => $this->user->id,
        ]);
    }

    public function test_scheduled_trip_lifecycle_advances_by_time_without_manual_confirmation(): void
    {
        $this->schedule->update(['status' => 'Ready']);

        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 04:59:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Ready', $this->schedule->fresh()->status);

        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 05:30:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Dispatched', $this->schedule->fresh()->status);
        $firstDispatchUpdatedAt = $this->schedule->fresh()->updated_at->toDateTimeString();

        // Running again before ETA must not count, save or notify the same transition.
        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 05:31:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')
            ->expectsOutput('Updated 0 trip statuses.')
            ->assertSuccessful();
        $this->assertSame('Dispatched', $this->schedule->fresh()->status);
        $this->assertSame($firstDispatchUpdatedAt, $this->schedule->fresh()->updated_at->toDateTimeString());

        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 06:01:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Pending DDR', $this->schedule->fresh()->status);

        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Pending DDR', $this->schedule->fresh()->status);
    }

    public function test_scheduled_lifecycle_does_not_override_cancelled_trips(): void
    {
        $this->schedule->update(['status' => 'Cancelled']);
        $this->travelTo(\Carbon\Carbon::parse('2026-09-23 07:00:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Cancelled', $this->schedule->fresh()->status);
    }

    public function test_breakdown_keeps_trip_on_hold_after_eta_until_verified_continued(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 05:30:00', 'Asia/Manila'));
        $this->schedule->update(['status' => 'Dispatched']);
        $incident = $this->createIncident();

        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 06:05:00', 'Asia/Manila'));
        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Dispatched', $this->schedule->fresh()->status);
        $this->assertTrue(app(\App\Services\Operation\TripDdrLifecycleService::class)
            ->isAssignmentOnIncidentHold($this->assignment));

        $incident->update([
            'status' => 'Resolved',
            'resolution_notes' => '[Trip outcome: Continued] Operations verified service continued.',
        ]);
        app(\App\Services\Operation\TripDdrLifecycleService::class)
            ->releaseAfterIncident($this->schedule->id);

        $this->assertSame('Pending DDR', $this->schedule->fresh()->status);
        $this->assertFalse(app(\App\Services\Operation\TripDdrLifecycleService::class)
            ->isAssignmentOnIncidentHold($this->assignment));
    }

    public function test_interrupted_breakdown_does_not_release_after_eta(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 06:05:00', 'Asia/Manila'));
        $this->schedule->update(['status' => 'Dispatched']);
        $incident = $this->createIncident();
        $incident->update([
            'status' => 'Resolved',
            'resolution_notes' => '[Trip outcome: Interrupted] Service was unable to continue.',
        ]);
        app(\App\Services\Operation\TripDdrLifecycleService::class)
            ->releaseAfterIncident($this->schedule->id);

        $this->artisan('operation:advance-trip-statuses')->assertSuccessful();
        $this->assertSame('Dispatched', $this->schedule->fresh()->status);
        $this->assertTrue(app(\App\Services\Operation\TripDdrLifecycleService::class)
            ->isAssignmentOnIncidentHold($this->assignment));
    }

    public function test_only_breakdown_affected_bus_slot_is_held(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-22 06:05:00', 'Asia/Manila'));
        $this->schedule->update(['status' => 'Dispatched', 'required_buses' => 2]);
        $healthyBus = Bus::create([
            'bus_no' => 'BUS-INTEGRITY-HEALTHY',
            'plate_no' => 'INT-6006',
            'status' => 'Active',
        ]);
        $healthyDriver = Driver::create([
            'driver_id' => 'D-INTEGRITY-HEALTHY',
            'driver_name' => 'Healthy Bus Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $healthy = TripAssignment::create([
            'trip_schedule_id' => $this->schedule->id,
            'driver_attendance_id' => null,
            'driver_id' => $healthyDriver->driver_id,
            'driver_name' => $healthyDriver->driver_name,
            'bus_id' => $healthyBus->id,
            'slot_number' => 2,
            'assigned_by' => $this->user->id,
        ]);
        $incident = $this->createIncident();
        $lifecycle = app(\App\Services\Operation\TripDdrLifecycleService::class);
        $this->assertTrue($lifecycle->isAssignmentOnIncidentHold($this->assignment));
        $this->assertFalse($lifecycle->isAssignmentOnIncidentHold($healthy));

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.schedule-lookup', [
                'report_date' => '2026-09-22',
                'driver_id' => $healthyDriver->driver_id,
                'pending_only' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'schedules')
            ->assertJsonPath('schedules.0.bus_id', $healthyBus->id);
    }

    private function createIncident(): Incident
    {
        return Incident::create([
            'incident_no' => 'INC-INTEGRITY-0001',
            'trip_schedule_id' => $this->schedule->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Integrity Test Location',
            'description' => 'Test breakdown.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $this->user->id,
        ]);
    }
}
