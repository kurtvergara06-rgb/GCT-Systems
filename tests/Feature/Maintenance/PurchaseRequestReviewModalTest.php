<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_head_sees_one_review_entry_point_and_modal_workflow_actions(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest();

        $response = $this->actingAs($head)->get(route('purchase-requests'));

        $response
            ->assertOk()
            ->assertSee('Purchase Request Details')
            ->assertSee('pr-review-modal-overlay', false)
            ->assertSee('Request Information')
            ->assertSee('Requested Items')
            ->assertDontSee('id="prReviewHistory"', false)
            ->assertDontSee('data-pr-review-target', false)
            ->assertSee('pr-review-btn open-view-pr-modal', false)
            ->assertSee('data-can-approve="1"', false)
            ->assertSee('reviewEditPrBtn', false)
            ->assertSee('reviewRejectPrBtn', false)
            ->assertSee('reviewApprovePrBtn', false)
            ->assertSee('reviewDecisionRemarks', false);
    }

    public function test_staff_can_review_and_edit_submitted_pr_but_cannot_approve_or_reject(): void
    {
        $staff = $this->maintenanceUser('staff');
        $this->purchaseRequest();

        $this->actingAs($staff)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-edit="1"', false)
            ->assertSee('data-can-approve="0"', false);
    }

    public function test_rejected_pr_exposes_revise_but_not_decision_permissions(): void
    {
        $head = $this->maintenanceUser('head');
        $this->purchaseRequest('Rejected');

        $this->actingAs($head)
            ->get(route('purchase-requests'))
            ->assertOk()
            ->assertSee('data-can-edit="1"', false)
            ->assertSee('data-can-approve="0"', false);
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

    public function test_review_modal_css_is_centered_not_a_side_drawer(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/purchase-requests.css')
        );

        $this->assertStringContainsString(
            '.pr-review-modal-overlay {',
            $css
        );
        $this->assertStringContainsString(
            'justify-content: center !important;',
            $css
        );
        $this->assertStringContainsString(
            '#editPrModal.pr-review-modal-overlay > .ui-form-modal',
            $css
        );
        $this->assertStringContainsString(
            'width: min(900px, calc(100vw - 56px)) !important;',
            $css
        );
        $this->assertStringContainsString(
            'font-size: 22px;',
            $css
        );
        $this->assertStringContainsString(
            'height: 40px;',
            $css
        );
        $this->assertStringContainsString(
            'border-radius: 9px;',
            $css
        );
        $this->assertStringContainsString(
            'background: var(--brand-yellow, #ffc400);',
            $css
        );
        $this->assertStringContainsString(
            'background: #fff3f3;',
            $css
        );
        $this->assertStringContainsString(
            'background: #eefbf3;',
            $css
        );
        $this->assertStringContainsString(
            '#editPrModal .pr-review-footer [hidden]',
            $css
        );
        $this->assertStringContainsString(
            '.purchase-page .purchase-request-table .actions .pr-review-btn',
            $css
        );
        $this->assertStringContainsString(
            'min-width: 92px !important;',
            $css
        );
        $this->assertStringContainsString(
            'width: auto !important;',
            $css
        );
    }
}
