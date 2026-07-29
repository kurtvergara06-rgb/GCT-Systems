<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShuttleRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code', 'route_name', 'origin', 'origin_address',
        'origin_latitude', 'origin_longitude', 'origin_source',
        'destination', 'destination_address', 'destination_latitude',
        'destination_longitude', 'destination_source', 'distance_km',
        'estimated_time_minutes', 'calculated_distance_km',
        'calculated_time_minutes', 'distance_source', 'distance_is_manual',
        'time_is_manual', 'route_geometry', 'route_calculated_at', 'status',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'estimated_time_minutes' => 'integer',
        'calculated_distance_km' => 'decimal:2',
        'calculated_time_minutes' => 'integer',
        'origin_latitude' => 'decimal:7',
        'origin_longitude' => 'decimal:7',
        'destination_latitude' => 'decimal:7',
        'destination_longitude' => 'decimal:7',
        'distance_is_manual' => 'boolean',
        'time_is_manual' => 'boolean',
        'route_geometry' => 'array',
        'route_calculated_at' => 'datetime',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }
}