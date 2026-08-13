<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::saving(function (DriverAttendance $attendance): void {
            $driver = Driver::query()
                ->where('driver_name', $attendance->driver_name)
                ->first();

            if (! $driver) {
                throw ValidationException::withMessages([
                    'driver_name' => 'Select an existing driver from the Driver Master List.',
                ]);
            }

            $attendance->driver_id = $driver->driver_id;
            $attendance->driver_name = $driver->driver_name;

            if (empty($attendance->shift)) {
                $attendance->shift = $driver->shift;
            }

            if ($attendance->attendance_date) {
                $duplicate = static::query()
                    ->where('driver_id', $driver->driver_id)
                    ->whereDate('attendance_date', $attendance->attendance_date)
                    ->when($attendance->exists, fn ($query) => $query->whereKeyNot($attendance->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'attendance_date' => 'This driver already has an attendance record for the selected date.',
                    ]);
                }
            }
        });
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'driver_id');
    }

    public function tripAssignments(): HasMany
    {
        return $this->hasMany(
            TripAssignment::class,
            'driver_attendance_id'
        );
    }
}
