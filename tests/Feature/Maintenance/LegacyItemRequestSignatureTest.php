<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyItemRequestSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        // Approval requires an explicit RBAC capability, not merely a Head
        // title. Grant it in this isolated fixture without changing production.
        if ($role === 'head') {
            RolePermission::updateOrCreate(
                ['role_key' => 'maintenance_head'],
                [
                    'label' => 'Maintenance Head',
                    'department' => 'Maintenance',
                    'role_type' => 'head',
                    'permissions' => ['maintenance' => [
                        'view' => true, 'edit' => true, 'approve' => true,
                    ]],
                ]
            );
        }

        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function legacyApprovedJobOrder(): JobOrder
    {
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-LEGACY-SIGN',
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Legacy repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'review_status' => 'Approved',
            'part_needed' => 'Engine Oil - Qty: 1 liter',
            'approved_at' => now()->subDays(10),
        ]);

        ItemRequest::create([
            'job_order_id' => $jobOrder->id,
            'status' => 'Partially Issued',
            'requested_parts' => [['name' => 'Engine Oil', 'quantity' => 1, 'unit' => 'liter']],
        ]);

        return $jobOrder;
    }

    public function test_head_can_attach_a_new_audited_signature_without_changing_original_approval(): void
    {
        Storage::fake('local');
        $head = $this->user('head');
        $jobOrder = $this->legacyApprovedJobOrder();
        $approvedAt = $jobOrder->approved_at;

        $this->actingAs($head)
            ->post(route('job-orders.complete-approval-signature', $jobOrder), [
                'electronic_signature' => $head->name,
                'signature_image' => UploadedFile::fake()->image('signature.png', 240, 100),
            ])
            ->assertRedirect(route('item-requests', ['view' => 'active']));

        $audit = $jobOrder->audits()->where('action', 'legacy_signature_attached')->firstOrFail();
        $path = data_get($audit->metadata, 'handwritten_signature_path');
        Storage::disk('local')->assertExists($path);
        $this->assertSame($head->id, $audit->user_id);
        $this->assertSame('Approved', $jobOrder->fresh()->review_status);
        $this->assertTrue($jobOrder->fresh()->approved_at->equalTo($approvedAt));
        $this->actingAs($head)->get(route('job-orders.approval-signature', $jobOrder))->assertOk();
    }

    public function test_head_can_reuse_own_saved_signature_with_a_new_explicit_audit(): void
    {
        Storage::fake('local');
        $head = $this->user('head');
        $source = JobOrder::create([
            'job_order_no' => 'JO-2026-SOURCE-SIGN',
            'bus_no' => 'GCT-107',
            'problem_issue' => 'Signed repair',
            'maintenance_type' => 'Repair',
            'status' => 'Completed',
            'review_status' => 'Approved',
        ]);
        $sourcePath = "maintenance/job-order-signatures/{$source->id}/original.png";
        $sourceImage = UploadedFile::fake()->image('signature.png', 240, 100);
        $sourceBytes = $sourceImage->get();
        Storage::disk('local')->put($sourcePath, $sourceBytes);
        $source->audits()->create([
            'user_id' => $head->id,
            'action' => 'approved',
            'to_status' => 'Approved',
            'metadata' => ['handwritten_signature_path' => $sourcePath],
        ]);

        $target = $this->legacyApprovedJobOrder();
        $originalDate = $target->approved_at;

        $this->actingAs($head)
            ->post(route('job-orders.complete-approval-signature', $target), [
                'electronic_signature' => $head->name,
                'reuse_saved_signature' => '1',
            ])
            ->assertRedirect(route('item-requests', ['view' => 'active']));

        $audit = $target->audits()->where('action', 'legacy_signature_attached')->firstOrFail();
        $savedPath = data_get($audit->metadata, 'handwritten_signature_path');
        $this->assertNotSame($sourcePath, $savedPath);
        $this->assertSame($source->id, data_get($audit->metadata, 'signature_source_job_order_id'));
        $this->assertSame($head->id, $audit->user_id);
        $this->assertSame($sourceBytes, Storage::disk('local')->get($savedPath));
        $this->assertTrue($target->fresh()->approved_at->equalTo($originalDate));
        $this->actingAs($head)->get(route('job-orders.approval-signature', $target))->assertOk();
    }

    public function test_head_cannot_reuse_another_users_signature(): void
    {
        Storage::fake('local');
        $head = $this->user('head');
        $otherHead = $this->user('head');
        $source = JobOrder::create([
            'job_order_no' => 'JO-2026-OTHER-SIGN',
            'bus_no' => 'GCT-109',
            'problem_issue' => 'Signed repair',
            'maintenance_type' => 'Repair',
            'status' => 'Completed',
            'review_status' => 'Approved',
        ]);
        $path = "maintenance/job-order-signatures/{$source->id}/other.png";
        $otherImage = UploadedFile::fake()->image('other.png', 240, 100);
        Storage::disk('local')->put($path, $otherImage->get());
        $source->audits()->create([
            'user_id' => $otherHead->id,
            'action' => 'approved',
            'to_status' => 'Approved',
            'metadata' => ['handwritten_signature_path' => $path],
        ]);
        $target = $this->legacyApprovedJobOrder();

        $this->actingAs($head)
            ->post(route('job-orders.complete-approval-signature', $target), [
                'electronic_signature' => $head->name,
                'reuse_saved_signature' => '1',
            ])
            ->assertSessionHasErrors('reuse_saved_signature');

        $this->assertSame(0, $target->audits()->where('action', 'legacy_signature_attached')->count());
    }

    public function test_staff_cannot_attach_a_signature(): void
    {
        Storage::fake('local');
        $staff = $this->user('staff');
        $jobOrder = $this->legacyApprovedJobOrder();

        $this->actingAs($staff)
            ->post(route('job-orders.complete-approval-signature', $jobOrder), [
                'electronic_signature' => $staff->name,
                'signature_image' => UploadedFile::fake()->image('signature.png', 240, 100),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('job_order_audits', [
            'job_order_id' => $jobOrder->id, 'action' => 'legacy_signature_attached',
        ]);
    }

    public function test_head_cannot_replace_an_existing_saved_signature(): void
    {
        Storage::fake('local');
        $head = $this->user('head');
        $jobOrder = $this->legacyApprovedJobOrder();
        $path = "maintenance/job-order-signatures/{$jobOrder->id}/existing.png";
        Storage::disk('local')->put($path, 'existing private signature');
        $jobOrder->audits()->create([
            'user_id' => $head->id,
            'action' => 'approved',
            'to_status' => 'Approved',
            'metadata' => ['handwritten_signature_path' => $path],
        ]);

        $this->actingAs($head)
            ->post(route('job-orders.complete-approval-signature', $jobOrder), [
                'electronic_signature' => $head->name,
                'signature_image' => UploadedFile::fake()->image('new.png', 240, 100),
            ])
            ->assertSessionHasErrors('signature_image');

        $this->assertSame(0, $jobOrder->audits()->where('action', 'legacy_signature_attached')->count());
    }
}
