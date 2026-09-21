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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportGenuineDelayDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Driver $driver;

    private Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->driver = Driver::firstOrCreate(
            ['driver_id' => 'D-2026-0001'],
            ['driver_name' => 'Test Driver', 'shift' => 'Morning', 'employment_status' => 'Active']
        );
        $this->bus = Bus::create([
            'bus_no' => 'BUS-001',
            'plate_no' => 'ABC-1234',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
    }

    public function test_exports_only_valid_matched_genuine_rows_with_incident_context(): void
    {
        // Genuine, assigned schedule with a pre-departure breakdown + dispatched
        // replacement bus, and a post-departure traffic incident (excluded).
        $this->createAssignedSchedule(
            'G-SCHED-1', 'R-001', '2026-09-15',
            '05:30:00', '06:30:00', 'Scheduled', 60
        );
        $genuine = $this->createDdr('DDR-2026-0001', '2026-09-15', '05:30:00', '06:35:00', 'TT-10001');

        $breakdown = $this->createIncident('INC-001', 'Bus Breakdown', '2026-09-15 05:10:00', $genuine);
        IncidentReplacement::create([
            'incident_id' => $breakdown->id,
            'original_bus_id' => $this->bus->id,
            'replacement_bus_id' => $this->bus->id,
            'dispatched_at' => '2026-09-15 05:20:00',
            'dispatched_by' => $this->user->id,
        ]);
        // Post-departure traffic incident -> must NOT be counted.
        $this->createIncident('INC-002', 'Traffic', '2026-09-15 06:00:00', $genuine);

        // DEMO schedule (TRIP-) with a matching DDR -> excluded.
        $this->createAssignedSchedule(
            'TRIP-001', 'R-002', '2026-09-15',
            '07:00:00', '08:00:00', 'Scheduled', 60
        );
        $this->createDdr('DDR-2026-0002', '2026-09-15', '07:05:00', '08:10:00', 'TT-10002');

        // Unmatched DDR (no schedule/assignment that day).
        $this->createDdr('DDR-2026-0003', '2026-09-16', '08:00:00', '09:00:00', 'TT-10003');

        // Cancelled schedule with a matching DDR -> excluded.
        $this->createAssignedSchedule(
            'G-SCHED-2', 'R-003', '2026-09-15',
            '09:00:00', '10:00:00', 'Cancelled', 60
        );
        $this->createDdr('DDR-2026-0004', '2026-09-15', '09:05:00', '10:10:00', 'TT-10004');

        // Impossibly short schedule (< 10 min) -> excluded.
        $this->createAssignedSchedule(
            'G-SCHED-3', 'R-004', '2026-09-15',
            '11:00:00', '11:05:00', 'Scheduled', 60
        );
        $this->createDdr('DDR-2026-0005', '2026-09-15', '11:02:00', '11:08:00', 'TT-10005');

        // Duplicate (report_date, trip_ticket) -> only one row exported.
        $this->createDdr('DDR-2026-0006', '2026-09-15', '05:35:00', '06:40:00', 'TT-10001');

        $path = storage_path('delay_genuine_test.csv');

        $exit = $this->artisan('delay:export-genuine', ['--path' => $path])->run();
        $this->assertSame(0, $exit, 'Genuine rows were exported so the command succeeds.');

        $rows = $this->parseCsv($path);
        $this->assertCount(2, $rows, 'Header row + the single valid genuine row (TT-10001).');

        $header = array_shift($rows);
        $this->assertContains('trip_code', $header, 'Header must use the GENUINE_RAW_COLUMNS layout.');
        $this->assertContains('incident_replacement_count', $header);
        $this->assertCount(1, $rows, 'Only the genuine TT-10001 row is data.');

        $codes = array_column($rows, 1);
        $this->assertNotContains('TRIP-001', $codes, 'Demo schedules are excluded.');
        $this->assertNotContains('G-SCHED-2', $codes, 'Cancelled schedules are excluded.');
        $this->assertNotContains('G-SCHED-3', $codes, 'Short schedules are excluded.');
        $this->assertCount(1, array_filter($codes, fn ($c) => $c === 'G-SCHED-1'), 'Only one row per (date, ticket).');

        $row = $rows[0];

        $this->assertSame('2026-09-15', $row[0]);
        $this->assertSame('R-001', $row[2]);
        $this->assertSame('BUS-001', $row[4]);
        $this->assertSame('TT-10001', $row[7]);
        $this->assertSame('05:30', $row[8], 'Scheduled departure comes from the schedule.');
        $this->assertSame('60', $row[12], 'Scheduled duration is 60 minutes.');
        $this->assertSame('25', $row[16], 'Route distance is 25km.');
        $this->assertSame('5', $row[15], 'Arrival delay 06:35 vs 06:30 = 5 minutes.');

        // Incident context: 1 pre-departure breakdown (with replacement) counts;
        // the post-departure traffic incident is excluded.
        $this->assertSame('1', $row[17], 'incident_before_count = 1 (post-departure incident excluded).');
        $this->assertSame('1', $row[18], 'incident_breakdown_count = 1.');
        $this->assertSame('0', $row[19], 'incident_traffic_count = 0 (post-departure).');
        $this->assertSame('1', $row[20], 'incident_replacement_count = 1.');

        $this->assertFileExists($path.'.meta.json');
        $meta = json_decode(file_get_contents($path.'.meta.json'), true);
        $this->assertSame('genuine', $meta['source']);
        $this->assertSame(1, $meta['matched_rows']);
        $this->assertSame(1, $meta['exclusions']['demo_schedule']);
        $this->assertSame(1, $meta['exclusions']['unmatched']);
        $this->assertSame(1, $meta['exclusions']['cancelled']);
        $this->assertSame(1, $meta['exclusions']['short_schedule']);
        $this->assertSame(1, $meta['exclusions']['duplicate']);

        @unlink($path);
        @unlink($path.'.meta.json');
    }

    public function test_export_fails_when_no_genuine_rows_are_available(): void
    {
        // Only a demo schedule exists -> nothing genuine can be exported.
        $this->createAssignedSchedule(
            'TRIP-001', 'R-001', '2026-09-15',
            '05:30:00', '06:30:00', 'Scheduled', 60
        );
        $this->createDdr('DDR-2026-0001', '2026-09-15', '05:30:00', '06:35:00', 'TT-10001');

        $path = storage_path('delay_genuine_empty_test.csv');

        $exit = $this->artisan('delay:export-genuine', ['--path' => $path])->run();
        $this->assertSame(1, $exit, 'No genuine rows -> command fails (NOT READY).');

        $rows = $this->parseCsv($path);
        $this->assertCount(1, $rows, 'Only the header row is written when nothing matches.');

        @unlink($path);
        @unlink($path.'.meta.json');
    }

    /** @return array<int, array<int, string>> */
    private function parseCsv(string $path): array
    {
        $this->assertFileExists($path, 'The export CSV must be written.');
        $rows = [];
        $handle = fopen($path, 'r');
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = $line;
        }
        fclose($handle);

        return $rows;
    }

    private function createAssignedSchedule(
        string $tripCode,
        string $routeCode,
        string $date,
        string $departure,
        string $arrival,
        string $status,
        int $estimatedMinutes
    ): void {
        $route = ShuttleRoute::create([
            'route_code' => $routeCode,
            'route_name' => 'Test Route '.$routeCode,
            'origin' => 'Batangas',
            'destination' => 'Lipa',
            'distance_km' => 25,
            'estimated_time_minutes' => $estimatedMinutes,
            'status' => 'Active',
        ]);

        $attendance = DriverAttendance::firstOrCreate(
            [
                'driver_id' => $this->driver->driver_id,
                'attendance_date' => $date,
            ],
            [
                'driver_name' => 'Test Driver',
                'shift' => 'Morning',
                'status' => 'Present',
            ]
        );

        $schedule = TripSchedule::create([
            'trip_code' => $tripCode,
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => $departure,
            'estimated_arrival_time' => $arrival,
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $schedule->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    private function createDdr(string $ddrNo, string $date, string $departure, string $arrival, string $ticket): DailyDriverReport
    {
        return DailyDriverReport::create([
            'ddr_no' => $ddrNo,
            'report_date' => $date,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => $ticket,
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => $departure,
            'arrival_time' => $arrival,
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);
    }

    private function createIncident(string $incidentNo, string $type, string $reportedAt, DailyDriverReport $report): Incident
    {
        return Incident::create([
            'incident_no' => $incidentNo,
            'trip_schedule_id' => $report ? TripSchedule::whereDate('trip_date', $report->report_date->toDateString())->first()?->id : null,
            'bus_id' => $report->bus_id,
            'driver_id' => $report->driver_id,
            'driver_name' => $report->driver_name,
            'incident_type' => $type,
            'location' => 'Test Location',
            'description' => 'Test incident',
            'incident_reported_at' => $reportedAt,
            'status' => 'Reported',
            'reported_by' => $this->user->id,
        ]);
    }
}