<?php

namespace Tests\Feature;

use App\Http\Controllers\Warehouse\WarehouseDashboardController;
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

    public function test_analytics_pages_render_without_forcing_a_notification(): void
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

            if (str_contains($content, 'data-analytics-insight-toast')) {
                $this->assertStringContainsString('data-toast-action', $content);
                $this->assertStringContainsString('data-target-selector', $content);
                $this->assertStringContainsString('window.gctHighlightTarget', $content);
            }
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
            'Client Trip Times' => route('client-trip-times'),
            'Driver Master List' => route('operation.personnel.drivers'),
            'Driver Attendance' => route('driver-attendance'),
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
        $resp->assertDontSee('Generate Daily Trips', false);
        $resp->assertDontSee('id="generateTripsModal"', false);
        $resp->assertSee('New Trip', false);
        $resp->assertSee('id="tripModal"', false);
        $resp->assertSee('id="tripModalTitle"', false);
        $resp->assertSee('id="tripSubmitText"', false);
        $resp->assertSee('id="viewTripModal"', false);
        $resp->assertSee('trip-editor-reference-layout', false);
        $resp->assertSee('trip-reference-grid', false);
        $resp->assertSee('name="trip_mode_choice" value="client"', false);
        $resp->assertSee('name="trip_mode_choice" value="general"', false);
        $resp->assertSee('id="departureTimeSlot" disabled hidden', false);
        $resp->assertSee('trip-details-hero', false);
    }

    public function test_operation_dialog_and_toolbar_viewport_guards_are_present(): void
    {
        $popoverScript = file_get_contents(resource_path('js/Main-js/table-toolbar.js'));
        $operationStyles = file_get_contents(resource_path('css/Operation/operation-action-buttons.css'));
        $modalRuntime = file_get_contents(resource_path('js/Main-js/global-modal-backdrop.js'));

        // The browser top layer avoids fixed-position offsets caused by GSAP
        // transforms. Form fields remain inside the original toolbar element.
        $this->assertStringContainsString("popover.showPopover()", $popoverScript);
        $this->assertStringContainsString("popover.hidePopover()", $popoverScript);
        $this->assertStringContainsString("getBoundingClientRect()", $popoverScript);
        $this->assertStringContainsString("window.visualViewport?.addEventListener('resize'", $popoverScript);

        // This is scoped to Operation and also supports dialogs that the
        // shared modal portal has moved from <main> into <body>.
        $this->assertStringContainsString('Operation-only viewport modal contract', $operationStyles);
        $this->assertStringContainsString('max-width: min(100%, calc(100vw - 24px))', $operationStyles);
        $this->assertStringContainsString('hasFixedPositionContainingBlock(overlay)', $modalRuntime);
        $this->assertStringContainsString('document.body.appendChild(overlay)', $modalRuntime);

        $assignmentScript = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js'));
        $datePickerScript = file_get_contents(resource_path('js/Main-js/date-time-picker.js'));

        // Searchable assignment menus also escape the modal scrollport,
        // without moving their option lists out of their comboboxes.
        $this->assertStringContainsString("showAssignmentMenu(assignmentDriverMenu, assignmentDriverTrigger)", $assignmentScript);
        $this->assertStringContainsString("showAssignmentMenu(assignmentBusMenu, assignmentBusTrigger)", $assignmentScript);
        $this->assertStringContainsString("menu.showPopover()", $assignmentScript);
        $this->assertStringContainsString("menu.hidePopover()", $assignmentScript);
        $this->assertStringContainsString("const dropdownOpen = assignmentDriverMenu?.classList.contains('show')", $assignmentScript);

        // Filter calendar must share its native top-layer popup so it is not
        // obscured by the filter itself.
        $this->assertStringContainsString("input.closest('.gct-table-tool-popover')", $datePickerScript);
        $this->assertStringContainsString('portalTarget.appendChild(this.popover)', $datePickerScript);
        $this->assertStringContainsString("toolbarPopover?.matches(':popover-open')", $datePickerScript);
        $this->assertStringContainsString('this.popover.showPopover()', $datePickerScript);
        $this->assertStringContainsString("this.popover.removeAttribute('popover')", $datePickerScript);
        $this->assertStringContainsString("menu.removeAttribute('popover')", $assignmentScript);
        $this->assertStringContainsString("popover.removeAttribute('popover')", $popoverScript);
        $this->assertStringContainsString("picker?.querySelector('.gct-picker-close')?.click()", $popoverScript);

        // Both bespoke Operation record overlays get the same viewport portal
        // and backdrop-dismissal protection as shared form modals.
        $this->assertStringContainsString("'.ddr-record-overlay'", $modalRuntime);
        $this->assertStringContainsString("'.inc-record-modal-backdrop'", $modalRuntime);
        $this->assertSame(2, substr_count(
            $modalRuntime,
            "attributeFilter: ['class', 'style', 'hidden', 'aria-hidden']"
        ));
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
            'Mechanic Master List' => route('maintenance.personnel.mechanics'),
            'Mechanic Attendance' => route('mechanic-attendance'),
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

        $resp->assertSee('fuel-records', false);
        $resp->assertSee('efficiencyPreview', false);
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

    public function test_warehouse_dashboard_operational_sections(): void
    {
        $response = $this->actingAs($this->warehouseUser)->get(route('warehouse.dashboard'));

        $response->assertOk()
            ->assertSee('Warehouse Dashboard')
            ->assertSee('Active Part Requests')
            ->assertSee('MAINTENANCE REQUISITIONS')
            ->assertSee('Incoming Deliveries')
            ->assertSee('PURCHASE SHIPMENTS')
            ->assertSee('Stock Status')
            ->assertSee('Recent Stock Movements')
            ->assertSee('TRANSACTION AUDIT')
            ->assertDontSee('QUICK ACCESS')
            ->assertDontSee('Warehouse Actions');
    }

    public function test_warehouse_dashboard_counts_all_active_requests_and_prioritizes_oldest_approved(): void
    {
        $initialActiveCount = app(WarehouseDashboardController::class)->data()['activePartRequestCount'];

        $requests = [
            ['PR-ACTIVE-001', 'Approved', now()->subDays(2)],
            ['PR-ACTIVE-002', 'Approved', now()->subDays(5)],
            ['PR-ACTIVE-003', 'For Purchase', now()->subDays(8)],
            ['PR-ACTIVE-004', 'Ordered', now()->subDays(7)],
            ['PR-ACTIVE-005', 'For Pick-up', now()->subDays(6)],
            ['PR-ACTIVE-006', 'For Delivery', now()->subDays(4)],
            ['PR-INACTIVE-001', 'Issued', now()->subDays(10)],
        ];

        foreach ($requests as [$prNo, $status, $createdAt]) {
            $request = PurchaseRequest::create([
                'pr_no' => $prNo,
                'job_order_no' => 'JO-'.$prNo,
                'bus_no' => 'BUS-001',
                'item' => 'Brake Pad',
                'quantity' => 2,
                'status' => $status,
                'source_type' => 'Maintenance Request',
            ]);

            $request->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        $data = app(WarehouseDashboardController::class)->data();

        $this->assertSame($initialActiveCount + 6, $data['activePartRequestCount']);
        $this->assertCount(5, $data['activePartRequests']);
        $this->assertSame('PR-ACTIVE-002', $data['activePartRequests']->first()->pr_no);
        $this->assertSame('Approved', $data['activePartRequests']->first()->status);
    }

    public function test_stock_movement_filters_do_not_expose_internal_record_sources(): void
    {
        $response = $this->actingAs($this->warehouseUser)->get(route('stock-movements'));

        $response
            ->assertOk()
            ->assertSee('Complete inventory transaction and adjustment history.')
            ->assertSee('Adjustments')
            ->assertDontSee('Record Origin')
            ->assertDontSee('movementSourceFilter', false)
            ->assertDontSee('Simulated')
            ->assertDontSee('System Transactions')
            ->assertSeeInOrder([
                'movementTypeFilter',
                'movementDateFilter',
            ], false);
    }

    public function test_inventory_table_uses_compact_columns_without_removing_supplier_data(): void
    {
        InventoryItem::create([
            'item_code' => 'COMPACT-001',
            'item_name' => 'Compact Layout Brake Pad',
            'category' => 'Brakes',
            'quantity_available' => 12,
            'unit_of_measurement' => 'sets',
            'reorder_level' => 4,
            'supplier' => 'Southern Luzon Parts Supply',
            'storage_location' => 'Rack B-2',
        ]);

        $response = $this->actingAs($this->warehouseUser)->get(route('inventory'));
        $response
            ->assertOk()
            ->assertSee('Issue Stock')
            ->assertSee('id="issueModal"', false);

        preg_match('/<table class="inventory-table">.*?<\/table>/s', $response->getContent(), $matches);
        $table = $matches[0] ?? '';

        $this->assertNotSame('', $table);
        $this->assertSame(8, substr_count($table, '<th>'));
        $this->assertStringContainsString('<th>Item / Part</th>', $table);
        $this->assertStringContainsString('<th>On Hand</th>', $table);
        $this->assertStringContainsString('Supplier:', $table);
        $this->assertStringNotContainsString('<th>Last Updated</th>', $table);
        $this->assertStringContainsString('inventory-item-cell', $table);
        $this->assertStringContainsString('inventory-stock-cell', $table);
        $this->assertStringContainsString('Southern Luzon Parts Supply', $table);
    }

    public function test_inventory_uses_one_operational_dataset_without_source_filter_or_badges(): void
    {
        $firstItem = InventoryItem::create([
            'item_code' => 'INV-UNIFIED-001',
            'item_name' => 'Unified Brake Pad A',
            'category' => 'Brakes',
            'on_hand' => 8,
            'quantity_available' => 8,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
            'source' => 'app',
        ]);

        $secondItem = InventoryItem::create([
            'item_code' => 'INV-UNIFIED-002',
            'item_name' => 'Unified Brake Pad B',
            'category' => 'Brakes',
            'on_hand' => 12,
            'quantity_available' => 12,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 3,
            'source' => 'simulated',
        ]);

        $response = $this->actingAs($this->warehouseUser)
            ->get(route('inventory'));

        $response
            ->assertOk()
            ->assertSee($firstItem->item_name)
            ->assertSee($secondItem->item_name)
            ->assertDontSee('inventorySourceFilter', false)
            ->assertDontSee('Application Records')
            ->assertDontSee('Simulated Records')
            ->assertDontSee('source-badge--app', false)
            ->assertDontSee('source-badge--simulated', false);
    }

    public function test_inventory_movement_history_opens_as_a_modal_without_the_simulated_notice(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'MODAL-001',
            'item_name' => 'Modal Test Battery',
            'category' => 'Electrical',
            'on_hand' => 8,
            'quantity_available' => 8,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
        ]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'reference_no' => 'SIM-MODAL-001',
            'movement_type' => 'Stock Out',
            'quantity_change' => -2,
            'previous_stock' => 10,
            'new_stock' => 8,
            'unit' => 'pcs',
            'remarks' => 'Modal test movement.',
            'source' => 'simulated',
        ]);

        $inventory = $this->actingAs($this->warehouseUser)->get(route('inventory'));

        $inventory
            ->assertOk()
            ->assertSee('openMovementHistory', false)
            ->assertSee('id="movementHistoryModal"', false)
            ->assertSee('data-item-id="'.$item->id.'"', false)
            ->assertSee(route('inventory.movements', $item), false)
            ->assertDontSee('href="'.route('inventory.movements', $item).'"', false);

        $modal = $this->actingAs($this->warehouseUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('inventory.movements', [
                'inventoryItem' => $item,
                'modal' => 1,
            ]));

        $modal
            ->assertOk()
            ->assertSee('data-movement-history-content', false)
            ->assertSee('Modal Test Battery')
            ->assertDontSee('Simulated')
            ->assertDontSee('Source')
            ->assertDontSee('This item has')
            ->assertDontSee('These rows are isolated from application records')
            ->assertDontSee('Back to Inventory');

        $direct = $this->actingAs($this->warehouseUser)
            ->get(route('inventory.movements', $item));

        $direct->assertRedirect(route('inventory', [
            'movement_item' => $item->id,
        ]));
    }

    public function test_inventory_movement_modal_uses_ajax_without_page_navigation(): void
    {
        $js = file_get_contents(
            resource_path('js/Warehouse/inventory.js')
        );

        $this->assertStringContainsString(
            'loadMovementHistory',
            $js
        );
        $this->assertStringContainsString(
            "event.target.closest('.openMovementHistory')",
            $js
        );
        $this->assertStringContainsString(
            "'X-Requested-With': 'XMLHttpRequest'",
            $js
        );
        $this->assertStringContainsString(
            "parsed.searchParams.set('modal', '1')",
            $js
        );
        $this->assertStringContainsString(
            'data-movement-history-filter',
            $js
        );
        $this->assertStringContainsString(
            'ajax:content-updated',
            $js
        );
        $this->assertStringContainsString(
            'movementHistoryRequest?.abort();',
            $js
        );
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
        ];

        foreach ($pages as $label => $url) {
            $resp = $this->actingAs($this->purchaseUser)->get($url);
            $this->assertTrue(
                $resp->status() === 200,
                "Purchase page '{$label}' failed with status {$resp->status()} at {$url}"
            );
        }
    }

    public function test_sidebar_account_menu_and_compact_profile_keep_supported_actions(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/layout/sidebar.blade.php'));
        $sidebarScript = file_get_contents(resource_path('js/Main-js/sidebar.js'));
        $sidebarStyles = file_get_contents(resource_path('css/Main-styles/sidebar.css'));
        $profile = file_get_contents(resource_path('views/Account/profile.blade.php'));

        $this->assertStringContainsString("route('account.profile', [], false)", $sidebar);
        $this->assertStringContainsString("route('account.settings', [], false)", $sidebar);
        $this->assertStringContainsString("route('onboarding.show', ['replay' => 1], false)", $sidebar);
        $this->assertStringContainsString('data-confirm-type="logout"', $sidebar);
        $this->assertStringContainsString('aria-controls="sidebarProfileMenu"', $sidebar);
        $this->assertStringNotContainsString('class="profile-menu-item" disabled', $sidebar);
        $this->assertStringNotContainsString("const accountUrl = label === 'profile'", $sidebarScript);
        $this->assertStringContainsString("profileMenu.setAttribute('aria-hidden', 'false')", $sidebarScript);
        $this->assertStringContainsString('Compact account menu: GCT', $sidebarStyles);

        $this->assertStringContainsString('<details class="account-card account-summary-card account-details-disclosure" open>', $profile);
        $this->assertStringContainsString('account-profile-grid', $profile);
        $this->assertStringContainsString("route('account.profile.update', [], false)", $profile);
        $this->assertStringContainsString("route('account.profile.photo.update', [], false)", $profile);
        $this->assertStringContainsString('account-access-section', $profile);
        $this->assertStringContainsString('account-profile-aside', $profile);
        $this->assertStringContainsString('account-profile-tabs', $profile);
        $this->assertStringContainsString('account-nav-tabs', $profile);
        $this->assertStringContainsString('account-profile-metrics', $profile);
        $this->assertStringContainsString('account-profile-photo-fields', $profile);
        $this->assertStringContainsString('Critical profile-header geometry', $profile);
        $this->assertStringContainsString('width="80" height="80"', $profile);
        $this->assertStringContainsString('object-fit:cover;border-radius:50%', $profile);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $profile);
        $this->assertStringContainsString('data-avatar-change-button', $profile);
        $this->assertSame(1, substr_count($profile, 'data-avatar-upload-form'));
        $this->assertSame(1, substr_count($profile, 'action="{{ route(\'account.profile.update\', [], false) }}"'));
        $accountScript = file_get_contents(resource_path('js/Account/account.js'));
        $this->assertStringContainsString("avatarTrigger?.click()", $accountScript);
        $this->assertStringContainsString('System Access', $profile);
        $this->assertStringContainsString('Account History', $profile);
        $this->assertStringContainsString('Member Since', $profile);
        $this->assertStringContainsString('Last Login Recorded', $profile);
        $this->assertStringContainsString("route('account.personalization')", $profile);
        $this->assertStringContainsString("route('account.preferences')", $profile);
        $this->assertStringNotContainsString('<input type="text" value="{{ $department }}" readonly>', $profile);
        $this->assertStringNotContainsString('<input type="text" value="{{ $displayRole }}" readonly>', $profile);
    }

    public function test_routes_inactive_summary_uses_supported_neutral_icon_box(): void
    {
        $routes = file_get_contents(resource_path('views/Operation/Routes/routes-stops.blade.php'));
        $sharedStyles = file_get_contents(resource_path('css/Main-styles/main.css'));

        // "orange" has no shared .stat-icon palette definition and would
        // leave the Inactive Routes icon floating without a background box.
        $this->assertMatchesRegularExpression(
            '/label="Inactive Routes"\\s+:value="\\$routeStats\\[\\x27inactive\\x27\\]"\\s+small="Currently unavailable"\\s+icon="fa-circle-pause"\\s+color="gray"/',
            $routes
        );
        $this->assertStringContainsString('.stat-icon.gray', $sharedStyles);
    }

    public function test_routes_dark_theme_keeps_headers_and_sticky_actions_readable(): void
    {
        $styles = file_get_contents(resource_path('css/Main-styles/account-preferences.css'));

        // The sticky-actions utility uses a light default background unless
        // its semantic dark-mode properties are explicitly provided.
        $this->assertStringContainsString('Routes dark-mode contrast contract', $styles);
        $this->assertStringContainsString('--gct-sticky-actions-background: var(--gct-theme-surface, #1d2b40)', $styles);
        $this->assertStringContainsString('--gct-sticky-actions-heading: var(--gct-theme-surface-raised, #243750)', $styles);
        $this->assertStringContainsString('--gct-sticky-actions-hover: var(--gct-theme-hover, #293c56)', $styles);
        // Auto-ID badges use a global black-text !important rule. Their
        // narrow Routes dark-mode override must restore readable ID text.
        $this->assertStringContainsString('.routes-table tbody td:first-child .system-id-badge', $styles);
        $this->assertStringContainsString('color: #edf5ff !important', $styles);
        $this->assertStringContainsString('.routes-table .route-action:is(.view,.edit)', $styles);
        $this->assertStringContainsString('.routes-table .route-action.delete', $styles);
        $this->assertStringContainsString('.route-action:focus-visible', $styles);
        $this->assertStringContainsString('html[data-gct-theme="dark"] .routes-page .routes-table thead th', $styles);
        $this->assertStringContainsString('html[data-gct-theme="dark"] .routes-page .routes-table tbody tr > td', $styles);
        $this->assertStringContainsString('.routes-record-controls > .routes-segmented-tabs', $styles);
    }

    public function test_personalization_and_general_settings_are_separate_authenticated_pages(): void
    {
        $this->get(route('account.personalization'))->assertRedirect(route('login'));
        $this->get(route('account.preferences'))->assertRedirect(route('login'));

        $this->actingAs($this->operationUser)
            ->get(route('account.personalization'))
            ->assertOk()
            ->assertSeeText('Make your workspace yours')
            ->assertSeeText('Appearance')
            ->assertSeeText('Typography')
            ->assertSeeText('Workspace')
            ->assertSee('data-gct-preference="theme"', false)
            ->assertSee('data-gct-preference="font"', false)
            ->assertSee('data-gct-preference="fontSize"', false)
            ->assertSee('data-gct-preference="density"', false)
            ->assertSee('data-gct-preference="reducedMotion"', false)
            ->assertSee('data-gct-reset-preferences', false);

        $this->actingAs($this->operationUser)
            ->get(route('account.preferences'))
            ->assertOk()
            ->assertSeeText('My Account Settings')
            ->assertSee('data-gct-reset-preferences', false)
            ->assertSee(route('account.settings', [], false), false);

        // Both account pages must load their component styling after account.css.
        // Depending on Vite CSS extraction, relying on the app.js import alone
        // can leave the theme cards and introduction banner unstyled.
        $personalization = file_get_contents(resource_path('views/Account/personalization.blade.php'));
        $settingsPage = file_get_contents(resource_path('views/Account/preferences.blade.php'));
        $explicitCss = "'resources/css/Account/account.css', 'resources/css/Main-styles/account-preferences.css'";
        $this->assertStringContainsString($explicitCss, $personalization);
        $this->assertStringContainsString($explicitCss, $settingsPage);
        $this->assertStringContainsString('gct-personalization-intro', $personalization);
        $this->assertStringContainsString('gct-theme-options', $personalization);

        $sidebar = file_get_contents(resource_path('views/components/layout/sidebar.blade.php'));
        $this->assertStringContainsString("route('account.personalization', [], false)", $sidebar);
        $this->assertStringContainsString("route('account.preferences', [], false)", $sidebar);

        // Require a matching authenticated identity before reading account-local preferences.
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $this->assertStringContainsString('name="gct-account-id"', $layout);
        $this->assertStringContainsString('content="{{ auth()->id() }}"', $layout);
        $this->assertStringContainsString('data-gct-user-id="{{ $authUser?->id }}"', $sidebar);


        $runtime = file_get_contents(resource_path('js/Main-js/account-preferences.js'));
        $this->assertStringContainsString("keyForCurrentUser", $runtime);

        $this->assertStringContainsString("currentAccountId", $runtime);
        $this->assertStringContainsString('meta[name="gct-account-id"]', $runtime);
        $this->assertStringContainsString('serverId === sidebarId', $runtime);
        $this->assertStringContainsString("window.addEventListener('pageshow', renderPreferences)", $runtime);
        $this->assertStringContainsString("window.addEventListener('pageshow', renderSoundPreference)", $runtime);
        $this->assertStringContainsString("localStorage.setItem", $runtime);
        $this->assertStringContainsString("localStorage.removeItem", $runtime);
        $this->assertStringContainsString("gct-reduced-motion", $runtime);
        $this->assertStringContainsString("gct-density-compact", $runtime);
        $this->assertStringContainsString("root.dataset.gctTheme", $runtime);
        $this->assertStringContainsString("root.dataset.gctFont", $runtime);
        $this->assertStringContainsString("root.dataset.gctFontSize", $runtime);
        $this->assertStringContainsString("prefers-color-scheme: dark", $runtime);
        $styles = file_get_contents(resource_path('css/Main-styles/account-preferences.css'));
        $this->assertStringContainsString('html[data-gct-theme="dark"]', $styles);
        $this->assertStringContainsString('.gct-theme-options', $styles);
    }

    public function test_operation_master_lists_use_ten_row_scrollable_table_viewports(): void
    {
        $views = [
            'Operation/Shuttle_Bus_Management/bus-master-list.blade.php',
            'Operation/Personnel Management/driver_master_list.blade.php',
            'Operation/Personnel Management/mechanic_master_list.blade.php',
            'Operation/Clients/index.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('gct-master-scroll-10', $contents, $view);
        }

        $runtime = file_get_contents(resource_path('js/Main-js/master-list-scroll.js'));
        $this->assertStringContainsString('const VISIBLE_ROWS = 10;', $runtime);
        $this->assertStringContainsString('rows.slice(0, VISIBLE_ROWS)', $runtime);
        $this->assertStringContainsString('gct:navigation-ready', $runtime);

        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("import './Main-js/master-list-scroll.js';", $app);

        $styles = file_get_contents(resource_path('css/Main-styles/gct-scrollbar.css'));
        $this->assertStringContainsString('gct-master-scroll-10.is-master-scrollable', $styles);
        $this->assertStringContainsString('position: sticky;', $styles);

        $busStyles = file_get_contents(resource_path('css/Operation/Shuttle_Bus_Management/bus-master-list.css'));
        $this->assertStringContainsString('gct-master-scroll-10.is-master-scrollable', $busStyles);
        $this->assertStringContainsString('overflow-y: auto !important;', $busStyles);
        $this->assertStringNotContainsString('.table-wrap:has(tbody > tr:nth-child(8))', $busStyles);

        $this->assertStringContainsString('MutationObserver(refreshMasterScroll)', $runtime);
        $this->assertStringContainsString('observeMasterRows()', $runtime);
    }

    public function test_driver_master_view_edit_modals_receive_decodable_record_attributes(): void
    {
        Driver::create([
            'driver_id' => 'DRV-UI-ENCODE-1',
            'driver_name' => 'Driver UI Regression',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $response = $this->actingAs($this->operationUser)
            ->get(route('operation.personnel.drivers'));

        $response->assertOk();
        $html = (string) $response->getContent();

        // The browser decodes HTML entities before JSON.parse receives them.
        $this->assertSame(
            Driver::query()->count() * 2,
            substr_count($html, 'data-record="{&quot;driver_id&quot;:')
        );
        $this->assertStringNotContainsString('&amp;quot;driver_id', $html);
        $this->assertStringContainsString('data-personnel-action="view"', $html);
        $this->assertStringContainsString('data-personnel-action="edit"', $html);
        $this->assertStringContainsString('data-personnel-modal', $html);
        $this->assertStringContainsString('data-personnel-view-notice', $html);
    }
}
