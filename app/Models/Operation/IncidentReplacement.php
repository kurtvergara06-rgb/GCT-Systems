<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class IncidentReplacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'original_bus_id',
        'replacement_bus_id',
        'dispatched_at',
        'dispatched_by',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (IncidentReplacement $replacement): void {
            if ($replacement->original_bus_id) {
                Bus::query()->find($replacement->original_bus_id)?->placeUnderMaintenance();
            }

            if (! Schema::hasColumn('trip_assignments', 'original_bus_id')) {
                return;
            }

            $incident = $replacement->incident()->first();

            if (! $incident?->trip_schedule_id) {
                return;
            }

            $assignmentQuery = TripAssignment::query()
                ->where('trip_schedule_id', $incident->trip_schedule_id);

            if ($incident->trip_assignment_id) {
                $assignmentQuery->where('id', $incident->trip_assignment_id);
            } elseif ($replacement->original_bus_id || $incident->bus_id) {
                $busId = $replacement->original_bus_id ?: $incident->bus_id;
                $assignmentQuery->where(function ($query) use ($busId): void {
                    $query->where('bus_id', $busId)
                        ->orWhere('original_bus_id', $busId);
                });
            }

            $assignment = $assignmentQuery->lockForUpdate()->first();

            if (! $assignment) {
                return;
            }

            if (! $assignment->original_bus_id) {
                $assignment->original_bus_id = $replacement->original_bus_id
                    ?: $assignment->bus_id;
            }

            // Preserve the existing trip, route, and driver. Only the effective bus changes.
            $assignment->bus_id = $replacement->replacement_bus_id;
            $assignment->save();

            $assignment->tripSchedule?->syncAssignmentStatus();
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(
            Incident::class,
            'incident_id'
        );
    }

    public function originalBus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'original_bus_id'
        );
    }

    public function replacementBus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'replacement_bus_id'
        );
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'dispatched_by'
        );
    }
}
