<?php

namespace Tests\Feature\Purchase;

use Tests\TestCase;

class PurchaseUiConsistencyTest extends TestCase
{
    public function test_all_purchase_pages_load_the_shared_ui_layer(): void
    {
        $views = [
            resource_path('views/Purchase/dashboard-purchase.blade.php'),
            resource_path('views/Purchase/purchase-orders.blade.php'),
            resource_path('views/Purchase/Requested_Purchase/maintenance-requests.blade.php'),
            resource_path('views/Purchase/Requested_Purchase/inventory-restock.blade.php'),
            resource_path('views/Purchase/purchase-history.blade.php'),
            resource_path('views/Purchase/scheduled-purchase.blade.php'),
        ];

        foreach ($views as $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString(
                'resources/css/Purchase/purchase-module-ui.css',
                $source
            );
            $this->assertStringContainsString(
                'purchase-module-page',
                $source
            );
            $this->assertStringContainsString(
                'records-page',
                $source
            );
        }
    }

    public function test_shared_purchase_ui_matches_warehouse_and_maintenance_layout_contracts(): void
    {
        $css = file_get_contents(
            resource_path('css/Purchase/purchase-module-ui.css')
        );

        $this->assertStringContainsString('PURCHASE MODULE — SHARED UI CONSISTENCY', $css);
        $this->assertStringContainsString('.purchase-module-page .stats-grid', $css);
        $this->assertStringContainsString('.purchase-module-page .table-card', $css);
        $this->assertStringContainsString('.purchase-module-page .toolbar', $css);
        $this->assertStringContainsString('.purchase-module-page .table-wrap', $css);
        $this->assertStringContainsString('overflow-x: hidden;', $css);
        $this->assertStringContainsString('.purchase-module-page .action-btn', $css);
        $this->assertStringContainsString('.requested-pr-style-modal', $css);
        $this->assertStringContainsString('.restock-view-modal', $css);
        $this->assertStringContainsString('.schedule-modal', $css);
        $this->assertStringContainsString('.po-modal-box', $css);
        $this->assertStringContainsString('@media (max-width: 1180px)', $css);
        $this->assertStringContainsString('ADMIN ACCOUNT MANAGEMENT REFERENCE PASS', $css);
        $this->assertStringContainsString('.purchase-module-page.records-page .records-toolbar', $css);
        $this->assertStringContainsString('.purchase-module-page.records-page .records-table', $css);
        $this->assertStringContainsString('.purchase-module-page.records-page .record-actions', $css);
    }

    public function test_purchase_record_pages_have_consistent_section_context(): void
    {
        $expectations = [
            'views/Purchase/purchase-orders.blade.php' => 'PROCUREMENT RECORDS',
            'views/Purchase/Requested_Purchase/maintenance-requests.blade.php' => 'REQUEST INBOX',
            'views/Purchase/Requested_Purchase/inventory-restock.blade.php' => 'INVENTORY REPLENISHMENT',
            'views/Purchase/purchase-history.blade.php' => 'AUDIT ARCHIVE',
            'views/Purchase/scheduled-purchase.blade.php' => 'PROCUREMENT PLANNING',
        ];

        foreach ($expectations as $path => $eyebrow) {
            $source = file_get_contents(resource_path($path));
            $this->assertStringContainsString('purchase-section-eyebrow', $source);
            $this->assertStringContainsString($eyebrow, $source);
        }
    }

    public function test_vite_build_contains_shared_purchase_ui_asset(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString(
            "'resources/css/Purchase/purchase-module-ui.css'",
            $vite
        );
    }

    public function test_purchase_record_pages_use_admin_record_structure_classes(): void
    {
        $views = [
            resource_path('views/Purchase/purchase-orders.blade.php'),
            resource_path('views/Purchase/Requested_Purchase/maintenance-requests.blade.php'),
            resource_path('views/Purchase/Requested_Purchase/inventory-restock.blade.php'),
            resource_path('views/Purchase/purchase-history.blade.php'),
            resource_path('views/Purchase/scheduled-purchase.blade.php'),
        ];

        foreach ($views as $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString('records-card', $source);
            $this->assertStringContainsString('records-toolbar', $source);
            $this->assertStringContainsString('records-table-wrap', $source);
            $this->assertStringContainsString('records-table', $source);
            $this->assertStringContainsString('record-actions', $source);
        }
    }


