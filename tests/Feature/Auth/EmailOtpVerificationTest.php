<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): User
    {
        return User::factory()->create([
            'department' => 'Unassigned', 'role' => 'unassigned',
            'status' => 'Pending', 'email_verified_at' => null,
        ]);
    }

    private function codeFor(User $user, string $code = '123456', int $minutes = 5): void
    {
        DB::table('email_verification_otps')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($minutes),
            'resend_available_at' => now()->addSeconds(60),
            'failed_attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_registration_sends_email_and_redirects_to_otp(): void
    {
        Mail::fake();
        $this->post(route('register.submit'), [
            'name' => 'OTP Test Employee', 'email' => 'otp.test@example.com',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('verification.otp'));

        $user = User::where('email', 'otp.test@example.com')->firstOrFail();
        $this->assertSame('Pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);
    }

    public function test_correct_code_verifies_but_does_not_activate_account(): void
    {
        $user = $this->applicant();
        $this->codeFor($user);
        $this->withSession(['registration_otp_user_id' => $user->id])
            ->post(route('verification.otp.submit'), ['code' => '123456'])
            ->assertRedirect(route('registration.pending'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('Pending', $user->fresh()->status);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
    }

    public function test_wrong_and_expired_codes_do_not_verify(): void
    {
        $user = $this->applicant();
        $this->codeFor($user, '123456', -1);
        $this->withSession(['registration_otp_user_id' => $user->id])
            ->post(route('verification.otp.submit'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_five_wrong_attempts_block_the_code(): void
    {
        $user = $this->applicant();
        $this->codeFor($user);
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['registration_otp_user_id' => $user->id])
                ->post(route('verification.otp.submit'), ['code' => '999999'])->assertSessionHasErrors('code');
        }
        $this->withSession(['registration_otp_user_id' => $user->id])
            ->post(route('verification.otp.submit'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_respects_cooldown_and_session_is_required(): void
    {
        Mail::fake();
        $user = $this->applicant();
        $this->codeFor($user);
        $this->post(route('verification.otp.resend'))->assertForbidden();
        $this->withSession(['registration_otp_user_id' => $user->id])
            ->post(route('verification.otp.resend'))->assertSessionHasErrors('code');
    }
}
