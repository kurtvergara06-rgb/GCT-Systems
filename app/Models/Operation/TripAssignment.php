<?php

namespace App\Models\Operation;

use App\Models\Maintenance\Bus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_schedule_id',
        'driver_attendance_id',
        'driver_id',
        'driver_name',
        'bus_id',
        'assigned_by',
    ];

    public function tripSchedule(): BelongsTo
    {
        return $this->belongsTo(
            TripSchedule::class,
            'trip_schedule_id'
        );
    }

    public function driverAttendance(): BelongsTo
    {
        return $this->belongsTo(
            DriverAttendance::class,
            'driver_attendance_id'
        );
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'bus_id'
        );
    }
}
