<?php

namespace Tests\Feature\Maintenance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetJobOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_filter_is_preview_only_by_default(): void
    {
        $this->insertJobOrder('JO-RESET-0001');

        $this->artisan('maintenance:reset-job-orders', [
            '--bus' => 'GCT-108',
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'JO-RESET-0001',
        ]);
    }

    public function test_execute_deletes_selected_job_order_and_safe_submitted_pr(): void
    {
        $this->insertJobOrder('JO-RESET-0001');

        $prId = DB::table('purchase_requests')->insertGetId([
            'pr_no' => 'PR-RESET-0001',
            'job_order_no' => 'JO-RESET-0001',
            'bus_no' => 'GCT-108',
            'item' => 'AC Refrigerant R134a - Qty: 1 can',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Resettable test PR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('maintenance:reset-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('job_orders', [
            'job_order_no' => 'JO-RESET-0001',
        ]);

        $this->assertDatabaseMissing('purchase_requests', [
            'id' => $prId,
        ]);
    }

    public function test_progressed_pr_blocks_job_order_reset(): void
    {
        $this->insertJobOrder('JO-RESET-0001');

        DB::table('purchase_requests')->insert([
            'pr_no' => 'PR-RESET-0001',
            'job_order_no' => 'JO-RESET-0001',
            'bus_no' => 'GCT-108',
            'item' => 'AC Refrigerant R134a - Qty: 1 can',
            'quantity' => 1,
            'status' => 'For Purchase',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Progressed workflow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('maintenance:reset-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'JO-RESET-0001',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'pr_no' => 'PR-RESET-0001',
        ]);
    }

    public function test_purchase_order_blocks_reset_even_when_pr_status_is_submitted(): void
    {
        $this->insertJobOrder('JO-RESET-0001');

        $prId = DB::table('purchase_requests')->insertGetId([
            'pr_no' => 'PR-RESET-0001',
            'job_order_no' => 'JO-RESET-0001',
            'bus_no' => 'GCT-108',
            'item' => 'AC Refrigerant R134a - Qty: 1 can',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Should be protected by PO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_orders')->insert([
            'po_no' => 'PO-RESET-0001',
            'po_date' => now()->toDateString(),
            'purchase_request_id' => $prId,
            'supplier_name' => 'Reset Test Supplier',
            'supplier_address_tel' => null,
            'terms' => null,
            'terms_of_payment' => null,
            'purpose' => 'Reset safety test',
            'items' => json_encode([]),
            'gross_amount' => 0,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'net_amount' => 0,
            'status' => 'Ordered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('maintenance:reset-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'JO-RESET-0001',
        ]);
    }

    public function test_completed_job_order_is_always_protected(): void
    {
        $this->insertJobOrder('JO-RESET-0001', [
            'status' => 'Completed',
            'completion_date' => now(),
        ]);

        $this->artisan('maintenance:reset-job-orders', [
            '--job-order' => ['JO-RESET-0001'],
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'JO-RESET-0001',
        ]);
    }

    private function insertJobOrder(string $jobOrderNo, array $overrides = []): void
    {
        DB::table('job_orders')->insert(array_merge([
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Aircon servicing test record.',
            'maintenance_type' => 'Aircon Servicing',
            'assigned_mechanic' => null,
            'part_needed' => 'AC Refrigerant R134a - Qty: 1 can',
            'start_date' => now(),
            'completion_date' => null,
            'status' => 'On Hold',
            'part_status' => 'Submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
