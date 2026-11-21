<?php

namespace Tests\Feature\Admin;

use App\Models\Maintenance\Bus;
use App\Models\Warehouse\InventoryItem;
use App\Models\Operation\TripSchedule;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_registered_buses_is_not_reported_as_full_fleet_readiness(): void
    {
        $metrics = app(AdminDashboardService::class)->data()['fleetMetrics'];
        $this->assertSame(0, $metrics['total_buses']);
        $this->assertSame(0.0, $metrics['readiness_pct']);
    }

    public function test_fleet_status_counters_use_current_operation_and_maintenance_states(): void
    {
        Bus::query()->create(['bus_no' => 'GCT-ADM-101', 'plate_no' => 'ADM-101', 'status' => 'Active', 'capacity' => 40]);
        Bus::query()->create(['bus_no' => 'GCT-ADM-102', 'plate_no' => 'ADM-102', 'status' => 'Under Maintenance', 'capacity' => 40]);
        Bus::query()->create(['bus_no' => 'GCT-ADM-103', 'plate_no' => 'ADM-103', 'status' => 'Inactive', 'capacity' => 40]);

        $metrics = app(AdminDashboardService::class)->data()['fleetMetrics'];
        $this->assertSame(3, $metrics['total_buses']);
        $this->assertSame(1, $metrics['operational']);
        $this->assertSame(1, $metrics['maintenance']);
        $this->assertSame(33.3, $metrics['readiness_pct']);
    }
    public function test_inventory_alert_counter_uses_warehouse_manual_threshold_when_history_is_insufficient(): void
    {
        InventoryItem::query()->create([
            'item_code' => 'ADM-LOW-STOCK',
            'parts_name' => 'Dashboard Audit Filter',
            'item_name' => 'Dashboard Audit Filter',
            'category' => 'Filters',
            'on_hand' => 2,
            'reorder_level' => 5,
        ]);

        $warehouse = app(AdminDashboardService::class)->data()['metricsDetail']['warehouse'];
        $this->assertSame(1, $warehouse['total']);
        $this->assertSame(1, $warehouse['low_stock']);
        $this->assertSame(0, $warehouse['healthy']);
    }

}
