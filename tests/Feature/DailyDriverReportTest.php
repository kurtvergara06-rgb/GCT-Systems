<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyDriverReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->driver = $this->createDriverMaster();
        $this->bus = Bus::create([
            'bus_no' => 'BUS-001',
            'plate_no' => 'ABC-1234',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
    }

    public function test_index_and_create_pages_render(): void
    {
        $this->actingAs($this->user)
            ->get(route('daily-driver-reports'))
            ->assertOk()
            ->assertSee('Daily Drivers Report')
            ->assertSee('openEncodeReportModal')
            ->assertSee('ddrEncodeModal');

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.create'))
            ->assertOk()
            ->assertSee('Encode Daily Driver Report');
    }

    public function test_store_creates_a_report_with_ddr_numbering(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('daily-driver-reports.store'), $this->validPayload())
            ->assertRedirect(route('daily-driver-reports'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_driver_reports', [
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-09-15',
            'driver_id' => 'D-2026-0001',
            'driver_name' => 'Test Driver',
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:30:00',
            'arrival_time' => '06:30:00',
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);
    }

    public function test_store_rejects_a_duplicate_trip_ticket_on_the_same_date(): void
    {
        DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-09-15',
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:30:00',
            'arrival_time' => '06:30:00',
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->from(route('daily-driver-reports.create'))
            ->post(route('daily-driver-reports.store'), $this->validPayload())
            ->assertRedirect(route('daily-driver-reports.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('daily_driver_reports', 1);
    }

    public function test_store_rejects_invalid_values_without_modifying_input(): void
    {
        $payload = $this->validPayload();
        $payload['passengers'] = -3;

        $this->actingAs($this->user)
            ->from(route('daily-driver-reports.create'))
            ->post(route('daily-driver-reports.store'), $payload)
            ->assertSessionHasErrors('passengers');

        $payload['passengers'] = 25;
        $payload['arrival_time'] = '99:99';

        $this->actingAs($this->user)
            ->from(route('daily-driver-reports.create'))
            ->post(route('daily-driver-reports.store'), $payload)
            ->assertSessionHasErrors('arrival_time');

        $this->assertDatabaseCount('daily_driver_reports', 0);
    }

    public function test_overnight_trip_is_accepted_and_kept_verbatim(): void
    {
        $payload = $this->validPayload();
        $payload['departure_time'] = '23:30';
        $payload['arrival_time'] = '00:45';

        $this->actingAs($this->user)
            ->post(route('daily-driver-reports.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('daily_driver_reports', [
            'departure_time' => '23:30:00',
            'arrival_time' => '00:45:00',
        ]);
    }

    public function test_show_renders_schedule_match_unavailable_when_no_schedule_exists(): void
    {
        $report = DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-09-15',
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:30:00',
            'arrival_time' => '06:30:00',
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.show', [
                'dailyDriverReport' => $report->ddr_no,
            ]))
            ->assertOk()
            ->assertSee('DDR-2026-0001')
            ->assertSee('Schedule match unavailable');
    }

    public function test_show_computes_delayed_status_from_a_real_schedule_only(): void
    {
        $this->createAssignedSchedule('05:00:00', '05:40:00');

        $report = DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-09-15',
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:45:00',
            'arrival_time' => '06:35:00',
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.show', [
                'dailyDriverReport' => $report->ddr_no,
            ]))
            ->assertOk()
            ->assertSee('T-SCHED-1')
            ->assertSee('Delayed')
            ->assertSee('Late by 45 min');
    }

    public function test_show_computes_on_time_when_actual_matches_schedule(): void
    {
        $this->createAssignedSchedule('05:00:00', '05:40:00');

        $report = DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-09-15',
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:06:00',
            'arrival_time' => '05:42:00',
            'passengers' => 25,
            'encoded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.show', [
                'dailyDriverReport' => $report->ddr_no,
            ]))
            ->assertOk()
            ->assertSee('On Time');
    }

    public function test_schedule_lookup_returns_matching_trips_for_driver_and_bus(): void
    {
        $this->createAssignedSchedule('06:15:00', '07:10:00');

        $this->actingAs($this->user)
            ->get(route('daily-driver-reports.schedule-lookup', [
                'report_date' => '2026-09-15',
                'driver_id' => $this->driver->driver_id,
                'bus_id' => $this->bus->id,
            ]))
            ->assertOk()
            ->assertJsonPath('schedules.0.trip_code', 'T-SCHED-1')
            ->assertJsonPath('schedules.0.departure_time', '06:15');
    }

    private function validPayload(): array
    {
        return [
            'report_date' => '2026-09-15',
            'driver_id' => $this->driver->driver_id,
            'bus_id' => $this->bus->id,
            'trip_ticket' => 'TT-10001',
            'from_location' => 'Batangas',
            'to_location' => 'Lipa',
            'departure_time' => '05:30',
            'arrival_time' => '06:30',
            'passengers' => 25,
        ];
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

    private function createAssignedSchedule(string $departure, string $arrival): array
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-001',
            'route_name' => 'Test Route',
            'origin' => 'Batangas',
            'destination' => 'Lipa',
            'distance_km' => 25,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);

        $attendance = DriverAttendance::create([
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => '2026-09-15',
            'status' => 'Present',
        ]);

        $schedule = TripSchedule::create([
            'trip_code' => 'T-SCHED-1',
            'trip_date' => '2026-09-15',
            'shuttle_route_id' => $route->id,
            'departure_time' => $departure,
            'estimated_arrival_time' => $arrival,
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Scheduled',
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

        return [$schedule, $attendance];
    }
}