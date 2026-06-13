<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;

class MechanicAttendance extends Model
{
    protected $fillable = [
        'mechanic_id',
        'mechanic_name',
        'shift',
        'assigned_job',
        'attendance_date',
        'time_in',
        'time_out',
        'status',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];
}
