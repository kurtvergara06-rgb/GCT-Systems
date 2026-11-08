<?php

namespace App\Support\Operation;

use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TripDdrActualTimes
{
    /**
     * Driver-reported times only; never substitute scheduled or manually stored
     * trip_schedules.actual_* values when no linked DDR exists.
     *
     * Includes primary reports and additional trip entries, without guessing
     * a trip association from dates, tickets, or matching vehicle names.
     */
    public static function forTrip(TripSchedule $trip): Collection
    {
        $rows = collect();

        foreach ($trip->dailyDriverReports as $report) {
            $rows->push([
                'ddr_no' => $report->ddr_no,
                'driver' => $report->driver_name ?: 'Driver not recorded',
                'bus' => $report->bus?->bus_no ?: ($report->bus?->plate_no ?: 'Bus not recorded'),
                'departure' => self::time($report->departure_time),
                'arrival' => self::time($report->arrival_time),
                'source' => 'Primary DDR',
                'sequence' => 1,
            ]);
        }

        foreach ($trip->dailyDriverReportTripEntries as $entry) {
            $report = $entry->report;
            if (! $report) {
                continue;
            }
            $rows->push([
                'ddr_no' => $report->ddr_no,
                'driver' => $report->driver_name ?: 'Driver not recorded',
                'bus' => $report->bus?->bus_no ?: ($report->bus?->plate_no ?: 'Bus not recorded'),
                'departure' => self::time($entry->departure_time),
                'arrival' => self::time($entry->arrival_time),
                'source' => 'Additional DDR trip',
                'sequence' => (int) $entry->sequence,
            ]);
        }

        return $rows->sortBy([['ddr_no', 'asc'], ['sequence', 'asc']])->values();
    }

    private static function time(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->format('g:i A')
            : Carbon::parse((string) $value)->format('g:i A');
    }
}
