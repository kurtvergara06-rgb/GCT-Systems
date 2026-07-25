<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'driver_name',
        'shift',
        'bus_assignment',
        'attendance_date',
        'time_in',
        'time_out',
        'status',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];
}