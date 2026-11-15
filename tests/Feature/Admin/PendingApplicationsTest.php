<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
    }

    private function applicant(bool $verified = true): User
    {
        return User::factory()->create([
            'department' => 'Unassigned',
            'role' => 'unassigned',
            'status' => 'Pending',
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    public function test_admin_can_filter_and_review_pending_applications(): void
    {
        $user = $this->applicant();
        $this->actingAs($this->admin())
            ->get(route('admin.users', ['status' => 'Pending']))
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee($user->email)
            ->assertSee('Review Employee Application');
    }

    public function test_verified_applicant_can_be_approved_with_assigned_department_and_role(): void
    {
        $user = $this->applicant();
        $this->actingAs($this->admin())
            ->post(route('admin.users.review-application', $user), [
                'decision' => 'approve',
                'department' => 'Operation',
                'role' => 'staff',
            ])->assertRedirect(route('admin.users', ['status' => 'Pending']));
        $this->assertSame('Active', $user->fresh()->status);
        $this->assertSame('Operation', $user->fresh()->department);
        $this->assertSame('staff', $user->fresh()->role);
    }

    public function test_unverified_applicant_cannot_be_approved(): void
    {
        $user = $this->applicant(false);
        $this->actingAs($this->admin())
            ->post(route('admin.users.review-application', $user), [
                'decision' => 'approve',
                'department' => 'Operation',
                'role' => 'staff',
            ])->assertSessionHas('error');
        $this->assertSame('Pending', $user->fresh()->status);
    }

    public function test_reject_keeps_record_but_blocks_access(): void
    {
        $user = $this->applicant();
        $this->actingAs($this->admin())
            ->post(route('admin.users.review-application', $user), [
                'decision' => 'reject',
            ])->assertSessionHas('success');
        $this->assertSame('Rejected', $user->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'Rejected']);
    }

    public function test_non_administrator_cannot_review_applicants(): void
    {
        $staff = User::factory()->create(['department' => 'Operation', 'role' => 'staff', 'status' => 'Active']);
        $user = $this->applicant();
        $this->actingAs($staff)
            ->post(route('admin.users.review-application', $user), [
                'decision' => 'approve',
                'department' => 'Operation',
                'role' => 'staff',
            ])->assertForbidden();
        $this->assertSame('Pending', $user->fresh()->status);
    }
}
