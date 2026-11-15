<?php

namespace App\Console\Commands;

use App\Services\SampleDataCleanupService;
use Illuminate\Console\Command;

class CleanupSampleData extends Command
{
    protected $signature = 'data:cleanup-samples
        {--execute : Delete confirmed sample records inside a transaction}
        {--force : Skip the execution confirmation}
        {--orphans : Report possible orphan records only; never delete}';

    protected $description = 'Audit or safely remove explicitly identified demo and simulated records';

    public function handle(SampleDataCleanupService $cleanup): int
    {
        $audit = $cleanup->audit();

        $this->newLine();
        $this->info('Sample Data Cleanup Audit');
        $this->line(str_repeat('-', 25));

        if ($this->option('orphans')) {
            $this->showOrphans($audit['orphans']);
            $this->newLine();
            $this->warn('ORPHAN REPORT ONLY. NO DATA DELETED.');

            return self::SUCCESS;
        }

        $labels = [
            'purchase_requests' => 'PR-GCT / DEMO-PR records',
            'job_orders' => 'JO-GCT / DEMO-JO records',
            'purchase_orders' => 'PO-GCT / DEMO-PO records',
            'trip_schedules' => 'TRIP-GCT / TRIP-DEMO records',
            'daily_driver_reports' => 'DDR-GCT / DEMO-DDR records',
            'incidents' => 'INC-GCT / DEMO-INC records',
            'stock_movements' => 'Demo/simulated stock movements',
            'buses' => 'Known sample buses',
            'drivers' => 'Sample drivers',
            'shuttle_routes' => 'Sample routes',
            'inventory_items' => 'Sample inventory items',
        ];
        $this->table(['Classification', 'Rows'], collect($labels)->map(fn (string $label, string $key) => [$label, $audit['counts'][$key] ?? 0])->values()->all());

        $this->showOrphans($audit['orphans']);
        $masterRows = collect($audit['candidates'])->flatMap(function (array $identifiers, string $type) use ($audit): array {
            return array_map(fn (string $identifier): array => [
                $type,
                $identifier,
                in_array($identifier, $audit['protected'][$type] ?? [], true)
                    ? 'protected: genuine/ambiguous reference'
                    : 'eligible after dependent sample rows are removed',
            ], $identifiers);
        })->values()->all();
        if ($masterRows !== []) {
            $this->newLine();
            $this->info('Sample master classification');
            $this->table(['Type', 'Identifier', 'Status'], $masterRows);
        }

        foreach ($audit['protected'] as $type => $identifiers) {
            foreach ($identifiers as $identifier) {
                $this->warn("Protected {$type}: {$identifier} (referenced by a genuine/ambiguous record)");
            }
        }

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('NO DATA DELETED.');
            $this->line('Review this report, then run with --execute to perform cleanup.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete only the classified, unprotected sample records?')) {
            $this->warn('Cleanup cancelled. NO DATA DELETED.');

            return self::SUCCESS;
        }

        $deleted = $cleanup->execute();
        $this->table(['Deleted category', 'Rows'], collect($deleted)->map(fn (int $count, string $type) => [$type, $count])->values()->all());
        $this->info('Sample cleanup completed in a database transaction.');

        return self::SUCCESS;
    }

    /** @param array<string, array<int, string>> $orphans */
    private function showOrphans(array $orphans): void
    {
        $this->newLine();
        $this->info('Potential orphans (report only)');
        $this->table(['Condition', 'Count', 'Identifiers'], collect($orphans)->map(
            fn (array $identifiers, string $condition) => [$condition, count($identifiers), implode(', ', array_slice($identifiers, 0, 10))]
        )->values()->all());
    }
}
