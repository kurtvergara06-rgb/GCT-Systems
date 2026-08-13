<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FleetTripAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->normalizePeriod((string) $request->input('period', 'this-month'));
        $selectedBus = strtoupper(trim((string) $request->input('bus', 'all')));
        [$periodStart, $periodEnd, $periodLabel] = $this->periodBounds($period);

        $records = $this->recordsQuery($periodStart, $periodEnd, $selectedBus)
            ->orderBy('beginning_at')
            ->get();

        $totalDistance = (float) $records->sum('mileage_km');
        $totalIdleMinutes = (int) $records->sum('idling_minutes');
        $totalMotionMinutes = (int) $records->sum('in_motion_minutes');

        $durationValues = $records
            ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
            ->filter(fn (float $minutes) => $minutes > 0);

        $totalDurationMinutes = (float) $durationValues->sum();
        $averageTripDuration = $durationValues->isNotEmpty()
            ? (float) $durationValues->average()
            : 0;

        $speedMinutes = $totalMotionMinutes > 0
            ? $totalMotionMinutes
            : $totalDurationMinutes;

        $averageSpeed = $speedMinutes > 0
            ? $totalDistance / ($speedMinutes / 60)
            : 0;

        $totalBuses = Bus::query()->count();
        $activeBuses = Bus::query()->where('status', 'Active')->count();
        $underMaintenance = Bus::query()->where('status', 'Under Maintenance')->count();
        $inactiveBuses = Bus::query()->where('status', 'Inactive')->count();

        $fleetAvailability = $totalBuses > 0
            ? ($activeBuses / $totalBuses) * 100
            : 0;

        [$previousStart, $previousEnd] = $this->previousPeriodBounds($periodStart, $periodEnd);
        $previousTripCount = $this->recordsQuery($previousStart, $previousEnd, $selectedBus)->count();
        $currentTripCount = $records->count();

        $tripGrowth = $previousTripCount > 0
            ? (($currentTripCount - $previousTripCount) / $previousTripCount) * 100
            : null;

        $trend = $this->buildTrend($records, $periodStart, $periodEnd);
        $routes = $this->buildRoutePerformance($records);

        $busLookup = Bus::query()
            ->orderBy('bus_no')
            ->get()
            ->keyBy(fn (Bus $bus) => strtoupper(trim($bus->bus_no)));

        $busActivity = $this->buildBusActivity($records, $busLookup);
        $diagnostics = $this->buildDiagnostics($records);

        return view('Admin.Analytics.fleet-trip', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'selectedBus' => $selectedBus,
            'busOptions' => $busLookup->values(),
            'tripCount' => $currentTripCount,
            'totalDistance' => $totalDistance,
            'totalIdleMinutes' => $totalIdleMinutes,
            'averageTripDuration' => $averageTripDuration,
            'averageSpeed' => $averageSpeed,
            'totalBuses' => $totalBuses,
            'activeBuses' => $activeBuses,
            'underMaintenance' => $underMaintenance,
            'inactiveBuses' => $inactiveBuses,
            'fleetAvailability' => $fleetAvailability,
            'tripGrowth' => $tripGrowth,
            'trend' => $trend,
            'routes' => $routes,
            'busActivity' => $busActivity,
            'diagnostics' => $diagnostics,
        ]);
    }

    private function recordsQuery(Carbon $start, Carbon $end, string $selectedBus)
    {
        $query = GpsTripRecord::query()
            ->whereBetween('beginning_at', [$start, $end])
            ->whereHas('batchUpload', function ($batchQuery): void {
                $batchQuery->where('status', 'Processed');
            });

        if ($selectedBus !== '' && $selectedBus !== 'ALL') {
            $query->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus]);
        }

        return $query;
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['this-month', 'last-30-days', 'last-3-months', 'this-year'], true)
            ? $period
            : 'this-month';
    }

    private function periodBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            'last-30-days' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                'Last 30 Days',
            ],
            'last-3-months' => [
                $now->copy()->subMonths(3)->startOfDay(),
                $now->copy()->endOfDay(),
                'Last 3 Months',
            ],
            'this-year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfDay(),
                'This Year',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
                'This Month',
            ],
        };
    }

    private function previousPeriodBounds(Carbon $start, Carbon $end): array
    {
        $durationSeconds = max(1, $start->diffInSeconds($end) + 1);
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subSeconds($durationSeconds - 1);

        return [$previousStart, $previousEnd];
    }

    private function durationMinutes(GpsTripRecord $record): float
    {
        $duration = (float) ($record->duration_minutes ?? 0);

        if ($duration > 0) {
            return $duration;
        }

        $total = (float) ($record->total_minutes ?? 0);

        if ($total > 0) {
            return $total;
        }

        if ($record->beginning_at && $record->ending_at) {
            return (float) $record->beginning_at->diffInMinutes($record->ending_at);
        }

        return 0;
    }

    private function recordSpeed(GpsTripRecord $record): float
    {
        $distance = (float) ($record->mileage_km ?? 0);
        $motionMinutes = (float) ($record->in_motion_minutes ?? 0);

        if ($distance <= 0) {
            return 0;
        }

        if ($motionMinutes <= 0) {
            $motionMinutes = $this->durationMinutes($record);
        }

        return $motionMinutes > 0
            ? $distance / ($motionMinutes / 60)
            : 0;
    }

    private function buildTrend(Collection $records, Carbon $start, Carbon $end): Collection
    {
        $totalDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $daysPerBucket = (int) ceil($totalDays / 4);
        $buckets = collect();

        for ($index = 0; $index < 4; $index++) {
            $bucketStart = $start->copy()->startOfDay()->addDays($index * $daysPerBucket);

            if ($bucketStart->greaterThan($end)) {
                $buckets->push((object) [
                    'label' => '—',
                    'count' => 0,
                    'height' => 0,
                ]);
                continue;
            }

            $bucketEnd = $bucketStart->copy()->addDays($daysPerBucket - 1)->endOfDay();

            if ($bucketEnd->greaterThan($end)) {
                $bucketEnd = $end->copy();
            }

            $count = $records->filter(function (GpsTripRecord $record) use ($bucketStart, $bucketEnd): bool {
                return $record->beginning_at
                    && $record->beginning_at->betweenIncluded($bucketStart, $bucketEnd);
            })->count();

            $buckets->push((object) [
                'label' => $bucketStart->format('M j'),
                'count' => $count,
                'height' => 0,
            ]);
        }

        $maxCount = max(1, (int) $buckets->max('count'));

        return $buckets->map(function ($bucket) use ($maxCount) {
            $bucket->height = $bucket->count > 0
                ? max(10, (int) round(($bucket->count / $maxCount) * 96))
                : 0;

            return $bucket;
        });
    }

    private function buildRoutePerformance(Collection $records): Collection
    {
        $groups = $records->groupBy(fn (GpsTripRecord $record) => $this->routeLabel($record));
        $totalTrips = max(1, $records->count());

        $routes = $groups
            ->map(function (Collection $routeRecords, string $label) use ($totalTrips) {
                $durations = $routeRecords
                    ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
                    ->filter(fn (float $minutes) => $minutes > 0);

                return (object) [
                    'label' => $label,
                    'trips' => $routeRecords->count(),
                    'average_duration' => $durations->isNotEmpty() ? (float) $durations->average() : 0,
                    'share' => ($routeRecords->count() / $totalTrips) * 100,
                    'progress' => 0,
                ];
            })
            ->sortByDesc('trips')
            ->take(4)
            ->values();

        $maxTrips = max(1, (int) $routes->max('trips'));

        return $routes->map(function ($route) use ($maxTrips) {
            $route->progress = (int) round(($route->trips / $maxTrips) * 100);

            return $route;
        });
    }

    private function routeLabel(GpsTripRecord $record): string
    {
        $initial = trim((string) $record->initial_location);
        $final = trim((string) $record->final_location);

        if ($initial !== '' && $final !== '') {
            return "{$initial} - {$final}";
        }

        $grouping = trim((string) $record->grouping);

        return $grouping !== '' ? $grouping : 'Unspecified Route';
    }

    private function buildBusActivity(Collection $records, Collection $busLookup): Collection
    {
        $totalTrips = max(1, $records->count());

        return $records
            ->filter(fn (GpsTripRecord $record) => trim((string) $record->bus_no) !== '')
            ->groupBy(fn (GpsTripRecord $record) => strtoupper(trim($record->bus_no)))
            ->map(function (Collection $busRecords, string $busNo) use ($totalTrips, $busLookup) {
                $distance = (float) $busRecords->sum('mileage_km');
                $trips = $busRecords->count();
                $bus = $busLookup->get($busNo);

                return (object) [
                    'bus' => $bus?->bus_no ?? $busNo,
                    'trips' => $trips,
                    'distance' => $distance,
                    'average_trip_distance' => $trips > 0 ? $distance / $trips : 0,
                    'share' => ($trips / $totalTrips) * 100,
                    'status' => $bus?->status ?? 'Unregistered',
                ];
            })
            ->sortByDesc('trips')
            ->take(5)
            ->values();
    }

    private function buildDiagnostics(Collection $records): object
    {
        $routeBaselines = $records
            ->groupBy(fn (GpsTripRecord $record) => $this->routeLabel($record))
            ->map(function (Collection $routeRecords) {
                $durations = $routeRecords
                    ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
                    ->filter(fn (float $minutes) => $minutes > 0)
                    ->sort()
                    ->values();

                $speeds = $routeRecords
                    ->map(fn (GpsTripRecord $record) => $this->recordSpeed($record))
                    ->filter(fn (float $speed) => $speed > 0)
                    ->sort()
                    ->values();

                return (object) [
                    'sample_size' => $routeRecords->count(),
                    'duration_median' => $this->median($durations),
                    'speed_median' => $this->median($speeds),
                ];
            });

        $evaluated = $records
            ->map(function (GpsTripRecord $record) use ($routeBaselines) {
                $route = $this->routeLabel($record);
                $baseline = $routeBaselines->get($route);
                $duration = $this->durationMinutes($record);
                $speed = $this->recordSpeed($record);
                $idleMinutes = (float) ($record->idling_minutes ?? 0);
                $idleShare = $duration > 0 ? $idleMinutes / $duration : 0;
                $hasBaseline = $baseline && $baseline->sample_size >= 3 && $baseline->duration_median > 0;

                $delayThreshold = $hasBaseline
                    ? max($baseline->duration_median * 1.20, $baseline->duration_median + 10)
                    : null;

                $isDelayed = $hasBaseline
                    && $duration > 0
                    && $duration > $delayThreshold;

                $isSlowMovement = $hasBaseline
                    && $baseline->speed_median > 0
                    && $speed > 0
                    && $speed < ($baseline->speed_median * 0.80);

                $isHighIdle = $idleMinutes >= 15
                    && $idleShare >= 0.20;

                $factors = collect([
                    $isDelayed ? 'Delay' : null,
                    $isSlowMovement ? 'Slow movement' : null,
                    $isHighIdle ? 'High idling' : null,
                ])->filter()->values();

                return (object) [
                    'record' => $record,
                    'route' => $route,
                    'duration' => $duration,
                    'speed' => $speed,
                    'idle_minutes' => $idleMinutes,
                    'idle_share' => $idleShare,
                    'baseline_duration' => $hasBaseline ? $baseline->duration_median : 0,
                    'baseline_speed' => $hasBaseline ? $baseline->speed_median : 0,
                    'has_baseline' => $hasBaseline,
                    'is_delayed' => $isDelayed,
                    'is_slow_movement' => $isSlowMovement,
                    'is_high_idle' => $isHighIdle,
                    'factors' => $factors,
                    'score' => ($isDelayed ? 3 : 0)
                        + ($isSlowMovement ? 2 : 0)
                        + ($isHighIdle ? 2 : 0),
                ];
            });

        $baselineCovered = $evaluated->where('has_baseline', true)->count();
        $delayed = $evaluated->where('is_delayed', true);
        $slowMovement = $evaluated->where('is_slow_movement', true);
        $highIdle = $evaluated->where('is_high_idle', true);
        $forReview = $evaluated
            ->filter(fn ($row) => $row->factors->isNotEmpty())
            ->sortByDesc('score')
            ->values();

        $delayedWithSlowMovement = $delayed
            ->filter(fn ($row) => $row->is_slow_movement)
            ->count();

        $delayedWithHighIdle = $delayed
            ->filter(fn ($row) => $row->is_high_idle)
            ->count();

        return (object) [
            'baseline_covered' => $baselineCovered,
            'baseline_coverage_percent' => $records->isNotEmpty()
                ? ($baselineCovered / $records->count()) * 100
                : 0,
            'delay_count' => $delayed->count(),
            'slow_movement_count' => $slowMovement->count(),
            'high_idle_count' => $highIdle->count(),
            'review_count' => $forReview->count(),
            'delayed_with_slow_movement' => $delayedWithSlowMovement,
            'delayed_with_high_idle' => $delayedWithHighIdle,
            'top_records' => $forReview->take(6),
            'route_deviation_supported' => false,
        ];
    }

    private function median(Collection $values): float
    {
        $count = $values->count();

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values->get($middle);
        }

        return ((float) $values->get($middle - 1) + (float) $values->get($middle)) / 2;
    }
}
