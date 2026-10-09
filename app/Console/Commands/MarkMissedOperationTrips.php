<?php

namespace App\Console\Commands;

use App\Models\Operation\TripSchedule;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkMissedOperationTrips extends Command
{
    use SystemDataUpdateBroadcaster;

    protected $signature = 'operation:mark-missed-trips';

    protected $description = 'Mark departed unassigned trips without operational history as Missed';

    public function handle(): int
    {
        $now = now(config('app.business_timezone', 'Asia/Manila'))->startOfMinute();
        $date = $now->toDateString();
        $time = $now->format('H:i:s');
        $updated = 0;

        TripSchedule::query()
            ->where('status', 'Scheduled')
            ->where('assignment_status', 'Unassigned')
            ->whereDoesntHave('assignment')
            ->whereDoesntHave('dailyDriverReports')
            ->whereDoesntHave('incidents')
            ->where(function ($query) use ($date, $time): void {
                $query->whereDate('trip_date', '<', $date)
                    ->orWhere(function ($today) use ($date, $time): void {
                        $today->whereDate('trip_date', $date)
                            ->whereTime('departure_time', '<', $time);
                    });
            })
            ->select('id')
            ->chunkById(100, function ($trips) use (&$updated, $date, $time): void {
                foreach ($trips as $trip) {
                    DB::transaction(function () use ($trip, &$updated, $date, $time): void {
                        $locked = TripSchedule::query()
                            ->whereKey($trip->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $locked || $locked->status !== 'Scheduled'
                            || $locked->assignment_status !== 'Unassigned'
                            || $locked->hasOperationalHistory()) {
                            return;
                        }

                        $departure = $locked->trip_date?->toDateString();
                        if ($departure === null || $departure > $date
                            || ($departure === $date && $locked->departure_time >= $time)) {
                            return;
                        }

                        $locked->update(['status' => 'Missed']);
                        $updated++;
                    });
                }
            });

        if ($updated > 0) {
            $this->broadcastSystemDataUpdated(
                'Operation',
                'TripSchedule',
                'updated',
                "missed:{$date}",
                "{$updated} overdue trip(s) were marked as Missed."
            );
        }

        $this->info("Marked {$updated} overdue trips as Missed.");

        return self::SUCCESS;
    }
}
