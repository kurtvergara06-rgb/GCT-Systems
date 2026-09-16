<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EtaPredictionService
{
    /**
     * Predict a single trip's duration via the ETA Random Forest model.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function predict(array $payload): ?array
    {
        $result = $this->predictBatch(['default' => $payload]);

        return $result['default'] ?? null;
    }

    /**
     * Predict trip durations in parallel via the ETA Random Forest model.
     *
     * Every configured request runs concurrently under a short, bounded
     * timeout so the Analytics page can never hang when the ML endpoint is
     * slow or offline. Returned map is keyed by the caller's payload keys;
     * any trip that fails resolves to null (never throws).
     *
     * @param  array<string, array<string, mixed>>  $keyedPayloads
     * @return array<string, array<string, mixed>|null>
     */
    public function predictBatch(array $keyedPayloads): array
    {
        if ($keyedPayloads === []) {
            return [];
        }

        $baseUrl = rtrim(
            (string) config(
                'services.nlp.api_url',
                'http://127.0.0.1:8000'
            ),
            '/'
        );

        try {
            $responses = Http::pool(function (Pool $pool) use ($keyedPayloads, $baseUrl): array {
                $requests = [];

                foreach ($keyedPayloads as $key => $payload) {
                    $requests[$key] = $pool->as($key)
                        ->acceptJson()
                        ->asJson()
                        ->connectTimeout(1)
                        ->timeout(3)
                        ->post($baseUrl.'/eta/predict', $payload);
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            Log::warning(
                'ETA ML prediction pool failed.',
                ['exception' => $exception->getMessage()]
            );

            return array_fill_keys(array_keys($keyedPayloads), null);
        }

        $results = [];

        foreach ($keyedPayloads as $key => $payload) {
            try {
                $response = $responses[$key] ?? null;

                if (! $response instanceof Response || ! $response->successful()) {
                    $results[$key] = null;

                    continue;
                }

                $data = $response->json();

                if (! is_array($data) || ($data['success'] ?? false) !== true) {
                    $results[$key] = null;

                    continue;
                }

                $results[$key] = $data;
            } catch (\Throwable $exception) {
                Log::warning(
                    'ETA ML prediction failed for a trip.',
                    [
                        'key' => $key,
                        'exception' => $exception->getMessage(),
                    ]
                );

                $results[$key] = null;
            }
        }

        return $results;
    }
}
