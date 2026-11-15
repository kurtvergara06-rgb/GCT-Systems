<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GoogleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function googleSession(): array
    {
        return ['google_registration_pending' => [
            'google_id' => 'google-test-subject',
            'email' => 'applicant@example.com',
            'name' => 'Google Display Name',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]];
    }

    public function test_new_google_employee_can_enter_full_name_then_receives_otp(): void
    {
        Mail::fake();

        $this->withSession($this->googleSession())
            ->get(route('auth.google.register'))
            ->assertOk()
            ->assertSee('Complete Your Registration')
            ->assertSee('applicant@example.com');

        $this->withSession($this->googleSession())
            ->post(route('auth.google.register.submit'), ['name' => 'Official Employee Name', 'password' => 'SecureGCTPassword#2026', 'password_confirmation' => 'SecureGCTPassword#2026'])
            ->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'applicant@example.com',
            'name' => 'Official Employee Name',
            'status' => 'Pending',
            'google_id' => 'google-test-subject',
            'email_verified_at' => null,
        ]);
        $this->assertTrue(Hash::check('SecureGCTPassword#2026', User::where('email', 'applicant@example.com')->firstOrFail()->password));
        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => User::where('email', 'applicant@example.com')->firstOrFail()->id,
        ]);
    }

    public function test_google_registration_requires_full_name(): void
    {
        $this->withSession($this->googleSession())
            ->post(route('auth.google.register.submit'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    }

    public function test_google_registration_rejects_mismatched_passwords(): void
    {
        $this->withSession($this->googleSession())
            ->post(route('auth.google.register.submit'), [
                'name' => 'Official Employee Name',
                'password' => 'SecureGCTPassword#2026',
                'password_confirmation' => 'DifferentPassword#2026',
            ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    }

    public function test_google_registration_does_not_accept_direct_access_without_google_session(): void
    {
        $this->get(route('auth.google.register'))->assertRedirect(route('login'));
        $this->post(route('auth.google.register.submit'), ['name' => 'Fake Name'])
            ->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    }

    public function test_existing_email_cannot_be_registered_again(): void
    {
        User::factory()->create(['email' => 'applicant@example.com']);
        $this->withSession($this->googleSession())
            ->post(route('auth.google.register.submit'), ['name' => 'Another Name', 'password' => 'SecureGCTPassword#2026', 'password_confirmation' => 'SecureGCTPassword#2026'])
            ->assertRedirect(route('login'));
        $this->assertSame(1, User::where('email', 'applicant@example.com')->count());
    }

    public function test_brevo_failure_does_not_turn_google_registration_into_a_gateway_timeout(): void
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

        $this->withSession($this->googleSession())
            ->post(route('auth.google.register.submit'), [
                'name' => 'Official Employee Name',
                'password' => 'SecureGCTPassword#2026',
                'password_confirmation' => 'SecureGCTPassword#2026',
            ])
            ->assertRedirect(route('verification.otp'))
            ->assertSessionHas('error', 'The verification code could not be sent. Please try Resend Code.');

        $user = User::where('email', 'applicant@example.com')->firstOrFail();
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
    }
}
