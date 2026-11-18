<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\User;
use Illuminate\Validation\ValidationException;

class MechanicAttendance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mechanic_id',
        'mechanic_name',
        'shift',
        'assigned_job',
        'attendance_date',
        'time_in',
        'time_out',
        'status',
        'void_reason',
        'voided_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (MechanicAttendance $attendance): void {
            // A master-list ID is authoritative. Names are not unique and
            // must never redirect an attendance record to another employee.
            $mechanicId = trim((string) $attendance->mechanic_id);
            $mechanics = Mechanic::query()
                ->when(
                    $mechanicId !== '',
                    fn ($query) => $query->where('mechanic_id', $mechanicId),
                    fn ($query) => $query->whereRaw(
                        'LOWER(TRIM(mechanic_name)) = ?',
                        [mb_strtolower(trim((string) $attendance->mechanic_name))]
                    )
                )
                ->limit(2)
                ->get();

            if ($mechanics->isEmpty()) {
                throw ValidationException::withMessages([
                    'mechanic_name' => 'Select an existing mechanic from the Mechanic Master List.',
                ]);
            }

            if ($mechanics->count() !== 1) {
                throw ValidationException::withMessages([
                    'mechanic_name' => 'This mechanic name is ambiguous. Use a unique Mechanic Master record before saving attendance.',
                ]);
            }

            $mechanic = $mechanics->first();

            $attendance->mechanic_id = $mechanic->mechanic_id;
            $attendance->mechanic_name = $mechanic->mechanic_name;

            if (empty($attendance->shift)) {
                $attendance->shift = $mechanic->shift;
            }

            if ($attendance->attendance_date) {
                // The database unique key also covers voided records. Check
                // history so attempts to replace a voided record fail with
                // a validation message rather than a SQL constraint error.
                $duplicate = static::withTrashed()
                    ->where('mechanic_id', $mechanic->mechanic_id)
                    ->whereDate('attendance_date', $attendance->attendance_date)
                    ->when($attendance->exists, fn ($query) => $query->whereKeyNot($attendance->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'attendance_date' => 'An attendance record already exists for this mechanic and date (including voided history). The original remains reserved for auditing.',
                    ]);
                }
            }
        });
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class, 'mechanic_id', 'mechanic_id');
    }
}
