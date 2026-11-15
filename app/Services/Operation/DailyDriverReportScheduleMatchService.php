<?php

namespace App\Services\Operation;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\DailyDriverReportTripEntry;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class DailyDriverReportScheduleMatchService
{
    private const LATE_THRESHOLD_MINUTES = 10;

    public function match(DailyDriverReport $report): ?TripSchedule
    {
        if (
            Schema::hasColumn('daily_driver_reports', 'trip_schedule_id')
            && $report->trip_schedule_id
        ) {
            $directSchedule = $this->scheduleQuery()
                ->whereKey($report->trip_schedule_id)
                ->first();

            if ($directSchedule) {
                return $directSchedule;
            }
        }

        if (
            Schema::hasColumn('daily_driver_reports', 'trip_assignment_id')
            && $report->trip_assignment_id
        ) {
            $assignment = TripAssignment::query()
                ->with(['tripSchedule.shuttleRoute', 'tripSchedule.assignment'])
                ->find($report->trip_assignment_id);

            if ($assignment?->tripSchedule) {
                return $assignment->tripSchedule;
            }
        }

        if (! $report->driver_id || ! $report->bus_id) {
            return null;
        }

        $query = $this->scheduleQuery()
            ->whereDate('trip_date', $report->report_date->toDateString())
            ->whereHas('assignment', function (Builder $assignmentQuery) use ($report) {
                $assignmentQuery
                    ->where('driver_id', $report->driver_id)
                    ->where(function (Builder $busQuery) use ($report) {
                        $busQuery->where('bus_id', $report->bus_id);

                        if (Schema::hasColumn('trip_assignments', 'original_bus_id')) {
                            $busQuery->orWhere('original_bus_id', $report->bus_id);
                        }
                    });
            });

        if ($report->trip_ticket) {
            $exactTicketMatch = (clone $query)
                ->where('trip_code', $report->trip_ticket)
                ->first();

            if ($exactTicketMatch) {
                return $exactTicketMatch;
            }
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // More than one assignment on the same date is ambiguous without an
        // exact trip ticket. Never silently attach operational history to the
        // merely nearest departure.
        return null;
    }

    public function persistMatch(DailyDriverReport $report): ?TripSchedule
    {
        if (
            ! Schema::hasColumn('daily_driver_reports', 'trip_schedule_id')
            || ! Schema::hasColumn('daily_driver_reports', 'trip_assignment_id')
        ) {
            return $this->match($report);
        }

        $schedule = $this->match($report);

        if (! $schedule) {
            return null;
        }

        $assignment = $schedule->relationLoaded('assignment')
            ? $schedule->assignment
            : $schedule->assignment()->first();

        $updates = [
            'trip_schedule_id' => $schedule->id,
            'trip_assignment_id' => $assignment?->id,
        ];

        if (
            (int) $report->trip_schedule_id !== (int) $updates['trip_schedule_id']
            || (int) $report->trip_assignment_id !== (int) ($updates['trip_assignment_id'] ?? 0)
        ) {
            $report->forceFill($updates)->saveQuietly();
        }

        return $schedule;
    }

    public function persistEntryMatch(DailyDriverReportTripEntry $entry): ?TripSchedule
    {
        if (! Schema::hasColumn('daily_driver_report_trip_entries', 'trip_schedule_id')) {
            return null;
        }

        $report = $entry->report()->first();
        if (! $report) {
            return null;
        }

        $schedule = $this->matchEntry($entry, $report);
        if (! $schedule) {
            return null;
        }

        $assignment = $schedule->relationLoaded('assignment')
            ? $schedule->assignment
            : $schedule->assignment()->first();

        $entry->forceFill([
            'trip_schedule_id' => $schedule->id,
            'trip_assignment_id' => $assignment?->id,
        ])->saveQuietly();

        return $schedule;
    }

    public function matchEntry(
        DailyDriverReportTripEntry $entry,
        DailyDriverReport $report
    ): ?TripSchedule {
        if ($entry->trip_schedule_id) {
            return $this->scheduleQuery()->find($entry->trip_schedule_id);
        }

        $query = $this->scheduleQuery()
            ->whereDate('trip_date', $report->report_date->toDateString())
            ->whereHas('assignment', function (Builder $assignmentQuery) use ($report): void {
                $assignmentQuery->where('driver_id', $report->driver_id)
                    ->where(function (Builder $busQuery) use ($report): void {
                        $busQuery->where('bus_id', $report->bus_id);
                        if (Schema::hasColumn('trip_assignments', 'original_bus_id')) {
                            $busQuery->orWhere('original_bus_id', $report->bus_id);
                        }
                    });
            });

        if ($entry->trip_ticket) {
            $exact = (clone $query)->where('trip_code', $entry->trip_ticket)->first();
            if ($exact) {
                return $exact;
            }
        }

        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return array{
     *     matched: bool,
     *     trip_code: string|null,
     *     shift: string|null,
     *     route_label: string|null,
     *     scheduled_departure: string|null,
     *     scheduled_arrival: string|null,
     *     actual_departure: string|null,
     *     actual_arrival: string|null,
     *     departure_delay_minutes: int,
     *     arrival_delay_minutes: int,
     *     delay_minutes: int,
     *     status: string,
     *     status_detail: string
     * }
     */
    public function comparison(
        ?TripSchedule $schedule,
        mixed $actualDeparture,
        mixed $actualArrival
    ): array {
        $actualDepartureString = $this->timeString($actualDeparture);
        $actualArrivalString = $this->timeString($actualArrival);

        if (! $schedule) {
            return [
                'matched' => false,
                'trip_code' => null,
                'shift' => null,
                'route_label' => null,
                'scheduled_departure' => null,
                'scheduled_arrival' => null,
                'actual_departure' => $actualDepartureString,
                'actual_arrival' => $actualArrivalString,
                'departure_delay_minutes' => 0,
                'arrival_delay_minutes' => 0,
                'delay_minutes' => 0,
                'status' => 'Schedule match unavailable',
                'status_detail' => 'No exact scheduled trip could be matched to this report.',
            ];
        }

        $scheduledDeparture = $this->timeString($schedule->departure_time);
        $scheduledArrival = $this->timeString($schedule->estimated_arrival_time);

        $departureDelay = $this->delayMinutes(
            $scheduledDeparture,
            $actualDepartureString
        );
        $arrivalDelay = $this->delayMinutes(
            $scheduledArrival,
            $actualArrivalString
        );
        $delayMinutes = max($departureDelay, $arrivalDelay, 0);

        $isDelayed = $delayMinutes > self::LATE_THRESHOLD_MINUTES;

        return [
            'matched' => true,
            'trip_code' => $schedule->trip_code,
            'shift' => $schedule->shift,
            'route_label' => $schedule->shuttleRoute
                ? trim(
                    ($schedule->shuttleRoute->route_code ?? '')
                    .' - '
                    .($schedule->shuttleRoute->route_name ?? '')
                )
                : null,
            'scheduled_departure' => $scheduledDeparture,
            'scheduled_arrival' => $scheduledArrival,
            'actual_departure' => $actualDepartureString,
            'actual_arrival' => $actualArrivalString,
            'departure_delay_minutes' => $departureDelay,
            'arrival_delay_minutes' => $arrivalDelay,
            'delay_minutes' => $delayMinutes,
            'status' => $isDelayed ? 'Delayed' : 'On Time',
            'status_detail' => $isDelayed
                ? "Late by {$delayMinutes} min"
                : 'Within the 10-minute tolerance',
        ];
    }

    private function scheduleQuery(): Builder
    {
        return TripSchedule::query()
            ->with(['shuttleRoute', 'assignment']);
    }

    private function delayMinutes(?string $scheduled, ?string $actual): int
    {
        if (! $scheduled || ! $actual) {
            return 0;
        }

        $scheduledMinutes = $this->minutesFromMidnight($scheduled);
        $actualMinutes = $this->minutesFromMidnight($actual);

        $difference = $actualMinutes - $scheduledMinutes;

        if ($difference < -720) {
            $difference += 1440;
        } elseif ($difference > 720) {
            $difference -= 1440;
        }

        return $difference;
    }

    private function minutesFromMidnight(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        $parsed = Carbon::parse($time);

        return ($parsed->hour * 60) + $parsed->minute;
    }

    private function timeString(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        return Carbon::parse((string) $time)->format('H:i');
    }
}
