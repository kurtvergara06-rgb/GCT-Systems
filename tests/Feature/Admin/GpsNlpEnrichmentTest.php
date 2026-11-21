<?php

namespace Tests\Feature\Admin;

use App\Jobs\EnrichGpsNlpChunk;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GpsNlpEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private function sampleBatch(): BatchUpload
    {
        return BatchUpload::create([
            'file_name' => 'gps.csv', 'stored_name' => 'gps.csv',
            'file_path' => 'gps.csv', 'file_type' => 'csv',
            'module' => 'Operation', 'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses', 'status' => 'In Review',
        ]);
    }

    public function test_enrichment_is_disabled_by_default(): void
    {
        config()->set('gps_nlp.enabled', false);
        config()->set('gps_nlp.token', 'test-token');
        Queue::fake();
        EnrichGpsNlpChunk::enqueueIfEnabled(1);
        Queue::assertNothingPushed();
    }

    public function test_enrichment_stores_advisory_results_without_changing_gps_records(): void
    {
        config()->set('gps_nlp.enabled', true);
        config()->set('gps_nlp.token', 'test-token');
        config()->set('gps_nlp.url', 'https://internal.example');
        $batch = $this->sampleBatch();
        $record = GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'bus_no' => 'BUS001',
            'description' => 'Engine idling',
            'severity' => 'Normal',
        ]);
        Http::fake(['https://internal.example/nlp/enrich-gps-batch' => Http::response([
            'success' => true,
            'records' => [[
                'id' => $record->id, 'success' => true,
                'entities' => ['events' => [['label' => 'IDLING_EVENT', 'text' => 'idling']]],
                'severity' => ['level' => 'Medium'],
                'anomaly' => ['is_anomaly' => true],
            ]],
        ])]);

        (new EnrichGpsNlpChunk($batch->id))->handle();

        $this->assertDatabaseHas('gps_nlp_enrichments', [
            'gps_trip_record_id' => $record->id,
            'batch_upload_id' => $batch->id,
            'source' => 'operational_rules',
        ]);
        $this->assertSame('Normal', $record->fresh()->severity);
        $this->assertSame('Engine idling', $record->fresh()->description);
        $this->assertTrue((bool) data_get(json_decode(DB::table('gps_nlp_enrichments')
            ->where('gps_trip_record_id', $record->id)->value('anomaly_suggestion'), true), 'is_anomaly'));
    }

    public function test_python_failure_does_not_modify_existing_gps_records(): void
    {
        config()->set('gps_nlp.enabled', true);
        config()->set('gps_nlp.token', 'test-token');
        config()->set('gps_nlp.url', 'https://internal.example');
        $batch = $this->sampleBatch();
        $record = GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'bus_no' => 'BUS002',
            'severity' => 'Normal',
        ]);
        Http::fake(['https://internal.example/nlp/enrich-gps-batch' => Http::response([], 503)]);

        (new EnrichGpsNlpChunk($batch->id))->handle();

        $this->assertSame('Normal', $record->fresh()->severity);
        $this->assertSame('In Review', $batch->fresh()->status);
        $this->assertDatabaseCount('gps_nlp_enrichments', 0);
    }
}
