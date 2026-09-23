<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FuelPredictionService
{
    /**
     * Predict fuel consumption for one linked GPS trip.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function predict(array $payload): ?array
    {
        $results = $this->predictBatch(['default' => $payload]);

        return $results['default'] ?? null;
    }

    /**
     * Predict multiple trips in parallel. Individual failures resolve to null
     * and never fail the entire analytics page.
     *
     * @param  array<string|int, array<string, mixed>>  $keyedPayloads
     * @return array<string|int, array<string, mixed>|null>
     */
    public function predictBatch(array $keyedPayloads): array
    {
        if ($keyedPayloads === []) {
            return [];
        }

        $baseUrl = $this->baseUrl();

        try {
            $responses = Http::pool(function (Pool $pool) use ($keyedPayloads, $baseUrl): array {
                $requests = [];

                foreach ($keyedPayloads as $key => $payload) {
                    $requests[$key] = $pool->as((string) $key)
                        ->acceptJson()
                        ->asJson()
                        ->connectTimeout(1)
                        ->timeout(3)
                        ->post($baseUrl.'/fuel/predict', $payload);
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            Log::warning('Fuel ML prediction pool failed.', [
                'exception' => $exception->getMessage(),
            ]);

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
                $results[$key] = is_array($data) && ($data['success'] ?? false) === true
                    ? $data
                    : null;
            } catch (\Throwable $exception) {
                Log::warning('Fuel ML prediction failed for a trip.', [
                    'key' => $key,
                    'exception' => $exception->getMessage(),
                ]);
                $results[$key] = null;
            }
        }

        return $results;
    }

    /** @return array<string, mixed>|null */
    public function status(): ?array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(1)
                ->timeout(3)
                ->get($this->baseUrl().'/fuel/status');
        } catch (\Throwable $exception) {
            Log::warning('Fuel ML status request failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) && ($data['success'] ?? false) === true
            ? $data
            : null;
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config('services.nlp.api_url', 'http://127.0.0.1:8000'),
            '/'
        );
    }
}
