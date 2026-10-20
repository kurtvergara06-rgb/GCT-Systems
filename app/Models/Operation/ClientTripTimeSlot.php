<?php

namespace App\Models\Operation;

use App\Support\ClientTripTimes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTripTimeSlot extends Model
{
    protected $table = 'client_trip_time_slots';

    protected $fillable = [
        'client_trip_time_configuration_id',
        'slot_time',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(
            ClientTripTimeConfiguration::class,
            'client_trip_time_configuration_id'
        );
    }

    public function minutes(): int
    {
        return ClientTripTimes::minutesFromTime((string) $this->slot_time);
    }
}
