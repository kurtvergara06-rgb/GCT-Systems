<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PythonEngineWakeTest extends TestCase
{
    public function test_login_page_includes_the_background_wake_request(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('/python/wake', false);
    }

    public function test_wake_route_is_a_no_op_outside_production(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config()->set('services.nlp.api_url', 'https://gct-python-engine.onrender.com');

        $this->get(route('python.wake'))->assertNoContent();

        Http::assertNothingSent();
    }
}
