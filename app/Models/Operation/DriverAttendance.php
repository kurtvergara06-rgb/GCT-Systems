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
            $drivers = Driver::query()
                ->whereRaw(
                    'LOWER(TRIM(driver_name)) = ?',
                    [mb_strtolower(trim((string) $attendance->driver_name))]
                )
                ->limit(2)
                ->get();

            if ($drivers->isEmpty()) {
                throw ValidationException::withMessages([
                    'driver_name' => 'Select an existing driver from the Driver Master List.',
                ]);
            }

            if ($drivers->count() !== 1) {
                throw ValidationException::withMessages([
                    'driver_name' => 'This driver name is ambiguous. Use a unique Driver Master record before saving attendance.',
                ]);
            }

            $driver = $drivers->first();

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
