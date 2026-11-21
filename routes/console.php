<?php

use App\Models\Admin\ActivityLog;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Maintenance\JobOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

// Attendance cutoff: only today's active, unrecorded mechanics become Absent.
// Do not modify recorded/voided history or mechanics working an approved JO.
Artisan::command('maintenance:close-mechanic-attendance', function () {
    $now = CarbonImmutable::now('Asia/Manila');
    if ($now->isWeekend()) {
        $this->info('Weekend: no automatic absences without an approved work schedule.');
        return;
    }
    if ($now->format('H:i') < '17:10') {
        $this->warn('Attendance cutoff has not been reached in Asia/Manila.');
        return;
    }

    $date = $now->toDateString();
    $created = 0;
    DB::transaction(function () use ($date, &$created): void {
        $mechanics = Mechanic::query()->where('employment_status', 'Active')
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($mechanics as $mechanic) {
            // withTrashed protects audit history: never recreate a voided record.
            if (MechanicAttendance::withTrashed()->where('mechanic_id', $mechanic->mechanic_id)
                ->whereDate('attendance_date', $date)->exists()) {
                continue;
            }
            if (JobOrder::query()->assignedToMechanic($mechanic->mechanic_name, $mechanic->mechanic_id)
                ->where('status', 'On Going')->where('review_status', 'Approved')->exists()) {
                continue;
            }
            MechanicAttendance::create([
                'mechanic_id' => $mechanic->mechanic_id,
                'mechanic_name' => $mechanic->mechanic_name,
                'shift' => 'Morning',
                'attendance_date' => $date,
                'time_in' => null,
                'time_out' => null,
                'status' => 'Absent',
            ]);
            $created++;
        }
    });

    $this->info("Marked {$created} unrecorded mechanic(s) Absent for {$date}.");
})->purpose('Close current-day mechanic attendance after the Manila cutoff');

Schedule::command('maintenance:close-mechanic-attendance')
    ->weekdays()
    ->dailyAt('17:10')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('activity-logs:prune')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('operation:mark-missed-trips')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('operation:advance-trip-statuses')
    ->everyMinute()
    ->withoutOverlapping();

// Shared database heartbeat works across web and scheduler instances.
Schedule::call(function (): void {
    if (\Illuminate\Support\Facades\Schema::hasTable('system_service_heartbeats')) {
        DB::table('system_service_heartbeats')->upsert(
            [['service' => 'scheduler', 'last_seen_at' => now()->timestamp]],
            ['service'],
            ['last_seen_at']
        );
    }
})->name('system-service-scheduler-heartbeat')->everyMinute();
