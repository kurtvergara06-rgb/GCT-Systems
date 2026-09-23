<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictiveDelayModelEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_URL = '*/delay/status';

    private const PREDICT_URL = '*/delay/predict';

    public function test_delay_prediction_endpoint_builds_pre_trip_context_for_displayed_trip(): void
    {
        [$schedule, $user] = $this->seedScheduledTrip();

        $departureAt = $schedule->trip_date->copy()->setTime(8, 0);

        Incident::create([
            'incident_no' => 'INC-PRE-001',
            'trip_schedule_id' => $schedule->id,
            'bus_id' => $schedule->assignment->bus_id,
            'driver_id' => $schedule->assignment->driver_id,
            'driver_name' => $schedule->assignment->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Terminal',
            'description' => 'Reported before departure.',
            'incident_reported_at' => $departureAt->copy()->subMinutes(30),
            'reported_by' => $user->id,
        ]);

        // This incident happens after scheduled departure and must not leak
        // into pre-trip Model #3 features.
        Incident::create([
            'incident_no' => 'INC-POST-001',
            'trip_schedule_id' => $schedule->id,
            'bus_id' => $schedule->assignment->bus_id,
            'driver_id' => $schedule->assignment->driver_id,
            'driver_name' => $schedule->assignment->driver_name,
            'incident_type' => 'Traffic',
            'location' => 'Highway',
            'description' => 'Reported after departure.',
            'incident_reported_at' => $departureAt->copy()->addMinutes(10),
            'reported_by' => $user->id,
        ]);

        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_ready' => true,
                'ready' => true,
                'dataset_type' => 'SAMPLE / DEMONSTRATION',
                'model_source' => 'sample',
                'data_source' => 'sample',
                'is_production_model' => false,
                'model_version' => 'DELAY_ML_READY (SAMPLE/DEVELOPMENT)',
                'warning' => 'Development-only sample model.',
                'disclaimer' => 'Not trained on genuine GCT delay history.',
            ]),
            self::PREDICT_URL => Http::response([
                'success' => true,
                'predicted_arrival_delay_minutes' => 12.5,
                'predicted_delay_minutes' => 12.5,
                'risk_status' => 'Moderate Delay',
                'risk_level' => 'Moderate Delay',
                'data_source' => 'sample',
                'model_source' => 'sample',
                'is_production_model' => false,
                'model_version' => 'DELAY_ML_READY (SAMPLE/DEVELOPMENT)',
                'ready' => true,
                'source' => 'ml',
                'disclaimer' => 'Not trained on genuine GCT delay history.',
            ]),
        ]);

        $response = $this->actingAs($user)->getJson(route('analytics.delay-predictions', [
            'trip_codes' => [$schedule->trip_code],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('model_ready', true)
            ->assertJsonPath('dataset_type', 'SAMPLE / DEMONSTRATION')
            ->assertJsonPath('is_production_model', false)
            ->assertJsonPath("predictions.{$schedule->trip_code}.available", true)
            ->assertJsonPath("predictions.{$schedule->trip_code}.prediction.predicted_delay_minutes", 12.5)
            ->assertJsonPath("predictions.{$schedule->trip_code}.prediction.risk_status", 'Moderate Delay')
            ->assertJsonPath("predictions.{$schedule->trip_code}.context.pre_trip_incident_count", 1);

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/delay/predict')) {
                return false;
            }

            return $request['route'] === 'Talisay - SM Seaside'
                && $request['scheduled_departure_time'] === '08:00'
                && $request['bus_no'] === 'GCT-101'
                && $request['driver_id'] === 'DRV-001'
                && $request['scheduled_duration_minutes'] === 50.0
                && $request['route_distance_km'] === 20.5
                && $request['incident_before_departure'] === true
                && $request['incident_breakdown_flag'] === true
                && $request['incident_traffic_flag'] === false
                && $request['incident_replacement_flag'] === false;
        });
    }

    public function test_delay_prediction_endpoint_does_not_call_predict_when_model_is_not_ready(): void
    {
        [$schedule, $user] = $this->seedScheduledTrip();

        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_ready' => false,
                'ready' => false,
                'dataset_type' => 'GENUINE',
                'model_source' => 'genuine',
                'is_production_model' => false,
                'warning' => 'Genuine model is not ready.',
            ]),
            self::PREDICT_URL => Http::response([
                'success' => true,
                'predicted_delay_minutes' => 99,
            ]),
        ]);

        $this->actingAs($user)
            ->getJson(route('analytics.delay-predictions', [
                'trip_codes' => [$schedule->trip_code],
            ]))
            ->assertOk()
            ->assertJsonPath('model_ready', false)
            ->assertJsonPath("predictions.{$schedule->trip_code}.available", false)
            ->assertJsonPath("predictions.{$schedule->trip_code}.prediction", null);

        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/delay/predict'));
    }

    public function test_delay_prediction_endpoint_requires_authentication(): void
    {
        $this->get('/analytics/delay-predictions?trip_codes[]=TRIP-001')
            ->assertRedirect();
    }

    /**
     * @return array{TripSchedule, User}
     */
    private function seedScheduledTrip(): array
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

        $schedule = TripSchedule::create([
            'trip_code' => 'TRIP-001',
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

        return [$schedule->fresh(['assignment.bus']), $user];
    }
}
