<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::saving(function (MechanicAttendance $attendance): void {
            $mechanic = Mechanic::query()
                ->where('mechanic_name', $attendance->mechanic_name)
                ->first();

            if (! $mechanic) {
                throw ValidationException::withMessages([
                    'mechanic_name' => 'Select an existing mechanic from the Mechanic Master List.',
                ]);
            }

            $attendance->mechanic_id = $mechanic->mechanic_id;
            $attendance->mechanic_name = $mechanic->mechanic_name;

            if (empty($attendance->shift)) {
                $attendance->shift = $mechanic->shift;
            }

            if ($attendance->attendance_date) {
                $duplicate = static::query()
                    ->where('mechanic_id', $mechanic->mechanic_id)
                    ->whereDate('attendance_date', $attendance->attendance_date)
                    ->when($attendance->exists, fn ($query) => $query->whereKeyNot($attendance->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'attendance_date' => 'This mechanic already has an attendance record for the selected date.',
                    ]);
                }
            }
        });
    }

    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class, 'mechanic_id', 'mechanic_id');
    }
}
