<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use App\Notifications\GctResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_account_receives_a_password_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['status' => 'Active']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account exists for this email, a password reset link will be sent.');

        Notification::assertSentTo($user, GctResetPasswordNotification::class);
    }

    public function test_unknown_email_receives_generic_response_without_notification(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.invalid'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account exists for this email, a password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_inactive_and_pending_accounts_do_not_receive_reset_links(): void
    {
        Notification::fake();
        foreach (['Deactivated', 'Pending'] as $status) {
            $user = User::factory()->create(['status' => $status]);

            $this->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect()
                ->assertSessionHas('status', 'If an account exists for this email, a password reset link will be sent.');
        }

        Notification::assertNothingSent();
    }

    public function test_token_created_before_deactivation_cannot_change_password(): void
    {
        $user = User::factory()->create(['status' => 'Active']);
        $originalPassword = $user->password;
        $token = Password::broker()->createToken($user);
        $user->update(['status' => 'Deactivated']);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertFalse(Hash::check('NewSecurePassword123!', $user->fresh()->password));
    }
}
