<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_date',
        'bus_no',
        'driver_name',
        'distance_km',
        'fuel_liters',
        'km_per_liter',
        'status',
        'remarks',
    ];

    protected $casts = [
        'report_date' => 'date',
        'distance_km' => 'decimal:2',
        'fuel_liters' => 'decimal:2',
        'km_per_liter' => 'decimal:2',
    ];
}