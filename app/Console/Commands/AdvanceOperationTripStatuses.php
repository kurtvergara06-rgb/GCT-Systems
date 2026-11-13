<?php

namespace App\Console\Commands;

use App\Models\Operation\TripSchedule;
use App\Services\Operation\TripDdrLifecycleService;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdvanceOperationTripStatuses extends Command
{
    use SystemDataUpdateBroadcaster;

    protected $signature = 'operation:advance-trip-statuses';

    protected $description = 'Advance approved assigned trips by departure and ETA to Pending DDR';

    public function handle(): int
    {
        $now = now(config('app.business_timezone', 'Asia/Manila'));
        $updated = 0;

        // Recover fully assigned schedules whose operational status was left at
        // Scheduled (for example, historical imports or demo-data inserts).
        // Reuse the model's availability and required-slot checks rather than
        // assuming that a stale assignment_status means the trip is ready.
        TripSchedule::query()
            ->where('status', 'Scheduled')
            ->where('assignment_status', 'Assigned')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) use (&$updated): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$updated): void {
                        $trip = TripSchedule::query()->lockForUpdate()->find($row->id);
                        if (! $trip || $trip->status !== 'Scheduled'
                            || $trip->assignment_status !== 'Assigned') {
                            return;
                        }

                        $trip->syncAssignmentStatus(false);

                        // Legacy DEMO records were inserted with assigned slots but
                        // without the model's Ready transition. A bus's *current*
                        // maintenance status cannot tell us whether it was usable
                        // on a historical demo trip date. Recover only fully
                        // assigned, departed demo records; keep real trip safety
                        // decisions and all future readiness checks unchanged.
                        if ($trip->status === 'Scheduled'
                            && str_starts_with((string) $trip->trip_code, 'TRIP-DEMO')
                            && $trip->hasDeparted()
                            && $trip->is_fully_assigned
                            && $trip->assignments()->exists()) {
                            $trip->status = 'Ready';
                        }

                        if (! $trip->isDirty()) {
                            return;
                        }

                        $trip->save();
                        $updated++;
                    }, 3);
                }
            });

        TripSchedule::query()
            ->whereIn('status', ['Ready', 'Dispatched'])
            ->whereDate('trip_date', '<=', $now->toDateString())
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) use ($now, &$updated): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $now, &$updated): void {
                        $trip = TripSchedule::query()->lockForUpdate()->find($row->id);
                        if (! $trip || ! in_array($trip->status, ['Ready', 'Dispatched'], true)) {
                            return;
                        }
                        if ($trip->assignments()->doesntExist()
                            || $trip->assignment_status !== 'Assigned'
                            || $trip->departureDateTime()->greaterThan($now)) {
                            return;
                        }

                        // ETA does not clear a breakdown: leave the parent In Progress
                        // while affected bus slots await a verified incident outcome.
                        // Healthy slots may still submit DDRs independently.
                        $etaPassed = $trip->estimatedArrivalDateTime()->lessThanOrEqualTo($now);
                        $incidentHold = $etaPassed
                            && app(TripDdrLifecycleService::class)->hasIncidentHold($trip);
                        $nextStatus = $etaPassed && ! $incidentHold
                            ? 'Pending DDR'
                            : 'Dispatched';

                        // Do not re-save or re-broadcast an already-dispatched trip
                        // on every minute of the scheduler cycle.
                        if ($nextStatus === $trip->status) {
                            return;
                        }

                        $trip->status = $nextStatus;
                        $trip->save();
                        $updated++;

                        if ($trip->status === 'Pending DDR') {
                            app(TripDdrLifecycleService::class)->reconcile($trip->id);
                        }
                    }, 3);
                }
            });

        if ($updated) {
            $this->broadcastSystemDataUpdated(
                'Operation', 'TripSchedule', 'updated',
                'trip-time-advance:'.$now->format('Y-m-d H:i'),
                $updated === 1
                    ? 'The status of 1 trip was automatically updated based on its schedule.'
                    : "The statuses of {$updated} trips were automatically updated based on their schedules."
            );
        }
        $this->info("Advanced {$updated} trip status(es).");

        return self::SUCCESS;
    }
}
