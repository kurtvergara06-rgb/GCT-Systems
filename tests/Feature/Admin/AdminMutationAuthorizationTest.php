<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $department = 'Admin', string $role = 'staff'): User
    {
        return User::factory()->create([
            'department' => $department,
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    public function test_admin_staff_cannot_modify_or_delete_accounts_by_direct_request(): void
    {
        $staff = $this->employee();
        $target = $this->employee('Warehouse', 'staff');
        $originalName = $target->name;

        $this->actingAs($staff)
            ->put(route('admin.users.update', $target), ['name' => 'Unauthorized Change'])
            ->assertForbidden();
        $this->actingAs($staff)
            ->patch(route('admin.users.update-status', $target), ['status' => 'Inactive'])
            ->assertForbidden();
        $this->actingAs($staff)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $target->refresh();
        $this->assertSame($originalName, $target->name);
        $this->assertSame('Active', $target->status);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_staff_cannot_approve_or_delete_gps_batches_by_direct_request(): void
    {
        $staff = $this->employee();
        $batch = BatchUpload::query()->create([
            'file_name' => 'gps-audit.csv',
            'stored_name' => 'gps-audit.csv',
            'file_path' => 'gps-batches/gps-audit.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'data_origin' => 'unknown',
            'status' => 'In Review',
            'total_records' => 0,
            'processed_records' => 0,
            'failed_records' => 0,
            'progress_percentage' => 0,
            'uploaded_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('batch-file-processing.confirm', $batch))
            ->assertForbidden();
        $this->actingAs($staff)
            ->delete(route('batch-file-processing.destroy', $batch))
            ->assertForbidden();

        $this->assertDatabaseHas('batch_uploads', [
            'id' => $batch->id,
            'status' => 'In Review',
        ]);
    }

    public function test_admin_staff_pages_hide_restricted_account_and_batch_actions(): void
    {
        $staff = $this->employee();
        $this->employee('Warehouse', 'staff');

        $this->actingAs($staff)->get(route('admin.users'))
            ->assertOk()
            ->assertDontSee('data-update-url=', false)
            ->assertDontSee('open-reset-password-modal', false);

        $this->actingAs($staff)->get(route('batch-file-processing'))
            ->assertOk()
            ->assertDontSee('data-delete-batch', false)
            ->assertDontSee('id="confirmBatchForm"', false);
    }

    public function test_admin_head_can_change_regular_account_status(): void
    {
        $head = $this->employee('Admin', 'head');
        $target = $this->employee('Warehouse', 'staff');

        $this->actingAs($head)
            ->patch(route('admin.users.update-status', $target), ['status' => 'Inactive'])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Inactive', $target->fresh()->status);
    }

    public function test_no_user_can_delete_own_or_protected_admin_account(): void
    {
        $head = $this->employee('Admin', 'head');
        $this->actingAs($head)
            ->delete(route('admin.users.destroy', $head))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $head->id]);
    }
}
