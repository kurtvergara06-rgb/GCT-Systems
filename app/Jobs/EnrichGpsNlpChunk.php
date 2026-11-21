<?php

namespace App\Jobs;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnrichGpsNlpChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 45;

    public function __construct(public int $batchId, public int $afterId = 0)
    {
        // Dedicated single-worker queue: do not increase concurrency on a 512MB service.
        $this->onConnection('database')->onQueue('gps-nlp');
    }

    public static function enqueueIfEnabled(int $batchId): void
    {
        if (! config('gps_nlp.enabled') || ! config('gps_nlp.token')) {
            return;
        }

        try {
            self::dispatch($batchId);
        } catch (\Throwable $exception) {
            // Optional analytics must never fail the operational GPS import.
            Log::warning('Optional GPS NLP queue dispatch failed', [
                'batch_id' => $batchId, 'error' => $exception->getMessage(),
            ]);
        }
    }

    public function handle(): void
    {
        if (! config('gps_nlp.enabled') || ! config('gps_nlp.token')) {
            return;
        }

        $batch = BatchUpload::find($this->batchId);
        if (! $batch || $batch->module !== 'Operation' || $batch->data_type !== 'GPS Trip Records'
            || in_array($batch->status, ['Processing', 'Failed'], true)) {
            return;
        }

        $size = (int) config('gps_nlp.batch_size', 25);
        $records = GpsTripRecord::query()
            ->where('batch_upload_id', $this->batchId)
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit($size)
            ->get(['id', 'description', 'bus_no', 'grouping',
                'duration_minutes', 'total_minutes', 'in_motion_minutes',
                'idling_minutes', 'mileage_km', 'engine_hours']);

        if ($records->isEmpty()) {
            return;
        }

        $payload = $records->map(fn (GpsTripRecord $record): array => [
            'id' => $record->id,
            'description' => mb_substr((string) $record->description, 0, 1200),
            'bus_no' => mb_substr((string) $record->bus_no, 0, 80),
            'grouping' => mb_substr((string) $record->grouping, 0, 120),
            'duration_minutes' => $record->duration_minutes === null ? null : (float) $record->duration_minutes,
            'total_minutes' => $record->total_minutes === null ? null : (float) $record->total_minutes,
            'in_motion_minutes' => $record->in_motion_minutes === null ? null : (float) $record->in_motion_minutes,
            'idling_minutes' => $record->idling_minutes === null ? null : (float) $record->idling_minutes,
            'mileage_km' => $record->mileage_km === null ? null : (float) $record->mileage_km,
            'engine_hours' => $record->engine_hours === null ? null : (float) $record->engine_hours,
        ])->all();

        try {
            $url = rtrim((string) config('gps_nlp.url'), '/').'/nlp/enrich-gps-batch';
            $response = Http::withHeaders([
                'X-GCT-NLP-Token' => (string) config('gps_nlp.token'),
            ])->connectTimeout(3)->timeout((int) config('gps_nlp.timeout', 8))
                ->retry(2, 500)
                ->post($url, ['records' => $payload]);

            $response->throw();
            $data = $response->json();
            if (! is_array($data) || ($data['success'] ?? false) !== true || ! is_array($data['records'] ?? null)) {
                throw new \RuntimeException('Invalid NLP enrichment response.');
            }
            $allowedIds = $records->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
            $receivedIds = [];
            foreach ($data['records'] as $result) {
                if (! is_array($result) || ! isset($result['id'])
                    || ! isset($allowedIds[(int) $result['id']])
                    || ($result['success'] ?? false) !== true) {
                    throw new \RuntimeException('NLP response contained an invalid or unsuccessful record.');
                }
                $id = (int) $result['id'];
                if (isset($receivedIds[$id])) {
                    throw new \RuntimeException('NLP response contained duplicate record IDs.');
                }
                $receivedIds[$id] = true;
            }
            if (count($receivedIds) !== count($allowedIds)) {
                throw new \RuntimeException('NLP response was incomplete; enrichment paused for safe retry.');
            }
            $now = now();
            foreach ($data['records'] as $result) {
                if (! is_array($result) || ($result['success'] ?? false) !== true
                    || ! isset($result['id']) || ! isset($allowedIds[(int) $result['id']])) {
                    continue;
                }
                DB::table('gps_nlp_enrichments')->updateOrInsert(
                    ['gps_trip_record_id' => (int) $result['id']],
                    [
                        'batch_upload_id' => $this->batchId,
                        'source' => 'operational_rules',
                        'entities' => json_encode($result['entities'] ?? []),
                        'severity_suggestion' => json_encode($result['severity'] ?? []),
                        'anomaly_suggestion' => json_encode($result['anomaly'] ?? []),
                        'updated_at' => $now,
                    ]
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('Optional GPS NLP enrichment stopped; original GPS data unchanged', [
                'batch_id' => $this->batchId,
                'after_id' => $this->afterId,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        // A single chain of tiny jobs avoids holding thousands of records in RAM.
        if ($records->count() === $size) {
            try {
                self::dispatch($this->batchId, (int) $records->last()->id);
            } catch (\Throwable $exception) {
                Log::warning('Optional GPS NLP continuation could not be queued', [
                    'batch_id' => $this->batchId, 'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
