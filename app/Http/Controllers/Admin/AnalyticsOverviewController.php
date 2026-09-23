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

class AnalyticsOverviewController extends Controller
{
    /**
     * Keep this aligned with Maintenance\PmsSchedulingController.
     */
    private const PMS_WARNING_RANGE_KM = 500;

    public function index(Request $request): View
    {
        $period = $this->normalizePeriod((string) $request->input('period', 'this-month'));
        [$periodStart, $periodEnd, $periodLabel] = $this->periodBounds($period);

        $tripRecords = $this->recordsQuery($periodStart, $periodEnd)
            ->orderBy('beginning_at')
            ->get();

        $tripCount = $tripRecords->count();
        $totalDistance = (float) $tripRecords->sum('mileage_km');
        $durationValues = $tripRecords
            ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
            ->filter(fn (float $minutes) => $minutes > 0)
            ->values();
        $totalDurationMinutes = (float) $durationValues->sum();
        $averageTripDuration = $durationValues->isNotEmpty()
            ? (float) $durationValues->average()
            : 0.0;
        $totalMotionMinutes = (float) $tripRecords->sum('in_motion_minutes');
        $speedMinutes = $totalMotionMinutes > 0 ? $totalMotionMinutes : $totalDurationMinutes;
        $averageSpeed = $speedMinutes > 0
            ? $totalDistance / ($speedMinutes / 60)
            : 0.0;
        $activeRouteCount = $tripRecords
            ->map(fn (GpsTripRecord $record) => $this->routeLabel($record))
            ->filter(fn (string $route) => $route !== 'Unspecified Route')
            ->unique()
            ->count();

        [$previousStart, $previousEnd] = $this->previousPeriodBounds($periodStart, $periodEnd);
        $previousTripCount = $this->recordsQuery($previousStart, $previousEnd)->count();
        $tripGrowth = $previousTripCount > 0
            ? (($tripCount - $previousTripCount) / $previousTripCount) * 100
            : null;

        $tripTrend = $this->buildTrend($tripRecords, $periodStart, $periodEnd);
        $tripDiagnostics = $this->buildDiagnostics($tripRecords);

        // Reuse the existing fuel analytics calculations. This controller is
        // database-only and does not invoke the Python prediction service.
        $fuelRequest = clone $request;
        $fuelRequest->merge(['period' => $period]);
        $fuel = app(FuelAnalyticsController::class)->data($fuelRequest);
        $fuelRecords = collect($fuel['records'] ?? []);
        $fuelReviewUnits = collect($fuel['reviewUnits'] ?? []);
        $fuelForecast = $fuel['forecast'] ?? (object) [
            'available' => false,
            'projected_liters' => null,
            'change_percent' => null,
            'sample_days' => 0,
        ];
        $totalFuel = (float) ($fuel['totalFuel'] ?? 0);
        $fleetFuelAverage = (float) ($fuel['fleetAverage'] ?? 0);

        $buses = Bus::query()->orderBy('bus_no')->get();
        $pmsRows = $buses
            ->filter(fn (Bus $bus) => $bus->latest_gps_km !== null
                && $bus->next_pms_km !== null
                && (float) $bus->next_pms_km > 0)
            ->map(function (Bus $bus): object {
                $currentKm = (float) $bus->latest_gps_km;
                $nextPmsKm = (float) $bus->next_pms_km;

                return (object) [
                    'bus_no' => $bus->bus_no,
                    'current_km' => $currentKm,
                    'next_pms_km' => $nextPmsKm,
                    'remaining_km' => $nextPmsKm - $currentKm,
                ];
            })
            ->values();

        $overduePms = $pmsRows
            ->filter(fn ($row) => $row->remaining_km <= 0)
            ->sortBy('remaining_km')
            ->values();
        $dueSoonPms = $pmsRows
            ->filter(fn ($row) => $row->remaining_km > 0
                && $row->remaining_km <= self::PMS_WARNING_RANGE_KM)
            ->sortBy('remaining_km')
            ->values();
        $pmsAttentionCount = $overduePms->count() + $dueSoonPms->count();
        $nearestPms = $overduePms->first()
            ?? $pmsRows->filter(fn ($row) => $row->remaining_km > 0)
                ->sortBy('remaining_km')
                ->first();

        $inventoryItems = InventoryItem::query()
            ->orderBy('category')
            ->orderBy('parts_name')
            ->get();
        $inventoryCriticalItems = $inventoryItems
            ->filter(fn (InventoryItem $item) => (int) $item->on_hand <= 0)
            ->values();
        $inventoryLowItems = $inventoryItems
            ->filter(function (InventoryItem $item): bool {
                $onHand = (int) $item->on_hand;
                $reorder = (int) $item->reorder_level;

                return $onHand > 0 && $reorder > 0 && $onHand <= $reorder;
            })
            ->values();
        $inventoryAttentionCount = $inventoryCriticalItems->count() + $inventoryLowItems->count();

        $findings = collect();

        if ($tripDiagnostics->review_count > 0) {
            $factors = collect([
                $tripDiagnostics->delay_count > 0 ? $tripDiagnostics->delay_count . ' delayed' : null,
                $tripDiagnostics->slow_movement_count > 0 ? $tripDiagnostics->slow_movement_count . ' slow-moving' : null,
                $tripDiagnostics->high_idle_count > 0 ? $tripDiagnostics->high_idle_count . ' high-idle' : null,
            ])->filter()->implode(', ');

            $findings->push((object) [
                'severity' => 'medium',
                'label' => 'Operational Review',
                'module' => 'Fleet & Trip',
                'title' => $tripDiagnostics->review_count . ' trip record(s) require performance review.',
                'description' => $factors !== ''
                    ? 'Recorded signals: ' . $factors . '.'
                    : 'Recorded trip conditions exceeded the review thresholds.',
                'route' => 'analytics.fleet-trip',
                'icon' => 'fa-route',
                'icon_class' => 'fleet',
            ]);
        }

        if ($fuelReviewUnits->isNotEmpty()) {
            $busList = $fuelReviewUnits
                ->pluck('bus_no')
                ->filter()
                ->take(3)
                ->implode(', ');

            $findings->push((object) [
                'severity' => 'medium',
                'label' => 'Investigate',
                'module' => 'Fuel',
                'title' => $fuelReviewUnits->count() . ' bus(es) require fuel-efficiency context review.',
                'description' => $busList !== ''
                    ? 'Review units: ' . $busList . '. Compare fuel efficiency with distance and idling before classifying wastage.'
                    : 'Compare fuel efficiency with distance and idling before classifying wastage.',
                'route' => 'analytics.fuel',
                'icon' => 'fa-gas-pump',
                'icon_class' => 'fuel',
            ]);
        }

        if ($overduePms->isNotEmpty()) {
            $bus = $overduePms->first();
            $findings->push((object) [
                'severity' => 'high',
                'label' => 'High Priority',
                'module' => 'Bus Health',
                'title' => $overduePms->count() . ' bus(es) reached or exceeded the PMS mileage threshold.',
                'description' => sprintf(
                    '%s is at %s km against a %s km next-PMS threshold%s.',
                    $bus->bus_no,
                    number_format($bus->current_km, 0),
                    number_format($bus->next_pms_km, 0),
                    $dueSoonPms->isNotEmpty() ? '; ' . $dueSoonPms->count() . ' additional bus(es) are due soon' : ''
                ),
                'route' => 'analytics.bus-health',
                'icon' => 'fa-screwdriver-wrench',
                'icon_class' => 'maintenance',
            ]);
        } elseif ($dueSoonPms->isNotEmpty()) {
            $bus = $dueSoonPms->first();
            $findings->push((object) [
                'severity' => 'medium',
                'label' => 'Due Soon',
                'module' => 'Bus Health',
                'title' => $dueSoonPms->count() . ' bus(es) are within ' . self::PMS_WARNING_RANGE_KM . ' km of the PMS threshold.',
                'description' => sprintf(
                    '%s has %s km remaining before its next PMS threshold.',
                    $bus->bus_no,
                    number_format($bus->remaining_km, 0)
                ),
                'route' => 'analytics.bus-health',
                'icon' => 'fa-screwdriver-wrench',
                'icon_class' => 'maintenance',
            ]);
        }

        if ($inventoryCriticalItems->isNotEmpty()) {
            $names = $inventoryCriticalItems
                ->map(fn (InventoryItem $item) => $item->parts_name ?? $item->item_name ?? $item->item_code)
                ->filter()
                ->take(3)
                ->implode(', ');

            $findings->push((object) [
                'severity' => 'high',
                'label' => 'High Priority',
                'module' => 'Inventory',
                'title' => $inventoryCriticalItems->count() . ' inventory item(s) are out of stock.',
                'description' => ($names !== '' ? 'Out of stock: ' . $names . '. ' : '')
                    . ($inventoryLowItems->isNotEmpty()
                        ? $inventoryLowItems->count() . ' additional item(s) are at or below reorder level.'
                        : 'Replenishment review is required.'),
                'route' => 'analytics.inventory',
                'icon' => 'fa-box-open',
                'icon_class' => 'inventory',
            ]);
        } elseif ($inventoryLowItems->isNotEmpty()) {
            $names = $inventoryLowItems
                ->map(fn (InventoryItem $item) => $item->parts_name ?? $item->item_name ?? $item->item_code)
                ->filter()
                ->take(3)
                ->implode(', ');

            $findings->push((object) [
                'severity' => 'medium',
                'label' => 'Restock Review',
                'module' => 'Inventory',
                'title' => $inventoryLowItems->count() . ' inventory item(s) are at or below reorder level.',
                'description' => $names !== ''
                    ? 'Items requiring review: ' . $names . '.'
                    : 'Current on-hand stock is at or below the configured reorder level.',
                'route' => 'analytics.inventory',
                'icon' => 'fa-box-open',
                'icon_class' => 'inventory',
            ]);
        }

        $highRecommendationCount = $findings->where('severity', 'high')->count();
        $mediumRecommendationCount = $findings->where('severity', 'medium')->count();
        $monitorRecommendationCount = $findings->where('severity', 'low')->count();
        $openRecommendationCount = $findings->count();

        $summaryText = $openRecommendationCount > 0
            ? sprintf(
                '%d current review finding(s) are supported by recorded fleet, fuel, maintenance, or inventory data.',
                $openRecommendationCount
            )
            : 'No threshold-based review findings were detected in the available records for this overview.';

        $overviewInsight = [
            'label' => 'Cross-Domain Insight',
            'title' => $openRecommendationCount > 0 ? 'Current Review Signals' : 'No Current Threshold Alerts',
            'message' => $summaryText,
            'metric' => $openRecommendationCount > 0
                ? sprintf('%d findings · %d high priority', $openRecommendationCount, $highRecommendationCount)
                : sprintf('%d trip records · %d inventory items', $tripCount, $inventoryItems->count()),
            'priority' => $highRecommendationCount > 0
                ? 'critical'
                : ($mediumRecommendationCount > 0 ? 'warning' : 'info'),
            'icon' => 'fa-solid fa-chart-pie',
            'action' => 'View Findings',
            'target' => '.priority-findings-section, .analytics-kpi-strip, .analytics-overview-page',
        ];

        $moduleStates = [
            'fleet' => $this->state(
                $tripCount === 0 ? 'No Data' : ($tripDiagnostics->review_count > 0 ? 'Review' : 'Current'),
                $tripCount === 0 ? 'watch' : ($tripDiagnostics->review_count > 0 ? 'warning' : 'good')
            ),
            'fuel' => $this->state(
                $fuelRecords->isEmpty() ? 'No Data' : ($fuelReviewUnits->isNotEmpty() ? 'Review' : 'Current'),
                $fuelRecords->isEmpty() ? 'watch' : ($fuelReviewUnits->isNotEmpty() ? 'watch' : 'good')
            ),
            'bus' => $this->state(
                $pmsRows->isEmpty() ? 'No Data' : ($overduePms->isNotEmpty() ? 'Attention' : ($dueSoonPms->isNotEmpty() ? 'Due Soon' : 'Current')),
                $pmsRows->isEmpty() ? 'watch' : ($overduePms->isNotEmpty() ? 'critical' : ($dueSoonPms->isNotEmpty() ? 'warning' : 'good'))
            ),
            'inventory' => $this->state(
                $inventoryItems->isEmpty() ? 'No Data' : ($inventoryCriticalItems->isNotEmpty() ? 'Attention' : ($inventoryLowItems->isNotEmpty() ? 'Low Stock' : 'Current')),
                $inventoryItems->isEmpty() ? 'watch' : ($inventoryCriticalItems->isNotEmpty() ? 'critical' : ($inventoryLowItems->isNotEmpty() ? 'warning' : 'good'))
            ),
        ];

        $pmsMilestoneValue = 'No data';
        $pmsMilestoneDescription = 'No current bus mileage/PMS threshold available';
        if ($nearestPms) {
            if ($nearestPms->remaining_km <= 0) {
                $pmsMilestoneValue = 'Overdue';
                $pmsMilestoneDescription = sprintf(
                    '%s exceeded threshold by %s km',
                    $nearestPms->bus_no,
                    number_format(abs($nearestPms->remaining_km), 0)
                );
            } else {
                $pmsMilestoneValue = number_format($nearestPms->remaining_km, 0) . ' km';
                $pmsMilestoneDescription = $nearestPms->bus_no . ' remaining runway';
            }
        }

        $projectedFuelValue = $fuelForecast->available && $fuelForecast->projected_liters !== null
            ? number_format((float) $fuelForecast->projected_liters, 0) . ' L'
            : 'Not enough data';
        $projectedFuelDescription = $fuelForecast->available
            ? ($fuelForecast->change_percent !== null
                ? sprintf('%+.1f%% vs previous recorded-day baseline', (float) $fuelForecast->change_percent)
                : '7-day recorded-day baseline')
            : 'Needs at least 4 recorded fuel days';

        return view('Admin.Analytics.overview', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodOptions' => $this->periodOptions(),
            'summaryText' => $summaryText,
            'overviewInsight' => $overviewInsight,
            'openRecommendationCount' => $openRecommendationCount,
            'highRecommendationCount' => $highRecommendationCount,
            'mediumRecommendationCount' => $mediumRecommendationCount,
            'monitorRecommendationCount' => $monitorRecommendationCount,
            'tripCount' => $tripCount,
            'totalDistance' => $totalDistance,
            'averageTripDuration' => $averageTripDuration,
            'averageSpeed' => $averageSpeed,
            'activeRouteCount' => $activeRouteCount,
            'tripGrowth' => $tripGrowth,
            'tripTrend' => $tripTrend,
            'tripDiagnostics' => $tripDiagnostics,
            'totalFuel' => $totalFuel,
            'fleetFuelAverage' => $fleetFuelAverage,
            'fuelRecordCount' => $fuelRecords->count(),
            'fuelReviewCount' => $fuelReviewUnits->count(),
            'projectedFuelValue' => $projectedFuelValue,
            'projectedFuelDescription' => $projectedFuelDescription,
            'pmsAttentionCount' => $pmsAttentionCount,
            'overduePmsCount' => $overduePms->count(),
            'dueSoonPmsCount' => $dueSoonPms->count(),
            'pmsMilestoneValue' => $pmsMilestoneValue,
            'pmsMilestoneDescription' => $pmsMilestoneDescription,
            'inventoryTotal' => $inventoryItems->count(),
            'inventoryAttentionCount' => $inventoryAttentionCount,
            'inventoryCriticalCount' => $inventoryCriticalItems->count(),
            'inventoryLowCount' => $inventoryLowItems->count(),
            'moduleStates' => $moduleStates,
            'findings' => $findings,
        ]);
    }

    private function recordsQuery(Carbon $start, Carbon $end)
    {
        return GpsTripRecord::query()
            ->whereBetween('beginning_at', [$start, $end])
            ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'));
    }

    private function normalizePeriod(string $period): string
    {
        return match (true) {
            in_array($period, array_keys($this->periodOptions()), true) => $period,
            $period === 'last-3-months' => 'last-90-days',
            $period === 'this-year' => 'last-12-months',
            default => 'this-month',
        };
    }

    private function periodOptions(): array
    {
        return [
            'this-week' => 'This Week',
            'this-month' => 'This Month',
            'last-30-days' => 'Last 30 Days',
            'last-90-days' => 'Last 90 Days',
            'last-12-months' => 'Last 12 Months',
        ];
    }

    private function periodBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            'last-30-days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'Last 30 Days'],
            'last-90-days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), 'Last 90 Days'],
            'last-12-months' => [$now->copy()->subMonths(12)->startOfDay(), $now->copy()->endOfDay(), 'Last 12 Months'],
            'this-week' => [$now->copy()->startOfWeek()->startOfDay(), $now->copy()->endOfDay(), 'This Week'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'This Month'],
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

        return 0.0;
    }

    private function recordSpeed(GpsTripRecord $record): float
    {
        $distance = (float) ($record->mileage_km ?? 0);
        $motionMinutes = (float) ($record->in_motion_minutes ?? 0);

        if ($distance <= 0) {
            return 0.0;
        }

        if ($motionMinutes <= 0) {
            $motionMinutes = $this->durationMinutes($record);
        }

        return $motionMinutes > 0 ? $distance / ($motionMinutes / 60) : 0.0;
    }

    private function routeLabel(GpsTripRecord $record): string
    {
        $initial = trim((string) $record->initial_location);
        $final = trim((string) $record->final_location);
        if ($initial !== '' && $final !== '') {
            return $initial . ' - ' . $final;
        }

        $grouping = trim((string) $record->grouping);

        return $grouping !== '' ? $grouping : 'Unspecified Route';
    }

    private function buildTrend(Collection $records, Carbon $start, Carbon $end): Collection
    {
        $totalDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $daysPerBucket = (int) ceil($totalDays / 4);
        $buckets = collect();

        for ($index = 0; $index < 4; $index++) {
            $bucketStart = $start->copy()->startOfDay()->addDays($index * $daysPerBucket);
            if ($bucketStart->greaterThan($end)) {
                $buckets->push((object) ['label' => '—', 'count' => 0, 'height' => 0]);
                continue;
            }

            $bucketEnd = $bucketStart->copy()->addDays($daysPerBucket - 1)->endOfDay();
            if ($bucketEnd->greaterThan($end)) {
                $bucketEnd = $end->copy();
            }

            $count = $records->filter(fn (GpsTripRecord $record) => $record->beginning_at
                && $record->beginning_at->betweenIncluded($bucketStart, $bucketEnd))->count();

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

    private function buildDiagnostics(Collection $records): object
    {
        $routeBaselines = $records
            ->groupBy(fn (GpsTripRecord $record) => $this->routeLabel($record))
            ->map(function (Collection $routeRecords): object {
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

        $evaluated = $records->map(function (GpsTripRecord $record) use ($routeBaselines): object {
            $route = $this->routeLabel($record);
            $baseline = $routeBaselines->get($route);
            $duration = $this->durationMinutes($record);
            $speed = $this->recordSpeed($record);
            $idleMinutes = (float) ($record->idling_minutes ?? 0);
            $idleShare = $duration > 0 ? $idleMinutes / $duration : 0.0;
            $hasBaseline = $baseline && $baseline->sample_size >= 3 && $baseline->duration_median > 0;
            $delayThreshold = $hasBaseline
                ? max($baseline->duration_median * 1.20, $baseline->duration_median + 10)
                : null;
            $isDelayed = $hasBaseline && $duration > 0 && $duration > $delayThreshold;
            $isSlowMovement = $hasBaseline
                && $baseline->speed_median > 0
                && $speed > 0
                && $speed < ($baseline->speed_median * 0.80);
            $isHighIdle = $idleMinutes >= 15 && $idleShare >= 0.20;

            return (object) [
                'is_delayed' => $isDelayed,
                'is_slow_movement' => $isSlowMovement,
                'is_high_idle' => $isHighIdle,
            ];
        });

        $delayCount = $evaluated->where('is_delayed', true)->count();
        $slowMovementCount = $evaluated->where('is_slow_movement', true)->count();
        $highIdleCount = $evaluated->where('is_high_idle', true)->count();
        $reviewCount = $evaluated
            ->filter(fn ($row) => $row->is_delayed || $row->is_slow_movement || $row->is_high_idle)
            ->count();

        return (object) [
            'review_count' => $reviewCount,
            'delay_count' => $delayCount,
            'slow_movement_count' => $slowMovementCount,
            'high_idle_count' => $highIdleCount,
        ];
    }

    private function median(Collection $values): float
    {
        $count = $values->count();
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values->get($middle);
        }

        return ((float) $values->get($middle - 1) + (float) $values->get($middle)) / 2;
    }

    private function state(string $label, string $class): object
    {
        return (object) [
            'label' => $label,
            'class' => $class,
        ];
    }
}
