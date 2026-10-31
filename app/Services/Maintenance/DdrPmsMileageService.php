<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\BusPmsMonitoring;
use App\Models\Operation\DailyDriverReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only DDR mileage estimate; never treat trip distances as verified
 * odometer measurements or mix GPS and DDR in the same calculation.
 */
class DdrPmsMileageService
{
    /**
     * Resolve all configured buses in one bounded read instead of N+1 queries.
     *
     * @param Collection<int, BusPmsMonitoring> $baselines
     * @return Collection<int, array{distance_km: float, estimated_odometer_km: float, trip_count: int}>
     */
    public function estimates(Collection $baselines): Collection
    {
        if ($baselines->isEmpty()) {
            return collect();
        }

        $byBus = $baselines->keyBy('bus_id');
        $oldest = $baselines->min(fn ($baseline) => $baseline->last_completed_pms_date->toDateString());
        $today = Carbon::now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $reports = DailyDriverReport::query()
            ->whereIn('bus_id', $byBus->keys()->all())
            ->whereDate('report_date', '>', $oldest)
            ->whereDate('report_date', '<=', $today)
            ->with('additionalTrips:id,daily_driver_report_id,km')
            ->get(['id', 'bus_id', 'report_date', 'km']);

        $totals = [];
        foreach ($reports as $report) {
            $baseline = $byBus->get($report->bus_id);
            if (!$baseline || $report->report_date->toDateString() <= $baseline->last_completed_pms_date->toDateString()) {
                continue;
            }

            $busId = $report->bus_id;
            $totals[$busId] ??= ['distance' => 0.0, 'trips' => 0, 'incomplete' => false];
            if ($report->km === null) {
                $totals[$busId]['incomplete'] = true;
            } else {
                $totals[$busId]['distance'] += (float) $report->km;
                $totals[$busId]['trips']++;
            }
            foreach ($report->additionalTrips as $entry) {
                if ($entry->km === null) {
                    $totals[$busId]['incomplete'] = true;
                } else {
                    $totals[$busId]['distance'] += (float) $entry->km;
                    $totals[$busId]['trips']++;
                }
            }
        }

        return collect($totals)->mapWithKeys(function ($row, $busId) use ($byBus) {
            if ($row['incomplete'] || $row['trips'] === 0) {
                return [];
            }
            $distance = round($row['distance'], 2);
            return [$busId => [
                'distance_km' => $distance,
                'estimated_odometer_km' => round((float) $byBus->get($busId)->last_completed_pms_odometer_km + $distance, 2),
                'trip_count' => $row['trips'],
            ]];
        });
    }
}
