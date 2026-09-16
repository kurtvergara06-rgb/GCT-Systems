<?php

namespace App\Models\Operation;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDriverReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'ddr_no',
        'report_date',
        'driver_id',
        'driver_name',
        'bus_id',
        'trip_ticket',
        'from_location',
        'to_location',
        'departure_time',
        'arrival_time',
        'passengers',
        'encoded_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
        'passengers' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'ddr_no';
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            Driver::class,
            'driver_id',
            'driver_id'
        );
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'bus_id'
        );
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'encoded_by'
        );
    }
}