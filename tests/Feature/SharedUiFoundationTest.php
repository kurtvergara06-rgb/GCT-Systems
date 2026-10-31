<?php

use App\Support\StatusBadge;
use Illuminate\Support\Facades\Blade;

test('shared ui foundation exposes the canonical typography and sizing tokens', function () {
    $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));

    expect($theme)
        ->toContain("--gct-font-family: 'Poppins', Arial, sans-serif;")
        ->toContain('--gct-status-min-height: 23px;')
        ->toContain('--gct-table-action-size: 32px;')
        ->toContain('--gct-button-height: 40px;');
});

test('shared status badge uses the support mapping and canonical css contract', function () {
    $html = Blade::render('<x-ui.status-badge status="For Review" />');
    $mainCss = file_get_contents(resource_path('css/Main-styles/main.css'));
    $sharedCss = file_get_contents(resource_path('css/Main-styles/shared-ui-enhancements.css'));

    expect(StatusBadge::resolve('For Review')['tone'])->toBe('info')
        ->and($html)->toContain('data-status="info"')
        ->and($html)->toContain('data-status-key="for-review"')
        ->and($sharedCss)->toContain('Canonical shared status-badge contract')
        ->and($mainCss)->not->toContain('span[data-ui-component="status-badge"]');
});

test('table action remains icon only by default and supports labeled workflow actions', function () {
    $legacy = Blade::render('<x-ui.table-action variant="view" />');
    $workflow = Blade::render('<x-ui.table-action variant="approve" label="Approve Job Order" show-label />');
    $link = Blade::render('<x-ui.table-action variant="create-po" label="Create PO" show-label href="/purchase-orders/create" />');

    expect($legacy)
        ->toContain('table-action-btn--icon')
        ->toContain('data-action="view"')
        ->not->toContain('<span>View</span>')
        ->and($workflow)
        ->toContain('table-action-btn--labeled')
        ->toContain('data-action="approve"')
        ->toContain('<span>Approve Job Order</span>')
        ->and($link)
        ->toContain('href="/purchase-orders/create"')
        ->toContain('data-action="create-po"')
        ->toContain('<span>Create PO</span>');
});

test('disabled table action does not render as an active link', function () {
    $html = Blade::render('<x-ui.table-action variant="receive" href="/deliveries/1" disabled show-label />');

    expect($html)
        ->toContain('<button')
        ->toContain('disabled')
        ->not->toContain('href="/deliveries/1"');
});

test('warehouse pages load one scoped status typography contract', function () {
    $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
    $warehouseCss = file_get_contents(resource_path('css/Warehouse/warehouse-status.css'));

    expect($layout)
        ->toContain('resources/css/Warehouse/warehouse-status.css')
        ->and($warehouseCss)
        ->toContain('#appSidebar[data-gct-shell="warehouse"]')
        ->toContain('font-size: 11px;')
        ->toContain('font-weight: 800;')
        ->toContain('height: 27px;')
        ->toContain('border-radius: 7px;')
        ->not->toContain('!important');
});
