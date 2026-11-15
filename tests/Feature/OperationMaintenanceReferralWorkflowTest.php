<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Operation\Incident;
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
}
