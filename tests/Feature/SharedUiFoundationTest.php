<?php

use App\Support\StatusBadge;
use Illuminate\Support\Facades\Blade;

test('shared ui foundation exposes the canonical typography and sizing tokens', function () {
    $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));

    expect($theme)
        ->toContain("--gct-font-family: 'Poppins', Arial, sans-serif;")
        ->toContain('--gct-status-min-height: 34px;')
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
        ->toContain('height: 34px;')
        ->toContain('border-radius: 8px;')
        // Scoped overrides intentionally beat legacy page-level declarations.
        ->toContain('height: 34px !important;');
});

test('shared record actions render accessible titles and disabled semantics safely', function () {
    $view = Blade::render('<x-ui.action-button type="view" title="View Bus" aria-label="View bus BUS-101" data-bus-no="BUS-101" />');
    $disabled = Blade::render('<x-ui.action-button type="edit" title="Locked: Under Maintenance" :disabled="true" />');

    expect($view)
        ->toContain('data-ui-component="action-button"')
        ->toContain('title="View Bus"')
        ->toContain('aria-label="View bus BUS-101"')
        ->toContain('data-bus-no="BUS-101"')
        ->not->toContain('title=&quot;')
        ->and($disabled)
        ->toContain('disabled')
        ->toContain('aria-disabled="true"')
        ->toContain('title="Locked: Under Maintenance"');
});

test('operation record tables reuse shared actions while retaining page event hooks', function () {
    $paths = [
        'Shuttle_Bus_Management/bus-master-list.blade.php' => ['open-view-bus', 'open-edit-bus'],
        'Personnel Management/driver_master_list.blade.php' => ['data-personnel-action="view"', 'data-personnel-action="edit"'],
        'Incidents/index.blade.php' => ['data-incident-workflow-open', 'data-incident-modal-action="edit"'],
        'Scheduling_And_Dispatch/trip-schedule.blade.php' => ['cancel-service-trip', 'data-cancel-url'],
    ];

    foreach ($paths as $path => $hooks) {
        $source = file_get_contents(resource_path('views/Operation/'.$path));
        expect($source)->toContain('<x-ui.action-button');
        foreach ($hooks as $hook) {
            expect($source)->toContain($hook);
        }
    }
});

test('operation DDR and Trip Records share labeled view controls with intact modal hooks', function () {
    $ddr = file_get_contents(resource_path('views/Operation/Daily_Driver_Reports/index.blade.php'));
    $trips = file_get_contents(resource_path('views/Operation/Trip_Records/trip-records.blade.php'));
    $assignmentHistory = file_get_contents(resource_path('views/Operation/Trip_Records/assignment-history.blade.php'));
    $ddrCss = file_get_contents(resource_path('css/Operation/Daily_Driver_Reports/daily-driver-reports.css'));
    $tripCss = file_get_contents(resource_path('css/Operation/Trip_Records/trip-records.css'));

    foreach ([$ddr, $trips] as $view) {
        expect($view)->toContain('<x-ui.table-action')
            ->toContain('variant="view"')
            ->toContain('show-label')
            ->toContain('label="View"')
            ->toContain('scope="col"');
    }

    expect($ddr)->toContain('class="ddr-view-action ddr-open-modal"')
        ->toContain('data-ddr-modal="ddrView-')
        ->toContain('<x-ui.table-footer :items="$reports"')
        ->and($trips)->toContain('class="trip-history-view-action view-trip-btn"')
        ->toContain(':data-trip="$tripDataJson"')
        ->toContain('<x-ui.table-footer :items="$trips"')
        ->and($assignmentHistory)->toContain('<th scope="col">Trip ID</th>')
        ->toContain('<x-ui.table-footer :items="$assignmentHistoryTrips"')
        ->and($ddrCss)->toContain('button.table-action-btn.ddr-view-action')
        ->and($tripCss)->toContain('button.table-action-btn.trip-history-view-action');
});

test('operation Routes and Bus Availability use shared table controls without losing page hooks', function () {
    $routes = file_get_contents(resource_path('views/Operation/Routes/routes-stops.blade.php'));
    $availability = file_get_contents(resource_path('views/Operation/Shuttle_Bus_Management/bus-availability.blade.php'));
    $routesJs = file_get_contents(resource_path('js/Operation/Routes/routes-stops.js'));
    $routeCss = file_get_contents(resource_path('css/Operation/Routes/routes-stops.css'));
    $availabilityCss = file_get_contents(resource_path('css/Operation/Shuttle_Bus_Management/bus-availability.css'));

    expect(substr_count($routes, '<x-ui.action-button'))->toBeGreaterThanOrEqual(4)
        ->and($routes)->toContain('class="route-action open-route-details"')
        ->toContain('class="route-action edit-route-btn"')
        ->toContain(':data-route-geometry="json_encode(')
        ->toContain('data-stops="{{ $stopsJson }}"')
        ->toContain('@if($canManageRoutes)')
        ->toContain('@if($canApproveRouteChanges)')
        ->toContain('<th scope="col">Route ID</th>')
        ->toContain('<x-ui.empty-row')
        ->toContain('<x-ui.table-footer')
        ->and($routesJs)->toContain("'.open-route-details'")
        ->toContain("'.edit-route-btn'")
        ->and($routeCss)->toContain('.gct-record-action.route-action')
        ->and($availability)->toContain('<x-ui.action-button type="view"')
        ->toContain('gct-responsive-records gct-sticky-actions')
        ->toContain('<th scope="col">Bus ID (Plate Number)</th>')
        ->toContain('data-availability="{{ $readiness }}"')
        ->toContain('<x-ui.empty-row')
        ->and($availabilityCss)->toContain('.availability-table-wrap.gct-responsive-records');
});
