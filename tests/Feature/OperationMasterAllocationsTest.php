<?php

namespace Tests\Feature;

use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\OperationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationMasterAllocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_bus_records_remain_unallocated_after_migration(): void
    {
        $bus = Bus::create(['bus_no' => 'LEGACY-01', 'status' => 'Active']);

        $this->assertTrue(Schema::hasTable('driver_authorized_buses'));
        $this->assertTrue(Schema::hasTable('bus_operation_clients'));
        $this->assertNull($bus->fresh()->client_allocation_type);
        $this->assertCount(0, $bus->operationClients);
    }

    public function test_driver_can_be_authorized_for_multiple_buses_and_bus_can_have_multiple_drivers(): void
    {
        $first = Bus::create(['bus_no' => 'DRV-BUS-1', 'status' => 'Active']);
        $second = Bus::create(['bus_no' => 'DRV-BUS-2', 'status' => 'Active']);
        $driverA = Driver::create(['driver_id' => 'DRV-A', 'driver_name' => 'Driver A', 'shift' => 'Morning', 'employment_status' => 'Active']);
        $driverB = Driver::create(['driver_id' => 'DRV-B', 'driver_name' => 'Driver B', 'shift' => 'Morning', 'employment_status' => 'Active']);

        $driverA->authorizedBuses()->sync([$first->id, $second->id]);
        $driverB->authorizedBuses()->sync([$first->id]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $driverA->fresh()->authorizedBuses->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$driverA->id, $driverB->id], $first->fresh()->authorizedDrivers->pluck('id')->all());
    }

    public function test_bus_allocations_support_single_and_multiple_clients(): void
    {
        $first = OperationClient::create(['name' => 'Client A']);
        $second = OperationClient::create(['name' => 'Client B']);
        $bus = Bus::create(['bus_no' => 'CLIENT-BUS-1', 'status' => 'Active', 'client_allocation_type' => 'Exclusive']);

        $bus->operationClients()->sync([$first->id]);
        $this->assertSame(['Client A'], $bus->fresh()->operationClients->pluck('name')->all());

        $bus->update(['client_allocation_type' => 'Shared']);
        $bus->operationClients()->sync([$first->id, $second->id]);
        $this->assertEqualsCanonicalizing(['Client A', 'Client B'], $bus->fresh()->operationClients->pluck('name')->all());
    }
}
