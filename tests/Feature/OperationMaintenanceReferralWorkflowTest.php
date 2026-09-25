<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Incident;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationMaintenanceReferralWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_head_can_refer_breakdown_and_maintenance_head_can_approve(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-REF-001',
            'status' => 'Active',
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-REF-0001',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Terminal A',
            'description' => 'Engine failed while entering the terminal.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident), [
                'notes' => 'Please inspect the engine.',
            ])
            ->assertRedirect();

        $referral = MaintenanceReferral::query()->firstOrFail();

        $this->assertSame($incident->id, $referral->incident_id);
        $this->assertSame('Pending', $referral->status);
        $this->assertSame($operationHead->id, $referral->referred_by);

        $this->actingAs($maintenanceHead)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();

        $referral->refresh();

        $this->assertSame('Approved', $referral->status);
        $this->assertSame($maintenanceHead->id, $referral->reviewed_by);
        $this->assertNotNull($referral->reviewed_at);
    }

    public function test_maintenance_staff_creates_traceable_job_order_from_approved_referral(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-REF-002',
            'status' => 'Active',
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-REF-0002',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Route 2',
            'description' => 'Brake pressure warning and loss of braking response.',
            'incident_reported_at' => now(),
            'status' => 'Responding',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertRedirect();

        $referral = MaintenanceReferral::query()->firstOrFail();

        $this->actingAs($maintenanceHead)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();

        $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral))
            ->assertRedirect();

        $jobOrder = JobOrder::query()->firstOrFail();
        $referral->refresh();

        $this->assertSame($referral->id, $jobOrder->maintenance_referral_id);
        $this->assertSame($incident->id, $jobOrder->incident_id);
        $this->assertSame($bus->bus_no, $jobOrder->bus_no);
        $this->assertSame('Repair', $jobOrder->maintenance_type);
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertSame('Job Order Created', $referral->status);
        $this->assertSame($jobOrder->id, $referral->jobOrder?->id);
    }

    public function test_referral_permissions_and_duplicate_guards_are_enforced(): void
    {
        $operationStaff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-REF-003',
            'status' => 'Active',
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-REF-0003',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Depot',
            'description' => 'Transmission fault.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationStaff)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertForbidden();

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertRedirect();

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertRedirect();

        $this->assertSame(1, MaintenanceReferral::count());

        $referral = MaintenanceReferral::query()->firstOrFail();

        $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertForbidden();

        $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral))
            ->assertRedirect();

        $this->assertSame(0, JobOrder::count());
    }

    public function test_non_breakdown_incident_cannot_be_referred(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-REF-004',
            'status' => 'Active',
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-REF-0004',
            'bus_id' => $bus->id,
            'incident_type' => 'Traffic',
            'location' => 'Highway',
            'description' => 'Heavy traffic only.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $operationHead->id,
        ]);

        $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident))
            ->assertRedirect();

        $this->assertSame(0, MaintenanceReferral::count());
    }

    public function test_complete_operation_maintenance_warehouse_end_to_end_workflow_and_traceability(): void
    {
        // 1. Setup multi-department personnel
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $bus = Bus::create([
            'bus_no' => 'BUS-E2E-001',
            'status' => 'Active',
            'latest_gps_km' => 25000.0,
        ]);

        // 2. Operation: Create Bus Breakdown Incident
        $incident = Incident::create([
            'incident_no' => 'INC-2026-E2E',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'EDSA Southbound Km 18',
            'description' => 'Alternator failure and total loss of electrical power.',
            'incident_reported_at' => now(),
            'status' => 'Responding',
            'reported_by' => $operationHead->id,
        ]);

        // 3. Operation Head sends Maintenance Referral
        $referResponse = $this->actingAs($operationHead)
            ->post(route('incidents.maintenance-referral.store', $incident), [
                'notes' => 'Requires alternator bench test and immediate replacement.',
            ]);
        $referResponse->assertRedirect();

        $referral = MaintenanceReferral::where('incident_id', $incident->id)->firstOrFail();
        $this->assertSame('Pending', $referral->status);
        $this->assertSame($incident->id, $referral->incident_id);

        // 4. Maintenance Head sees referral and approves it
        $referralViewResponse = $this->actingAs($maintenanceHead)->get(route('maintenance-referrals'));
        $referralViewResponse->assertStatus(200);
        $referralViewResponse->assertSee('INC-2026-E2E');
        $referralViewResponse->assertSee('Pending');

        $approveResponse = $this->actingAs($maintenanceHead)
            ->post(route('maintenance-referrals.approve', $referral));
        $approveResponse->assertRedirect();

        $referral->refresh();
        $this->assertSame('Approved', $referral->status);
        $this->assertSame($maintenanceHead->id, $referral->reviewed_by);

        // 5. Maintenance Staff creates Job Order from approved referral
        $createJoResponse = $this->actingAs($maintenanceStaff)
            ->post(route('maintenance-referrals.job-order.store', $referral));
        $createJoResponse->assertRedirect();

        $jobOrder = JobOrder::where('maintenance_referral_id', $referral->id)->firstOrFail();
        $referral->refresh();

        $this->assertSame('Job Order Created', $referral->status);
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertSame('Repair', $jobOrder->maintenance_type);
        $this->assertSame($bus->bus_no, $jobOrder->bus_no);
        $this->assertSame($incident->id, $jobOrder->incident_id);
        $this->assertNull($jobOrder->assigned_mechanic);

        // 6. Setup mechanic attendance & assign mechanic with required parts
        $mechanic = Mechanic::create([
            'mechanic_id' => 'MECH-E2E',
            'mechanic_name' => 'Juan Dela Cruz',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);
        MechanicAttendance::create([
            'mechanic_id' => $mechanic->mechanic_id,
            'mechanic_name' => $mechanic->mechanic_name,
            'attendance_date' => today(),
            'status' => 'Present',
        ]);

        $updateJoResponse = $this->actingAs($maintenanceStaff)
            ->put(route('job-orders.update', $jobOrder), [
                'job_order_no' => $jobOrder->job_order_no,
                'bus_no' => $jobOrder->bus_no,
                'problem_issue' => $jobOrder->problem_issue,
                'maintenance_type' => 'Repair',
                'assigned_mechanic' => $mechanic->mechanic_name,
                'parts' => [
                    ['name' => 'Heavy Duty Alternator 24V', 'quantity' => 1, 'unit' => 'pcs'],
                ],
            ]);
        $updateJoResponse->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertSame($mechanic->mechanic_name, $jobOrder->assigned_mechanic);
        $this->assertSame('Not Requested', $jobOrder->part_status);
        $this->assertStringContainsString('Heavy Duty Alternator 24V', $jobOrder->part_needed);

        $attendance = MechanicAttendance::where('mechanic_name', $mechanic->mechanic_name)->latest('id')->first();
        $this->assertSame('On Duty', $attendance->status);

        // 7. Maintenance Staff creates Purchase Request from Job Order
        $createPrResponse = $this->actingAs($maintenanceStaff)
            ->post(route('job-orders.create-pr', $jobOrder));
        $createPrResponse->assertRedirect();

        $pr = PurchaseRequest::where('job_order_no', $jobOrder->job_order_no)->firstOrFail();
        $this->assertSame('Submitted', $pr->status);
        $this->assertSame('Submitted', $jobOrder->fresh()->part_status);

        // 8. Maintenance Head approves the Purchase Request
        $approvePrResponse = $this->actingAs($maintenanceHead)
            ->post(route('purchase-requests.approve', $pr));
        $approvePrResponse->assertRedirect();

        $this->assertSame('Approved', $pr->fresh()->status);
        $this->assertSame('Approved', $jobOrder->fresh()->part_status);

        // 9. Warehouse Head issues parts from stock
        $inventoryItem = InventoryItem::create([
            'item_code' => 'ALT-24V-001',
            'item_name' => 'Heavy Duty Alternator 24V',
            'category' => 'Electrical',
            'quantity_available' => 3,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
            'supplier' => 'Bosch Automotive',
            'storage_location' => 'Warehouse Shelf A-2',
        ]);

        $issueResponse = $this->actingAs($warehouseHead)
            ->post(route('part-requests.issue', $pr));
        $issueResponse->assertRedirect();

        $this->assertSame('Issued', $pr->fresh()->status);
        $this->assertSame('Issued', $jobOrder->fresh()->part_status);
        $this->assertSame(2, (int) $inventoryItem->fresh()->quantity_available);

        // 10. Maintenance completes the Job Order
        $finishResponse = $this->actingAs($maintenanceStaff)
            ->post(route('job-orders.finish', $jobOrder));
        $finishResponse->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('Completed', $jobOrder->status);
        $this->assertNotNull($jobOrder->completion_date);

        // Mechanic returned to Present status
        $attendance->refresh();
        $this->assertSame('Present', $attendance->status);

        // 11. End-to-End Traceability Validation across all modules
        // Trace from JobOrder -> Referral & Incident
        $this->assertSame($referral->id, $jobOrder->maintenance_referral_id);
        $this->assertSame($incident->id, $jobOrder->incident_id);
        $this->assertSame($referral->id, $jobOrder->maintenanceReferral->id);
        $this->assertSame($incident->id, $jobOrder->incident->id);

        // Trace from Referral -> JobOrder & Incident
        $this->assertSame($jobOrder->id, $referral->jobOrder->id);
        $this->assertSame($incident->id, $referral->incident->id);
        $this->assertSame('Job Order Created', $referral->status);

        // Trace from Incident -> Referral & Bus
        $this->assertSame($referral->id, $incident->maintenanceReferral->id);
        $this->assertSame($bus->id, $incident->bus->id);

        // Trace from PurchaseRequest -> JobOrder
        $this->assertSame($jobOrder->job_order_no, $pr->job_order_no);
        $this->assertSame('Issued', $pr->fresh()->status);
    }
}
