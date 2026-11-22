<?php

namespace Tests\Feature\Admin;

use App\Services\SystemServiceHealth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoEmailHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'brevo');
        config()->set('mail.mailers.brevo.transport', 'brevo');
        config()->set('services.brevo.key', 'test-brevo-api-key');
    }

    public function test_brevo_account_check_reports_online_without_sending_email(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.brevo.com/v3/account' => Http::response(['email' => 'test@example.com'], 200),
        ]);

        $this->assertSame('Online', app(SystemServiceHealth::class)->email());

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.brevo.com/v3/account'
            && $request->hasHeader('api-key', 'test-brevo-api-key'));
    }

    public function test_brevo_without_key_reports_not_verified_and_does_not_call_api(): void
    {
        config()->set('services.brevo.key', null);
        Http::fake();

        $this->assertSame('Not verified', app(SystemServiceHealth::class)->email());
        Http::assertNothingSent();
    }

    public function test_brevo_authentication_failure_reports_offline(): void
    {
        Http::fake(['https://api.brevo.com/v3/account' => Http::response([], 401)]);

        $this->assertSame('Offline', app(SystemServiceHealth::class)->email());
    }

    public function test_brevo_permission_or_rate_limit_does_not_falsely_claim_mail_is_offline(): void
    {
        foreach ([403, 429] as $status) {
            Http::fake(['https://api.brevo.com/v3/account' => Http::response([], $status)]);

            $this->assertSame('Not verified', app(SystemServiceHealth::class)->email());
        }
    }

    public function test_log_mailer_remains_disabled(): void
    {
        config()->set('mail.default', 'log');
        config()->set('mail.mailers.log.transport', 'log');
        Http::fake();

        $this->assertSame('Disabled', app(SystemServiceHealth::class)->email());
        Http::assertNothingSent();
    }
}
