<?php

namespace App\Models\Operation;

use App\Support\ClientTripTimes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientTripTimeConfiguration extends Model
{
    protected $table = 'client_trip_time_configurations';

    protected $fillable = [
        'operation_client_id',
        'category',
        'start_time',
        'end_time',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ClientTripTimeSlot::class, 'client_trip_time_configuration_id')
            ->orderBy('slot_time');
    }

    public function startMinutes(): int
    {
        return ClientTripTimes::minutesFromTime((string) $this->start_time);
    }

    public function endMinutes(): int
    {
        return ClientTripTimes::minutesFromTime((string) $this->end_time);
    }

    /**
     * Enabled slot minutes from midnight, sorted ascending.
     *
     * @return array<int, int>
     */
    public function enabledSlotMinutes(): array
    {
        return $this->slots
            ->filter(fn (ClientTripTimeSlot $slot): bool => $slot->is_enabled)
            ->map(fn (ClientTripTimeSlot $slot): int => $slot->minutes())
            ->sort()
            ->values()
            ->all();
    }

    public function categoryLabel(): string
    {
        return ClientTripTimes::categoryLabel((string) $this->category);
    }
}
