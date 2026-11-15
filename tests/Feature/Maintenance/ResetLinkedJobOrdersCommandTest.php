<?php

namespace Tests\Feature\Maintenance;

use App\Models\Maintenance\JobOrder;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ResetLinkedJobOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_deletes_nothing(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001');

        $this->artisan('maintenance:reset-linked-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
        ])->expectsOutputToContain('DRY RUN ONLY')->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-RESET-0001']);
        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-RESET-0001']);
    }

    public function test_execute_targets_only_exact_job_order_and_removes_submitted_pr(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $this->insertJobOrder('JO-KEEP-0001');
        $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001');
        $this->insertPurchaseRequest('PR-KEEP-0001', 'JO-KEEP-0001');

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseMissing('job_orders', ['job_order_no' => 'JO-RESET-0001']);
        $this->assertDatabaseMissing('purchase_requests', ['pr_no' => 'PR-RESET-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-KEEP-0001']);
        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-KEEP-0001']);
    }

    public function test_rejected_pr_can_be_removed_with_selected_job_order(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'Rejected');

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseMissing('purchase_requests', ['pr_no' => 'PR-RESET-0001']);
        $this->assertDatabaseMissing('job_orders', ['job_order_no' => 'JO-RESET-0001']);
    }

    public function test_shared_purchase_order_chain_is_detected_and_blocked(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $targetPr = $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'For Purchase');
        $otherPr = $this->insertPurchaseRequest('PR-OTHER-0001', 'JO-OTHER-0001', 'For Purchase');
        $this->insertPurchaseOrder('PO-SHARED-0001', $otherPr, [
            $this->poItem('PR-RESET-0001', 'Brake Pad', 2),
            $this->poItem('PR-OTHER-0001', 'Oil Filter', 1),
        ]);

        $this->artisan('maintenance:reset-linked-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
        ])->expectsOutputToContain('BLOCK')->assertSuccessful();

        $this->execute('JO-RESET-0001')->assertSuccessful();
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-RESET-0001']);
        $this->assertDatabaseHas('purchase_orders', ['po_no' => 'PO-SHARED-0001']);
        $this->assertDatabaseHas('purchase_requests', ['id' => $targetPr]);
    }

    public function test_inventory_reversal_is_recorded_and_stock_is_restored(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $prId = $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'Picked Up');
        $this->insertPurchaseOrder('PO-RESET-0001', $prId, [
            $this->poItem('PR-RESET-0001', 'Brake Pad', 5),
        ], ['status' => 'Picked Up', 'inventory_posted_at' => now()]);
        $item = $this->insertInventoryItem('PART-BRAKE', 'Brake Pad', 10);
        $movementId = $this->insertMovement($item->id, 'PO-RESET-0001', 5, 5, 10, 'Brake Pad');

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertSame(5, (int) $item->fresh()->quantity_available);
        $this->assertDatabaseHas('stock_movements', ['id' => $movementId, 'quantity_change' => 5]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_no' => 'RESET-JO-RESET-0001',
            'quantity_change' => -5,
            'previous_stock' => 10,
            'new_stock' => 5,
        ]);
        $this->assertDatabaseMissing('purchase_orders', ['po_no' => 'PO-RESET-0001']);
    }

    public function test_inventory_posting_without_provable_ledger_blocks_reset(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $prId = $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'Delivered');
        $this->insertPurchaseOrder('PO-RESET-0001', $prId, [
            $this->poItem('PR-RESET-0001', 'Brake Pad', 5),
        ], ['status' => 'Delivered', 'inventory_posted_at' => now()]);

        $this->artisan('maintenance:reset-linked-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
        ])->expectsOutputToContain('inventory-posted but has no matching Stock In')->assertSuccessful();

        $this->execute('JO-RESET-0001')->assertSuccessful();
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-RESET-0001']);
    }

    public function test_inventory_reversal_that_would_make_stock_negative_blocks_reset(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $prId = $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'Delivered');
        $this->insertPurchaseOrder('PO-RESET-0001', $prId, [
            $this->poItem('PR-RESET-0001', 'Brake Pad', 5),
        ], ['status' => 'Delivered', 'inventory_posted_at' => now()]);
        $item = $this->insertInventoryItem('PART-BRAKE', 'Brake Pad', 2);
        $this->insertMovement($item->id, 'PO-RESET-0001', 5, 0, 5, 'Brake Pad');

        $this->artisan('maintenance:reset-linked-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
        ])->expectsOutputToContain('current stock 2 is below 5')->assertSuccessful();

        $this->execute('JO-RESET-0001')->assertSuccessful();
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-RESET-0001']);
        $this->assertSame(2, (int) $item->fresh()->quantity_available);
    }

    public function test_pms_schedule_is_preserved_for_reuse(): void
    {
        $pmsId = DB::table('pms_schedules')->insertGetId([
            'bus_no' => 'GCT-108',
            'last_pms_km' => 1000,
            'pms_interval_km' => 5000,
            'next_pms_km' => 6000,
            'maintenance_type' => 'Aircon Servicing',
            'recommended_date' => today(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertJobOrder('JO-RESET-0001', ['pms_schedule_id' => $pmsId]);

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseHas('pms_schedules', ['id' => $pmsId]);
        $this->assertDatabaseMissing('job_orders', ['job_order_no' => 'JO-RESET-0001']);
    }

    public function test_mechanic_remains_on_duty_when_another_active_job_exists(): void
    {
        $this->insertMechanicAttendance('MECH-001', 'Alex Mechanic', 'On Duty');
        $this->insertJobOrder('JO-RESET-0001', ['assigned_mechanic' => 'Alex Mechanic']);
        $this->insertJobOrder('JO-KEEP-0001', ['assigned_mechanic' => 'Alex Mechanic']);

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseHas('mechanic_attendances', [
            'mechanic_name' => 'Alex Mechanic',
            'status' => 'On Duty',
        ]);
    }

    public function test_mechanic_becomes_present_when_no_other_active_job_exists(): void
    {
        $this->insertMechanicAttendance('MECH-001', 'Alex Mechanic', 'On Duty', 'JO-RESET-0001');
        $this->insertJobOrder('JO-RESET-0001', ['assigned_mechanic' => 'Alex Mechanic']);

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseHas('mechanic_attendances', [
            'mechanic_name' => 'Alex Mechanic',
            'status' => 'Present',
            'assigned_job' => null,
        ]);
    }

    public function test_completed_unrelated_job_order_is_untouched(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $this->insertJobOrder('JO-HISTORY-0001', [
            'status' => 'Completed',
            'completion_date' => now(),
        ]);

        $this->execute('JO-RESET-0001')->assertSuccessful();

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'JO-HISTORY-0001',
            'status' => 'Completed',
        ]);
    }

    public function test_each_job_order_transaction_rolls_back_when_delete_fails(): void
    {
        $this->insertJobOrder('JO-RESET-0001');
        $prId = $this->insertPurchaseRequest('PR-RESET-0001', 'JO-RESET-0001', 'Delivered');
        $this->insertPurchaseOrder('PO-RESET-0001', $prId, [
            $this->poItem('PR-RESET-0001', 'Brake Pad', 5),
        ], ['status' => 'Delivered', 'inventory_posted_at' => now()]);
        $item = $this->insertInventoryItem('PART-BRAKE', 'Brake Pad', 10);
        $this->insertMovement($item->id, 'PO-RESET-0001', 5, 5, 10, 'Brake Pad');

        $event = 'eloquent.deleting: '.JobOrder::class;
        Event::listen($event, function (JobOrder $jobOrder): void {
            if ($jobOrder->job_order_no === 'JO-RESET-0001') {
                throw new RuntimeException('Simulated reset failure');
            }
        });

        try {
            $this->execute('JO-RESET-0001')->expectsOutputToContain('rolled back')->assertFailed();
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-RESET-0001']);
        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-RESET-0001']);
        $this->assertDatabaseHas('purchase_orders', ['po_no' => 'PO-RESET-0001']);
        $this->assertSame(10, (int) $item->fresh()->quantity_available);
        $this->assertSame(1, StockMovement::count());
    }

    private function execute(string $jobOrderNo)
    {
        return $this->artisan('maintenance:reset-linked-job-orders', [
            '--job-order' => [$jobOrderNo],
            '--execute' => true,
            '--force' => true,
        ]);
    }

    private function insertJobOrder(string $jobOrderNo, array $overrides = []): int
    {
        return DB::table('job_orders')->insertGetId(array_merge([
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'pms_schedule_id' => null,
            'maintenance_referral_id' => null,
            'incident_id' => null,
            'problem_issue' => 'Reset command test',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => null,
            'part_needed' => null,
            'start_date' => now(),
            'completion_date' => null,
            'status' => 'On Hold',
            'part_status' => 'No Parts Required',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertPurchaseRequest(string $prNo, string $jobOrderNo, string $status = 'Submitted'): int
    {
        return DB::table('purchase_requests')->insertGetId([
            'pr_no' => $prNo,
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'item' => 'Brake Pad - Qty: 5 pcs',
            'quantity' => 5,
            'status' => $status,
            'source_type' => 'Maintenance Request',
            'remarks' => 'Reset command test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPurchaseOrder(string $poNo, int $prId, array $items, array $overrides = []): int
    {
        return DB::table('purchase_orders')->insertGetId(array_merge([
            'po_no' => $poNo,
            'po_date' => today(),
            'purchase_request_id' => $prId,
            'supplier_name' => 'Test Supplier',
            'items' => json_encode($items),
            'gross_amount' => 500,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 500,
            'status' => 'Ordered',
            'inventory_posted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function poItem(string $prNo, string $name, int $quantity): array
    {
        return [
            'pr_no' => $prNo,
            'item_description' => $name,
            'quantity' => $quantity,
            'unit' => 'pcs',
            'cost' => 100,
        ];
    }

    private function insertInventoryItem(string $code, string $name, int $stock): InventoryItem
    {
        return InventoryItem::create([
            'item_code' => $code,
            'item_name' => $name,
            'category' => 'Auto Parts',
            'on_hand' => $stock,
            'quantity_available' => $stock,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
            'status' => 'In Stock',
            'source' => 'app',
        ]);
    }

    private function insertMovement(int $itemId, string $reference, int $change, int $previous, int $new, string $name): int
    {
        return DB::table('stock_movements')->insertGetId([
            'inventory_item_id' => $itemId,
            'item_code' => 'PART-BRAKE',
            'item_name' => $name,
            'reference_no' => $reference,
            'movement_type' => $change > 0 ? 'Stock In' : 'Stock Out',
            'quantity_change' => $change,
            'previous_stock' => $previous,
            'new_stock' => $new,
            'unit' => 'pcs',
            'remarks' => 'Reset command test movement',
            'source' => 'app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMechanicAttendance(string $mechanicId, string $name, string $status, ?string $assignedJob = null): void
    {
        DB::table('mechanics')->insert([
            'mechanic_id' => $mechanicId,
            'mechanic_name' => $name,
            'shift' => 'Morning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('mechanic_attendances')->insert([
            'mechanic_id' => $mechanicId,
            'mechanic_name' => $name,
            'shift' => 'Morning',
            'assigned_job' => $assignedJob,
            'attendance_date' => today(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
