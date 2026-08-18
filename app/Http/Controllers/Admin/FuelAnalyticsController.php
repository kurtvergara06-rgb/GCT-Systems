<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FuelAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        return view('Admin.Analytics.fuel', $this->data($request));
    }

    public function data(Request $request): array
    {
        $allowedPeriods = ['this-month', 'last-30-days', 'last-3-months', 'this-year'];
        $period = in_array($request->string('period')->toString(), $allowedPeriods, true)
            ? $request->string('period')->toString()
            : 'this-month';

        $allowedTrendWindows = ['7-days', '14-days', '30-days'];
        $trendWindow = in_array($request->string('fuel_trend')->toString(), $allowedTrendWindows, true)
            ? $request->string('fuel_trend')->toString()
            : '7-days';
        $trendLabel = match ($trendWindow) {
            '14-days' => 'Last 14 Days',
            '30-days' => 'Last 30 Days',
            default => 'Last 7 Days',
        };

        [$start, $end] = $this->periodBounds($period);
        $selectedBus = trim((string) $request->input('bus', 'all')) ?: 'all';

        $records = FuelReport::query()
            ->with('bus')
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->when($selectedBus !== 'all', fn ($query) => $query->where('bus_no', $selectedBus))
            ->orderBy('report_date')
            ->orderBy('id')
            ->get();

        $totalFuel = (float) $records->sum('fuel_liters');
        $totalDistance = (float) $records->sum('distance_km');
        $fleetAverage = $totalFuel > 0 ? $totalDistance / $totalFuel : 0.0;

        $gpsRecords = GpsTripRecord::query()
            ->whereBetween('beginning_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
            ->when($selectedBus !== 'all', fn ($query) => $query->where('bus_no', $selectedBus))
            ->get();

        $gpsByBus = $gpsRecords
            ->groupBy(fn ($row) => strtoupper(trim((string) $row->bus_no)))
            ->map(function (Collection $rows): object {
                $distance = (float) $rows->sum('mileage_km');
                $idling = (float) $rows->sum('idling_minutes');

                return (object) [
                    'distance_km' => $distance,
                    'idling_minutes' => $idling,
                    'idling_per_100km' => $distance > 0 ? ($idling / $distance) * 100 : 0.0,
                ];
            });

        $idlingMedian = (float) ($gpsByBus
            ->pluck('idling_per_100km')
            ->filter(fn ($value) => $value > 0)
            ->median() ?? 0.0);

        $busSummaries = $records
            ->groupBy(fn ($row) => strtoupper(trim((string) $row->bus_no)))
            ->map(function (Collection $rows, string $busKey) use ($fleetAverage, $gpsByBus, $idlingMedian): object {
                $fuel = (float) $rows->sum('fuel_liters');
                $distance = (float) $rows->sum('distance_km');
                $efficiency = $fuel > 0 ? $distance / $fuel : 0.0;
                $gps = $gpsByBus->get($busKey);
                $vsAverage = $fleetAverage > 0 ? (($efficiency - $fleetAverage) / $fleetAverage) * 100 : 0.0;
                $lowEfficiency = $fleetAverage > 0 && $efficiency > 0 && $efficiency < ($fleetAverage * 0.90);
                $priorityEfficiency = $fleetAverage > 0 && $efficiency > 0 && $efficiency < ($fleetAverage * 0.80);
                $highIdling = $gps && $idlingMedian > 0
                    && $gps->idling_minutes >= 15
                    && $gps->idling_per_100km > ($idlingMedian * 1.25);

                $signals = collect([
                    $lowEfficiency ? 'Efficiency is more than 10% below the selected fleet average.' : null,
                    $highIdling ? 'Idling intensity is more than 25% above the selected fleet median.' : null,
                ])->filter()->values();

                $status = match (true) {
                    $priorityEfficiency => 'Priority Review',
                    $lowEfficiency || $highIdling => 'Review',
                    $fleetAverage > 0 && $efficiency >= ($fleetAverage * 1.05) => 'Efficient',
                    default => 'Normal',
                };

                return (object) [
                    'bus_no' => (string) ($rows->first()?->bus_no ?? $busKey),
                    'distance_km' => $distance,
                    'fuel_liters' => $fuel,
                    'km_per_liter' => $efficiency,
                    'vs_average' => $vsAverage,
                    'entries' => $rows->count(),
                    'idling_minutes' => (float) ($gps?->idling_minutes ?? 0),
                    'idling_per_100km' => (float) ($gps?->idling_per_100km ?? 0),
                    'signals' => $signals,
                    'needs_review' => $signals->isNotEmpty(),
                    'status' => $status,
                ];
            })
            ->sortByDesc('km_per_liter')
            ->values();

        $reviewUnits = $busSummaries->where('needs_review', true)->values();
        $highIdlingUnits = $busSummaries
            ->filter(fn ($row) => $row->signals->contains(fn ($signal) => str_contains($signal, 'Idling intensity')))
            ->values();

        $trend = $this->buildTrend($selectedBus, $end, $trendWindow);
        $forecast = $this->buildForecast($selectedBus, $end);
        $recommendations = $this->buildRecommendations($reviewUnits, $highIdlingUnits, $forecast);
        $buses = Bus::query()->orderBy('bus_no')->get(['bus_no']);

        return compact(
            'period',
            'selectedBus',
            'start',
            'end',
            'records',
            'totalFuel',
            'totalDistance',
            'fleetAverage',
            'busSummaries',
            'reviewUnits',
            'highIdlingUnits',
            'idlingMedian',
            'trend',
            'trendWindow',
            'trendLabel',
            'forecast',
            'recommendations',
            'buses'
        );
    }

    private function periodBounds(string $period): array
    {
        $end = now()->endOfDay();
        $start = match ($period) {
            'last-30-days' => now()->subDays(29)->startOfDay(),
            'last-3-months' => now()->subMonths(3)->startOfDay(),
            'this-year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        return [$start, $end];
    }

    private function buildTrend(string $selectedBus, Carbon $end, string $trendWindow): Collection
    {
        $days = match ($trendWindow) {
            '14-days' => 14,
            '30-days' => 30,
            default => 7,
        };
        $trendStart = $end->copy()->subDays($days - 1)->startOfDay();

        $records = FuelReport::query()
            ->whereBetween('report_date', [$trendStart->toDateString(), $end->toDateString()])
            ->when($selectedBus !== 'all', fn ($query) => $query->where('bus_no', $selectedBus))
            ->orderBy('report_date')
            ->get();

        $daily = $records
            ->groupBy(fn ($row) => Carbon::parse($row->report_date)->toDateString())
            ->map(function (Collection $rows): object {
                $fuel = (float) $rows->sum('fuel_liters');
                $distance = (float) $rows->sum('distance_km');

                return (object) [
                    'fuel_liters' => $fuel,
                    'distance_km' => $distance,
                    'efficiency' => $fuel > 0 ? $distance / $fuel : 0.0,
                ];
            });

        return collect(range(0, $days - 1))->map(function (int $offset) use ($trendStart, $daily): object {
            $date = $trendStart->copy()->addDays($offset);
            $key = $date->toDateString();
            $summary = $daily->get($key);

            return (object) [
                'key' => $key,
                'label' => $date->format('M j'),
                'fuel_liters' => (float) ($summary?->fuel_liters ?? 0),
                'distance_km' => (float) ($summary?->distance_km ?? 0),
                'efficiency' => (float) ($summary?->efficiency ?? 0),
            ];
        });
    }

    private function buildForecast(string $selectedBus, Carbon $end): object
    {
        $windowStart = $end->copy()->subDays(13)->startOfDay();

        $records = FuelReport::query()
            ->whereBetween('report_date', [$windowStart->toDateString(), $end->toDateString()])
            ->when($selectedBus !== 'all', fn ($query) => $query->where('bus_no', $selectedBus))
            ->get();

        $daily = $records
            ->groupBy(fn ($row) => Carbon::parse($row->report_date)->toDateString())
            ->map(fn (Collection $rows) => (float) $rows->sum('fuel_liters'));

        $recentStart = $end->copy()->subDays(6)->toDateString();
        $previousStart = $end->copy()->subDays(13)->toDateString();
        $previousEnd = $end->copy()->subDays(7)->toDateString();

        $recent = $daily->filter(fn ($value, $date) => $date >= $recentStart);
        $previous = $daily->filter(fn ($value, $date) => $date >= $previousStart && $date <= $previousEnd);

        $available = $recent->count() >= 4;
        $recentAverage = $recent->count() > 0 ? (float) $recent->avg() : 0.0;
        $previousAverage = $previous->count() > 0 ? (float) $previous->avg() : 0.0;
        $change = $previousAverage > 0 ? (($recentAverage - $previousAverage) / $previousAverage) * 100 : null;

        return (object) [
            'available' => $available,
            'method' => '7-day recorded-day baseline',
            'sample_days' => $recent->count(),
            'projected_liters' => $available ? $recentAverage * 7 : null,
            'recent_average' => $recentAverage,
            'previous_average' => $previousAverage,
            'change_percent' => $change,
        ];
    }

    private function buildRecommendations(Collection $reviewUnits, Collection $highIdlingUnits, object $forecast): Collection
    {
        $recommendations = collect();

        if ($reviewUnits->isNotEmpty()) {
            $unit = $reviewUnits->sortBy('km_per_liter')->first();
            $recommendations->push((object) [
                'icon' => 'fa-screwdriver-wrench',
                'title' => "Review {$unit->bus_no} efficiency with maintenance history",
                'reason' => sprintf('Recorded efficiency is %.2f km/L and is materially below the selected fleet baseline.', $unit->km_per_liter),
                'tone' => 'warning',
            ]);
        }

        if ($highIdlingUnits->isNotEmpty()) {
            $unit = $highIdlingUnits->sortByDesc('idling_per_100km')->first();
            $recommendations->push((object) [
                'icon' => 'fa-hourglass-half',
                'title' => "Review idling pattern for {$unit->bus_no}",
                'reason' => sprintf('Idling intensity is %.1f minutes per 100 km, above the selected fleet median.', $unit->idling_per_100km),
                'tone' => 'info',
            ]);
        }

        if ($forecast->available && $forecast->change_percent !== null && $forecast->change_percent > 5) {
            $recommendations->push((object) [
                'icon' => 'fa-gas-pump',
                'title' => 'Plan additional short-term fuel allocation',
                'reason' => sprintf('The recent recorded-day baseline is %.1f%% above the preceding seven-day baseline.', $forecast->change_percent),
                'tone' => 'purple',
            ]);
        }

        if ($recommendations->isEmpty()) {
            $recommendations->push((object) [
                'icon' => 'fa-circle-check',
                'title' => 'Maintain current monitoring cadence',
                'reason' => 'No selected fuel signal currently requires a stronger advisory action.',
                'tone' => 'green',
            ]);
        }

        return $recommendations;
    }
}
