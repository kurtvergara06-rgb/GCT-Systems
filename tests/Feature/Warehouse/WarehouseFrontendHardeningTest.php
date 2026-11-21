<?php

namespace Tests\Feature\Warehouse;

use Tests\TestCase;

class WarehouseFrontendHardeningTest extends TestCase
{
    public function test_realtime_maps_cover_warehouse_pages_and_permission_updates(): void
    {
        $echo = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString("'Warehouse:RolePermission'", $echo);
        $this->assertStringContainsString("'Warehouse:PurchaseOrder'", $echo);

        foreach (['/warehouse/dashboard', '/inventory', '/part-requests', '/warehouse/incoming-deliveries', '/warehouse/stock-movements'] as $path) {
            $this->assertStringContainsString($path, $echo);
        }
    }

    public function test_warehouse_assets_are_registered_and_filters_use_ajax_regions(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString('resources/css/Warehouse/part-requests-cleanup.css', $vite);
        $this->assertStringContainsString('resources/js/Warehouse/part-requests-cleanup.js', $vite);
        $this->assertStringContainsString('resources/js/Warehouse/dashboard-warehouse.js', $vite);

        foreach (['inventory', 'part-requests', 'incoming-deliveries', 'stock-movements'] as $view) {
            $contents = file_get_contents(resource_path("views/Warehouse/{$view}.blade.php"));
            $this->assertStringContainsString('data-ajax-region="summary"', $contents);
            $this->assertStringContainsString('data-ajax-region="records"', $contents);
            $this->assertStringContainsString('data-server-filter="true"', $contents);
        }
    }

    public function test_warehouse_modal_scripts_do_not_close_on_backdrop_click(): void
    {
        foreach (['inventory.js', 'part-requests.js'] as $script) {
            $contents = file_get_contents(resource_path("js/Warehouse/{$script}"));
            $this->assertStringNotContainsString('event.target === modal', $contents);
            $this->assertStringNotContainsString('event.target === viewPrModal', $contents);
            $this->assertStringNotContainsString('event.target === issuePartsModal', $contents);
            $this->assertStringContainsString('GCTSystemAnimations', $contents);
            $this->assertStringContainsString('GCTModalBackdrop', $contents);
        }
    }

