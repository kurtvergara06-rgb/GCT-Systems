<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModalInteractionPolicyTest extends TestCase
{
    public function test_global_modal_policy_ignores_backdrop_clicks(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/global-modal-backdrop.js')
        );

        $this->assertStringContainsString(
            'function preventBackdropDismissal(event)',
            $source
        );
        $this->assertStringContainsString(
            "target.matches(OVERLAY_QUERY)",
            $source
        );
        $this->assertStringContainsString(
            "event.stopPropagation();",
            $source
        );
        $this->assertStringContainsString(
            "preventBackdropDismissal,\n  true",
            $source
        );
    }

    public function test_partial_navigation_waits_for_confirmation_links(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/partial-navigation.js')
        );

        $this->assertStringContainsString(
            "if (link.hasAttribute('data-confirm-action')) return false;",
            $source
        );
    }

    public function test_global_confirmation_x_uses_the_same_cancel_path(): void
    {
        $component = file_get_contents(
            resource_path('views/components/ui/action-buttom-modal.blade.php')
        );
        $source = file_get_contents(
            resource_path('js/Main-js/confirmation-modal.js')
        );

        $this->assertStringContainsString(
            'id="closeGlobalConfirmation"',
            $component
        );
        $this->assertStringContainsString(
            "const closeButton = document.getElementById('closeGlobalConfirmation');",
            $source
        );
        $this->assertStringContainsString(
            "closeButton.addEventListener('click', closeModal);",
            $source
        );
        $this->assertStringNotContainsString(
            'if (event.target === modal)',
            $source
        );
    }

    public function test_pms_create_job_order_links_require_confirmation_first(): void
    {
        $pms = file_get_contents(
            resource_path('views/Maintenance/pms-scheduling.blade.php')
        );
        $dashboard = file_get_contents(
            resource_path('views/Maintenance/maintenance-dashboard.blade.php')
        );

        foreach ([$pms, $dashboard] as $source) {
            $this->assertStringContainsString(
                'data-confirm-title="Create PMS Job Order?"',
                $source
            );
            $this->assertStringContainsString(
                'data-confirm-action',
                $source
            );
            $this->assertStringContainsString(
                'data-no-partial-navigation',
                $source
            );
        }
    }

    public function test_pms_job_order_auto_open_uses_the_standard_job_order_initializer(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );
        $source = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'data-pms-create="{{ $pmsCreate ? \'true\' : \'false\' }}"',
            $view
        );
        $this->assertStringNotContainsString(
            "modal.classList.add('show', 'active');",
            $view
        );
        $this->assertStringContainsString(
            'const isPmsCreateFlow =',
            $source
        );
        $this->assertStringContainsString(
            'function cancelNewJobOrder()',
            $source
        );
        $this->assertStringContainsString(
            'function cleanPmsCreateUrl()',
            $source
        );
        $this->assertStringContainsString(
            'window.requestAnimationFrame(',
            $source
        );

        // The same standard JO structure remains in place for manual and PMS opens.
        $this->assertStringContainsString(
            'jo-create-basic',
            $view
        );
        $this->assertStringContainsString(
            'jo-create-details',
            $view
        );
        $this->assertStringContainsString(
            'jo-create-parts',
            $view
        );
    }
}
