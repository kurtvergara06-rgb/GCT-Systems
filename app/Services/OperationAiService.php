<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationAiService
{
    /**
     * Stop repeated waits during the same Laravel request after the
     * Python service has already timed out or returned an invalid response.
     */
    private bool $unavailableForCurrentRequest = false;

    /**
     * Ask the Python Operation AI service to recommend
     * a driver and bus or explain a scheduling conflict.
     */
    public function recommend(array $payload): ?array
    {
        return $this->request(
            endpoint: '/operation/auto-scheduling/ai/recommend',
            payload: $payload,
            requestName: 'recommendation'
        );
    }

    /**
     * Analyze an already selected driver and bus.
     */
    public function analyze(array $payload): ?array
    {
        return $this->request(
            endpoint: '/operation/auto-scheduling/ai/analyze',
            payload: $payload,
            requestName: 'analysis'
        );
    }

    /**
     * Perform a fail-fast request to the Python service.
     *
     * A scheduling request may contain several conflicts. Previously each
     * conflict could wait for the full timeout independently. Once one call
     * proves that the service is unavailable, later conflicts now use the
     * normal Laravel fallback immediately instead of repeating the delay.
     */
    private function request(
        string $endpoint,
        array $payload,
        string $requestName
    ): ?array {
        if ($this->unavailableForCurrentRequest) {
            return null;
        }

        $baseUrl = rtrim(
            (string) config(
                'services.operation_ai.base_url',
                'http://127.0.0.1:8000'
            ),
            '/'
        );

        // Keep AI optional and responsive. The normal scheduler remains the
        // source of truth when the Python service is sleeping or unreachable.
        $configuredTimeout = max(
            1,
            (int) config(
                'services.operation_ai.timeout',
                3
            )
        );

        $timeout = min($configuredTimeout, 3);
        $startedAt = microtime(true);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(1)
                ->timeout($timeout)
                ->post(
                    $baseUrl . $endpoint,
                    $payload
                );

            if (!$response->successful()) {
                $this->markUnavailable(
                    requestName: $requestName,
                    payload: $payload,
                    response: $response,
                    startedAt: $startedAt
                );

                return null;
            }

            $data = $response->json();

            if (!$this->isValidResponse($data)) {
                $this->unavailableForCurrentRequest = true;

                Log::warning(
                    "Operation AI returned an invalid {$requestName} response.",
                    [
                        'trip_id' => data_get($payload, 'trip.id'),
                        'duration_ms' => $this->durationMs($startedAt),
                        'response' => $data,
                    ]
                );

                return null;
            }

            Log::debug(
                "Operation AI {$requestName} completed.",
                [
                    'trip_id' => data_get($payload, 'trip.id'),
                    'duration_ms' => $this->durationMs($startedAt),
                ]
            );

            return $data;
        } catch (\Throwable $exception) {
            $this->unavailableForCurrentRequest = true;

            Log::warning(
                "Operation AI {$requestName} service is unavailable.",
                [
                    'trip_id' => data_get($payload, 'trip.id'),
                    'duration_ms' => $this->durationMs($startedAt),
                    'exception' => $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Validate the common response shape returned by both AI endpoints.
     */
    private function isValidResponse(mixed $data): bool
    {
        return is_array($data)
            && ($data['success'] ?? false) === true
            && is_array($data['analysis'] ?? null);
    }

    /**
     * Mark the service unavailable for the remainder of this web request.
     */
    private function markUnavailable(
        string $requestName,
        array $payload,
        Response $response,
        float $startedAt
    ): void {
        $this->unavailableForCurrentRequest = true;

        Log::warning(
            "Operation AI {$requestName} request failed.",
            [
                'status' => $response->status(),
                'trip_id' => data_get($payload, 'trip.id'),
                'duration_ms' => $this->durationMs($startedAt),
                'response' => $response->body(),
            ]
        );
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round(
            (microtime(true) - $startedAt) * 1000
        );
    }
}