    public function test_purchase_typography_matches_admin_account_management_reference(): void
    {
        $css = file_get_contents(
            resource_path('css/Purchase/purchase-module-ui.css')
        );

        $this->assertStringContainsString('font-family: "Poppins", sans-serif;', $css);
        $this->assertStringContainsString('font-size: 13px !important;', $css);
        $this->assertStringContainsString('font-size: 14px !important;', $css);
        $this->assertStringContainsString(
            '.purchase-module-page.records-page .records-table th',
            $css
        );
        $this->assertStringContainsString(
            '.purchase-module-page.records-page .records-table td',
            $css
        );
        $this->assertStringContainsString(
            '.purchase-module-page.records-page .record-actions .action-btn',
            $css
        );
    }


    public function test_purchase_order_create_and_edit_modal_match_the_approved_reference(): void
    {
        $view = file_get_contents(
            resource_path('views/Purchase/purchase-orders.blade.php')
        );
        $css = file_get_contents(
            resource_path('css/Purchase/purchase-module-ui.css')
        );
        $js = file_get_contents(
            resource_path('js/Purchase/purchase-orders.js')
        );

        foreach ([
            'poModalSubtitle',
            'po-form-section po-order-information',
            'po-form-section po-purchase-items-section',
            'name="supplier_name"',
            'po-summary-card',
            'po_subtotal_display',
            'poSaveButtonLabel',
            'poActionHint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString(
            'PURCHASE ORDER CREATE / EDIT MODAL — APPROVED MOCKUP',
            $css
        );
        $this->assertStringContainsString('#poModal .po-form-section', $css);
        $this->assertStringContainsString('#poModal .po-summary-card', $css);
        $this->assertStringContainsString('#poModal .po-modal-actions', $css);

        $this->assertStringContainsString(
            "setText('poModalTitle', 'New Purchase Order')",
            $js
        );
        $this->assertStringContainsString(
            "'Edit Purchase Order'",
            $js
        );
        $this->assertStringContainsString(
            "setText('poSaveButtonLabel', 'Save Changes')",
            $js
        );
        $this->assertStringContainsString(
            "setText('po_subtotal_display', money(total))",
            $js
        );
    }


    public function test_purchase_order_view_action_is_always_read_only_and_edit_is_separate(): void
    {
        $view = file_get_contents(
            resource_path('views/Purchase/purchase-orders.blade.php')
        );
        $js = file_get_contents(
            resource_path('js/Purchase/purchase-orders.js')
        );

        $this->assertStringContainsString(
            'type="view"',
            $view
        );
        $this->assertStringContainsString(
            'class="open-view-po-modal"',
            $view
        );
        $this->assertStringContainsString(
            'type="edit"',
            $view
        );
        $this->assertStringContainsString(
            'class="open-edit-po-modal"',
            $view
        );
        $this->assertStringContainsString(
            "\$isEditable = \$normalizedPoStatus === 'ordered';",
            $view
        );
        $this->assertStringContainsString(
            '@if($isEditable)',
            $view
        );
        $this->assertStringContainsString(
            "const isViewOnly = mode === 'view'",
            $js
        );
        $this->assertStringContainsString(
            "configureExistingForm(button, viewButton ? 'view' : 'edit')",
            $js
        );

        $this->assertStringNotContainsString(
            'GCT TRANSPORT SERVICES INC.',
            $view
        );
        $this->assertStringNotContainsString(
            '<p>PURCHASE ORDER</p>',
            $view
        );
    }


    public function test_purchase_order_view_mode_has_no_edit_footer_actions(): void
    {
        $view = file_get_contents(
            resource_path('views/Purchase/purchase-orders.blade.php')
        );
        $js = file_get_contents(
            resource_path('js/Purchase/purchase-orders.js')
        );

        $this->assertStringNotContainsString('id="poViewActions"', $view);
        $this->assertStringNotContainsString('id="closeViewPoModal"', $view);
        $this->assertStringContainsString(
            "editActions.classList.toggle('hidden', viewOnly)",
            $js
        );
        $this->assertStringContainsString(
            "viewActions.classList.add('hidden')",
            $js
        );
    }

}
