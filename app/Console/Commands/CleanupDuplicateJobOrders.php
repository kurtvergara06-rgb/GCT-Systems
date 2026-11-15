<?php

namespace App\Console\Commands;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateJobOrders extends Command
{
    protected $signature = 'maintenance:cleanup-duplicate-job-orders
        {--execute : Delete safe duplicate Job Orders instead of performing a dry run}
        {--force : Skip the interactive confirmation when executing}
        {--window-minutes=10 : Maximum creation-time gap between duplicate submissions}';

    protected $description = 'Preview and safely remove duplicate Job Orders that have no downstream workflow links';

    public function handle(): int
    {
        $windowMinutes = max(1, (int) $this->option('window-minutes'));
        $execute = (bool) $this->option('execute');

        $jobOrders = JobOrder::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $groups = $jobOrders
            ->groupBy(fn (JobOrder $jobOrder): string => $this->fingerprint($jobOrder))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        $plan = $this->buildPlan($groups, $windowMinutes);

        $this->info($execute
            ? 'Duplicate Job Order cleanup execution plan:'
            : 'Duplicate Job Order cleanup dry run:');

        if ($plan->isEmpty()) {
            $this->line('No safe duplicate Job Orders were found.');

            return self::SUCCESS;
        }

        $rows = $plan
            ->map(fn (array $item): array => [
                $item['action'],
                $item['job_order_no'],
                $item['keep_job_order_no'],
                $item['reason'],
            ])
            ->all();

        $this->table(
            ['Action', 'Job Order', 'Keep', 'Reason'],
            $rows
        );

        $deletable = $plan->where('action', 'DELETE');

        if ($deletable->isEmpty()) {
            $this->warn('Duplicate-looking records were found, but none are safe to delete.');

            return self::SUCCESS;
        }

        if (! $execute) {
            $this->warn(
                'Dry run only. '.$deletable->count().' Job Order(s) would be deleted. '
                .'Re-run with --execute after reviewing the exact list.'
            );

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm(
                'Delete exactly these '.$deletable->count().' safe duplicate Job Order(s)?',
                false
            )) {
            $this->warn('Cleanup cancelled; no Job Orders were deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;

        foreach ($deletable as $item) {
            $result = DB::transaction(function () use ($item): string {
                $jobOrder = JobOrder::query()
                    ->whereKey($item['id'])
                    ->lockForUpdate()
                    ->first();

                if (! $jobOrder) {
                    return 'missing';
                }

                $protectionReason = $this->protectionReason($jobOrder);

                if ($protectionReason !== null) {
                    return 'protected';
                }

                $keeper = JobOrder::query()
                    ->whereKey($item['keep_id'])
                    ->first();

                if (! $keeper || $this->fingerprint($keeper) !== $this->fingerprint($jobOrder)) {
                    return 'changed';
                }

                if (! $this->withinWindow($keeper->created_at, $jobOrder->created_at, (int) $this->option('window-minutes'))) {
                    return 'changed';
                }

                $jobOrder->delete();

                return 'deleted';
            });

            if ($result === 'deleted') {
                $deleted++;
                $this->line('Deleted '.$item['job_order_no'].'.');
            } else {
                $skipped++;
                $this->warn(
                    'Skipped '.$item['job_order_no'].' because its state changed or it became protected.'
                );
            }
        }

        $this->newLine();
        $this->info($deleted.' duplicate Job Order(s) deleted.');

        if ($skipped > 0) {
            $this->warn($skipped.' candidate(s) were skipped during the final safety check.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<string, Collection<int, JobOrder>>  $groups
     * @return Collection<int, array{
     *     action:string,
     *     id:int,
     *     job_order_no:string,
     *     keep_id:int,
     *     keep_job_order_no:string,
     *     reason:string
     * }>
     */
    private function buildPlan(Collection $groups, int $windowMinutes): Collection
    {
        $plan = collect();

        foreach ($groups as $group) {
            $ordered = $group
                ->sortBy(fn (JobOrder $jobOrder): string => sprintf(
                    '%s-%020d',
                    optional($jobOrder->created_at)->format('Y-m-d H:i:s.u') ?? '0000-00-00 00:00:00.000000',
                    $jobOrder->id
                ))
                ->values();

            $keeper = $ordered->first();

            if (! $keeper) {
                continue;
            }

            foreach ($ordered->slice(1) as $candidate) {
                if (! $this->withinWindow($keeper->created_at, $candidate->created_at, $windowMinutes)) {
                    $keeper = $candidate;
                    continue;
                }

                $protectionReason = $this->protectionReason($candidate);

                $plan->push([
                    'action' => $protectionReason === null ? 'DELETE' : 'SKIP',
                    'id' => $candidate->id,
                    'job_order_no' => $candidate->job_order_no,
                    'keep_id' => $keeper->id,
                    'keep_job_order_no' => $keeper->job_order_no,
                    'reason' => $protectionReason ?? 'Exact operational duplicate within '.$windowMinutes.' minute(s)',
                ]);
            }
        }

        return $plan;
    }

    private function protectionReason(JobOrder $jobOrder): ?string
    {
        if ($jobOrder->status === 'Completed' || $jobOrder->completion_date !== null) {
            return 'Completed Job Orders are preserved';
        }

        if ($jobOrder->pms_schedule_id !== null) {
            return 'Linked to a PMS schedule';
        }

        if ($jobOrder->maintenance_referral_id !== null || $jobOrder->incident_id !== null) {
            return 'Linked to an incident/referral workflow';
        }

        if (PurchaseRequest::query()->where('job_order_no', $jobOrder->job_order_no)->exists()) {
            return 'Linked Purchase Request exists';
        }

        if (! in_array(
            $jobOrder->part_status,
            [null, '', 'No Parts Needed', 'No Parts Required', 'Not Requested'],
            true
        )) {
            return 'Part workflow has already progressed';
        }

        return null;
    }

    private function fingerprint(JobOrder $jobOrder): string
    {
        $fields = [
            $this->normalize($jobOrder->bus_no),
            $this->normalize($jobOrder->problem_issue),
            $this->normalize($jobOrder->work_to_perform),
            $this->normalize($jobOrder->maintenance_type),
            $this->normalize($jobOrder->assigned_mechanic),
            $this->normalize($jobOrder->part_needed),
            $this->normalizeNumber($jobOrder->estimated_duration_value),
            $this->normalize($jobOrder->estimated_duration_unit),
            $this->normalize($jobOrder->status),
            $this->normalize($jobOrder->part_status),
        ];

        return hash('sha256', implode('|', $fields));
    }

    private function normalize(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) ($value ?? '')));

        return mb_strtolower($normalized ?? '');
    }

    private function normalizeNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 4, '.', '');
    }

    private function withinWindow(
        ?CarbonInterface $keeperCreatedAt,
        ?CarbonInterface $candidateCreatedAt,
        int $windowMinutes
    ): bool {
        if (! $keeperCreatedAt || ! $candidateCreatedAt) {
            return false;
        }

        return $keeperCreatedAt->diffInMinutes($candidateCreatedAt) <= $windowMinutes;
    }
}
