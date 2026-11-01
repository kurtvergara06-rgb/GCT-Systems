<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\BusPmsMonitoring;
use App\Models\Admin\GpsTripRecord;
use App\Models\Operation\DailyDriverReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only DDR mileage estimate. Start after the verified snapshot to
 * avoid recounting trips already reflected in the physical odometer.
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
            ->whereDate('report_date', '>=', $oldest)
            ->whereDate('report_date', '<=', $today)
            ->with('additionalTrips:id,daily_driver_report_id,km')
            ->get(['id', 'bus_id', 'report_date', 'km']);

        $totals = [];
        foreach ($reports as $report) {
            $baseline = $byBus->get($report->bus_id);
            if (! $baseline) {
                continue;
            }
            // Only complete days after a verified starting snapshot are safe:
            // DDR has a report date, not necessarily a reliable per-trip timestamp.
            $snapshot = $baseline->current_odometer_verified_at;
            $cutoffDate = $snapshot
                ? $snapshot->toDateString()
                : $baseline->last_completed_pms_date->toDateString();
            if ($report->report_date->toDateString() <= $cutoffDate) {
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

        // Conservative GPS fallback: only for an entire calendar day with no
        // DDR record for that bus. An incomplete DDR day is never supplemented
        // with GPS because trip-level overlap is not reliably identifiable.
        $busIdsByNumber = $baselines->mapWithKeys(
            fn ($baseline) => [strtoupper(trim((string) $baseline->bus?->bus_no)) => $baseline->bus_id]
        )->filter();
        if ($busIdsByNumber->isNotEmpty()) {
            $gps = GpsTripRecord::query()
                ->whereNotNull('beginning_at')
                ->whereNotNull('ending_at')
                ->whereNotNull('mileage_km')
                ->where('mileage_km', '>', 0)
                ->whereDate('beginning_at', '>=', $oldest)
                ->whereDate('beginning_at', '<=', $today)
                ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
                ->get(['id', 'bus_no', 'beginning_at', 'ending_at', 'mileage_km', 'initial_location', 'final_location']);
            $ddrDays = $reports->groupBy(fn ($report) => $report->bus_id.'|'.$report->report_date->toDateString());
            $seen = [];
            foreach ($gps as $trip) {
                $busId = $busIdsByNumber->get(strtoupper(trim((string) $trip->bus_no)));
                $baseline = $busId ? $byBus->get($busId) : null;
                if (! $baseline || $baseline->verified_current_odometer_km === null || ! $baseline->current_odometer_verified_at) {
                    continue;
                }
                $start = $trip->beginning_at;
                $finish = $trip->ending_at;
                $date = $start->toDateString();
                if ($start->lessThanOrEqualTo($baseline->current_odometer_verified_at)
                    || $finish->lessThanOrEqualTo($start)
                    || $finish->toDateString() !== $date
                    || $ddrDays->has($busId.'|'.$date)) {
                    continue;
                }
                // Identically imported GPS journeys must not contribute twice.
                $fingerprint = implode('|', [
                    $busId, $start->toIso8601String(), $finish->toIso8601String(),
                    (string) $trip->mileage_km, trim((string) $trip->initial_location),
                    trim((string) $trip->final_location),
                ]);
                if (isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;
                $totals[$busId] ??= ['distance' => 0.0, 'trips' => 0, 'incomplete' => false];
                $totals[$busId]['distance'] += (float) $trip->mileage_km;
                $totals[$busId]['trips']++;
                $totals[$busId]['gps_trips'] = ($totals[$busId]['gps_trips'] ?? 0) + 1;
            }
        }

        return collect($totals)->mapWithKeys(function ($row, $busId) use ($byBus) {
            if ($row['incomplete'] || $row['trips'] === 0) {
                return [];
            }
            $distance = round($row['distance'], 2);
            return [$busId => [
                'distance_km' => $distance,
                'estimated_odometer_km' => round((float) ($byBus->get($busId)->verified_current_odometer_km ?? $byBus->get($busId)->last_completed_pms_odometer_km) + $distance, 2),
                'trip_count' => $row['trips'],
                'gps_trip_count' => $row['gps_trips'] ?? 0,
            ]];
        });
    }
}