    public function test_warehouse_read_only_modals_use_the_maintenance_form_language(): void
    {
        $inventory = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));
        $partRequests = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));
        $sharedPrDetails = file_get_contents(resource_path('views/components/ui/pr-details-modal.blade.php'));

        $this->assertStringContainsString('inventory-details-modal', $inventory);
        $this->assertStringContainsString('01 — Item Information', $inventory);
        $this->assertStringContainsString('02 — Stock Information', $inventory);
        $this->assertStringContainsString('03 — Supply &amp; Storage', $inventory);
        $this->assertStringContainsString('inventory-details-footer', $inventory);
        $this->assertStringContainsString('inventory-movement-title-icon', $inventory);
        $this->assertStringContainsString('inventory-movement-modal__footer', $inventory);

        $this->assertStringContainsString('resources/css/Main-styles/form-components.css', $partRequests);
        $this->assertStringContainsString('resources/css/Main-styles/pr-details-modal.css', $partRequests);
        $this->assertStringContainsString('<x-ui.pr-details-modal', $partRequests);
        $this->assertStringContainsString('Purchase Request Details', $sharedPrDetails);
        $this->assertStringContainsString('Parts Requiring Purchase', $sharedPrDetails);
        $this->assertStringContainsString('Maintenance Head Authorization', $sharedPrDetails);
        $this->assertStringContainsString('Warehouse Stock Reference', $sharedPrDetails);
        $this->assertStringContainsString('Remarks', $sharedPrDetails);
        $this->assertStringContainsString('shared-pr-details-footer', $sharedPrDetails);
    }

    public function test_dashboard_uses_unique_realtime_regions(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));
        preg_match_all('/data-ajax-region="([^"]+)"/', $view, $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertSame($matches[1], array_values(array_unique($matches[1])));
        $this->assertContains('recent-stock-movements', $matches[1]);
        $this->assertStringContainsString('warehouseInventoryBar', $view);
        $this->assertStringContainsString('warehouseInventoryDonut', $view);
        $this->assertStringContainsString('warehouseMovementTrend', $view);
    }

    public function test_purchase_request_dark_theme_keeps_tabs_and_row_text_readable(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));

        $this->assertStringContainsString('background:#dbeafe;color:#174ea6;border:1px solid #93c5fd;', $css);
        $this->assertStringContainsString('html[data-gct-theme="dark"] .warehouse-part-main .warehouse-part-card .warehouse-record-tab', $css);
        $this->assertMatchesRegularExpression(
            '/\\.warehouse-record-tabs\\s*\\{\\s*background:\\s*#1b2a40;\\s*border-color:\\s*#344761;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\\.warehouse-record-tab\\.is-active,.*?\\.warehouse-record-tab\\.is-active:hover\\s*\\{\\s*background:\\s*#304563;\\s*color:\\s*#ffffff;/s',
            $css
        );
        $this->assertStringContainsString('html[data-gct-theme="dark"] .warehouse-part-main .warehouse-part-card .warehouse-pr-item-name', $css);
        $this->assertStringContainsString('html[data-gct-theme="dark"] .warehouse-part-main .warehouse-part-card .qty-needed-badge', $css);
        $this->assertStringContainsString('color: #f1f5f9;', $css);
    }

    public function test_restock_pr_dark_modal_uses_readable_section_and_form_labels(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests.css'));
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));

        $this->assertStringContainsString('warehouse-restock-pr-section-title', $view);
        $this->assertStringContainsString('warehouse-restock-signature-column', $view);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] .warehouse-part-main .warehouse-restock-pr-dialog .warehouse-restock-pr-section-title strong',
            $css
        );
        $this->assertStringContainsString('color: #bfdbfe;', $css);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] .warehouse-part-main .warehouse-restock-pr-dialog .form-group label',
            $css
        );
        $this->assertStringContainsString('color: #e2e8f0;', $css);
    }

    public function test_warehouse_purchase_review_modals_have_readable_dark_surfaces_and_full_width_revision_notes(): void
    {
        $cleanup = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));
        $form = file_get_contents(resource_path('css/Warehouse/part-requests.css'));

        $this->assertStringContainsString('#warehouseSendMissingModal #sendMissingRevisionRemarks {', $cleanup);
        $this->assertStringContainsString('width: 100%;', $cleanup);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] #warehouseSendMissingModal .warehouse-pr-reference-section',
            $cleanup
        );
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] #warehouseSendMissingModal #sendMissingRevisionRemarks',
            $cleanup
        );
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] .warehouse-part-main .warehouse-restock-pr-dialog #restockPrSignatoryName[readonly]',
            $form
        );
    }

    public function test_restock_signature_upload_is_dark_and_keyboard_visible_in_dark_mode(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests.css'));

        $this->assertMatchesRegularExpression(
            '/html\\[data-gct-theme="dark"\\] \\.warehouse-part-main \\.warehouse-restock-pr-dialog \\.warehouse-restock-signature-upload\\s*\\{[^}]*background:\\s*#24364f;[^}]*color:\\s*#dbeafe;/s',
            $css
        );
        $this->assertStringContainsString(
            '.warehouse-restock-signature-upload:focus-within',
            $css
        );
        $this->assertStringContainsString('color: #b9cbe1;', $css);
    }

    public function test_send_missing_parts_modal_scrolls_instead_of_shrinking_its_sections(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));

        $this->assertStringContainsString(
            '#warehouseSendMissingModal .warehouse-modal-scrollable > * {',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#warehouseSendMissingModal \\.warehouse-modal-scrollable > \\* \\{\\s*flex:\\s*0 0 auto;/',
            $css
        );
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('id="sendMissingRevisionRemarks"', $view);
        $this->assertStringContainsString('warehouse-pr-approval-panel', $view);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] #warehouseSendMissingModal .warehouse-pr-view-table tbody td',
            $css
        );
        $this->assertStringContainsString('background: #1b2a40;', $css);
        $this->assertSame(
            1,
            substr_count($css, ':is(#viewPrModal, #warehouseSendMissingModal) .warehouse-pr-reference-view {')
        );
        $this->assertStringContainsString('width: min(940px, calc(100vw - 32px));', $css);
        $this->assertStringContainsString('margin: auto;', $css);
    }

    public function test_send_missing_purchase_review_layout_keeps_approval_and_actions_visible(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));

        $this->assertStringContainsString(
            '#warehouseSendMissingModal .warehouse-pr-head-signature img {',
            $css
        );
        $this->assertStringContainsString('max-height: 105px;', $css);
        $this->assertStringContainsString(
            '#warehouseSendMissingModal .warehouse-pr-reference-section .warehouse-modal-section-body {',
            $css
        );
        $this->assertStringContainsString(
            '#warehouseSendMissingModal .warehouse-edit-footer {',
            $css
        );
        $this->assertStringContainsString('id="sendMissingConfirm"', $view);
        $this->assertStringContainsString('id="sendMissingRevision"', $view);
    }

    public function test_send_to_purchase_button_is_compact_in_review_footer(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));

        $this->assertStringContainsString('id="sendMissingConfirm"', $view);
        $this->assertStringContainsString('warehouse-send-purchase-primary', $view);
        $this->assertStringContainsString('background: #ffbf19 !important;', $css);
        $this->assertStringContainsString('color: #102a56 !important;', $css);
        $this->assertMatchesRegularExpression(
            '/#warehouseSendMissingModal \\.warehouse-send-purchase-primary\\s*\\{[^}]*height:\\s*42px;[^}]*padding:\\s*0 16px;[^}]*font-size:\\s*12px;/s',
            $css
        );
    }

    public function test_warehouse_restock_view_modal_has_dark_contrast_and_responsive_table(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/part-requests.css'));
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));

        $this->assertStringContainsString('id="warehouseRestockViewModal"', $view);
        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] #warehouseRestockViewModal .warehouse-restock-view-dialog',
            $css
        );
        $this->assertStringContainsString('--restock-surface: #162235;', $css);
        $this->assertStringContainsString('--restock-text: #e2e8f0;', $css);
        $this->assertStringContainsString(
            '#warehouseRestockViewModal .warehouse-restock-view-table-wrap table {',
            $css
        );
        $this->assertStringContainsString('table-layout: fixed;', $css);
        $this->assertStringContainsString('min-width: 0;', $css);
    }

    public function test_warehouse_purchase_history_has_no_redundant_note_and_aligned_compact_table(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));

        $this->assertStringNotContainsString('warehouse-history-note', $view);
        $this->assertStringNotContainsString(
            'Completed and closed Warehouse requisitions are kept here',
            $view
        );
        $this->assertStringNotContainsString('.warehouse-history-note {', $css);
        $this->assertStringContainsString(
            "padding:10px 12px;vertical-align:middle;",
            $css
        );
        $this->assertStringContainsString(
            'Match PR quantity and the two status columns to their respective headings.',
            $css
        );
        $this->assertStringContainsString('td:nth-child(1){width:18%}', $css);
    }

    public function test_incoming_delivery_tabs_have_no_counts_and_empty_table_uses_consistent_surface(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/incoming-deliveries.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/incoming-deliveries.css'));
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));

        $this->assertStringContainsString('<span>Active</span>', $view);
        $this->assertStringContainsString('<span>History</span>', $view);
        $this->assertStringNotContainsString('gct-record-tab-count', $view);
        $this->assertStringContainsString(
            '.incoming-delivery-card .incoming-delivery-table .gct-empty-cell',
            $css
        );
        $this->assertStringContainsString(
            '.incoming-delivery-card .incoming-delivery-table .gct-empty-table-row',
            $dark
        );
        $this->assertStringContainsString('background-color: var(--gct-theme-surface);', $dark);
    }

    public function test_incoming_delivery_record_tabs_follow_dark_theme(): void
    {
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));

        $this->assertStringContainsString(
            'html[data-gct-theme="dark"] body[data-gct-warehouse-page="1"] .incoming-delivery-card .warehouse-record-tabs',
            $dark
        );
        $this->assertMatchesRegularExpression(
            '/\\.incoming-delivery-card \\.warehouse-record-tabs\\s*\\{\\s*background:\\s*#1b2a40;\\s*border-color:\\s*#344761;/',
            $dark
        );
        $this->assertMatchesRegularExpression(
            '/\\.incoming-delivery-card \\.warehouse-record-tab\\.is-active\\s*\\{\\s*background:\\s*#304563;\\s*color:\\s*#ffffff;/',
            $dark
        );
    }

    public function test_incoming_deliveries_dark_table_and_receipt_dialog_use_readable_colors(): void
    {
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
        $view = file_get_contents(resource_path('views/Warehouse/incoming-deliveries.blade.php'));

        $this->assertStringContainsString('id="incomingReceiptModal"', $view);
        $this->assertStringContainsString('incoming-date-stack', $view);
        $this->assertStringContainsString('po-receipt-compact', $view);
        $this->assertStringContainsString('.incoming-delivery-card .incoming-date-stack small', $dark);
        $this->assertStringContainsString('.incoming-delivery-card .po-receipt-left', $dark);
        $this->assertStringContainsString('#incomingReceiptModal .incoming-receipt-dialog', $dark);
        $this->assertStringContainsString('#incomingReceiptModal .incoming-receipt-mode-button[aria-pressed="true"]', $dark);
        $this->assertStringContainsString('#incomingReceiptModal .incoming-receipt-items td', $dark);
        $this->assertStringContainsString('#incomingReceiptModal .incoming-receipt-items input[readonly]', $dark);
        $this->assertStringContainsString('background: #162235;', $dark);
    }

    public function test_receiving_method_cards_are_clear_accessible_and_themed(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/incoming-deliveries.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/incoming-deliveries.css'));
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
        $js = file_get_contents(resource_path('js/Warehouse/incoming-deliveries.js'));

        $this->assertStringContainsString('id="incomingReceiptModeTitle"', $view);
        $this->assertStringContainsString('id="incomingReceiptModeStatus"', $view);
        $this->assertStringContainsString('aria-live="polite"', $view);
        $this->assertSame(2, substr_count($view, 'class="incoming-receipt-mode-indicator"'));
        $this->assertStringContainsString('id="incomingReceiveAll"', $view);
        $this->assertStringContainsString('id="incomingReceivePartial"', $view);
        $this->assertStringContainsString('aria-pressed="false"', $view);
        $this->assertStringContainsString('.incoming-receipt-mode-button[aria-pressed="true"] .incoming-receipt-mode-indicator', $css);
        $this->assertStringContainsString('.incoming-receipt-mode-button:focus-visible', $css);
        $this->assertStringContainsString('#incomingReceiptModal .incoming-receipt-mode-button[aria-pressed="true"] .incoming-receipt-mode-indicator', $dark);
        $this->assertStringContainsString('Selected: Receive All', $js);
        $this->assertStringContainsString('Selected: Partial Receipt', $js);
        $this->assertStringContainsString("receiptMode = null;", $js);
        $this->assertStringContainsString("submitButton.disabled = !valid;", $js);
    }

    public function test_warehouse_modal_sizing_is_centered_without_redundant_width_rules(): void
    {
        $pr = file_get_contents(resource_path('css/Warehouse/part-requests.css'));
        $cleanup = file_get_contents(resource_path('css/Warehouse/part-requests-cleanup.css'));
        $receiving = file_get_contents(resource_path('css/Warehouse/incoming-deliveries.css'));

        $this->assertSame(
            1,
            substr_count($pr, 'width: min(920px, calc(100vw - 32px));')
        );
        $this->assertStringContainsString('margin: auto;', $pr);
        $this->assertStringContainsString('justify-content: center;', $pr);
        $this->assertStringContainsString('overflow-y: auto;', $pr);
        $this->assertSame(
            1,
            substr_count($cleanup, ':is(#viewPrModal, #warehouseSendMissingModal) .warehouse-pr-reference-view {')
        );
        $this->assertStringContainsString('max-height: calc(100dvh - 34px);', $cleanup);
        $this->assertStringContainsString('justify-content: center;', $receiving);
        $this->assertStringContainsString('align-items: center;', $receiving);
    }

    public function test_warehouse_item_request_dialog_is_centered_and_has_one_final_width(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/item-requests.css'));
        $shared = file_get_contents(resource_path('css/Maintenance/item-requests.css'));

        $this->assertStringContainsString('width:min(1140px,calc(100vw - 34px));', $css);
        $this->assertStringContainsString('margin:auto;', $css);
        $this->assertStringNotContainsString('width:min(790px,calc(100vw - 32px));', $css);
        $this->assertStringNotContainsString('width:min(900px,calc(100vw - 30px));', $css);
        $this->assertStringContainsString('align-items:center;justify-content:center;', $shared);
    }

    public function test_warehouse_modal_css_uses_single_canonical_section_and_review_body_rules(): void
    {
        $purchase = file_get_contents(resource_path('css/Warehouse/part-requests.css'));
        $requests = file_get_contents(resource_path('css/Warehouse/item-requests.css'));

        $this->assertSame(1, substr_count($purchase, '.warehouse-part-main .warehouse-restock-pr-section{'));
        $this->assertSame(1, substr_count($purchase, '.warehouse-part-main .warehouse-restock-pr-section-title{'));
        $this->assertStringContainsString(
            'margin-top:10px;border:1px solid #dce7f7;border-radius:12px;padding:0;overflow:hidden',
            $purchase
        );
        $this->assertStringContainsString('margin:0 0 10px;padding:9px 13px;', $purchase);
        $this->assertMatchesRegularExpression(
            '/#warehouseItemRequestDetails \\.warehouse-ir-details-body\\s*\\{\\s*display:flex;/',
            $requests
        );
        $this->assertStringContainsString('background:#fcfdff;', $requests);
    }

    public function test_warehouse_ir_stock_table_uses_one_final_width_and_physical_stock_color(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/item-requests.css'));

        $this->assertSame(1, substr_count($css, '#warehouseItemRequestDetails .warehouse-ir-stock-table {min-width:850px}'));
        $this->assertStringNotContainsString('min-width:730px', $css);
        $this->assertStringNotContainsString('min-width:790px', $css);
        $this->assertSame(1, substr_count($css, '#warehouseItemRequestDetails .warehouse-ir-stock-physical {font-weight:900;color:#153d68;}'));
    }

    public function test_inventory_actions_use_one_canonical_layout_rule(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));

        // One base rule plus the intentional small-screen gap adjustment.
        $this->assertSame(
            2,
            substr_count($css, '.warehouse-inventory-page .inventory-table .actions{')
        );
        $this->assertStringContainsString(
            '.warehouse-inventory-page .inventory-table .actions{gap:6px}',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\\.warehouse-inventory-page \\.inventory-table \\.actions\\s*\\{[^}]*display:flex;[^}]*align-items:center;[^}]*justify-content:center;[^}]*flex-wrap:nowrap;[^}]*gap:8px;/s',
            $css
        );
    }

    public function test_warehouse_table_typography_and_item_spacing_are_not_overridden_repeatedly(): void
    {
        $ir = file_get_contents(resource_path('css/Warehouse/item-requests.css'));
        $inventory = file_get_contents(resource_path('css/Warehouse/inventory.css'));

        $this->assertStringNotContainsString(
            '#warehouseItemRequestDetails .warehouse-ir-stock-table th{font-size:10px;padding:11px}',
            $ir
        );
        $this->assertStringNotContainsString(
            '#warehouseItemRequestDetails .warehouse-ir-stock-table td{padding:12px 11px;font-size:11px}',
            $ir
        );
        $this->assertStringContainsString(
            '#warehouseItemRequestDetails .warehouse-ir-stock-table th {font-size:11px;padding:10px 11px;}',
            $ir
        );
        $this->assertStringContainsString(
            '#warehouseItemRequestDetails .warehouse-ir-stock-table td {font-size:12px;padding:10px 11px;}',
            $ir
        );
        $this->assertMatchesRegularExpression(
            '/\\.warehouse-inventory-page \\.inventory-table \\.inventory-item-identification\\s*\\{\\s*gap:5px;\\s*line-height:1\\.4;/',
            $inventory
        );
    }

    public function test_movement_history_uses_final_modal_spacing_without_superseded_values(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/inventory.css'));

        $this->assertStringContainsString(
            '#movementHistoryModal .movement-history-redesign { width:min(1060px,96vw)!important; }',
            $css
        );
        $this->assertStringContainsString(
            '#movementHistoryContent { padding:17px 22px 8px!important; }',
            $css
        );
        $this->assertStringContainsString(
            '#movementHistoryContent .item-movement-stats { gap:10px!important;margin-bottom:14px!important; }',
            $css
        );
        $this->assertStringNotContainsString('width:min(1120px,96vw)!important;', $css);
        $this->assertStringNotContainsString('padding:20px 24px 12px!important;', $css);
        $this->assertStringNotContainsString('font-size:21px!important;', $css);
        $this->assertStringContainsString(
            '@media(max-width:760px){#movementHistoryModal{padding:10px!important}',
            $css
        );
    }

    public function test_warehouse_dark_mode_covers_stock_filters_inventory_labels_and_dashboard_actions(): void
    {
        $stock = file_get_contents(resource_path('css/Warehouse/stock-movements.css'));
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));

        $this->assertStringContainsString('background: var(--sm-filter-bg, #ffffff) !important;', $stock);
        $this->assertStringNotContainsString('background: #ffffff !important;', $stock);
        $this->assertStringContainsString('--sm-filter-bg: var(--gct-theme-input);', $dark);

        foreach ([
            '.form-grid label, .details-grid label',
            '.inventory-section-basic h3, .inventory-section-details h3',
            '.inventory-section-additional h3, .inventory-details-section h3',
            '.inventory-movement-title-icon',
            '.warehouse-dashboard-page .warehouse-view-action {',
            '.warehouse-dashboard-page .warehouse-view-action:is(:hover,:focus-visible)',
        ] as $selector) {
            $this->assertStringContainsString($selector, $dark);
        }

        $this->assertStringContainsString('background: var(--gct-theme-surface-raised) !important;', $dark);
        $this->assertStringContainsString('color: var(--gct-theme-fg);', $dark);
        $this->assertStringContainsString('background: var(--gct-theme-hover) !important;', $dark);
    }

    public function test_remaining_warehouse_neutral_surfaces_follow_dark_mode(): void
    {
        $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));

        foreach ([
            '.warehouse-trend-summary > div',
            '.warehouse-usage-summary-note',
            '.warehouse-panel-period',
            '.warehouse-category-filter',
            '.issue-stock-preview',
            '.inventory-movement-modal__footer',
            '.issue-part-item-card',
            '.warehouse-issued-quantities',
            '.toolbar-count-chip',
            '> .warehouse-jo-parts-popup',
            '.warehouse-jo-parts-row strong',
            '.stock-movement-record-count',
        ] as $selector) {
            $this->assertStringContainsString($selector, $dark);
        }

        $this->assertStringContainsString(
            'background: var(--gct-theme-surface-raised);',
            $dark
        );
        $this->assertStringContainsString(
            'background: var(--gct-theme-input);',
            $dark
        );
        $this->assertStringContainsString(
            'background: var(--gct-theme-surface);',
            $dark
        );
        $this->assertStringContainsString(
            '@media print {',
            $dark
        );
    }

    public function test_warehouse_record_pages_expose_active_and_history_tabs(): void
    {
        $partRequests = file_get_contents(resource_path('views/Warehouse/part-requests.blade.php'));
        $this->assertStringContainsString('warehouse-record-tabs', $partRequests);
        $this->assertStringContainsString("'view' => 'pr'", $partRequests);
        $this->assertStringContainsString("'view' => 'history'", $partRequests);
        $this->assertStringContainsString('name="view"', $partRequests);

        $deliveries = file_get_contents(resource_path('views/Warehouse/incoming-deliveries.blade.php'));
        $this->assertStringContainsString('warehouse-record-tabs', $deliveries);
        $this->assertStringContainsString("'view' => 'active'", $deliveries);
        $this->assertStringContainsString("'view' => 'history'", $deliveries);
        $this->assertStringContainsString('name="view"', $deliveries);
        $this->assertStringContainsString('$isHistory || $received', $deliveries);
    }


    public function test_inventory_add_and_edit_modals_use_shared_form_language_and_server_submission_contracts(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));

        $this->assertStringContainsString('resources/css/Main-styles/form-components.css', $view);
        $this->assertStringContainsString('inventory-form-modal-overlay', $view);
        $this->assertStringContainsString('inventory-section-basic', $view);
        $this->assertStringContainsString('inventory-section-details', $view);
        $this->assertStringContainsString('inventory-section-additional', $view);
        $this->assertStringContainsString('method="POST"', $view);
        $this->assertStringContainsString('id="editForm"', $view);
        $this->assertStringContainsString("@method('PUT')", $view);
        $this->assertStringContainsString('data-confirm-form', $view);
        $this->assertStringNotContainsString('data-ajax-submit', $view);
        // Editing master data must not expose a manual stock adjustment field.
        // Stock changes are recorded separately through the inventory ledger.
        $this->assertStringNotContainsString('id="edit_adjustment_reason"', $view);
        $this->assertStringContainsString('id="edit_quantity"', $view);
        $this->assertStringContainsString('readonly aria-readonly="true"', $view);
        $this->assertStringContainsString('class="close-btn closeModal"', $view);
    }


    public function test_dashboard_matches_reference_layout_and_keeps_queue_previews_compact(): void
    {
        $view = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));
        $css = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));
        $js = file_get_contents(resource_path('js/Warehouse/dashboard-warehouse.js'));

        $this->assertStringContainsString('warehouse-reference-kpis', $view);
        $this->assertStringContainsString('Inventory Overview', $view);
        $this->assertStringContainsString('Stock Status by Category', $view);
        $this->assertStringContainsString('Alerts & Notifications', $view);
        $this->assertStringContainsString('warehouseCategoryFilter', $view);
        $this->assertStringContainsString('warehouse-feature-header', $view);
        $this->assertStringContainsString('warehouse-audit-table', $view);
        $this->assertStringContainsString('warehouse-item-cell', $view);
        $this->assertStringContainsString('warehouse-user-cell', $view);
        $this->assertStringContainsString('warehouse-view-action', $view);
        $this->assertStringNotContainsString('warehouse-audit-summary', $view);
        $this->assertStringNotContainsString('warehouse-usage-summary', $view);
        $this->assertStringNotContainsString('warehouse-trend-summary', $view);
        $this->assertStringContainsString('$expectedDeliveries->take(5)', $view);
        $this->assertStringContainsString('$activePartRequests->take(5)', $view);
        $this->assertSame(2, substr_count($view, 'warehouse-queue-panel'));

        $this->assertStringContainsString('overflow-x: hidden !important;', $css);
        $this->assertStringContainsString('warehouse-reference-overview', $css);
        $this->assertStringContainsString('OPERATIONAL INTELLIGENCE PANELS', $css);
        $this->assertStringContainsString('LOWER DASHBOARD REFERENCE LAYOUT', $css);
        $this->assertStringContainsString('bindInventoryCategoryFilter', $js);
        $this->assertStringContainsString('reorder: Number(statusData.reorder || 0)', $js);
        $this->assertStringContainsString("text: 'Quantity'", $js);

        $controller = file_get_contents(app_path('Http/Controllers/Warehouse/WarehouseDashboardController.php'));
        $this->assertStringContainsString("->with('creator')", $controller);
    }


    public function test_dashboard_overview_readability_scale_is_preserved(): void
    {
        $css = file_get_contents(
            resource_path('css/Warehouse/dashboard-warehouse.css')
        );
        $js = file_get_contents(
            resource_path('js/Warehouse/dashboard-warehouse.js')
        );

        $this->assertStringContainsString(
            'DASHBOARD READABILITY PASS',
            $css
        );
        $this->assertStringContainsString(
            'font-size: 16px;',
            $css
        );
        $this->assertStringContainsString(
            'font-size: 27px;',
            $css
        );
        $this->assertStringContainsString(
            'min-height: 44px;',
            $css
        );
        $this->assertStringContainsString(
            "font: { size: 12.5, weight: '600'",
            $js
        );
        $this->assertStringContainsString(
            "font: { size: 11.5, family:",
            $js
        );
    }

    public function test_dashboard_kpis_use_balanced_three_column_rows(): void
    {
        $css = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));

        $this->assertStringContainsString(
            "grid-template-columns: repeat(3, minmax(0, 1fr));",
            $css
        );
    }


    public function test_part_requests_skip_duplicate_warehouse_approval_actions(): void
    {
        $view = file_get_contents(
            resource_path('views/Warehouse/part-requests.blade.php')
        ).file_get_contents(
            resource_path('views/Warehouse/part-request-table-body.blade.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/Warehouse/WarehousePartRequestController.php')
        );

        $this->assertStringContainsString(
            'Authorized Requests',
            $view
        );
        $this->assertStringContainsString(
            'Approved Job Orders ready for Warehouse',
            $view
        );
        $this->assertStringContainsString(
            'Ready to Issue',
            $view
        );
        $this->assertStringNotContainsString(
            'Approve Part Issuance?',
            $view
        );
        $this->assertStringNotContainsString(
            'Reject or Hold Release',
            $view
        );
        $this->assertStringNotContainsString(
            'view_approved_by',
            $view
        );
        $this->assertStringContainsString(
            "'Ready to Issue'",
            $controller
        );
        $this->assertStringContainsString(
            '$purchaseRequest->can_prepare = false;',
            $controller
        );
        $this->assertStringNotContainsString(
            'Warehouse approval is required before preparation.',
            $controller
        );
        $this->assertStringNotContainsString(
            "route('part-requests.prepare'",
            $view
        );
        $this->assertStringNotContainsString(
            'Prepare Parts?',
            $view
        );
    }


    public function test_dashboard_lower_cards_are_equal_height_and_have_no_horizontal_scroll(): void
    {
        $css = file_get_contents(
            resource_path('css/Warehouse/dashboard-warehouse.css')
        );

        $this->assertStringContainsString(
            'LOWER DASHBOARD ALIGNMENT',
            $css
        );
        $this->assertStringContainsString(
            '[data-ajax-region="recent-stock-movements"] .warehouse-table-scroll',
            $css
        );
        $this->assertStringContainsString(
            '[data-ajax-region="dashboard-low-stock"] .warehouse-table-scroll',
            $css
        );
        $this->assertStringContainsString(
            'overflow-x: hidden !important;',
            $css
        );
        $this->assertStringContainsString(
            '.warehouse-bottom-grid > .warehouse-panel',
            $css
        );
        $this->assertStringContainsString(
            'min-height: 440px;',
            $css
        );
        $this->assertStringContainsString(
            'height: 440px;',
            $css
        );
        $this->assertStringContainsString(
            '[data-ajax-region="dashboard-movement-trend"] .trend-chart-wrap',
            $css
        );
    }

    public function test_warehouse_modals_use_shared_centering_and_item_request_uses_id_component(): void
    {
        $sharedCss = file_get_contents(resource_path('css/Warehouse/warehouse-status.css'));
        $partCss = file_get_contents(resource_path('css/Warehouse/part-requests.css'));
        $itemRequests = file_get_contents(resource_path('views/Warehouse/item-requests.blade.php'));

        $this->assertStringContainsString('Warehouse modal overlay contract', $sharedCss);
        $this->assertStringContainsString('justify-content: center;', $sharedCss);
        $this->assertStringContainsString('.warehouse-view-overlay:is(.show, .active)', $sharedCss);
        $this->assertStringNotContainsString(".warehouse-view-overlay {\n  position: fixed;", $partCss);
        $this->assertStringContainsString('<x-ui.id-badge', $itemRequests);
        $this->assertStringContainsString('warehouse-ir-id-badge', $itemRequests);
    }

}
