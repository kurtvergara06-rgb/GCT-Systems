<?php

namespace App\Services;

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

        // The delay API uses "success": pred for predictions and "success":
        // true for status; both are valid here.
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