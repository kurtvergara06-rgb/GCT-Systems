<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiModelStatusService
{
    /** Current application records, separate from saved model training metadata. */
    public function operationalRecordsPresent(): array
    {
        $gps = DB::table('gps_trip_records')->exists();

        return [
            'eta' => $gps,
            'fuel' => DB::table('fuel_reports')->exists() || $gps,
            'delay' => $gps,
            'inventory' => DB::table('stock_movements')->exists(),
            'scheduling' => $gps || DB::table('trip_assignments')->exists()
                || DB::table('driver_attendances')->exists(),
        ];
    }

    /**
     * Query every production ML status endpoint in parallel and normalize the
     * results for the Analytics Overview. Synthetic/development models are
     * never reported as production-ready.
     *
     * @return array<int, object>
     */
    public function all(): array
    {
        $nlpBaseUrl = rtrim(
            (string) config('services.nlp.api_url', 'http://127.0.0.1:8000'),
            '/'
        );
        $operationBaseUrl = rtrim(
            (string) config('services.operation_ai.base_url', $nlpBaseUrl),
            '/'
        );

        try {
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('eta')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->get($nlpBaseUrl.'/eta/status'),
                $pool->as('fuel')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->get($nlpBaseUrl.'/fuel/status'),
                $pool->as('delay')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->get($nlpBaseUrl.'/delay/status'),
                $pool->as('inventory')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->get($nlpBaseUrl.'/inventory/status'),
                $pool->as('scheduling')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(12)
                    ->get($operationBaseUrl.'/operation/auto-scheduling/ai/training/status'),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AI model status pool failed.', [
                'exception' => $exception->getMessage(),
            ]);

            $responses = [];
        }

        $decoded = collect(['eta', 'fuel', 'delay', 'inventory', 'scheduling'])
            ->mapWithKeys(fn (string $key): array => [$key => $this->decode($responses[$key] ?? null, $key)])->all();
        $status = fn (string $key): ?array => $decoded[$key];

        $models = [
            $this->normalizeStandard(
                key: 'eta',
                name: 'ETA Model',
                number: '#1',
                icon: 'fa-clock',
                status: $status('eta'),
                expectedDataset: 'GENUINE GCT GPS RECORDS'
            ),
            $this->normalizeStandard(
                key: 'fuel',
                name: 'Fuel Model',
                number: '#2',
                icon: 'fa-gas-pump',
                status: $status('fuel'),
                expectedDataset: 'GENUINE GCT RECORDS'
            ),
            $this->normalizeStandard(
                key: 'delay',
                name: 'Delay Model',
                number: '#3',
                icon: 'fa-hourglass-half',
                status: $status('delay'),
                expectedDataset: 'GENUINE GCT RECORDS'
            ),
            $this->normalizeStandard(
                key: 'inventory',
                name: 'Inventory Model',
                number: '#4',
                icon: 'fa-boxes-stacked',
                status: $status('inventory'),
                expectedDataset: 'GENUINE GCT RECORDS'
            ),
            $this->normalizeScheduling($status('scheduling')),
        ];

        return array_map(fn (object $model): object => $this->pendingProvenance($model, $decoded[$model->key]), $models);
    }

    /** Endpoint claims are not independent audit approval. No prediction/artifact changes here. */
    private function pendingProvenance(object $model, ?array $status): object
    {
        $model->provenance_verified = false;
        $model->ready = false;
        $model->demo_ready = false;
        $model->sample_count = 0;
        $model->count_label = $status === null ? 'Data unavailable' : 'Verification Pending';
        $model->reported_sample_count = null;
        $model->availability = $status === null ? 'Service unavailable' : 'Service reachable';
        $model->data_source = 'Source Not Verified';
        $model->dataset_type = 'Training dataset not independently verified';
        $model->candidate_models = [];
        $model->model_version = '';
        $model->selected_model = '';
        $model->split_strategy = '';
        if ($status === null) {
            return $model;
        }

        $sampleSources = ['sample', 'synthetic', 'generated', 'demo', 'development'];
        $sample = collect(['data_source', 'source', 'training_source', 'model_source'])
            ->contains(fn (string $field): bool => in_array(strtolower(trim((string) ($status[$field] ?? ''))), $sampleSources, true));
        $model->state = 'Provenance Not Verified';
        $model->tone = 'not-ready';
        $model->data_source = $sample ? 'Sample / Synthetic Data Reported' : 'Source Not Verified';
        $model->reason = $sample
            ? 'This endpoint reports sample or synthetic training data. Conflicting genuine-data claims are not accepted; these rows are not verified operational evidence.'
            : 'Source-record IDs, original training snapshots and artifact lineage have not passed an independent provenance audit. Model availability does not establish production suitability.';

        if ($model->key === 'scheduling') {
            $model->availability .= (($status['bus_model_ready'] ?? false) === true || ($status['driver_model_ready'] ?? false) === true)
                ? ' · model component reported loaded' : ' · models not reported ready';
            foreach ($model->details as $index => $part) {
                $prefix = $index === 0 ? 'bus' : 'driver';
                $part->sample_count = 0;
                $part->reported_sample_count = max(0, (int) ($status[$prefix.'_sample_count'] ?? 0));
                $part->value = ($status[$prefix.'_model_ready'] ?? false) === true ? 'Reported loaded' : 'Reported fallback / not ready';
            }
            // GPS samples and driver-level rows are different populations: never sum them.
        } else {
            $model->availability .= ($status['model_ready'] ?? false) === true ? ' · model reported loaded' : ' · model not reported ready';
            $model->reported_sample_count = max(0, (int) ($status['sample_count'] ?? $status['training_record_count'] ?? 0));
        }

        return $model;
    }

    private function decode(mixed $response, string $key): ?array
    {
        if (! $response instanceof Response) {
            return null;
        }

        try {
            if (! $response->successful()) {
                Log::warning('AI model status endpoint returned a non-success response.', [
                    'model' => $key,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ($data['success'] ?? false) !== true) {
                return null;
            }

            return $data;
        } catch (\Throwable $exception) {
            Log::warning('AI model status response could not be decoded.', [
                'model' => $key,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizeStandard(
        string $key,
        string $name,
        string $number,
        string $icon,
        ?array $status,
        string $expectedDataset
    ): object {
        if ($status === null) {
            return $this->unavailable($key, $name, $number, $icon);
        }

        $modelReady = ($status['model_ready'] ?? false) === true;
        $dataSource = strtolower(trim((string) ($status['data_source'] ?? '')));
        $productionFlag = ($status['is_production_model'] ?? false) === true;

        // Fail closed. Missing provenance is unknown, never implicitly genuine.
        $genuine = $dataSource === 'genuine';
        $developmentSource = in_array(
            $dataSource,
            ['sample', 'synthetic', 'generated', 'demo', 'development'],
            true
        );
        $productionReady = $modelReady && $genuine && $productionFlag;
        $runtimeMode = strtolower(trim((string) ($status['runtime_mode'] ?? '')));
        $demoReady = ! app()->environment('production')
            && (bool) config('services.ai.allow_demo_models', false)
            && $modelReady
            && $developmentSource
            && in_array($runtimeMode, ['development', 'dev', 'demo', 'local', 'testing', 'test'], true);
        // Only verified genuine records may be counted on the production dashboard.
        // Sample, synthetic, or unknown provenance never contributes to readiness.
        $sampleCount = $genuine ? max(0, (int) ($status['sample_count'] ?? $status['training_record_count'] ?? 0)) : 0;
        $datasetType = trim((string) ($status['dataset_type'] ?? ''));
        if ($datasetType === '' && $genuine) {
            $datasetType = $expectedDataset;
        }

        if ($productionReady) {
            $state = 'Ready';
            $tone = 'ready';
        } elseif (! $genuine) {
            $state = $developmentSource ? 'No Training Data' : 'Data Not Verified';
            $tone = 'not-ready';
        } elseif ($sampleCount === 0) {
            $state = 'No Training Data';
            $tone = 'not-ready';
        } elseif (str_contains(strtolower((string) ($status['reason'] ?? $status['message'] ?? '')), 'insufficient')) {
            $state = 'Insufficient Data';
            $tone = 'warning';
        } else {
            $state = 'Not Trained';
            $tone = 'warning';
        }

        return (object) [
            'key' => $key,
            'name' => $name,
            'number' => $number,
            'icon' => $icon,
            'state' => $state,
            'tone' => $tone,
            'ready' => $productionReady,
            'demo_ready' => false,
            'reachable' => true,
            'data_source' => $genuine ? 'Genuine Data' : ($developmentSource ? 'No verified genuine data' : 'Unknown Source'),
            'dataset_type' => $genuine ? ($datasetType !== '' ? $datasetType : 'Source not reported') : 'No verified genuine training dataset',
            'sample_count' => $sampleCount,
            'reason' => ! $genuine
                ? 'Training records from this endpoint are not verified as genuine GCT operational data and are excluded from production readiness.'
                : trim((string) ($status['reason'] ?? $status['message'] ?? 'No readiness reason reported.')),
            'details' => [],
            'model_version' => $genuine ? trim((string) ($status['model_version'] ?? '')) : '',
            'split_strategy' => $genuine ? trim((string) ($status['split_strategy'] ?? '')) : '',
            'selected_model' => $genuine ? trim((string) data_get($status, 'selected_model.name', '')) : '',
            'candidate_models' => $genuine ? $this->normalizeCandidates($status['candidate_models'] ?? []) : [],
        ];
    }

    private function normalizeScheduling(?array $status): object
    {
        if ($status === null) {
            return $this->unavailable(
                'scheduling',
                'Auto Scheduling',
                'Operation AI',
                'fa-wand-magic-sparkles'
            );
        }

        $busReady = ($status['bus_model_ready'] ?? false) === true;
        $driverReady = ($status['driver_model_ready'] ?? false) === true;
        // Never assume provenance is genuine just because the API omitted it.
        $dataSource = strtolower(trim((string) ($status['data_source'] ?? '')));
        $genuine = $dataSource === 'genuine';
        $fullyReady = $busReady && $driverReady && $genuine;
        $partiallyReady = ($busReady || $driverReady) && $genuine;

        $busCount = $genuine ? max(0, (int) ($status['bus_sample_count'] ?? 0)) : 0;
        $driverCount = $genuine ? max(0, (int) ($status['driver_sample_count'] ?? 0)) : 0;
        $state = match (true) {
            $fullyReady => 'Ready',
            $partiallyReady => 'Partial ML',
            ! $genuine => 'Data Not Verified',
            ($busCount + $driverCount) === 0 => 'No Training Data',
            default => 'Insufficient Data',
        };

        $tone = match ($state) {
            'Ready' => 'ready',
            'Partial ML' => 'partial',
            default => 'not-ready',
        };

        $busSource = (string) ($status['bus_source'] ?? 'rule_fallback');
        $driverSource = (string) ($status['driver_source'] ?? 'rule_fallback');

        return (object) [
            'key' => 'scheduling',
            'name' => 'Auto Scheduling',
            'number' => 'Operation AI',
            'icon' => 'fa-wand-magic-sparkles',
            'state' => $state,
            'tone' => $tone,
            'ready' => $fullyReady,
            'demo_ready' => false,
            'reachable' => true,
            'data_source' => $genuine ? 'Genuine Data' : 'Unknown Source',
            'dataset_type' => $genuine ? (string) ($status['dataset_type'] ?? 'GENUINE GCT GPS + ATTENDANCE RECORDS') : 'No verified genuine training dataset',
            'sample_count' => $busCount + $driverCount,
            'reason' => $genuine
                ? $this->schedulingReason($status, $fullyReady, $partiallyReady)
                : 'Scheduling training-data provenance is not verified as genuine; no records are counted.',
            'details' => [
                (object) [
                    'label' => 'Bus model',
                    'value' => $busReady ? 'ML Ready' : $this->sourceLabel($busSource),
                    'sample_count' => $busCount,
                ],
                (object) [
                    'label' => 'Driver model',
                    'value' => $driverReady ? 'ML Ready' : $this->sourceLabel($driverSource),
                    'sample_count' => $driverCount,
                ],
            ],
            'model_version' => '',
            'split_strategy' => '',
            'selected_model' => '',
            'candidate_models' => [],
        ];
    }

    /**
     * @return array<int, object>
     */
    private function normalizeCandidates(mixed $candidates): array
    {
        if (! is_array($candidates)) {
            return [];
        }

        return collect($candidates)
            ->filter(fn (mixed $candidate): bool => is_array($candidate))
            ->map(function (array $candidate): object {
                $metrics = is_array($candidate['metrics'] ?? null)
                    ? $candidate['metrics']
                    : [];

                $numericMetric = static function (string $key) use ($metrics): ?float {
                    $value = $metrics[$key] ?? null;

                    return is_numeric($value) ? (float) $value : null;
                };

                return (object) [
                    'key' => trim((string) ($candidate['key'] ?? '')),
                    'name' => trim((string) ($candidate['name'] ?? 'Unnamed candidate')),
                    'family' => trim((string) ($candidate['family'] ?? 'Not reported')),
                    'selected' => ($candidate['selected'] ?? false) === true,
                    'status' => trim((string) ($candidate['status'] ?? 'not_evaluated')),
                    'reason' => trim((string) ($candidate['reason'] ?? '')),
                    'mae' => $numericMetric('mae'),
                    'rmse' => $numericMetric('rmse'),
                    'r2' => $numericMetric('r2'),
                ];
            })
            ->values()
            ->all();
    }

    private function schedulingReason(array $status, bool $fullyReady, bool $partiallyReady): string
    {
        if ($fullyReady) {
            return 'Bus and driver ranking models are ready from genuine GCT operational history.';
        }

        if ($partiallyReady) {
            return 'One scheduling ML component is ready; the other uses a transparent genuine-data fallback.';
        }

        $reasons = collect([
            $status['bus_reason'] ?? null,
            $status['driver_reason'] ?? null,
        ])->filter()->implode(' ');

        return $reasons !== ''
            ? $reasons
            : 'Scheduling ML components do not currently meet their readiness thresholds.';
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'data', 'data_fallback' => 'Data Fallback',
            'ml' => 'ML Ready',
            default => 'Rule Fallback',
        };
    }

    private function sourceDisplayName(string $source): string
    {
        return match ($source) {
            'genuine' => 'Genuine Data',
            'sample', 'synthetic', 'demo', 'generated', 'development' => 'Simulated Data',
            '' => 'Unknown Source',
            default => ucfirst($source).' Data',
        };
    }

    private function unavailable(string $key, string $name, string $number, string $icon): object
    {
        return (object) [
            'key' => $key,
            'name' => $name,
            'number' => $number,
            'icon' => $icon,
            'state' => 'Service Unavailable',
            'tone' => 'offline',
            'ready' => false,
            'demo_ready' => false,
            'reachable' => false,
            'data_source' => 'Unavailable',
            'dataset_type' => 'Status endpoint unavailable',
            'sample_count' => 0,
            'reason' => 'The Python engine status endpoint did not respond successfully.',
            'details' => [],
            'model_version' => '',
            'split_strategy' => '',
            'selected_model' => '',
            'candidate_models' => [],
        ];
    }
}
