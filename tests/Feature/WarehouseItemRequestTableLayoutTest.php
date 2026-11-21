<?php

use Illuminate\Support\Facades\File;

it('keeps Warehouse Item Requests as a readable seven-column table', function (): void {
    $blade = File::get(resource_path('views/Warehouse/item-requests.blade.php'));
    $css = File::get(resource_path('css/Warehouse/item-requests.css'));
    $dark = File::get(resource_path('css/Warehouse/warehouse-dark-mode.css'));

    expect($blade)
        ->toContain('<th>IR ID</th><th>ITEM</th><th>QTY</th><th>FULFILLMENT</th><th>POLICY</th><th>STATUS</th><th>ACTIONS</th>')
        ->toContain('class="ir-table-wrap gct-responsive-records"')
        ->not->toContain('class="ir-table-wrap gct-responsive-records gct-sticky-actions"')
        ->toContain('class="warehouse-ir-row-actions"')
        ->toContain('class="warehouse-ir-labeled-view"')
        ->toContain('data-ir-view')
        ->toContain('data-ir-prepare')
        ->toContain('warehouse-ir-fulfillment')
        ->not->toContain('warehouse-ir-fulfillment-track')
        ->not->toContain('$fulfillmentPercent')
        ->toContain('warehouse-ir-jo-tag')
        ->not->toContain('class="warehouse-ir-labeled-view" style=');

    expect($css)
        ->toContain('Seven-column Warehouse IR list')
        ->toContain(':is(th,td):nth-child(7) {width:9%;text-align:center;min-width:0}')
        ->toContain('--wh-ir-progress-ink')
        ->not->toContain('.warehouse-ir-fulfillment-track')
        ->toContain('--wh-ir-jo-ink')
        ->toContain('.warehouse-ir-row-actions')
        ->toContain('--wh-ir-row-alt')
        ->toContain('tbody tr:nth-child(odd) > td')
        ->toContain('tbody tr:nth-child(even) > td')
        ->not->toContain('.ir-table-wrap.gct-sticky-actions')
        ->toContain('min-width:0;')
        ->toContain('@media (max-width:1100px)')
        ->not->toContain('.warehouse-ir-page .ir-table th:nth-child(8)');

    expect($dark)
        ->toContain('--wh-ir-jo-ink: #ccddf5')
        ->toContain('--wh-ir-progress-ink: #e6f0ff')
        ->toContain('--wh-ir-row: #17253a')
        ->toContain('--wh-ir-row-alt: #20334a');
});


it('keeps warehouse Item Request review and SWIS readable in dark mode without changing print ink', function (): void {
    $dark = File::get(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $view = File::get(resource_path('views/Warehouse/item-requests.blade.php'));

    expect($view)
        ->toContain('id="warehouseItemRequestDetails"')
        ->toContain('id="warehouseIrSlipModal"')
        ->toContain('id="warehouseIrSignedIssueForm"')
        ->toContain('id="warehouse-ir-stock-rows"')
        ->toContain('id="ir-slip-lines"');

    expect($dark)
        ->toContain('@media screen {')
        ->toContain(':is(#warehouseItemRequestDetails, #warehouseIrSlipModal)')
        ->toContain('--wh-ir-dialog-ink: #eef5ff')
        ->toContain('--wh-ir-dialog-muted: #bed0e6')
        ->toContain('.warehouse-ir-stock-table tbody tr:nth-child(even) td')
        ->toContain('.warehouse-ir-slip-signatures span')
        ->toContain('.warehouse-ir-slip-meta-line strong')
        ->toContain('@media print {')
        ->toContain('background: #fff !important; color: #111 !important;');
});
