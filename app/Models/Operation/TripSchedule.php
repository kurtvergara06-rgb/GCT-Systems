<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_code',
        'trip_date',
        'shuttle_route_id',
        'departure_time',
        'estimated_arrival_time',
        'shift',
        'assignment_status',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'trip_date' => 'date',
    ];

    public function shuttleRoute(): BelongsTo
    {
        return $this->belongsTo(
            ShuttleRoute::class,
            'shuttle_route_id'
        );
    }
}