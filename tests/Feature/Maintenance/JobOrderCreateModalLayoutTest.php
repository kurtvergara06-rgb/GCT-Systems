<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class JobOrderCreateModalLayoutTest extends TestCase
{
    public function test_new_job_order_modal_uses_structured_gct_sections(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );

        $this->assertStringContainsString(
            'class="jo-create-modal-overlay"',
            $view
        );
        $this->assertStringContainsString(
            'Basic Information',
            $view
        );
        $this->assertStringContainsString(
            'Job Details',
            $view
        );
        $this->assertStringContainsString(
            'Requested Parts',
            $view
        );
        $this->assertStringContainsString(
            'jo-create-parts-table-head',
            $view
        );

        // Existing workflow hooks must remain intact.
        $this->assertStringContainsString(
            'id="jobAssignedMechanic"',
            $view
        );
        $this->assertStringContainsString(
            'id="newRequestedPartsSection"',
            $view
        );
        $this->assertStringContainsString(
            'id="newPartsLockedNotice"',
            $view
        );
        $this->assertStringContainsString(
            'id="partsNeededWrapper"',
            $view
        );
        $this->assertStringContainsString(
            'id="addPartBtn"',
            $view
        );

        // Do not invent a persistence field that the Job Order backend does not support.
        $this->assertStringNotContainsString(
            'name="work_repair_to_perform"',
            $view
        );
    }

    public function test_new_job_order_modal_css_is_centered_and_matches_gct_scale(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            '#jobModal.jo-create-modal-overlay',
            $css
        );
        $this->assertStringContainsString(
            'justify-content: center !important;',
            $css
        );
        $this->assertStringContainsString(
            'width: min(920px, calc(100vw - 56px)) !important;',
            $css
        );
        $this->assertStringContainsString(
            'grid-template-columns: 42px minmax(0, 1fr) 96px 120px 58px;',
            $css
        );
        $this->assertStringContainsString(
            'background: linear-gradient(135deg, #ffc400, #f5a800);',
            $css
        );
        $this->assertStringContainsString(
            '#jobModal .ui-form-content',
            $css
        );
        $this->assertStringContainsString(
            'flex: 1 1 auto;',
            $css
        );
        $this->assertStringContainsString(
            'max-height: none;',
            $css
        );
        $this->assertStringContainsString(
            'flex: 0 0 auto;',
            $css
        );
    }
}
