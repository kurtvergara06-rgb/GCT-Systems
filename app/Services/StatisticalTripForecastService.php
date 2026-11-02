<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Conservative, database-backed statistical fallback when FastAPI is offline.
 * This is not a trained machine-learning model.
 */
class StatisticalTripForecastService
{
    public function forecast(array $records, array $targets): array
    {
        $history = collect($records)
            ->filter(fn (array $record): bool => (float) ($record['duration_minutes'] ?? 0) > 0
                && $this->routeKey((string) ($record['route'] ?? '')) !== '')
            ->groupBy(fn (array $record): string => $this->routeKey((string) $record['route']));

        $predictions = [];
        $eligibility = [];

        foreach ($targets as $target) {
            $route = (string) ($target['route'] ?? '');
            $key = $this->routeKey($route);
            $samples = $key !== '' ? $history->get($key, collect()) : collect();
            $count = $samples->count();
            $eligible = $count >= 5;
            $eligibility[] = [
                'trip_code' => $target['trip_code'] ?? 'Scheduled Trip',
                'route' => $route,
                'normalized_route' => $key,
                'route_history_count' => $count,
                'same_hour_count' => 0,
                'same_weekday_hour_count' => 0,
                'eligible' => $eligible,
                'method' => $eligible ? 'laravel-route-median' : 'insufficient-history',
            ];

            if (! $eligible || empty($target['departure_at'])) {
                continue;
            }

            $durations = $samples->pluck('duration_minutes')
                ->map(fn ($value): float => (float) $value)
                ->sort()->values();
            $baseline = $this->median($durations);
            if ($baseline <= 0) {
                continue;
            }

            // Historical proportion exceeding 120% of the route median:
            // an evidence-based risk indicator, not a calibrated ML probability.
            $risk = round($durations->filter(fn (float $duration): bool => $duration > $baseline * 1.2)->count() / $count * 100, 1);
            $departure = Carbon::parse($target['departure_at']);
            $predictions[] = [
                'trip_code' => $target['trip_code'] ?? 'Scheduled Trip',
                'route' => $route,
                'bus_no' => $target['bus_no'] ?? null,
                'departure_at' => $departure->toIso8601String(),
                'predicted_duration_minutes' => round($baseline, 1),
                'estimated_arrival_at' => $departure->copy()->addSeconds((int) round($baseline * 60))->toIso8601String(),
                'delay_risk_percent' => $risk,
                'risk_level' => $risk >= 50 ? 'High' : ($risk >= 25 ? 'Medium' : 'Low'),
                'sample_size' => $count,
                'method' => 'laravel-route-median',
                'baseline_duration_minutes' => round($baseline, 1),
            ];
        }

        return [
            'success' => true,
            'model' => 'laravel-statistical-fallback-v1',
            'historical_records' => count($records),
            'target_count' => count($targets),
            'predicted_target_count' => count($predictions),
            'predictions' => $predictions,
            'eligibility' => $eligibility,
            'peak_periods' => [],
        ];
    }

    private function routeKey(string $route): string
    {
        // Deliberately exact after case/whitespace normalization: never
        // match unrelated routes through fuzzy similarity.
        return mb_strtolower(trim((string) preg_replace('/\\s+/u', ' ', $route)));
    }

    private function median(Collection $sorted): float
    {
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $sorted->get($middle)
            : (((float) $sorted->get($middle - 1) + (float) $sorted->get($middle)) / 2);
    }
}
