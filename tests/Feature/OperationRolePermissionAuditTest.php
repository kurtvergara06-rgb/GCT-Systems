<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OperationRolePermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_view_permission_protects_every_primary_operation_page(): void
    {
        $staff = $this->operationUser('staff');
        $this->setPermissions($staff, view: false, edit: false, approve: false);

        $routes = [
            'dashboard-operation',
            'bus-master-list',
            'driver-attendance',
            'mechanic-attendance',
            'operation.routes',
            'operation.personnel.drivers',
            'operation.personnel.mechanics',
            'trip-schedule',
            'driver-bus-assignment',
            'auto-scheduling',
            'trip-records',
            'daily-driver-reports',
            'incidents',
            'bus-availability',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($staff)->get(route($routeName))->assertForbidden();
        }

        $this->actingAs($staff)
            ->getJson(route('operation.attendance.batch.roster', ['type' => 'driver']))
            ->assertForbidden();
    }

    public function test_disabling_edit_hides_operation_mutation_controls_for_heads_and_staff(): void
    {
        $head = $this->operationUser('head');
        $this->setPermissions($head, view: true, edit: false, approve: true);

        $this->actingAs($head)->get(route('operation.routes'))
            ->assertOk()
            ->assertDontSee('id="openRouteModal"', false);

        $this->actingAs($head)->get(route('driver-attendance'))
            ->assertOk()
            ->assertDontSee('data-batch-attendance-open', false)
            ->assertDontSee('open-edit-driver-attendance-modal', false)
            ->assertDontSee('id="driverAttendanceModal"', false);

        $this->actingAs($head)->get(route('mechanic-attendance'))
            ->assertOk()
            ->assertDontSee('data-batch-attendance-open', false)
            ->assertDontSee('open-edit-attendance-modal', false)
            ->assertDontSee('id="editMechanicAttendanceModal"', false);

        $this->actingAs($head)->get(route('incidents'))
            ->assertOk()
            ->assertDontSee('id="openIncidentReportModal"', false)
            ->assertDontSee('id="incidentReportModal"', false);

        $this->actingAs($head)->get(route('daily-driver-reports'))
            ->assertOk()
            ->assertDontSee('id="openEncodeReportModal"', false)
            ->assertDontSee('id="ddrEncodeModal"', false);

        $this->actingAs($head)->post(route('driver-attendance.store'), [])->assertForbidden();
        $this->actingAs($head)->post(route('mechanic-attendance.store'), [])->assertForbidden();
        $this->actingAs($head)->post(route('incidents.store'), [])->assertForbidden();
        $this->actingAs($head)->post(route('daily-driver-reports.store'), [])->assertForbidden();
    }

    public function test_approve_controls_final_confirmation_and_reserve_bus_authority(): void
    {
        $head = $this->operationUser('head');
        $this->setPermissions($head, view: true, edit: true, approve: false);

        $this->assertFalse($head->canAssignReserveBus());
        $this->actingAs($head)->get(route('auto-scheduling'))
            ->assertOk()
            ->assertSee('id="saveAutoScheduleDraftButton"', false)
            ->assertDontSee('id="confirmScheduleButton"', false);
        $this->actingAs($head)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [],
        ])->assertForbidden();

        $this->setPermissions($head, view: true, edit: false, approve: true);
        $this->assertFalse($head->canAssignReserveBus());
        $this->actingAs($head)->get(route('auto-scheduling'))
            ->assertOk()
            ->assertDontSee('id="saveAutoScheduleDraftButton"', false)
            ->assertDontSee('id="confirmScheduleButton"', false);
        $this->actingAs($head)->postJson(route('auto-scheduling.confirm'), [
            'recommendations' => [],
        ])->assertForbidden();

        $this->setPermissions($head, view: true, edit: true, approve: true);
        $this->assertTrue($head->canAssignReserveBus());
    }

    public function test_admin_save_takes_effect_on_the_very_next_operation_request(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $staff = $this->operationUser('staff');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            '_permission_update' => '1',
            'role_key' => 'operation_staff',
            'permissions' => [
                'operation' => ['view' => '1', 'edit' => '0', 'approve' => '0'],
            ],
        ])->assertRedirect(route('admin.roles-permissions', ['role' => 'operation_staff']));

        $this->actingAs($staff)->get(route('driver-attendance'))
            ->assertOk()
            ->assertDontSee('data-batch-attendance-open', false);
        $this->actingAs($staff)->post(route('driver-attendance.store'), [])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            '_permission_update' => '1',
            'role_key' => 'operation_staff',
            'permissions' => [
                'operation' => ['view' => '0', 'edit' => '0', 'approve' => '0'],
            ],
        ]);

        $this->actingAs($staff)->get(route('driver-attendance'))->assertForbidden();
    }

    public function test_operation_mutation_routes_have_the_expected_capability_middleware(): void
    {
        $editRoutes = [
            'bus-master-list.store', 'bus-master-list.update', 'bus-master-list.deactivate',
            'bus-master-list.activate', 'bus-master-list.destroy', 'driver-attendance.store',
            'driver-attendance.import', 'driver-attendance.update', 'driver-attendance.destroy',
            'operation.routes.store', 'operation.routes.update', 'operation.routes.destroy',
            'trip-schedule.store', 'trip-schedule.update', 'trip-schedule.destroy',
            'external-bus-rentals.store', 'external-bus-rentals.assign',
            'driver-bus-assignment.store', 'driver-bus-assignment.update', 'driver-bus-assignment.destroy',
            'auto-scheduling.draft.save', 'auto-scheduling.draft.delete',
            'auto-scheduling.edit-preview', 'auto-scheduling.resolve',
            'daily-driver-reports.store', 'daily-driver-reports.update', 'daily-driver-reports.destroy',
            'incidents.maintenance-referral.store', 'incidents.store', 'incidents.details.update',
            'incidents.destroy', 'incidents.update', 'incidents.dispatch', 'incidents.response',
            'mechanic-attendance.store', 'mechanic-attendance.update',
            'mechanic-attendance.destroy', 'mechanic-attendance.import',
            'operation.attendance.batch.store',
            'operation.personnel.drivers.store', 'operation.personnel.drivers.update',
            'operation.personnel.drivers.deactivate', 'operation.personnel.mechanics.store',
            'operation.personnel.mechanics.update', 'operation.personnel.mechanics.deactivate',
            'operation.personnel.mechanics.activate',
        ];

        foreach ($editRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Missing route {$routeName}");
            $this->assertContains(
                'system.permission:operation,edit',
                $route->gatherMiddleware(),
                "Route {$routeName} is not protected by Operation Edit"
            );
        }

        $this->assertContains(
            'system.permission:operation,approve',
            Route::getRoutes()->getByName('auto-scheduling.confirm')->gatherMiddleware()
        );
        $this->assertContains(
            'system.permission:operation,edit',
            Route::getRoutes()->getByName('auto-scheduling.confirm')->gatherMiddleware()
        );
    }

    private function operationUser(string $role): User
    {
        return User::factory()->create([
            'department' => 'Operation',
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function setPermissions(
        User $user,
        bool $view,
        bool $edit,
        bool $approve
    ): void {
        $rolePermission = RolePermission::where('role_key', $user->permissionRoleKey())->firstOrFail();
        $permissions = $rolePermission->permissions;
        data_set($permissions, 'operation.view', $view);
        data_set($permissions, 'operation.edit', $edit);
        data_set($permissions, 'operation.approve', $approve);
        $rolePermission->update(['permissions' => $permissions]);
    }
}
