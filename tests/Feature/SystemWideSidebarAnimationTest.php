<?php

use Illuminate\Support\Facades\Blade;

it('uses one shared sidebar animation owner for every department shell', function (): void {
    $sharedSidebar = file_get_contents(resource_path('js/Main-js/sidebar.js'));
    $maintenance = file_get_contents(resource_path('js/Maintenance/maintenance-animations.js'));

    expect($sharedSidebar)
        ->toContain("import gsap from 'gsap';")
        ->toContain('animateSidebarActiveItem')
        ->toContain('animateSidebarShellState')
        ->toContain("document.addEventListener('pointerover'")
        ->toContain(":scope > i:not(.dropdown-arrow), :scope > span")
        ->toContain("window.addEventListener('gct:navigation-ready'")
        ->not->toContain("gctShell !== 'maintenance'");

    expect($maintenance)
        ->not->toContain('animateSidebarActiveItem')
        ->not->toContain('animateSidebarShellState')
        ->not->toContain("'#appSidebar .menu-item");
});

it('renders the same animated sidebar contract for every application module', function (string $department): void {
    $html = Blade::render('<x-layout.sidebar :department="$department" />', [
        'department' => $department,
    ]);

    expect($html)
        ->toContain('id="appSidebar"')
        ->toContain('id="sidebarCollapseBtn"')
        ->toContain('class="menu')
        ->toContain('data-gct-shell="'.strtolower($department).'"');
})->with([
    'Administration' => 'Admin',
    'Operation' => 'Operation',
    'Maintenance' => 'Maintenance',
    'Warehouse' => 'Warehouse',
    'Purchase' => 'Purchase',
]);

it('themes the shared account menu in every module without changing the sidebar shell', function (): void {
    $css = file_get_contents(resource_path('css/Main-styles/sidebar.css'));
    $sidebar = file_get_contents(resource_path('views/components/layout/sidebar.blade.php'));

    expect($css)
        ->toContain('html:not([data-gct-theme="dark"]) #appSidebar .sidebar-profile-menu')
        ->toContain('html[data-gct-theme="dark"] #appSidebar .sidebar-profile-menu')
        ->toContain('html:not([data-gct-theme="dark"]) #appSidebar .profile-menu-item.logout')
        ->toContain('html:not([data-gct-theme="dark"]) #appSidebar .profile-menu-divider');

    expect($sidebar)
        ->toContain('id="sidebarProfileMenu"')
        ->toContain('class="profile-menu-item"')
        ->toContain('data-account-page=');
});


it('loads the warehouse dark appearance bridge through the shared layout', function (): void {
    $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
    $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
    $warehouseCss = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $vite = file_get_contents(base_path('vite.config.js'));

    expect($layout)
        ->toContain('data-gct-warehouse-page="1"')
        ->toContain("'resources/css/Warehouse/warehouse-dark-mode.css'")
        ->toContain('$warehouseThemePage || $purchaseThemePage || $adminThemePage || $accountThemePage');

    expect($theme)->toContain('[data-gct-warehouse-page="1"]');
    expect($warehouseCss)
        ->toContain('html[data-gct-theme="dark"] body[data-gct-warehouse-page="1"]')
        ->toContain('@media print')
        ->not->toContain('html:not([data-gct-theme="dark"])');
    expect($vite)->toContain("'resources/css/Warehouse/warehouse-dark-mode.css'");
});


it('keeps Warehouse dark navigation loading and partial transitions theme-aware', function (): void {
    $transitions = file_get_contents(resource_path('js/Main-js/page-transitions.js'));
    $navigation = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
    $css = file_get_contents(resource_path('css/Main-styles/page-transitions.css'));

    expect($transitions)
        ->toContain("document.body.dataset.gctWarehousePage === '1'")
        ->toContain("document.documentElement.dataset.gctTheme === 'dark'")
        ->toContain("loader.dataset.gctLoaderTheme");
    expect($navigation)
        ->toContain("'gctOperationPage', 'gctMaintenancePage', 'gctWarehousePage'");
    expect($css)
        ->toContain('.gct-navigation-loader[data-gct-loader-theme="dark"]');
});


it('keeps Warehouse dashboard dark canvas and readable chart labels', function (): void {
    $dashboard = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));
    $darkCss = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $charts = file_get_contents(resource_path('js/Warehouse/dashboard-warehouse.js'));
    $preferences = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

    expect($dashboard)->toContain('radial-gradient(circle at 48% 0%');
    expect($darkCss)
        ->toContain('.warehouse-dashboard-main {')
        ->toContain('background: var(--gct-theme-canvas);')
        ->toContain('.warehouse-reference-title h2')
        ->toContain('.warehouse-reference-legend strong em');
    expect($charts)
        ->toContain("document.documentElement.dataset.gctTheme === 'dark'")
        ->toContain("text: '#cbd5e1'")
        ->toContain('color: axisText')
        ->toContain('warehouseThemeObserver');
    expect($preferences)->toContain("document.body.dataset.gctWarehousePage === '1'");
});


it('keeps Warehouse stock audit rows dark without affecting queue previews', function (): void {
    $css = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $dashboard = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));

    expect($css)
        ->toContain('.warehouse-audit-panel .warehouse-audit-table tbody td')
        ->toContain('.warehouse-audit-panel .warehouse-audit-table tbody tr:nth-child(even) td')
        ->toContain('.warehouse-audit-reference,')
        ->toContain('var(--wh-dashboard-row) !important');

    expect($dashboard)->toContain('data-ajax-region="recent-stock-movements"');
});


