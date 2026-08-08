<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class JobOrder extends Model
{
    protected $fillable = [
        'job_order_no',
        'bus_no',
        'pms_schedule_id',
        'problem_issue',
        'maintenance_type',
        'assigned_mechanic',
        'part_needed',
        'estimated_duration_value',
        'estimated_duration_unit',
        'start_date',
        'completion_date',
        'status',
        'part_status',
    ];

    protected $casts = [
        'estimated_duration_value' => 'decimal:2',
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (JobOrder $jobOrder): void {
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();

            if (! $request->has('estimated_duration_value') && ! $request->has('estimated_duration_unit')) {
                return;
            }

            $value = $request->input('estimated_duration_value');
            $unit = $request->input('estimated_duration_unit');

            if ($value === null || $value === '' || ! is_numeric($value) || (float) $value <= 0) {
                throw ValidationException::withMessages([
                    'estimated_duration_value' => 'Enter a valid estimated work duration greater than zero.',
                ]);
            }

            if (! in_array($unit, ['Minutes', 'Hours', 'Days'], true)) {
                throw ValidationException::withMessages([
                    'estimated_duration_unit' => 'Select Minutes, Hours, or Days for the estimated work duration.',
                ]);
            }

            $jobOrder->estimated_duration_value = round((float) $value, 2);
            $jobOrder->estimated_duration_unit = $unit;
        });
    }

    public function pmsSchedule(): BelongsTo
    {
        return $this->belongsTo(PmsSchedule::class, 'pms_schedule_id');
    }
}
