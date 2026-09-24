<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InventoryPredictionService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function predict(array $payload): ?array
    {
        return $this->post('/inventory/predict', $payload);
    }

    /**
     * Predict multiple inventory items concurrently while preserving caller keys.
     *
     * @param  array<string, array<string, mixed>>  $keyedPayloads
     * @return array<string, array<string, mixed>|null>
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
                    $requests[$key] = $pool->as($key)
                        ->acceptJson()
                        ->asJson()
                        ->connectTimeout(1)
                        ->timeout(3)
                        ->post($baseUrl.'/inventory/predict', $payload);
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            Log::warning('Inventory ML prediction pool failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return array_fill_keys(array_keys($keyedPayloads), null);
        }

        $results = [];

        foreach ($keyedPayloads as $key => $payload) {
            try {
                $response = $responses[$key] ?? null;

                if (! $response instanceof Response) {
                    $results[$key] = null;
                    continue;
                }

                $results[$key] = $this->decode($response, '/inventory/predict');
            } catch (\Throwable $exception) {
                Log::warning('Inventory ML prediction failed for an item.', [
                    'key' => $key,
                    'exception' => $exception->getMessage(),
                ]);

                $results[$key] = null;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(): ?array
    {
        return $this->get('/inventory/status');
    }

    public function isReady(): bool
    {
        return ($this->status()['model_ready'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function post(string $endpoint, array $payload): ?array
    {
        try {
            $response = $this->client()->post($this->baseUrl().$endpoint, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Inventory ML request failed.', [
                'endpoint' => $endpoint,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return $this->decode($response, $endpoint);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get(string $endpoint): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl().$endpoint);
        } catch (\Throwable $exception) {
            Log::warning('Inventory ML request failed.', [
                'endpoint' => $endpoint,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return $this->decode($response, $endpoint);
    }

    private function client()
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(1)
            ->timeout(3);
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config('services.nlp.api_url', 'http://127.0.0.1:8000'),
            '/'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Response $response, string $endpoint): ?array
    {
        if (! $response->successful()) {
            Log::warning('Inventory ML endpoint returned a non-success status.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['success'] ?? false) !== true) {
            return null;
        }

        return $data;
    }
}
