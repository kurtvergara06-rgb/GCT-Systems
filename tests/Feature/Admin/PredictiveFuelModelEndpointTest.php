<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictiveFuelModelEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_URL = '*/fuel/status';
    private const PREDICT_URL = '*/fuel/predict';

    public function test_endpoint_uses_latest_linked_gps_trip_and_returns_genuine_prediction(): void
    {
        $user = User::factory()->create();
        $this->seedLinkedFuelTrip('GCT-101', 3.40);

        Http::fake([
            self::STATUS_URL => Http::response($this->statusPayload()),
            self::PREDICT_URL => Http::response($this->predictionPayload(3.10)),
        ]);

        $response = $this->actingAs($user)->getJson(route('analytics.fuel-predictions', [
            'bus_nos' => ['GCT-101'],
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertJsonPath('model_ready', true)
            ->assertJsonPath('data_source', 'genuine')
            ->assertJsonPath('is_production_model', true)
            ->assertJsonPath('sample_count', 156)
            ->assertJsonPath('predictions.GCT-101.available', true)
            ->assertJsonPath('predictions.GCT-101.prediction.predicted_fuel_liters', 3.10)
            ->assertJsonPath('predictions.GCT-101.actual_fuel_liters', 3.4)
            ->assertJsonPath('predictions.GCT-101.variance_liters', 0.3);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/fuel/predict')
            && $request['route'] === 'Talisay - SM Seaside'
            && $request['bus_no'] === 'GCT-101'
            && (float) $request['distance_km'] === 11.7
            && (float) $request['trip_duration_minutes'] === 55.0
            && (float) $request['in_motion_minutes'] === 50.0
            && (float) $request['idling_minutes'] === 5.0
            && (float) $request['engine_on_hours'] === 0.92);
    }

    public function test_endpoint_does_not_call_predict_when_model_is_not_ready(): void
    {
        $user = User::factory()->create();
        $this->seedLinkedFuelTrip('GCT-101', 3.40);

        Http::fake([
            self::STATUS_URL => Http::response([
                ...$this->statusPayload(),
                'model_ready' => false,
                'is_production_model' => false,
                'model_version' => 'FUEL_ML_NOT_READY',
                'reason' => 'Fuel model is not trained or could not be loaded.',
            ]),
            self::PREDICT_URL => Http::response($this->predictionPayload(3.10)),
        ]);

        $this->actingAs($user)->getJson(route('analytics.fuel-predictions', [
            'bus_nos' => ['GCT-101'],
            'period' => 'this-month',
        ]))
            ->assertOk()
            ->assertJsonPath('model_ready', false)
            ->assertJsonPath('predictions.GCT-101.available', false);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/fuel/predict'));
    }

    public function test_endpoint_reports_missing_linked_trip_without_inventing_prediction(): void
    {
        $user = User::factory()->create();

        DB::table('fuel_reports')->insert([
            'report_date' => now()->toDateString(),
            'bus_no' => 'GCT-202',
            'distance_km' => 10.0,
            'fuel_liters' => 3.0,
            'km_per_liter' => 3.33,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            self::STATUS_URL => Http::response($this->statusPayload()),
        ]);

        $this->actingAs($user)->getJson(route('analytics.fuel-predictions', [
            'bus_nos' => ['GCT-202'],
            'period' => 'this-month',
        ]))
            ->assertOk()
            ->assertJsonPath('predictions.GCT-202.report_found', false)
            ->assertJsonPath('predictions.GCT-202.available', false);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson(route('analytics.fuel-predictions', [
            'bus_nos' => ['GCT-101'],
        ]))->assertUnauthorized();
    }

    private function seedLinkedFuelTrip(string $busNo, float $fuelLiters): void
    {
        $batchId = DB::table('batch_uploads')->insertGetId([
            'file_name' => 'fuel-model-test.csv',
            'stored_name' => 'fuel-model-test.csv',
            'file_path' => 'testing/fuel-model-test.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => $busNo,
            'status' => 'Processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startedAt = now()->startOfDay()->addHours(8);
        $gpsId = DB::table('gps_trip_records')->insertGetId([
            'batch_upload_id' => $batchId,
            'record_no' => 'GPS-FUEL-001',
            'bus_no' => $busNo,
            'grouping' => 'Talisay - SM Seaside',
            'trip_type' => 'Shuttle Service',
            'beginning_at' => $startedAt,
            'initial_location' => 'Talisay',
            'ending_at' => $startedAt->copy()->addMinutes(55),
            'final_location' => 'SM Seaside',
            'duration_minutes' => 55,
            'total_minutes' => 55,
            'in_motion_minutes' => 50,
            'idling_minutes' => 5,
            'mileage_km' => 11.70,
            'engine_hours' => 0.92,
            'severity' => 'Normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_reports')->insert([
            'report_date' => now()->toDateString(),
            'bus_no' => $busNo,
            'gps_trip_record_id' => $gpsId,
            'distance_km' => 11.70,
            'distance_source' => 'GPS',
            'fuel_liters' => $fuelLiters,
            'km_per_liter' => round(11.70 / $fuelLiters, 2),
            'status' => 'Completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusPayload(): array
    {
        return [
            'success' => true,
            'model_ready' => true,
            'source' => 'ml',
            'data_source' => 'genuine',
            'dataset_type' => 'GENUINE GCT RECORDS',
            'is_production_model' => true,
            'model_version' => 'FUEL_ML_READY',
            'sample_count' => 156,
            'model_path' => '/tmp/fuel.pkl',
            'reason' => 'Fuel Random Forest model is ready.',
        ];
    }

    private function predictionPayload(float $liters): array
    {
        return [
            'success' => true,
            'model_ready' => true,
            'source' => 'ml',
            'data_source' => 'genuine',
            'dataset_type' => 'GENUINE GCT RECORDS',
            'is_production_model' => true,
            'model_version' => 'FUEL_ML_READY',
            'sample_count' => 156,
            'predicted_fuel_liters' => $liters,
            'feature_inputs' => [],
            'message' => 'Prediction ready.',
        ];
    }
}
