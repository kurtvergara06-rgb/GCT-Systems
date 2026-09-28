<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_departments_cannot_access_admin_routes(): void
    {
        $protectedRoutes = [
            'admin.dashboard',
            'admin.users',
            'admin.roles-permissions',
            'admin.activity-logs',
            'admin.notifications',
            'admin.batch-file-processing',
            'admin.import-export',
            'admin.data-history',
            'admin.settings.general',
            'admin.settings.notifications',
            'admin.settings.security',
        ];

        foreach (['Operation', 'Maintenance', 'Warehouse', 'Purchase'] as $department) {
            $user = User::factory()->create([
                'department' => $department,
                'role' => 'staff',
                'status' => 'Active',
            ]);

            foreach ($protectedRoutes as $routeName) {
                $this->actingAs($user)
                    ->get(route($routeName))
                    ->assertForbidden();
            }
        }
    }

    public function test_admin_staff_can_access_admin_department_pages(): void
    {
        $adminStaff = User::factory()->create([
            'department' => 'Admin',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $this->actingAs($adminStaff)
            ->get(route('admin.settings.general'))
            ->assertOk();
    }

    public function test_guest_is_still_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }
}
