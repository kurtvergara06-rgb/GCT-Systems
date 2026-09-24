<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_with_full_control_can_reset_regular_user_password(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $target = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $target), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas(
                'success',
                'Password reset successfully. The user must change the temporary password at next login.'
            );

        $target->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $target->password));
        $this->assertTrue($target->must_change_password);
    }

    public function test_non_admin_user_cannot_reset_another_users_password(): void
    {
        $staff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $target = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'password' => 'OriginalPassword123!',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.users.reset-password', $target), [
                'password' => 'ChangedPassword123!',
                'password_confirmation' => 'ChangedPassword123!',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('OriginalPassword123!', $target->fresh()->password));
    }

    public function test_protected_system_admin_cannot_be_reset_from_account_management(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'password' => 'OriginalAdminPassword123!',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $admin), [
                'password' => 'ChangedAdminPassword123!',
                'password_confirmation' => 'ChangedAdminPassword123!',
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertTrue(Hash::check('OriginalAdminPassword123!', $admin->fresh()->password));
    }

    public function test_admin_reset_requires_at_least_eight_characters(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $target = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'password' => 'OriginalPassword123!',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users'))
            ->post(route('admin.users.reset-password', $target), [
                'password' => 'short7',
                'password_confirmation' => 'short7',
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('OriginalPassword123!', $target->fresh()->password));
        $this->assertFalse($target->fresh()->must_change_password);
    }
}
