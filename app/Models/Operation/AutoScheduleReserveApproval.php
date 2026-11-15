<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoScheduleReserveApproval extends Model
{
    protected $fillable = [
        'trip_assignment_id',
        'recorded_by',
        'approved_by',
        'approval_method',
        'approved_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function tripAssignment(): BelongsTo
    {
        return $this->belongsTo(TripAssignment::class);
    }
}
