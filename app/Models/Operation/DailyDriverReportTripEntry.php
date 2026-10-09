<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDriverReportTripEntry extends Model
{
    protected $fillable = [
        'sequence', 'trip_ticket', 'from_location', 'to_location',
        'departure_time', 'arrival_time', 'passengers',
    ];

    protected $casts = ['passengers' => 'integer'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyDriverReport::class, 'daily_driver_report_id');
    }
}
