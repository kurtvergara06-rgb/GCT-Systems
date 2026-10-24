<?php

namespace Tests\Feature\Auth;

use App\Exceptions\EmailDeliveryException;
use App\Models\Admin\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    public function test_resend_after_cooldown_replaces_the_code_and_sets_a_new_cooldown(): void
    {
        Mail::fake();
        $user = $this->applicant();
        $this->codeFor($user, '123456');
        DB::table('email_verification_otps')->where('user_id', $user->id)->update([
            'resend_available_at' => now()->subSecond(),
        ]);
        $before = DB::table('email_verification_otps')->where('user_id', $user->id)->first();

        $this->withSession(['registration_otp_user_id' => $user->id])
            ->post(route('verification.otp.resend'))
            ->assertRedirect(route('verification.otp'))
            ->assertSessionHas('status', 'A new verification code has been sent.');

        $after = DB::table('email_verification_otps')->where('user_id', $user->id)->first();
        $this->assertNotSame($before->code_hash, $after->code_hash);
        $this->assertTrue(now()->lessThan($after->resend_available_at));
    }

    public function test_failed_brevo_resend_preserves_the_existing_valid_code(): void
    {
        config()->set('mail.default', 'brevo');
        config()->set('mail.from.address', 'verified-sender@example.com');
        config()->set('services.brevo.key', 'test-brevo-key');
        config()->set('services.brevo.endpoint', 'https://api.brevo.com/v3/smtp/email');
        Mail::purge();

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'message' => 'Temporary delivery failure.',
            ], 503),
        ]);

        $user = $this->applicant();
        $this->codeFor($user, '123456');
        DB::table('email_verification_otps')->where('user_id', $user->id)->update([
            'resend_available_at' => now()->subSecond(),
        ]);
        $before = DB::table('email_verification_otps')->where('user_id', $user->id)->first();

        try {
            app(EmailOtpService::class)->send($user, true);
            $this->fail('A failed Brevo request should stop OTP replacement.');
        } catch (EmailDeliveryException) {
            // Expected: the previous OTP must remain untouched.
        }

        $after = DB::table('email_verification_otps')->where('user_id', $user->id)->first();
        $this->assertSame($before->code_hash, $after->code_hash);
        $this->assertSame($before->expires_at, $after->expires_at);
        $this->assertSame($before->resend_available_at, $after->resend_available_at);
        $this->assertTrue(Hash::check('123456', $after->code_hash));
    }

    public function test_failed_initial_brevo_delivery_returns_quick_recovery_message(): void
    {
        config()->set('mail.default', 'brevo');
        config()->set('mail.from.address', 'verified-sender@example.com');
        config()->set('services.brevo.key', 'test-brevo-key');
        config()->set('services.brevo.endpoint', 'https://api.brevo.com/v3/smtp/email');
        Mail::purge();

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'message' => 'Temporary delivery failure.',
            ], 503),
        ]);

        $response = $this->post(route('register.submit'), [
            'name' => 'Brevo Failure Employee',
            'email' => 'brevo.failure@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertRedirect(route('verification.otp'))
            ->assertSessionHas('error', 'The verification code could not be sent. Please try Resend Code.');

        $user = User::where('email', 'brevo.failure@example.com')->firstOrFail();
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
        $this->assertSame($user->id, session('registration_otp_user_id'));
    }
}
