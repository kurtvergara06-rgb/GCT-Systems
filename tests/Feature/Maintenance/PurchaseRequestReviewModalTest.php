<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderAudit;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseRequestReviewModalTest extends TestCase
{
    use RefreshDatabase;

    private function maintenanceUser(string $role): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function setMaintenanceStaffApproval(bool $allowed): void
    {
        $role = RolePermission::where('role_key', 'maintenance_staff')->firstOrFail();
        $permissions = $role->permissions ?? [];
        data_set($permissions, 'maintenance.approve', $allowed);

        $role->update([
            'permissions' => $permissions,
        ]);
    }

    private function purchaseRequest(string $status = 'Submitted'): PurchaseRequest
    {
        return PurchaseRequest::create([
            'pr_no' => 'PR-2026-9001',
            'job_order_no' => 'JO-2026-9001',
            'bus_no' => 'GCT-108',
            'item' => 'AC Refrigerant R134a - Qty: 2 can, O-Ring Set - Qty: 4 set',
            'quantity' => 6,
            'remarks' => 'For AC preventive maintenance.',
            'status' => $status,
            'source_type' => 'Maintenance Request',
        ]);
    }

    public function test_head_sees_standardized_read_only_purchase_requisition_details(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest();

        $response = $this->actingAs($head)->get(route('purchase-requests'));

        $response
            ->assertOk()
            ->assertSee('Purchase Requisition Details')
            ->assertSee('Maintenance purchase requisition information and authorization')
            ->assertSee('pr-review-modal-overlay', false)
            ->assertSee('Request Overview')
            ->assertSee('Head Approval')
            ->assertSee('Parts &amp; Warehouse', false)
            ->assertSee('Request Details')
            ->assertSee('id="reviewPrSummaryJobOrder"', false)
            ->assertSee('id="reviewPrTotalQuantity"', false)
            ->assertSee('id="reviewPrAuthorizationJobOrder"', false)
            ->assertSee('id="reviewPrSignature"', false)
            ->assertSee('id="closeViewOnlyPr"', false)
            ->assertDontSee('id="prReviewHistory"', false)
            ->assertDontSee('data-pr-review-target', false)
            ->assertSee('pr-review-btn open-view-pr-modal', false)
            ->assertSee('data-can-approve="0"', false)
            ->assertDontSee('reviewEditPrBtn', false)
            ->assertDontSee('reviewRejectPrBtn', false)
            ->assertDontSee('reviewApprovePrBtn', false)
            ->assertDontSee('reviewDecisionRemarks', false);
    }

    public function test_staff_sees_submitted_pr_as_read_only_without_approval_actions(): void
    {
        $staff = $this->maintenanceUser('staff');
        $this->purchaseRequest();

        $this->actingAs($staff)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-edit="0"', false)
            ->assertSee('data-can-approve="0"', false)
            ->assertDontSee('reviewApprovePrBtn', false)
            ->assertDontSee('reviewRejectPrBtn', false);
    }

    public function test_staff_approval_permission_does_not_add_a_second_pr_approval_action(): void
    {
        $staff = $this->maintenanceUser('staff');
        $this->setMaintenanceStaffApproval(true);
        $this->purchaseRequest();

        $this->actingAs($staff)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-approve="0"', false)
            ->assertDontSee('reviewApprovePrBtn', false)
            ->assertDontSee('reviewRejectPrBtn', false);
    }

    public function test_staff_without_approve_permission_cannot_call_approve_endpoint(): void
    {
        $staff = $this->maintenanceUser('staff');
        $this->setMaintenanceStaffApproval(false);
        $purchaseRequest = $this->purchaseRequest();

        $this->actingAs($staff)
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertForbidden();

        $this->assertSame('Submitted', $purchaseRequest->fresh()->status);
    }

    public function test_rejected_pr_details_remain_read_only_without_decision_controls(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest('Rejected');

        $this->actingAs($head)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-edit="1"', false)
            ->assertSee('data-can-approve="0"', false)
            ->assertDontSee('reviewEditPrBtn', false)
            ->assertDontSee('reviewDecisionRemarks', false);
    }

    public function test_processed_pr_is_review_only(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest('Approved');

        $this->actingAs($head)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-edit="0"', false)
            ->assertSee('data-can-approve="0"', false)
            ->assertSee('data-can-delete="0"', false);
    }

    public function test_history_pr_is_review_only(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest('Issued');

        $this->actingAs($head)
            ->get(route('purchase-requests', ['record_view' => 'history']))
            ->assertOk()
            ->assertSee('data-history="1"', false)
            ->assertSee('data-can-edit="0"', false)
            ->assertSee('data-can-approve="0"', false)
            ->assertSee('data-can-delete="0"', false);
    }

    public function test_review_modal_shows_work_repair_to_perform_from_source_job_order(): void
    {
        $head = $this->maintenanceUser('head');

        JobOrder::create([
            'job_order_no' => 'JO-2026-9001',
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Brake vibration',
            'work_to_perform' => 'Replace brake pads and inspect calipers.',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Submitted',
        ]);

        $this->purchaseRequest();

        $this->actingAs($head)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('Work / Repair to Perform')
            ->assertSee('id="reviewPrWorkToPerform"', false)
            ->assertSee(
                'data-work-to-perform="Replace brake pads and inspect calipers."',
                false
            );
    }

    public function test_review_modal_uses_real_source_job_order_approval_and_authenticated_signature(): void
    {
        Storage::fake('local');

        $head = $this->maintenanceUser('head');
        $approver = $this->maintenanceUser('head');
        $approvedAt = now()->subMinutes(15);

        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-9001',
            'bus_no' => 'GCT-108',
            'problem_issue' => 'Brake vibration',
            'work_to_perform' => 'Replace brake pads and inspect calipers.',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Brake Pad - Qty: 2 pcs',
            'start_date' => now(),
            'status' => 'On Going',
            'part_status' => 'Approved',
            'review_status' => 'Approved',
            'approved_by' => $approver->id,
            'approved_at' => $approvedAt,
        ]);

        $signaturePath = "maintenance/job-order-signatures/{$jobOrder->id}/approval.png";
        Storage::disk('local')->put($signaturePath, 'png-signature');
        JobOrderAudit::create([
            'job_order_id' => $jobOrder->id,
            'user_id' => $approver->id,
            'action' => 'approved',
            'to_status' => 'Approved',
            'metadata' => ['handwritten_signature_path' => $signaturePath],
        ]);

        $this->purchaseRequest('Submitted');

        $response = $this->actingAs($head)->get(route('purchase-requests'));

        $response
            ->assertOk()
            ->assertSee('data-status="Submitted"', false)
            ->assertSee('data-jo-approved="1"', false)
            ->assertSee('data-jo-review-status="Approved"', false)
            ->assertSee('data-jo-approver="'.$approver->name.'"', false)
            ->assertSee('data-jo-signature-url="'.route('job-orders.approval-signature', $jobOrder, false).'"', false);

        $this->actingAs($head)
            ->get(route('job-orders.approval-signature', $jobOrder))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_open_review_modal_resyncs_permissions_after_realtime_refresh(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/purchase-requests.js')
        );

        $this->assertStringContainsString(
            'const syncOpenReviewPermissions',
            $source
        );
        $this->assertStringContainsString(
            "'system-regions-refreshed'",
            $source
        );
        $this->assertStringContainsString(
            "candidate.dataset.prNo === activePrNo",
            $source
        );
        $this->assertStringContainsString(
            "configureReviewActions(",
            $source
        );
    }

    public function test_review_modal_css_is_centered_not_a_side_drawer(): void
    {
        $legacyCss = file_get_contents(
            resource_path('css/Maintenance/purchase-requests.css')
        );
        $sharedCss = file_get_contents(
            resource_path('css/Maintenance/request-modal-shared.css')
        );

        $this->assertStringContainsString(
            '.pr-review-modal-overlay {',
            $legacyCss
        );
        $this->assertStringContainsString(
            ':is(#prModal, #editPrModal) > .ui-form-modal.ui-form-modal-xl',
            $sharedCss
        );
        $this->assertStringContainsString(
            'width: min(1160px, calc(100vw - 36px)) !important;',
            $sharedCss
        );
        $this->assertStringContainsString(
            'background: rgba(10, 25, 49, .56);',
            $legacyCss
        );
        $this->assertStringContainsString(
            'backdrop-filter: blur(5px);',
            $legacyCss
        );
        $this->assertStringContainsString(
            'position: sticky;',
            $sharedCss
        );
        $this->assertStringContainsString(
            'bottom: 0;',
            $sharedCss
        );
        $this->assertStringContainsString(
            'overflow-x: hidden;',
            $sharedCss
        );
        $this->assertStringContainsString(
            '#editPrModal .pr-review-footer [hidden]',
            $legacyCss
        );
        $this->assertStringContainsString(
            '.purchase-page .purchase-request-table .actions .pr-review-btn',
            $legacyCss
        );
        $this->assertStringContainsString(
            'min-width: 92px !important;',
            $legacyCss
        );
        $this->assertStringContainsString(
            'width: auto !important;',
            $legacyCss
        );
    }

    public function test_saved_pr_matches_pending_modal_width_and_parts_heading(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest();

        $this->actingAs($head)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('<span class="pr-step-icon">02</span>', false)
            ->assertSee('id="reviewPrItemCount" class="pr-step-tag"', false)
            ->assertSee('Missing parts — Purchase Request (PR), for procurement only');

        $css = file_get_contents(
            resource_path('css/Maintenance/request-modal-shared.css')
        );

        $this->assertStringContainsString(
            'width: min(1160px, calc(100vw - 36px)) !important;',
            $css
        );
        $this->assertStringContainsString(
            'max-width: 1160px !important;',
            $css
        );
        $this->assertStringContainsString(
            ':is(#prModal, #editPrModal) .pr-review-simplified .pr-step-heading',
            $css
        );

        $source = file_get_contents(
            resource_path('js/Maintenance/purchase-requests.js')
        );
        $this->assertStringContainsString(
            "openModal(\n        editPrModal",
            $source
        );
    }

    public function test_head_cannot_trigger_a_second_pr_approval_or_rejection(): void
    {
        $head = $this->maintenanceUser('head');
        $purchaseRequest = $this->purchaseRequest('Submitted');

        $this->actingAs($head)
            ->post(route('purchase-requests.approve', $purchaseRequest))
            ->assertStatus(409);

        $this->actingAs($head)
            ->post(route('purchase-requests.reject', $purchaseRequest))
            ->assertStatus(409);

        $this->assertSame('Submitted', $purchaseRequest->fresh()->status);
    }

    public function test_rejected_pr_cannot_change_parts_outside_signed_jo_scope(): void
    {
        $head = $this->maintenanceUser('head');
        JobOrder::create([
            'job_order_no' => 'JO-2026-9001',
            'bus_no' => 'GCT-108',
            'problem_issue' => 'AC repair',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'AC Refrigerant R134a - Qty: 2 can, O-Ring Set - Qty: 4 set',
            'status' => 'On Hold',
            'review_status' => 'Approved',
            'part_status' => 'Rejected',
        ]);
        $pr = $this->purchaseRequest('Rejected');

        $this->actingAs($head)->post(route('purchase-requests.resubmit', $pr), [
            'parts' => [['name' => 'New unauthorized part', 'quantity' => 5, 'unit' => 'pcs']],
        ])->assertSessionHasErrors('parts');

        $this->assertSame('Rejected', $pr->fresh()->status);
    }

    public function test_rejected_pr_with_unchanged_jo_authorized_parts_needs_no_second_approval(): void
    {
        $head = $this->maintenanceUser('head');
        JobOrder::create([
            'job_order_no' => 'JO-2026-9001',
            'bus_no' => 'GCT-108',
            'problem_issue' => 'AC repair',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'AC Refrigerant R134a - Qty: 2 can, O-Ring Set - Qty: 4 set',
            'status' => 'On Hold',
            'review_status' => 'Approved',
            'part_status' => 'Rejected',
        ]);
        $pr = $this->purchaseRequest('Rejected');

        $this->actingAs($head)->post(route('purchase-requests.resubmit', $pr), [
            'parts' => [
                ['name' => 'AC Refrigerant R134a', 'quantity' => 2, 'unit' => 'can'],
                ['name' => 'O-Ring Set', 'quantity' => 4, 'unit' => 'set'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Submitted', $pr->fresh()->status);
    }

}
