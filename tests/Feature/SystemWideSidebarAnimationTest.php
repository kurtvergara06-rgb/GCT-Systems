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
