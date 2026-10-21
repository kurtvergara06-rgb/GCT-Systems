<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSensitiveEditTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
    }

    private function employee(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance', 'role' => 'staff',
            'status' => 'Active', 'email_verified_at' => now(),
        ]);
    }

    private function fields(User $employee, array $overrides = []): array
    {
        return array_merge([
            'name' => $employee->name, 'email' => $employee->email,
            'department' => $employee->department, 'role' => $employee->role,
            'status' => $employee->status,
        ], $overrides);
    }

    public function test_email_change_is_staged_and_old_verified_email_remains_active(): void
    {
        Mail::fake();
        $employee = $this->employee();
        $oldEmail = $employee->email;

        $this->actingAs($this->administrator())
            ->put(route('admin.users.update', $employee), $this->fields($employee, [
                'email' => 'employee.new@example.com',
            ]))->assertRedirect(route('admin.users'));

        $this->assertSame($oldEmail, $employee->fresh()->email);
        $this->assertNotNull($employee->fresh()->email_verified_at);
        $this->assertDatabaseHas('account_email_changes', [
            'user_id' => $employee->id,
            'new_email' => 'employee.new@example.com',
        ]);
    }

    public function test_department_and_role_changes_are_saved_without_email_change(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->administrator())
            ->put(route('admin.users.update', $employee), $this->fields($employee, [
                'department' => 'Operation', 'role' => 'head',
            ]))->assertRedirect(route('admin.users'));

        $this->assertSame('Operation', $employee->fresh()->department);
        $this->assertSame('head', $employee->fresh()->role);
        $this->assertDatabaseMissing('account_email_changes', ['user_id' => $employee->id]);
    }

    public function test_protected_admin_cannot_be_edited(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->fields($admin, [
                'email' => 'admin.changed@example.com',
            ]))->assertSessionHas('error');
        $this->assertNotEquals('admin.changed@example.com', $admin->fresh()->email);
    }

    public function test_invalid_email_change_link_does_not_update_address(): void
    {
        $employee = $this->employee();
        $this->get('/account/email-change/'.$employee->id.'/invalid-token')->assertForbidden();
        $this->assertDatabaseMissing('account_email_changes', ['user_id' => $employee->id]);
    }
    public function test_valid_signed_link_confirms_staged_email_without_changing_permissions(): void
    {
        $employee = $this->employee();
        $token = str_repeat('t', 64);
        DB::table('account_email_changes')->insert([
            'user_id' => $employee->id,
            'new_email' => 'verified.new@example.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('account.email-change.verify', now()->addMinutes(10), [
            'id' => $employee->id, 'token' => $token,
        ]);
        $this->get($url)->assertRedirect(route('login'));

        $this->assertSame('verified.new@example.com', $employee->fresh()->email);
        $this->assertNotNull($employee->fresh()->email_verified_at);
        $this->assertSame('Maintenance', $employee->fresh()->department);
        $this->assertSame('staff', $employee->fresh()->role);
        $this->assertDatabaseMissing('account_email_changes', ['user_id' => $employee->id]);
    }

}
