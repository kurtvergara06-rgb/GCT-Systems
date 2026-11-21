<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginEntranceAnimationTest extends TestCase
{
    public function test_login_entrance_animation_is_scoped_to_sign_in_only(): void
    {
        $view = file_get_contents(resource_path('views/Login/login.blade.php'));
        $this->assertStringContainsString('class="gct-login-page gct-login-page--signin"', $view);

        $this->assertStringContainsString('id="gct-login-entrance-v4"', $view);
        $this->assertStringContainsString('@keyframes gct-login-entrance-card-v4', $view);
        $this->assertStringContainsString('@keyframes gct-login-entrance-brand-v4', $view);
        $this->assertStringContainsString('animation: gct-login-entrance-card-v4 3s', $view);
        $this->assertStringContainsString('translateY(90px)', $view);
        $this->assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $view);

        $css = file_get_contents(resource_path('css/Login/login.css'));
        $this->assertStringNotContainsString('gct-login-card-enter', $css);
        $script = file_get_contents(resource_path('js/Login/login.js'));
        $this->assertStringNotContainsString('gct-login-animate', $script);
    }
}
