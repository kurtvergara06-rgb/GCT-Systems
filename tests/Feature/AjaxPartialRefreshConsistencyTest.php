<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Operation\Driver;
use App\Models\Operation\Mechanic;
use App\Models\Operation\ShuttleRoute;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjaxPartialRefreshConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $purchaseUser;
    private User $operationUser;
    private User $maintenanceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $this->purchaseUser = User::factory()->create([
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $this->operationUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $this->maintenanceUser = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_admin_user_status_update_returns_json_on_ajax(): void
    {
        $targetUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson(route('admin.users.update-status', $targetUser), [
                'status' => 'Inactive',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSame('Inactive', $targetUser->fresh()->status);
    }

    public function test_admin_user_reset_password_returns_json_on_ajax(): void
    {
        $targetUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.users.reset-password', $targetUser), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_admin_user_destroy_returns_json_on_ajax(): void
    {
        $targetUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson(route('admin.users.destroy', $targetUser));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_driver_attendance_store_returns_json_on_ajax(): void
    {
        $driver = Driver::create([
            'driver_id' => 'DRV-AJAX-001',
            'driver_name' => 'Juan Dela Cruz',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $response = $this->actingAs($this->operationUser)
            ->postJson(route('driver-attendance.store'), [
                'driver_name' => $driver->driver_name,
                'shift' => 'Morning',
                'attendance_date' => now()->toDateString(),
                'status' => 'Present',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_mechanic_attendance_store_returns_json_on_ajax(): void
    {
        $mechanic = Mechanic::create([
            'mechanic_id' => 'MEC-AJAX-001',
            'mechanic_name' => 'Pedro Penduko',
            'shift' => 'Day Shift',
            'employment_status' => 'Active',
        ]);

        $response = $this->actingAs($this->operationUser)
            ->postJson(route('mechanic-attendance.store'), [
                'mechanic_name' => $mechanic->mechanic_name,
                'shift' => 'Day Shift',
                'attendance_date' => now()->toDateString(),
                'status' => 'Present',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_bus_master_list_destroy_returns_json_on_ajax(): void
    {
        $bus = Bus::create([
            'bus_no' => 'BUS-AJAX-999',
            'plate_no' => 'AJX-999',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->operationUser)
            ->deleteJson(route('bus-master-list.destroy', $bus));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertDatabaseMissing('buses', ['id' => $bus->id]);
    }

    public function test_routes_stops_destroy_returns_json_on_ajax(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'RTE-AJAX-01',
            'route_name' => 'City Terminal to Depot',
            'origin' => 'City Terminal',
            'destination' => 'Depot',
            'distance_km' => 15.5,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->operationUser)
            ->deleteJson(route('operation.routes.destroy', $route));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertDatabaseMissing('shuttle_routes', ['id' => $route->id]);
    }

    public function test_purchase_order_update_status_returns_json_on_ajax(): void
    {
        $po = PurchaseOrder::create([
            'po_no' => 'PO-AJAX-001',
            'po_date' => now()->toDateString(),
            'supplier_name' => 'Acme Parts Co.',
            'terms' => 'Net 30',
            'terms_of_payment' => 'Bank Transfer',
            'purpose' => 'Fleet Maintenance',
            'gross_amount' => 12500.00,
            'net_amount' => 12500.00,
            'status' => 'Ordered',
        ]);

        $response = $this->actingAs($this->purchaseUser)
            ->patchJson(route('purchase-orders.update-status', $po), [
                'status' => 'For Delivery',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSame('For Delivery', $po->fresh()->status);
    }

    public function test_scheduled_purchase_toggle_status_returns_json_on_ajax(): void
    {
        $scheduled = ScheduledPurchase::create([
            'schedule_no' => 'SCH-AJAX-001',
            'schedule_name' => 'Monthly Engine Oil Restock',
            'supplier_name' => 'Shell Philippines',
            'item' => 'Engine Oil 15W-40',
            'quantity' => 10,
            'unit' => 'drum',
            'frequency' => 'Monthly',
            'start_date' => now()->toDateString(),
            'next_purchase_date' => now()->addMonth()->toDateString(),
            'estimated_cost' => 50000.00,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->purchaseUser)
            ->patchJson(route('scheduled-purchase.toggle-status', $scheduled));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertSame('Paused', $scheduled->fresh()->status);
    }

    public function test_pms_schedule_store_returns_json_on_ajax(): void
    {
        Bus::create([
            'bus_no' => 'BUS-PMS-01',
            'plate_no' => 'PMS-001',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->maintenanceUser)
            ->postJson(route('pms-schedules.store'), [
                'bus_no' => 'BUS-PMS-01',
                'maintenance_type' => 'Brake Check',
                'last_pms_km' => 10000,
                'pms_interval_km' => 5000,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'PMS task created successfully.',
        ]);

        $this->assertDatabaseHas('pms_schedules', [
            'bus_no' => 'BUS-PMS-01',
            'maintenance_type' => 'Brake Check',
        ]);
    }

    public function test_fuel_report_destroy_returns_json_on_ajax(): void
    {
        $bus = Bus::create([
            'bus_no' => 'BUS-FUEL-01',
            'plate_no' => 'FL-001',
            'status' => 'Active',
        ]);

        $fuelReport = FuelReport::create([
            'report_date' => now()->toDateString(),
            'bus_no' => $bus->bus_no,
            'fuel_liters' => 50.00,
            'distance_km' => 200.00,
            'km_per_liter' => 4.00,
            'status' => 'Normal',
        ]);

        $response = $this->actingAs($this->maintenanceUser)
            ->deleteJson(route('fuel-reports.destroy', $fuelReport));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Fuel record deleted successfully.',
        ]);

        $this->assertDatabaseMissing('fuel_reports', ['id' => $fuelReport->id]);
    }
}
