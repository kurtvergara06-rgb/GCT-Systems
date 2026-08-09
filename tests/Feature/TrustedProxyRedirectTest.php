<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyRedirectTest extends TestCase
{
    public function test_auth_redirect_uses_the_request_host_instead_of_a_malformed_forwarded_host(): void
    {
        $this
            ->withHeaders([
                'Host' => 'gct-systems.onrender.com',
                'X-Forwarded-Host' => 'https',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->get('/topbar/summary')
            ->assertRedirect('https://gct-systems.onrender.com/');
    }
}
