<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyRedirectTest extends TestCase
{
    public function test_auth_redirect_uses_the_request_host_instead_of_a_malformed_forwarded_host(): void
    {
        $response = $this->call(
            'GET',
            'https://gct-systems.onrender.com/topbar/summary',
            [],
            [],
            [],
            [
                'HTTP_X_FORWARDED_HOST' => 'https',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        $response->assertRedirect('https://gct-systems.onrender.com/');
    }
}
