<?php

namespace App\Services\Operation;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;

class DailyDriverReportScheduleMatchService
{
    /**
     * Trips more than this many minutes past the scheduled time are
     * considered delayed. Derived from real scheduled vs. actual values only.
     */
    public const LATE_THRESHOLD_MINUTES = 10;

    public function match(DailyDriverReport $report): ?TripSchedule
    {
        if (! $report->driver_id || ! $report->bus_id) {
            return null;
        }

        $query = TripSchedule::query()
            ->with(['shuttleRoute', 'assignment'])
            ->whereDate('trip_date', $report->report_date->toDateString())
            ->whereHas('assignment', function ($assignmentQuery) use ($report) {
                $assignmentQuery
                    ->where('driver_id', $report->driver_id)
                    ->where('bus_id', $report->bus_id);
            });

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        if ($schedules->count() === 1) {
            return $schedules->first();
        }

        $actualDeparture = $this->toCarbon($report->departure_time);

        $closest = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($schedules as $schedule) {
            $scheduledDeparture = $this->toCarbon($schedule->departure_time);

            $distance = abs($actualDeparture->diffInMinutes($scheduledDeparture));

            if ($distance > 720) {
                $distance = 1440 - $distance;
            }

            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closest = $schedule;
            }
        }

        return $closest;
    }

    public function comparison(
        ?TripSchedule $schedule,
        mixed $departureTime,
        mixed $arrivalTime
    ): array {
        $actualDeparture = $this->toCarbon($departureTime);
        $actualArrival = $this->toCarbon($arrivalTime);

        if (! $schedule) {
            return [
                'matched' => false,
                'status' => 'Schedule match unavailable',
                'trip_code' => null,
                'route_label' => null,
                'shift' => null,
                'scheduled_departure' => null,
                'scheduled_arrival' => null,
                'actual_departure' => $actualDeparture?->format('H:i'),
                'actual_arrival' => $actualArrival?->format('H:i'),
                'departure_delay_minutes' => null,
                'arrival_delay_minutes' => null,
                'delay_minutes' => null,
            ];
        }

        $scheduledDeparture = $this->toCarbon($schedule->departure_time);
        $scheduledArrival = $this->toCarbon($schedule->estimated_arrival_time);

        $departureDelay = $this->minutesLate(
            $actualDeparture,
            $scheduledDeparture
        );

        $arrivalDelay = $this->minutesLate(
            $actualArrival,
            $scheduledArrival
        );

        $delayMinutes = max($departureDelay, $arrivalDelay);

        $route = $schedule->shuttleRoute;

        $routeLabel = $route
            ? ($route->route_code . ' - ' . $route->route_name
                . ($route->origin && $route->destination
                    ? ' (' . $route->origin . ' → ' . $route->destination . ')'
                    : ''))
            : null;

        return [
            'matched' => true,
            'status' => $delayMinutes > self::LATE_THRESHOLD_MINUTES
                ? 'Delayed'
                : 'On Time',
            'trip_code' => $schedule->trip_code,
            'route_label' => $routeLabel,
            'shift' => $schedule->shift,
            'scheduled_departure' => $scheduledDeparture->format('H:i'),
            'scheduled_arrival' => $scheduledArrival->format('H:i'),
            'actual_departure' => $actualDeparture->format('H:i'),
            'actual_arrival' => $actualArrival->format('H:i'),
            'departure_delay_minutes' => $departureDelay,
            'arrival_delay_minutes' => $arrivalDelay,
            'delay_minutes' => $delayMinutes,
        ];
    }

    private function minutesLate(Carbon $actual, Carbon $scheduled): int
    {
        // Carbon's signed diff returns scheduled minus actual, so negate to
        // get "actual is this many minutes later than scheduled".
        $diff = -1 * (int) $actual->diffInMinutes($scheduled, false);

        // Arrivals that wrap past midnight land on the following day.
        if ($diff < -720) {
            $diff += 1440;
        }

        return max(0, $diff);
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}/', $value)) {
            return Carbon::createFromFormat('H:i', substr($value, 0, 5));
        }

        return null;
    }
}