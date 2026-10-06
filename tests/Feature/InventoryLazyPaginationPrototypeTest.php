<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryLazyPaginationPrototypeTest extends TestCase
{
    public function test_inventory_page_opts_into_lazy_scroll_pagination(): void
    {
        $view = file_get_contents(
            resource_path('views/Warehouse/inventory.blade.php')
        );

        $this->assertStringContainsString(
            'data-lazy-pagination="true"',
            $view
        );
    }

    public function test_shared_paginator_supports_lazy_scroll_loading(): void
    {
        $source = file_get_contents(
            resource_path('js/Main-js/scroll-table-pagination.js')
        );

        $this->assertStringContainsString(
            "footer.dataset.lazyPagination === 'true'",
            $source
        );
        $this->assertStringContainsString(
            "context.wrap.addEventListener('scroll'",
            $source
        );
        $this->assertStringContainsString(
            "'gct:load-all-records'",
            $source
        );
        $this->assertStringContainsString(
            'Loading more records...',
            $source
        );
        $this->assertStringNotContainsString(
            'window.requestAnimationFrame(maybeLoadNextPage);',
            $source
        );
    }

    public function test_inventory_filters_can_request_the_remaining_lazy_rows(): void
    {
        $source = file_get_contents(
            resource_path('js/Warehouse/inventory.js')
        );

        $this->assertStringContainsString(
            'function requestAllInventoryRows()',
            $source
        );
        $this->assertStringContainsString(
            "inventoryFooter.dispatchEvent(new CustomEvent('gct:load-all-records'))",
            $source
        );
    }

    public function test_inventory_source_filter_is_removed_from_the_real_inventory_view(): void
    {
        $view = file_get_contents(
            resource_path('views/Warehouse/inventory.blade.php')
        );
        $source = file_get_contents(
            resource_path('js/Warehouse/inventory.js')
        );

        $this->assertStringNotContainsString(
            'name="source"',
            $view
        );
        $this->assertStringNotContainsString(
            "querySelector('select[name=\"source\"]')",
            $source
        );
        $this->assertStringNotContainsString(
            "url.searchParams.set('source'",
            $source
        );
    }
}
