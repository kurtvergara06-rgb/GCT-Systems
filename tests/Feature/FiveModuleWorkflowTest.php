<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Incident;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Models\Maintenance\Bus;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiveModuleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_incident_can_move_from_reported_to_responding(): void
    {
        $operationUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $date = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $route = ShuttleRoute::create([
            'route_code' => 'R-INC-WORKFLOW',
            'route_name' => 'Incident Workflow Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'status' => 'Active',
        ]);
        $driver = Driver::create([
            'driver_id' => 'DRV-INC-WORKFLOW',
            'driver_name' => 'Incident Workflow Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        $attendance = DriverAttendance::create([
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'attendance_date' => $date,
            'shift' => 'Morning',
            'status' => 'Present',
        ]);
        $bus = Bus::create([
            'bus_no' => 'BUS-INC-WORKFLOW',
            'plate_no' => 'INC-1001',
            'status' => 'Active',
            'fleet_purpose' => 'Regular',
        ]);
        $trip = TripSchedule::create([
            'trip_code' => 'TRIP-INC-WORKFLOW',
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'estimated_arrival_date' => $date,
            'shift' => 'Morning',
            'direction' => 'Incoming',
            'required_buses' => 1,
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);
        $assignment = TripAssignment::create([
            'trip_schedule_id' => $trip->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
            'slot_number' => 1,
        ]);
        $trip->syncAssignmentStatus();

        $this->actingAs($operationUser)->post(route('incidents.store'), [
            'trip_schedule_id' => $trip->id,
            'trip_assignment_id' => $assignment->id,
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Test terminal',
            'description' => 'Engine stopped during a trip.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame('Reported', $incident->status);

        $this->actingAs($operationUser)->put(route('incidents.update', $incident), [
            'status' => 'Responding',
            'location' => 'Test terminal',
            'resolution_notes' => 'Operations dispatched assistance.',
        ])->assertRedirect();

        $this->assertSame('Responding', $incident->fresh()->status);
    }

    public function test_maintenance_warehouse_purchase_and_inventory_round_trip_stays_synchronized(): void
    {
        Storage::fake('local');
        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $warehouseUser = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $warehouseStaff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $purchaseUser = User::factory()->create([
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-FLOW-0001',
            'bus_no' => 'BUS-FLOW-0001',
            'problem_issue' => 'Brake pads need replacement',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Flow Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'start_date' => now(),
            'status' => 'On Hold',
            'part_status' => 'Not Requested',
            'review_status' => 'Approved',
            'approved_by' => $maintenanceHead->id,
            'approved_at' => now(),
            'parts_issue_strategy' => 'wait_until_complete',
            'inventory_snapshot' => ['shortages' => [['name' => 'Brake Pad', 'quantity' => 2, 'unit' => 'pcs']]],
        ]);

        $this->actingAs($maintenanceStaff)
            ->post(route('purchase-requests.store'), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
            ])
            ->assertRedirect(route('purchase-requests', ['record_view' => 'active'], false));

        $originalPr = PurchaseRequest::query()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->firstOrFail();

        $this->assertSame('Submitted', $originalPr->status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);

        $this->actingAs($warehouseUser)
            ->post(route('part-requests.send-to-purchase', $originalPr))
            ->assertRedirect();

        $this->assertSame(1, PurchaseRequest::query()->where('job_order_no', $jobOrder->job_order_no)->count());
        $this->assertSame('For Purchase', $originalPr->fresh()->status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);

        $this->actingAs($purchaseUser)->post(route('purchase-orders.store'), [
            'purchase_request_id' => $originalPr->id,
            'supplier_name' => 'Flow Supplier',
            'status' => 'For Review',
            'items' => [[
                'pr_no' => $originalPr->pr_no,
                'bus_no' => $originalPr->bus_no,
                'item_description' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'pcs',
                'cost' => 100,
            ]],
        ])->assertRedirect('/purchase-orders');

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('For Review', $purchaseOrder->status);
        $this->assertSame('For Purchase', $originalPr->fresh()->status);
        $this->assertSame('For Purchase', $jobOrder->fresh()->part_status);

        $this->actingAs($purchaseUser)
            ->patch(route('purchase-orders.approve', $purchaseOrder), [
                'signature' => UploadedFile::fake()->create('purchase-head.png', 20, 'image/png'),
            ])->assertRedirect('/purchase-orders')->assertSessionHasNoErrors();

        $purchaseOrder->refresh();
        $this->assertSame('Ordered', $purchaseOrder->status);
        $this->assertSame('Ordered', $originalPr->fresh()->status);
        $this->assertSame('Ordered', $jobOrder->fresh()->part_status);

        $this->actingAs($purchaseUser)
            ->patch(route('purchase-orders.update-status', $purchaseOrder), [
                'status' => 'For Delivery',
            ])
            ->assertRedirect('/purchase-orders');

        $this->actingAs($warehouseUser)
            ->post(route('incoming-deliveries.receive', $purchaseOrder), ['received_quantities' => [1]])
            ->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();

        $inventoryItem = InventoryItem::query()->where('item_name', 'Brake Pad')->firstOrFail();
        $this->assertSame(1, (int) $inventoryItem->quantity_available);
        $this->assertNull($purchaseOrder->fresh()->inventory_posted_at);
        $this->assertSame('For Delivery', $originalPr->fresh()->status);

        $this->actingAs($warehouseUser)
            ->post(route('incoming-deliveries.receive', $purchaseOrder), ['received_quantities' => [1]])
            ->assertRedirect('/warehouse/incoming-deliveries')->assertSessionHasNoErrors();

        $inventoryItem->refresh();

        $this->assertSame(2, (int) $inventoryItem->quantity_available);
        $this->assertSame('Delivered', $originalPr->fresh()->status);
        $this->assertSame('Delivered', $jobOrder->fresh()->part_status);
        $this->assertNotNull($purchaseOrder->fresh()->inventory_posted_at);

        $itemRequest = ItemRequest::create([
            'job_order_id' => $jobOrder->id,
            'requested_by' => $maintenanceStaff->id,
            'status' => 'Submitted',
            'requested_parts' => [[
                'name' => 'Brake Pad', 'quantity' => 2, 'unit' => 'pcs',
                'inventory_item_id' => $inventoryItem->id,
            ]],
        ]);
        $this->actingAs($warehouseStaff)
            ->post(route('warehouse.item-requests.issue', $itemRequest), [
                'received_by' => 'Flow Mechanic',
                'signed_slip' => UploadedFile::fake()->create('signed-issuance.pdf', 20, 'application/pdf'),
                'handover_confirmed' => '1',
                'issued_quantities' => [2],
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Delivered', $originalPr->fresh()->status);
        $this->assertSame('Issued', $itemRequest->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(0, (int) $inventoryItem->fresh()->quantity_available);
    }

    public function test_administration_security_coverage_remains_part_of_the_five_module_suite(): void
    {
        $this->assertFileExists(base_path('tests/Feature/Admin/AdminPasswordResetSecurityTest.php'));
    }
}
