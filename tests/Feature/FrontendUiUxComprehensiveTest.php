<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontendUiUxComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operationUser;
    protected User $maintenanceUser;
    protected User $warehouseUser;
    protected User $purchaseUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->operationUser = User::factory()->create([
            'name' => 'Operation Manager',
            'email' => 'operation@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->maintenanceUser = User::factory()->create([
            'name' => 'Maintenance Manager',
            'email' => 'maintenance@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->warehouseUser = User::factory()->create([
            'name' => 'Warehouse Supervisor',
            'email' => 'warehouse@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->purchaseUser = User::factory()->create([
            'name' => 'Purchase Officer',
            'email' => 'purchase@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        // Seed baseline records so pages render with real content
        $route = ShuttleRoute::create([
            'route_name' => 'Cebu IT Park - Talisay',
            'route_code' => 'RT-001',
            'origin' => 'Cebu IT Park',
            'origin_latitude' => 10.328,
            'origin_longitude' => 123.906,
            'destination' => 'Talisay Terminal',
            'destination_latitude' => 10.250,
            'destination_longitude' => 123.850,
            'distance_km' => 15.5,
            'estimated_time_minutes' => 45,
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-101',
            'plate_no' => 'ABC-1234',
            'bus_model' => 'Hino Grand Echo',
            'capacity' => 49,
            'status' => 'Active',
            'latest_gps_km' => 15000,
        ]);

        $driver = Driver::create([
            'driver_id' => 'DRV-101',
            'driver_name' => 'Juan Dela Cruz',
            'contact_number' => '09123456789',
            'license_number' => 'D01-98-123456',
            'license_expiry' => now()->addYears(2)->toDateString(),
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $mechanic = Mechanic::create([
            'mechanic_id' => 'MEC-101',
            'mechanic_name' => 'Pedro Penduko',
            'contact_number' => '09187654321',
            'skills' => 'Engine, Brakes',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'TRIP-1001',
            'shuttle_route_id' => $route->id,
            'trip_date' => now()->toDateString(),
            'shift' => 'Morning',
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'status' => 'Scheduled',
        ]);

        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        MechanicAttendance::create([
            'mechanic_name' => $mechanic->mechanic_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Highway 1',
            'description' => 'Minor engine overheating on route.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $this->operationUser->id,
        ]);

        $referral = MaintenanceReferral::create([
            'incident_id' => $incident->id,
            'bus_id' => $bus->id,
            'reason' => 'Radiator inspection required',
            'status' => 'Pending',
            'referred_by' => $this->operationUser->id,
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-001',
            'bus_no' => $bus->bus_no,
            'maintenance_referral_id' => $referral->id,
            'problem_issue' => 'Radiator inspection and repair',
            'status' => 'On Going',
            'maintenance_type' => 'Repair',
        ]);

        PmsSchedule::create([
            'bus_no' => $bus->bus_no,
            'pms_type' => 'PMS 10K',
            'target_date' => now()->addDays(5)->toDateString(),
            'latest_mileage' => 15000,
            'notes' => 'Routine 10k PMS inspection',
        ]);

        FuelReport::create([
            'report_date' => now()->toDateString(),
            'bus_no' => $bus->bus_no,
            'driver_name' => $driver->driver_name,
            'distance_km' => 120.0,
            'fuel_liters' => 35.5,
            'km_per_liter' => 3.38,
            'remarks' => 'Normal daily trip consumption',
        ]);

        $invItem = InventoryItem::create([
            'item_code' => 'FLT-001',
            'item_name' => 'Oil Filter Heavy Duty',
            'category' => 'Filters',
            'on_hand' => 25,
            'quantity_available' => 25,
            'reorder_level' => 10,
            'unit_of_measurement' => 'pcs',
            'storage_location' => 'Bay 1',
        ]);

        StockMovement::create([
            'inventory_item_id' => $invItem->id,
            'item_code' => $invItem->item_code,
            'item_name' => $invItem->item_name,
            'movement_type' => 'Stock In',
            'quantity_change' => 25,
            'previous_stock' => 0,
            'new_stock' => 25,
            'source' => 'app',
            'reference_no' => 'REC-001',
        ]);

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-001',
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $bus->bus_no,
            'status' => 'For Purchase',
            'item' => 'Oil Filter Heavy Duty',
            'quantity' => 2,
        ]);

        PurchaseOrder::create([
            'po_no' => 'PO-001',
            'po_date' => now()->toDateString(),
            'purchase_request_id' => $pr->id,
            'supplier_name' => 'ABC Auto Parts',
            'terms' => 'Net 30',
            'purpose' => 'Maintenance replenishment',
            'items' => [['item' => 'Oil Filter', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000]],
            'gross_amount' => 1000.0,
            'net_amount' => 1000.0,
            'status' => 'Ordered',
        ]);

        ScheduledPurchase::create([
            'schedule_no' => 'SCH-001',
            'schedule_name' => 'Monthly Lubricant Bulk Order',
            'supplier_name' => 'Petron',
            'item' => 'Engine Oil 15W-40',
            'quantity' => 10,
            'unit' => 'drums',
            'frequency' => 'Monthly',
            'start_date' => now()->toDateString(),
            'next_purchase_date' => now()->addDays(10)->toDateString(),
            'estimated_cost' => 15000.0,
            'status' => 'Active',
        ]);
    }

    // =========================================================================
    // 1. GLOBAL LAYOUT TEST
    // =========================================================================
    public function test_global_layout_components(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        // 1.1 Viewport meta tag
        $response->assertSee('name="viewport"', false);
        $response->assertSee('content="width=device-width, initial-scale=1.0"', false);

        // 1.2 Layout containers
        $response->assertSee('sidebar', false);
        $response->assertSee('topbar', false);

        // 1.3 Notifications bell & User profile menu
        $response->assertSee('topbar-action-item', false);
        $response->assertSee('fa-bell', false);
        $response->assertSee('System Administrator', false);

        // 1.4 Logout button / form with confirmation modal
        $response->assertSee(route('logout', [], false), false);
        $response->assertSee('profile-logout-form', false);
        $response->assertSee('data-confirm-form', false);
        $response->assertSee('data-confirm-title="Log Out"', false);
        $response->assertSee('data-confirm-type="logout"', false);

        // 1.5 Shared external assets. Production asset compilation is verified
        // independently by the dedicated Frontend build CI job (`npm run build`).
        $response->assertSee('fonts.googleapis.com', false);
        $response->assertSee('font-awesome', false);
    }

    // =========================================================================
    // 2. ADMIN MODULE PAGES
    // =========================================================================
    public function test_admin_module_every_page(): void
    {
        $pages = [
            'Dashboard' => route('admin.dashboard'),
            'Accounts' => route('admin.users'),
            'Roles & Permissions' => route('admin.roles-permissions'),
            'Activity Logs' => route('admin.activity-logs'),
            'Notifications' => route('admin.notifications'),
            'Data Import Management' => route('admin.batch-file-processing'),
            'Import / Export' => route('admin.import-export'),
            'Data History' => route('admin.data-history'),
            'Analytics Overview' => route('analytics.overview'),
            'Analytics Descriptive' => route('analytics.descriptive'),
            'Analytics Diagnostic' => route('analytics.stage', ['stage' => 'diagnostic']),
            'Analytics Predictive' => route('analytics.stage', ['stage' => 'predictive']),
            'Analytics Prescriptive' => route('analytics.stage', ['stage' => 'prescriptive']),
            'General Settings' => route('admin.settings.general'),
            'Notification Settings' => route('admin.settings.notifications'),
            'Security Settings' => route('admin.settings.security'),
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->adminUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Admin page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }

    public function test_admin_sidebar_label_data_import_management(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $content = $response->getContent();

        $this->assertTrue(str_contains($content, 'Data Import Management'), 'Sidebar should contain the "Data Import Management" menu entry');
    }

    public function test_all_analytics_pages_have_insight_toast_and_view_details(): void
    {
        $analyticsPages = [
            'Overview' => route('analytics.overview'),
            'Descriptive' => route('analytics.descriptive'),
            'Diagnostic' => route('analytics.stage', ['stage' => 'diagnostic']),
            'Predictive' => route('analytics.stage', ['stage' => 'predictive']),
            'Prescriptive' => route('analytics.stage', ['stage' => 'prescriptive']),
        ];

        foreach ($analyticsPages as $name => $url) {
            $resp = $this->actingAs($this->adminUser)->get($url);
            $this->assertSame(200, $resp->status(), "Analytics page {$name} must return 200 OK");

            $content = $resp->getContent();
            $this->assertStringContainsString('data-analytics-insight-toast', $content, "Analytics page {$name} must include the insight toast component");
            $this->assertStringContainsString('data-toast-action', $content, "Analytics page {$name} must include the View Details/Action button");
            $this->assertStringContainsString('data-target-selector', $content, "Analytics page {$name} must configure target selector for highlighting");
            $this->assertStringContainsString('window.gctHighlightTarget', $content, "Analytics page {$name} must include the reusable gctHighlightTarget script");
        }
    }

    public function test_topbar_has_no_horizontal_divider_across_modules(): void
    {
        // 1. Verify CSS files enforce no topbar bottom border or pseudo divider
        $mainCss = file_get_contents(resource_path('css/Main-styles/main.css'));
        $designCss = file_get_contents(resource_path('css/Admin/Analytics/design-system.css'));
        $themeCss = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $enhancementsCss = file_get_contents(resource_path('css/Main-styles/shared-ui-enhancements.css'));

        $this->assertStringContainsString('border-bottom: none !important', $mainCss);
        $this->assertStringContainsString('border-bottom: none !important', $designCss);
        $this->assertStringContainsString('border-bottom: none !important', $themeCss);
        $this->assertStringContainsString('border-bottom: none !important', $enhancementsCss);

        // 2. Verify all five departments render topbar without <hr> or hardcoded divider markup
        $modulePages = [
            'Admin' => [$this->adminUser, route('admin.dashboard')],
            'Operation' => [$this->operationUser, route('dashboard-operation')],
            'Maintenance' => [$this->maintenanceUser, route('maintenance-dashboard')],
            'Warehouse' => [$this->warehouseUser, route('warehouse.dashboard')],
            'Purchase' => [$this->purchaseUser, route('dashboard-purchase')],
        ];

        foreach ($modulePages as $department => [$user, $url]) {
            $resp = $this->actingAs($user)->get($url);
            $this->assertSame(200, $resp->status(), "{$department} dashboard failed status check");
            $content = $resp->getContent();
            $this->assertStringContainsString('class="topbar"', $content, "{$department} page must have .topbar header");
            $this->assertStringNotContainsString('<header class="topbar"><hr', $content);
        }
    }

    // =========================================================================
    // 3. OPERATION MODULE PAGES
    // =========================================================================
    public function test_operation_module_every_page(): void
    {
        $pages = [
            'Dashboard' => route('dashboard-operation'),
            'Routes' => route('operation.routes'),
            'Trip Schedule' => route('trip-schedule'),
            'Driver & Bus Assignment' => route('driver-bus-assignment'),
            'Auto Scheduling' => route('auto-scheduling'),
            'Driver Master List' => route('operation.personnel.drivers'),
            'Mechanic Master List' => route('operation.personnel.mechanics'),
            'Driver Attendance' => route('driver-attendance'),
            'Mechanic Attendance' => route('mechanic-attendance'),
            'Bus Master List' => route('bus-master-list'),
            'Trip Records' => route('trip-records'),
            'Daily Drivers Report' => route('daily-driver-reports'),
            'Incidents' => route('incidents'),
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->operationUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Operation page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }

    public function test_operation_route_validation_and_restoration(): void
    {
        $response = $this->actingAs($this->operationUser)->from(route('operation.routes'))->post(route('operation.routes.store'), [
            'route_name' => 'Cebu IT Park - Talisay',
            'origin' => 'Origin Point A',
            'origin_latitude' => 10.3,
            'origin_longitude' => 123.9,
            'destination' => 'Destination Point B',
            'destination_latitude' => 10.2,
            'destination_longitude' => 123.8,
            'distance_km' => 10.0,
            'estimated_time_minutes' => 30,
        ]);

        $response->assertRedirect(route('operation.routes'));
        $response->assertSessionHasErrors(['route_name']);

        $pageResp = $this->actingAs($this->operationUser)->get(route('operation.routes'));
        $pageResp->assertOk();
    }

    public function test_operation_trip_schedule_filters_and_buttons(): void
    {
        $resp = $this->actingAs($this->operationUser)->get(route('trip-schedule'));
        $resp->assertOk();

        $resp->assertSee('name="search"', false);
        $resp->assertSee('name="trip_date"', false);
        $resp->assertSee('name="status"', false);
        $resp->assertSee('Generate Daily Trips', false);
        $resp->assertSee('New Trip', false);
    }

    // =========================================================================
    // 4. MAINTENANCE MODULE PAGES
    // =========================================================================
    public function test_maintenance_module_every_page(): void
    {
        $pages = [
            'Dashboard' => route('maintenance-dashboard'),
            'Maintenance Referrals' => route('maintenance-referrals'),
            'Job Orders' => route('job-orders'),
            'PMS Scheduling' => route('PMS-Scheduling'),
            'Mechanic Availability' => route('mechanic-list'),
            'Fuel Reports' => route('fuel-reports'),
            'Purchase Requests' => route('purchase-requests'),
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->maintenanceUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Maintenance page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }

    public function test_maintenance_dashboard_scroll_containers(): void
    {
        $resp = $this->actingAs($this->maintenanceUser)->get(route('maintenance-dashboard'));
        $resp->assertOk();

        $resp->assertSee('dashboard-scroll-job-orders', false);
        $resp->assertSee('dashboard-scroll-mechanics', false);
        $resp->assertSee('dashboard-scroll-pms', false);
        $resp->assertSee('dashboard-scroll-parts', false);
        $resp->assertSee('dashboard-scroll-referrals', false);
    }

    public function test_maintenance_fuel_reports_charts(): void
    {
        $resp = $this->actingAs($this->maintenanceUser)->get(route('fuel-reports'));
        $resp->assertOk();

        $resp->assertSee('fuelEfficiencyChart', false);
        $resp->assertSee('fuelUsageChart', false);
        $resp->assertSee('fuelAnalyticsData', false);
    }

    // =========================================================================
    // 5. WAREHOUSE MODULE PAGES
    // =========================================================================
    public function test_warehouse_module_every_page(): void
    {
        $pages = [
            'Dashboard' => route('warehouse.dashboard'),
            'Inventory' => route('inventory'),
            'Part Requests' => route('part-requests'),
            'Incoming Deliveries' => route('incoming-deliveries'),
            'Stock Movements' => route('stock-movements'),
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->warehouseUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Warehouse page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }

    // =========================================================================
    // 6. PURCHASE MODULE PAGES
    // =========================================================================
    public function test_purchase_module_every_page(): void
    {
        $pages = [
            'Dashboard' => route('dashboard-purchase'),
            'Purchase Orders' => route('purchase-orders'),
            'Maintenance Requests' => route('maintenance-requests'),
            'Inventory Restock' => route('inventory-restock'),
            'Purchase History' => route('maintenance-requests', ['view' => 'history']),
            'Scheduled Purchase' => route('scheduled-purchase'),
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->purchaseUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Purchase page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }
}
