<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelayPredictionService
{
    /**
     * Predict a scheduled trip's expected arrival delay (minutes) via the
     * Model #3 delay Random Forest served by the same Python engine as ETA.
     *
     * The frontend-friendly keys (predicted_delay_minutes, risk_level,
     * model_source, ready) are aliases the API already returns next to the
     * original keys (predicted_arrival_delay_minutes, risk_status,
     * data_source, model_ready), so no re-mapping is needed here.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function predict(array $payload): ?array
    {
        return $this->post('/delay/predict', $payload);
    }

    /**
     * Predict multiple scheduled trips concurrently.
     *
     * The returned map keeps the caller's keys. A failed trip resolves to
     * null without failing the rest of the pool, so Analytics can degrade
     * gracefully when one prediction is unavailable.
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
                        ->post($baseUrl.'/delay/predict', $payload);
                }

                return $requests;
            });
        } catch (\Throwable $exception) {
            Log::warning(
                'Delay ML prediction pool failed.',
                ['exception' => $exception->getMessage()]
            );

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

                $results[$key] = $this->decode($response, '/delay/predict');
            } catch (\Throwable $exception) {
                Log::warning(
                    'Delay ML prediction failed for a trip.',
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

    /**
     * Query the delay model's readiness / data-sufficiency status.
     *
     * @return array<string, mixed>|null
     */
    public function status(): ?array
    {
        return $this->get('/delay/status');
    }

    /**
     * True when the delay model reports it is ready to serve predictions.
     */
    public function isReady(): bool
    {
        $status = $this->status();

        if ($status === null) {
            Log::warning('Delay model status unavailable - treated as not ready.');

            return false;
        }

        return ($status['model_ready'] ?? false) === true;
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
            Log::warning(
                'Delay ML request failed.',
                ['endpoint' => $endpoint, 'exception' => $exception->getMessage()]
            );

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
            Log::warning(
                'Delay ML request failed.',
                ['endpoint' => $endpoint, 'exception' => $exception->getMessage()]
            );

            return null;
        }

        return $this->decode($response, $endpoint);
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
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
            Log::warning(
                'Delay ML endpoint returned a non-success status.',
                ['endpoint' => $endpoint, 'status' => $response->status()]
            );

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        // The delay API uses "success": true for both predictions and status.
        if (($data['success'] ?? false) !== true) {
            Log::warning(
                'Delay ML endpoint response missing success flag.',
                ['endpoint' => $endpoint]
            );

            return null;
        }

        return $data;
    }
}
