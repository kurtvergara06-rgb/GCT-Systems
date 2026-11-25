<?php

namespace Tests\Feature\Purchase;

use Tests\TestCase;

class PurchaseDarkModeFoundationTest extends TestCase
{
    public function test_purchase_pages_load_the_scoped_dark_mode_stylesheet(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('$purchaseThemePage = collect($assets)->contains(', $layout);
        $this->assertStringContainsString('data-gct-purchase-page="1"', $layout);
        $this->assertStringContainsString(
            "'resources/css/Purchase/purchase-dark-mode.css'",
            $layout
        );
        $this->assertStringContainsString(
            "'resources/css/Purchase/purchase-dark-mode.css'",
            $vite
        );
        $this->assertStringContainsString('[data-gct-purchase-page="1"]', $theme);
    }

    public function test_purchase_dark_palette_covers_records_dashboard_forms_and_dialogs(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        $this->assertStringContainsString('@media screen {', $css);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] body[data-gct-purchase-page="1"]',
            $css
        );
        foreach ([
            '--purchase-surface:', '--pd-card:', '--records-card:',
            '.purchase-spend-card', '.purchase-dashboard-card',
            '.requested-purchase-card', '.restock-card',
            '.purchase-order-card', '.records-card',
            '.requested-pr-style-modal', '.restock-view-modal',
            '.po-modal-box', '.po-status-modal', '.po-head-review-dialog',
            '.po-detail-dialog', '.purchase-module-page th',
            '.purchase-module-page tbody td', '.po-modal-actions',
            '.main select', '.main textarea', '.po-status-choice.is-selected',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('var(--gct-theme-input) !important;', $css);
        $this->assertStringContainsString('var(--gct-theme-surface) !important;', $css);
        $this->assertStringContainsString('var(--gct-theme-surface-raised) !important;', $css);
        $this->assertStringContainsString('background: #fff;', $css);
    }

    public function test_purchase_head_review_inner_cards_keep_dark_contrast(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        $this->assertStringContainsString(
            '#poHeadReviewModal :is(',
            $css
        );
        $this->assertStringContainsString('.po-head-review-meta > div', $css);
        $this->assertStringContainsString('.po-head-review-footer', $css);
        $this->assertStringContainsString('.po-jo-review-signature', $css);
        $this->assertStringContainsString('.po-head-review-meta > div::after', $css);
        $this->assertStringContainsString('var(--gct-theme-muted) !important;', $css);
    }

    public function test_purchase_order_details_dark_table_and_status_keep_readable_contrast(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $js = file_get_contents(resource_path('js/Purchase/purchase-orders.js'));

        foreach ([
            '#poDetailOverlay #poDetailItems tr',
            '#poDetailOverlay #poDetailItems td',
            '#poDetailOverlay #poDetailItems tr:hover td',
            '#poDetailOverlay .po-detail-facts #poDetailStatus',
            '#poDetailStatus[data-status-tone="success"]',
            '#poDetailStatus[data-status-tone="danger"]',
            '#poDetailOverlay .po-detail-signature-image p',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('background-color: var(--gct-theme-surface) !important;', $css);
        $this->assertStringContainsString('color: var(--gct-theme-fg) !important;', $css);
        $this->assertStringContainsString('detailStatus.dataset.statusTone', $js);
        $this->assertStringContainsString("normalizedStatus === 'rejected'", $js);
    }

    public function test_purchase_order_details_and_parts_popovers_follow_dark_theme(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        $this->assertStringContainsString('#poDetailOverlay :is(', $css);
        $this->assertStringContainsString('.po-detail-facts > div', $css);
        $this->assertStringContainsString('.po-detail-signature-image', $css);
        $this->assertStringContainsString('.po-items-floating-popover', $css);
        $this->assertStringContainsString('.requested-pr-more-panel', $css);
        $this->assertStringContainsString('.restock-more-panel', $css);
    }

    public function test_shared_requisition_detail_dialog_is_dark_without_recoloring_signature(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        $this->assertStringContainsString('.shared-pr-details-dialog', $css);
        $this->assertStringContainsString('.shared-pr-details-scrollable', $css);
        $this->assertStringContainsString('.shared-pr-parts-head', $css);
        $this->assertStringContainsString('.shared-pr-details-title-copy h2', $css);
        $this->assertStringContainsString('.shared-pr-head-signature img', $css);
    }

    public function test_purchase_theme_initializes_before_first_paint_and_navigation_loader_stays_dark(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $transitions = file_get_contents(resource_path('js/Main-js/page-transitions.js'));

        $this->assertStringContainsString(
            'gct:ui-preferences:',
            $layout
        );
        $this->assertStringContainsString(
            "document.body.dataset.gctPurchasePage === '1'",
            $transitions
        );
        $this->assertStringContainsString(
            "loader.dataset.gctLoaderTheme = document.documentElement.dataset.gctTheme === 'dark'",
            $transitions
        );
    }

    public function test_purchase_dashboard_headers_do_not_render_inset_filled_blocks(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        $this->assertStringContainsString(
            '.purchase-dashboard-page .dashboard-card-header {',
            $css
        );
        $this->assertStringContainsString(
            'background: transparent !important;',
            $css
        );
        $this->assertStringNotContainsString(
            '.dashboard-card-header, .po-items-header,',
            $css
        );
    }

    public function test_requisition_tabs_show_one_selected_state_and_empty_table_uses_dark_surface(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $view = file_get_contents(resource_path('views/Purchase/Requested_Purchase/maintenance-requests.blade.php'));

        $this->assertStringContainsString(
            '.purchase-maintenance-requests-page .requested-pr-record-tab:not(.is-active)',
            $css
        );
        $this->assertStringContainsString(
            '.purchase-maintenance-requests-page .requested-pr-record-tab.is-active',
            $css
        );
        $this->assertStringContainsString('background: #294c76 !important;', $css);
        $this->assertStringContainsString('background: transparent !important;', $css);
        $this->assertStringContainsString(
            '.purchase-maintenance-requests-page .requested-purchase-table tbody tr.gct-empty-table-row > td.gct-empty-cell',
            $css
        );
        $this->assertStringContainsString(
            '.purchase-maintenance-requests-page .requested-purchase-table thead th',
            $css
        );
        $this->assertStringContainsString(
            "record_view' => 'active'",
            $view
        );
        $this->assertStringContainsString(
            "record_view' => 'history'",
            $view
        );
        $this->assertStringContainsString(
            '$recordView === \'history\' ? \'is-active\' : \'\'',
            $view
        );
    }

    public function test_purchase_order_history_compact_table_fits_card_without_horizontal_scroll(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-orders.css'));
        $dark = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $view = file_get_contents(resource_path('views/Purchase/purchase-orders.blade.php'));

        $this->assertStringNotContainsString('min-width: 1480px !important;', $css);
        $this->assertStringContainsString('overflow-x: hidden !important;', $css);
        $this->assertStringContainsString('min-width: 0 !important;', $css);
        foreach ([
            ':nth-child(1) { width: 14% !important; }',
            ':nth-child(2) { width: 15% !important; }',
            ':nth-child(4) { width: 14% !important; }',
            ':nth-child(9) { width: 7% !important; }',
        ] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
        $this->assertStringContainsString('text-overflow: ellipsis !important;', $css);
        $this->assertStringContainsString('color: #173e73 !important;', $css);
        $this->assertStringContainsString(
            '.records-table tbody td:nth-child(1) .po-number-cell strong',
            $dark
        );
        $this->assertStringContainsString(':value="$purchaseOrder->po_no"', $view);
        $this->assertStringContainsString('class="po-reference purchase-record-id"', $view);
    }

    public function test_po_and_pr_identifier_badges_have_matching_theme_colors(): void
    {
        $light = file_get_contents(resource_path('css/Purchase/purchase-orders.css'));
        $dark = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        foreach ([$light, $dark] as $css) {
            $this->assertStringContainsString(
                '.records-table tbody td:nth-child(1) .po-number-cell strong,',
                $css
            );
            $this->assertStringContainsString(
                '.records-table tbody td:nth-child(2) .po-reference {',
                $css
            );
            $this->assertStringContainsString('border-radius: 5px !important;', $css);
        }

        $this->assertStringContainsString('background: #eaf3ff !important;', $light);
        $this->assertStringContainsString('color: #173e73 !important;', $light);
        $this->assertStringContainsString(
            'background: var(--gct-theme-surface-raised) !important;',
            $dark
        );
        $this->assertStringContainsString(
            'color: var(--gct-theme-fg) !important;',
            $dark
        );
        $this->assertStringNotContainsString(
            '/* PO ID badges are deliberately pale',
            $dark
        );
    }

    public function test_purchase_order_identifiers_wrap_fully_in_compact_badges(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-orders.css'));

        $this->assertStringContainsString(
            '/* Full PO / PR references wrap inside compact cells',
            $css
        );
        $this->assertStringContainsString('white-space: normal !important;', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere !important;', $css);
        $this->assertStringContainsString('display: inline-block !important;', $css);
        $this->assertStringContainsString('height: auto !important;', $css);
        $this->assertStringNotContainsString(
            '/* Long PO and PR references must never draw across neighboring table cells. */',
            $css
        );
    }

    public function test_purchase_order_statuses_are_rectangular_and_ids_do_not_overlap(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-orders.css'));
        $dark = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $view = file_get_contents(resource_path('views/Purchase/purchase-orders.blade.php'));

        $this->assertStringContainsString(
            'td.po-status-cell > [data-ui-component="status-badge"].po-status-box',
            $css
        );
        $this->assertStringContainsString('border-radius: 7px !important;', $css);
        $this->assertStringContainsString('min-width: 96px !important;', $css);
        $this->assertStringContainsString('text-overflow: ellipsis !important;', $css);
        $this->assertStringContainsString(
            'td.po-status-cell > [data-ui-component="status-badge"].po-status-box',
            $dark
        );
        $this->assertStringContainsString('.po-receipt-compact > strong', $dark);
        $this->assertStringContainsString(':value="$purchaseOrder->po_no"', $view);
        $this->assertStringContainsString('class="po-reference purchase-record-id"', $view);
    }

    public function test_purchase_order_toolbar_has_full_width_without_redundant_tabs(): void
    {
        $shared = file_get_contents(resource_path('css/Purchase/purchase-module-ui.css'));
        $orders = file_get_contents(resource_path('views/Purchase/purchase-orders.blade.php'));

        $this->assertStringContainsString(
            '.purchase-orders-page .purchase-order-card.records-card {',
            $shared
        );
        $this->assertStringContainsString('display: block;', $shared);
        $this->assertStringContainsString(
            '.purchase-orders-page .purchase-order-card > .po-record-controls {',
            $shared
        );
        $this->assertStringContainsString('justify-content: space-between;', $shared);
        $this->assertStringContainsString('width: 100%;', $shared);
        $this->assertStringNotContainsString(
            'class="po-record-tabs"',
            $orders
        );
        $this->assertStringNotContainsString(
            '.purchase-order-card>.po-record-controls{grid-column:2',
            $shared
        );
        $this->assertStringContainsString(
            '<div class="po-record-controls purchase-list-controls">',
            $orders
        );
    }

    public function test_all_purchase_record_tabs_share_the_reference_segmented_design(): void
    {
        $shared = file_get_contents(resource_path('css/Purchase/purchase-module-ui.css'));
        $dark = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));

        foreach ([
            '.requested-pr-record-tabs',
            '.restock-record-tabs',
            '.po-record-tabs',
            '.purchase-history-record-tabs',
            '.requested-pr-record-tab.is-active',
            '.restock-record-tab.is-active',
            '.po-record-tab.is-active',
            '.purchase-history-record-tab.is-active',
        ] as $selector) {
            $this->assertStringContainsString($selector, $shared);
            $this->assertStringContainsString($selector, $dark);
        }

        $this->assertStringContainsString('gap: 4px !important;', $shared);
        $this->assertStringContainsString('height: 35px !important;', $shared);
        $this->assertStringContainsString('border-radius: 11px !important;', $shared);
        $this->assertStringContainsString('background: #162235 !important;', $dark);
        $this->assertStringContainsString('background: #294c76 !important;', $dark);
        $this->assertStringContainsString('background: transparent !important;', $dark);
        $this->assertStringContainsString('outline: 2px solid #9dc4fa !important;', $dark);
    }

    public function test_purchase_record_navigation_and_empty_states_are_dark_across_pages(): void
    {
        $dark = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $orders = file_get_contents(resource_path('views/Purchase/purchase-orders.blade.php'));
        $restock = file_get_contents(resource_path('views/Purchase/Requested_Purchase/inventory-restock.blade.php'));
        $history = file_get_contents(resource_path('views/Purchase/purchase-history.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Purchase/PurchaseOrderController.php'));

        foreach ([
            '.inventory-restock-page .restock-record-tab:not(.is-active)',
            '.purchase-orders-page .po-record-tab:not(.is-active)',
            '.purchase-history-page .purchase-history-record-tab:not(.is-active)',
            '.inventory-restock-page .restock-record-tab.is-active',
            '.purchase-orders-page .po-record-tab.is-active',
            '.purchase-history-page .purchase-history-record-tab.is-active',
            '.purchase-orders-page .purchase-order-card > .records-table-wrap thead th',
            '.purchase-history-page .purchase-history-reference-table thead th',
            '.inventory-restock-page .restock-table thead th',
        ] as $selector) {
            $this->assertStringContainsString($selector, $dark);
        }

        $this->assertStringNotContainsString('class="po-record-tabs"', $orders);
        $this->assertStringContainsString('name="record_view" value="{{ $activeTab }}"', $orders);
        $this->assertStringContainsString('class="restock-record-tabs"', $restock);
        $this->assertStringNotContainsString('class="purchase-history-record-tabs"', $history);
        $this->assertStringContainsString('name="view" value="history"', $history);
        $this->assertStringContainsString("['Delivered', 'Picked Up', 'Rejected']", $controller);
    }

    public function test_dashboard_white_cards_and_requisition_empty_states_are_corrected(): void
    {
        $css = file_get_contents(resource_path('css/Purchase/purchase-dark-mode.css'));
        $view = file_get_contents(resource_path('views/Purchase/Requested_Purchase/maintenance-requests.blade.php'));
        $dashboard = file_get_contents(resource_path('views/Purchase/dashboard-purchase.blade.php'));

        $this->assertStringContainsString(
            '.purchase-dashboard-main.purchase-module-page.records-page .purchase-dashboard-card',
            $css
        );
        foreach ([
            '.purchase-chart-card', '.purchase-fulfillment-card', '.supplier-card',
            '.sources-card', '.recent-request-card', '.recent-order-card',
            '.quick-actions-card', '.supplier-row', '.purchase-quick-item',
            '.fulfillment-count', '.fulfillment-chart-center strong',
            '.fulfillment-chart-center span', '.source-val', '.supplier-spend strong',
            '.gct-empty-content .gct-empty-title',
            'tr.gct-empty-table-row > td.gct-empty-cell',
            '.purchase-module-page.records-page .records-table-wrap',
            '.purchase-module-page.records-page .records-table tbody tr',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('class="purchase-dashboard-card purchase-chart-card"', $dashboard);
        $this->assertStringContainsString('<x-ui.empty-row', $view);
        $this->assertStringContainsString('var(--gct-theme-fg) !important;', $css);
    }

    public function test_dashboard_charts_use_dark_axis_and_segment_contrast(): void
    {
        $js = file_get_contents(resource_path('js/Purchase/dashboard-purchase.js'));

        $this->assertStringContainsString("document.documentElement.dataset.gctTheme === 'dark'", $js);
        $this->assertStringContainsString("color: palette.tick,", $js);
        $this->assertStringContainsString("color: palette.grid,", $js);
        $this->assertStringContainsString("borderColor: palette.segmentBorder,", $js);
        $this->assertStringContainsString("[palette.emptySegment]", $js);
    }

    public function test_existing_purchase_page_assets_use_department_theme_detection(): void
    {
        foreach ([
            'Purchase/dashboard-purchase.blade.php',
            'Purchase/purchase-orders.blade.php',
            'Purchase/purchase-history.blade.php',
            'Purchase/Requested_Purchase/maintenance-requests.blade.php',
            'Purchase/Requested_Purchase/inventory-restock.blade.php',
        ] as $view) {
            $blade = file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('resources/css/Purchase/', $blade);
            $this->assertStringContainsString('<x-layout.app', $blade);
        }
    }
}
