<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectedApplicationReconsiderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reconsider_rejected_application_without_activating_it(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $applicant = User::factory()->create(['department' => 'Unassigned', 'role' => 'unassigned', 'status' => 'Rejected', 'email_verified_at' => null]);

        $this->actingAs($admin)->post(route('admin.users.reconsider-application', $applicant))
            ->assertRedirect(route('admin.applications', ['status' => 'Pending']));

        $this->assertSame('Pending', $applicant->fresh()->status);
        $this->assertNull($applicant->fresh()->email_verified_at);
    }

    public function test_employee_cannot_reconsider_an_application(): void
    {
        $employee = User::factory()->create(['department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active']);
        $applicant = User::factory()->create(['status' => 'Rejected']);

        $this->actingAs($employee)->post(route('admin.users.reconsider-application', $applicant))
            ->assertForbidden();

        $this->assertSame('Rejected', $applicant->fresh()->status);
    }

    public function test_pending_application_cannot_be_reconsidered_again(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $applicant = User::factory()->create(['status' => 'Pending']);

        $this->actingAs($admin)->post(route('admin.users.reconsider-application', $applicant))
            ->assertNotFound();
    }
}
