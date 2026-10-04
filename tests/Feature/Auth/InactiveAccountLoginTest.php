<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InactiveAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_account_is_blocked_before_authentication_and_receives_modal_notice(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive.staff@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Purchase',
            'role' => 'staff',
            'status' => 'Inactive',
            'remember_token' => 'unchanged-token',
            'last_login_at' => null,
        ]);

        $response = $this
            ->from(route('login'))
            ->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'WrongPasswordStillBlocked!',
                'remember' => '1',
            ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('blocked_account_status', 'Inactive');

        $this->assertGuest();

        $user->refresh();

        $this->assertNull($user->last_login_at);
        $this->assertSame('unchanged-token', $user->remember_token);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Account Deactivated')
            ->assertSee('This account has been deactivated and cannot sign in.')
            ->assertSee('data-account-status-modal', false);
    }

    public function test_inactive_account_notice_takes_precedence_over_login_rate_limit(): void
    {
        $user = User::factory()->create([
            'email' => 'blocked.rate.limit@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Inactive',
        ]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this
            ->from(route('login'))
            ->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'still-wrong',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('blocked_account_status', 'Inactive')
            ->assertSessionDoesntHaveErrors();

        $this->assertGuest();
    }

    public function test_pending_account_receives_pending_activation_notice(): void
    {
        $user = User::factory()->create([
            'email' => 'pending.staff@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Pending',
        ]);

        $this
            ->from(route('login'))
            ->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'Password123!',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('blocked_account_status', 'Pending');

        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Account Pending Activation');
    }

    public function test_active_account_login_still_works(): void
    {
        $user = User::factory()->create([
            'email' => 'active.staff@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Purchase',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('purchase-orders'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_authenticated_user_is_logged_out_and_gets_same_notice(): void
    {
        $user = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Inactive',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.users'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('blocked_account_status', 'Inactive');

        $this->assertGuest();
    }
}
