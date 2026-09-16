<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictiveEtaColumnTest extends TestCase
{
    use RefreshDatabase;

    private const STATISTICAL_URL = '*/analytics/fleet-trip/predict';

    private const ETA_URL = '*/eta/predict';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSchedules();
    }

    public function test_predictive_fleet_trip_page_shows_ml_eta_when_eta_service_is_available(): void
    {
        Http::fake([
            self::STATISTICAL_URL => Http::response($this->statisticalPayload()),
            self::ETA_URL => Http::response($this->etaPayload()),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analytics.stage', ['stage' => 'predictive', 'domain' => 'fleet-trip']))
            ->assertOk()
            ->assertSee('ML ETA')
            ->assertSee('TRIP-001')
            ->assertSee('Talisay - SM Seaside')
            ->assertSee('Oct 3, 2026 08:00 AM')
            // Existing prediction columns are preserved.
            ->assertSee('Delay Risk')
            ->assertSee('Scheduled')
            // ML ETA value wins over the statistical value.
            ->assertSee('Oct 3, 2026 08:45 AM')
            ->assertSee('ft-eta-src--ml')
            ->assertDontSee('ft-eta-src--stat')
            ->assertDontSee('Oct 3, 2026 09:00 AM');
    }

    public function test_predictive_fleet_trip_page_falls_back_to_statistical_eta_when_eta_service_unavailable(): void
    {
        Http::fake([
            self::STATISTICAL_URL => Http::response($this->statisticalPayload()),
            self::ETA_URL => Http::response([
                'success' => false,
                'detail' => 'ETA model is not trained or could not be loaded.',
            ], 503),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analytics.stage', ['stage' => 'predictive', 'domain' => 'fleet-trip']))
            ->assertOk()
            ->assertSee('ML ETA')
            ->assertSee('TRIP-001')
            // Statistical ETA is used as the fallback value.
            ->assertSee('Oct 3, 2026 09:00 AM')
            ->assertSee('ft-eta-src--stat')
            ->assertDontSee('ft-eta-src--ml')
            ->assertDontSee('Oct 3, 2026 08:45 AM');
    }

    public function test_predictive_fleet_trip_page_survives_an_eta_service_connection_failure(): void
    {
        Http::fake([
            self::STATISTICAL_URL => Http::response($this->statisticalPayload()),
            self::ETA_URL => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analytics.stage', ['stage' => 'predictive', 'domain' => 'fleet-trip']))
            ->assertOk()
            ->assertSee('ML ETA')
            ->assertSee('ft-eta-src--stat')
            ->assertSee('Oct 3, 2026 09:00 AM');
    }

    public function test_predictive_fleet_trip_page_renders_empty_state_without_predictions(): void
    {
        Http::fake([
            self::STATISTICAL_URL => Http::response([
                'success' => true,
                'model' => 'historical-statistical-v1',
                'historical_records' => 0,
                'target_count' => 4,
                'predicted_target_count' => 0,
                'predictions' => [],
                'eligibility' => [],
                'peak_periods' => [],
            ]),
            self::ETA_URL => Http::response($this->etaPayload()),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('analytics.stage', ['stage' => 'predictive', 'domain' => 'fleet-trip']))
            ->assertOk()
            ->assertSee('ML ETA')
            ->assertSee('No trip predictions available for the selected filters.');
    }

    private function tripCodes(): array
    {
        return ['TRIP-001', 'TRIP-002', 'TRIP-003', 'TRIP-004'];
    }

    private function seedSchedules(): void
    {
        $user = User::factory()->create();

        $route = ShuttleRoute::create([
            'route_code' => 'TAL-SM',
            'route_name' => 'Talisay - SM Seaside',
            'origin' => 'Talisay',
            'destination' => 'SM Seaside',
            'distance_km' => 20.5,
            'estimated_time_minutes' => 50,
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'GCT-101',
            'plate_no' => 'XYZ-123',
            'bus_model' => 'Hino',
            'status' => 'Active',
        ]);

        $driver = Driver::create([
            'driver_id' => 'DRV-001',
            'driver_name' => 'Juan Dela Cruz',
            'shift' => 'Morning',
            'contact_number' => '09171234567',
            'license_number' => 'L-001-2026',
            'employment_status' => 'Active',
        ]);

        $attendance = DriverAttendance::create([
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
        ]);

        foreach ($this->tripCodes() as $code) {
            $schedule = TripSchedule::create([
                'trip_code' => $code,
                'trip_date' => now()->addDay()->toDateString(),
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00:00',
                'estimated_arrival_time' => '08:50:00',
                'shift' => 'Morning',
                'status' => 'Scheduled',
                'created_by' => $user->id,
            ]);

            TripAssignment::create([
                'trip_schedule_id' => $schedule->id,
                'driver_attendance_id' => $attendance->id,
                'driver_id' => $attendance->driver_id,
                'driver_name' => $attendance->driver_name,
                'bus_id' => $bus->id,
            ]);
        }
    }

    private function statisticalPayload(): array
    {
        $risks = [
            'TRIP-001' => 80,
            'TRIP-002' => 55,
            'TRIP-003' => 35,
            'TRIP-004' => 15,
        ];

        $predictions = [];

        foreach ($this->tripCodes() as $code) {
            $predictions[] = [
                'trip_code' => $code,
                'route' => 'Talisay - SM Seaside',
                'departure_at' => '2026-10-03 08:00:00',
                'predicted_duration_minutes' => 60,
                'estimated_arrival_at' => '2026-10-03 09:00:00',
                'delay_risk_percent' => $risks[$code],
                'risk_level' => 'Medium',
                'sample_size' => 12,
                'method' => 'route history',
                'baseline_duration_minutes' => 55,
            ];
        }

        return [
            'success' => true,
            'model' => 'historical-statistical-v1',
            'historical_records' => 120,
            'target_count' => 4,
            'predicted_target_count' => 4,
            'predictions' => $predictions,
            'eligibility' => [],
            'peak_periods' => [],
        ];
    }

    private function etaPayload(): array
    {
        return [
            'success' => true,
            'model_ready' => true,
            'source' => 'ml',
            'sample_count' => 362,
            'predicted_duration_minutes' => 45,
            'estimated_arrival_at' => '2026-10-03 08:45:00',
            'departure_at' => '2026-10-03 08:00:00',
            'feature_inputs' => [],
            'message' => 'Predicted trip duration is 45 minutes based on historical GPS trip data.',
        ];
    }
}