<?php

namespace App\Models\Maintenance;

use App\Models\TopbarNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

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

            /*
             * Edit forms may leave the optional estimate field blank. In that
             * case keep the saved estimate instead of rejecting unrelated Job
             * Order updates. A value explicitly entered as zero/invalid still
             * fails validation below.
             */
            if (($value === null || $value === '') && $jobOrder->exists) {
                $savedValue = $jobOrder->getOriginal('estimated_duration_value');
                $savedUnit = $jobOrder->getOriginal('estimated_duration_unit');

                if ($savedValue !== null && $savedValue !== '' && $savedUnit) {
                    $jobOrder->estimated_duration_value = $savedValue;
                    $jobOrder->estimated_duration_unit = $savedUnit;
                    return;
                }
            }

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

        static::retrieved(function (JobOrder $jobOrder): void {
            if (! $jobOrder->is_overdue || app()->runningInConsole()) {
                return;
            }

            try {
                if (! Schema::hasTable('topbar_notifications')) {
                    return;
                }

                $duration = $jobOrder->formatted_estimated_duration;
                $message = "{$jobOrder->job_order_no} exceeded its estimated {$duration} work duration. Please verify whether the repair is completed or needs more time.";

                $alreadyNotified = TopbarNotification::query()
                    ->where('module', 'Maintenance')
                    ->where('entity', 'JobOrder')
                    ->where('action', 'overdue')
                    ->where('record_id', $jobOrder->id)
                    ->where('message', $message)
                    ->exists();

                if (! $alreadyNotified) {
                    TopbarNotification::create([
                        'module' => 'Maintenance',
                        'entity' => 'JobOrder',
                        'action' => 'overdue',
                        'record_id' => $jobOrder->id,
                        'message' => $message,
                        'created_by' => auth()->id(),
                    ]);
                }
            } catch (Throwable) {
                // Overdue highlighting must continue even if notification storage is unavailable.
            }
        });
    }

    public function getEstimatedDueAtAttribute(): ?Carbon
    {
        if (! $this->start_date || ! $this->estimated_duration_value || ! $this->estimated_duration_unit) {
            return null;
        }

        $dueAt = $this->start_date->copy();
        $value = (float) $this->estimated_duration_value;

        return match ($this->estimated_duration_unit) {
            'Minutes' => $dueAt->addMinutes($value),
            'Hours' => $dueAt->addMinutes($value * 60),
            'Days' => $dueAt->addMinutes($value * 1440),
            default => null,
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        $dueAt = $this->estimated_due_at;

        return $this->status === 'On Going'
            && $this->completion_date === null
            && $dueAt !== null
            && now()->greaterThan($dueAt);
    }

    public function getOverdueMinutesAttribute(): int
    {
        if (! $this->is_overdue || ! $this->estimated_due_at) {
            return 0;
        }

        return (int) $this->estimated_due_at->diffInMinutes(now());
    }

    public function getOverdueLabelAttribute(): string
    {
        $minutes = $this->overdue_minutes;

        if ($minutes < 60) {
            return "Exceeded by {$minutes}m";
        }

        if ($minutes < 1440) {
            $hours = intdiv($minutes, 60);
            $remainingMinutes = $minutes % 60;

            return $remainingMinutes > 0
                ? "Exceeded by {$hours}h {$remainingMinutes}m"
                : "Exceeded by {$hours}h";
        }

        $days = intdiv($minutes, 1440);
        $remainingHours = intdiv($minutes % 1440, 60);

        return $remainingHours > 0
            ? "Exceeded by {$days}d {$remainingHours}h"
            : "Exceeded by {$days}d";
    }

    public function getFormattedEstimatedDurationAttribute(): string
    {
        if (! $this->estimated_duration_value || ! $this->estimated_duration_unit) {
            return 'Not set';
        }

        $value = rtrim(rtrim(number_format((float) $this->estimated_duration_value, 2, '.', ''), '0'), '.');

        return "{$value} {$this->estimated_duration_unit}";
    }

    public function pmsSchedule(): BelongsTo
    {
        return $this->belongsTo(PmsSchedule::class, 'pms_schedule_id');
    }
}
