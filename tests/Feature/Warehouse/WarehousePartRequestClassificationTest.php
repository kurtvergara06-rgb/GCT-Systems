<?php

namespace Tests\Feature\Warehouse;

use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class WarehousePartRequestClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pr_number_containing_dash_p_is_not_misclassified_as_missing_parts_copy(): void
    {
        $originalId = DB::table('purchase_requests')->insertGetId([
            'pr_no' => 'DEMO-PR-0001',
            'job_order_no' => 'DEMO-JO-0001',
            'bus_no' => 'DEMO-BUS-101',
            'item' => '[DEMO] Brake Pad Set',
            'quantity' => 2,
            'status' => 'Approved',
            'source_type' => 'Maintenance Request',
            'remarks' => 'DEMO / SYNTHETIC maintenance request.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $copyId = DB::table('purchase_requests')->insertGetId([
            'pr_no' => 'DEMO-PR-0001-P',
            'job_order_no' => 'DEMO-JO-0001',
            'bus_no' => 'DEMO-BUS-101',
            'item' => '[DEMO] Brake Pad Set',
            'quantity' => 1,
            'status' => 'For Purchase',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Missing parts from DEMO-PR-0001. Only unavailable parts were sent to Purchase Department.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = app(WarehousePartRequestController::class);

        $maintenanceMethod = new ReflectionMethod(
            WarehousePartRequestController::class,
            'warehouseMaintenanceRequestQuery'
        );
        $maintenanceMethod->setAccessible(true);
        $maintenanceQuery = $maintenanceMethod->invoke($controller);

        $this->assertTrue((clone $maintenanceQuery)->where('id', $originalId)->exists());
        $this->assertFalse((clone $maintenanceQuery)->where('id', $copyId)->exists());

        $missingMethod = new ReflectionMethod(
            WarehousePartRequestController::class,
            'missingPartsPurchaseQuery'
        );
        $missingMethod->setAccessible(true);
        $missingQuery = $missingMethod->invoke($controller);

        $this->assertFalse((clone $missingQuery)->where('id', $originalId)->exists());
        $this->assertTrue((clone $missingQuery)->where('id', $copyId)->exists());
    }
}
