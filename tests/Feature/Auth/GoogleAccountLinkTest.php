<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GoogleAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'employee@example.com',
            'password' => Hash::make('SecureTestPass#2026'),
            'department' => 'Operation',
            'role' => 'Staff',
            'status' => 'Active',
            'google_id' => null,
        ], $overrides));
    }

    private function linkingSession(User $user): array
    {
        return ['google_link_pending' => [
            'user_id' => $user->id,
            'google_id' => 'google-subject-123',
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]];
    }

    public function test_active_employee_can_confirm_password_and_link_google(): void
    {
        $employee = $this->employee();
        $this->withSession($this->linkingSession($employee))
            ->post(route('auth.google.link.confirm'), ['password' => 'SecureTestPass#2026'])
            ->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($employee);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'google_id' => 'google-subject-123']);
    }

    public function test_wrong_password_does_not_link_google(): void
    {
        $employee = $this->employee();
        $this->from(route('auth.google.link'))
            ->withSession($this->linkingSession($employee))
            ->post(route('auth.google.link.confirm'), ['password' => 'wrong-pass'])
            ->assertRedirect(route('auth.google.link'))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertNull($employee->fresh()->google_id);
    }

    public function test_admin_account_cannot_be_linked_through_password_confirmation(): void
    {
        $admin = $this->employee(['department' => 'Admin', 'role' => 'Head']);
        $this->withSession($this->linkingSession($admin))
            ->post(route('auth.google.link.confirm'), ['password' => 'SecureTestPass#2026'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_expired_linking_session_is_rejected(): void
    {
        $employee = $this->employee();
        $session = $this->linkingSession($employee);
        $session['google_link_pending']['expires_at'] = now()->subMinute()->timestamp;

        $this->withSession($session)->post(route('auth.google.link.confirm'), [
            'password' => 'SecureTestPass#2026',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($employee->fresh()->google_id);
    }
}
