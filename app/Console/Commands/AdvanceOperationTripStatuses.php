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

                        // A delayed scheduler run can transition directly from Ready
                        // to Pending DDR if the entire planned window has elapsed.
                        $trip->status = $trip->estimatedArrivalDateTime()->lessThanOrEqualTo($now)
                            ? 'Pending DDR'
                            : 'Dispatched';
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
                "{$updated} trip status(es) advanced by scheduled times."
            );
        }
        $this->info("Advanced {$updated} trip status(es).");

        return self::SUCCESS;
    }
}
