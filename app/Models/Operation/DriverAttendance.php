<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'driver_name',
        'shift',
        'attendance_date',
        'time_in',
        'time_out',
        'status',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function tripAssignments(): HasMany
    {
        return $this->hasMany(
            TripAssignment::class,
            'driver_attendance_id'
        );
    }
}