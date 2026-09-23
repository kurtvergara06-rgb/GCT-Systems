<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiModelStatusService
{
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
                    ->connectTimeout(1)
                    ->timeout(3)
                    ->get($nlpBaseUrl.'/eta/status'),
                $pool->as('fuel')
                    ->acceptJson()
                    ->connectTimeout(1)
                    ->timeout(3)
                    ->get($nlpBaseUrl.'/fuel/status'),
                $pool->as('delay')
                    ->acceptJson()
                    ->connectTimeout(1)
                    ->timeout(3)
                    ->get($nlpBaseUrl.'/delay/status'),
                $pool->as('inventory')
                    ->acceptJson()
                    ->connectTimeout(1)
                    ->timeout(3)
                    ->get($nlpBaseUrl.'/inventory/status'),
                $pool->as('scheduling')
                    ->acceptJson()
                    ->connectTimeout(1)
                    ->timeout(3)
                    ->get($operationBaseUrl.'/operation/auto-scheduling/ai/training/status'),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('AI model status pool failed.', [
                'exception' => $exception->getMessage(),
            ]);

            $responses = [];
        }

        $status = fn (string $key): ?array => $this->decode($responses[$key] ?? null, $key);

        return [
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

        // ETA became a genuine-only model before the shared provenance fields
        // existed. The Python API now exposes them, but this fallback keeps the
        // UI correct while mixed deployments roll forward.
        if ($key === 'eta' && $dataSource === '' && ($status['source'] ?? null) === 'ml') {
            $dataSource = 'genuine';
            $productionFlag = $modelReady;
        }

        $genuine = $dataSource === 'genuine';
        $productionReady = $modelReady && $genuine && $productionFlag;
        $sampleCount = (int) ($status['sample_count'] ?? $status['training_record_count'] ?? 0);
        $datasetType = trim((string) ($status['dataset_type'] ?? ''));
        if ($datasetType === '' && $genuine) {
            $datasetType = $expectedDataset;
        }

        if ($productionReady) {
            $state = 'Ready';
            $tone = 'ready';
        } elseif ($modelReady && ! $genuine) {
            $state = 'Development Only';
            $tone = 'warning';
        } else {
            $state = 'MODEL NOT READY';
            $tone = 'not-ready';
        }

        return (object) [
            'key' => $key,
            'name' => $name,
            'number' => $number,
            'icon' => $icon,
            'state' => $state,
            'tone' => $tone,
            'ready' => $productionReady,
            'reachable' => true,
            'data_source' => $genuine ? 'Genuine Data' : ($dataSource !== '' ? ucfirst($dataSource).' Data' : 'Unknown Source'),
            'dataset_type' => $datasetType !== '' ? $datasetType : 'Source not reported',
            'sample_count' => $sampleCount,
            'reason' => trim((string) ($status['reason'] ?? $status['message'] ?? 'No readiness reason reported.')),
            'details' => [],
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
        $dataSource = strtolower(trim((string) ($status['data_source'] ?? 'genuine')));
        $genuine = $dataSource === 'genuine';
        $fullyReady = $busReady && $driverReady && $genuine;
        $partiallyReady = ($busReady || $driverReady) && $genuine;

        $state = match (true) {
            $fullyReady => 'Ready',
            $partiallyReady => 'Partial ML',
            default => 'MODEL NOT READY',
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
            'reachable' => true,
            'data_source' => $genuine ? 'Genuine Data' : ucfirst($dataSource).' Data',
            'dataset_type' => (string) ($status['dataset_type'] ?? 'GENUINE GCT GPS + ATTENDANCE RECORDS'),
            'sample_count' => (int) ($status['bus_sample_count'] ?? 0) + (int) ($status['driver_sample_count'] ?? 0),
            'reason' => $this->schedulingReason($status, $fullyReady, $partiallyReady),
            'details' => [
                (object) [
                    'label' => 'Bus model',
                    'value' => $busReady ? 'ML Ready' : $this->sourceLabel($busSource),
                    'sample_count' => (int) ($status['bus_sample_count'] ?? 0),
                ],
                (object) [
                    'label' => 'Driver model',
                    'value' => $driverReady ? 'ML Ready' : $this->sourceLabel($driverSource),
                    'sample_count' => (int) ($status['driver_sample_count'] ?? 0),
                ],
            ],
        ];
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
            'reachable' => false,
            'data_source' => 'Unavailable',
            'dataset_type' => 'Status endpoint unavailable',
            'sample_count' => 0,
            'reason' => 'The Python engine status endpoint did not respond successfully.',
            'details' => [],
        ];
    }
}
