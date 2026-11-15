<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_is_safe_when_oauth_is_not_configured(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect', null);

        $this->get(route('auth.google'))->assertRedirect(route('login'));
    }

    public function test_google_callback_rejects_missing_state(): void
    {
        config()->set('services.google.client_id', 'client');
        config()->set('services.google.client_secret', 'secret');
        config()->set('services.google.redirect', route('auth.google.callback'));

        Http::fake();
        $this->get(route('auth.google.callback', ['code' => 'fake', 'state' => 'forged']))
            ->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_google_existing_email_is_not_auto_linked_to_privileged_account(): void
    {
        config()->set('services.google.client_id', 'client');
        config()->set('services.google.client_secret', 'secret');
        config()->set('services.google.redirect', route('auth.google.callback'));
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'department' => 'admin',
            'role' => 'head',
        ]);

        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock-access'], 200),
            '*openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-subject-123',
                'email' => 'existing@example.test',
                'email_verified' => true,
                'name' => 'Existing Account',
            ], 200),
        ]);

        $this->withSession([
            'google_oauth_state' => 'expected-state',
            'google_oauth_verifier' => 'valid-verifier',
        ])->get(route('auth.google.callback', ['state' => 'expected-state', 'code' => 'test-code']))
            ->assertRedirect(route('login'));

        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
        $this->assertSame(1, User::count());
    }
}
