<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_permission_update_cannot_grant_cross_module_access(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                '_permission_update' => '1',
                'role_key' => 'warehouse_head',
                'permissions' => [
                    'warehouse' => [
                        'view' => '1',
                        'edit' => '1',
                        'approve' => '1',
                    ],
                    'analytics' => [
                        'view' => '1',
                        'analyze' => '1',
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

        foreach (['operation', 'maintenance', 'purchase', 'analytics', 'administration'] as $module) {
            foreach ((array) data_get($role->permissions, $module, []) as $allowed) {
                $this->assertFalse((bool) $allowed);
            }
        }
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
            ->assertSee('Warehouse Module Access')
            ->assertSee('Module Access Restriction')
            ->assertSee('3 of 3 Allowed')
            ->assertDontSee('System-Wide Access');

        $this->actingAs($admin)
            ->get(route('admin.roles-permissions', ['role' => 'admin_head']))
            ->assertOk()
            ->assertSee('System-Wide Access')
            ->assertSee('Full System Access')
            ->assertSee('Protected Role');
    }
}