it('styles warehouse dashboard alerts as compact accessible dark cards', function (): void {
    $css = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $dashboard = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));

    expect($css)
        ->toContain('.warehouse-reference-overview > .warehouse-reference-alerts')
        ->toContain('grid-template-columns: 36px minmax(0, 1fr) auto 12px')
        ->toContain('.warehouse-alert-row:is(:hover,:focus-visible)')
        ->toContain('.warehouse-alert-copy strong')
        ->toContain('.warehouse-alert-row time')
        ->toContain('@media (max-width: 640px)');

    expect($dashboard)
        ->toContain('data-ajax-region="warehouse-alerts"')
        ->toContain('class="warehouse-alert-row"')
        ->toContain('{{ $alert[\'url\'] }}');
});


it('renders simple queue empty panels instead of nested empty tables', function (): void {
    $styles = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));
    $darkCss = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $dashboard = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));

    expect($dashboard)
        ->toContain('@if($expectedDeliveries->isNotEmpty())')
        ->toContain('@if($activePartRequests->isNotEmpty())')
        ->toContain('@foreach($expectedDeliveries->take(5) as $delivery)')
        ->toContain('@foreach($activePartRequests->take(5) as $request)')
        ->toContain('class="warehouse-queue-empty" role="status"')
        ->toContain('No Incoming Deliveries')
        ->toContain('No Active Part Requests')
        ->not->toContain('<x-ui.empty-row');

    expect($styles)
        ->toContain('.warehouse-queue-empty {')
        ->toContain('.warehouse-queue-empty-icon {')
        ->toContain('.warehouse-queue-empty p {');

    expect($darkCss)
        ->toContain('--wh-queue-empty-heading')
        ->not->toContain('Consistent empty preview design for both Warehouse dashboard queues')
        ->not->toContain('tbody td.gct-empty-cell');
});


it('keeps stock movement audit columns readable without legacy width overrides', function (): void {
    $css = file_get_contents(resource_path('css/Warehouse/dashboard-warehouse.css'));
    $darkCss = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $view = file_get_contents(resource_path('views/Warehouse/dashboard-warehouse.blade.php'));

    expect($css)
        ->toContain('Single source for all eight audit columns')
        ->toContain('.warehouse-audit-table {')
        ->toContain('min-width: 0;')
        ->toContain('overflow-x: hidden;')
        ->toContain('min-width:72px!important')
        ->toContain('Stock audit cards at tablet/mobile widths')
        ->toContain('@media (max-width: 1180px)')
        ->toContain('@media (max-width: 690px)')
        ->toContain('var(--wh-stock-in-bg,#e5f7eb)')
        ->not->toContain('min-width: 1160px')
        ->not->toContain('Full-width audit table should never require horizontal scrolling');

    expect($darkCss)
        ->toContain('--wh-stock-in-bg: #173e34')
        ->toContain('--wh-stock-out-bg: #472a38')
        ->toContain('--wh-adjustment-bg: #213e62');

    expect($view)
        ->toContain('warehouse-dashboard-table warehouse-audit-table')
        ->toContain('class="warehouse-view-action"');
});


it('keeps Warehouse Inventory rows and Add Item modal on a consistent theme palette', function (): void {
    $styles = file_get_contents(resource_path('css/Warehouse/inventory.css'));
    $dark = file_get_contents(resource_path('css/Warehouse/warehouse-dark-mode.css'));
    $view = file_get_contents(resource_path('views/Warehouse/inventory.blade.php'));

    expect($styles)
        ->toContain('background: var(--wh-inventory-row, #ffffff)')
        ->toContain('background: var(--wh-inventory-row-alt, #fbfdff)')
        ->toContain('background: var(--wh-inventory-alert-row, transparent) !important')
        ->toContain('background:var(--wh-inventory-section,#fff)')
        ->toContain('background:var(--wh-inventory-section-header,linear-gradient(')
        ->toContain('background:var(--wh-inventory-footer,#fff)')
        ->toContain('.inventory-add-header .close-btn')
        ->toContain('.modal-actions .cancel-btn');

    expect($dark)
        ->toContain('.warehouse-inventory-page {')
        ->toContain('--wh-inventory-row: #18283d')
        ->toContain('--wh-inventory-section: #1b2b40')
        ->toContain('--wh-inventory-footer: #18283d');

    expect($view)
        ->toContain('class="inventory-table"')
        ->toContain('id="addModal"')
        ->toContain('inventory-add-dialog')
        ->toContain('data-confirm-form');
});

it('keeps the sidebar mounted during compatible page navigation with safe fallback', function (): void {
    $navigation = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
    $transitions = file_get_contents(resource_path('js/Main-js/page-transitions.js'));

    expect($navigation)
        ->toContain('const isSidebarLink = Boolean(link.closest(\'#appSidebar\'))')
        ->toContain("document.querySelector('meta[name=\"gct-partial-navigation\"]')?.content !== 'enabled'")
        ->toContain('currentMain.replaceWith(nextMain)')
        ->toContain('syncSidebarState(nextDocument)')
        ->toContain('if (!isCompatibleDocument(nextDocument))')
        ->toContain("parsed.pathname === '/operation/trip-schedule'")
        ->toContain("link.hasAttribute('data-no-partial-navigation')");
    expect($transitions)
        ->toContain("const sidebar = document.getElementById('appSidebar')")
        ->toContain("loader.style.setProperty('--gct-loader-left'");
});
