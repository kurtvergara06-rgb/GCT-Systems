<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelReportTrip extends Model
{
    protected $fillable = ['sequence', 'trip_date', 'ticket_number', 'origin', 'destination', 'distance_km'];

    protected $casts = ['trip_date' => 'date', 'distance_km' => 'decimal:2'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(FuelReport::class, 'fuel_report_id');
    }
}
