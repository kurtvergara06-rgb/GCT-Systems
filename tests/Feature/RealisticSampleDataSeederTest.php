<?php

namespace Tests\Feature;

use Database\Seeders\RealisticSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealisticSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_sample_seeder_populates_frontend_tables_without_destroying_existing_data(): void
    {
        DB::table('job_orders')->insert([
            'job_order_no' => 'REAL-JO-KEEP-0001',
            'bus_no' => 'REAL-BUS-01',
            'problem_issue' => 'Existing operational row that must survive simulated seeding.',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => null,
            'part_needed' => null,
            'start_date' => now(),
            'completion_date' => null,
            'status' => 'On Going',
            'part_status' => 'No Parts Needed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $realItemId = DB::table('inventory_items')->insertGetId([
            'item_code' => 'REAL-PART-001',
            'item_name' => 'Existing Genuine Part',
            'category' => 'Existing',
            'quantity_available' => 10,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
            'supplier' => 'Existing Supplier',
            'storage_location' => 'Rack X',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_movements')->insert([
            'inventory_item_id' => $realItemId,
            'item_code' => 'REAL-PART-001',
            'item_name' => 'Existing Genuine Part',
            'reference_no' => 'REAL-REF-001',
            'movement_type' => 'Stock Out',
            'quantity_change' => -1,
            'previous_stock' => 11,
            'new_stock' => 10,
            'unit' => 'pcs',
            'remarks' => 'Existing application-written movement.',
            'created_by' => null,
            'source' => 'app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(RealisticSampleDataSeeder::class);

        $this->assertDatabaseHas('job_orders', [
            'job_order_no' => 'REAL-JO-KEEP-0001',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_no' => 'REAL-REF-001',
            'source' => 'app',
        ]);

        $this->assertSame(
            460,
            DB::table('trip_schedules')->where('trip_code', 'like', 'TRIP-GCT-%')->count()
        );
        $this->assertSame(
            460,
            DB::table('daily_driver_reports')->where('ddr_no', 'like', 'DDR-GCT-%')->count()
        );
        $this->assertGreaterThanOrEqual(
            40,
            DB::table('incidents')->where('incident_no', 'like', 'INC-GCT-%')->count()
        );

        $this->assertSame(
            24,
            DB::table('inventory_items')->where('item_code', 'like', 'GCT-PART-%')->count()
        );
        $this->assertGreaterThanOrEqual(
            700,
            DB::table('stock_movements')
                ->where('source', 'simulated')
                ->count()
        );
        $this->assertSame(
            0,
            DB::table('stock_movements')
                ->where('reference_no', 'like', 'DEMO-%')
                ->count()
        );

        $this->assertSame(
            12,
            DB::table('job_orders')->where('job_order_no', 'like', 'JO-GCT-%')->count()
        );
        $this->assertSame(
            12,
            DB::table('purchase_requests')->where('pr_no', 'like', 'PR-GCT-%')->count()
        );
        $this->assertSame(
            12,
            DB::table('purchase_requests')
                ->where('pr_no', 'like', 'PR-GCT-%')
                ->where('source_type', 'Maintenance Request')
                ->count()
        );
        $this->assertSame(
            9,
            DB::table('purchase_orders')->where('po_no', 'like', 'PO-GCT-%')->count()
        );

        $this->assertSame(
            0,
            DB::table('trip_schedules')
                ->where('trip_code', 'like', 'TRIP-GCT-%')
                ->where('notes', 'like', '%DEMO%')
                ->count()
        );

        $this->assertSame(
            0,
            DB::table('inventory_items')
                ->where('item_code', 'like', 'GCT-PART-%')
                ->where(function ($query): void {
                    $query->where('item_name', 'like', '%DEMO%')
                        ->orWhere('supplier', 'like', '%DEMO%');
                })
                ->count()
        );
    }

    public function test_realistic_sample_seeder_is_idempotent_for_its_own_simulated_rows(): void
    {
        $this->seed(RealisticSampleDataSeeder::class);

        $firstCounts = $this->simulatedCounts();

        $this->seed(RealisticSampleDataSeeder::class);

        $this->assertSame($firstCounts, $this->simulatedCounts());
    }

    private function simulatedCounts(): array
    {
        return [
            'trips' => DB::table('trip_schedules')->where('trip_code', 'like', 'TRIP-GCT-%')->count(),
            'ddr' => DB::table('daily_driver_reports')->where('ddr_no', 'like', 'DDR-GCT-%')->count(),
            'incidents' => DB::table('incidents')->where('incident_no', 'like', 'INC-GCT-%')->count(),
            'parts' => DB::table('inventory_items')->where('item_code', 'like', 'GCT-PART-%')->count(),
            'movements' => DB::table('stock_movements')->where('source', 'simulated')->count(),
            'job_orders' => DB::table('job_orders')->where('job_order_no', 'like', 'JO-GCT-%')->count(),
            'purchase_requests' => DB::table('purchase_requests')->where('pr_no', 'like', 'PR-GCT-%')->count(),
            'purchase_orders' => DB::table('purchase_orders')->where('po_no', 'like', 'PO-GCT-%')->count(),
        ];
    }
}
