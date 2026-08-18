<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Warehouse\InventoryItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DescriptiveAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $allowedPeriods = ['this-month', 'last-30-days', 'last-3-months', 'this-year'];
        $period = in_array((string) $request->input('period'), $allowedPeriods, true)
            ? (string) $request->input('period')
            : 'this-month';
        $selectedBus = strtoupper(trim((string) $request->input('bus', 'all')));
        $domain = strtolower(trim((string) $request->input('domain', 'all')));
        $domain = in_array($domain, ['all', 'fleet-trip', 'fuel', 'bus-health', 'inventory'], true)
            ? $domain
            : 'all';

        [$start, $end, $periodLabel] = $this->periodBounds($period);
        [$comparisonStart, $comparisonEnd, $comparisonLabel] = $this->comparisonBounds($period, $start, $end);

        $records = $this->tripQuery($selectedBus, $start, $end)
            ->orderBy('beginning_at')
            ->get();
        $previousRecords = $this->tripQuery($selectedBus, $comparisonStart, $comparisonEnd)
            ->orderBy('beginning_at')
            ->get();

        $currentMetrics = $this->tripMetrics($records);
        $previousMetrics = $this->tripMetrics($previousRecords);

        $tripCount = $currentMetrics['tripCount'];
        $totalDistance = $currentMetrics['totalDistance'];
        $totalIdleMinutes = $currentMetrics['totalIdleMinutes'];
        $averageTripDuration = $currentMetrics['averageTripDuration'];
        $averageSpeed = $currentMetrics['averageSpeed'];

        $comparison = [
            'label' => $comparisonLabel,
            'trips' => $this->percentChange($tripCount, $previousMetrics['tripCount']),
            'distance' => $this->percentChange($totalDistance, $previousMetrics['totalDistance']),
            'idle' => $this->percentChange($totalIdleMinutes, $previousMetrics['totalIdleMinutes']),
            'duration' => $this->percentChange($averageTripDuration, $previousMetrics['averageTripDuration']),
            'speed' => $this->percentChange($averageSpeed, $previousMetrics['averageSpeed']),
            'previousTrips' => $previousMetrics['tripCount'],
            'previousDistance' => $previousMetrics['totalDistance'],
            'previousIdleMinutes' => $previousMetrics['totalIdleMinutes'],
        ];

        $buses = Bus::query()->orderBy('bus_no')->get();
        $totalBuses = $buses->count();
        $activeBuses = $buses->where('status', 'Active')->count();
        $underMaintenance = $buses->where('status', 'Under Maintenance')->count();
        $inactiveBuses = $buses->where('status', 'Inactive')->count();
        $fleetAvailability = $totalBuses > 0 ? ($activeBuses / $totalBuses) * 100 : 0.0;

        $trend = $this->buildTrend($records, $start, $end);
        $routes = $this->buildRoutes($records);
        $busActivity = $this->buildBusActivity($records);

        $inventoryTotal = InventoryItem::query()->count();
        $inventoryLow = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('on_hand', '>', 0)
            ->count();
        $inventoryCritical = InventoryItem::query()->where('on_hand', '<=', 0)->count();
        $inventoryHealthy = max(0, $inventoryTotal - $inventoryLow - $inventoryCritical);

        $fuel = app(FuelAnalyticsController::class)->data($request);
        $notificationData = app(NotificationCenterController::class)->data($request);
        $recentAlerts = collect($notificationData['notifications']->items())->take(3)->values();

        return view('Admin.Analytics.descriptive.layout', compact(
            'period',
            'periodLabel',
            'selectedBus',
            'domain',
            'buses',
            'tripCount',
            'totalDistance',
            'totalIdleMinutes',
            'averageTripDuration',
            'averageSpeed',
            'comparison',
            'totalBuses',
            'activeBuses',
            'underMaintenance',
            'inactiveBuses',
            'fleetAvailability',
            'trend',
            'routes',
            'busActivity',
            'inventoryTotal',
            'inventoryHealthy',
            'inventoryLow',
            'inventoryCritical',
            'fuel',
            'recentAlerts'
        ));
    }

    private function tripQuery(string $selectedBus, Carbon $start, Carbon $end)
    {
        return GpsTripRecord::query()
            ->whereBetween('beginning_at', [$start, $end])
            ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
            ->when(
                $selectedBus !== '' && $selectedBus !== 'ALL',
                fn ($query) => $query->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus])
            );
    }

    private function tripMetrics(Collection $records): array
    {
        $tripCount = $records->count();
        $totalDistance = (float) $records->sum('mileage_km');
        $totalIdleMinutes = (float) $records->sum('idling_minutes');
        $totalMotionMinutes = (float) $records->sum('in_motion_minutes');
        $durations = $records
            ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
            ->filter(fn (float $minutes) => $minutes > 0);
        $averageTripDuration = $durations->isNotEmpty() ? (float) $durations->average() : 0.0;
        $speedMinutes = $totalMotionMinutes > 0 ? $totalMotionMinutes : (float) $durations->sum();
        $averageSpeed = $speedMinutes > 0 ? $totalDistance / ($speedMinutes / 60) : 0.0;

        return compact(
            'tripCount',
            'totalDistance',
            'totalIdleMinutes',
            'averageTripDuration',
            'averageSpeed'
        );
    }

    private function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return (((float) $current - (float) $previous) / abs((float) $previous)) * 100;
    }

    private function periodBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            'last-30-days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'Last 30 Days'],
            'last-3-months' => [$now->copy()->subMonths(3)->startOfDay(), $now->copy()->endOfDay(), 'Last 3 Months'],
            'this-year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'This Year'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'This Month'],
        };
    }

    private function comparisonBounds(string $period, Carbon $start, Carbon $end): array
    {
        if ($period === 'this-month') {
            $previousStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
            $elapsedDays = max(0, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
            $previousEnd = $previousStart->copy()->addDays($elapsedDays)->endOfDay();
            if ($previousEnd->month !== $previousStart->month) {
                $previousEnd = $previousStart->copy()->endOfMonth();
            }

            return [$previousStart, $previousEnd, 'vs last month'];
        }

        if ($period === 'this-year') {
            return [
                $start->copy()->subYear()->startOfYear(),
                $end->copy()->subYear()->endOfDay(),
                'vs last year',
            ];
        }

        $days = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return [$previousStart, $previousEnd, 'vs previous period'];
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

        return $record->beginning_at && $record->ending_at
            ? (float) $record->beginning_at->diffInMinutes($record->ending_at)
            : 0.0;
    }

    private function routeLabel(GpsTripRecord $record): string
    {
        $initial = trim((string) $record->initial_location);
        $final = trim((string) $record->final_location);

        if ($initial !== '' && $final !== '') {
            return $initial . ' - ' . $final;
        }

        return trim((string) $record->grouping) ?: 'Unspecified Route';
    }

    private function buildTrend(Collection $records, Carbon $start, Carbon $end): Collection
    {
        $totalDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $bucketCount = min(8, $totalDays);
        $daysPerBucket = max(1, (int) ceil($totalDays / $bucketCount));
        $trend = collect();

        for ($index = 0; $index < $bucketCount; $index++) {
            $bucketStart = $start->copy()->startOfDay()->addDays($index * $daysPerBucket);
            if ($bucketStart->greaterThan($end)) {
                break;
            }

            $bucketEnd = $bucketStart->copy()->addDays($daysPerBucket - 1)->endOfDay();
            if ($bucketEnd->greaterThan($end)) {
                $bucketEnd = $end->copy();
            }

            $count = $records->filter(
                fn (GpsTripRecord $record) => $record->beginning_at
                    && $record->beginning_at->betweenIncluded($bucketStart, $bucketEnd)
            )->count();
            $trend->push((object) ['label' => $bucketStart->format('M j'), 'count' => $count]);
        }

        return $trend;
    }

    private function buildRoutes(Collection $records): Collection
    {
        $totalTrips = max(1, $records->count());
        $routes = $records
            ->groupBy(fn (GpsTripRecord $record) => $this->routeLabel($record))
            ->map(function (Collection $rows, string $label) use ($totalTrips): object {
                $durations = $rows
                    ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
                    ->filter(fn (float $minutes) => $minutes > 0);

                return (object) [
                    'label' => $label,
                    'trips' => $rows->count(),
                    'average_duration' => $durations->isNotEmpty() ? (float) $durations->average() : 0.0,
                    'share' => ($rows->count() / $totalTrips) * 100,
                ];
            })
            ->sortByDesc('trips')
            ->take(5)
            ->values();

        $maxTrips = max(1, (int) ($routes->max('trips') ?? 0));

        return $routes->map(function ($route) use ($maxTrips) {
            $route->progress = ($route->trips / $maxTrips) * 100;
            return $route;
        });
    }

    private function buildBusActivity(Collection $records): Collection
    {
        $totalTrips = max(1, $records->count());
        $activity = $records
            ->filter(fn (GpsTripRecord $record) => trim((string) $record->bus_no) !== '')
            ->groupBy(fn (GpsTripRecord $record) => strtoupper(trim((string) $record->bus_no)))
            ->map(function (Collection $rows, string $busNo) use ($totalTrips): object {
                return (object) [
                    'bus' => $busNo,
                    'trips' => $rows->count(),
                    'distance' => (float) $rows->sum('mileage_km'),
                    'share' => ($rows->count() / $totalTrips) * 100,
                ];
            })
            ->sortByDesc('trips')
            ->take(5)
            ->values();

        $maxTrips = max(1, (int) ($activity->max('trips') ?? 0));

        return $activity->map(function ($bus) use ($maxTrips) {
            $bus->progress = ($bus->trips / $maxTrips) * 100;
            return $bus;
        });
    }
}
