<?php

namespace App\Models\Operation;

use App\Services\Operation\DailyDriverReportScheduleMatchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDriverReportTripEntry extends Model
{
    protected $fillable = [
        'sequence', 'trip_schedule_id', 'trip_assignment_id',
        'trip_ticket', 'from_location', 'to_location',
        'departure_time', 'arrival_time', 'passengers', 'km',
    ];

    protected $casts = ['passengers' => 'integer', 'km' => 'decimal:2'];

    protected static function booted(): void
    {
        static::created(function (DailyDriverReportTripEntry $entry): void {
            app(DailyDriverReportScheduleMatchService::class)
                ->persistEntryMatch($entry);
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyDriverReport::class, 'daily_driver_report_id');
    }

    public function tripSchedule(): BelongsTo
    {
        return $this->belongsTo(TripSchedule::class, 'trip_schedule_id');
    }

    public function tripAssignment(): BelongsTo
    {
        return $this->belongsTo(TripAssignment::class, 'trip_assignment_id');
    }
}
