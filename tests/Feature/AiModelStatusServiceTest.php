<?php

namespace Tests\Feature;

use App\Services\AiModelStatusService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiModelStatusServiceTest extends TestCase
{
    public function test_it_reports_only_genuine_production_models_as_ready(): void
    {
        Http::fake([
            '*/eta/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'source' => 'ml',
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT GPS RECORDS',
                'is_production_model' => true,
                'sample_count' => 120,
                'reason' => 'ETA Random Forest model is ready.',
            ]),
            '*/fuel/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT RECORDS',
                'is_production_model' => true,
                'sample_count' => 156,
                'reason' => 'Fuel Random Forest model is ready.',
            ]),
            '*/delay/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT RECORDS',
                'is_production_model' => false,
                'sample_count' => 24,
                'reason' => 'MODEL NOT READY: insufficient genuine delay history.',
            ]),
            '*/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT RECORDS',
                'is_production_model' => false,
                'sample_count' => 0,
                'reason' => 'MODEL NOT READY: genuine stock-movement history is insufficient.',
            ]),
            '*/operation/auto-scheduling/ai/training/status' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT GPS + ATTENDANCE RECORDS',
                'bus_model_ready' => true,
                'driver_model_ready' => false,
                'bus_source' => 'ml',
                'driver_source' => 'data_fallback',
                'bus_sample_count' => 367,
                'driver_sample_count' => 4,
                'bus_reason' => 'ML bus model is ready.',
                'driver_reason' => 'Using genuine attendance reliability fallback.',
            ]),
        ]);

        $models = collect(app(AiModelStatusService::class)->all())->keyBy('key');

        $this->assertTrue($models['eta']->ready);
        $this->assertSame('Ready', $models['eta']->state);
        $this->assertTrue($models['fuel']->ready);
        $this->assertSame('Ready', $models['fuel']->state);

        $this->assertFalse($models['delay']->ready);
        $this->assertSame('MODEL NOT READY', $models['delay']->state);
        $this->assertFalse($models['inventory']->ready);
        $this->assertSame('MODEL NOT READY', $models['inventory']->state);

        $this->assertFalse($models['scheduling']->ready);
        $this->assertSame('Partial ML', $models['scheduling']->state);
        $this->assertSame('Genuine Data', $models['scheduling']->data_source);
        $this->assertSame('ML Ready', $models['scheduling']->details[0]->value);
        $this->assertSame('Data Fallback', $models['scheduling']->details[1]->value);
    }

    public function test_it_never_marks_sample_or_synthetic_models_as_production_ready(): void
    {
        Http::fake([
            '*/eta/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'source' => 'not_trained',
                'data_source' => 'genuine',
                'is_production_model' => false,
                'sample_count' => 0,
                'reason' => 'ETA model is not trained.',
            ]),
            '*/fuel/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'data_source' => 'genuine',
                'is_production_model' => false,
                'sample_count' => 0,
                'reason' => 'Fuel model is not trained.',
            ]),
            '*/delay/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'data_source' => 'sample',
                'dataset_type' => 'SYNTHETIC / DEVELOPMENT',
                'is_production_model' => false,
                'sample_count' => 500,
                'reason' => 'Development sample model is loaded.',
            ]),
            '*/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'data_source' => 'sample',
                'dataset_type' => 'SYNTHETIC / DEVELOPMENT',
                'is_production_model' => false,
                'sample_count' => 600,
                'reason' => 'Development sample model is loaded.',
            ]),
            '*/operation/auto-scheduling/ai/training/status' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'bus_model_ready' => false,
                'driver_model_ready' => false,
                'bus_source' => 'rule_fallback',
                'driver_source' => 'data_fallback',
                'bus_sample_count' => 0,
                'driver_sample_count' => 2,
            ]),
        ]);

        $models = collect(app(AiModelStatusService::class)->all())->keyBy('key');

        $this->assertFalse($models['delay']->ready);
        $this->assertSame('Development Only', $models['delay']->state);
        $this->assertSame('Sample Data', $models['delay']->data_source);

        $this->assertFalse($models['inventory']->ready);
        $this->assertSame('Development Only', $models['inventory']->state);
        $this->assertSame('Sample Data', $models['inventory']->data_source);
    }

    public function test_it_reports_unreachable_status_endpoints_without_inventing_readiness(): void
    {
        Http::fake(fn () => Http::response(['detail' => 'offline'], 503));

        $models = app(AiModelStatusService::class)->all();

        $this->assertCount(5, $models);

        foreach ($models as $model) {
            $this->assertFalse($model->ready);
            $this->assertFalse($model->reachable);
            $this->assertSame('Service Unavailable', $model->state);
        }
    }
}
