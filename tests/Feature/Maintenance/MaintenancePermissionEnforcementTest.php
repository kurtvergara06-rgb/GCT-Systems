<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Operation\Incident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function permissions(bool $view, bool $edit, bool $approve): void
    {
        $role = RolePermission::where('role_key', 'maintenance_staff')->firstOrFail();
        $permissions = $role->permissions ?? [];
        data_set($permissions, 'maintenance.view', $view);
        data_set($permissions, 'maintenance.edit', $edit);
        data_set($permissions, 'maintenance.approve', $approve);
        $role->update(['permissions' => $permissions]);
    }

    private function pendingReferral(User $user, string $suffix): MaintenanceReferral
    {
        $bus = Bus::create([
            'bus_no' => "BUS-PERM-{$suffix}",
            'status' => 'Active',
        ]);
        $incident = Incident::create([
            'incident_no' => "INC-PERM-{$suffix}",
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Permission Test',
            'description' => 'Permission enforcement test referral.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $user->id,
        ]);

        return MaintenanceReferral::create([
            'incident_id' => $incident->id,
            'status' => 'Pending',
            'referred_by' => $user->id,
        ]);
    }

    public function test_view_permission_blocks_maintenance_pages(): void
    {
        $staff = $this->staff();
        $this->permissions(false, true, true);

        $this->actingAs($staff)->get(route('maintenance-dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('job-orders'))->assertForbidden();
        $this->actingAs($staff)->get(route('PMS-Scheduling'))->assertForbidden();
        $this->actingAs($staff)->get(route('fuel-reports'))->assertForbidden();
        $this->actingAs($staff)->get(route('maintenance-referrals'))->assertForbidden();
    }

    public function test_edit_permission_blocks_create_update_delete_and_workflow_mutations(): void
    {
        $staff = $this->staff();
        $this->permissions(true, false, false);
        $bus = Bus::create([
            'bus_no' => 'BUS-PERM-EDIT',
            'status' => 'Under Maintenance',
        ]);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-PERM-EDIT',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Permission test repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'part_status' => 'No Parts Required',
        ]);
        $referral = $this->pendingReferral($staff, 'REFEDIT');
        $referral->update(['status' => 'Approved']);

        $this->actingAs($staff)->post(route('job-orders.store'))->assertForbidden();
        $this->actingAs($staff)->put(route('job-orders.update', $jobOrder))->assertForbidden();
        $this->actingAs($staff)->delete(route('job-orders.destroy', $jobOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('pms-schedules.store'))->assertForbidden();
        $this->actingAs($staff)->post(route('fuel-reports.store'))->assertForbidden();
        $this->actingAs($staff)->post(route('maintenance-referrals.job-order.store', $referral))->assertForbidden();

        $this->assertDatabaseHas('job_orders', ['id' => $jobOrder->id]);
        $this->assertSame('Active', $referral->incident->bus->fresh()->status);
    }

    public function test_referral_review_and_ui_follow_approve_permission_instead_of_role_name(): void
    {
        $staff = $this->staff();
        $referral = $this->pendingReferral($staff, 'APPROVE');
        $this->permissions(true, true, false);

        $this->actingAs($staff)
            ->get(route('maintenance-referrals'))
            ->assertOk()
            ->assertDontSee('title="Approve Referral"', false);

        $this->actingAs($staff)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertForbidden();

        $this->assertSame('Pending', $referral->fresh()->status);

        $this->permissions(true, true, true);

        $this->actingAs($staff)
            ->get(route('maintenance-referrals'))
            ->assertOk()
            ->assertSee('title="Approve Referral"', false);

        $this->actingAs($staff)
            ->post(route('maintenance-referrals.approve', $referral))
            ->assertRedirect();

        $this->assertSame('Approved', $referral->fresh()->status);
    }
}
