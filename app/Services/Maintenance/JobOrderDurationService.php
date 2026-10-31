<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\JobOrder;

class JobOrderDurationService
{
    public const MINIMUM_SAMPLES = 3;

    public function suggestion(string $maintenanceType, ?string $maintenanceJob = null): ?array
    {
        $maintenanceJob = trim((string) $maintenanceJob);
        if (mb_strtolower($maintenanceJob) === 'other') {
            return null;
        }

        $minutes = JobOrder::query()
            ->where('status', 'Completed')
            ->where('review_status', 'Approved')
            ->where('maintenance_type', $maintenanceType)
            ->when($maintenanceJob !== '', fn ($query) => $query
                ->whereRaw('LOWER(TRIM(work_to_perform)) = ?', [mb_strtolower($maintenanceJob)]))
            ->whereNotNull('actual_started_at')
            ->whereNotNull('actual_completed_at')
            ->whereColumn('actual_completed_at', '>=', 'actual_started_at')
            ->get(['actual_started_at', 'actual_completed_at'])
            ->map(fn (JobOrder $jobOrder) => $jobOrder->actual_started_at->diffInMinutes($jobOrder->actual_completed_at))
            ->filter(fn ($value) => $value > 0)
            ->sort()
            ->values();

        if ($minutes->count() < self::MINIMUM_SAMPLES) {
            return null;
        }

        $middle = intdiv($minutes->count(), 2);
        $median = $minutes->count() % 2
            ? $minutes[$middle]
            : ($minutes[$middle - 1] + $minutes[$middle]) / 2;

        if ($median >= 1440 && fmod($median, 1440.0) === 0.0) {
            return ['value' => $median / 1440, 'unit' => 'Days', 'sample_size' => $minutes->count()];
        }

        if ($median >= 60) {
            return ['value' => round($median / 60, 2), 'unit' => 'Hours', 'sample_size' => $minutes->count()];
        }

        return ['value' => $median, 'unit' => 'Minutes', 'sample_size' => $minutes->count()];
    }
}
