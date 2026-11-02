<?php

namespace Tests\Feature\Account;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstLoginOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registered_password_does_not_require_first_login_change(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'password' => Hash::make('ChosenPassword123!'),
            'must_change_password' => false,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'ChosenPassword123!',
        ])->assertRedirect(route('maintenance-dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_temporary_password_user_is_redirected_to_account_settings(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'password' => Hash::make('Temporary123!'),
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertRedirect(route('account.settings'));
    }

    public function test_temporary_password_change_returns_to_account_settings(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'password' => Hash::make('Temporary123!'),
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Temporary123!',
                'password' => 'PrivatePassword456!',
                'password_confirmation' => 'PrivatePassword456!',
            ])
            ->assertRedirect(route('account.settings'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertFalse($user->onboarding_completed);
        $this->assertTrue(Hash::check('PrivatePassword456!', $user->password));
    }

    public function test_password_required_user_cannot_access_module_pages(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('maintenance-dashboard'))
            ->assertRedirect(route('account.settings'));
    }

    public function test_incomplete_optional_tutorial_does_not_block_module_pages(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('maintenance-dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Welcome to GCT')
            ->assertSee('Maintenance Referrals');
    }

    public function test_onboarding_profile_cannot_change_verified_email(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'email' => 'verified@gct.example',
            'email_verified_at' => now(),
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('readonly aria-readonly="true"', false);

        $this->actingAs($user)->put(route('onboarding.profile.update'), [
            'name' => 'Updated Employee',
            'email' => 'attacker@example.com',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertSame('Updated Employee', $user->fresh()->name);
        $this->assertSame('verified@gct.example', $user->fresh()->email);
    }

    public function test_finishing_onboarding_persists_completion_and_redirects_to_department_dashboard(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('maintenance-dashboard'));

        $user->refresh();
        $this->assertTrue($user->onboarding_completed);
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_completed_user_can_open_tutorial_from_account_settings(): void
    {
        $user = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('System Tutorial')
            ->assertSee(route('onboarding.show', ['replay' => 1]), false);
    }

    public function test_completed_user_can_replay_tutorial_without_resetting_state(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.show', ['replay' => 1]))
            ->assertOk()
            ->assertSee('Tutorial Replay')
            ->assertSee('Daily Driver Reports');

        $this->assertTrue($user->fresh()->onboarding_completed);
    }

    public function test_authenticated_completed_user_cannot_return_to_login_page(): void
    {
        $user = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('maintenance-dashboard'));
    }

    public function test_authenticated_users_follow_password_and_department_routing(): void
    {
        $passwordUser = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($passwordUser)
            ->get(route('login'))
            ->assertRedirect(route('account.settings'));

        $onboardingUser = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($onboardingUser)
            ->get(route('login'))
            ->assertRedirect(route('dashboard-operation'));
    }

    public function test_temporary_password_login_submission_opens_account_settings(): void
    {
        User::factory()->create([
            'email' => 'new.user@gct.test',
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'password' => Hash::make('Temporary123!'),
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->post(route('login.submit'), [
            'email' => 'new.user@gct.test',
            'password' => 'Temporary123!',
        ])->assertRedirect(route('account.settings'));
    }

    public function test_guest_login_page_is_not_cached(): void
    {
        $response = $this->get(route('login'))
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
