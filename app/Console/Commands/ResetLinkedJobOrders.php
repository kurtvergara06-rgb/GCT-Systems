<?php

namespace App\Console\Commands;

use App\Services\Maintenance\LinkedJobOrderResetService;
use Illuminate\Console\Command;
use Throwable;

class ResetLinkedJobOrders extends Command
{
    protected $signature = 'maintenance:reset-linked-job-orders
        {--job-order=* : Exact Job Order number(s) to inspect/reset}
        {--execute : Execute only plans proven safe; default is dry-run}
        {--force : Skip the final confirmation (requires --execute)}';

    protected $description = 'Dry-run or safely reset exact Job Orders and their proven linked workflow records';

    public function handle(LinkedJobOrderResetService $service): int
    {
        $jobOrderNos = collect($this->option('job-order'))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($jobOrderNos->isEmpty()) {
            $this->error('Specify at least one exact --job-order=JO-... value.');

            return self::FAILURE;
        }

        if ($this->option('force') && ! $this->option('execute')) {
            $this->error('--force is only valid together with --execute.');

            return self::FAILURE;
        }

        $plans = $jobOrderNos->map(fn (string $jobOrderNo): array => $service->plan($jobOrderNo));

        $this->info($this->option('execute') ? 'Linked Job Order reset execution plan:' : 'Linked Job Order reset DRY RUN:');
        $this->table(
            ['JO', 'Linked PMS', 'Referral / Incident', 'Linked PR(s)', 'Linked PO(s)', 'Warehouse records', 'Inventory effects', 'Mechanic', 'Action', 'Reason'],
            $plans->map(fn (array $plan): array => $this->tableRow($plan))->all()
        );

        $ready = $plans->whereIn('action', ['READY', 'READY WITH INVENTORY REVERSAL'])->values();
        $blocked = $plans->where('action', 'BLOCK')->values();

        $this->newLine();
        $this->line($ready->count().' Job Order(s) ready; '.$blocked->count().' blocked.');

        if (! $this->option('execute')) {
            $this->warn('DRY RUN ONLY: no records were changed. Add --execute only after reviewing this plan.');

            return self::SUCCESS;
        }

        if ($ready->isEmpty()) {
            $this->warn('Nothing is safe to reset.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Execute the READY reset plans shown above?', false)) {
            $this->warn('Reset cancelled; no records were changed.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($ready as $plan) {
            try {
                $result = $service->reset($plan['job_order_no']);
                $this->info(sprintf(
                    'Reset %s: %d PR(s), %d PO(s), %d issuance(s), %d inventory reversal(s).',
                    $result['job_order_no'],
                    $result['purchase_requests_deleted'],
                    $result['purchase_orders_deleted'],
                    $result['issuances_deleted'],
                    $result['inventory_reversals']
                ));
            } catch (Throwable $exception) {
                $failures++;
                $this->error("{$plan['job_order_no']} rolled back: {$exception->getMessage()}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $plan */
    private function tableRow(array $plan): array
    {
        $pms = $plan['pms']
            ? 'PMS #'.$plan['pms']->id.' (preserve; '.$plan['pms_other_job_orders'].' other JO)'
            : 'None';
        $referralIncident = collect([
            $plan['referral'] ? 'Referral #'.$plan['referral']->id.' (reset to Approved)' : null,
            $plan['incident'] ? ($plan['incident']->incident_no ?? 'Incident #'.$plan['incident']->id).' (preserve)' : null,
        ])->filter()->implode('; ') ?: 'None';
        $prs = $plan['purchase_requests']->map(fn ($pr): string => "{$pr->pr_no} [{$pr->status}]")->implode(', ') ?: 'None';
        $pos = $plan['purchase_orders']->map(fn ($po): string => "{$po->po_no} [{$po->status}]")->implode(', ') ?: 'None';
        $warehouse = collect([
            $plan['scheduled_purchases']->count().' scheduled link(s)',
            $plan['issuances']->count().' issuance(s)',
            $plan['movements']->count().' movement(s)',
            $plan['notification_count'].' notification(s)',
        ])->implode('; ');

        return [
            $plan['job_order_no'].' ['.$plan['job_order_status'].']',
            $pms,
            $referralIncident,
            $prs,
            $pos,
            $warehouse,
            $plan['inventory_effects'],
            $plan['mechanic'] ?: 'None',
            $plan['action'],
            $plan['reason'],
        ];
    }
}
