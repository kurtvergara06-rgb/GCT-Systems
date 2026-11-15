<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurgeWorkflowTestTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_workflow_or_inventory_data(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $records = $this->seedLinkedWorkflow();
        $inventoryBefore = DB::table('inventory_items')->orderBy('id')->get();
        $movementsBefore = DB::table('stock_movements')->orderBy('id')->get();

        $this->artisan('data:purge-workflow-test-transactions')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['id' => $records['job_order_id']]);
        $this->assertDatabaseHas('maintenance_item_requests', ['id' => $records['item_request_id']]);
        $this->assertDatabaseHas('purchase_requests', ['id' => $records['purchase_request_id']]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $records['purchase_order_id']]);
        $this->assertEquals($inventoryBefore, DB::table('inventory_items')->orderBy('id')->get());
        $this->assertEquals($movementsBefore, DB::table('stock_movements')->orderBy('id')->get());
    }

    public function test_execute_requires_database_confirmation_and_backup(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->artisan('data:purge-workflow-test-transactions', ['--execute' => true, '--force' => true])
            ->expectsOutputToContain('Execution requires --confirm-database')
            ->assertFailed();
    }

    public function test_execute_deletes_workflow_transactions_but_preserves_inventory_and_ledger(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $records = $this->seedLinkedWorkflow();
        $inventoryBefore = DB::table('inventory_items')->orderBy('id')->get();
        $movementsBefore = DB::table('stock_movements')->orderBy('id')->get();
        $backup = storage_path('framework/testing-workflow-backup.sql');
        file_put_contents($backup, '-- test backup');

        try {
            $this->artisan('data:purge-workflow-test-transactions', [
                '--execute' => true,
                '--confirm-database' => (string) config('database.connections.'.config('database.default').'.database'),
                '--backup' => $backup,
                '--force' => true,
            ])->assertSuccessful();
        } finally {
            @unlink($backup);
        }

        $this->assertDatabaseCount('job_orders', 0);
        $this->assertDatabaseCount('maintenance_item_requests', 0);
        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('inventory_issuances', 0);
        $this->assertDatabaseCount('inventory_issuance_items', 0);
        $this->assertDatabaseCount('job_order_mechanics', 0);
        $this->assertDatabaseCount('job_order_audits', 0);
        $this->assertDatabaseCount('topbar_notifications', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertEquals($inventoryBefore, DB::table('inventory_items')->orderBy('id')->get());
        $this->assertEquals($movementsBefore, DB::table('stock_movements')->orderBy('id')->get());
        $this->assertDatabaseHas('scheduled_purchases', ['id' => $records['scheduled_purchase_id'], 'last_po_id' => null]);
    }

    /** @return array<string, int> */
    private function seedLinkedWorkflow(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Local Tester',
            'email' => 'cleanup@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inventoryItemId = DB::table('inventory_items')->insertGetId([
            'item_code' => 'INV-CLEAN-001',
            'parts_name' => 'Preserved Belt',
            'item_name' => 'Preserved Belt',
            'category' => 'Mechanical',
            'on_hand' => 9,
            'quantity_available' => 9,
            'unit' => 'pc',
            'unit_of_measurement' => 'pc',
            'reorder_level' => 2,
            'status' => 'Available',
            'source' => 'warehouse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobOrderId = DB::table('job_orders')->insertGetId([
            'job_order_no' => 'JO-CLEAN-0001',
            'bus_no' => 'BUS-CLEAN',
            'problem_issue' => 'Test issue',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'part_status' => 'For Purchase',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemRequestId = DB::table('maintenance_item_requests')->insertGetId([
            'job_order_id' => $jobOrderId,
            'requested_by' => $userId,
            'status' => 'Submitted',
            'requested_parts' => json_encode([['item' => 'Preserved Belt', 'quantity' => 1]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $purchaseRequestId = DB::table('purchase_requests')->insertGetId([
            'pr_no' => 'PR-CLEAN-0001',
            'job_order_no' => 'JO-CLEAN-0001',
            'bus_no' => 'BUS-CLEAN',
            'item' => 'Preserved Belt',
            'quantity' => 1,
            'status' => 'For Purchase',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
            'po_no' => 'PO-CLEAN-0001',
            'po_date' => now()->toDateString(),
            'purchase_request_id' => $purchaseRequestId,
            'supplier_name' => 'Test Supplier',
            'items' => json_encode([['item' => 'Preserved Belt', 'quantity' => 1]]),
            'status' => 'Delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $movementId = DB::table('stock_movements')->insertGetId([
            'inventory_item_id' => $inventoryItemId,
            'item_code' => 'INV-CLEAN-001',
            'item_name' => 'Preserved Belt',
            'reference_no' => 'PO-CLEAN-0001',
            'movement_type' => 'Stock In',
            'quantity_change' => 1,
            'previous_stock' => 8,
            'new_stock' => 9,
            'unit' => 'pc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $issuanceId = DB::table('inventory_issuances')->insertGetId([
            'issue_no' => 'ISS-CLEAN-0001',
            'issued_to' => 'Maintenance',
            'purpose' => 'Test issue',
            'reference_no' => 'PR-CLEAN-0001',
            'issued_by' => $userId,
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_issuance_items')->insert([
            'inventory_issuance_id' => $issuanceId,
            'inventory_item_id' => $inventoryItemId,
            'item_code' => 'INV-CLEAN-001',
            'item_name' => 'Preserved Belt',
            'quantity' => 1,
            'unit' => 'pc',
            'previous_stock' => 10,
            'new_stock' => 9,
            'stock_movement_id' => $movementId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $scheduledPurchaseId = DB::table('scheduled_purchases')->insertGetId([
            'schedule_no' => 'SP-CLEAN-0001',
            'schedule_name' => 'Test schedule',
            'supplier_name' => 'Test Supplier',
            'item' => 'Preserved Belt',
            'quantity' => 1,
            'unit' => 'pc',
            'frequency' => 'Monthly',
            'start_date' => now()->toDateString(),
            'next_purchase_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
            'last_po_id' => $purchaseOrderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('job_order_audits')->insert(['job_order_id' => $jobOrderId, 'user_id' => $userId, 'action' => 'created', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('topbar_notifications')->insert(['module' => 'Maintenance', 'entity' => 'JobOrder', 'action' => 'created', 'record_id' => (string) $jobOrderId, 'message' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('activity_logs')->insert(['user_id' => $userId, 'user_name' => 'Local Tester', 'activity' => 'Created test JO', 'module' => 'Maintenance', 'reference' => 'JO-CLEAN-0001', 'event_type' => 'created', 'created_at' => now(), 'updated_at' => now()]);

        return [
            'job_order_id' => $jobOrderId,
            'item_request_id' => $itemRequestId,
            'purchase_request_id' => $purchaseRequestId,
            'purchase_order_id' => $purchaseOrderId,
            'scheduled_purchase_id' => $scheduledPurchaseId,
        ];
    }
}
