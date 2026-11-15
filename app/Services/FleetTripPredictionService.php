<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FleetTripPredictionService
{
    public function predict(array $payload): ?array
    {
        $baseUrl = rtrim(
            (string) config(
                'services.nlp.api_url',
                'http://127.0.0.1:8000'
            ),
            '/'
        );

        $startedAt = microtime(true);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(1)
                ->timeout(5)
                ->post(
                    $baseUrl . '/analytics/fleet-trip/predict',
                    $payload
                );

            if (! $response->successful()) {
                Log::warning(
                    'Fleet & Trip prediction service request failed.',
                    [
                        'status' => $response->status(),
                        'duration_ms' => $this->durationMs($startedAt),
                        'response' => $response->body(),
                    ]
                );

                return null;
            }

            $data = $response->json();

            if (
                ! is_array($data)
                || ($data['success'] ?? false) !== true
                || ! is_array($data['predictions'] ?? null)
                || ! is_array($data['peak_periods'] ?? null)
            ) {
                Log::warning(
                    'Fleet & Trip prediction service returned an invalid response.',
                    [
                        'duration_ms' => $this->durationMs($startedAt),
                        'response' => $data,
                    ]
                );

                return null;
            }

            return $data;
        } catch (\Throwable $exception) {
            Log::warning(
                'Fleet & Trip prediction service is unavailable.',
                [
                    'duration_ms' => $this->durationMs($startedAt),
                    'exception' => $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round(
            (microtime(true) - $startedAt) * 1000
        );
    }
}
