<?php

namespace Tests\Feature;

use Tests\TestCase;

class LazyPaginationRolloutTest extends TestCase
{
    public function test_shared_table_search_supports_lazy_and_server_filtered_tables(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/automatic-table-search.js')
        );

        $this->assertStringContainsString(
            "toolbar?.dataset?.serverFilter === 'true'",
            $source
        );
        $this->assertStringContainsString(
            "footer.dispatchEvent(new CustomEvent('gct:load-all-records'))",
            $source
        );
        $this->assertStringContainsString(
            "replaceServerFilteredTable",
            $source
        );
        $this->assertStringContainsString(
            "await fetch(",
            $source
        );
        $this->assertStringContainsString(
            "window.history.replaceState(",
            $source
        );
        $this->assertStringContainsString(
            "'ajax:content-updated'",
            $source
        );
        $this->assertStringContainsString(
            "params.delete('page');",
            $source
        );
    }

    public function test_server_filtered_search_does_not_require_full_page_navigation(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/automatic-table-search.js')
        );

        $this->assertStringContainsString(
            "currentBody.replaceWith(",
            $source
        );
        $this->assertStringContainsString(
            "currentContext.footer.replaceWith(",
            $source
        );
        $this->assertStringContainsString(
            "event.preventDefault();\n      void runServerFilter(form);",
            $source
        );
        $this->assertStringContainsString(
            "AbortController",
            $source
        );
    }

    public function test_history_heavy_pages_opt_into_lazy_pagination(): void
    {
        $stockMovements = file_get_contents(
            resource_path('views/Warehouse/stock-movements.blade.php')
        );
        $activityLogs = file_get_contents(
            resource_path('views/Admin/System_Monitoring/activity-logs.blade.php')
        );
        $purchaseHistory = file_get_contents(
            resource_path('views/Purchase/purchase-history.blade.php')
        );

        $this->assertStringContainsString(
            'data-lazy-pagination="true"',
            $stockMovements
        );
        $this->assertStringContainsString(
            'data-server-filter="true"',
            $stockMovements
        );

        $this->assertStringContainsString(
            'data-lazy-pagination="true"',
            $activityLogs
        );
        $this->assertStringContainsString(
            'data-server-filter-owned="true"',
            $activityLogs
        );

        $this->assertStringContainsString(
            'data-lazy-pagination="true"',
            $purchaseHistory
        );
        $this->assertStringContainsString(
            'data-server-filter="true"',
            $purchaseHistory
        );
    }

    public function test_maintenance_lazy_loading_is_limited_to_history_views(): void
    {
        $jobOrders = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );
        $purchaseRequests = file_get_contents(
            resource_path('views/Maintenance/purchase-requests.blade.php')
        );
        $referrals = file_get_contents(
            resource_path('views/Maintenance/referrals.blade.php')
        );

        foreach ([$jobOrders, $purchaseRequests, $referrals] as $source) {
            $this->assertStringContainsString(
                'data-lazy-pagination="{{ $recordView === \'history\' ? \'true\' : \'false\' }}"',
                $source
            );
            $this->assertStringContainsString(
                'data-server-filter="{{ $recordView === \'history\' ? \'true\' : \'false\' }}"',
                $source
            );
        }
    }

    public function test_job_order_history_uses_smaller_batches_without_changing_active_batch_size(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Maintenance/JobOrderIndexController.php')
        );

        $this->assertStringContainsString(
            "->paginate(\$recordView === 'history' ? 20 : 1000)",
            $source
        );
    }

    public function test_lazy_loaded_job_order_history_actions_use_delegated_click_handlers(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringNotContainsString(
            "document.querySelectorAll('.open-edit-modal')",
            $source
        );
        $this->assertStringContainsString(
            "event.target.closest(\n          '.open-edit-modal'",
            $source
        );
        $this->assertStringContainsString(
            "event.target.closest(\n          '.open-delete-modal'",
            $source
        );
    }

    public function test_shared_styles_expose_lazy_loading_feedback(): void
    {
        $styles = file_get_contents(
            resource_path('css/Main-styles/main.css')
        );

        $this->assertStringContainsString(
            '.table-footer[data-lazy-pagination="true"][data-has-more="true"][data-lazy-loading="false"]::after',
            $styles
        );
        $this->assertStringContainsString(
            'Scroll inside the table to load more records',
            $styles
        );
    }
}
