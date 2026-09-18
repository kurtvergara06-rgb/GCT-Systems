<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
