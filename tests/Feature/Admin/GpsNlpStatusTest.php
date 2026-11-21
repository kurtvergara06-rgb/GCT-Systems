<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\BatchFileProcessingController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GpsNlpStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget('gps-nlp-enrichment-readiness');
        parent::tearDown();
    }

    public function test_indicator_shows_disabled_without_contacting_python(): void
    {
        config()->set('gps_nlp.enabled', false);
        Http::fake();
        $response = app(BatchFileProcessingController::class)->nlpStatus();

        $this->assertSame('disabled', $response->getData(true)['status']);
        Http::assertNothingSent();
    }

    public function test_indicator_shows_online_only_for_authenticated_ready_endpoint(): void
    {
        config()->set('gps_nlp.enabled', true);
        config()->set('gps_nlp.token', 'test-secret');
        config()->set('gps_nlp.url', 'https://nlp.example');
        Cache::forget('gps-nlp-enrichment-readiness');
        Http::fake(['https://nlp.example/nlp/enrich-gps-status' => Http::response([
            'ready' => true, 'source' => 'operational_rules',
        ])]);

        $response = app(BatchFileProcessingController::class)->nlpStatus();

        $this->assertSame('online', $response->getData(true)['status']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-GCT-NLP-Token', 'test-secret'));
        $this->assertStringNotContainsString('test-secret', $response->getContent());
    }

    public function test_indicator_shows_offline_on_service_failure(): void
    {
        config()->set('gps_nlp.enabled', true);
        config()->set('gps_nlp.token', 'test-secret');
        config()->set('gps_nlp.url', 'https://nlp.example');
        Cache::forget('gps-nlp-enrichment-readiness');
        Http::fake(['https://nlp.example/nlp/enrich-gps-status' => Http::response([], 503)]);

        $response = app(BatchFileProcessingController::class)->nlpStatus();

        $this->assertSame('offline', $response->getData(true)['status']);
    }
}
