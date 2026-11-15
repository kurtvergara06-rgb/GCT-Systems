<?php

namespace Tests\Feature;

use Tests\TestCase;

class LazyPaginationSecondRolloutTest extends TestCase
{
    public function test_second_rollout_pages_use_lazy_server_filtered_pagination(): void
    {
        $views = [
            resource_path('views/Purchase/purchase-orders.blade.php'),
            resource_path('views/Warehouse/incoming-deliveries.blade.php'),
            resource_path('views/Warehouse/part-requests.blade.php'),
            resource_path('views/Purchase/scheduled-purchase.blade.php'),
        ];

        foreach ($views as $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString(
                'data-server-filter="true"',
                $source
            );
            $this->assertStringContainsString(
                'data-lazy-pagination="true"',
                $source
            );
        }
    }

    public function test_purchase_order_actions_are_delegated_for_lazy_loaded_rows(): void
    {
        $source = file_get_contents(
            resource_path('js/Purchase/purchase-orders.js')
        );

        foreach ([
            '.open-edit-po-modal',
            '.open-view-po-modal',
            '.open-po-status-modal',
            '.open-delete-po-modal',
        ] as $selector) {
            $this->assertStringContainsString(
                "event.target.closest('{$selector}')",
                $source
            );
        }
    }

    public function test_scheduled_purchase_actions_are_delegated_for_lazy_loaded_rows(): void
    {
        $source = file_get_contents(
            resource_path('js/Purchase/scheduled-purchase.js')
        );

        $this->assertStringContainsString(
            "document.addEventListener('click'",
            $source
        );
        $this->assertStringContainsString(
            "'.open-edit-schedule, .open-view-schedule'",
            $source
        );
    }

    public function test_warehouse_part_request_actions_are_delegated_for_lazy_loaded_rows(): void
    {
        $source = file_get_contents(
            resource_path('js/Warehouse/part-requests.js')
        );

        $this->assertStringContainsString(
            "event.target.closest('.open-view-pr-modal')",
            $source
        );
        $this->assertStringContainsString(
            "event.target.closest('.open-issue-modal')",
            $source
        );
    }

    public function test_confirmation_forms_support_lazy_loaded_workflow_rows(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/confirmation-modal.js')
        );

        $this->assertStringContainsString(
            "document.addEventListener('submit'",
            $source
        );
        $this->assertStringContainsString(
            "event.target.closest?.('[data-confirm-form]')",
            $source
        );
    }
}
