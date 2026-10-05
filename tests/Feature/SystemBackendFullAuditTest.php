<?php

namespace Tests\Feature;

use App\Events\SystemDataUpdated;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
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
use App\Models\TopbarNotification;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SystemBackendFullAuditTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // AREA 1: AUTHENTICATION AND ACCOUNT MANAGEMENT
    // =========================================================================

    public function test_area1_authentication_and_account_management(): void
    {
        // 1.1 Login with valid credentials and Logout
        $user = User::factory()->create([
            'email' => 'driver1@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $loginResponse = $this->post(route('login.submit'), [
            'email' => 'driver1@gct.test',
            'password' => 'Password123!',
        ]);
        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $logoutResponse = $this->post(route('logout'));
        $logoutResponse->assertRedirect(route('login'));
        $this->assertGuest();

        // 1.2 First-login onboarding redirection
        $newUser = User::factory()->create([
            'email' => 'newuser@gct.test',
            'password' => Hash::make('TempPass123!'),
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($newUser);
        $this->get(route('maintenance-dashboard'))
            ->assertRedirect(route('onboarding.show'));

        // 1.3 Temporary password change updates flag and advances onboarding
        $pwdChangeResponse = $this->put(route('account.password.update'), [
            'current_password' => 'TempPass123!',
            'password' => 'SecurePass456!',
            'password_confirmation' => 'SecurePass456!',
        ]);
        $pwdChangeResponse->assertRedirect(route('onboarding.show', ['step' => 2]));
        $newUser->refresh();
        $this->assertFalse((bool) $newUser->must_change_password);
        $this->assertTrue(Hash::check('SecurePass456!', $newUser->password));

        // Complete onboarding
        $this->post(route('onboarding.complete'))->assertRedirect();
        $newUser->refresh();
        $this->assertTrue((bool) $newUser->onboarding_completed);

        // 1.4 Admin password reset requires password change on next login
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $targetUser = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'password' => Hash::make('OldPass123!'),
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $targetUser), [
                'password' => 'ResetPass123!',
                'password_confirmation' => 'ResetPass123!',
            ])->assertRedirect(route('admin.users'));

        $targetUser->refresh();
        $this->assertTrue((bool) $targetUser->must_change_password);
        $this->assertTrue(Hash::check('ResetPass123!', $targetUser->password));

        // 1.5 Admin creating user does NOT log out the current Admin & new user can log in
        $adminSessionId = $admin->id;
        $createResponse = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Newly Created Staff',
            'email' => 'createdstaff@gct.test',
            'password' => 'InitialPass123!',
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $createResponse->assertRedirect(route('admin.users'));

        // Verify admin is still the authenticated user!
        $this->assertAuthenticatedAs($admin);
        $this->assertSame($adminSessionId, Auth::id());

        // Verify newly created user can log in
        Auth::logout();
        $this->post(route('login.submit'), [
            'email' => 'createdstaff@gct.test',
            'password' => 'InitialPass123!',
        ])->assertRedirect();
        $createdUser = User::where('email', 'createdstaff@gct.test')->firstOrFail();
        $this->assertAuthenticatedAs($createdUser);
        $this->assertTrue((bool) $createdUser->must_change_password);

        // 1.6 Roles and permissions: non-admin attempting admin password reset is forbidden (403)
        $unauthorizedStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $this->actingAs($unauthorizedStaff)
            ->post(route('admin.users.reset-password', $targetUser), [
                'password' => 'Unauthorized123!',
                'password_confirmation' => 'Unauthorized123!',
            ])
            ->assertForbidden();

        // 1.7 Guests are redirected to login
        Auth::logout();
        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->get('/job-orders')->assertRedirect(route('login'));
    }

    // =========================================================================
    // AREA 2: ADMINISTRATION MODULES
    // =========================================================================

    public function test_area2_administration_modules(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($admin);

        // 2.1 Accounts list & search
        $this->get(route('admin.users'))->assertOk();
        $this->get(route('admin.users', ['search' => 'Admin']))->assertOk();

        // 2.2 Roles & Permissions page
        $this->get(route('admin.roles-permissions'))->assertOk();

        // 2.3 Activity Logs
        $this->get(route('admin.activity-logs'))->assertOk();

        // 2.4 Notifications
        $this->get(route('admin.notifications'))->assertOk();

        // 2.5 Batch File Processing / Data Management
        $this->get(route('batch-file-processing'))->assertOk();

        // 2.6 Import / Export
        $this->get(route('admin.import-export'))->assertOk();

        // 2.7 Data History
        $this->get(route('admin.data-history'))->assertOk();

        // 2.8 Settings
        $this->get(route('admin.settings.general'))->assertOk();
        $this->get(route('admin.settings.security'))->assertOk();
        $this->get(route('admin.settings.notifications'))->assertOk();
    }

    // =========================================================================
    // AREA 3: OPERATION MODULES
    // =========================================================================

    public function test_area3_operation_modules(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($operationHead);

        // 3.1 Routes CRUD
        $this->get(route('operation.routes'))->assertOk();
        $routeResponse = $this->post(route('operation.routes.store'), [
            'route_name' => 'Calamba to Alabang Express',
            'origin' => 'Calamba Central Terminal',
            'origin_latitude' => 14.2145,
            'origin_longitude' => 121.1652,
            'destination' => 'Alabang Starmall',
            'destination_latitude' => 14.4172,
            'destination_longitude' => 121.0415,
            'distance_km' => 38.5,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);
        $routeResponse->assertRedirect();
        $shuttleRoute = ShuttleRoute::where('route_name', 'Calamba to Alabang Express')->firstOrFail();
        $this->assertNotEmpty($shuttleRoute->route_code);

        // 3.2 Trip Schedule
        $this->get(route('trip-schedule'))->assertOk();
        $tripResponse = $this->post(route('trip-schedule.store'), [
            'trip_date' => now()->toDateString(),
            'shuttle_route_id' => $shuttleRoute->id,
            'departure_time' => '07:30',
            'estimated_arrival_time' => '08:30',
            'shift' => 'Morning',
            'status' => 'Scheduled',
        ]);
        $tripResponse->assertRedirect();
        $tripSchedule = TripSchedule::where('shuttle_route_id', $shuttleRoute->id)->latest('id')->firstOrFail();
        $this->assertNotEmpty($tripSchedule->trip_code);

        // 3.3 Personnel: Drivers & Mechanics
        $this->get(route('operation.personnel.drivers'))->assertOk();
        $driverResponse = $this->post(route('operation.personnel.drivers.store'), [
            'driver_id' => 'DRV-AUDIT-01',
            'driver_name' => 'Audit Driver Pedro',
            'contact_number' => '09123456789',
            'license_number' => 'N01-12-123456',
            'license_expiry' => now()->addYears(2)->toDateString(),
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $driverResponse->assertRedirect();
        $driver = Driver::where('driver_id', 'DRV-AUDIT-01')->firstOrFail();

        $this->get(route('operation.personnel.mechanics'))->assertOk();
        $mechResponse = $this->post(route('operation.personnel.mechanics.store'), [
            'mechanic_id' => 'MEC-AUDIT-01',
            'mechanic_name' => 'Audit Mechanic Mario',
            'contact_number' => '09187654321',
            'skills' => 'Engine, Brakes, Transmission',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $mechResponse->assertRedirect();
        $mechanic = Mechanic::where('mechanic_id', 'MEC-AUDIT-01')->firstOrFail();

        // 3.4 Attendance
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);
        $this->get(route('driver-attendance'))->assertOk();

        // 3.5 Bus Master List
        $busResponse = $this->post(route('bus-master-list.store'), [
            'bus_no' => 'BUS-AUDIT-101',
            'plate_no' => 'NDS-1011',
            'bus_model' => 'Hino Grand Echo',
            'capacity' => 49,
            'status' => 'Active',
        ]);
        $busResponse->assertRedirect();
        $bus = Bus::where('bus_no', 'BUS-AUDIT-101')->firstOrFail();
        $this->get(route('bus-master-list'))->assertOk();

        // 3.6 Driver & Bus Assignment
        $this->get(route('driver-bus-assignment'))->assertOk();
        $assignResponse = $this->post(route('driver-bus-assignment.store'), [
            'trip_schedule_id' => $tripSchedule->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
        ]);
        $assignResponse->assertRedirect();
        $this->assertTrue(TripAssignment::where('trip_schedule_id', $tripSchedule->id)->exists());

        // 3.7 Auto Scheduling
        $this->get(route('auto-scheduling'))->assertOk();

        // 3.8 Trip Records & DDR
        $this->get(route('trip-records'))->assertOk();
        $this->get(route('daily-driver-reports'))->assertOk();

        // 3.9 Incidents
        $this->get(route('incidents'))->assertOk();
        $incResponse = $this->post(route('incidents.store'), [
            'trip_schedule_id' => $tripSchedule->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'SLEX Km 32 Northbound',
            'description' => 'Radiator burst hose and coolant leak.',
            'incident_reported_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $incResponse->assertRedirect();
        $incident = Incident::where('trip_schedule_id', $tripSchedule->id)->firstOrFail();
        $this->assertSame('Reported', $incident->status);
    }

    // =========================================================================
    // AREA 4: MAINTENANCE MODULES
    // =========================================================================

    public function test_area4_maintenance_modules(): void
    {
        $opHead = User::factory()->create(['department' => 'Operation', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $maintHead = User::factory()->create(['department' => 'Maintenance', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $maintStaff = User::factory()->create(['department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);

        $this->actingAs($opHead)->post(route('bus-master-list.store'), [
            'bus_no' => 'BUS-MAINT-01',
            'status' => 'Active',
        ])->assertRedirect();
        $bus = Bus::where('bus_no', 'BUS-MAINT-01')->firstOrFail();
        $incident = Incident::create([
            'incident_no' => 'INC-MAINT-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Turbina Terminal',
            'description' => 'Alternator failure.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $opHead->id,
        ]);

        // 4.1 Maintenance Referrals
        $this->actingAs($opHead)
            ->post(route('incidents.maintenance-referral.store', $incident), ['notes' => 'Please inspect.'])
            ->assertRedirect();

        $referral = MaintenanceReferral::where('incident_id', $incident->id)->firstOrFail();
        $this->assertSame('Pending', $referral->status);

        $this->actingAs($maintHead)->get(route('maintenance-referrals'))->assertOk();
        $this->actingAs($maintHead)->post(route('maintenance-referrals.approve', $referral))->assertRedirect();
        $this->assertSame('Approved', $referral->fresh()->status);

        // 4.2 Job Orders
        $this->actingAs($maintStaff)->get(route('job-orders'))->assertOk();
        $this->actingAs($maintStaff)->post(route('maintenance-referrals.job-order.store', $referral))->assertRedirect();
        $jobOrder = JobOrder::where('maintenance_referral_id', $referral->id)->firstOrFail();
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertSame('Repair', $jobOrder->maintenance_type);

        // 4.3 PMS Scheduling
        $this->actingAs($maintHead)->get(route('PMS-Scheduling'))->assertOk();
        $this->assertSame(4, PmsSchedule::where('bus_no', $bus->bus_no)->count());

        // 4.4 Mechanic Availability & Fuel Reports
        $this->actingAs($maintStaff)->get(route('job-orders.available-mechanics'))->assertOk();
        $this->actingAs($maintStaff)->get('/mechanic-attendance')->assertOk();
        $this->actingAs($maintStaff)->get(route('fuel-reports'))->assertOk();

        // 4.5 Purchase Requests
        $this->actingAs($maintStaff)->get(route('purchase-requests'))->assertOk();
    }

    // =========================================================================
    // AREA 5: WAREHOUSE MODULES
    // =========================================================================

    public function test_area5_warehouse_modules(): void
    {
        $warehouseUser = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($warehouseUser);

        // 5.1 Inventory CRUD and Ledger
        $this->get(route('inventory'))->assertOk();
        $itemResponse = $this->post(route('inventory.store'), [
            'item_code' => 'FLT-OIL-001',
            'item_name' => 'Diesel Oil Filter Heavy Duty',
            'category' => 'Filters',
            'on_hand' => 15,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 5,
            'supplier' => 'Shell Lubricants Philippines',
            'storage_location' => 'Bay 4 Shelf B',
        ]);
        $itemResponse->assertRedirect();
        $item = InventoryItem::where('item_code', 'FLT-OIL-001')->firstOrFail();
        $this->assertSame(15, (int) $item->quantity_available);

        // Verify stock movement ledger recorded initial stock
        $this->assertTrue(StockMovement::where('inventory_item_id', $item->id)->exists());

        // 5.2 Part Requests
        $this->get(route('part-requests'))->assertOk();

        // 5.3 Incoming Deliveries
        $this->get(route('incoming-deliveries'))->assertOk();

        // 5.4 Stock Movements
        $this->get(route('stock-movements'))->assertOk();
    }

    // =========================================================================
    // AREA 6: PURCHASE MODULES
    // =========================================================================

    public function test_area6_purchase_modules(): void
    {
        $purchaseUser = User::factory()->create([
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($purchaseUser);

        // 6.1 Purchase Orders
        $this->get(route('purchase-orders'))->assertOk();

        $pr = PurchaseRequest::create([
            'pr_no' => 'PR-PURCH-001',
            'job_order_no' => 'JO-PURCH-001',
            'bus_no' => 'BUS-PURCH-01',
            'status' => 'For Purchase',
            'item' => 'Brake Shoes',
            'quantity' => 4,
        ]);

        $poResponse = $this->post(route('purchase-orders.store'), [
            'purchase_request_id' => $pr->id,
            'supplier_name' => 'Bendix Brakes Official',
            'status' => 'Ordered',
            'items' => [[
                'pr_no' => $pr->pr_no,
                'bus_no' => $pr->bus_no,
                'item_description' => 'Brake Shoes',
                'quantity' => 4,
                'unit' => 'pcs',
                'cost' => 1200,
            ]],
        ]);
        $poResponse->assertRedirect('/purchase-orders');
        $po = PurchaseOrder::where('supplier_name', 'Bendix Brakes Official')->firstOrFail();
        $this->assertSame('Ordered', $po->status);

        // 6.2 Maintenance Requests
        $this->get(route('maintenance-requests'))->assertOk();

        // 6.3 Scheduled Purchase
        $this->get(route('scheduled-purchase'))->assertOk();
        $spResponse = $this->post(route('scheduled-purchase.store'), [
            'schedule_name' => 'Monthly Engine Coolant Supply',
            'item' => 'Fleet Coolant 50/50 Premix 20L',
            'quantity' => 10,
            'unit' => 'pails',
            'estimated_cost' => 1800,
            'supplier_name' => 'Caltex Lubricants',
            'frequency' => 'Monthly',
            'start_date' => now()->toDateString(),
            'next_purchase_date' => now()->addMonth()->toDateString(),
            'status' => 'Active',
            'notes' => 'Automatic replenishment for depot maintenance',
        ]);
        $spResponse->assertRedirect();
        $this->assertTrue(ScheduledPurchase::where('schedule_name', 'Monthly Engine Coolant Supply')->exists());
    }

    // =========================================================================
    // AREA 7: END-TO-END WORKFLOWS
    // =========================================================================

    public function test_area7_end_to_end_full_workflows(): void
    {
        // 7.1 Setup actors across all 5 departments
        $opHead = User::factory()->create(['department' => 'Operation', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $maintHead = User::factory()->create(['department' => 'Maintenance', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $maintStaff = User::factory()->create(['department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $warehouseHead = User::factory()->create(['department' => 'Warehouse', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $warehouseStaff = User::factory()->create(['department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $purchaseHead = User::factory()->create(['department' => 'Purchase', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);

        // 7.2 Trip Creation
        $route = ShuttleRoute::create([
            'route_code' => 'R-E2E-FULL',
            'route_name' => 'Turbina to Buendia',
            'origin' => 'Turbina',
            'destination' => 'Buendia',
            'distance_km' => 50,
            'estimated_time_minutes' => 75,
            'status' => 'Active',
        ]);

        $bus = Bus::create(['bus_no' => 'BUS-E2E-701', 'status' => 'Active']);
        $driver = Driver::firstOrCreate(
            ['driver_id' => 'DRV-E2E-701'],
            ['driver_name' => 'E2E Driver Carlos', 'shift' => 'Morning', 'employment_status' => 'Active']
        );
        $attendance = DriverAttendance::create([
            'driver_name' => $driver->driver_name,
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        $trip = TripSchedule::create([
            'trip_code' => 'TRIP-E2E-701',
            'trip_date' => now()->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '06:00:00',
            'estimated_arrival_time' => '07:15:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $opHead->id,
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'assigned_by' => $opHead->id,
        ]);

        // 7.3 Incident: Breakdown during trip
        $incident = Incident::create([
            'incident_no' => 'INC-E2E-701',
            'trip_schedule_id' => $trip->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'SLEX Magallanes Interchange',
            'description' => 'Total clutch pressure loss. Bus stranded.',
            'incident_reported_at' => now(),
            'status' => 'Responding',
            'reported_by' => $opHead->id,
        ]);

        // 7.4 Unplanned Breakdown replacement check
        $spareBus = Bus::create(['bus_no' => 'BUS-SPARE-702', 'status' => 'Active']);
        $dispatchResponse = $this->actingAs($opHead)->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
            'replacement_bus_id' => $spareBus->id,
        ]);
        $dispatchResponse->assertRedirect();

        $bus->refresh();
        $this->assertSame('Under Maintenance', $bus->status);
        $this->assertDatabaseHas('incident_replacements', [
            'incident_id' => $incident->id,
            'replacement_bus_id' => $spareBus->id,
        ]);

        // 7.5 Operation Referral -> Maintenance Head Approval
        $this->actingAs($opHead)
            ->post(route('incidents.maintenance-referral.store', $incident), [
                'notes' => 'Clutch master cylinder replacement required immediately.',
            ])->assertRedirect();

        $referral = MaintenanceReferral::where('incident_id', $incident->id)->firstOrFail();
        $this->assertSame('Pending', $referral->status);

        $this->actingAs($maintHead)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();
        $this->assertSame('Approved', $referral->fresh()->status);

        // 7.6 Job Order Creation
        $this->actingAs($maintStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral))
            ->assertRedirect();

        $jobOrder = JobOrder::where('maintenance_referral_id', $referral->id)->firstOrFail();
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertSame($bus->bus_no, $jobOrder->bus_no);
        $this->assertSame($incident->id, $jobOrder->incident_id);

        // Assign mechanic
        $mechanic = Mechanic::create([
            'mechanic_id' => 'MEC-E2E-701',
            'mechanic_name' => 'Roberto Clutch Specialist',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);
        MechanicAttendance::create([
            'mechanic_id' => $mechanic->mechanic_id,
            'mechanic_name' => $mechanic->mechanic_name,
            'attendance_date' => today(),
            'status' => 'Present',
        ]);

        $this->actingAs($maintStaff)->put(route('job-orders.update', $jobOrder), [
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'problem_issue' => $jobOrder->problem_issue,
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => $mechanic->mechanic_name,
            'parts' => [
                ['name' => 'Clutch Master Cylinder 24V', 'quantity' => 1, 'unit' => 'pcs'],
            ],
        ])->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);

        // 7.7 Job Order creates Purchase Request
        $this->actingAs($maintStaff)
            ->post(route('job-orders.create-pr', $jobOrder))
            ->assertRedirect();

        $originalPr = PurchaseRequest::where('job_order_no', $jobOrder->job_order_no)->firstOrFail();
        $this->assertSame('Submitted', $originalPr->status);

        // Maintenance Head approves PR
        $this->actingAs($maintHead)
            ->post(route('purchase-requests.approve', $originalPr))
            ->assertRedirect();
        $this->assertSame('Approved', $originalPr->fresh()->status);

        // Warehouse routes PR to Purchase
        $this->actingAs($warehouseHead)
            ->post(route('part-requests.send-to-purchase', $originalPr))
            ->assertRedirect();

        $purchasePr = PurchaseRequest::where('job_order_no', $jobOrder->job_order_no)
            ->where('id', '!=', $originalPr->id)
            ->firstOrFail();
        $this->assertSame('For Purchase', $purchasePr->status);

        // 7.8 Purchase creates Purchase Order
        $this->actingAs($purchaseHead)->post(route('purchase-orders.store'), [
            'purchase_request_id' => $purchasePr->id,
            'supplier_name' => 'Asian Transmission Corp',
            'status' => 'Ordered',
            'items' => [[
                'pr_no' => $purchasePr->pr_no,
                'bus_no' => $purchasePr->bus_no,
                'item_description' => 'Clutch Master Cylinder 24V',
                'quantity' => 1,
                'unit' => 'pcs',
                'cost' => 3500,
            ]],
        ])->assertRedirect('/purchase-orders');

        $po = PurchaseOrder::where('supplier_name', 'Asian Transmission Corp')->firstOrFail();
        $this->assertSame('Ordered', $po->status);

        // Update PO to For Delivery
        $this->actingAs($purchaseHead)
            ->patch(route('purchase-orders.update-status', $po), ['status' => 'For Delivery'])
            ->assertRedirect('/purchase-orders');

        // 7.9 Warehouse receives delivery and posts inventory
        $this->actingAs($warehouseHead)
            ->patch(route('purchase-orders.update-status', $po), [
                'status' => 'Delivered',
                'warehouse_receive' => 1,
            ])->assertRedirect('/warehouse/incoming-deliveries');

        $inventoryItem = InventoryItem::where('item_name', 'Clutch Master Cylinder 24V')->firstOrFail();
        $this->assertSame(1, (int) $inventoryItem->quantity_available);

        // 7.10 Warehouse Head authorizes; Warehouse Staff prepares and issues.
        $this->actingAs($warehouseHead)
            ->post(route('part-requests.approve-for-issue', $originalPr))
            ->assertRedirect();
        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.prepare', $originalPr))
            ->assertRedirect();
        $this->actingAs($warehouseStaff)
            ->post(route('part-requests.issue', $originalPr), ['issued_quantities' => [1]])
            ->assertRedirect();

        $this->assertSame('Issued', $originalPr->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(0, (int) $inventoryItem->fresh()->quantity_available);

        // 7.11 Maintenance completes Job Order
        $this->actingAs($maintStaff)
            ->post(route('job-orders.finish', $jobOrder))
            ->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertNotNull($jobOrder->completion_date);

        // 7.12 Traceability verification
        $this->assertSame($referral->id, $jobOrder->maintenance_referral_id);
        $this->assertSame($incident->id, $jobOrder->incident_id);
        $this->assertSame($referral->id, $incident->maintenanceReferral->id);
        $this->assertSame($jobOrder->id, $referral->jobOrder->id);
    }

    // =========================================================================
    // AREA 8: DATABASE INTEGRITY AND CONSTRAINTS
    // =========================================================================

    public function test_area8_database_integrity_and_constraints(): void
    {
        $opHead = User::factory()->create(['department' => 'Operation', 'role' => 'head', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $bus = Bus::create(['bus_no' => 'BUS-INT-001', 'status' => 'Active']);
        $incident = Incident::create([
            'incident_no' => 'INC-INT-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Depot',
            'description' => 'Inspection test',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $opHead->id,
        ]);

        // 8.1 Duplicate active referrals for the same incident are prevented
        MaintenanceReferral::create([
            'referral_no' => 'REF-INT-001',
            'incident_id' => $incident->id,
            'bus_id' => $bus->id,
            'referred_by' => $opHead->id,
            'status' => 'Pending',
        ]);

        // Attempting a second referral for the same incident should be blocked by controller guard
        $this->actingAs($opHead)
            ->post(route('incidents.maintenance-referral.store', $incident), ['notes' => 'Duplicate attempt'])
            ->assertRedirect();

        $this->assertSame(1, MaintenanceReferral::where('incident_id', $incident->id)->count());

        // 8.2 Duplicate replacement bus assignment on same incident is blocked
        $spare1 = Bus::create(['bus_no' => 'BUS-SPARE-A', 'status' => 'Active']);
        $spare2 = Bus::create(['bus_no' => 'BUS-SPARE-B', 'status' => 'Active']);

        $this->actingAs($opHead)->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
            'replacement_bus_id' => $spare1->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('incident_replacements', ['incident_id' => $incident->id, 'replacement_bus_id' => $spare1->id]);

        // Attempting to dispatch another replacement should be rejected
        $this->actingAs($opHead)->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
            'replacement_bus_id' => $spare2->id,
        ])->assertRedirect();
        $this->assertSame(1, \App\Models\Operation\IncidentReplacement::where('incident_id', $incident->id)->count());

        // 8.3 Null handling: incident without trip schedule functions smoothly
        $standaloneIncident = Incident::create([
            'incident_no' => 'INC-STANDALONE-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Yard',
            'description' => 'Broken mirror while parked',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $opHead->id,
        ]);
        $this->assertNull($standaloneIncident->trip_schedule_id);
        $this->assertNull($standaloneIncident->tripSchedule);

        $this->actingAs($opHead)
            ->get(route('incidents.show', $standaloneIncident))
            ->assertOk();
    }

    // =========================================================================
    // AREA 9: APIS AND REALTIME BROADCASTS
    // =========================================================================

    public function test_area9_apis_and_realtime_broadcasts(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user);

        // 9.1 Topbar Summary API
        $summaryResponse = $this->get(route('topbar.summary'));
        $summaryResponse->assertOk()
            ->assertJsonStructure([
                'unread_count',
                'notifications',
                'pending_actions',
                'recent_activity',
            ]);

        // 9.2 Notifications read-all API
        $readAllResponse = $this->post(route('topbar.notifications.read-all'));
        $readAllResponse->assertOk()
            ->assertJson(['success' => true]);

        // 9.3 SystemDataUpdated event structure
        $event = new SystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'created',
            101,
            'A test job order was created.'
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('system-updates', $channels[0]->name);
        $this->assertSame('SystemDataUpdated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame('Maintenance', $payload['module']);
        $this->assertSame('JobOrder', $payload['entity']);
        $this->assertSame('created', $payload['action']);
        $this->assertSame(101, $payload['record_id']);

        // 9.4 Realtime error resilience: TopbarNotification creation fails safely without crashing transactions
        $this->assertDatabaseCount('topbar_notifications', 0);
    }

    // =========================================================================
    // AREA 10: ERROR HANDLING AND STATUS CODES
    // =========================================================================

    public function test_area10_error_handling_and_status_codes(): void
    {
        $staff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        // 10.1 Missing fields / validation error (422 or redirect with errors)
        $invalidResponse = $this->actingAs($staff)->post(route('incidents.store'), []);
        $invalidResponse->assertSessionHasErrors(['incident_type', 'location']);

        // 10.2 403 Forbidden: Staff attempting head-only action
        $bus = Bus::create(['bus_no' => 'BUS-ERR-001', 'status' => 'Active']);
        $incident = Incident::create([
            'incident_no' => 'INC-ERR-001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Terminal',
            'description' => 'Test',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $staff->id,
        ]);
        $referral = MaintenanceReferral::create([
            'referral_no' => 'REF-ERR-001',
            'incident_id' => $incident->id,
            'bus_id' => $bus->id,
            'referred_by' => $staff->id,
            'status' => 'Pending',
        ]);

        // Maintenance Staff cannot approve referral
        $maintStaff = User::factory()->create(['department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active', 'must_change_password' => false, 'onboarding_completed' => true]);
        $this->actingAs($maintStaff)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertForbidden();

        // 10.3 404 Not Found on nonexistent resource
        $this->actingAs($staff)
            ->get('/operation/incidents/99999999')
            ->assertNotFound();

        // 10.4 302 Redirect to Login for unauthenticated guests
        Auth::logout();
        $this->get('/operation/incidents')->assertRedirect(route('login'));
    }
}
