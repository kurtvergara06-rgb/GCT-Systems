<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginAttemptLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_failed_passwords_trigger_a_five_minute_login_cooldown(): void
    {
        $user = User::factory()->create([
            'status' => 'Active',
            'department' => 'Operation',
            'role' => 'head',
            'onboarding_completed' => true,
            'must_change_password' => false,
        ]);
        $key = sprintf('login:%s|%s', strtolower($user->email), '127.0.0.1');
        RateLimiter::clear($key);

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->post(route('login.submit'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])->assertSessionHasErrors('email');
            }

            $response = $this->post(route('login.submit'), [
                'email' => $user->email,
                'password' => 'password',
            ]);
            $response->assertSessionHasErrors('email')
                ->assertSessionHas('login_lockout_seconds');
            $this->assertGuest();
            $this->assertGreaterThan(0, RateLimiter::availableIn($key));
            $this->assertLessThanOrEqual(300, RateLimiter::availableIn($key));
        } finally {
            RateLimiter::clear($key);
        }
    }
}
