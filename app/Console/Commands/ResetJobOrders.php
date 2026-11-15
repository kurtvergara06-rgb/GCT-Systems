<?php

namespace App\Console\Commands;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\MechanicAttendance;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResetJobOrders extends Command
{
    protected $signature = 'maintenance:reset-job-orders
        {--job-order=* : Exact Job Order number(s) to reset}
        {--bus= : Select active Job Orders for this exact bus number}
        {--maintenance= : Narrow bus selection to this exact maintenance type}
        {--execute : Delete the previewed safe Job Orders and their safe linked PRs}
        {--force : Skip the interactive confirmation when executing}';

    protected $description = 'Safely preview or reset selected active Job Orders and Maintenance-stage Purchase Requests';

    private const SAFE_PR_STATUSES = [
        'Submitted',
        'Rejected',
    ];

    public function handle(): int
    {
        $jobOrderNos = collect($this->option('job-order'))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $bus = trim((string) ($this->option('bus') ?? ''));
        $maintenance = trim((string) ($this->option('maintenance') ?? ''));

        if ($jobOrderNos->isEmpty() && $bus === '') {
            $this->error('Specify at least one --job-order=JO-... or an exact --bus=BUS-... filter.');

            return self::FAILURE;
        }

        $jobOrders = $this->selectionQuery($jobOrderNos, $bus, $maintenance)->get();

        if ($jobOrders->isEmpty()) {
            $this->warn('No matching active Job Orders were found.');

            return self::SUCCESS;
        }

        $plan = $jobOrders
            ->map(fn (JobOrder $jobOrder): array => $this->planFor($jobOrder))
            ->values();

        $this->info($this->option('execute')
            ? 'Job Order reset execution plan:'
            : 'Job Order reset dry run:');

        $this->table(
            ['Action', 'Job Order', 'Bus', 'Maintenance', 'Mechanic', 'JO Status', 'PR(s)', 'Reason'],
            $plan->map(fn (array $row): array => [
                $row['action'],
                $row['job_order_no'],
                $row['bus_no'],
                $row['maintenance_type'],
                $row['assigned_mechanic'] ?: '—',
                $row['status'],
                $row['purchase_requests'] ?: '—',
                $row['reason'],
            ])->all()
        );

        $deletable = $plan->where('action', 'DELETE')->values();
        $blocked = $plan->where('action', 'BLOCK')->values();

        $this->newLine();
        $this->line($deletable->count().' Job Order(s) are safe to reset.');
        $this->line($blocked->count().' Job Order(s) are protected and will not be deleted.');

        if (! $this->option('execute')) {
            $this->warn('Dry run only. Review the exact Job Order numbers above before using --execute.');

            return self::SUCCESS;
        }

        if ($deletable->isEmpty()) {
            $this->warn('Nothing is safe to delete.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm(
                'Delete exactly these '.$deletable->count().' Job Order(s) and their listed safe linked PR(s)?',
                false
            )) {
            $this->warn('Reset cancelled; no records were deleted.');

            return self::SUCCESS;
        }

        $mechanics = collect();
        $deletedJobOrders = 0;
        $deletedPurchaseRequests = 0;

        foreach ($deletable as $item) {
            $result = DB::transaction(function () use ($item): array {
                $jobOrder = JobOrder::query()
                    ->whereKey($item['id'])
                    ->lockForUpdate()
                    ->first();

                if (! $jobOrder) {
                    return ['deleted' => false, 'prs' => 0, 'mechanic' => null];
                }

                $freshPlan = $this->planFor($jobOrder);

                if ($freshPlan['action'] !== 'DELETE') {
                    return ['deleted' => false, 'prs' => 0, 'mechanic' => null];
                }

                $purchaseRequests = PurchaseRequest::query()
                    ->where('job_order_no', $jobOrder->job_order_no)
                    ->lockForUpdate()
                    ->get();

                $prIds = $purchaseRequests->pluck('id');

                if ($prIds->isNotEmpty()
                    && PurchaseOrder::query()->whereIn('purchase_request_id', $prIds)->exists()) {
                    return ['deleted' => false, 'prs' => 0, 'mechanic' => null];
                }

                $prCount = $purchaseRequests->count();
                $mechanic = trim((string) ($jobOrder->assigned_mechanic ?? ''));

                PurchaseRequest::query()
                    ->where('job_order_no', $jobOrder->job_order_no)
                    ->delete();

                $jobOrder->delete();

                return [
                    'deleted' => true,
                    'prs' => $prCount,
                    'mechanic' => $mechanic !== '' ? $mechanic : null,
                ];
            });

            if (! $result['deleted']) {
                $this->warn('Skipped '.$item['job_order_no'].' because its workflow state changed.');

                continue;
            }

            $deletedJobOrders++;
            $deletedPurchaseRequests += $result['prs'];

            if ($result['mechanic']) {
                $mechanics->push($result['mechanic']);
            }

            $this->line(
                'Deleted '.$item['job_order_no']
                .($result['prs'] > 0 ? ' + '.$result['prs'].' linked PR(s).' : '.')
            );
        }

        $this->releaseMechanics($mechanics->unique()->values());

        $this->newLine();
        $this->info($deletedJobOrders.' Job Order(s) deleted.');
        $this->info($deletedPurchaseRequests.' linked Purchase Request(s) deleted.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, string>  $jobOrderNos
     */
    private function selectionQuery(
        Collection $jobOrderNos,
        string $bus,
        string $maintenance
    ): Builder {
        return JobOrder::query()
            ->when(
                $jobOrderNos->isNotEmpty(),
                fn (Builder $query): Builder => $query->whereIn('job_order_no', $jobOrderNos->all()),
                function (Builder $query) use ($bus, $maintenance): Builder {
                    return $query
                        ->where('status', '!=', 'Completed')
                        ->where('bus_no', $bus)
                        ->when(
                            $maintenance !== '',
                            fn (Builder $maintenanceQuery): Builder =>
                                $maintenanceQuery->where('maintenance_type', $maintenance)
                        );
                }
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{
     *     action:string,
     *     id:int,
     *     job_order_no:string,
     *     bus_no:string,
     *     maintenance_type:string,
     *     assigned_mechanic:string,
     *     status:string,
     *     purchase_requests:string,
     *     reason:string
     * }
     */
    private function planFor(JobOrder $jobOrder): array
    {
        $purchaseRequests = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->orderBy('id')
            ->get();

        $prSummary = $purchaseRequests
            ->map(fn (PurchaseRequest $request): string =>
                $request->pr_no.' ['.$request->status.']')
            ->implode(', ');

        $reason = $this->protectionReason($jobOrder, $purchaseRequests);

        return [
            'action' => $reason === null ? 'DELETE' : 'BLOCK',
            'id' => $jobOrder->id,
            'job_order_no' => (string) $jobOrder->job_order_no,
            'bus_no' => (string) $jobOrder->bus_no,
            'maintenance_type' => (string) $jobOrder->maintenance_type,
            'assigned_mechanic' => (string) ($jobOrder->assigned_mechanic ?? ''),
            'status' => (string) $jobOrder->status,
            'purchase_requests' => $prSummary,
            'reason' => $reason ?? (
                $purchaseRequests->isEmpty()
                    ? 'No downstream workflow; safe to recreate'
                    : 'Only Maintenance-stage PR(s); safe to reset together'
            ),
        ];
    }

    /**
     * @param  Collection<int, PurchaseRequest>  $purchaseRequests
     */
    private function protectionReason(
        JobOrder $jobOrder,
        Collection $purchaseRequests
    ): ?string {
        if ($jobOrder->status === 'Completed' || $jobOrder->completion_date !== null) {
            return 'Completed Job Orders are historical records';
        }

        if ($jobOrder->pms_schedule_id !== null) {
            return 'Linked to a PMS schedule';
        }

        if ($jobOrder->maintenance_referral_id !== null || $jobOrder->incident_id !== null) {
            return 'Linked to an incident/referral workflow';
        }

        if ($purchaseRequests->isEmpty()) {
            return null;
        }

        $unsafeStatuses = $purchaseRequests
            ->pluck('status')
            ->filter(fn ($status): bool => ! in_array($status, self::SAFE_PR_STATUSES, true))
            ->unique()
            ->values();

        if ($unsafeStatuses->isNotEmpty()) {
            return 'PR workflow already progressed: '.$unsafeStatuses->implode(', ');
        }

        $prIds = $purchaseRequests->pluck('id');

        if (PurchaseOrder::query()->whereIn('purchase_request_id', $prIds)->exists()) {
            return 'A Purchase Order already exists';
        }

        return null;
    }

    /**
     * @param  Collection<int, string>  $mechanics
     */
    private function releaseMechanics(Collection $mechanics): void
    {
        foreach ($mechanics as $mechanicName) {
            $stillAssigned = JobOrder::query()
                ->where('assigned_mechanic', $mechanicName)
                ->where('status', '!=', 'Completed')
                ->exists();

            if ($stillAssigned) {
                continue;
            }

            $attendance = MechanicAttendance::query()
                ->where('mechanic_name', $mechanicName)
                ->whereDate('attendance_date', today())
                ->latest('id')
                ->first();

            if ($attendance && $attendance->status === 'On Duty') {
                $attendance->update(['status' => 'Present']);
            }
        }
    }
}
