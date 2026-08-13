<?php

use App\Models\Admin\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('activity-logs:prune {--days=}', function () {
    $configuredDays = (int) config('activity-log.retention_days', 365);
    $requestedDays = $this->option('days');
    $retentionDays = max(
        30,
        $requestedDays !== null && $requestedDays !== ''
            ? (int) $requestedDays
            : $configuredDays
    );

    $batchSize = max(100, (int) config('activity-log.prune_batch_size', 1000));
    $cutoff = now()->subDays($retentionDays);
    $deleted = 0;

    do {
        $ids = ActivityLog::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        if ($ids->isEmpty()) {
            break;
        }

        $deleted += ActivityLog::query()
            ->whereIn('id', $ids)
            ->delete();
    } while ($ids->count() === $batchSize);

    $this->info(
        "Pruned {$deleted} activity log(s) older than {$retentionDays} days."
    );
})->purpose('Prune activity logs that exceed the configured retention period');

Schedule::command('activity-logs:prune')
    ->dailyAt('02:30')
    ->withoutOverlapping();
