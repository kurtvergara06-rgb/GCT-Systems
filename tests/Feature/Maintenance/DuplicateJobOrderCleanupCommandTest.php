<?php

namespace Tests\Feature\Maintenance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DuplicateJobOrderCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_is_a_dry_run_by_default(): void
    {
        $this->insertDuplicatePair();

        $this->artisan('maintenance:cleanup-duplicate-job-orders')
            ->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0002']);
    }

    public function test_execute_deletes_only_the_later_safe_exact_duplicate(): void
    {
        $this->insertDuplicatePair();

        $this->artisan('maintenance:cleanup-duplicate-job-orders', [
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0001']);
        $this->assertDatabaseMissing('job_orders', ['job_order_no' => 'JO-DUP-0002']);
    }

    public function test_linked_purchase_request_protects_duplicate_job_order(): void
    {
        $this->insertDuplicatePair();

        DB::table('purchase_requests')->insert([
            'pr_no' => 'PR-DUP-0001',
            'job_order_no' => 'JO-DUP-0002',
            'bus_no' => 'GCT-108',
            'item' => 'Brake Pad - Qty: 1 set',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Protected workflow record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('maintenance:cleanup-duplicate-job-orders', [
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0002']);
    }

    public function test_records_outside_duplicate_time_window_are_preserved(): void
    {
        $this->insertDuplicatePair(secondCreatedAt: now()->subHour());

        $this->artisan('maintenance:cleanup-duplicate-job-orders', [
            '--execute' => true,
            '--force' => true,
            '--window-minutes' => 10,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0002']);
    }

    public function test_different_operational_content_is_not_considered_duplicate(): void
    {
        $this->insertJobOrder(
            'JO-DUP-0001',
            now()->subMinutes(5)
        );

        $this->insertJobOrder(
            'JO-DUP-0002',
            now()->subMinutes(3),
            ['assigned_mechanic' => 'Different Mechanic']
        );

        $this->artisan('maintenance:cleanup-duplicate-job-orders', [
            '--execute' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0001']);
        $this->assertDatabaseHas('job_orders', ['job_order_no' => 'JO-DUP-0002']);
    }

    private function insertDuplicatePair($secondCreatedAt = null): void
    {
        $this->insertJobOrder(
            'JO-DUP-0001',
            now()->subMinutes(5)
        );

        $this->insertJobOrder(
            'JO-DUP-0002',
            $secondCreatedAt ?? now()->subMinutes(3)
        );
    }

    private function insertJobOrder(
        string $jobOrderNo,
        $createdAt,
        array $overrides = []
    ): void {
        $row = [
            'job_order_no' => $jobOrderNo,
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Brake noise during stopping.',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Sample Mechanic',
            'part_needed' => null,
            'start_date' => $createdAt,
            'completion_date' => null,
            'status' => 'On Hold',
            'part_status' => 'Not Requested',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        if (Schema::hasColumn('job_orders', 'work_to_perform')) {
            $row['work_to_perform'] = 'Inspect and repair brake assembly.';
        }

        if (Schema::hasColumn('job_orders', 'estimated_duration_value')) {
            $row['estimated_duration_value'] = 2;
        }

        if (Schema::hasColumn('job_orders', 'estimated_duration_unit')) {
            $row['estimated_duration_unit'] = 'Hours';
        }

        DB::table('job_orders')->insert(array_merge($row, $overrides));
    }
}
