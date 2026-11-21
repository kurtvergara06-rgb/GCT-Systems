<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\SystemSetting;
use App\Models\Admin\User;
use App\Services\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_head_can_save_and_reload_validated_policy(): void
    {
        $head = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $this->actingAs($head)->put(route('admin.settings.security.update'), [
            'minimum_password_length' => 10,
            'password_expiration' => 90,
            'session_timeout' => 60,
            'failed_attempts' => 3,
            'lockout_duration' => 15,
            'require_uppercase' => 1,
            'require_number' => 1,
        ])->assertRedirect(route('admin.settings.security'));

        SecurityPolicy::clearCache();
        $policy = SecurityPolicy::current();
        $this->assertSame(10, $policy['minimum_password_length']);
        $this->assertSame(3, $policy['failed_attempts']);
        $this->assertTrue($policy['require_uppercase']);
        $this->assertFalse($policy['require_special']);
        $this->assertSame(90, $policy['password_expiration']);
        $this->assertNotNull(SystemSetting::query()->find(1));
    }

    public function test_admin_staff_cannot_update_security_policy(): void
    {
        $staff = User::factory()->create(['department' => 'Admin', 'role' => 'staff', 'status' => 'Active']);
        $this->actingAs($staff)->put(route('admin.settings.security.update'), [
            'minimum_password_length' => 8,
            'password_expiration' => 0,
            'session_timeout' => 0,
            'failed_attempts' => 5,
            'lockout_duration' => 5,
        ])->assertForbidden();
    }

    public function test_invalid_password_policy_cannot_be_saved(): void
    {
        $head = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $this->actingAs($head)->from(route('admin.settings.security'))
            ->put(route('admin.settings.security.update'), [
                'minimum_password_length' => 2,
                'password_expiration' => 0,
                'session_timeout' => 0,
                'failed_attempts' => 1,
                'lockout_duration' => 5,
            ])->assertSessionHasErrors(['minimum_password_length', 'failed_attempts']);
    }
    public function test_session_timeout_cannot_exceed_laravel_session_lifetime(): void
    {
        config()->set('session.lifetime', 60);
        $head = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $this->actingAs($head)->from(route('admin.settings.security'))
            ->put(route('admin.settings.security.update'), [
                'minimum_password_length' => 8,
                'password_expiration' => 0,
                'session_timeout' => 240,
                'failed_attempts' => 5,
                'lockout_duration' => 5,
            ])->assertSessionHasErrors('session_timeout');
    }

}
