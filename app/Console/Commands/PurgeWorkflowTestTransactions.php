<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurgeWorkflowTestTransactions extends Command
{
    protected $signature = 'data:purge-workflow-test-transactions
        {--execute : Permanently delete the previewed local transaction records}
        {--confirm-database= : Exact local database name required for execution}
        {--backup= : Existing non-empty SQL backup required for execution}
        {--force : Skip the final interactive confirmation}';

    protected $description = 'Dry-run or purge all local JO, IR, PR, and PO test transactions while preserving inventory and its ledger';

    public function handle(): int
    {
        if (! $this->isConfirmedLocalDatabase()) {
            return self::FAILURE;
        }

        $plan = $this->buildPlan();
        $this->renderPlan($plan);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('DRY RUN ONLY: no database records were changed.');
            $this->line('Create a full local SQL backup, then execute with --execute, --confirm-database, and --backup.');

            return self::SUCCESS;
        }

        if (! $this->validateExecutionGuards()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Permanently delete every previewed JO, IR, PR, and PO transaction from this local database?',
            false
        )) {
            $this->warn('Cleanup cancelled; no records were changed.');

            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(function (): array {
                $this->lockCleanupScope();
                $lockedPlan = $this->buildPlan();
                $inventoryBefore = $this->tableFingerprint('inventory_items');
                $movementsBefore = $this->tableFingerprint('stock_movements');

                $deleted = $this->deletePlannedRecords($lockedPlan);

                if ($inventoryBefore !== $this->tableFingerprint('inventory_items')) {
                    throw new RuntimeException('Inventory items or balances changed; the cleanup was rolled back.');
                }

                if ($movementsBefore !== $this->tableFingerprint('stock_movements')) {
                    throw new RuntimeException('Stock movement history changed; the cleanup was rolled back.');
                }

                return [
                    'deleted' => $deleted,
                    'inventory_count' => DB::table('inventory_items')->count(),
                    'inventory_fingerprint' => $inventoryBefore,
                    'stock_movement_count' => DB::table('stock_movements')->count(),
                    'stock_movement_fingerprint' => $movementsBefore,
                ];
            }, 3);
        } catch (\Throwable $exception) {
            $this->error('Cleanup failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Local workflow transaction cleanup completed.');
        $this->table(
            ['Deleted category', 'Rows'],
            collect($result['deleted'])->map(fn (int $count, string $category): array => [$category, $count])->values()->all()
        );
        $this->line("Inventory preserved: {$result['inventory_count']} rows; fingerprint {$result['inventory_fingerprint']}");
        $this->line("Stock ledger preserved: {$result['stock_movement_count']} rows; fingerprint {$result['stock_movement_fingerprint']}");

        return self::SUCCESS;
    }

    private function isConfirmedLocalDatabase(): bool
    {
        $environment = app()->environment();
        $connection = (string) config('database.default');
        $host = strtolower(trim((string) config("database.connections.{$connection}.host")));
        $database = trim((string) config("database.connections.{$connection}.database"));
        $localHosts = ['127.0.0.1', 'localhost', '::1'];

        if ($environment !== 'local' || ! in_array($host, $localHosts, true)) {
            $this->error("Refusing cleanup: expected local environment and loopback database host; got {$environment} / {$host}.");

            return false;
        }

        if ($database === '') {
            $this->error('Refusing cleanup: the configured database name is empty.');

            return false;
        }

        $this->info("Target confirmed as local: {$connection} database {$database} on {$host}.");

        return true;
    }

    private function validateExecutionGuards(): bool
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! hash_equals($database, trim((string) $this->option('confirm-database')))) {
            $this->error("Execution requires --confirm-database={$database}.");

            return false;
        }

        $backup = trim((string) $this->option('backup'));
        if ($backup === '' || ! is_file($backup) || filesize($backup) === 0) {
            $this->error('Execution requires --backup pointing to an existing, non-empty SQL backup.');

            return false;
        }

        if (strtolower((string) pathinfo($backup, PATHINFO_EXTENSION)) !== 'sql') {
            $this->error('The backup file must use the .sql extension.');

            return false;
        }

        $this->line('Backup verified: '.realpath($backup).' ('.number_format((int) filesize($backup)).' bytes)');

        return true;
    }

    /** @return array<string, mixed> */
    private function buildPlan(): array
    {
        $jobOrders = DB::table('job_orders')->orderBy('id')->get(['id', 'job_order_no']);
        $jobOrderIds = $jobOrders->pluck('id');
        $jobOrderNos = $jobOrders->pluck('job_order_no')->filter();

        $itemRequests = DB::table('maintenance_item_requests')->orderBy('id')->get(['id', 'job_order_id']);
        $purchaseRequests = DB::table('purchase_requests')->orderBy('id')->get(['id', 'pr_no', 'job_order_no']);
        $purchaseRequestIds = $purchaseRequests->pluck('id');
        $purchaseRequestNos = $purchaseRequests->pluck('pr_no')->filter();

        $purchaseOrders = DB::table('purchase_orders')->orderBy('id')->get(['id', 'po_no', 'purchase_request_id']);
        $purchaseOrderIds = $purchaseOrders->pluck('id');
        $purchaseOrderNos = $purchaseOrders->pluck('po_no')->filter();

        $transactionReferences = $jobOrderNos
            ->merge($purchaseRequestNos)
            ->merge($purchaseOrderNos)
            ->filter()
            ->unique()
            ->values();

        $issuances = DB::table('inventory_issuances')
            ->whereIn('reference_no', $transactionReferences)
            ->orderBy('id')
            ->get(['id', 'issue_no', 'reference_no']);
        $issuanceIds = $issuances->pluck('id');

        $preservedMovementReferences = $transactionReferences
            ->merge($issuances->pluck('issue_no'))
            ->filter()
            ->unique()
            ->values();
        $preservedMovements = DB::table('stock_movements')
            ->whereIn('reference_no', $preservedMovementReferences)
            ->orderBy('id')
            ->get(['id', 'reference_no', 'movement_type', 'quantity_change']);

        $notificationCount = DB::table('topbar_notifications')
            ->where(function ($query) use ($jobOrderIds, $purchaseRequestIds, $purchaseOrderIds): void {
                $query->where(fn ($item) => $item->where('entity', 'JobOrder')->whereIn('record_id', $jobOrderIds->map(fn ($id): string => (string) $id)))
                    ->orWhere(fn ($item) => $item->where('entity', 'PurchaseRequest')->whereIn('record_id', $purchaseRequestIds->map(fn ($id): string => (string) $id)))
                    ->orWhere(fn ($item) => $item->where('entity', 'PurchaseOrder')->whereIn('record_id', $purchaseOrderIds->map(fn ($id): string => (string) $id)));
            })
            ->count();

        return [
            'job_orders' => $jobOrders,
            'item_requests' => $itemRequests,
            'purchase_requests' => $purchaseRequests,
            'purchase_orders' => $purchaseOrders,
            'inventory_issuances' => $issuances,
            'inventory_issuance_items_count' => $issuanceIds->isEmpty() ? 0 : DB::table('inventory_issuance_items')->whereIn('inventory_issuance_id', $issuanceIds)->count(),
            'job_order_mechanics_count' => $jobOrderIds->isEmpty() ? 0 : DB::table('job_order_mechanics')->whereIn('job_order_id', $jobOrderIds)->count(),
            'job_order_audits_count' => $jobOrderIds->isEmpty() ? 0 : DB::table('job_order_audits')->whereIn('job_order_id', $jobOrderIds)->count(),
            'notifications_count' => $notificationCount,
            'activity_logs_count' => $transactionReferences->isEmpty() ? 0 : DB::table('activity_logs')->whereIn('reference', $transactionReferences)->count(),
            'mechanic_attendance_links_count' => $jobOrderNos->isEmpty() ? 0 : DB::table('mechanic_attendances')->whereIn('assigned_job', $jobOrderNos)->count(),
            'scheduled_purchase_links_count' => $purchaseOrderIds->isEmpty() ? 0 : DB::table('scheduled_purchases')->whereIn('last_po_id', $purchaseOrderIds)->count(),
            'preserved_movements' => $preservedMovements,
            'inventory_count' => DB::table('inventory_items')->count(),
            'inventory_fingerprint' => $this->tableFingerprint('inventory_items'),
            'stock_movement_count' => DB::table('stock_movements')->count(),
            'stock_movement_fingerprint' => $this->tableFingerprint('stock_movements'),
            'job_order_ids' => $jobOrderIds,
            'job_order_nos' => $jobOrderNos,
            'purchase_request_ids' => $purchaseRequestIds,
            'purchase_order_ids' => $purchaseOrderIds,
            'transaction_references' => $transactionReferences,
            'issuance_ids' => $issuanceIds,
        ];
    }

    /** @param array<string, mixed> $plan */
    private function renderPlan(array $plan): void
    {
        $this->newLine();
        $this->info($this->option('execute') ? 'Workflow cleanup execution plan' : 'Workflow cleanup DRY RUN');
        $this->table(['Category', 'Rows', 'Action'], [
            ['Job Orders', $plan['job_orders']->count(), 'DELETE'],
            ['Maintenance Item Requests', $plan['item_requests']->count(), 'DELETE'],
            ['Purchase Requests', $plan['purchase_requests']->count(), 'DELETE'],
            ['Purchase Orders', $plan['purchase_orders']->count(), 'DELETE'],
            ['Inventory Issuances', $plan['inventory_issuances']->count(), 'DELETE linked wrappers'],
            ['Inventory Issuance Items', $plan['inventory_issuance_items_count'], 'DELETE linked details'],
            ['JO Mechanic Assignments', $plan['job_order_mechanics_count'], 'DELETE'],
            ['JO Audit Records', $plan['job_order_audits_count'], 'DELETE'],
            ['Workflow Notifications', $plan['notifications_count'], 'DELETE'],
            ['Matching Activity Logs', $plan['activity_logs_count'], 'DELETE'],
            ['Mechanic Attendance Links', $plan['mechanic_attendance_links_count'], 'CLEAR assignment only'],
            ['Scheduled Purchase PO Links', $plan['scheduled_purchase_links_count'], 'CLEAR link only'],
            ['Inventory Items / Balances', $plan['inventory_count'], 'PRESERVE unchanged'],
            ['Stock Movements', $plan['stock_movement_count'], 'PRESERVE unchanged'],
        ]);

        $this->identifierTable('Job Orders', $plan['job_orders'], 'job_order_no');
        $this->identifierTable('Maintenance Item Requests', $plan['item_requests'], 'id');
        $this->identifierTable('Purchase Requests', $plan['purchase_requests'], 'pr_no');
        $this->identifierTable('Purchase Orders', $plan['purchase_orders'], 'po_no');
        $this->identifierTable('Linked Inventory Issuances', $plan['inventory_issuances'], 'issue_no');

        $this->newLine();
        $this->info('Preserved linked stock movements');
        if ($plan['preserved_movements']->isEmpty()) {
            $this->line('None linked by transaction reference.');
        } else {
            $this->table(['ID', 'Reference', 'Type', 'Quantity change'], $plan['preserved_movements']->map(fn ($movement): array => [
                $movement->id,
                $movement->reference_no,
                $movement->movement_type,
                $movement->quantity_change,
            ])->all());
        }
        $this->line("Inventory fingerprint: {$plan['inventory_fingerprint']}");
        $this->line("Stock movement fingerprint: {$plan['stock_movement_fingerprint']}");
    }

    private function identifierTable(string $label, Collection $records, string $column): void
    {
        $this->newLine();
        $this->line($label.': '.($records->pluck($column)->map(fn ($value): string => (string) $value)->implode(', ') ?: 'None'));
    }

    private function lockCleanupScope(): void
    {
        foreach (['inventory_items', 'stock_movements', 'inventory_issuance_items', 'inventory_issuances', 'maintenance_item_requests', 'job_order_mechanics', 'job_order_audits', 'purchase_orders', 'purchase_requests', 'job_orders'] as $table) {
            DB::table($table)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    /** @param array<string, mixed> $plan
     * @return array<string, int>
     */
    private function deletePlannedRecords(array $plan): array
    {
        $deleted = [];

        $deleted['inventory_issuance_items'] = $plan['issuance_ids']->isEmpty()
            ? 0
            : DB::table('inventory_issuance_items')->whereIn('inventory_issuance_id', $plan['issuance_ids'])->delete();
        $deleted['inventory_issuances'] = $plan['issuance_ids']->isEmpty()
            ? 0
            : DB::table('inventory_issuances')->whereIn('id', $plan['issuance_ids'])->delete();

        $deleted['workflow_notifications'] = DB::table('topbar_notifications')
            ->where(function ($query) use ($plan): void {
                $query->where(fn ($item) => $item->where('entity', 'JobOrder')->whereIn('record_id', $plan['job_order_ids']->map(fn ($id): string => (string) $id)))
                    ->orWhere(fn ($item) => $item->where('entity', 'PurchaseRequest')->whereIn('record_id', $plan['purchase_request_ids']->map(fn ($id): string => (string) $id)))
                    ->orWhere(fn ($item) => $item->where('entity', 'PurchaseOrder')->whereIn('record_id', $plan['purchase_order_ids']->map(fn ($id): string => (string) $id)));
            })->delete();

        $deleted['activity_logs'] = $plan['transaction_references']->isEmpty()
            ? 0
            : DB::table('activity_logs')->whereIn('reference', $plan['transaction_references'])->delete();

        $deleted['mechanic_attendance_links_cleared'] = $plan['job_order_nos']->isEmpty()
            ? 0
            : DB::table('mechanic_attendances')->whereIn('assigned_job', $plan['job_order_nos'])->update([
                'assigned_job' => null,
                'status' => DB::raw("CASE WHEN status = 'On Duty' THEN 'Present' ELSE status END"),
                'updated_at' => now(),
            ]);

        $deleted['scheduled_purchase_links_cleared'] = $plan['purchase_order_ids']->isEmpty()
            ? 0
            : DB::table('scheduled_purchases')->whereIn('last_po_id', $plan['purchase_order_ids'])->update(['last_po_id' => null]);

        $deleted['maintenance_item_requests'] = DB::table('maintenance_item_requests')->delete();
        $deleted['job_order_mechanics'] = DB::table('job_order_mechanics')->delete();
        $deleted['job_order_audits'] = DB::table('job_order_audits')->delete();
        $deleted['purchase_orders'] = DB::table('purchase_orders')->delete();
        $deleted['purchase_requests'] = DB::table('purchase_requests')->delete();
        $deleted['job_orders'] = DB::table('job_orders')->delete();

        return $deleted;
    }

    private function tableFingerprint(string $table): string
    {
        if (! Schema::hasTable($table)) {
            return hash('sha256', 'missing');
        }

        $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
}
