<?php

namespace App\Services\Operation;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\DailyDriverReportTripEntry;
use App\Models\Operation\Incident;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled ETA never verifies a breakdown-affected assignment. Incidents hold
 * only their assigned bus slot; other buses may still submit their DDRs.
 */
class TripDdrLifecycleService
{
    public function isAssignmentOnIncidentHold(TripAssignment $assignment): bool
    {
        return Incident::query()
            ->where('trip_schedule_id', $assignment->trip_schedule_id)
            ->where('incident_type', 'Bus Breakdown')
            ->get()
            ->contains(fn (Incident $incident) => $this->blocksCompletion($incident)
                && ($incident->trip_assignment_id
                    ? (int) $incident->trip_assignment_id === (int) $assignment->id
                    : (int) $incident->bus_id === (int) $assignment->bus_id
                        || ($assignment->original_bus_id && (int) $incident->bus_id === (int) $assignment->original_bus_id)));
    }

    public function hasIncidentHold(TripSchedule $trip): bool
    {
        $assignments = $trip->assignments()->get();
        return $assignments->contains(fn (TripAssignment $assignment) => $this->isAssignmentOnIncidentHold($assignment));
    }

    private function blocksCompletion(Incident $incident): bool
    {
        // Keep interrupted/cancelled/unknown outcomes under Operations review.
        // Existing verified outcomes are stored in resolution_notes.
        return $incident->status !== 'Resolved'
            || ! preg_match('/\\[Trip outcome:\\s*Continued\\]/i', (string) $incident->resolution_notes);
    }

    /**
     * Called after an incident changes status; release an ETA-expired trip only
     * when every affected slot has a verified Continued outcome.
     */
    public function releaseAfterIncident(int $tripScheduleId): void
    {
        DB::transaction(function () use ($tripScheduleId): void {
            $trip = TripSchedule::query()->lockForUpdate()->find($tripScheduleId);
            if (! $trip || ! in_array($trip->status, ['Ready', 'Dispatched', 'Pending DDR'], true)
                || $trip->estimatedArrivalDateTime()->isFuture()
                || $this->hasIncidentHold($trip)) {
                return;
            }
            if ($trip->status !== 'Pending DDR') {
                $trip->status = 'Pending DDR';
                $trip->save();
            }
            $this->reconcile($trip->id);
        }, 3);
    }

    public function reconcile(int $tripScheduleId): void
    {
        DB::transaction(function () use ($tripScheduleId): void {
            $trip = TripSchedule::query()->lockForUpdate()->find($tripScheduleId);
            if (! $trip || in_array($trip->status, ['Cancelled', 'Missed'], true)
                || $this->hasIncidentHold($trip)) {
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
