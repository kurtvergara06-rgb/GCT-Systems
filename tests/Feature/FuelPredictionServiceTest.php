<?php

namespace Tests\Feature;

use App\Services\FuelPredictionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelPredictionServiceTest extends TestCase
{
    private const STATUS_URL = '*/fuel/status';
    private const PREDICT_URL = '*/fuel/predict';

    public function test_status_returns_genuine_model_metadata(): void
    {
        Http::fake([
            self::STATUS_URL => Http::response([
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
            ]),
        ]);

        $status = (new FuelPredictionService())->status();

        $this->assertIsArray($status);
        $this->assertTrue($status['model_ready']);
        $this->assertSame('genuine', $status['data_source']);
        $this->assertTrue($status['is_production_model']);
        $this->assertSame(156, $status['sample_count']);
    }

    public function test_predict_batch_isolates_failed_trip_requests(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/fuel/predict')) {
                if (($request['bus_no'] ?? '') === 'GCT-FAIL') {
                    return Http::response(['detail' => 'failed'], 500);
                }

                return Http::response([
                    'success' => true,
                    'model_ready' => true,
                    'source' => 'ml',
                    'data_source' => 'genuine',
                    'dataset_type' => 'GENUINE GCT RECORDS',
                    'is_production_model' => true,
                    'model_version' => 'FUEL_ML_READY',
                    'sample_count' => 156,
                    'predicted_fuel_liters' => 3.21,
                    'feature_inputs' => [],
                    'message' => 'ok',
                ]);
            }

            return Http::response([], 404);
        });

        $payload = fn (string $bus) => [
            'route' => 'Talisay - SM Seaside',
            'trip_started_at' => '2026-09-20T08:00:00+08:00',
            'bus_no' => $bus,
            'distance_km' => 11.7,
            'trip_duration_minutes' => 55,
            'in_motion_minutes' => 50,
            'idling_minutes' => 5,
            'engine_on_hours' => 1.0,
        ];

        $result = (new FuelPredictionService())->predictBatch([
            'GCT-101' => $payload('GCT-101'),
            'GCT-FAIL' => $payload('GCT-FAIL'),
        ]);

        $this->assertSame(3.21, $result['GCT-101']['predicted_fuel_liters']);
        $this->assertNull($result['GCT-FAIL']);
    }

    public function test_status_returns_null_when_python_engine_is_unavailable(): void
    {
        Http::fake([
            self::STATUS_URL => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->assertNull((new FuelPredictionService())->status());
    }
}
