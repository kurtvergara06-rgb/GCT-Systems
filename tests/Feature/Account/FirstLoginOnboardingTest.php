<?php

namespace Tests\Feature\Account;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstLoginOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_account_must_change_password_before_onboarding(): void
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

    public function test_temporary_password_change_redirects_new_user_to_onboarding(): void
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
            ->assertRedirect(route('onboarding.show'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertFalse($user->onboarding_completed);
    }

    public function test_incomplete_onboarding_blocks_normal_module_pages(): void
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
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Welcome to GCT')
            ->assertSee('Maintenance Referrals');
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

    public function test_forced_password_screen_explains_next_onboarding_step_and_hides_replay_link(): void
    {
        $user = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => true,
            'onboarding_completed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('First login — Step 1 of 2:')
            ->assertSee('Welcome to GCT')
            ->assertDontSee('System Tutorial');
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
}
