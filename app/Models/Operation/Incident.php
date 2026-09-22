<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_no',
        'trip_schedule_id',
        'trip_assignment_id',
        'bus_id',
        'driver_id',
        'driver_name',
        'incident_type',
        'location',
        'description',
        'incident_reported_at',
        'status',
        'resolution_notes',
        'resolved_at',
        'reported_by',
        'resolved_by',
    ];

    protected $casts = [
        'incident_reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Incident $incident): void {
            if (
                Schema::hasColumn('incidents', 'trip_assignment_id')
                && $incident->trip_schedule_id
                && ! $incident->trip_assignment_id
            ) {
                $incident->trip_assignment_id = TripAssignment::query()
                    ->where('trip_schedule_id', $incident->trip_schedule_id)
                    ->value('id');
            }

            if (
                $incident->isDirty('status')
                && $incident->status !== 'Resolved'
            ) {
                $incident->resolved_at = null;
                $incident->resolved_by = null;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'incident_no';
    }

    public function tripSchedule(): BelongsTo
    {
        return $this->belongsTo(
            TripSchedule::class,
            'trip_schedule_id'
        );
    }

    public function tripAssignment(): BelongsTo
    {
        return $this->belongsTo(
            TripAssignment::class,
            'trip_assignment_id'
        );
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'bus_id'
        );
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by'
        );
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        );
    }

    public function responses(): HasMany
    {
        return $this->hasMany(
            IncidentResponse::class,
            'incident_id'
        )->orderBy('created_at');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(
            IncidentReplacement::class,
            'incident_id'
        );
    }
}
