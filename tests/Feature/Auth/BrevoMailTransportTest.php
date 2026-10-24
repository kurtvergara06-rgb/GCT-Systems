<?php

namespace Tests\Feature\Auth;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BrevoMailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mail.default', 'brevo');
        config()->set('mail.from.address', 'verified-sender@example.com');
        config()->set('mail.from.name', 'GCT Transport Services');
        config()->set('services.brevo.key', 'test-brevo-key');
        config()->set('services.brevo.endpoint', 'https://api.brevo.com/v3/smtp/email');
        config()->set('services.brevo.timeout', 10);
        config()->set('services.brevo.connect_timeout', 3);
        Mail::purge();
    }

    protected function tearDown(): void
    {
        Mail::purge();

        parent::tearDown();
    }

    public function test_brevo_transport_sends_mail_through_the_https_api(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<brevo-message-id@example.com>',
            ], 201),
        ]);

        Mail::raw('Your verification code is 123456.', function ($message): void {
            $message->to('employee@example.com', 'Test Employee')
                ->subject('GCT-Systems email verification code');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && $request['sender'] === [
                    'email' => 'verified-sender@example.com',
                    'name' => 'GCT Transport Services',
                ]
                && $request['to'] === [[
                    'email' => 'employee@example.com',
                    'name' => 'Test Employee',
                ]]
                && $request['subject'] === 'GCT-Systems email verification code'
                && $request['textContent'] === 'Your verification code is 123456.';
        });
    }

    public function test_brevo_api_failure_becomes_a_mail_transport_exception(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'message' => 'Sender is not verified.',
            ], 400),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo email delivery failed with HTTP 400');

        Mail::raw('Your verification code is 123456.', function ($message): void {
            $message->to('employee@example.com')
                ->subject('GCT-Systems email verification code');
        });
    }
}
