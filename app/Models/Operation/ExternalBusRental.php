<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalBusRental extends Model
{
    protected $fillable = [
        'trip_schedule_id',
        'slot_number',
        'company_name',
        'plate_number',
        'driver_name',
        'contact_details',
        'status',
        'notes',
        'recorded_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'slot_number' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function tripSchedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }
}
