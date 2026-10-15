<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoScheduleSlotDecision extends Model
{
    protected $fillable = [
        'trip_schedule_id',
        'slot_number',
        'decision',
        'status',
        'notes',
        'decided_by',
        'fulfilled_by_assignment_id',
        'decided_at',
        'resolved_at',
    ];

    protected $casts = [
        'slot_number' => 'integer',
        'decided_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function tripSchedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function fulfilledByAssignment(): BelongsTo
    {
        return $this->belongsTo(TripAssignment::class, 'fulfilled_by_assignment_id');
    }
}
