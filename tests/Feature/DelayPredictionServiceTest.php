<?php

namespace Tests\Feature;

use App\Services\DelayPredictionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DelayPredictionServiceTest extends TestCase
{
    private const PREDICT_URL = '*/delay/predict';

    private const STATUS_URL = '*/delay/status';

    public function test_predict_posts_the_delay_payload_and_returns_the_response(): void
    {
        Http::fake([
            self::PREDICT_URL => Http::response([
                'success' => true,
                'model' => 'delay-rf-genuine-v1',
                'model_source' => 'genuine',
                'predicted_arrival_delay_minutes' => 12,
                'predicted_delay_minutes' => 12,
                'risk_status' => 'Moderate Delay',
                'risk_level' => 'Moderate Delay',
                'confidence' => 0.82,
                'model_ready' => true,
                'ready' => true,
            ]),
        ]);

        $service = new DelayPredictionService();

        $result = $service->predict([
            'route' => 'R-001',
            'bus_no' => 'BUS-001',
            'driver_id' => 'D-2026-0001',
            'trip_date' => '2026-09-20',
            'scheduled_departure_time' => '05:30',
            'scheduled_duration_minutes' => 60,
            'incident_before_departure' => true,
            'incident_breakdown_flag' => true,
            'incident_traffic_flag' => false,
            'incident_replacement_flag' => false,
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/delay/predict')
            && $request['route'] === 'R-001'
            && $request['scheduled_departure_time'] === '05:30'
            && $request['incident_before_departure'] === true
            && $request['incident_breakdown_flag'] === true);

        $this->assertIsArray($result);
        $this->assertSame(12, $result['predicted_delay_minutes']);
        $this->assertSame('genuine', $result['model_source']);
        $this->assertTrue($result['ready']);
    }

    public function test_predict_batch_returns_keyed_results_and_isolates_failed_trips(): void
    {
        Http::fake([
            self::PREDICT_URL => function ($request) {
                if (($request['route'] ?? null) === 'Route B') {
                    return Http::response([
                        'detail' => 'Unable to produce a delay prediction for this trip.',
                    ], 500);
                }

                return Http::response([
                    'success' => true,
                    'predicted_arrival_delay_minutes' => 8.5,
                    'predicted_delay_minutes' => 8.5,
                    'risk_status' => 'Minor Delay',
                    'risk_level' => 'Minor Delay',
                    'data_source' => 'sample',
                    'model_source' => 'sample',
                    'is_production_model' => false,
                    'ready' => true,
                ]);
            },
        ]);

        $results = (new DelayPredictionService())->predictBatch([
            'TRIP-A' => [
                'route' => 'Route A',
                'scheduled_departure_time' => '08:00',
            ],
            'TRIP-B' => [
                'route' => 'Route B',
                'scheduled_departure_time' => '09:00',
            ],
        ]);

        $this->assertSame(8.5, $results['TRIP-A']['predicted_delay_minutes']);
        $this->assertSame('sample', $results['TRIP-A']['model_source']);
        $this->assertNull($results['TRIP-B']);
    }

    public function test_predict_returns_null_when_the_api_returns_an_error_status(): void
    {
        Http::fake([
            self::PREDICT_URL => Http::response([
                'success' => false,
                'detail' => 'Delay model is not trained or could not be loaded.',
            ], 503),
        ]);

        $this->assertNull((new DelayPredictionService())->predict(['route' => 'R-001']));
    }

    public function test_predict_returns_null_when_the_success_flag_is_missing(): void
    {
        Http::fake([
            self::PREDICT_URL => Http::response([
                'model' => 'delay-rf-v1',
            ]),
        ]);

        $this->assertNull((new DelayPredictionService())->predict(['route' => 'R-001']));
    }

    public function test_predict_returns_null_on_connection_failure(): void
    {
        Http::fake([
            self::PREDICT_URL => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->assertNull((new DelayPredictionService())->predict(['route' => 'R-001']));
    }

    public function test_status_returns_readiness_payload(): void
    {
        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model' => 'delay-rf-v1',
                'model_source' => 'sample',
                'model_ready' => false,
                'ready' => false,
                'message' => 'Sample model demonstration.',
                'dataset_type' => 'SAMPLE / DEMONSTRATION',
                'is_production_model' => false,
                'warning' => '...',
                'disclaimer' => '...',
            ]),
        ]);

        $result = (new DelayPredictionService())->status();

        $this->assertIsArray($result);
        $this->assertFalse($result['model_ready']);
        $this->assertSame('SAMPLE / DEMONSTRATION', $result['dataset_type']);
    }

    public function test_is_ready_is_false_when_the_service_is_down(): void
    {
        Http::fake([
            self::STATUS_URL => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->assertFalse((new DelayPredictionService())->isReady());
    }

    public function test_is_ready_is_false_when_the_model_is_not_ready(): void
    {
        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_ready' => false,
                'ready' => false,
            ]),
        ]);

        $this->assertFalse((new DelayPredictionService())->isReady());
    }

    public function test_is_ready_is_true_when_the_model_is_ready(): void
    {
        Http::fake([
            self::STATUS_URL => Http::response([
                'success' => true,
                'model_source' => 'genuine',
                'model_ready' => true,
                'ready' => true,
            ]),
        ]);

        $this->assertTrue((new DelayPredictionService())->isReady());
    }
}
