<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_no',
        'plate_no',
        'bus_model',
        'year_model',
        'capacity',
        'route_grouping',
        'status',
        'latest_gps_km',
        'latest_gps_at',
        'last_pms_km',
        'pms_interval_km',
        'next_pms_km',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'latest_gps_km' => 'decimal:2',
        'latest_gps_at' => 'datetime',
        'last_pms_km' => 'decimal:2',
        'pms_interval_km' => 'decimal:2',
        'next_pms_km' => 'decimal:2',
    ];
}