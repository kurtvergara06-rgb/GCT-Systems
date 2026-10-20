<?php

namespace App\Services\Operation;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\DailyDriverReportTripEntry;
use App\Models\Operation\TripSchedule;
use Illuminate\Support\Facades\DB;

/**
 * A trip is complete only when every internal assigned bus has a linked DDR.
 * A report proves an actual service was documented; the ETA does not.
 */
class TripDdrLifecycleService
{
    public function reconcile(int $tripScheduleId): void
    {
        DB::transaction(function () use ($tripScheduleId): void {
            $trip = TripSchedule::query()->lockForUpdate()->find($tripScheduleId);
            if (! $trip || in_array($trip->status, ['Cancelled', 'Missed'], true)) {
                return;
            }

            $assignmentIds = $trip->assignments()->pluck('id')->map(fn ($id) => (int) $id);
            if ($assignmentIds->isEmpty()) {
                return;
            }

            $directIds = DailyDriverReport::query()
                ->where('trip_schedule_id', $trip->id)
                ->whereIn('trip_assignment_id', $assignmentIds)
                ->pluck('trip_assignment_id');
            $extraIds = DailyDriverReportTripEntry::query()
                ->where('trip_schedule_id', $trip->id)
                ->whereIn('trip_assignment_id', $assignmentIds)
                ->pluck('trip_assignment_id');

            $reported = $directIds->concat($extraIds)->map(fn ($id) => (int) $id)->unique();
            if ($reported->isEmpty()) {
                return;
            }

            $trip->status = $assignmentIds->every(fn ($id) => $reported->contains($id))
                ? 'Completed'
                : 'Pending DDR';
            $trip->save();
        }, 3);
    }
}
