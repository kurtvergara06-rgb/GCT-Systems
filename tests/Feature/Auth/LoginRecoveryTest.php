<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use App\Notifications\GctResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class LoginRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_token_and_keeps_response_generic(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recovery@example.test']);
        $this->get(route('password.request'))->assertOk()
            ->assertSee('Send Reset Link')
            ->assertDontSee('Create Account')
            ->assertDontSee('register.submit');
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, GctResetPasswordNotification::class);
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status');
    }

    public function test_reset_token_changes_password_without_activating_account(): void
    {
        $user = User::factory()->create(['status' => 'Pending']);
        $token = Password::createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()
            ->assertSee('Reset Password')
            ->assertDontSee('Create Account');
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->fresh()->password));
        $this->assertSame('Pending', $user->fresh()->status);
    }

    public function test_remember_me_sets_persistent_authentication_token(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'onboarding_completed' => true,
            'must_change_password' => false,
        ]);
        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard-operation'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
    }
}
