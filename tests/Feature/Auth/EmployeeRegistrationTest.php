<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmployeeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_without_granting_module_access(): void
    {
        Notification::fake();

        $response = $this->post(route('register.submit'), [
            'name' => 'New Employee',
            'email' => 'employee@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department' => 'admin',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $response->assertRedirect(route('registration.pending'));
        $user = User::where('email', 'employee@example.test')->firstOrFail();
        $this->assertSame('Pending', $user->status);
        $this->assertSame('Unassigned', $user->department);
        $this->assertSame('unassigned', $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->hasSystemPermission('operation', 'view'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unsigned_verification_is_rejected_and_signed_verification_succeeds(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'Pending',
            'department' => 'Unassigned',
            'role' => 'unassigned',
        ]);
        $path = '/email/verify/'.$user->id.'/'.sha1($user->email);
        $this->get($path)->assertForbidden();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->get($url)->assertRedirect(route('registration.pending'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('Pending', $user->fresh()->status);
    }

    public function test_registration_password_confirmation_and_unique_email_are_required(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $this->post(route('register.submit'), [
            'name' => 'Test',
            'email' => $existing->email,
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
    }
}
