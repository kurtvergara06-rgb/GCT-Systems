<?php

namespace Tests\Feature\Account;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequiredPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagged_user_is_redirected_to_security_settings_before_accessing_app(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard-operation'))
            ->assertRedirect(route('account.settings'));
    }

    public function test_flagged_user_can_access_security_settings(): void
    {
        $user = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk();
    }

    public function test_successful_password_change_clears_required_change_flag(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'password' => 'Temporary123!',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Temporary123!',
                'password' => 'PrivatePassword456!',
                'password_confirmation' => 'PrivatePassword456!',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('success', 'Password updated successfully.');

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('PrivatePassword456!', $user->password));
    }

    public function test_incorrect_temporary_password_keeps_required_change_flag(): void
    {
        $user = User::factory()->create([
            'department' => 'Purchase',
            'role' => 'staff',
            'status' => 'Active',
            'password' => 'Temporary123!',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->put(route('account.password.update'), [
                'current_password' => 'WrongPassword123!',
                'password' => 'PrivatePassword456!',
                'password_confirmation' => 'PrivatePassword456!',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
