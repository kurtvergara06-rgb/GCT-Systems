<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShuttleRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code',
        'route_name',
        'origin',
        'destination',
        'distance_km',
        'estimated_time_minutes',
        'status',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'estimated_time_minutes' => 'integer',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)
            ->orderBy('stop_order');
    }
}