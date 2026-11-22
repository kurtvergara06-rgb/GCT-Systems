<?php

namespace Tests\Feature\Admin;

use App\Services\SystemServiceHealth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

class BrevoEmailHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'brevo');
        config()->set('mail.mailers.brevo.transport', 'brevo');
        config()->set('services.brevo.key', 'test-brevo-api-key');
        Http::preventStrayRequests();
    }

    public function test_transactional_key_checks_read_only_statistics_without_account_access_or_sending(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertSame('GET', $request->method());
            $this->assertSame('https://api.brevo.com/v3/smtp/statistics/aggregatedReport?days=1', $request->url());
            $this->assertTrue($request->hasHeader('api-key', 'test-brevo-api-key'));
            $this->assertSame(2, $options['connect_timeout']);
            $this->assertSame(3, $options['timeout']);
            $this->assertFalse($options['allow_redirects']);
            return Http::response(['requests' => 0, 'range' => '2026-11-22|2026-11-22']);
        });
        $report = app(SystemServiceHealth::class)->emailReport();
        $this->assertSame('Online', $report['status']);
        $this->assertStringContainsString('delivery was not tested', $report['message']);
        $this->assertStringNotContainsString('test-brevo-api-key', json_encode($report));
        Http::assertSentCount(1);
    }

    public function test_missing_key_does_not_call_brevo(): void
    {
        config()->set('services.brevo.key', '   ');
        Http::fake();
        $report = app(SystemServiceHealth::class)->emailReport();
        $this->assertSame('Not verified', $report['status']);
        $this->assertStringContainsString('not configured', $report['message']);
        Http::assertNothingSent();
    }

    public static function failedResponses(): array
    {
        return [
            'invalid key' => [401, 'Offline', 'authentication'],
            'restricted permissions or IP' => [403, 'Not verified', 'access restrictions'],
            'rate limited' => [429, 'Not verified', 'rate-limited'],
            'provider unavailable' => [503, 'Offline', 'temporarily unavailable'],
            'unexpected response' => [400, 'Not verified', 'unexpected'],
            'redirect not followed' => [302, 'Not verified', 'unexpected'],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_failures_are_distinguished_without_exposing_provider_response(int $code, string $status, string $reason): void
    {
        Http::fake(['*' => Http::response(['message' => 'sensitive-provider-response'], $code)]);
        $report = app(SystemServiceHealth::class)->emailReport();
        $this->assertSame($status, $report['status']);
        $this->assertStringContainsString($reason, $report['message']);
        $this->assertStringNotContainsString('sensitive-provider-response', json_encode($report));
        Http::assertSentCount(1);
    }

    public function test_timeout_is_offline_without_leaking_exception(): void
    {
        Http::fake(['*' => Http::failedConnection('secret-network-details')]);
        $report = app(SystemServiceHealth::class)->emailReport();
        $this->assertSame('Offline', $report['status']);
        $this->assertStringContainsString('timeout', $report['message']);
        $this->assertStringNotContainsString('secret-network-details', json_encode($report));
    }

    public function test_unexpected_success_body_does_not_claim_online(): void
    {
        Http::fake(['*' => Http::response('<html>unavailable</html>', 200)]);
        $this->assertSame('Not verified', app(SystemServiceHealth::class)->email());
    }

    public function test_log_mailer_remains_disabled(): void
    {
        config()->set('mail.default', 'log');
        Http::fake();
        $this->assertSame('Disabled', app(SystemServiceHealth::class)->emailReport()['status']);
        Http::assertNothingSent();
    }

    public function test_services_response_exposes_safe_email_explanation_and_caches_the_probe(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Support\Facades\DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);
        Http::fake(['*' => Http::response(['message' => 'private-provider-details'], 403)]);
        $batch = Mockery::mock(\App\Http\Controllers\Admin\BatchFileProcessingController::class);
        $batch->shouldReceive('nlpStatus')->once()->andReturn(response()->json(['status' => 'unknown']));
        $health = Mockery::mock(SystemServiceHealth::class)->makePartial();
        $health->shouldReceive('reverb')->once()->andReturn('Disabled');
        $health->shouldReceive('scheduler')->twice()->andReturn('Not verified');
        $controller = app(\App\Http\Controllers\Admin\GeneralSettingsController::class);

        $first = $controller->services($batch, $health);
        $second = $controller->services($batch, $health);
        $this->assertSame('Not verified', $first->getData(true)['email']);
        $this->assertStringContainsString('access restrictions', $first->getData(true)['email_message']);
        $this->assertSame($first->getData(true), $second->getData(true));
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private-provider-details', $first->getContent());
        Http::assertSentCount(2); // One Python probe and one transactional Brevo probe, cached together.
    }

    public static function smtpResults(): array
    {
        return ['connected' => [false, 'Online'], 'failed' => [true, 'Offline']];
    }

    #[DataProvider('smtpResults')]
    public function test_smtp_still_connects_with_short_timeout_and_always_stops(bool $fails, string $status): void
    {
        config()->set('mail.default', 'smtp');
        $stream = new SocketStream;
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('getStream')->once()->andReturn($stream);
        $start = $transport->shouldReceive('start')->once();
        if ($fails) {
            $start->andThrow(new \RuntimeException('connection failed'));
        }
        $transport->shouldReceive('stop')->once();
        $mailer = Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        Http::fake();
        $this->assertSame($status, app(SystemServiceHealth::class)->emailReport()['status']);
        $this->assertSame(3.0, $stream->getTimeout());
        Http::assertNothingSent();
    }
}
