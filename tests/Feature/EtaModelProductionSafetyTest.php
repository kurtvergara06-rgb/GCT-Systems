<?php

namespace Tests\Feature;

use App\Models\Admin\BatchUpload;
use App\Services\AiModelStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EtaModelProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_uploads_remain_unknown_until_explicitly_verified(): void
    {
        $batch = BatchUpload::create([
            'file_name' => 'uploaded-gps.csv',
            'stored_name' => 'uploaded-gps.csv',
            'file_path' => 'gps-batches/uploaded-gps.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses',
            'status' => 'Processed',
        ]);

        $this->assertSame('unknown', $batch->fresh()->data_origin);

        $verified = BatchUpload::create([
            'file_name' => 'verified-gps.csv',
            'stored_name' => 'verified-gps.csv',
            'file_path' => 'gps-batches/verified-gps.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'data_origin' => 'genuine',
            'bus_no' => 'Multiple Buses',
            'status' => 'Processed',
        ]);

        $this->assertSame('genuine', $verified->fresh()->data_origin);

        $directId = DB::table('batch_uploads')->insertGetId([
            'file_name' => 'seeded-demo.csv',
            'stored_name' => 'seeded-demo.csv',
            'file_path' => 'demo/seeded-demo.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses',
            'status' => 'Processed',
            'total_records' => 10,
            'processed_records' => 10,
            'failed_records' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            'unknown',
            DB::table('batch_uploads')->where('id', $directId)->value('data_origin')
        );
    }

    public function test_missing_eta_provenance_is_never_inferred_as_genuine(): void
    {
        Http::fake([
            '*/eta/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'source' => 'ml',
                'is_production_model' => true,
                'sample_count' => 362,
                'reason' => 'Legacy endpoint without provenance.',
            ]),
            '*/fuel/status' => Http::response($this->notReadyStatus()),
            '*/delay/status' => Http::response($this->notReadyStatus()),
            '*/inventory/status' => Http::response($this->notReadyStatus()),
            '*/operation/auto-scheduling/ai/training/status' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'bus_model_ready' => false,
                'driver_model_ready' => false,
                'bus_sample_count' => 0,
                'driver_sample_count' => 0,
            ]),
        ]);

        $models = collect(app(AiModelStatusService::class)->all())->keyBy('key');
        $eta = $models['eta'];

        $this->assertFalse($eta->ready);
        $this->assertSame('MODEL NOT READY', $eta->state);
        $this->assertSame('Unknown Source', $eta->data_source);
    }

    public function test_explicit_synthetic_eta_is_development_only_not_production_ready(): void
    {
        Http::fake([
            '*/eta/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'source' => 'ml',
                'data_source' => 'synthetic',
                'dataset_type' => 'SAMPLE / DEMO ETA DATA',
                'is_production_model' => false,
                'sample_count' => 362,
                'reason' => 'Development/demo model is loaded.',
            ]),
            '*/fuel/status' => Http::response($this->notReadyStatus()),
            '*/delay/status' => Http::response($this->notReadyStatus()),
            '*/inventory/status' => Http::response($this->notReadyStatus()),
            '*/operation/auto-scheduling/ai/training/status' => Http::response([
                'success' => true,
                'data_source' => 'genuine',
                'bus_model_ready' => false,
                'driver_model_ready' => false,
                'bus_sample_count' => 0,
                'driver_sample_count' => 0,
            ]),
        ]);

        $eta = collect(app(AiModelStatusService::class)->all())->keyBy('key')['eta'];

        $this->assertFalse($eta->ready);
        $this->assertSame('Development Only', $eta->state);
        $this->assertSame('Synthetic Data', $eta->data_source);
    }

    private function notReadyStatus(): array
    {
        return [
            'success' => true,
            'model_ready' => false,
            'data_source' => 'genuine',
            'dataset_type' => 'GENUINE GCT RECORDS',
            'is_production_model' => false,
            'sample_count' => 0,
            'reason' => 'MODEL NOT READY',
        ];
    }
}
