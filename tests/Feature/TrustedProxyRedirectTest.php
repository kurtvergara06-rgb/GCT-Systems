<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyRedirectTest extends TestCase
{
    public function test_auth_redirect_uses_the_request_host_instead_of_a_malformed_forwarded_host(): void
    {
        $this
            ->withServerVariables([
                'HTTP_HOST' => 'gct-systems.onrender.com',
                'HTTP_X_FORWARDED_HOST' => 'https',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->get('/topbar/summary')
            ->assertRedirect('https://gct-systems.onrender.com/');
    }
}
