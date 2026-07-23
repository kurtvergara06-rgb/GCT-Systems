<?php

namespace App\Models\Maintenance;

use App\Models\Admin\GpsTripRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_date',
        'bus_no',
        'driver_name',
        'gps_trip_record_id',
        'distance_km',
        'distance_source',
        'fuel_liters',
        'km_per_liter',
        'status',
        'remarks',
        'manual_distance_reason',
    ];

    protected $casts = [
        'report_date' => 'date',
        'distance_km' => 'decimal:2',
        'fuel_liters' => 'decimal:2',
        'km_per_liter' => 'decimal:2',
    ];

    public function gpsTripRecord(): BelongsTo
    {
        return $this->belongsTo(
            GpsTripRecord::class,
            'gps_trip_record_id'
        );
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'bus_no',
            'bus_no'
        );
    }
}