<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationAiService
{
    /**
     * Ask the Python Operation AI service to recommend
     * a driver and bus or explain a scheduling conflict.
     */
    public function recommend(array $payload): ?array
    {
        $baseUrl = rtrim(
            (string) config(
                'services.operation_ai.base_url',
                'http://127.0.0.1:8000'
            ),
            '/'
        );

        $timeout = max(
            1,
            (int) config(
                'services.operation_ai.timeout',
                5
            )
        );

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout($timeout)
                ->retry(
                    times: 1,
                    sleepMilliseconds: 200,
                    throw: false
                )
                ->post(
                    $baseUrl
                    . '/operation/auto-scheduling/ai/recommend',
                    $payload
                );

            if (!$response->successful()) {
                Log::warning(
                    'Operation AI recommendation request failed.',
                    [
                        'status' => $response->status(),
                        'trip_id' => data_get(
                            $payload,
                            'trip.id'
                        ),
                        'response' => $response->body(),
                    ]
                );

                return null;
            }

            $data = $response->json();

            if (
                !is_array($data)
                || ($data['success'] ?? false) !== true
                || !is_array($data['analysis'] ?? null)
            ) {
                Log::warning(
                    'Operation AI returned an invalid recommendation response.',
                    [
                        'trip_id' => data_get(
                            $payload,
                            'trip.id'
                        ),
                        'response' => $data,
                    ]
                );

                return null;
            }

            return $data;
        } catch (\Throwable $exception) {
            Log::warning(
                'Operation AI service is unavailable.',
                [
                    'trip_id' => data_get(
                        $payload,
                        'trip.id'
                    ),
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Analyze an already selected driver and bus.
     */
    public function analyze(array $payload): ?array
    {
        $baseUrl = rtrim(
            (string) config(
                'services.operation_ai.base_url',
                'http://127.0.0.1:8000'
            ),
            '/'
        );

        $timeout = max(
            1,
            (int) config(
                'services.operation_ai.timeout',
                5
            )
        );

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout($timeout)
                ->retry(
                    times: 1,
                    sleepMilliseconds: 200,
                    throw: false
                )
                ->post(
                    $baseUrl
                    . '/operation/auto-scheduling/ai/analyze',
                    $payload
                );

            if (!$response->successful()) {
                Log::warning(
                    'Operation AI analysis request failed.',
                    [
                        'status' => $response->status(),
                        'trip_id' => data_get(
                            $payload,
                            'trip.id'
                        ),
                        'response' => $response->body(),
                    ]
                );

                return null;
            }

            $data = $response->json();

            if (
                !is_array($data)
                || ($data['success'] ?? false) !== true
                || !is_array($data['analysis'] ?? null)
            ) {
                Log::warning(
                    'Operation AI returned an invalid analysis response.',
                    [
                        'trip_id' => data_get(
                            $payload,
                            'trip.id'
                        ),
                        'response' => $data,
                    ]
                );

                return null;
            }

            return $data;
        } catch (\Throwable $exception) {
            Log::warning(
                'Operation AI analysis service is unavailable.',
                [
                    'trip_id' => data_get(
                        $payload,
                        'trip.id'
                    ),
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            return null;
        }
    }
}