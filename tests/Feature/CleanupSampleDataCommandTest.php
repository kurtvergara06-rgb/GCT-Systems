<?php

namespace Tests\Feature;

use App\Services\SampleDataCleanupService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealisticSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupSampleDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_deletes_nothing(): void
    {
        $this->insertMaintenanceChain('GCT', 'GCT-201');

        $this->artisan('data:cleanup-samples')
            ->expectsOutputToContain('NO DATA DELETED')
            ->assertSuccessful();

        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-GCT-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-GCT-0001']);
        $this->assertDatabaseHas('purchase_orders', ['po_no' => 'PO-GCT-0001']);
    }

    public function test_execute_removes_known_sample_maintenance_chain(): void
    {
        $this->insertMaintenanceChain('GCT', 'GCT-201');

        $this->artisan('data:cleanup-samples', ['--execute' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('purchase_requests', ['pr_no' => 'PR-GCT-0001']);
        $this->assertDatabaseMissing('job_orders', ['job_order_no' => 'JO-GCT-0001']);
        $this->assertDatabaseMissing('purchase_orders', ['po_no' => 'PO-GCT-0001']);
    }

    public function test_execute_keeps_genuine_records(): void
    {
        $this->insertMaintenanceChain('REAL', 'BUS-REAL-01');

        $this->artisan('data:cleanup-samples', ['--execute' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-REAL-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-REAL-0001']);
        $this->assertDatabaseHas('purchase_orders', ['po_no' => 'PO-REAL-0001']);
    }

    public function test_sample_master_referenced_by_genuine_record_is_protected(): void
    {
        DB::table('buses')->insert($this->bus('GCT-201'));
        DB::table('job_orders')->insert($this->jobOrder('JO-REAL-0099', 'GCT-201'));

        $this->artisan('data:cleanup-samples', ['--execute' => true, '--force' => true])
            ->expectsOutputToContain('Protected buses: GCT-201')
            ->assertSuccessful();

        $this->assertDatabaseHas('buses', ['bus_no' => 'GCT-201']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-REAL-0099']);
    }

    public function test_orphan_report_detects_purchase_request_with_missing_job_order(): void
    {
        DB::table('purchase_requests')->insert($this->purchaseRequest('PR-GCT-ORPHAN', 'JO-GCT-MISSING', 'GCT-201'));

        $this->artisan('data:cleanup-samples', ['--orphans' => true])
            ->expectsOutputToContain('purchase_requests_missing_job_order')
            ->expectsOutputToContain('ORPHAN REPORT ONLY. NO DATA DELETED.')
            ->assertSuccessful();

        $this->assertContains(
            'PR-GCT-ORPHAN',
            app(SampleDataCleanupService::class)->orphans()['purchase_requests_missing_job_order']
        );
        $this->assertDatabaseHas('purchase_requests', ['pr_no' => 'PR-GCT-ORPHAN']);
    }

    public function test_execute_removes_simulated_movement_but_preserves_application_movement(): void
    {
        $itemId = DB::table('inventory_items')->insertGetId($this->inventoryItem('GCT-PART-001'));
        DB::table('stock_movements')->insert([
            $this->movement($itemId, 'SIM-001', 'simulated'),
            $this->movement($itemId, 'REAL-001', 'app'),
        ]);
        $issuedMovementId = DB::table('stock_movements')->insertGetId(
            $this->movement($itemId, 'SIM-ISSUED-001', 'simulated')
        );
        $issuanceId = DB::table('inventory_issuances')->insertGetId([
            'issue_no' => 'ISSUE-TEST-001',
            'issued_to' => 'Test Mechanic',
            'purpose' => 'Auditable issuance',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_issuance_items')->insert([
            'inventory_issuance_id' => $issuanceId,
            'inventory_item_id' => $itemId,
            'item_code' => 'GCT-PART-001',
            'item_name' => 'Test Part',
            'quantity' => 1,
            'unit' => 'pcs',
            'previous_stock' => 10,
            'new_stock' => 9,
            'stock_movement_id' => $issuedMovementId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('data:cleanup-samples', ['--execute' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('stock_movements', ['reference_no' => 'SIM-001']);
        $this->assertDatabaseHas('stock_movements', ['reference_no' => 'REAL-001', 'source' => 'app']);
        $this->assertDatabaseHas('stock_movements', ['reference_no' => 'SIM-ISSUED-001', 'source' => 'simulated']);
        $this->assertDatabaseHas('inventory_items', ['item_code' => 'GCT-PART-001']);
    }

    public function test_database_seeder_does_not_run_demo_seeder_when_opt_in_is_false(): void
    {
        config()->set('demo-data.enabled', false);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('buses', 0);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_execute_cleans_the_realistic_seeder_dataset_in_dependency_order(): void
    {
        $this->seed(RealisticSampleDataSeeder::class);

        $this->artisan('data:cleanup-samples', ['--execute' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('trip_schedules')->where('trip_code', 'like', 'TRIP-GCT-%')->count());
        $this->assertSame(0, DB::table('daily_driver_reports')->where('ddr_no', 'like', 'DDR-GCT-%')->count());
        $this->assertSame(0, DB::table('incidents')->where('incident_no', 'like', 'INC-GCT-%')->count());
        $this->assertSame(0, DB::table('purchase_orders')->where('po_no', 'like', 'PO-GCT-%')->count());
        $this->assertSame(0, DB::table('purchase_requests')->where('pr_no', 'like', 'PR-GCT-%')->count());
        $this->assertSame(0, DB::table('job_orders')->where('job_order_no', 'like', 'JO-GCT-%')->count());
        $this->assertSame(0, DB::table('stock_movements')->where('source', 'simulated')->count());
        $this->assertSame(0, DB::table('inventory_items')->where('item_code', 'like', 'GCT-PART-%')->count());
        $this->assertSame(0, DB::table('drivers')->where('driver_id', 'like', 'GCT-DRV-%')->count());
        $this->assertSame(0, DB::table('shuttle_routes')->where('route_code', 'like', 'GCT-RT-%')->count());
        $this->assertSame(0, DB::table('buses')->whereIn('bus_no', ['GCT-201', 'GCT-202', 'GCT-203', 'GCT-204', 'GCT-205', 'GCT-206', 'GCT-207', 'GCT-208'])->count());
    }

    private function insertMaintenanceChain(string $kind, string $busNo): void
    {
        $isSample = $kind === 'GCT';
        $joNo = $isSample ? 'JO-GCT-0001' : 'JO-REAL-0001';
        $prNo = $isSample ? 'PR-GCT-0001' : 'PR-REAL-0001';
        $poNo = $isSample ? 'PO-GCT-0001' : 'PO-REAL-0001';

        DB::table('job_orders')->insert($this->jobOrder($joNo, $busNo));
        $prId = DB::table('purchase_requests')->insertGetId($this->purchaseRequest($prNo, $joNo, $busNo));
        DB::table('purchase_orders')->insert($this->purchaseOrder($poNo, $prNo, $prId));
    }

    private function jobOrder(string $number, string $busNo): array
    {
        return [
            'job_order_no' => $number,
            'bus_no' => $busNo,
            'problem_issue' => 'Test issue',
            'maintenance_type' => 'Corrective',
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function purchaseRequest(string $number, string $jobOrder, string $busNo): array
    {
        return [
            'pr_no' => $number,
            'job_order_no' => $jobOrder,
            'bus_no' => $busNo,
            'item' => 'Brake Pad',
            'quantity' => 1,
            'status' => 'Submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function purchaseOrder(string $number, string $prNo, int $prId): array
    {
        return [
            'po_no' => $number,
            'po_date' => now()->toDateString(),
            'purchase_request_id' => $prId,
            'supplier_name' => 'Test Supplier',
            'items' => json_encode([['pr_no' => $prNo]]),
            'status' => 'Ordered',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function bus(string $number): array
    {
        return [
            'bus_no' => $number,
            'status' => 'Active',
            'last_pms_km' => 0,
            'pms_interval_km' => 5000,
            'next_pms_km' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function inventoryItem(string $code): array
    {
        return [
            'item_code' => $code,
            'item_name' => 'Test Part',
            'category' => 'Test',
            'quantity_available' => 10,
            'on_hand' => 10,
            'unit_of_measurement' => 'pcs',
            'unit' => 'pcs',
            'reorder_level' => 2,
            'source' => 'simulated',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function movement(int $itemId, string $reference, string $source): array
    {
        return [
            'inventory_item_id' => $itemId,
            'item_code' => 'GCT-PART-001',
            'item_name' => 'Test Part',
            'reference_no' => $reference,
            'movement_type' => 'Stock In',
            'quantity_change' => 10,
            'previous_stock' => 0,
            'new_stock' => 10,
            'unit' => 'pcs',
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
