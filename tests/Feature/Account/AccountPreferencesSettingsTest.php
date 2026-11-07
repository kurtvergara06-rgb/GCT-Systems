<?php

namespace Tests\Feature\Account;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountPreferencesSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_requires_authentication(): void
    {
        $this->get(route('account.preferences'))->assertRedirect(route('login'));
        $this->delete(route('account.sessions.revoke', 'missing'))->assertRedirect(route('login'));
    }

    public function test_settings_has_notifications_security_and_browser_controls(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('account.preferences'))
            ->assertOk()
            ->assertSeeText('Notifications')
            ->assertSeeText('Always enabled')
            ->assertSee('data-gct-notification-sound', false)
            ->assertSeeText('Security & Sessions')
            ->assertSeeText('Browser & Display')
            ->assertSee('data-gct-reset-preferences', false);
    }

    public function test_users_can_only_revoke_their_own_other_database_sessions(): void
    {
        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('Database sessions migration is not installed.');
        }

        $user = User::factory()->create([
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $other = User::factory()->create();
        foreach ([
            ['id' => 'owned-session-111', 'user_id' => $user->id],
            ['id' => 'other-session-222', 'user_id' => $other->id],
        ] as $session) {
            DB::table('sessions')->insert([
                ...$session,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test browser',
                'payload' => base64_encode('test'),
                'last_activity' => time(),
            ]);
        }

        config()->set('session.driver', 'database');

        $this->actingAs($user)
            ->delete(route('account.sessions.revoke', 'other-session-222'))
            ->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'other-session-222']);

        $this->actingAs($user)
            ->delete(route('account.sessions.revoke', 'owned-session-111'))
            ->assertRedirect(route('account.preferences'));
        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session-111']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session-222']);
    }

    public function test_current_session_cannot_be_revoked_by_device_revoke_endpoint(): void
    {
        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('Database sessions migration is not installed.');
        }

        config()->set('session.driver', 'database');
        $user = User::factory()->create([
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $this->actingAs($user);
        $currentId = $this->app['session']->driver()->getId();

        // The request must carry the same session cookie as the browser.
        // Without it, StartSession generates a different request session ID,
        // so the controller correctly responds 404 for an unknown session.
        $this->withCookie(config('session.cookie'), $currentId)
            ->delete(route('account.sessions.revoke', $currentId))
            ->assertStatus(422);
    }

    public function test_active_session_list_only_displays_signed_in_users_sessions(): void
    {
        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('Database sessions migration is not installed.');
        }

        $user = User::factory()->create([
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $other = User::factory()->create([
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        foreach ([
            ['id' => 'owned-visible-session-111', 'user_id' => $user->id, 'user_agent' => 'OwnBrowserMarker'],
            ['id' => 'foreign-hidden-session-222', 'user_id' => $other->id, 'user_agent' => 'OtherEmployeeBrowserMarker'],
        ] as $session) {
            DB::table('sessions')->insert([
                ...$session,
                'ip_address' => '127.0.0.1',
                'payload' => base64_encode('test'),
                'last_activity' => time(),
            ]);
        }

        config()->set('session.driver', 'database');

        $this->actingAs($user)
            ->get(route('account.preferences'))
            ->assertOk()
            ->assertSee('OwnBrowserMarker')
            ->assertSee('owned-visible-session-111')
            ->assertDontSee('OtherEmployeeBrowserMarker')
            ->assertDontSee('foreign-hidden-session-222');

        $this->actingAs($other)
            ->get(route('account.preferences'))
            ->assertOk()
            ->assertSee('OtherEmployeeBrowserMarker')
            ->assertDontSee('OwnBrowserMarker')
            ->assertDontSee('owned-visible-session-111');
    }

}
