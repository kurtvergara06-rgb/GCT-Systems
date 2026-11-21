<?php

namespace Tests\Feature\Admin;

use App\Events\SystemDataUpdated;
use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IsolatedRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function systemAdmin(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    public function test_department_roles_are_seeded_with_only_their_own_module_access(): void
    {
        foreach (['operation', 'maintenance', 'purchase', 'warehouse'] as $module) {
            foreach (['head', 'staff'] as $roleType) {
                $role = RolePermission::where('role_key', "{$module}_{$roleType}")->firstOrFail();

                foreach (['operation', 'maintenance', 'purchase', 'warehouse', 'analytics', 'administration'] as $candidate) {
                    $capabilities = (array) data_get($role->permissions, $candidate, []);

                    if ($candidate === $module) {
                        $this->assertTrue((bool) ($capabilities['view'] ?? false));
                        $this->assertTrue((bool) ($capabilities['edit'] ?? false));

                        // Heads retain separately revocable approval authority;
                        // Staff never inherit it, including in Warehouse.
                        $this->assertSame(
                            $roleType === 'head',
                            (bool) ($capabilities['approve'] ?? false)
                        );

                        continue;
                    }

                    foreach ($capabilities as $allowed) {
                        $this->assertFalse((bool) $allowed);
                    }
                }
            }
        }
    }

    public function test_system_admin_has_full_access_to_every_permission_capability(): void
    {
        $role = RolePermission::where('role_key', 'admin_head')->firstOrFail();

        foreach ((array) $role->permissions as $capabilities) {
            foreach ((array) $capabilities as $allowed) {
                $this->assertTrue((bool) $allowed);
            }
        }
    }

    public function test_account_creation_requires_administration_full_control(): void
    {
        $payload = [
            'name' => 'Unauthorized Created User',
            'email' => 'unauthorized-create@gct.test',
            'password' => 'TemporaryPass123!',
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ];

        $staff = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.users.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

        $this->actingAs($this->systemAdmin())
            ->post(route('admin.users.store'), $payload)
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['email' => $payload['email']]);
    }

    public function test_permission_update_cannot_grant_cross_module_access(): void
    {
        Event::fake([SystemDataUpdated::class]);

        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->put(route('admin.roles-permissions.update', ['rolePermission' => 'warehouse_head']), [
                'permissions' => [
                    'warehouse' => [
                        'view' => '1',
                        'edit' => '1',
                        'approve' => '1',
                    ],
                    'analytics' => [
                        'view' => '1',
                        'diagnostic' => '1',
                        'predictive' => '1',
                        'recommendations' => '1',
                    ],
                    'administration' => [
                        'view' => '1',
                        'manage' => '1',
                        'full_control' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.roles-permissions', ['role' => 'warehouse_head']));

        $role = RolePermission::where('role_key', 'warehouse_head')->firstOrFail();

        $this->assertTrue((bool) data_get($role->permissions, 'warehouse.view'));
        $this->assertTrue((bool) data_get($role->permissions, 'warehouse.edit'));
        $this->assertTrue((bool) data_get($role->permissions, 'warehouse.approve'));

        foreach (['operation', 'maintenance', 'purchase', 'administration'] as $module) {
            foreach ((array) data_get($role->permissions, $module, []) as $allowed) {
                $this->assertFalse((bool) $allowed);
            }
        }

        // Analytics is explicitly shareable while operational modules remain isolated.
        $this->assertTrue((bool) data_get($role->permissions, 'analytics.view'));
        $this->assertTrue((bool) data_get($role->permissions, 'analytics.diagnostic'));
        $this->assertTrue((bool) data_get($role->permissions, 'analytics.predictive'));
        $this->assertTrue((bool) data_get($role->permissions, 'analytics.recommendations'));

        Event::assertDispatched(
            SystemDataUpdated::class,
            fn (SystemDataUpdated $event) =>
                $event->module === 'Warehouse'
                && $event->entity === 'RolePermission'
                && $event->action === 'permissions_updated'
                && $event->record_id === 'warehouse_head'
        );
    }

    public function test_only_full_control_admin_can_update_roles_and_protected_role_is_forbidden(): void
    {
        $payload = ['permissions' => ['warehouse' => ['view' => '1']]];

        $staff = User::factory()->create([
            'department' => 'Admin', 'role' => 'staff', 'status' => 'Active',
            'must_change_password' => false, 'onboarding_completed' => true,
        ]);
        $this->actingAs($staff)
            ->put(route('admin.roles-permissions.update', ['rolePermission' => 'warehouse_head']), $payload)
            ->assertForbidden();

        $this->actingAs($this->systemAdmin())
            ->put(route('admin.roles-permissions.update', ['rolePermission' => 'admin_head']), $payload)
            ->assertForbidden();
    }

    public function test_permissions_can_no_longer_be_updated_through_account_creation(): void
    {
        $before = RolePermission::where('role_key', 'warehouse_head')->firstOrFail()->permissions;
        $this->actingAs($this->systemAdmin())
            ->post(route('admin.users.store'), [
                '_permission_update' => '1',
                'role_key' => 'warehouse_head',
                'permissions' => ['warehouse' => ['approve' => '1']],
            ])
            ->assertSessionHasErrors(['name', 'email', 'password', 'department', 'role', 'status']);
        $this->assertSame($before, RolePermission::where('role_key', 'warehouse_head')->firstOrFail()->permissions);
    }

    public function test_department_accounts_cannot_open_other_department_modules_by_url(): void
    {
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($warehouseHead)
            ->get(route('dashboard-purchase'))
            ->assertForbidden();

        $this->actingAs($warehouseHead)
            ->get(route('dashboard-operation'))
            ->assertForbidden();

        $maintenanceStaff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($maintenanceStaff)
            ->get(route('warehouse.dashboard'))
            ->assertForbidden();
    }

    public function test_system_admin_can_cross_department_module_boundaries(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->get(route('warehouse.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard-purchase'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard-operation'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('analytics.fleet-trip'))
            ->assertRedirect('/analytics/descriptive?domain=fleet-trip');
    }

    public function test_non_admin_department_cannot_open_analytics_routes(): void
    {
        $warehouseHead = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($warehouseHead)
            ->get(route('analytics.fleet-trip'))
            ->assertForbidden();
    }

    public function test_roles_permissions_page_shows_isolated_department_layout_and_full_admin_layout(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->get(route('admin.roles-permissions', ['role' => 'warehouse_head']))
            ->assertOk()
            ->assertSee('Department Permissions')
            ->assertSee('Other departments remain isolated')
            ->assertSee('permissions[warehouse][view]', false)
            ->assertSee('permissions[analytics][view]', false);

        $this->actingAs($admin)
            ->get(route('admin.roles-permissions', ['role' => 'admin_head']))
            ->assertOk()
            ->assertSee('System-Wide Access')
            ->assertSee('Full System Access')
            ->assertSee('Protected Role');
    }

    public function test_roles_permissions_controls_reinitialize_after_partial_navigation(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->get(route('admin.roles-permissions', ['role' => 'operation_head']))
            ->assertOk()
            ->assertSee('permissions[operation][view]', false)
            ->assertSee('permissions[operation][edit]', false)
            ->assertSee('permissions[operation][approve]', false);

        $source = file_get_contents(resource_path('js/Admin/User_Management/permissions.js'));
        $view = file_get_contents(resource_path('views/Admin/User_Management/permissions.blade.php'));
        $this->assertStringContainsString('resources/js/Admin/User_Management/permissions.js', $view);
        $this->assertStringContainsString("registerInitializer(\n    'admin-role-permissions'", $source);
        $this->assertStringContainsString("'.permissions-page'", $source);
        $this->assertStringNotContainsString('DOMContentLoaded', $source);
    }

    public function test_department_descriptive_analytics_reuses_admin_route_with_scoped_content(): void
    {
        $head = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $role = RolePermission::where('role_key', 'maintenance_head')->firstOrFail();
        $permissions = $role->permissions;
        data_set($permissions, 'analytics.view', false);
        $role->update(['permissions' => $permissions]);

        $this->actingAs($head)->get(route('analytics.descriptive'))->assertForbidden();

        data_set($permissions, 'analytics.view', true);
        $role->update(['permissions' => $permissions]);

        $this->actingAs($head)->get(route('analytics.descriptive'))
            ->assertOk()
            ->assertViewIs('Admin.Analytics.descriptive.layout')
            ->assertViewHas('departmentScoped', true)
            ->assertSee('Total Job Orders')->assertSee('Monthly Fuel Consumption')
            ->assertDontSee('Operational and administrative summaries from recorded system activity');

        $this->actingAs($head)->get(route('analytics.department.stage', ['stage' => 'descriptive']))
            ->assertRedirect(route('analytics.descriptive', [
                'from' => now()->startOfMonth()->subMonths(5)->toDateString(),
                'to' => now()->toDateString(),
            ]));

        // View alone never grants Analyze or Recommendations access.
        $this->actingAs($head)->get(route('analytics.stage', ['stage' => 'diagnostic']))
            ->assertForbidden();
    }

    public function test_diagnostic_and_predictive_access_can_be_granted_independently(): void
    {
        $head = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $role = RolePermission::where('role_key', 'maintenance_head')->firstOrFail();
        $permissions = $role->permissions;
        data_set($permissions, 'analytics.view', true);
        data_set($permissions, 'analytics.diagnostic', true);
        data_set($permissions, 'analytics.predictive', false);
        data_set($permissions, 'analytics.recommendations', false);
        $role->update(['permissions' => $permissions]);

        $this->actingAs($head);
        $this->assertTrue($head->hasSystemPermission('analytics', 'diagnostic'));
        $this->assertFalse($head->hasSystemPermission('analytics', 'predictive'));
        $this->get(route('analytics.stage', ['stage' => 'diagnostic']))->assertOk();
        $this->get(route('analytics.stage', ['stage' => 'predictive']))->assertForbidden();

        data_set($permissions, 'analytics.diagnostic', false);
        data_set($permissions, 'analytics.predictive', true);
        $role->update(['permissions' => $permissions]);

        $this->assertFalse($head->hasSystemPermission('analytics', 'diagnostic'));
        $this->assertTrue($head->hasSystemPermission('analytics', 'predictive'));
        $this->get(route('analytics.stage', ['stage' => 'diagnostic']))->assertForbidden();
        $this->get(route('analytics.stage', ['stage' => 'predictive']))->assertOk();
    }

    public function test_system_admin_descriptive_analytics_still_uses_original_unrestricted_view(): void
    {
        $this->actingAs($this->systemAdmin())
            ->get(route('analytics.descriptive'))
            ->assertOk()
            ->assertViewIs('Admin.Analytics.descriptive.layout')
            ->assertSee('Operational and administrative summaries from recorded system activity');
    }
}
