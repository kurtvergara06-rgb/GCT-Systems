<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrescriptiveAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/analytics/fleet-trip/predict' => Http::response(['success' => false], 503),
            '*/eta/predict' => Http::response(['success' => false], 503),
            '*' => Http::response([], 200),
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('analytics.stage', ['stage' => 'prescriptive']));

        $response->assertRedirect(route('login'));
    }

    public function test_operations_prescriptive_view_renders_demo_simulation_when_no_records(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'prescriptive',
            'domain' => 'fleet-trip',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.prescriptive.layout')
            ->assertViewHas('activePrescriptiveView', 'operations')
            ->assertViewHas('operationsPrescriptive')
            ->assertSeeText('Prescriptive Analytics')
            ->assertSeeText('Operations Scheduling')
            ->assertSeeText('Demo Data')
            ->assertSeeText('Trips Needing Assignment')
            ->assertSeeText('Active Driver Candidates')
            ->assertSeeText('Active Bus Candidates')
            ->assertSeeText('Scheduling Conflicts')
            ->assertSeeText('Recommended Departure & Trip Schedules')
            ->assertSeeText('Thesis Objective 6.4 Decision Support Mandate');

        $opsData = $response->viewData('operationsPrescriptive');
        $this->assertTrue($opsData->is_demo);
        $this->assertGreaterThan(0, $opsData->trips_needing_assignment);
    }

    public function test_operations_prescriptive_view_renders_live_records(): void
    {
        $admin = $this->createAdmin();

        $bus = Bus::create([
            'bus_no' => 'GCT-LIVE-101',
            'plate_no' => 'LIVE-101',
            'status' => 'Active',
            'capacity' => 50,
        ]);

        $driver = Driver::create([
            'driver_id' => 'DRV-LIVE-01',
            'driver_name' => 'Live Test Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-LIVE-01',
            'route_name' => 'Live Terminal Route',
            'origin' => 'Depot A',
            'destination' => 'Depot B',
            'status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'T-LIVE-2026',
            'shuttle_route_id' => $route->id,
            'trip_date' => now()->toDateString(),
            'departure_time' => '07:30:00',
            'estimated_arrival_time' => '08:15:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'client_name' => 'Live Client',
            'direction' => 'Northbound',
            'required_buses' => 1,
            'route_name_snapshot' => 'Live Terminal Route',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'prescriptive',
            'domain' => 'fleet-trip',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.prescriptive.layout')
            ->assertViewHas('activePrescriptiveView', 'operations')
            ->assertSeeText('Live Database Records')
            ->assertSeeText('T-LIVE-2026');

        $opsData = $response->viewData('operationsPrescriptive');
        $this->assertFalse($opsData->is_demo);
        $this->assertGreaterThanOrEqual(1, $opsData->trips_needing_assignment);
        $this->assertSame('Requires review', $opsData->driver_recommendations->first()->eligibility);
        $this->assertSame('Needs review', $opsData->schedule_recommendations->first()->headway_gap);
        $response->assertDontSeeText('15m+ verified');
    }

    public function test_restocking_prescriptive_view_renders_demo_simulation_when_no_records(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'prescriptive',
            'domain' => 'inventory',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.prescriptive.layout')
            ->assertViewHas('activePrescriptiveView', 'restocking')
            ->assertViewHas('restockingPrescriptive')
            ->assertSeeText('Prescriptive Analytics')
            ->assertSeeText('Inventory Restocking')
            ->assertSeeText('Demo Data')
            ->assertSeeText('Inventory Items Monitored')
            ->assertSeeText('Low Stock Attention')
            ->assertSeeText('Critical / Out of Stock')
            ->assertSeeText('Threshold Gap Units')
            ->assertSeeText('Spare-Parts Replenishment Recommendations')
            ->assertSeeText('Strategic Restocking Playbooks');

        $restockData = $response->viewData('restockingPrescriptive');
        $this->assertTrue($restockData->is_demo);
        $this->assertGreaterThan(0, $restockData->recommended_restock_qty);
    }

    public function test_restocking_prescriptive_view_renders_live_records(): void
    {
        $admin = $this->createAdmin();

        $item = InventoryItem::create([
            'item_code' => 'LIVE-PART-01',
            'parts_name' => 'Live Test Filter',
            'item_name' => 'Live Test Filter',
            'category' => 'Filters',
            'on_hand' => 1,
            'reorder_level' => 10,
            'safety_stock' => 5,
            'unit_of_measurement' => 'pcs',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'prescriptive',
            'domain' => 'inventory',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.prescriptive.layout')
            ->assertViewHas('activePrescriptiveView', 'restocking')
            ->assertSeeText('Live Database Records')
            ->assertSeeText('LIVE-PART-01');

        $restockData = $response->viewData('restockingPrescriptive');
        $this->assertFalse($restockData->is_demo);
        $this->assertSame(9, $restockData->recommended_restock_qty);
        $this->assertNull($restockData->restock_items->first()->monthly_demand);
        $this->assertSame(0, $restockData->restock_items->first()->incoming_supply);
        $response->assertSeeText('Not measured')
            ->assertSeeText('Not verified')
            ->assertDontSeeText('Urgent Brake System Restock')
            ->assertDontSeeText('PO-2026-0004');
    }

    public function test_healthy_recorded_inventory_shows_live_empty_state_instead_of_demo_recommendations(): void
    {
        InventoryItem::create([
            'item_code' => 'HEALTHY-PART-01',
            'parts_name' => 'Healthy Test Filter',
            'item_name' => 'Healthy Test Filter',
            'category' => 'Filters',
            'on_hand' => 20,
            'reorder_level' => 10,
            'safety_stock' => 5,
            'unit_of_measurement' => 'pcs',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->createAdmin())->get(route('analytics.stage', [
            'stage' => 'prescriptive',
            'domain' => 'inventory',
        ]));

        $response->assertOk()
            ->assertSeeText('Live Database Records')
            ->assertSeeText('No restocking threshold gaps')
            ->assertDontSeeText('Urgent Brake System Restock');
        $this->assertFalse($response->viewData('restockingPrescriptive')->is_demo);
    }
}
